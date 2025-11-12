/**
 * JavaScript para Dashboard (REFACTORIZADO)
 * Mobile-first, modular
 *
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications (si es necesario)
 * - Modal component (para confirmaciones)
 */

console.log("[Dashboard] Cargando script de dashboard...");

// Obtener base URL desde window.BASE_URL (inyectado por index.php) o usar default
const BASE_URL = (typeof window !== "undefined" && window.BASE_URL) || "";

console.log("[Dashboard] BASE_URL:", BASE_URL);

// Usar API client centralizado en lugar de variables locales duplicadas
let user = null;

/**
 * Espera a que los componentes estén cargados
 */
async function waitForComponents() {
  const maxAttempts = 50; // 5 segundos máximo
  let attempts = 0;

  while (attempts < maxAttempts) {
    if (typeof API !== "undefined" && typeof Modal !== "undefined") {
      return true;
    }
    await new Promise((resolve) => setTimeout(resolve, 100));
    attempts++;
  }

  console.error(
    "Componentes no cargados después de 5 segundos. Asegúrate de incluir components-loader.js"
  );
  return false;
}

// Inicialización
document.addEventListener("DOMContentLoaded", async () => {
  console.log("[Dashboard] Inicializando dashboard...");

  // Cargar información del usuario desde localStorage (solo para mostrar en UI)
  const userStr = localStorage.getItem("user");
  if (userStr) {
    try {
      user = JSON.parse(userStr);
    } catch (e) {
      console.error("Error parsing user data:", e);
    }
  }

  // Verificar tokens directamente desde localStorage primero (más confiable)
  const accessToken = localStorage.getItem("access_token");
  const refreshToken = localStorage.getItem("refresh_token");
  const tenantId = localStorage.getItem("tenant_id");

  console.log("[Dashboard] Verificando tokens desde localStorage...");
  console.log("[Dashboard] Access token presente:", !!accessToken);
  console.log("[Dashboard] Refresh token presente:", !!refreshToken);
  console.log("[Dashboard] Tenant ID presente:", !!tenantId);

  if (!accessToken) {
    console.error(
      "[Dashboard] No hay access token en localStorage, redirigiendo a login"
    );
    window.location.href = `${BASE_URL}/login`;
    return;
  }

  // Esperar a que el API client esté disponible y haya cargado los tokens
  console.log("[Dashboard] Esperando a que el API client esté disponible...");
  let apiReady = false;
  let attempts = 0;
  const maxAttempts = 50; // 5 segundos máximo

  while (!apiReady && attempts < maxAttempts) {
    if (typeof API !== "undefined") {
      // Forzar recarga de tokens en el API client
      API.loadTokens();

      if (API.accessToken) {
        console.log("[Dashboard] API client tiene tokens, continuando...");
        apiReady = true;
        break;
      }
    }
    await new Promise((resolve) => setTimeout(resolve, 100));
    attempts++;
  }

  if (!apiReady || !API.accessToken) {
    console.error(
      "[Dashboard] API client no está disponible o no tiene tokens después de esperar"
    );
    console.error("[Dashboard] API disponible:", typeof API !== "undefined");
    console.error(
      "[Dashboard] API.accessToken:",
      API?.accessToken ? "presente" : "ausente"
    );
    console.error("[Dashboard] Redirigiendo a login...");
    window.location.href = `${BASE_URL}/login`;
    return;
  }

  console.log("[Dashboard] Token encontrado, continuando...");

  // Esperar a que los componentes estén cargados
  const componentsReady = await waitForComponents();
  if (!componentsReady) {
    console.error(
      "[Dashboard] No se pueden cargar los componentes. Algunas funcionalidades pueden no estar disponibles."
    );
  } else {
    console.log("[Dashboard] Componentes cargados correctamente");
  }

  // Asegurar que el auto-refresh esté activo
  // NOTA: No llamar API.loadTokens() aquí porque puede causar carga duplicada de traducciones
  // El API client ya carga los tokens en su constructor
  if (typeof API !== "undefined" && API.initAutoRefresh) {
    console.log("[Dashboard] Verificando auto-refresh del token...");
    // Solo verificar e inicializar auto-refresh si hay tokens, sin recargar tokens
    if (API.accessToken && API.refreshToken) {
      API.initAutoRefresh(); // Iniciar o verificar auto-refresh
    } else {
      console.warn(
        "[Dashboard] API client no tiene tokens, puede haber un problema"
      );
    }
  } else {
    console.warn("[Dashboard] API client no está disponible aún");
  }

  loadUserInfo();
  loadTenantInfo();
  loadActiveRequests();
  initializeQuickActions();

  // MOB-141: Lazy loading agresivo de datos secundarios (achievements, task progress)
  if (window.innerWidth < 992) {
    // En móvil, cargar datos secundarios solo cuando sean visibles
    initLazyLoadSecondaryData();
  } else {
    // En desktop, cargar inmediatamente
    initializeEngagement();
  }

  // MOB-140: Inicializar pull to refresh en dashboard (solo en móvil)
  // Nota: layout.js también inicializa pull-to-refresh, pero aquí usamos una función de refresh específica
  if (window.innerWidth < 992 && typeof PullToRefresh !== "undefined") {
    const mainContent = document.getElementById("main-content");
    if (
      mainContent &&
      !mainContent.hasAttribute("data-pull-to-refresh-initialized")
    ) {
      // Marcar como inicializado para evitar duplicación
      mainContent.setAttribute("data-pull-to-refresh-initialized", "true");

      PullToRefresh.init(mainContent, {
        onRefresh: async () => {
          // Recargar todos los datos del dashboard (sin recargar la página completa)
          loadUserInfo();
          loadTenantInfo();
          loadActiveRequests();
          initializeEngagement();
        },
        threshold: 80,
        resistance: 2.5,
      });

      console.log(
        "[Dashboard] Pull-to-refresh inicializado con función de refresh específica"
      );
    } else if (
      mainContent &&
      mainContent.hasAttribute("data-pull-to-refresh-initialized")
    ) {
      // Si ya está inicializado por layout.js, reemplazar con nuestra función específica
      // Esto requiere que PullToRefresh devuelva un objeto con método destroy
      console.log(
        "[Dashboard] Pull-to-refresh ya inicializado, usando función de refresh específica"
      );
      // Crear función global para que layout.js la use si existe
      window.refreshPage = async () => {
        loadUserInfo();
        loadTenantInfo();
        loadActiveRequests();
        initializeEngagement();
      };
    }
  }
});

