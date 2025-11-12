/**
 * Layout JavaScript - Workee
 *
 * Maneja sidebar, header y layout general
 */

// BASE_URL está inyectado por index.php en window.BASE_URL
// Usar directamente window.BASE_URL para evitar conflictos con otros scripts

// Inicialización
document.addEventListener("DOMContentLoaded", () => {
  // Inicializar componentes UI comunes (tooltips e iconos)
  // Nota: layout.js se carga en todas las páginas, así que inicializamos UI aquí también
  if (typeof UIInitializer !== "undefined") {
    UIInitializer.init();
  }

  initSidebar();
  initHeader();
  initHeaderScroll();
  loadUserInfo();
  setActiveNavItem();
  initCollapsibleSections();
  initNavigationBadges();
  initSkipLink();

  // NAV-136: Inicializar búsqueda integrada en sidebar
  initSidebarSearch();

  // NAV-134: Inicializar mega menus si están disponibles
  if (typeof initMegaMenus !== "undefined") {
    // Esperar a que MegaMenu esté disponible
    setTimeout(() => {
      if (typeof MegaMenu !== "undefined") {
        initMegaMenus();
      }
    }, 500);
  }

  // NAV-135: Procesar iconos SVG en navegación con lazy loading
  // Iconos visibles inmediatamente (sidebar principal) se procesan de inmediato
  // Iconos ocultos (subsecciones colapsadas) se cargan cuando sean visibles
  if (typeof IconHelper !== "undefined") {
    // Procesar iconos visibles inmediatamente (sin lazy loading)
    IconHelper.processDataIcons("#main-navigation", { lazy: false });
    // También procesar el botón toggle del sidebar
    IconHelper.processDataIcons("#sidebar-toggle", { lazy: false });

    // NAV-135: Procesar iconos en subsecciones colapsadas con lazy loading
    // Estos se cargarán cuando las subsecciones se expandan
    const collapsedSubsections = document.querySelectorAll(
      '.nav-subsection[data-collapsed="true"] [data-icon]'
    );
    if (collapsedSubsections.length > 0 && "IntersectionObserver" in window) {
      const lazyIconObserver = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              const element = entry.target;
              const iconName = element.getAttribute("data-icon");
              if (iconName && IconHelper.icons[iconName]) {
                IconHelper._processIconElement(element, iconName);
                lazyIconObserver.unobserve(element);
              }
            }
          });
        },
        {
          rootMargin: "20px",
        }
      );

      collapsedSubsections.forEach((element) => {
        lazyIconObserver.observe(element);
      });
    }
  }

  // NAV-135: Preload de páginas frecuentes
  initPagePreload();

  // MOB-140: Inicializar pull to refresh en todas las páginas (solo en móvil)
  initPullToRefresh();
});

/**
 * Inicializa el skip link para que solo sea accesible cuando sea necesario
 * El skip link está con tabindex="-1" por defecto para no interferir con el tab order normal
 */
function initSkipLink() {
  const skipLink = document.querySelector(".skip-link");
  if (!skipLink) return;

  // El skip link está con tabindex="-1" por defecto en el HTML
  // Solo activarlo cuando el usuario hace focus explícito o presiona una tecla específica
  // Permitir que sea accesible con Shift+Tab desde el primer elemento o cuando se presiona Tab desde body

  // Cuando el usuario hace click o focus explícito, activarlo
  skipLink.addEventListener("focus", () => {
    skipLink.setAttribute("tabindex", "0");
  });

  // Cuando el skip link pierde el focus, volver a ocultarlo del tab order
  skipLink.addEventListener("blur", () => {
    skipLink.setAttribute("tabindex", "-1");
  });

  // Permitir acceso con teclado: si el usuario presiona una tecla específica, activar el skip link
  // Esto permite que usuarios de lectores de pantalla puedan acceder al skip link
  document.addEventListener("keydown", (e) => {
    // Si el usuario presiona 'S' (para "Skip") desde el body, activar el skip link
    if (e.key === "s" || e.key === "S") {
      if (
        document.activeElement === document.body ||
        document.activeElement === document.documentElement
      ) {
        skipLink.setAttribute("tabindex", "0");
        skipLink.focus();
        e.preventDefault();
      }
    }
  });
}

/**
 * Inicializa el sidebar
 */
function initSidebar() {
  const sidebarToggle = document.getElementById("sidebar-toggle");
  const sidebar = document.getElementById("main-navigation");
  const overlay = document.getElementById("sidebar-overlay");

  // Asegurar que sidebar-toggle oculto no esté en tab order en desktop
  if (sidebarToggle) {
    if (window.innerWidth >= 992) {
      sidebarToggle.setAttribute("tabindex", "-1");
    }

    // Actualizar tabindex cuando cambia el tamaño de ventana
    window.addEventListener("resize", () => {
      if (window.innerWidth >= 992) {
        sidebarToggle.setAttribute("tabindex", "-1");
      } else {
        sidebarToggle.removeAttribute("tabindex");
      }
    });
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", () => {
      const isOpen = sidebar?.classList.contains("app-sidebar--open");
      if (isOpen) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
  }

  if (overlay) {
    overlay.addEventListener("click", () => {
      closeSidebar();
    });
  }

  // Cerrar sidebar al hacer clic en un enlace (mobile)
  const navLinks = document.querySelectorAll(
    ".sidebar-nav .nav-link, .sidebar-nav__link"
  );
  navLinks.forEach((link) => {
    link.addEventListener("click", () => {
      if (window.innerWidth < 992) {
        closeSidebar();
      }
    });
  });

  // Inicializar sidebar colapsable (desktop)
  initSidebarCollapse();

  // Inicializar swipe gestures para móvil
  initSwipeGestures();

  // Inicializar focus management
  initSidebarFocusManagement();
}

/**
 * Abre el sidebar (mobile)
 */
function openSidebar() {
  const sidebar = document.getElementById("main-navigation");
  const overlay = document.getElementById("sidebar-overlay");

  if (sidebar && overlay) {
    sidebar.classList.add("app-sidebar--open", "open");
    overlay.classList.add("sidebar-overlay--show", "show");

    // Focus management: mover focus al primer item de navegación
    const firstNavLink = sidebar.querySelector(".sidebar-nav .nav-link");
    if (firstNavLink) {
      firstNavLink.focus();
    }
  }
}

