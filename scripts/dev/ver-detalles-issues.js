/**
 * Script Helper para Ver Detalles de Issues - Workee
 *
 * Ejecuta este código en la consola después de ejecutar MobileTestingVerification.runAll()
 * para ver los detalles de los issues encontrados
 */

(function () {
  "use strict";

  if (!window.mobileTestingResults) {
    console.error(
      "❌ No hay resultados disponibles. Ejecuta primero: MobileTestingVerification.runAll()"
    );
    return;
  }

  const results = window.mobileTestingResults;

  console.log("📊 DETALLES DE ISSUES ENCONTRADOS\n");
  console.log("================================\n");

  // Font Sizes Issues
  if (results.fontSizes && results.fontSizes.issues.length > 0) {
    console.log("❌ FONT SIZES MENORES A 16px:\n");
    results.fontSizes.issues.forEach((issue, index) => {
      console.log(`${index + 1}. Selector: ${issue.selector}`);
      console.log(`   Font Size: ${issue.fontSize}`);
      console.log(`   Texto: "${issue.text}"`);
      console.log(`   Elemento:`, issue.element);
      console.log("");
    });
  }

  // Spacing Issues
  if (results.spacing && results.spacing.issues.length > 0) {
    console.log("❌ ESPACIADO INSUFICIENTE (< 8px):\n");
    results.spacing.issues.forEach((issue, index) => {
      console.log(`${index + 1}. Elemento 1: ${issue.element1}`);
      console.log(`   Elemento 2: ${issue.element2}`);
      console.log(`   Espaciado: ${issue.spacing}`);
      console.log(`   Dirección: ${issue.direction}`);
      console.log("");
    });
  }

  // Animations Issues
  if (results.animations && results.animations.issues.length > 0) {
    console.log("⚠️ ANIMACIONES QUE PODRÍAN OPTIMIZARSE:\n");
    results.animations.issues.forEach((issue, index) => {
      console.log(`${index + 1}. Selector: ${issue.selector}`);
      console.log(`   Transition: ${issue.transition}`);
      console.log(`   Nota: ${issue.note}`);
      console.log(`   Elemento:`, issue.element);
      console.log("");
    });
  }

  // Resumen
  console.log("\n📈 RESUMEN:\n");
  console.log(
    `✅ Touch Targets: ${results.touchTargets.passed} passed, ${results.touchTargets.failed} failed`
  );
  console.log(
    `${results.fontSizes.failed > 0 ? "❌" : "✅"} Font Sizes: ${
      results.fontSizes.passed
    } passed, ${results.fontSizes.failed} failed`
  );
  console.log(
    `${results.spacing.failed > 0 ? "❌" : "✅"} Spacing: ${
      results.spacing.passed
    } passed, ${results.spacing.failed} failed`
  );
  console.log(
    `✅ Lazy Loading: ${results.lazyLoading.passed} passed, ${results.lazyLoading.failed} failed`
  );
  console.log(
    `✅ Responsive Images: ${results.responsiveImages.passed} passed, ${results.responsiveImages.failed} failed`
  );
  console.log(
    `${results.animations.failed > 0 ? "⚠️" : "✅"} Animations: ${
      results.animations.passed
    } passed, ${results.animations.failed} failed`
  );
  console.log(
    `✅ Accessibility: ${results.accessibility.passed} passed, ${results.accessibility.failed} failed`
  );

  // Guardar detalles en un objeto accesible
  window.mobileTestingDetails = {
    fontSizes: results.fontSizes.issues,
    spacing: results.spacing.issues,
    animations: results.animations.issues,
  };

  console.log("\n💾 Detalles guardados en window.mobileTestingDetails");
  console.log(
    "💡 Ejecuta: console.log(window.mobileTestingDetails) para ver los detalles"
  );
})();