// Función loadTokens() eliminada - usar API client centralizado
// El API client ya maneja la carga de tokens en su constructor

/**
 * Carga información del usuario
 */
function loadUserInfo() {
  if (user) {
    const welcomeMsg = document.getElementById("welcome-message");
    if (welcomeMsg) {
      welcomeMsg.textContent = `${user.first_name || ""} ${
        user.last_name || ""
      } (${user.email || ""})`;
    }
  }
}

/**
 * Carga información del tenant
 */
async function loadTenantInfo() {
  console.log("[Dashboard] loadTenantInfo() llamado");
  console.log("[Dashboard] API disponible:", typeof API !== "undefined");
  console.log("[Dashboard] API.tenantId:", API?.tenantId);
  console.log("[Dashboard] API.accessToken:", !!API?.accessToken);

  // Usar API client centralizado en lugar de variables locales
  if (!API || !API.tenantId || !API.accessToken) {
    console.warn(
      "[Dashboard] loadTenantInfo: No hay API, tenantId o accessToken, saliendo"
    );
    return;
  }

  // Verificar que API esté disponible
  if (typeof API === "undefined") {
    console.error("API no está disponible. Esperando componentes...");
    const componentsReady = await waitForComponents();
    if (!componentsReady) {
      // Fallback: mostrar solo el ID
      const tenantInfo = document.getElementById("tenant-info");
      if (tenantInfo && API && API.tenantId) {
        tenantInfo.innerHTML = `
                    <strong>Tenant ID: ${API.tenantId}</strong><br>
                    <small class="text-muted">No se pudieron cargar los componentes necesarios</small>
                `;
      }
      return;
    }
  }

  // MOB-143: Cache agresivo de datos de dashboard
  if (window.innerWidth < 992 && typeof PerformanceMobile !== "undefined") {
    try {
      const cached = await PerformanceMobile.getCachedData(
        `tenant_${API.tenantId}`,
        async () => {
          const response = await API.get(`/tenants/${API.tenantId}`);
          return response;
        },
        5 * 60 * 1000 // 5 minutos de cache (tenant info cambia poco)
      );

      if (cached && cached.success && cached.data) {
        const tenantInfo = document.getElementById("tenant-info");
        if (tenantInfo) {
          const orgType =
            cached.data.org_type === "empresa" ? "Empresa" : "Temporera";
          tenantInfo.innerHTML = `
                    <strong>${escapeHtml(
                      cached.data.name || "N/A"
                    )}</strong><br>
                    <small class="text-muted">${orgType} - ${escapeHtml(
            cached.data.plan || "N/A"
          )}</small>
                `;
        }
        return;
      }
    } catch (error) {
      console.warn("[Dashboard] Error en cache, cargando desde API:", error);
    }
  }

  try {
    console.log(`[Dashboard] Haciendo petición GET /tenants/${API.tenantId}`);
    const response = await API.get(`/tenants/${API.tenantId}`);
    console.log("[Dashboard] Respuesta de loadTenantInfo:", response);

    if (response && response.success && response.data) {
      const tenantInfo = document.getElementById("tenant-info");
      if (tenantInfo) {
        const orgType =
          response.data.org_type === "empresa" ? "Empresa" : "Temporera";
        tenantInfo.innerHTML = `
                    <strong>${escapeHtml(
                      response.data.name || "N/A"
                    )}</strong><br>
                    <small class="text-muted">${orgType} - ${escapeHtml(
          response.data.plan || "N/A"
        )}</small>
                `;
      }
    } else {
      // Si falla, mostrar solo el ID
      const tenantInfo = document.getElementById("tenant-info");
      if (tenantInfo) {
        tenantInfo.innerHTML = `
                    <strong>Tenant ID: ${API.tenantId}</strong><br>
                    <small class="text-muted">No se pudo cargar información completa</small>
                `;
      }
    }
  } catch (error) {
    console.error("[Dashboard] ========================================");
    console.error("[Dashboard] Error cargando información del tenant:", error);
    console.error("[Dashboard] Tipo de error:", error?.constructor?.name);
    console.error("[Dashboard] Mensaje de error:", error?.message);
    console.error("[Dashboard] Stack trace:", error?.stack);
    console.error("[Dashboard] Tenant ID en error:", API?.tenantId);
    console.error("[Dashboard] ========================================");
    // Fallback: mostrar solo el ID
    const tenantInfo = document.getElementById("tenant-info");
    if (tenantInfo && API && API.tenantId) {
      tenantInfo.innerHTML = `
                <strong>Tenant ID: ${API.tenantId}</strong><br>
                <small class="text-muted">Error al cargar información</small>
            `;
    }
  }
}