/**
 * Cierra el sidebar (mobile)
 */
function closeSidebar() {
  const sidebar = document.getElementById("main-navigation");
  const overlay = document.getElementById("sidebar-overlay");

  if (sidebar && overlay) {
    sidebar.classList.remove("app-sidebar--open", "open");
    overlay.classList.remove("sidebar-overlay--show", "show");

    // NAV-133: Actualizar aria-expanded del toggle button
    const sidebarToggle = document.getElementById("sidebar-toggle");
    if (sidebarToggle) {
      sidebarToggle.setAttribute("aria-expanded", "false");
    }
  }
}

/**
 * Inicializa swipe gestures para abrir/cerrar sidebar en móvil
 */
function initSwipeGestures() {
  // Solo en móvil/tablet
  if (window.innerWidth >= 992) return;

  let touchStartX = 0;
  let touchStartY = 0;
  let touchEndX = 0;
  let touchEndY = 0;
  const minSwipeDistance = 50; // Distancia mínima para considerar swipe
  const maxVerticalDistance = 100; // Máxima distancia vertical para ignorar swipe horizontal

  const handleTouchStart = (e) => {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
  };

  const handleTouchEnd = (e) => {
    touchEndX = e.changedTouches[0].clientX;
    touchEndY = e.changedTouches[0].clientY;

    const deltaX = touchEndX - touchStartX;
    const deltaY = Math.abs(touchEndY - touchStartY);

    // Ignorar si el movimiento vertical es mayor que el horizontal
    if (deltaY > maxVerticalDistance) return;

    const sidebar = document.getElementById("main-navigation");
    const isOpen = sidebar?.classList.contains("app-sidebar--open");

    // Swipe desde borde izquierdo (primeros 20px) para abrir
    if (!isOpen && touchStartX <= 20 && deltaX > minSwipeDistance) {
      openSidebar();
      e.preventDefault();
    }
    // Swipe derecho para cerrar
    else if (isOpen && deltaX < -minSwipeDistance) {
      closeSidebar();
      e.preventDefault();
    }
  };

  // Agregar event listeners al documento
  document.addEventListener("touchstart", handleTouchStart, { passive: true });
  document.addEventListener("touchend", handleTouchEnd, { passive: false });

  // También permitir swipe en el overlay para cerrar
  const overlay = document.getElementById("sidebar-overlay");
  if (overlay) {
    overlay.addEventListener("touchstart", handleTouchStart, { passive: true });
    overlay.addEventListener("touchend", handleTouchEnd, { passive: false });
  }
}

/**
 * Inicializa focus management para sidebar
 */
function initSidebarFocusManagement() {
  const sidebar = document.getElementById("main-navigation");
  const sidebarToggle = document.getElementById("sidebar-toggle");

  if (!sidebar || !sidebarToggle) return;

  // Observar cambios en la clase del sidebar
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (
        mutation.type === "attributes" &&
        mutation.attributeName === "class"
      ) {
        const isOpen = sidebar.classList.contains("app-sidebar--open");

        if (isOpen && window.innerWidth < 992) {
          // Mover focus al primer item de navegación cuando se abre
          setTimeout(() => {
            const firstNavLink = sidebar.querySelector(
              ".sidebar-nav .nav-link"
            );
            if (firstNavLink) {
              firstNavLink.focus();
            }
          }, 100); // Pequeño delay para permitir animación
        }
      }
    });
  });

  observer.observe(sidebar, {
    attributes: true,
    attributeFilter: ["class"],
  });

  // También manejar cuando se presiona Esc para cerrar
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && window.innerWidth < 992) {
      const isOpen = sidebar.classList.contains("app-sidebar--open");
      if (isOpen) {
        closeSidebar();
        // Devolver focus al toggle button
        if (sidebarToggle) {
          sidebarToggle.focus();
        }
      }
    }
  });

  // Inicializar navegación con arrow keys
  initArrowKeyNavigation();
}

/**
 * Inicializa navegación con arrow keys en sidebar
 */
function initArrowKeyNavigation() {
  const sidebar = document.getElementById("main-navigation");
  if (!sidebar) return;

  // Obtener todos los items de navegación (links y botones de subsecciones)
  const getNavItems = () => {
    return Array.from(
      sidebar.querySelectorAll(
        ".sidebar-nav .nav-link, .sidebar-nav .nav-subsection-title--toggle"
      )
    ).filter((item) => {
      // Excluir items disabled
      return !item.disabled && !item.classList.contains("disabled");
    });
  };

  sidebar.addEventListener("keydown", (e) => {
    const navItems = getNavItems();
    if (navItems.length === 0) return;

    const currentIndex = navItems.indexOf(document.activeElement);
    if (currentIndex === -1) return;

    let nextIndex = currentIndex;

    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        nextIndex = (currentIndex + 1) % navItems.length;
        navItems[nextIndex]?.focus();
        break;

      case "ArrowUp":
        e.preventDefault();
        nextIndex = currentIndex === 0 ? navItems.length - 1 : currentIndex - 1;
        navItems[nextIndex]?.focus();
        break;

      case "Home":
        e.preventDefault();
        navItems[0]?.focus();
        break;

      case "End":
        e.preventDefault();
        navItems[navItems.length - 1]?.focus();
        break;

      case "Enter":
      case " ":
        // Si es un botón de subsección, activar toggle
        if (
          document.activeElement.classList.contains(
            "nav-subsection-title--toggle"
          )
        ) {
          e.preventDefault();
          document.activeElement.click();
        }
        break;
    }
  });
}

/**
 * Inicializa secciones colapsables en sidebar
 */
