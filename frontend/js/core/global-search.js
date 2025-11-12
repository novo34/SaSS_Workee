/**
 * Búsqueda Global (Ctrl+K)
 *
 * Búsqueda global rápida en toda la aplicación
 *
 * @example
 * GlobalSearch.init();
 */

class GlobalSearch {
  constructor() {
    this.modal = null;
    this.searchInput = null;
    this.resultsContainer = null;
    this.searchIndex = [];
    this.init();
  }

  init() {
    // Crear modal de búsqueda global
    this.createModal();

    // Registrar atajo Ctrl+K
    if (typeof KeyboardShortcuts !== "undefined") {
      KeyboardShortcuts.init().register(
        "ctrl+k",
        () => this.open(),
        "Búsqueda global"
      );
    } else {
      document.addEventListener("keydown", (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === "k") {
          e.preventDefault();
          this.open();
        }
      });
    }
  }

  /**
   * Crea el modal de búsqueda global
   */
  createModal() {
    this.modal = document.createElement("div");
    this.modal.className = "global-search-modal";
    this.modal.innerHTML = `
            <div class="global-search-modal__backdrop"></div>
            <div class="global-search-modal__content">
                <div class="global-search-modal__header">
                    <input 
                        type="search" 
                        class="global-search-modal__input" 
                        placeholder="Buscar en toda la aplicación..."
                        autocomplete="off"
                    >
                    <button class="global-search-modal__close" aria-label="Cerrar">×</button>
                </div>
                <div class="global-search-modal__results"></div>
                <div class="global-search-modal__footer">
                    <small class="text-muted">
                        <kbd>↑</kbd> <kbd>↓</kbd> Navegar • <kbd>Enter</kbd> Seleccionar • <kbd>Esc</kbd> Cerrar
                    </small>
                </div>
            </div>
        `;

    document.body.appendChild(this.modal);

    this.searchInput = this.modal.querySelector(".global-search-modal__input");
    this.resultsContainer = this.modal.querySelector(
      ".global-search-modal__results"
    );
    const closeBtn = this.modal.querySelector(".global-search-modal__close");
    const backdrop = this.modal.querySelector(".global-search-modal__backdrop");

    // Event listeners
    this.searchInput.addEventListener("input", (e) => this.handleSearch(e));
    this.searchInput.addEventListener("keydown", (e) => this.handleKeyDown(e));
    closeBtn.addEventListener("click", () => this.close());
    backdrop.addEventListener("click", () => this.close());

    // Construir índice de búsqueda
    this.buildSearchIndex();
  }

  /**
   * Construye índice de búsqueda desde el DOM
   */
  buildSearchIndex() {
    this.searchIndex = [];

    // Buscar enlaces de navegación
    document
      .querySelectorAll("nav a, .sidebar a, [data-i18n]")
      .forEach((link) => {
        const text = link.textContent.trim();
        const href = link.getAttribute("href");
        if (text && href) {
          this.searchIndex.push({
            type: "navigation",
            title: text,
            url: href,
            icon: "🔗",
          });
        }
      });

    // Buscar páginas principales
    const mainPages = [
      { title: "Dashboard", url: "/dashboard", icon: "📊" },
      { title: "Calendario", url: "/calendar", icon: "📅" },
      { title: "Solicitudes", url: "/staffing-requests", icon: "📋" },
      { title: "Propuestas", url: "/staffing-proposals", icon: "💼" },
      { title: "Grupos", url: "/group-builder", icon: "👥" },
      { title: "Asignaciones", url: "/assignments", icon: "✅" },
      { title: "Órdenes de Trabajo", url: "/work-orders", icon: "📝" },
      { title: "Aprobaciones", url: "/approvals", icon: "✓" },
      { title: "Disputas", url: "/disputes", icon: "⚖️" },
      { title: "Configuración", url: "/settings", icon: "⚙️" },
    ];

    this.searchIndex.push(...mainPages);
  }

  /**
   * Abre el modal de búsqueda
   */
  open() {
    this.modal.classList.add("global-search-modal--show");
    this.searchInput.focus();
    this.searchInput.select();
  }

  /**
   * Cierra el modal
   */
  close() {
    this.modal.classList.remove("global-search-modal--show");
    this.searchInput.value = "";
    this.resultsContainer.innerHTML = "";
  }

  /**
   * Maneja la búsqueda
   */
  handleSearch(e) {
    const query = e.target.value.trim().toLowerCase();

    if (query.length < 2) {
      this.resultsContainer.innerHTML = "";
      return;
    }

    const results = this.searchIndex
      .filter((item) => {
        return (
          item.title.toLowerCase().includes(query) ||
          item.url.toLowerCase().includes(query)
        );
      })
      .slice(0, 10);

    this.renderResults(results);
  }

  /**
   * Renderiza resultados
   */
  renderResults(results) {
    if (results.length === 0) {
      this.resultsContainer.innerHTML = `
                <div class="global-search-modal__empty">
                    <p class="text-muted">No se encontraron resultados</p>
                </div>
            `;
      return;
    }

    this.resultsContainer.innerHTML = results
      .map(
        (item, index) => `
            <div 
                class="global-search-modal__result ${
                  index === 0 ? "global-search-modal__result--selected" : ""
                }"
                data-url="${this.escapeHtml(item.url)}"
                data-index="${index}"
            >
                <span class="global-search-modal__result-icon">${
                  item.icon
                }</span>
                <div class="global-search-modal__result-content">
                    <div class="global-search-modal__result-title">${this.escapeHtml(
                      item.title
                    )}</div>
                    <div class="global-search-modal__result-url">${this.escapeHtml(
                      item.url
                    )}</div>
                </div>
            </div>
        `
      )
      .join("");

    // Event listeners
    this.resultsContainer
      .querySelectorAll(".global-search-modal__result")
      .forEach((result) => {
        result.addEventListener("click", () => {
          const url = result.dataset.url;
          if (url) {
            window.location.href = url;
          }
        });
      });
  }

  /**
   * Maneja teclas del teclado
   */
  handleKeyDown(e) {
    const results = this.resultsContainer.querySelectorAll(
      ".global-search-modal__result"
    );
    const selected = this.resultsContainer.querySelector(
      ".global-search-modal__result--selected"
    );
    let selectedIndex = selected ? parseInt(selected.dataset.index) : -1;

    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        selectedIndex = Math.min(selectedIndex + 1, results.length - 1);
        this.highlightResult(selectedIndex);
        break;

      case "ArrowUp":
        e.preventDefault();
        selectedIndex = Math.max(selectedIndex - 1, -1);
        if (selectedIndex >= 0) {
          this.highlightResult(selectedIndex);
        }
        break;

      case "Enter":
        e.preventDefault();
        if (selectedIndex >= 0 && results[selectedIndex]) {
          const url = results[selectedIndex].dataset.url;
          if (url) {
            window.location.href = url;
          }
        }
        break;

      case "Escape":
        this.close();
        break;
    }
  }

  /**
   * Resalta un resultado
   */
  highlightResult(index) {
    this.resultsContainer
      .querySelectorAll(".global-search-modal__result")
      .forEach((result, i) => {
        result.classList.toggle(
          "global-search-modal__result--selected",
          i === index
        );
      });
  }

  escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Inicializa el sistema de búsqueda global
   */
  static init() {
    if (!window.globalSearchInstance) {
      window.globalSearchInstance = new GlobalSearch();
    }
    return window.globalSearchInstance;
  }
}

// Auto-inicializar
if (typeof window !== "undefined") {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => GlobalSearch.init());
  } else {
    GlobalSearch.init();
  }

  window.GlobalSearch = GlobalSearch;
}