/**
 * Cierra sesión
 */
async function logout() {
  if (typeof Modal === "undefined") {
    // Si Modal no está disponible, cerrar sesión directamente
    if (typeof API !== "undefined" && API.stopAutoRefresh) {
      API.stopAutoRefresh();
    }
    localStorage.removeItem("access_token");
    localStorage.removeItem("refresh_token");
    localStorage.removeItem("tenant_id");
    localStorage.removeItem("user");
    window.location.href = `${BASE_URL}/login`;
    return;
  }

  const confirmed = await Modal.confirm({
    title: "Cerrar Sesión",
    message: "¿Está seguro de cerrar sesión?",
    confirmLabel: "Cerrar Sesión",
    cancelLabel: "Cancelar",
  });

  if (confirmed) {
    if (typeof API !== "undefined" && API.stopAutoRefresh) {
      API.stopAutoRefresh();
    }
    localStorage.removeItem("access_token");
    localStorage.removeItem("refresh_token");
    localStorage.removeItem("tenant_id");
    localStorage.removeItem("user");
    window.location.href = `${BASE_URL}/login`;
  }
}

/**
 * Carga solicitudes activas para KPI Card
 */
async function loadActiveRequests() {
  console.log("[Dashboard] loadActiveRequests() llamado");
  console.log("[Dashboard] API.tenantId:", API?.tenantId);
  console.log("[Dashboard] API.accessToken:", !!API?.accessToken);

  if (!API || !API.tenantId || !API.accessToken) {
    console.warn(
      "[Dashboard] loadActiveRequests: No hay API, tenantId o accessToken, saliendo"
    );
    return;
  }

  // Verificar que API esté disponible
  if (typeof API === "undefined") {
    const componentsReady = await waitForComponents();
    if (!componentsReady) {
      return;
    }
  }

  // MOB-143: Cache agresivo de datos de dashboard
  if (window.innerWidth < 992 && typeof PerformanceMobile !== "undefined") {
    try {
      const cached = await PerformanceMobile.getCachedData(
        "active_requests",
        async () => {
          const response = await API.get("/staffing-requests", {
            status: "pending,open",
            page: 1,
            page_size: 1,
          });
          return response;
        },
        2 * 60 * 1000 // 2 minutos de cache
      );

      if (cached && cached.success && cached.data) {
        const kpiValue = document.getElementById("kpi-active-requests");
        if (kpiValue) {
          kpiValue.textContent = cached.data.total || 0;
        }
        const trend = document.getElementById("kpi-requests-trend");
        if (trend && cached.data.total > 0) {
          trend.classList.remove(
            "kpi-card__trend--up",
            "kpi-card__trend--down",
            "kpi-card__trend--neutral"
          );
          trend.classList.add("kpi-card__trend--up");
        }
        return;
      }
    } catch (error) {
      console.warn("[Dashboard] Error en cache, cargando desde API:", error);
    }
  }

  try {
    console.log(
      "[Dashboard] Haciendo petición GET /staffing-requests con filtros..."
    );
    console.log("[Dashboard] Tenant ID antes de petición:", API.tenantId);
    const response = await API.get("/staffing-requests", {
      status: "pending,open",
      page: 1,
      page_size: 1,
    });
    console.log("[Dashboard] Respuesta de loadActiveRequests:", response);
    console.log("[Dashboard] Tenant ID después de petición:", API.tenantId);

    if (response && response.success && response.data) {
      const kpiValue = document.getElementById("kpi-active-requests");
      if (kpiValue) {
        kpiValue.textContent = response.data.total || 0;
      }

      // Actualizar trend (ejemplo: comparar con período anterior)
      // Por ahora, mostrar un trend estático
      const trend = document.getElementById("kpi-requests-trend");
      if (trend && response.data.total > 0) {
        trend.classList.remove(
          "kpi-card__trend--up",
          "kpi-card__trend--down",
          "kpi-card__trend--neutral"
        );
        trend.classList.add("kpi-card__trend--up");
      }
    }
  } catch (error) {
    console.error("[Dashboard] ========================================");
    console.error("[Dashboard] Error cargando solicitudes activas:", error);
    console.error("[Dashboard] Tipo de error:", error?.constructor?.name);
    console.error("[Dashboard] Mensaje de error:", error?.message);
    console.error("[Dashboard] Stack trace:", error?.stack);
    console.error("[Dashboard] Tenant ID en error:", API?.tenantId);
    console.error("[Dashboard] ========================================");
    // Si falla, mostrar 0
    const kpiValue = document.getElementById("kpi-active-requests");
    if (kpiValue) {
      kpiValue.textContent = "0";
    }
  }
}