function initCollapsibleSections() {
  const subsections = document.querySelectorAll(
    ".nav-subsection[data-nav-group]"
  );

  subsections.forEach((subsection) => {
    const toggle = subsection.querySelector(".nav-subsection-title--toggle");
    const content = subsection.querySelector(".nav-subsection-content");
    const group = subsection.getAttribute("data-nav-group");

    if (!toggle || !content) return;

    // Cargar estado guardado de localStorage
    const savedState = localStorage.getItem(`nav-subsection-${group}`);
    const isExpanded = savedState !== "false"; // Por defecto expandido

    // Aplicar estado inicial
    if (!isExpanded) {
      subsection.setAttribute("data-collapsed", "true");
      toggle.setAttribute("aria-expanded", "false");
      // NAV-133: Actualizar aria-label según estado
      const titleText =
        toggle.querySelector(".nav-subsection-title-text")?.textContent ||
        "sección";
      toggle.setAttribute("aria-label", `Expandir sección ${titleText}`);
      // Cambiar chevron a chevronRight cuando está colapsado
      const chevron = toggle.querySelector(".nav-subsection-chevron");
      if (chevron) {
        chevron.setAttribute("data-icon", "chevronRight");
        if (typeof IconHelper !== "undefined") {
          IconHelper.processDataIcons(chevron);
        }
      }
    } else {
      // NAV-133: Actualizar aria-label cuando está expandido
      const titleText =
        toggle.querySelector(".nav-subsection-title-text")?.textContent ||
        "sección";
      toggle.setAttribute("aria-label", `Colapsar sección ${titleText}`);
    }

    // Agregar event listener para toggle
    toggle.addEventListener("click", () => {
      const isCurrentlyExpanded =
        toggle.getAttribute("aria-expanded") === "true";
      const newState = !isCurrentlyExpanded;

      // Actualizar estado
      toggle.setAttribute("aria-expanded", newState.toString());
      subsection.setAttribute("data-collapsed", (!newState).toString());

      // NAV-133: Actualizar aria-label según estado
      const titleText =
        toggle.querySelector(".nav-subsection-title-text")?.textContent ||
        "sección";
      toggle.setAttribute(
        "aria-label",
        newState
          ? `Colapsar sección ${titleText}`
          : `Expandir sección ${titleText}`
      );

      // Cambiar icono chevron
      const chevron = toggle.querySelector(".nav-subsection-chevron");
      if (chevron) {
        chevron.setAttribute(
          "data-icon",
          newState ? "chevronDown" : "chevronRight"
        );
        if (typeof IconHelper !== "undefined") {
          IconHelper.processDataIcons(chevron);
        }
      }

      // NAV-135: Procesar iconos en la subsección cuando se expande (lazy loading)
      if (newState && typeof IconHelper !== "undefined") {
        const subsectionIcons = subsection.querySelectorAll("[data-icon]");
        subsectionIcons.forEach((iconElement) => {
          const iconName = iconElement.getAttribute("data-icon");
          if (iconName && IconHelper.icons[iconName]) {
            IconHelper._processIconElement(iconElement, iconName);
          }
        });
      }

      // Guardar estado en localStorage
      localStorage.setItem(`nav-subsection-${group}`, newState.toString());
    });
  });
}

/**
 * Inicializa el toggle de colapsar/expandir sidebar (desktop)
 */
function initSidebarCollapse() {
  const collapseToggle = document.getElementById("sidebar-collapse-toggle");
  const sidebar = document.getElementById("main-navigation");

  if (!collapseToggle || !sidebar) return;

  // Asegurar que sidebar-collapse-toggle no esté en tab order en móvil
  const updateTabIndex = () => {
    if (window.innerWidth < 768) {
      // En móvil, ocultar del tab order
      collapseToggle.setAttribute("tabindex", "-1");
    } else {
      // En desktop/tablet, restaurar tabindex
      collapseToggle.setAttribute("tabindex", "0");
    }
  };

  // Actualizar al cargar
  updateTabIndex();

  // Actualizar cuando cambia el tamaño de ventana
  window.addEventListener("resize", updateTabIndex);

  // Cargar estado guardado
  const savedState = localStorage.getItem("sidebar-collapsed");
  const isCollapsed = savedState === "true";

  // Aplicar estado inicial
  if (isCollapsed) {
    sidebar.classList.add("app-sidebar--collapsed");
    collapseToggle.setAttribute("aria-expanded", "false");
    updateTooltips(true);
  } else {
    collapseToggle.setAttribute("aria-expanded", "true");
    updateTooltips(false);
  }

  // Event listener para toggle
  collapseToggle.addEventListener("click", () => {
    const isCurrentlyCollapsed = sidebar.classList.contains(
      "app-sidebar--collapsed"
    );
    const newState = !isCurrentlyCollapsed;

    if (newState) {
      sidebar.classList.add("app-sidebar--collapsed");
      collapseToggle.setAttribute("aria-expanded", "false");
      updateTooltips(true);
      localStorage.setItem("sidebar-collapsed", "true");
    } else {
      sidebar.classList.remove("app-sidebar--collapsed");
      collapseToggle.setAttribute("aria-expanded", "true");
      updateTooltips(false);
      localStorage.setItem("sidebar-collapsed", "false");
    }
  });
}

/**
 * Actualiza tooltips en items de navegación según estado del sidebar
 */
function updateTooltips(isCollapsed) {
  const navLinks = document.querySelectorAll(".sidebar-nav .nav-link");
  const navSubsectionTitles = document.querySelectorAll(
    ".sidebar-nav .nav-subsection-title--toggle"
  );

  if (isCollapsed) {
    // Agregar tooltips cuando está colapsado
    navLinks.forEach((link) => {
      const textElement = link.querySelector(".nav-text");
      if (textElement) {
        const text = textElement.textContent.trim();
        if (text && !link.hasAttribute("data-tooltip")) {
          link.setAttribute("data-tooltip", text);
          link.setAttribute("title", text);
        }
      }
    });

    navSubsectionTitles.forEach((title) => {
      const textElement = title.querySelector(".nav-subsection-title-text");
      if (textElement) {
        const text = textElement.textContent.trim();
        if (text && !title.hasAttribute("data-tooltip")) {
          title.setAttribute("data-tooltip", text);
          title.setAttribute("title", text);
        }
      }
    });
  } else {
    // Remover tooltips cuando está expandido
    navLinks.forEach((link) => {
      if (link.hasAttribute("data-tooltip")) {
        link.removeAttribute("data-tooltip");
        link.removeAttribute("title");
      }
    });

    navSubsectionTitles.forEach((title) => {
      if (title.hasAttribute("data-tooltip")) {
        title.removeAttribute("data-tooltip");
        title.removeAttribute("title");
      }
    });
  }

  // Reinicializar tooltips si UIInitializer está disponible
  if (typeof UIInitializer !== "undefined" && typeof Tooltip !== "undefined") {
    Tooltip.init();
  }
}

