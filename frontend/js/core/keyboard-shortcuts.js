/**
 * Sistema de Atajos de Teclado
 *
 * Gestiona atajos de teclado para acciones frecuentes
 *
 * @example
 * KeyboardShortcuts.register('ctrl+k', () => {
 *   openGlobalSearch();
 * }, 'Abrir búsqueda global');
 *
 * KeyboardShortcuts.init();
 */

class KeyboardShortcuts {
  constructor() {
    this.shortcuts = new Map();
    this.helpModal = null;
    this.init();
  }

  init() {
    // Registrar atajo para mostrar ayuda (Ctrl+?)
    this.register("ctrl+?", () => this.showHelp(), "Mostrar ayuda de atajos");

    // Registrar atajo para búsqueda global (Ctrl+K)
    this.register("ctrl+k", () => this.openGlobalSearch(), "Búsqueda global");

    // Registrar atajo para nuevo elemento (Ctrl+N)
    this.register("ctrl+n", () => this.handleNew(), "Crear nuevo");

    // Registrar atajo para guardar (Ctrl+S)
    this.register(
      "ctrl+s",
      (e) => {
        e.preventDefault();
        this.handleSave();
      },
      "Guardar"
    );

    // Registrar atajo para cerrar modales (Escape)
    this.register("escape", () => this.handleEscape(), "Cerrar modal/dropdown");

    // Event listener global
    document.addEventListener("keydown", (e) => this.handleKeyDown(e));
  }

  /**
   * Registra un atajo de teclado
   */
  register(keys, callback, description = "") {
    const normalized = this.normalizeKeys(keys);
    this.shortcuts.set(normalized, {
      callback,
      description,
      keys: normalized,
    });
  }

  /**
   * Normaliza las teclas a un formato estándar
   */
  normalizeKeys(keys) {
    return keys.toLowerCase().replace(/\s+/g, "").split("+").sort().join("+");
  }

  /**
   * Maneja el evento keydown
   */
  handleKeyDown(e) {
    // Ignorar si está escribiendo en un input, textarea o contenteditable
    const target = e.target;
    if (
      target.tagName === "INPUT" &&
      target.type !== "search" &&
      target.type !== "text"
    ) {
      return; // Ignorar inputs especiales (checkbox, radio, etc.)
    }
    if (target.tagName === "TEXTAREA" || target.isContentEditable) {
      return;
    }

    // Construir combinación de teclas
    const keys = [];
    if (e.ctrlKey || e.metaKey) keys.push("ctrl");
    if (e.altKey) keys.push("alt");
    if (e.shiftKey) keys.push("shift");

    const key = e.key.toLowerCase();
    if (
      key !== "control" &&
      key !== "alt" &&
      key !== "shift" &&
      key !== "meta"
    ) {
      keys.push(key);
    }

    const combination = this.normalizeKeys(keys.join("+"));
    const shortcut = this.shortcuts.get(combination);

    if (shortcut) {
      e.preventDefault();
      shortcut.callback(e);
    }
  }

  /**
   * Abre búsqueda global
   */
  openGlobalSearch() {
    // Buscar si existe un componente de búsqueda global
    const searchInput = document.querySelector("[data-global-search]");
    if (searchInput) {
      searchInput.focus();
      searchInput.select();
    } else {
      // Crear búsqueda global si no existe
      this.createGlobalSearch();
    }
  }

  /**
   * Crea búsqueda global si no existe
   */
  createGlobalSearch() {
    // Por ahora solo mostrar un mensaje, se implementará en UI-118
    if (typeof Toast !== "undefined") {
      Toast.info("Búsqueda global - Próximamente", 2000);
    }
  }

  /**
   * Maneja Ctrl+N (nuevo)
   */
  handleNew() {
    // Buscar botón de "Nuevo" o "Crear" en la página actual
    const newButton = document.querySelector(
      '[data-action="new"], [data-action="create"], .btn-new, .btn-create'
    );
    if (newButton && !newButton.disabled) {
      newButton.click();
    }
  }

  /**
   * Maneja Ctrl+S (guardar)
   */
  handleSave() {
    // Buscar botón de "Guardar" en formularios
    const saveButton = document.querySelector(
      'form [type="submit"], .btn-save, [data-action="save"]'
    );
    if (saveButton && !saveButton.disabled) {
      const form = saveButton.closest("form");
      if (form && form.checkValidity()) {
        saveButton.click();
      }
    }
  }

  /**
   * Maneja Escape (cerrar)
   */
  handleEscape() {
    // Cerrar modales abiertos
    const openModal = document.querySelector(
      ".modal.show, .modal-backdrop.show"
    );
    if (openModal) {
      const modal = bootstrap.Modal.getInstance(openModal);
      if (modal) {
        modal.hide();
      }
    }

    // Cerrar dropdowns abiertos
    const openDropdown = document.querySelector(".dropdown-menu.show");
    if (openDropdown) {
      const dropdown = bootstrap.Dropdown.getInstance(
        openDropdown.previousElementSibling
      );
      if (dropdown) {
        dropdown.hide();
      }
    }
  }

  /**
   * Muestra ayuda de atajos
   */
  showHelp() {
    if (typeof Modal !== "undefined") {
      const shortcutsList = Array.from(this.shortcuts.entries())
        .map(([keys, data]) => {
          const keysDisplay = keys
            .split("+")
            .map((k) => {
              if (k === "ctrl") return "Ctrl";
              if (k === "alt") return "Alt";
              if (k === "shift") return "Shift";
              return k.toUpperCase();
            })
            .join(" + ");
          return `
                        <tr>
                            <td><kbd>${keysDisplay}</kbd></td>
                            <td>${this.escapeHtml(
                              data.description || "Sin descripción"
                            )}</td>
                        </tr>
                    `;
        })
        .join("");

      Modal.alert({
        title: "Atajos de Teclado",
        message: `
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Atajo</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${shortcutsList}
                            </tbody>
                        </table>
                    </div>
                `,
        size: "lg",
      });
    }
  }

  /**
   * Obtiene lista de atajos registrados
   */
  getShortcuts() {
    return Array.from(this.shortcuts.entries()).map(([keys, data]) => ({
      keys,
      description: data.description,
    }));
  }

  escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Inicializa el sistema de atajos
   */
  static init() {
    if (!window.keyboardShortcutsInstance) {
      window.keyboardShortcutsInstance = new KeyboardShortcuts();
    }
    return window.keyboardShortcutsInstance;
  }
}

// Auto-inicializar
if (typeof window !== "undefined") {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () =>
      KeyboardShortcuts.init()
    );
  } else {
    KeyboardShortcuts.init();
  }

  window.KeyboardShortcuts = KeyboardShortcuts;
}