/**
 * Inicializa componentes de engagement
 */
async function initializeEngagement() {
  // Esperar a que los componentes estén cargados
  await waitForEngagementComponents();

  // Inicializar sistema de logros
  if (typeof Achievements !== "undefined") {
    const achievements = Achievements.init();
    achievements.updateAchievementBadges();

    // Verificar logros automáticos basados en acciones del usuario
    checkAutomaticAchievements(achievements);
  }

  // Inicializar progreso de tareas
  if (typeof TaskProgress !== "undefined") {
    initializeTaskProgress();
  }
}

/**
 * Espera a que los componentes de engagement estén cargados
 */
async function waitForEngagementComponents() {
  const maxAttempts = 50;
  let attempts = 0;

  while (attempts < maxAttempts) {
    if (
      typeof Achievements !== "undefined" &&
      typeof TaskProgress !== "undefined"
    ) {
      return;
    }
    await new Promise((resolve) => setTimeout(resolve, 100));
    attempts++;
  }
}

/**
 * Verifica logros automáticos basados en acciones del usuario
 */
function checkAutomaticAchievements(achievements) {
  // Verificar si es primera vez en el dashboard
  const firstVisit = !localStorage.getItem("dashboard_visited");
  if (firstVisit) {
    localStorage.setItem("dashboard_visited", "true");
    achievements.showAchievement(
      "first_dashboard_visit",
      "Primera Visita",
      "Has visitado el dashboard por primera vez",
      "👋"
    );
  }

  // Verificar si hay solicitudes activas (logro de actividad)
  const activeRequests = document.getElementById("kpi-active-requests");
  if (activeRequests && parseInt(activeRequests.textContent) > 0) {
    if (!achievements.hasAchievement("first_active_request")) {
      achievements.showAchievement(
        "first_active_request",
        "Primera Solicitud Activa",
        "Tienes tu primera solicitud activa",
        "📋"
      );
    }
  }
}