/**
 * Inicializa el header con detección de scroll
 */
function initHeaderScroll() {
  const header = document.querySelector(".app-header");
  if (!header) return;

  let lastScrollTop = 0;

  const handleScroll = () => {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

    if (scrollTop > 10) {
      header.classList.add("app-header--scrolled");
    } else {
      header.classList.remove("app-header--scrolled");
    }

    lastScrollTop = scrollTop;
  };

  // MOB-143: Usar throttle para mejor performance (mejorado con PerformanceMobile)
  let scrollHandler;
  if (typeof PerformanceMobile !== "undefined" && window.innerWidth < 992) {
    // En móvil, usar throttle más agresivo
    scrollHandler = PerformanceMobile.throttle(handleScroll, 100);
  } else {
    // En desktop, usar requestAnimationFrame (ya optimizado)
    let ticking = false;
    scrollHandler = () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          handleScroll();
          ticking = false;
        });
        ticking = true;
      }
    };
  }

  window.addEventListener("scroll", scrollHandler, { passive: true });
}

/**
 * Inicializa el header
 */
function initHeader() {
  // Actualizar breadcrumb según la página actual
  updateBreadcrumb();

  // Mostrar/ocultar secciones según rol
  const userStr = localStorage.getItem("user");
  if (userStr) {
    const user = JSON.parse(userStr);
    const role = localStorage.getItem("user_role") || "";

    // Owner section
    const ownerSection = document.getElementById("owner-section");
    if (role === "owner_admin") {
      if (ownerSection) {
        ownerSection.style.display = "block";
        ownerSection.setAttribute("aria-hidden", "false");
        // Restaurar tabindex y remover aria-hidden en elementos dentro de la sección
        ownerSection.querySelectorAll("a, button").forEach((el) => {
          if (
            el.hasAttribute("tabindex") &&
            el.getAttribute("tabindex") === "-1"
          ) {
            el.removeAttribute("tabindex");
          }
          // Remover aria-hidden si existe (para screen readers)
          // Solo remover si el elemento no es decorativo (los iconos deben mantener aria-hidden)
          if (
            el.hasAttribute("aria-hidden") &&
            !el.classList.contains("icon") &&
            !el.querySelector("svg")
          ) {
            el.removeAttribute("aria-hidden");
          }
        });
      }
    } else {
      // Si no es owner_admin, asegurar que la sección esté oculta
      if (ownerSection) {
        ownerSection.style.display = "none";
        ownerSection.setAttribute("aria-hidden", "true");
        ownerSection.querySelectorAll("a, button").forEach((el) => {
          el.setAttribute("tabindex", "-1");
          // ACCESIBILIDAD: Agregar aria-hidden a elementos dentro de sección oculta
          el.setAttribute("aria-hidden", "true");
        });
      }
    }

    // Empresa section (empresa_admin, planificador)
    const empresaRoles = ["empresa_admin", "planificador"];
    const empresaSection = document.getElementById("empresa-section");
    if (empresaRoles.includes(role)) {
      if (empresaSection) {
        empresaSection.style.display = "block";
        empresaSection.setAttribute("aria-hidden", "false");
        // Restaurar tabindex y remover aria-hidden en elementos dentro de la sección
        empresaSection.querySelectorAll("a, button").forEach((el) => {
          if (
            el.hasAttribute("tabindex") &&
            el.getAttribute("tabindex") === "-1"
          ) {
            el.removeAttribute("tabindex");
          }
          // Remover aria-hidden si existe (para screen readers)
          // Solo remover si el elemento no es decorativo (los iconos deben mantener aria-hidden)
          if (
            el.hasAttribute("aria-hidden") &&
            !el.classList.contains("icon") &&
            !el.querySelector("svg")
          ) {
            el.removeAttribute("aria-hidden");
          }
        });
      }
    } else {
      // Si no es empresa_admin o planificador, asegurar que la sección esté oculta
      if (empresaSection) {
        empresaSection.style.display = "none";
        empresaSection.setAttribute("aria-hidden", "true");
        empresaSection.querySelectorAll("a, button").forEach((el) => {
          el.setAttribute("tabindex", "-1");
          // ACCESIBILIDAD: Agregar aria-hidden a elementos dentro de sección oculta
          el.setAttribute("aria-hidden", "true");
        });
      }
    }

    // Temporera section (temporera_admin, coordinador)
    const temporeraRoles = ["temporera_admin", "coordinador"];
    const temporeraSection = document.getElementById("temporera-section");
    if (temporeraRoles.includes(role)) {
      if (temporeraSection) {
        temporeraSection.style.display = "block";
        temporeraSection.setAttribute("aria-hidden", "false");
        // Restaurar tabindex y remover aria-hidden en elementos dentro de la sección
        temporeraSection.querySelectorAll("a, button").forEach((el) => {
          if (
            el.hasAttribute("tabindex") &&
            el.getAttribute("tabindex") === "-1"
          ) {
            el.removeAttribute("tabindex");
          }
          // Remover aria-hidden si existe (para screen readers)
          // Solo remover si el elemento no es decorativo (los iconos deben mantener aria-hidden)
          if (
            el.hasAttribute("aria-hidden") &&
            !el.classList.contains("icon") &&
            !el.querySelector("svg")
          ) {
            el.removeAttribute("aria-hidden");
          }
        });
      }
    } else {
      // Si no es temporera_admin o coordinador, asegurar que la sección esté oculta
      if (temporeraSection) {
        temporeraSection.style.display = "none";
        temporeraSection.setAttribute("aria-hidden", "true");
        temporeraSection.querySelectorAll("a, button").forEach((el) => {
          el.setAttribute("tabindex", "-1");
          // ACCESIBILIDAD: Agregar aria-hidden a elementos dentro de sección oculta
          el.setAttribute("aria-hidden", "true");
        });
      }
    }
  } else {
    // Si no hay usuario, ocultar todas las secciones
    ["empresa-section", "temporera-section", "owner-section"].forEach(
      (sectionId) => {
        const section = document.getElementById(sectionId);
        if (section) {
          section.style.display = "none";
          section.setAttribute("aria-hidden", "true");
          section.querySelectorAll("a, button").forEach((el) => {
            el.setAttribute("tabindex", "-1");
            // ACCESIBILIDAD: Agregar aria-hidden a elementos dentro de sección oculta
            el.setAttribute("aria-hidden", "true");
          });
        }
      }
    );
  }
}

