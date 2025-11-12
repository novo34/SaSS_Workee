/**
 * Mobile Testing Verification Script - Workee
 *
 * Script para verificar automáticamente aspectos técnicos del testing móvil
 * MOB-148: Testing Móvil Completo
 *
 * Ejecutar en consola del navegador en modo móvil (Chrome DevTools Device Toolbar)
 */

(function () {
  "use strict";

  const MobileTestingVerification = {
    results: {
      touchTargets: { passed: 0, failed: 0, issues: [] },
      fontSizes: { passed: 0, failed: 0, issues: [] },
      spacing: { passed: 0, failed: 0, issues: [] },
      lazyLoading: { passed: 0, failed: 0, issues: [] },
      responsiveImages: { passed: 0, failed: 0, issues: [] },
      animations: { passed: 0, failed: 0, issues: [] },
      accessibility: { passed: 0, failed: 0, issues: [] },
    },

    /**
     * Verifica que todos los touch targets sean >= 44px
     */
    verifyTouchTargets() {
      console.log("🔍 Verificando touch targets...");
      const issues = [];
      const interactiveElements = document.querySelectorAll(
        'button, a, input, select, textarea, [role="button"], [tabindex="0"], .btn, .btn-modern, .nav-link'
      );

      interactiveElements.forEach((el) => {
        // Saltar elementos ocultos
        if (el.offsetWidth === 0 && el.offsetHeight === 0) return;
        if (window.getComputedStyle(el).display === "none") return;

        const rect = el.getBoundingClientRect();
        const minSize = 44; // 44px mínimo

        if (rect.width < minSize || rect.height < minSize) {
          issues.push({
            element: el,
            width: Math.round(rect.width),
            height: Math.round(rect.height),
            selector: this.getElementSelector(el),
            text:
              el.textContent?.substring(0, 30) || el.alt || el.title || "N/A",
          });
        }
      });

      this.results.touchTargets.failed = issues.length;
      this.results.touchTargets.issues = issues;

      if (issues.length > 0) {
        console.warn(
          `❌ ${issues.length} touch targets menores a 44px encontrados:`,
          issues
        );
        return false;
      }

      console.log(
        `✅ Todos los touch targets son >= 44px (${interactiveElements.length} elementos verificados)`
      );
      this.results.touchTargets.passed = interactiveElements.length;
      return true;
    },

    /**
     * Verifica que todos los font sizes sean >= 16px (para evitar zoom en iOS)
     */
    verifyFontSizes() {
      console.log("🔍 Verificando font sizes...");
      const issues = [];
      const textElements = document.querySelectorAll(
        "body, p, span, div, label, button, a, input, textarea, select, .text, .text-normal"
      );

      textElements.forEach((el) => {
        // Saltar elementos ocultos o sin texto
        if (el.offsetHeight === 0) return;
        const style = window.getComputedStyle(el);
        if (style.display === "none" || style.visibility === "hidden") return;

        const computed = window.getComputedStyle(el);
        const fontSize = parseFloat(computed.fontSize);
        const minSize = 16; // 16px mínimo para evitar zoom en iOS

        // Elementos permitidos con font-size < 16px (no causan zoom en iOS)
        const allowedSmallText = [
          "text-xs",
          "text-extra-small",
          "badge",
          "badge-modern",
          "text-small",
          "text-muted",
          "small",
          "small",
          "nav-badge",
          "fab-badge",
          "toast__close",
        ];

        const hasAllowedClass = allowedSmallText.some((cls) =>
          el.classList.contains(cls)
        );
        const isBadge =
          el.tagName === "SPAN" &&
          (el.classList.contains("badge") ||
            el.classList.contains("badge-modern"));
        const isSmall = el.tagName === "SMALL";

        // Verificar si es un pseudo-elemento (::before, ::after) - estos son decorativos
        // Los pseudo-elementos no tienen offsetParent, pero podemos detectarlos por su contenido
        const isPseudoElement =
          el.offsetParent === null &&
          el.offsetWidth === 0 &&
          el.offsetHeight === 0;

        // Verificar si es un separador decorativo (solo contiene un carácter como /, >, etc.)
        const text = el.textContent?.trim() || el.value || "";
        const isSeparator = text.length <= 2 && /^[\/\>\|\-\.]+$/.test(text);

        // Permitir texto extra pequeño solo si es explícitamente permitido
        if (
          fontSize < minSize &&
          !hasAllowedClass &&
          !isBadge &&
          !isSmall &&
          !isSeparator
        ) {
          // Verificar que realmente tiene texto visible
          if (text.length > 0) {
            // Verificar que no es un elemento decorativo (solo iconos, etc.)
            const hasOnlyIcons =
              el.querySelectorAll('svg, .icon, [class*="icon"]').length > 0 &&
              text.length < 5;
            if (!hasOnlyIcons) {
              issues.push({
                element: el,
                fontSize: fontSize + "px",
                selector: this.getElementSelector(el),
                text: text.substring(0, 30),
                classes: Array.from(el.classList).join(", "),
              });
            }
          }
        }
      });

      this.results.fontSizes.failed = issues.length;
      this.results.fontSizes.issues = issues;

      if (issues.length > 0) {
        console.warn(
          `❌ ${issues.length} font sizes menores a 16px encontrados:`,
          issues
        );
        return false;
      }

      console.log(
        `✅ Todos los font sizes son >= 16px (${textElements.length} elementos verificados)`
      );
      this.results.fontSizes.passed = textElements.length;
      return true;
    },

    /**
     * Verifica espaciado mínimo de 8px entre elementos táctiles
     */
    verifySpacing() {
      console.log("🔍 Verificando espaciado...");
      const issues = [];
      const interactiveElements = Array.from(
        document.querySelectorAll(
          'button, a, input, select, [role="button"], .btn, .btn-modern'
        )
      ).filter((el) => {
        // Solo elementos visibles
        const style = window.getComputedStyle(el);
        return (
          style.display !== "none" && el.offsetWidth > 0 && el.offsetHeight > 0
        );
      });

      const minSpacing = 8; // 8px mínimo

      for (let i = 1; i < interactiveElements.length; i++) {
        const prevEl = interactiveElements[i - 1];
        const currEl = interactiveElements[i];

        const rect1 = prevEl.getBoundingClientRect();
        const rect2 = currEl.getBoundingClientRect();

        // Saltar si los elementos están muy separados (no son adyacentes)
        const distance = Math.sqrt(
          Math.pow(rect2.left - rect1.right, 2) +
            Math.pow(rect2.top - rect1.bottom, 2)
        );
        if (distance > 100) continue; // Elementos muy separados, no verificar

        // Verificar si están en la misma fila/columna
        const sameRow = Math.abs(rect1.top - rect2.top) < 10;
        const sameCol = Math.abs(rect1.left - rect2.left) < 10;

        // Saltar si están en diferentes contenedores (ej: diferentes cards)
        const prevContainer = prevEl.closest(
          ".card, .card-modern, .modal, .dropdown-menu"
        );
        const currContainer = currEl.closest(
          ".card, .card-modern, .modal, .dropdown-menu"
        );
        if (prevContainer !== currContainer && prevContainer && currContainer)
          continue;

        if (sameRow) {
          // Misma fila: verificar espaciado horizontal
          const spacing = rect2.left - rect1.right;
          if (spacing > 0 && spacing < minSpacing) {
            // Verificar que no son elementos dentro del mismo grupo (ej: botones en un btn-group)
            const isSameGroup =
              prevEl.closest(".btn-group, .input-group, .form-group") ===
              currEl.closest(".btn-group, .input-group, .form-group");
            if (!isSameGroup) {
              issues.push({
                element1: this.getElementSelector(prevEl),
                element2: this.getElementSelector(currEl),
                spacing: Math.round(spacing) + "px",
                direction: "horizontal",
              });
            }
          }
        } else if (sameCol) {
          // Misma columna: verificar espaciado vertical
          const spacing = rect2.top - rect1.bottom;
          if (spacing > 0 && spacing < minSpacing) {
            // Verificar que no son elementos dentro del mismo grupo
            const isSameGroup =
              prevEl.closest(".btn-group, .input-group, .form-group") ===
              currEl.closest(".btn-group, .input-group, .form-group");
            if (!isSameGroup) {
              issues.push({
                element1: this.getElementSelector(prevEl),
                element2: this.getElementSelector(currEl),
                spacing: Math.round(spacing) + "px",
                direction: "vertical",
              });
            }
          }
        }
      }

      this.results.spacing.failed = issues.length;
      this.results.spacing.issues = issues;

      if (issues.length > 0) {
        console.warn(
          `❌ ${issues.length} casos de espaciado insuficiente encontrados:`,
          issues
        );
        return false;
      }

      console.log(
        `✅ Espaciado adecuado entre elementos (${interactiveElements.length} elementos verificados)`
      );
      this.results.spacing.passed = interactiveElements.length;
      return true;
    },

    /**
     * Verifica que lazy loading esté implementado correctamente
     */
    verifyLazyLoading() {
      console.log("🔍 Verificando lazy loading...");
      const issues = [];
      const images = document.querySelectorAll("img");
      const viewportHeight = window.innerHeight;

      images.forEach((img) => {
        const isAboveFold = img.offsetTop < viewportHeight * 2;
        const hasLazy =
          img.hasAttribute("loading") && img.getAttribute("loading") === "lazy";
        const hasDataSrc = img.hasAttribute("data-src");

        // Imágenes fuera del viewport inicial deben tener lazy loading
        if (!isAboveFold && !hasLazy && !hasDataSrc) {
          issues.push({
            element: img,
            src: (img.src || img.getAttribute("src") || "N/A").substring(0, 50),
            offsetTop: Math.round(img.offsetTop),
            viewportHeight: viewportHeight,
          });
        }
      });

      this.results.lazyLoading.failed = issues.length;
      this.results.lazyLoading.issues = issues;

      if (issues.length > 0) {
        console.warn(
          `❌ ${issues.length} imágenes sin lazy loading encontradas:`,
          issues
        );
        return false;
      }

      console.log(
        `✅ Lazy loading implementado correctamente (${images.length} imágenes verificadas)`
      );
      this.results.lazyLoading.passed = images.length;
      return true;
    },

    /**
     * Verifica que las imágenes sean responsivas
     */
    verifyResponsiveImages() {
      console.log("🔍 Verificando imágenes responsivas...");
      const issues = [];
      const images = document.querySelectorAll("img");

      images.forEach((img) => {
        const hasSrcset = img.hasAttribute("srcset");
        const hasSizes = img.hasAttribute("sizes");
        const hasAspectRatio =
          img.hasAttribute("data-aspect-ratio") || img.style.aspectRatio;
        const isInPicture = img.closest("picture");

        // Verificar que imágenes importantes tengan srcset o estén en picture
        if (!hasSrcset && !isInPicture && img.offsetWidth > 200) {
          issues.push({
            element: img,
            src: (img.src || "N/A").substring(0, 50),
            width: img.offsetWidth,
            hasSrcset: false,
            hasSizes: hasSizes,
            hasAspectRatio: !!hasAspectRatio,
          });
        }
      });

      this.results.responsiveImages.failed = issues.length;
      this.results.responsiveImages.issues = issues;

      if (issues.length > 0) {
        console.warn(
          `⚠️ ${issues.length} imágenes sin srcset encontradas (recomendado, no crítico):`,
          issues
        );
        return false;
      }

      console.log(
        `✅ Imágenes responsivas verificadas (${images.length} imágenes verificadas)`
      );
      this.results.responsiveImages.passed = images.length;
      return true;
    },

    /**
     * Verifica que las animaciones sean optimizadas
     */
    verifyAnimations() {
      console.log("🔍 Verificando animaciones...");
      const issues = [];
      const animatedElements = document.querySelectorAll(
        '[style*="transition"], [style*="animation"], .card-modern, .btn-modern'
      );

      animatedElements.forEach((el) => {
        const computed = window.getComputedStyle(el);
        const transition = computed.transition;
        const animation = computed.animation;

        // Verificar que las animaciones usen transform/opacity
        if (
          transition &&
          !transition.includes("transform") &&
          !transition.includes("opacity")
        ) {
          // Permitir transiciones de color para algunos casos
          if (
            !transition.includes("color") &&
            !transition.includes("background")
          ) {
            issues.push({
              element: el,
              selector: this.getElementSelector(el),
              transition: transition,
              note: "Considerar usar transform/opacity para mejor performance",
            });
          }
        }
      });

      this.results.animations.failed = issues.length;
      this.results.animations.issues = issues;

      if (issues.length > 0) {
        console.warn(
          `⚠️ ${issues.length} animaciones podrían optimizarse:`,
          issues
        );
        return false;
      }

      console.log(
        `✅ Animaciones verificadas (${animatedElements.length} elementos verificados)`
      );
      this.results.animations.passed = animatedElements.length;
      return true;
    },

    /**
     * Verifica aspectos básicos de accesibilidad
     */
    verifyAccessibility() {
      console.log("🔍 Verificando accesibilidad básica...");
      const issues = [];

      // Verificar que botones tengan texto o aria-label
      const buttons = document.querySelectorAll('button, [role="button"]');
      buttons.forEach((btn) => {
        const hasText = btn.textContent?.trim().length > 0;
        const hasAriaLabel = btn.hasAttribute("aria-label");
        const hasTitle = btn.hasAttribute("title");

        if (!hasText && !hasAriaLabel && !hasTitle) {
          issues.push({
            element: btn,
            selector: this.getElementSelector(btn),
            issue: "Botón sin texto ni aria-label",
          });
        }
      });

      // Verificar que imágenes tengan alt
      const images = document.querySelectorAll("img");
      images.forEach((img) => {
        if (!img.hasAttribute("alt") && !img.hasAttribute("aria-hidden")) {
          issues.push({
            element: img,
            selector: this.getElementSelector(img),
            issue: "Imagen sin alt attribute",
          });
        }
      });

      this.results.accessibility.failed = issues.length;
      this.results.accessibility.issues = issues;

      if (issues.length > 0) {
        console.warn(
          `⚠️ ${issues.length} issues de accesibilidad encontrados:`,
          issues
        );
        return false;
      }

      console.log(`✅ Accesibilidad básica verificada`);
      this.results.accessibility.passed = buttons.length + images.length;
      return true;
    },

    /**
     * Obtiene un selector único para un elemento
     */
    getElementSelector(el) {
      if (el.id) return `#${el.id}`;
      if (el.className) {
        const classes = Array.from(el.classList).slice(0, 2).join(".");
        return `${el.tagName.toLowerCase()}${classes ? "." + classes : ""}`;
      }
      return el.tagName.toLowerCase();
    },

    /**
     * Ejecuta todas las verificaciones
     */
    runAll() {
      console.log("🚀 Iniciando verificación completa de testing móvil...\n");
      console.log(
        `📱 Viewport: ${window.innerWidth}x${window.innerHeight}px\n`
      );

      const results = {
        touchTargets: this.verifyTouchTargets(),
        fontSizes: this.verifyFontSizes(),
        spacing: this.verifySpacing(),
        lazyLoading: this.verifyLazyLoading(),
        responsiveImages: this.verifyResponsiveImages(),
        animations: this.verifyAnimations(),
        accessibility: this.verifyAccessibility(),
      };

      console.log("\n📊 RESUMEN DE VERIFICACIÓN:");
      console.log("========================");

      Object.keys(results).forEach((key) => {
        const result = results[key];
        const icon = result ? "✅" : "❌";
        console.log(`${icon} ${key}: ${result ? "PASSED" : "FAILED"}`);
      });

      const allPassed = Object.values(results).every((r) => r);
      console.log(
        `\n${allPassed ? "✅" : "❌"} VERIFICACIÓN GENERAL: ${
          allPassed ? "PASSED" : "FAILED"
        }`
      );

      // Guardar resultados para inspección
      window.mobileTestingResults = this.results;
      console.log("\n💾 Resultados guardados en window.mobileTestingResults");

      return allPassed;
    },
  };

  // Exportar para uso global
  if (typeof window !== "undefined") {
    window.MobileTestingVerification = MobileTestingVerification;

    // Auto-ejecutar si se carga en consola
    if (typeof console !== "undefined") {
      console.log("📱 Mobile Testing Verification cargado");
      console.log("Ejecutar: MobileTestingVerification.runAll()");
    }
  }
})();