/**
 * Inicializa el progreso de tareas
 */
function initializeTaskProgress() {
  const container = document.getElementById("task-progress-container");
  if (!container) return;

  // Obtener progreso desde localStorage o API
  const taskProgress = getTaskProgress();

  if (typeof TaskProgress !== "undefined") {
    const progress = new TaskProgress(container, {
      total: taskProgress.total || 10,
      completed: taskProgress.completed || 0,
      milestones: [3, 5, 10],
      title: "Tareas Completadas",
      showMilestones: true,
      onComplete: () => {
        if (typeof Achievements !== "undefined") {
          const achievements = Achievements.init();
          achievements.showAchievement(
            "tasks_completed",
            "Tareas Completadas",
            "Has completado todas las tareas",
            "✅"
          );
        }
      },
    });

    // Guardar referencia para actualizar después
    window.taskProgressInstance = progress;
  }
}

/**
 * MOB-141: Inicializa lazy loading para datos secundarios (achievements, task progress)
 */
function initLazyLoadSecondaryData() {
  // Usar IntersectionObserver para cargar datos secundarios solo cuando sean visibles
  if (typeof IntersectionObserver === "undefined") {
    // Fallback: cargar inmediatamente si IntersectionObserver no está disponible
    initializeEngagement();
    return;
  }

  const engagementSection =
    document
      .querySelector("[data-achievements-container]")
      ?.closest(".card-modern") ||
    document.getElementById("task-progress-container")?.closest(".card-modern");

  if (!engagementSection) {
    // Si no hay sección de engagement, cargar inmediatamente
    initializeEngagement();
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          // Cuando la sección es visible, cargar datos
          initializeEngagement();
          observer.unobserve(entry.target);
        }
      });
    },
    {
      rootMargin: "100px", // Cargar 100px antes de que sea visible
    }
  );

  observer.observe(engagementSection);
}

/**
 * Obtiene el progreso de tareas
 */
function getTaskProgress() {
  const stored = localStorage.getItem("workee_task_progress");
  if (stored) {
    try {
      return JSON.parse(stored);
    } catch (e) {
      return { total: 10, completed: 0 };
    }
  }
  return { total: 10, completed: 0 };
}

/**
 * Actualiza el progreso de tareas
 */
function updateTaskProgress(completed) {
  const progress = getTaskProgress();
  progress.completed = completed;
  localStorage.setItem("workee_task_progress", JSON.stringify(progress));

  if (window.taskProgressInstance) {
    window.taskProgressInstance.setProgress(completed);
  }
}

/**
 * Inicializa quick actions
 */
function initializeQuickActions() {
  // Botón para mostrar atajos de teclado
  const btnShowShortcuts = document.getElementById("btn-show-shortcuts");
  if (btnShowShortcuts) {
    btnShowShortcuts.addEventListener("click", () => {
      if (typeof KeyboardShortcuts !== "undefined") {
        KeyboardShortcuts.init().showHelp();
      }
    });
  }

  // Inicializar componentes UI comunes (tooltips e iconos)
  if (typeof UIInitializer !== "undefined") {
    UIInitializer.init();
  } else {
    // Fallback si UIInitializer no está disponible
    if (typeof Tooltip !== "undefined") {
      Tooltip.init("[data-tooltip]");
    }
    if (typeof IconHelper !== "undefined") {
      const iconElements = document.querySelectorAll("[data-icon]");
      iconElements.forEach((el) => {
        const iconName = el.getAttribute("data-icon");
        if (iconName) {
          const iconHtml = IconHelper.get(iconName, {
            className: el.className,
            size: el.classList.contains("icon-lg")
              ? "lg"
              : el.classList.contains("icon-sm")
              ? "sm"
              : "md",
          });
          if (iconHtml) {
            el.innerHTML = iconHtml;
          }
        }
      });
    }
  }
}

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}