/**
 * Carga información del usuario en el header
 */
function loadUserInfo() {
  const userStr = localStorage.getItem("user");
  if (!userStr) return;

  const user = JSON.parse(userStr);
  const role = localStorage.getItem("user_role") || "";

  // Avatar
  const avatar = document.getElementById("user-avatar");
  if (avatar && user.first_name) {
    avatar.textContent = user.first_name.charAt(0).toUpperCase();
  }

  // Nombre
  const userName = document.getElementById("user-name");
  if (userName && user.first_name) {
    userName.textContent = `${user.first_name} ${user.last_name || ""}`.trim();
  }

  // Rol
  const userRole = document.getElementById("user-role");
  if (userRole) {
    const roleNames = {
      owner_admin: "Owner Admin",
      empresa_admin: "Admin Empresa",
      temporera_admin: "Admin Temporera",
      planificador: "Planificador",
      supervisor: "Supervisor",
      empleado: "Empleado",
      coordinador: "Coordinador",
      trabajador: "Trabajador",
    };
    userRole.textContent = roleNames[role] || role;
  }
}

/**
 * Establece el item de navegación activo según la página actual
 * NAV-133: Agrega aria-current="page" al link activo para accesibilidad
 */
function setActiveNavItem() {
  const currentPath = window.location.pathname;
  const navLinks = document.querySelectorAll(".sidebar-nav .nav-link");

  navLinks.forEach((link) => {
    link.classList.remove("active", "sidebar-nav__link--active", "loading");
    link.removeAttribute("aria-current");
    const href = link.getAttribute("href");
    if (href && currentPath.includes(href.replace(window.BASE_URL || "", ""))) {
      link.classList.add("active", "sidebar-nav__link--active");
      link.setAttribute("aria-current", "page");
    }
  });
}

/**
 * Agrega estado de loading a un item de navegación
 */
function setNavItemLoading(page) {
  const navLink = document.querySelector(
    `.sidebar-nav .nav-link[data-page="${page}"]`
  );
  if (navLink) {
    navLink.classList.add("loading", "sidebar-nav__link--loading");
  }
}

/**
 * Remueve estado de loading de un item de navegación
 */
function removeNavItemLoading(page) {
  const navLink = document.querySelector(
    `.sidebar-nav .nav-link[data-page="${page}"]`
  );
  if (navLink) {
    navLink.classList.remove("loading", "sidebar-nav__link--loading");
  }
}

/**
 * Actualiza badge de notificación en un item de navegación
 */
function updateNavItemBadge(page, count) {
  const navLink = document.querySelector(
    `.sidebar-nav .nav-link[data-page="${page}"]`
  );
  if (!navLink) return;

  let badge = navLink.querySelector(".nav-badge");
  const oldCount = badge ? parseInt(badge.textContent) || 0 : 0;

  if (count > 0) {
    if (!badge) {
      badge = document.createElement("span");
      badge.className = "nav-badge";
      navLink.appendChild(badge);
    }

    // Animación sutil al actualizar
    if (oldCount !== count) {
      badge.classList.add("updating");
      setTimeout(() => {
        badge.classList.remove("updating");
      }, 300);
    }

    badge.textContent = count > 99 ? "99+" : count.toString();
    badge.style.display = "inline-flex";
  } else if (badge) {
    badge.style.display = "none";
  }
}

/**
 * Actualiza badges de notificación desde APIs
 */
async function updateNavigationBadges() {
  if (typeof API === "undefined") return;

  try {
    // Actualizar badge de aprobaciones
    try {
      const approvalsResponse = await API.get("/approvals/pending");
      if (
        approvalsResponse &&
        approvalsResponse.success &&
        approvalsResponse.data
      ) {
        updateNavItemBadge("approvals", approvalsResponse.data.count || 0);
      }
    } catch (error) {
      console.warn(
        "[Navigation] Error al cargar conteo de aprobaciones:",
        error
      );
    }

    // Aquí se pueden agregar más actualizaciones de badges:
    // - Disputas pendientes
    // - Solicitudes pendientes
    // - Propuestas pendientes
    // etc.
  } catch (error) {
    console.error("[Navigation] Error al actualizar badges:", error);
  }
}

/**
 * Inicializa actualización periódica de badges
 */
function initNavigationBadges() {
  // Actualizar inmediatamente
  updateNavigationBadges();

  // Actualizar cada 30 segundos
  setInterval(() => {
    updateNavigationBadges();
  }, 30000);
}

/**
 * Actualiza el breadcrumb según la página actual
 */
