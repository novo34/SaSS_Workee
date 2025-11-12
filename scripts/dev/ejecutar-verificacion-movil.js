/**
 * Script Helper para Ejecutar Verificación Móvil - Workee
 *
 * Copia y pega este código completo en la consola del navegador
 * cuando estés en la aplicación principal (no en el checklist HTML)
 *
 * Asegúrate de estar en modo móvil (Ctrl+Shift+M en Chrome DevTools)
 */

(function () {
  "use strict";

  console.log("🚀 Iniciando verificación móvil...\n");

  // Verificar que estamos en la aplicación principal
  if (window.location.pathname.includes("mobile-testing-checklist.html")) {
    console.error(
      "❌ Este script debe ejecutarse en la aplicación principal, no en el checklist HTML."
    );
    console.log(
      "📋 Abre la aplicación principal (ej: http://localhost/SaSS_Workee/) y ejecuta este script allí."
    );
    return;
  }

  // Función para cargar y ejecutar el script
  function loadAndRun() {
    // Verificar si ya está cargado
    if (typeof MobileTestingVerification !== "undefined") {
      console.log("✅ Script ya está cargado. Ejecutando verificación...\n");
      MobileTestingVerification.runAll();
      return;
    }

    // Cargar el script
    console.log("📥 Cargando script de verificación...");
    const script = document.createElement("script");

    // Intentar diferentes rutas posibles
    const possiblePaths = [
      "/SaSS_Workee/scripts/dev/mobile-testing-verification.js",
      "/scripts/dev/mobile-testing-verification.js",
      "scripts/dev/mobile-testing-verification.js",
      "../scripts/dev/mobile-testing-verification.js",
    ];

    let currentPathIndex = 0;

    function tryLoadScript() {
      if (currentPathIndex >= possiblePaths.length) {
        console.error("❌ No se pudo cargar el script desde ninguna ruta.");
        console.log("💡 Rutas intentadas:", possiblePaths);
        console.log(
          "💡 Asegúrate de que el archivo existe en: scripts/dev/mobile-testing-verification.js"
        );
        console.log("💡 O carga el script manualmente con la ruta correcta.");
        return;
      }

      script.src = possiblePaths[currentPathIndex];
      console.log(`🔄 Intentando cargar desde: ${script.src}`);

      script.onload = () => {
        console.log("✅ Script cargado correctamente desde:", script.src);
        console.log("🚀 Ejecutando verificación...\n");

        // Esperar un momento para que el script se inicialice
        setTimeout(() => {
          if (typeof MobileTestingVerification !== "undefined") {
            MobileTestingVerification.runAll();
          } else {
            console.error(
              "❌ El script se cargó pero MobileTestingVerification no está disponible."
            );
          }
        }, 100);
      };

      script.onerror = () => {
        console.warn(`⚠️ No se pudo cargar desde: ${script.src}`);
        currentPathIndex++;
        tryLoadScript();
      };

      document.head.appendChild(script);
    }

    tryLoadScript();
  }

  // Verificar modo móvil
  const isMobile = window.innerWidth < 992;
  if (!isMobile) {
    console.warn(
      "⚠️ ADVERTENCIA: El viewport es >= 992px. Para resultados precisos, activa el modo móvil:"
    );
    console.log("   1. Presiona Ctrl+Shift+M (o Cmd+Shift+M en Mac)");
    console.log("   2. Selecciona un dispositivo móvil (ej: iPhone 12)");
    console.log("   3. Ejecuta este script nuevamente\n");
  } else {
    console.log(
      `✅ Viewport móvil detectado: ${window.innerWidth}x${window.innerHeight}px\n`
    );
  }

  // Cargar y ejecutar
  loadAndRun();

  // Guardar función helper para uso futuro
  window.runMobileVerification = loadAndRun;
  console.log(
    '\n💡 Tip: Puedes ejecutar "runMobileVerification()" en cualquier momento para volver a ejecutar la verificación.'
  );
})();
