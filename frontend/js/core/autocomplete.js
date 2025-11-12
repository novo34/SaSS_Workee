/**
 * Sistema de Autocompletado Inteligente
 *
 * Autocompletado para búsquedas y formularios
 *
 * @example
 * const autocomplete = new Autocomplete(inputElement, {
 *   source: async (query) => {
 *     const response = await API.get('/api/search', { q: query });
 *     return response.data;
 *   },
 *   minLength: 2,
 *   displayKey: 'name'
 * });
 */

class Autocomplete {
  constructor(input, options = {}) {
    this.input =
      typeof input === "string" ? document.querySelector(input) : input;

    if (!this.input) {
      console.error("Autocomplete: Input no encontrado");
      return;
    }

    this.options = {
      source: options.source || (() => []),
      minLength: options.minLength || 2,
      displayKey: options.displayKey || "name",
      valueKey: options.valueKey || "id",
      delay: options.delay || 300,
      limit: options.limit || 10,
      onSelect: options.onSelect || null,
      ...options,
    };

    this.dropdown = null;
    this.selectedIndex = -1;
    this.items = [];
    this.debounceTimeout = null;

    this.init();
  }

  init() {
    // Crear contenedor de dropdown
    this.dropdown = document.createElement("div");
    this.dropdown.className = "autocomplete-dropdown";
    this.dropdown.setAttribute("role", "listbox");
    this.input.parentElement.style.position = "relative";
    this.input.parentElement.appendChild(this.dropdown);

    // Event listeners
    this.input.addEventListener("input", (e) => this.handleInput(e));
    this.input.addEventListener("keydown", (e) => this.handleKeyDown(e));
    this.input.addEventListener("focus", () => {
      if (this.items.length > 0) {
        this.showDropdown();
      }
    });

    // Cerrar al hacer clic fuera
    document.addEventListener("click", (e) => {
      if (!this.input.contains(e.target) && !this.dropdown.contains(e.target)) {
        this.hideDropdown();
      }
    });
  }

  /**
   * Maneja el evento input
   */
  handleInput(e) {
    const query = e.target.value.trim();

    clearTimeout(this.debounceTimeout);

    if (query.length < this.options.minLength) {
      this.hideDropdown();
      return;
    }

    this.debounceTimeout = setTimeout(() => {
      this.search(query);
    }, this.options.delay);
  }

  /**
   * Busca resultados
   */
  async search(query) {
    try {
      const results = await this.options.source(query);
      this.items = Array.isArray(results)
        ? results.slice(0, this.options.limit)
        : [];
      this.renderDropdown();
    } catch (error) {
      console.error("Error en autocompletado:", error);
      this.items = [];
      this.hideDropdown();
    }
  }

  /**
   * Renderiza el dropdown
   */
  renderDropdown() {
    if (this.items.length === 0) {
      this.hideDropdown();
      return;
    }

    this.dropdown.innerHTML = this.items
      .map((item, index) => {
        const display =
          typeof item === "string" ? item : item[this.options.displayKey];
        const value =
          typeof item === "string" ? item : item[this.options.valueKey];
        return `
                <div 
                    class="autocomplete-item ${
                      index === this.selectedIndex
                        ? "autocomplete-item--selected"
                        : ""
                    }"
                    data-index="${index}"
                    data-value="${this.escapeHtml(String(value))}"
                    role="option"
                >
                    ${this.escapeHtml(String(display))}
                </div>
            `;
      })
      .join("");

    this.showDropdown();

    // Event listeners para items
    this.dropdown
      .querySelectorAll(".autocomplete-item")
      .forEach((item, index) => {
        item.addEventListener("click", () => this.selectItem(index));
        item.addEventListener("mouseenter", () => this.highlightItem(index));
      });
  }

  /**
   * Muestra el dropdown
   */
  showDropdown() {
    this.dropdown.classList.add("autocomplete-dropdown--show");
    this.updatePosition();
  }

  /**
   * Oculta el dropdown
   */
  hideDropdown() {
    this.dropdown.classList.remove("autocomplete-dropdown--show");
    this.selectedIndex = -1;
  }

  /**
   * Actualiza la posición del dropdown
   */
  updatePosition() {
    const rect = this.input.getBoundingClientRect();
    this.dropdown.style.top = `${rect.bottom + window.scrollY}px`;
    this.dropdown.style.left = `${rect.left + window.scrollX}px`;
    this.dropdown.style.width = `${rect.width}px`;
  }

  /**
   * Maneja teclas del teclado
   */
  handleKeyDown(e) {
    if (!this.dropdown.classList.contains("autocomplete-dropdown--show")) {
      return;
    }

    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        this.selectedIndex = Math.min(
          this.selectedIndex + 1,
          this.items.length - 1
        );
        this.highlightItem(this.selectedIndex);
        break;

      case "ArrowUp":
        e.preventDefault();
        this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
        if (this.selectedIndex >= 0) {
          this.highlightItem(this.selectedIndex);
        } else {
          this.clearHighlight();
        }
        break;

      case "Enter":
        e.preventDefault();
        if (this.selectedIndex >= 0) {
          this.selectItem(this.selectedIndex);
        }
        break;

      case "Escape":
        this.hideDropdown();
        break;
    }
  }

  /**
   * Resalta un item
   */
  highlightItem(index) {
    this.selectedIndex = index;
    this.dropdown.querySelectorAll(".autocomplete-item").forEach((item, i) => {
      item.classList.toggle("autocomplete-item--selected", i === index);
    });

    // Scroll al item seleccionado
    const selectedItem = this.dropdown.querySelector(`[data-index="${index}"]`);
    if (selectedItem) {
      selectedItem.scrollIntoView({ block: "nearest", behavior: "smooth" });
    }
  }

  /**
   * Limpia el resaltado
   */
  clearHighlight() {
    this.dropdown.querySelectorAll(".autocomplete-item").forEach((item) => {
      item.classList.remove("autocomplete-item--selected");
    });
  }

  /**
   * Selecciona un item
   */
  selectItem(index) {
    if (index < 0 || index >= this.items.length) {
      return;
    }

    const item = this.items[index];
    const value = typeof item === "string" ? item : item[this.options.valueKey];
    const display =
      typeof item === "string" ? item : item[this.options.displayKey];

    this.input.value = display;
    this.hideDropdown();

    if (this.options.onSelect) {
      this.options.onSelect(item, value, display);
    }

    // Disparar evento personalizado
    this.input.dispatchEvent(
      new CustomEvent("autocomplete-select", {
        detail: { item, value, display },
      })
    );
  }

  /**
   * Destruye el autocompletado
   */
  destroy() {
    if (this.dropdown) {
      this.dropdown.remove();
      this.dropdown = null;
    }
    clearTimeout(this.debounceTimeout);
  }

  escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }
}

// Exportar para uso global
if (typeof window !== "undefined") {
  window.Autocomplete = Autocomplete;
}