function updateBreadcrumb() {
  const currentPath = window.location.pathname;
  const breadcrumbNav = document.getElementById("breadcrumb-nav");
  const breadcrumbList = breadcrumbNav?.querySelector(".breadcrumb-nav__list");

  if (!breadcrumbList) return;

  const pageNames = {
    "/dashboard": "Dashboard",
    "/agency-company-links": "Relaciones",
    "/settings": "Configuración",
    "/owner/dashboard": "Dashboard Owner",
    "/onboarding": "Onboarding",
    "/calendar": "Calendario",
    "/staffing-requests": "Solicitudes de Personal",
    "/staffing-proposals": "Propuestas de Candidatos",
    "/group-builder": "Constructor de Grupos",
    "/assignments": "Asignaciones",
    "/work-orders": "Órdenes de Trabajo",
    "/work-orders-edit": "Editor de Orden de Trabajo",
    "/work-orders-calendar": "Calendario OT",
    "/group-shift-report": "Parte del Líder",
    "/work-order-daily-report": "Parte Diario OT",
    "/approvals": "Aprobaciones",
    "/reports": "Reportes",
    "/settings-notifications": "Preferencias de Notificaciones",
    "/import-export": "Importar/Exportar Datos",
    "/employees": "Empleados",
    "/agency-workers": "Trabajadores",
    "/time-clock": "Fichaje",
    "/disputes": "Disputas",
    "/ratings": "Valoraciones",
    "/owner/dashboard": "Dashboard Owner",
    "/owner/billing": "Billing Owner",
  };

  // Encontrar nombre de página actual
  let currentPageName = "Página Actual";
  for (const [path, name] of Object.entries(pageNames)) {
    if (currentPath.includes(path)) {
      currentPageName = name;
      break;
    }
  }

  // Actualizar item actual
  const currentItem = breadcrumbList.querySelector(
    ".breadcrumb-nav__item--current"
  );
  if (currentItem) {
    const currentSpan = currentItem.querySelector(".breadcrumb-nav__current");
    if (currentSpan) {
      currentSpan.textContent = currentPageName;
    }
  }

  // Construir breadcrumb completo basado en la ruta
  const pathParts = currentPath.split("/").filter((p) => p);
  const breadcrumbItems = [];

  // Siempre incluir Dashboard como primer item
  breadcrumbItems.push({
    label: "Dashboard",
    href: "/dashboard",
  });

  // Agregar items intermedios si existen
  if (pathParts.length > 1) {
    // Por ejemplo: /work-orders/edit -> ["work-orders", "edit"]
    // Agregar "work-orders" como item intermedio
    const intermediatePath = "/" + pathParts[0];
    if (pageNames[intermediatePath] && intermediatePath !== currentPath) {
      breadcrumbItems.push({
        label: pageNames[intermediatePath],
        href: intermediatePath,
      });
    }
  }

  // Reconstruir breadcrumb
  breadcrumbList.innerHTML = "";

  breadcrumbItems.forEach((item, index) => {
    const li = document.createElement("li");
    li.className = "breadcrumb-nav__item";

    const link = document.createElement("a");
    link.href = item.href;
    link.className = "breadcrumb-nav__link";
    link.textContent = item.label;
    link.setAttribute(
      "data-i18n",
      `nav.${item.href.replace("/", "").replace("-", "_")}`
    );

    li.appendChild(link);
    breadcrumbList.appendChild(li);
  });

  // NAV-130: Agregar item actual (no clickeable) - estructura semántica completa
  const currentLi = document.createElement("li");
  currentLi.className = "breadcrumb-nav__item breadcrumb-nav__item--current";
  currentLi.setAttribute("aria-current", "page");

  const currentSpan = document.createElement("span");
  currentSpan.className = "breadcrumb-nav__current";
  currentSpan.id = "breadcrumb-current";
  currentSpan.textContent = currentPageName;
  // NAV-133: Agregar aria-label para screen readers
  currentSpan.setAttribute("aria-label", `Página actual: ${currentPageName}`);

  currentLi.appendChild(currentSpan);
  breadcrumbList.appendChild(currentLi);

  // NAV-133: Actualizar aria-label del item actual en breadcrumb (ya se agregó arriba en currentSpan)
}

/**
 * NAV-135: Preload de páginas frecuentes para mejorar performance
 * Preload de páginas más usadas cuando el usuario está inactivo
 */
/**
 * NAV-136: Inicializa búsqueda integrada en sidebar
 */
