/**
 * JavaScript para Panel de Configuración (REFACTORIZADO)
 * Mobile-first, modular
 *
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications
 */

/**
 * Espera a que los componentes estén cargados
 */
async function waitForComponents() {
  const maxAttempts = 50; // 5 segundos máximo
  let attempts = 0;

  while (attempts < maxAttempts) {
    if (typeof API !== "undefined" && typeof Toast !== "undefined") {
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
  // Esperar a que los componentes estén cargados
  const componentsReady = await waitForComponents();
  if (!componentsReady) {
    console.error(
      "No se pueden cargar los componentes. Algunas funcionalidades pueden no estar disponibles."
    );
  }

  // Inicializar componentes UI comunes (tooltips e iconos)
  if (typeof UIInitializer !== 'undefined') {
    UIInitializer.init();
  }

  loadSettings();
  setupEventListeners();
});

/**
 * Configura event listeners
 */
function setupEventListeners() {
  const formGeneral = document.getElementById("form-general");
  const formTimeclock = document.getElementById("form-timeclock");
  const formApprovals = document.getElementById("form-approvals");
  const formNotifications = document.getElementById("form-notifications");

  if (formGeneral) formGeneral.addEventListener("submit", saveGeneralSettings);
  if (formTimeclock)
    formTimeclock.addEventListener("submit", saveTimeclockSettings);
  if (formApprovals)
    formApprovals.addEventListener("submit", saveApprovalSettings);
  if (formNotifications)
    formNotifications.addEventListener("submit", saveNotificationSettings);
}

/**
 * Carga configuración actual
 */
async function loadSettings() {
  // Verificar que API esté disponible
  if (typeof API === "undefined") {
    console.error("API no está disponible. Esperando componentes...");
    const componentsReady = await waitForComponents();
    if (!componentsReady) {
      console.error("No se pudieron cargar los componentes necesarios.");
      return;
    }
  }

  try {
    const response = await API.get("/config");

    if (response && response.success && response.data) {
      populateSettings(response.data);
    }
  } catch (error) {
    console.error("Error cargando configuración:", error);
    if (typeof Toast !== "undefined") {
      Toast.error("Error al cargar configuración");
    }
  }
}

/**
 * Pobla formularios con configuración
 */
function populateSettings(config) {
  // General
  const settingTimezone = document.getElementById("setting-timezone");
  const settingLocale = document.getElementById("setting-locale");
  const settingCurrency = document.getElementById("setting-currency");

  if (settingTimezone && config.timezone)
    settingTimezone.value = config.timezone;
  if (settingLocale && config.locale) settingLocale.value = config.locale;
  if (settingCurrency && config.currency)
    settingCurrency.value = config.currency;

  // Fichaje
  const timeclockRequired = document.getElementById("timeclock-required");
  const timeclockTolerance = document.getElementById("timeclock-tolerance");
  const timeclockMinBreak = document.getElementById("timeclock-min-break");
  const timeclockRounding = document.getElementById("timeclock-rounding");

  if (timeclockRequired && config["timeclock.required"] !== undefined) {
    timeclockRequired.checked = config["timeclock.required"];
  }
  if (timeclockTolerance && config["timeclock.tolerance_minutes"]) {
    timeclockTolerance.value = config["timeclock.tolerance_minutes"];
  }
  if (timeclockMinBreak && config["timeclock.min_break_minutes"]) {
    timeclockMinBreak.value = config["timeclock.min_break_minutes"];
  }
  if (timeclockRounding && config["timeclock.rounding_minutes"]) {
    timeclockRounding.value = config["timeclock.rounding_minutes"];
  }

  // Aprobaciones
  const approvalStages = document.getElementById("approval-stages");
  const approvalCoApproval = document.getElementById("approval-co-approval");
  const approvalAutoThreshold = document.getElementById(
    "approval-auto-threshold"
  );

  if (approvalStages && config["approval.stages"]) {
    approvalStages.value = config["approval.stages"];
  }
  if (
    approvalCoApproval &&
    config["approval.require_co_approval_agency"] !== undefined
  ) {
    approvalCoApproval.checked = config["approval.require_co_approval_agency"];
  }
  if (
    approvalAutoThreshold &&
    config["approval.auto_approve_threshold_hours"]
  ) {
    approvalAutoThreshold.value =
      config["approval.auto_approve_threshold_hours"];
  }

  // Notificaciones
  const notifEmail = document.getElementById("notif-email");
  const notifSms = document.getElementById("notif-sms");
  const notifWhatsapp = document.getElementById("notif-whatsapp");
  const notifQuietStart = document.getElementById("notif-quiet-start");
  const notifQuietEnd = document.getElementById("notif-quiet-end");

  if (notifEmail && config["notifications.email_enabled"] !== undefined) {
    notifEmail.checked = config["notifications.email_enabled"];
  }
  if (notifSms && config["notifications.sms_enabled"] !== undefined) {
    notifSms.checked = config["notifications.sms_enabled"];
  }
  if (notifWhatsapp && config["notifications.whatsapp_enabled"] !== undefined) {
    notifWhatsapp.checked = config["notifications.whatsapp_enabled"];
  }
  if (notifQuietStart && config["notifications.quiet_hours_start"]) {
    notifQuietStart.value = config["notifications.quiet_hours_start"];
  }
  if (notifQuietEnd && config["notifications.quiet_hours_end"]) {
    notifQuietEnd.value = config["notifications.quiet_hours_end"];
  }
}

/**
 * Guarda configuración general
 */
async function saveGeneralSettings(e) {
  e.preventDefault();

  const settingTimezone = document.getElementById("setting-timezone");
  const settingLocale = document.getElementById("setting-locale");
  const settingCurrency = document.getElementById("setting-currency");

  const data = {
    timezone: settingTimezone?.value,
    locale: settingLocale?.value,
    currency: settingCurrency?.value,
  };

  await saveSettings("general", data);
}

/**
 * Guarda configuración de fichaje
 */
async function saveTimeclockSettings(e) {
  e.preventDefault();

  const timeclockRequired = document.getElementById("timeclock-required");
  const timeclockTolerance = document.getElementById("timeclock-tolerance");
  const timeclockMinBreak = document.getElementById("timeclock-min-break");
  const timeclockRounding = document.getElementById("timeclock-rounding");

  const data = {
    "timeclock.required": timeclockRequired?.checked,
    "timeclock.tolerance_minutes": parseInt(timeclockTolerance?.value) || 0,
    "timeclock.min_break_minutes": parseInt(timeclockMinBreak?.value) || 0,
    "timeclock.rounding_minutes": parseInt(timeclockRounding?.value) || 0,
  };

  await saveSettings("timeclock", data);
}

/**
 * Guarda configuración de aprobaciones
 */
async function saveApprovalSettings(e) {
  e.preventDefault();

  const approvalStages = document.getElementById("approval-stages");
  const approvalCoApproval = document.getElementById("approval-co-approval");
  const approvalAutoThreshold = document.getElementById(
    "approval-auto-threshold"
  );

  const data = {
    "approval.stages": parseInt(approvalStages?.value) || 1,
    "approval.require_co_approval_agency": approvalCoApproval?.checked,
    "approval.auto_approve_threshold_hours":
      parseInt(approvalAutoThreshold?.value) || 0,
  };

  await saveSettings("approvals", data);
}

/**
 * Guarda configuración de notificaciones
 */
async function saveNotificationSettings(e) {
  e.preventDefault();

  const notifEmail = document.getElementById("notif-email");
  const notifSms = document.getElementById("notif-sms");
  const notifWhatsapp = document.getElementById("notif-whatsapp");
  const notifQuietStart = document.getElementById("notif-quiet-start");
  const notifQuietEnd = document.getElementById("notif-quiet-end");

  const data = {
    "notifications.email_enabled": notifEmail?.checked,
    "notifications.sms_enabled": notifSms?.checked,
    "notifications.whatsapp_enabled": notifWhatsapp?.checked,
    "notifications.quiet_hours_start": notifQuietStart?.value || null,
    "notifications.quiet_hours_end": notifQuietEnd?.value || null,
  };

  await saveSettings("notifications", data);
}

/**
 * Guarda configuración en el servidor
 */
async function saveSettings(section, data) {
  // Verificar que API esté disponible
  if (typeof API === "undefined") {
    console.error("API no está disponible");
    if (typeof Toast !== "undefined") {
      Toast.error("Error: API no disponible");
    }
    return;
  }

  try {
    console.log(`[Settings] Guardando configuración de sección: ${section}`, data);
    
    const response = await API.put('/config', data);
    
    if (response && response.success) {
      console.log('[Settings] Configuración guardada correctamente');
      if (typeof Toast !== "undefined") {
        Toast.success("Configuración guardada correctamente");
      }
    } else {
      console.error('[Settings] Error al guardar configuración:', response);
      if (typeof Toast !== "undefined") {
        Toast.error(response?.message || response?.error || 'Error al guardar configuración');
      }
    }
  } catch (error) {
    console.error("[Settings] Error:", error);
    if (typeof Toast !== "undefined") {
      Toast.error("Error de conexión. Verifica que el servidor esté ejecutándose.");
    }
  }
}