function initSidebarSearch() {
  const searchInput = document.getElementById("sidebar-search-input");
  const searchResults = document.getElementById("sidebar-search-results");
  const searchClear = document.getElementById("sidebar-search-clear");
  const sidebarNav = document.getElementById("sidebar-nav-main");

  if (!searchInput || !searchResults || !sidebarNav) return;

  // Obtener todos los items de navegación indexables
  const getAllNavItems = () => {
    const items = [];
    const navLinks = sidebarNav.querySelectorAll(".nav-link");
    const subsectionToggles = sidebarNav.querySelectorAll(
      ".nav-subsection-title--toggle"
    );

    navLinks.forEach((link) => {
      const text = link.textContent?.trim() || "";
      const ariaLabel = link.getAttribute("aria-label") || "";
      const href = link.getAttribute("href") || "";
      const dataPage = link.getAttribute("data-page") || "";

      if (text || ariaLabel) {
        items.push({
          element: link,
          text: text,
          ariaLabel: ariaLabel,
          href: href,
          dataPage: dataPage,
          type: "link",
          searchableText: `${text} ${ariaLabel}`.toLowerCase().trim(),
        });
      }
    });

    subsectionToggles.forEach((toggle) => {
      const text = toggle.textContent?.trim() || "";
      const ariaLabel = toggle.getAttribute("aria-label") || "";

      if (text || ariaLabel) {
        items.push({
          element: toggle,
          text: text,
          ariaLabel: ariaLabel,
          type: "subsection",
          searchableText: `${text} ${ariaLabel}`.toLowerCase().trim(),
        });
      }
    });

    return items;
  };

  let allNavItems = getAllNavItems();
  let filteredItems = [];
  let selectedIndex = -1;

  // Función de búsqueda con debounce
  const performSearch = (query) => {
    if (!query || query.trim().length === 0) {
      searchResults.style.display = "none";
      searchResults.innerHTML = "";
      filteredItems = [];
      selectedIndex = -1;
      // Mostrar navegación completa
      sidebarNav.style.display = "";
      return;
    }

    const searchTerm = query.toLowerCase().trim();
    filteredItems = allNavItems.filter((item) => {
      return item.searchableText.includes(searchTerm);
    });

    // Ocultar navegación completa y mostrar resultados
    sidebarNav.style.display = "none";
    searchResults.style.display = "block";

    // Renderizar resultados
    renderSearchResults(filteredItems, searchTerm);

    // Mostrar/ocultar botón clear
    if (searchClear) {
      searchClear.style.display =
        filteredItems.length > 0 || query.length > 0 ? "flex" : "none";
    }
  };

  // Renderizar resultados de búsqueda
  const renderSearchResults = (items, searchTerm) => {
    if (items.length === 0) {
      searchResults.innerHTML = `
        <div class="sidebar-search__no-results">
          <span class="icon icon-md" data-icon="search" aria-hidden="true"></span>
          <p>No se encontraron resultados para "${searchTerm}"</p>
        </div>
      `;
      // Procesar icono
      if (typeof IconHelper !== "undefined") {
        IconHelper.processDataIcons(searchResults, { lazy: false });
      }
      return;
    }

    const resultsHTML = items
      .map((item, index) => {
        const isSubsection = item.type === "subsection";
        const highlightedText = highlightSearchTerm(item.text, searchTerm);

        return `
        <div 
          class="sidebar-search__result-item ${
            isSubsection ? "sidebar-search__result-item--subsection" : ""
          }" 
          role="option"
          data-index="${index}"
          aria-selected="false"
          tabindex="-1">
          ${
            !isSubsection && item.element.querySelector(".nav-icon")
              ? `
            <span class="sidebar-search__result-icon icon icon-sm" data-icon="${
              item.element
                .querySelector(".nav-icon")
                ?.getAttribute("data-icon") || "link"
            }" aria-hidden="true"></span>
          `
              : ""
          }
          <span class="sidebar-search__result-text">${highlightedText}</span>
          ${
            isSubsection
              ? '<span class="sidebar-search__result-badge">Sección</span>'
              : ""
          }
        </div>
      `;
      })
      .join("");

    searchResults.innerHTML = resultsHTML;

    // Procesar iconos
    if (typeof IconHelper !== "undefined") {
      IconHelper.processDataIcons(searchResults, { lazy: false });
    }

    // Agregar event listeners a los resultados
    const resultItems = searchResults.querySelectorAll(
      ".sidebar-search__result-item"
    );
    resultItems.forEach((item, index) => {
      item.addEventListener("click", () => {
        const navItem = filteredItems[index];
        if (navItem) {
          if (navItem.type === "link") {
            window.location.href = navItem.href;
          } else if (navItem.type === "subsection") {
            // Expandir subsección y hacer scroll
            navItem.element.click();
            // Cerrar búsqueda
            searchInput.value = "";
            performSearch("");
            // Scroll a la subsección
            navItem.element.scrollIntoView({
              behavior: "smooth",
              block: "nearest",
            });
          }
        }
      });

      item.addEventListener("mouseenter", () => {
        // Remover selección anterior
        resultItems.forEach((i) => {
          i.classList.remove("sidebar-search__result-item--selected");
          i.setAttribute("aria-selected", "false");
        });
        // Seleccionar item actual
        item.classList.add("sidebar-search__result-item--selected");
        item.setAttribute("aria-selected", "true");
        selectedIndex = index;
      });
    });
  };

  // Resaltar término de búsqueda en el texto
  const highlightSearchTerm = (text, term) => {
    if (!term || term.length === 0) return text;
    const regex = new RegExp(`(${term})`, "gi");
    return text.replace(
      regex,
      '<mark class="sidebar-search__highlight">$1</mark>'
    );
  };

  // Navegación por teclado en resultados
  const handleKeyboardNavigation = (e) => {
    if (searchResults.style.display === "none" || filteredItems.length === 0)
      return;

    const resultItems = searchResults.querySelectorAll(
      ".sidebar-search__result-item"
    );
    if (resultItems.length === 0) return;

    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        selectedIndex = (selectedIndex + 1) % resultItems.length;
        updateSelectedItem(resultItems);
        break;

      case "ArrowUp":
        e.preventDefault();
        selectedIndex =
          selectedIndex <= 0 ? resultItems.length - 1 : selectedIndex - 1;
        updateSelectedItem(resultItems);
        break;

      case "Enter":
        if (selectedIndex >= 0 && selectedIndex < resultItems.length) {
          e.preventDefault();
          resultItems[selectedIndex].click();
        }
        break;

      case "Escape":
        e.preventDefault();
        searchInput.value = "";
        performSearch("");
        searchInput.focus();
        break;
    }
  };

  const updateSelectedItem = (resultItems) => {
    resultItems.forEach((item, index) => {
      if (index === selectedIndex) {
        item.classList.add("sidebar-search__result-item--selected");
        item.setAttribute("aria-selected", "true");
        item.setAttribute("tabindex", "0");
        item.focus();
        // Scroll al item seleccionado
        item.scrollIntoView({ behavior: "smooth", block: "nearest" });
      } else {
        item.classList.remove("sidebar-search__result-item--selected");
        item.setAttribute("aria-selected", "false");
        item.setAttribute("tabindex", "-1");
      }
    });
  };

  // Event listeners
  // Búsqueda con debounce (300ms)
  let searchTimeout;
  searchInput.addEventListener("input", (e) => {
    const query = e.target.value;

    // Mostrar/ocultar botón clear
    if (searchClear) {
      searchClear.style.display = query.length > 0 ? "flex" : "none";
    }

    // Debounce: esperar 300ms antes de buscar
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      performSearch(query);
      selectedIndex = -1; // Reset selección
    }, 300);
  });

  // Limpiar búsqueda
  if (searchClear) {
    searchClear.addEventListener("click", () => {
      searchInput.value = "";
      performSearch("");
      searchInput.focus();
      if (searchClear) {
        searchClear.style.display = "none";
      }
    });
  }

  // Navegación por teclado
  searchInput.addEventListener("keydown", handleKeyboardNavigation);

  // Cerrar resultados al hacer click fuera
  document.addEventListener("click", (e) => {
    const searchContainer = document.getElementById("sidebar-search-container");
    if (searchContainer && !searchContainer.contains(e.target)) {
      // No cerrar si se hace click en un resultado
      if (!searchResults.contains(e.target)) {
        // Opcional: mantener resultados visibles hasta que se seleccione uno
        // searchResults.style.display = "none";
      }
    }
  });

  // Actualizar items cuando cambia la navegación (ej: se expanden subsecciones)
  const updateNavItems = () => {
    allNavItems = getAllNavItems();
    // Si hay búsqueda activa, actualizar resultados
    if (searchInput.value.trim().length > 0) {
      performSearch(searchInput.value);
    }
  };

  // Observar cambios en la navegación (subsecciones expandidas/colapsadas)
  const navObserver = new MutationObserver(() => {
    updateNavItems();
  });

  navObserver.observe(sidebarNav, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ["data-collapsed", "style"],
  });

  // Procesar iconos del input de búsqueda
  if (typeof IconHelper !== "undefined") {
    IconHelper.processDataIcons(searchInput.closest(".sidebar-search"), {
      lazy: false,
    });
  }
}

function initPagePreload() {
  // Páginas frecuentes según rol (detectado desde el sidebar visible)
  const frequentPages = {
    empresa: [
      "/dashboard",
      "/calendar",
      "/staffing-requests",
      "/assignments",
      "/work-orders",
      "/approvals",
    ],
    temporera: [
      "/dashboard",
      "/staffing-proposals",
      "/agency-workers",
      "/assignments",
    ],
    owner: ["/owner/dashboard", "/owner/tenants", "/owner/billing"],
  };

  // Detectar rol actual desde el sidebar visible
  let currentRole = null;
  const empresaSection = document.getElementById("empresa-section");
  const temporeraSection = document.getElementById("temporera-section");
  const ownerSection = document.getElementById("owner-section");

  if (empresaSection && !empresaSection.hasAttribute("aria-hidden")) {
    currentRole = "empresa";
  } else if (
    temporeraSection &&
    !temporeraSection.hasAttribute("aria-hidden")
  ) {
    currentRole = "temporera";
  } else if (ownerSection && !ownerSection.hasAttribute("aria-hidden")) {
    currentRole = "owner";
  }

  if (!currentRole || !frequentPages[currentRole]) return;

  // NAV-135: Preload después de que la página esté completamente cargada
  // y cuando el usuario esté inactivo (idle)
  let preloadTimeout = null;
  let isPreloading = false;

  const preloadPages = () => {
    if (isPreloading) return;
    isPreloading = true;

    const pages = frequentPages[currentRole];
    const BASE_URL = window.BASE_URL || "";

    // Preload solo las primeras 3 páginas más frecuentes
    pages.slice(0, 3).forEach((page) => {
      const link = document.createElement("link");
      link.rel = "prefetch";
      link.href = `${BASE_URL}${page}`;
      link.as = "document";
      document.head.appendChild(link);
    });

    console.log(
      "[NAV-135] Páginas frecuentes preloadadas para rol:",
      currentRole
    );
  };

  // Preload después de 2 segundos de inactividad (cuando la página está cargada)
  const schedulePreload = () => {
    clearTimeout(preloadTimeout);
    preloadTimeout = setTimeout(() => {
      if (document.readyState === "complete") {
        preloadPages();
      }
    }, 2000);
  };

  // Preload cuando la página esté completamente cargada
  if (document.readyState === "complete") {
    schedulePreload();
  } else {
    window.addEventListener("load", schedulePreload);
  }

  // NAV-135: También preload cuando el usuario está inactivo (mouse/keyboard)
  let idleTimeout = null;
  const resetIdleTimer = () => {
    clearTimeout(idleTimeout);
    idleTimeout = setTimeout(() => {
      if (!isPreloading && document.readyState === "complete") {
        preloadPages();
      }
    }, 3000); // 3 segundos de inactividad
  };

  // Resetear timer en eventos de usuario
  ["mousemove", "keydown", "scroll", "touchstart"].forEach((event) => {
    document.addEventListener(event, resetIdleTimer, { passive: true });
  });

  // Iniciar timer después de carga completa
  if (document.readyState === "complete") {
    resetIdleTimer();
  } else {
    window.addEventListener("load", resetIdleTimer);
  }
}

/**
 * MOB-140: Inicializa pull to refresh en todas las páginas (solo en móvil)
 * Esta función se ejecuta en layout.js que se carga en todas las páginas
 */
function initPullToRefresh() {
  // Solo en móvil
  if (window.innerWidth >= 992) return;

  // Esperar a que PullToRefresh esté disponible (cargado por components-loader)
  // Usar un pequeño delay para asegurar que los componentes estén cargados
  setTimeout(() => {
    if (typeof PullToRefresh === "undefined") {
      console.warn("[Layout] PullToRefresh no disponible, reintentando...");
      // Reintentar después de 500ms
      setTimeout(initPullToRefresh, 500);
      return;
    }

    // Buscar el contenedor principal de la página
    // Prioridad: #main-content > .main-content > main > body
    let container = document.getElementById("main-content");

    if (!container) {
      container = document.querySelector(".main-content");
    }

    if (!container) {
      container = document.querySelector("main");
    }

    if (!container) {
      container = document.body;
    }

    if (!container) {
      console.warn("[Layout] No se encontró contenedor para pull-to-refresh");
      return;
    }

    // Verificar si ya tiene pull-to-refresh inicializado
    // (por ejemplo, en dashboard.js o data-table.js)
    if (container.hasAttribute("data-pull-to-refresh-initialized")) {
      console.log(
        "[Layout] Pull-to-refresh ya inicializado en este contenedor"
      );
      return;
    }

    // Marcar como inicializado
    container.setAttribute("data-pull-to-refresh-initialized", "true");

    // Función de refresh genérica
    // Si la página define window.refreshPage, usarla (permite refresh sin recargar página)
    // Si no, recargar la página completa
    const onRefresh = async () => {
      if (typeof window.refreshPage === "function") {
        try {
          await window.refreshPage();
        } catch (error) {
          console.error("[Layout] Error en refreshPage:", error);
          // Si falla, recargar la página como fallback
          window.location.reload();
        }
      } else {
        // Por defecto, recargar la página
        window.location.reload();
      }
    };

    // Inicializar pull-to-refresh
    try {
      PullToRefresh.init(container, {
        onRefresh: onRefresh,
        threshold: 80,
        resistance: 2.5,
      });

      console.log(
        "[Layout] Pull-to-refresh inicializado en",
        container.id || container.className || container.tagName
      );
    } catch (error) {
      console.error("[Layout] Error al inicializar pull-to-refresh:", error);
    }
  }, 100);
}

/**
 * Cierra sesión
 */
async function logout() {
  // Usar Modal component si está disponible, sino usar confirm nativo como fallback
  let confirmed = false;

  if (typeof Modal !== "undefined") {
    confirmed = await Modal.confirm({
      title: "Cerrar Sesión",
      message: "¿Está seguro de cerrar sesión?",
      confirmLabel: "Cerrar Sesión",
      cancelLabel: "Cancelar",
    });
  } else {
    confirmed = confirm("¿Está seguro de cerrar sesión?");
  }

  if (confirmed) {
    if (typeof API !== "undefined" && API.stopAutoRefresh) {
      API.stopAutoRefresh();
    }
    localStorage.removeItem("access_token");
    localStorage.removeItem("refresh_token");
    localStorage.removeItem("tenant_id");
    localStorage.removeItem("user");
    localStorage.removeItem("user_role");
    window.location.href = `${window.BASE_URL || ""}/login`;
  }
}

// Exportar para uso global
window.logout = logout;
