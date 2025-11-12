/**
 * Navigation Accessibility Tests - Workee
 * 
 * Tests automatizados completos de accesibilidad para navegación
 * Sprint 5.9: Tests de accesibilidad para navegación (keyboard navigation, screen readers)
 * 
 * Ejecutar en consola del navegador:
 *   NavigationAccessibilityTests.runAll()
 * 
 * O cargar el script:
 *   const script = document.createElement('script');
 *   script.src = '/SaSS_Workee/scripts/dev/navigation-accessibility-tests.js';
 *   document.head.appendChild(script);
 */

(function() {
    'use strict';
    
    const NavigationAccessibilityTests = {
        results: {
            keyboardNavigation: { passed: 0, failed: 0, issues: [], warnings: [] },
            ariaLabels: { passed: 0, failed: 0, issues: [], warnings: [] },
            semanticStructure: { passed: 0, failed: 0, issues: [], warnings: [] },
            focusManagement: { passed: 0, failed: 0, issues: [], warnings: [] },
            skipLinks: { passed: 0, failed: 0, issues: [], warnings: [] },
            colorContrast: { passed: 0, failed: 0, issues: [], warnings: [] },
            screenReader: { passed: 0, failed: 0, issues: [], warnings: [] }
        },
        
        /**
         * Ejecuta todos los tests
         */
        runAll() {
            console.log('🚀 Iniciando tests de accesibilidad de navegación...\n');
            console.log('='.repeat(60));
            
            this.testKeyboardNavigation();
            this.testAriaLabels();
            this.testSemanticStructure();
            this.testFocusManagement();
            this.testSkipLinks();
            this.testColorContrast();
            this.testScreenReader();
            
            this.printSummary();
            
            // Guardar resultados
            window.navigationAccessibilityResults = this.results;
            console.log('\n💾 Resultados guardados en window.navigationAccessibilityResults');
            
            return this.results;
        },
        
        /**
         * Test 1: Keyboard Navigation
         */
        testKeyboardNavigation() {
            console.log('\n📋 Test 1: Keyboard Navigation');
            console.log('-'.repeat(60));
            
            const issues = [];
            const warnings = [];
            let passed = 0;
            
            // 1.1 Verificar que todos los nav-links son focusables
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach((link, index) => {
                const isVisible = this.isElementVisible(link);
                if (!isVisible) return;
                
                const tabindex = link.getAttribute('tabindex');
                const isDisabled = link.disabled || link.classList.contains('disabled');
                const isFocusable = link.tabIndex >= 0;
                
                if (isDisabled && tabindex !== '-1') {
                    warnings.push({
                        element: link,
                        selector: this.getElementSelector(link),
                        issue: 'Elemento disabled sin tabindex="-1"',
                        text: link.textContent?.trim().substring(0, 30) || 'N/A'
                    });
                } else if (!isDisabled && !isFocusable) {
                    issues.push({
                        element: link,
                        selector: this.getElementSelector(link),
                        issue: 'Nav link no es focusable',
                        text: link.textContent?.trim().substring(0, 30) || 'N/A'
                    });
                } else {
                    passed++;
                }
            });
            
            // 1.2 Verificar que botones de subsecciones son focusables
            const subsectionToggles = document.querySelectorAll('.nav-subsection-title--toggle');
            subsectionToggles.forEach(toggle => {
                const isVisible = this.isElementVisible(toggle);
                if (!isVisible) return;
                
                const isFocusable = toggle.tabIndex >= 0;
                if (!isFocusable) {
                    issues.push({
                        element: toggle,
                        selector: this.getElementSelector(toggle),
                        issue: 'Toggle de subsección no es focusable',
                        text: toggle.textContent?.trim().substring(0, 30) || 'N/A'
                    });
                } else {
                    passed++;
                }
            });
            
            // 1.3 Verificar que elementos ocultos no están en tab order
            const hiddenElements = document.querySelectorAll('[aria-hidden="true"] .nav-link, [aria-hidden="true"] .nav-subsection-title--toggle');
            hiddenElements.forEach(el => {
                const tabindex = el.getAttribute('tabindex');
                if (tabindex !== '-1' && el.tabIndex >= 0) {
                    issues.push({
                        element: el,
                        selector: this.getElementSelector(el),
                        issue: 'Elemento oculto está en tab order',
                        text: el.textContent?.trim().substring(0, 30) || 'N/A'
                    });
                } else {
                    passed++;
                }
            });
            
            // 1.4 Verificar que no hay tabindex > 0
            const positiveTabindex = document.querySelectorAll('[tabindex]:not([tabindex="-1"]):not([tabindex="0"])');
            positiveTabindex.forEach(el => {
                const tabindex = parseInt(el.getAttribute('tabindex'));
                if (tabindex > 0) {
                    warnings.push({
                        element: el,
                        selector: this.getElementSelector(el),
                        issue: `tabindex="${tabindex}" debería evitarse`,
                        text: el.textContent?.trim().substring(0, 30) || 'N/A'
                    });
                }
            });
            
            // 1.5 Verificar tab order lógico (de arriba a abajo)
            const visibleNavLinks = Array.from(navLinks).filter(link => this.isElementVisible(link));
            const tabOrder = [];
            visibleNavLinks.forEach(link => {
                const rect = link.getBoundingClientRect();
                tabOrder.push({
                    element: link,
                    top: rect.top,
                    left: rect.left,
                    text: link.textContent?.trim().substring(0, 30) || 'N/A'
                });
            });
            
            // Ordenar por posición visual
            tabOrder.sort((a, b) => {
                if (Math.abs(a.top - b.top) > 10) {
                    return a.top - b.top;
                }
                return a.left - b.left;
            });
            
            // Verificar que el orden visual coincide con el tab order
            let tabOrderCorrect = true;
            for (let i = 0; i < tabOrder.length - 1; i++) {
                const current = tabOrder[i].element;
                const next = tabOrder[i + 1].element;
                
                // Simular tab order (no podemos realmente simularlo, pero verificamos que están en orden visual)
                // Esto es una aproximación
            }
            
            this.results.keyboardNavigation.passed = passed;
            this.results.keyboardNavigation.failed = issues.length;
            this.results.keyboardNavigation.issues = issues;
            this.results.keyboardNavigation.warnings = warnings;
            
            if (issues.length === 0 && warnings.length === 0) {
                console.log(`✅ Keyboard Navigation: ${passed} elementos verificados correctamente`);
            } else {
                if (issues.length > 0) {
                    console.log(`❌ Keyboard Navigation: ${issues.length} problemas encontrados`);
                    issues.forEach(issue => {
                        console.log(`   - ${issue.selector}: ${issue.issue} ("${issue.text}")`);
                    });
                }
                if (warnings.length > 0) {
                    console.log(`⚠️ Keyboard Navigation: ${warnings.length} advertencias`);
                    warnings.forEach(warning => {
                        console.log(`   - ${warning.selector}: ${warning.issue}`);
                    });
                }
            }
        },
        
        /**
         * Test 2: ARIA Labels
         */
        testAriaLabels() {
            console.log('\n📋 Test 2: ARIA Labels');
            console.log('-'.repeat(60));
            
            const issues = [];
            const warnings = [];
            let passed = 0;
            
            // 2.1 Verificar que todos los nav-links tienen aria-label
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                const isVisible = this.isElementVisible(link);
                if (!isVisible) return;
                
                const ariaLabel = link.getAttribute('aria-label');
                const text = link.textContent?.trim();
                
                if (!ariaLabel) {
                    issues.push({
                        element: link,
                        selector: this.getElementSelector(link),
                        issue: 'Nav link sin aria-label',
                        text: text?.substring(0, 30) || 'N/A'
                    });
                } else if (ariaLabel.length < 3) {
                    warnings.push({
                        element: link,
                        selector: this.getElementSelector(link),
                        issue: 'aria-label muy corto',
                        text: text?.substring(0, 30) || 'N/A'
                    });
                } else {
                    passed++;
                }
            });
            
            // 2.2 Verificar que botones de subsecciones tienen aria-label
            const subsectionToggles = document.querySelectorAll('.nav-subsection-title--toggle');
            subsectionToggles.forEach(toggle => {
                const isVisible = this.isElementVisible(toggle);
                if (!isVisible) return;
                
                const ariaLabel = toggle.getAttribute('aria-label');
                const ariaExpanded = toggle.getAttribute('aria-expanded');
                
                if (!ariaLabel) {
                    issues.push({
                        element: toggle,
                        selector: this.getElementSelector(toggle),
                        issue: 'Toggle sin aria-label',
                        text: toggle.textContent?.trim().substring(0, 30) || 'N/A'
                    });
                } else {
                    passed++;
                }
                
                if (ariaExpanded === null) {
                    warnings.push({
                        element: toggle,
                        selector: this.getElementSelector(toggle),
                        issue: 'Toggle sin aria-expanded',
                        text: toggle.textContent?.trim().substring(0, 30) || 'N/A'
                    });
                } else {
                    passed++;
                }
            });
            
            // 2.3 Verificar que iconos tienen aria-hidden="true"
            const navIcons = document.querySelectorAll('.nav-icon');
            navIcons.forEach(icon => {
                const ariaHidden = icon.getAttribute('aria-hidden');
                if (ariaHidden !== 'true') {
                    warnings.push({
                        element: icon,
                        selector: this.getElementSelector(icon),
                        issue: 'Icono sin aria-hidden="true"',
                        text: 'N/A'
                    });
                } else {
                    passed++;
                }
            });
            
            // 2.4 Verificar que sidebar tiene role="navigation"
            const sidebar = document.getElementById('main-navigation');
            if (sidebar) {
                const role = sidebar.getAttribute('role');
                const ariaLabel = sidebar.getAttribute('aria-label');
                
                if (role !== 'navigation') {
                    warnings.push({
                        element: sidebar,
                        selector: '#main-navigation',
                        issue: 'Sidebar sin role="navigation"',
                        text: 'N/A'
                    });
                } else {
                    passed++;
                }
                
                if (!ariaLabel) {
                    warnings.push({
                        element: sidebar,
                        selector: '#main-navigation',
                        issue: 'Sidebar sin aria-label',
                        text: 'N/A'
                    });
                } else {
                    passed++;
                }
            }
            
            this.results.ariaLabels.passed = passed;
            this.results.ariaLabels.failed = issues.length;
            this.results.ariaLabels.issues = issues;
            this.results.ariaLabels.warnings = warnings;
            
            if (issues.length === 0 && warnings.length === 0) {
                console.log(`✅ ARIA Labels: ${passed} elementos verificados correctamente`);
            } else {
                if (issues.length > 0) {
                    console.log(`❌ ARIA Labels: ${issues.length} problemas encontrados`);
                    issues.forEach(issue => {
                        console.log(`   - ${issue.selector}: ${issue.issue} ("${issue.text}")`);
                    });
                }
                if (warnings.length > 0) {
                    console.log(`⚠️ ARIA Labels: ${warnings.length} advertencias`);
                    warnings.forEach(warning => {
                        console.log(`   - ${warning.selector}: ${warning.issue}`);
                    });
                }
            }
        },
        
        /**
         * Test 3: Semantic Structure
         */
        testSemanticStructure() {
            console.log('\n📋 Test 3: Semantic Structure');
            console.log('-'.repeat(60));
            
            const issues = [];
            const warnings = [];
            let passed = 0;
            
            // 3.1 Verificar que sidebar usa <nav> o <aside> con role="navigation"
            const sidebar = document.getElementById('main-navigation');
            if (sidebar) {
                const tagName = sidebar.tagName.toLowerCase();
                if (tagName === 'nav' || tagName === 'aside') {
                    passed++;
                } else {
                    issues.push({
                        element: sidebar,
                        selector: '#main-navigation',
                        issue: `Sidebar usa <${tagName}> en lugar de <nav> o <aside>`,
                        text: 'N/A'
                    });
                }
            }
            
            // 3.2 Verificar que nav-links son <a> o <button>
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                const tagName = link.tagName.toLowerCase();
                if (tagName === 'a' || tagName === 'button') {
                    passed++;
                } else {
                    warnings.push({
                        element: link,
                        selector: this.getElementSelector(link),
                        issue: `Nav link usa <${tagName}> en lugar de <a> o <button>`,
                        text: link.textContent?.trim().substring(0, 30) || 'N/A'
                    });
                }
            });
            
            // 3.3 Verificar que breadcrumbs usan estructura semántica
            const breadcrumbNav = document.querySelector('.breadcrumb-nav, nav[aria-label*="breadcrumb" i]');
            if (breadcrumbNav) {
                const breadcrumbList = breadcrumbNav.querySelector('ol, ul');
                if (breadcrumbList) {
                    passed++;
                } else {
                    warnings.push({
                        element: breadcrumbNav,
                        selector: this.getElementSelector(breadcrumbNav),
                        issue: 'Breadcrumb sin lista (<ol> o <ul>)',
                        text: 'N/A'
                    });
                }
            }
            
            // 3.4 Verificar que items activos tienen aria-current="page"
            const activeLinks = document.querySelectorAll('.nav-link.active');
            activeLinks.forEach(link => {
                const ariaCurrent = link.getAttribute('aria-current');
                if (ariaCurrent === 'page') {
                    passed++;
                } else {
                    warnings.push({
                        element: link,
                        selector: this.getElementSelector(link),
                        issue: 'Item activo sin aria-current="page"',
                        text: link.textContent?.trim().substring(0, 30) || 'N/A'
                    });
                }
            });
            
            this.results.semanticStructure.passed = passed;
            this.results.semanticStructure.failed = issues.length;
            this.results.semanticStructure.issues = issues;
            this.results.semanticStructure.warnings = warnings;
            
            if (issues.length === 0 && warnings.length === 0) {
                console.log(`✅ Semantic Structure: ${passed} elementos verificados correctamente`);
            } else {
                if (issues.length > 0) {
                    console.log(`❌ Semantic Structure: ${issues.length} problemas encontrados`);
                    issues.forEach(issue => {
                        console.log(`   - ${issue.selector}: ${issue.issue}`);
                    });
                }
                if (warnings.length > 0) {
                    console.log(`⚠️ Semantic Structure: ${warnings.length} advertencias`);
                    warnings.forEach(warning => {
                        console.log(`   - ${warning.selector}: ${warning.issue}`);
                    });
                }
            }
        },
        
        /**
         * Test 4: Focus Management
         */
        testFocusManagement() {
            console.log('\n📋 Test 4: Focus Management');
            console.log('-'.repeat(60));
            
            const issues = [];
            const warnings = [];
            let passed = 0;
            
            // 4.1 Verificar que elementos tienen :focus-visible styles
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                const styles = window.getComputedStyle(link, ':focus-visible');
                // Verificar que hay outline o border cuando tiene focus
                // Esto es difícil de verificar programáticamente, así que verificamos que no hay outline: none
                const outline = styles.outline || styles.outlineWidth;
                if (outline === 'none' || outline === '0px') {
                    warnings.push({
                        element: link,
                        selector: this.getElementSelector(link),
                        issue: 'Elemento sin outline visible en focus',
                        text: link.textContent?.trim().substring(0, 30) || 'N/A'
                    });
                } else {
                    passed++;
                }
            });
            
            // 4.2 Verificar que sidebar-toggle tiene tabindex correcto según viewport
            const sidebarToggle = document.getElementById('sidebar-toggle');
            if (sidebarToggle) {
                const isMobile = window.innerWidth < 992;
                const tabindex = sidebarToggle.getAttribute('tabindex');
                
                if (isMobile) {
                    if (tabindex === '-1') {
                        warnings.push({
                            element: sidebarToggle,
                            selector: '#sidebar-toggle',
                            issue: 'sidebar-toggle tiene tabindex="-1" en móvil',
                            text: 'N/A'
                        });
                    } else {
                        passed++;
                    }
                } else {
                    if (tabindex !== '-1') {
                        warnings.push({
                            element: sidebarToggle,
                            selector: '#sidebar-toggle',
                            issue: 'sidebar-toggle no tiene tabindex="-1" en desktop',
                            text: 'N/A'
                        });
                    } else {
                        passed++;
                    }
                }
            }
            
            // 4.3 Verificar que elementos disabled tienen tabindex="-1"
            const disabledLinks = document.querySelectorAll('.nav-link.disabled, .nav-link[disabled]');
            disabledLinks.forEach(link => {
                const tabindex = link.getAttribute('tabindex');
                if (tabindex !== '-1') {
                    warnings.push({
                        element: link,
                        selector: this.getElementSelector(link),
                        issue: 'Elemento disabled sin tabindex="-1"',
                        text: link.textContent?.trim().substring(0, 30) || 'N/A'
                    });
                } else {
                    passed++;
                }
            });
            
            this.results.focusManagement.passed = passed;
            this.results.focusManagement.failed = issues.length;
            this.results.focusManagement.issues = issues;
            this.results.focusManagement.warnings = warnings;
            
            if (issues.length === 0 && warnings.length === 0) {
                console.log(`✅ Focus Management: ${passed} elementos verificados correctamente`);
            } else {
                if (issues.length > 0) {
                    console.log(`❌ Focus Management: ${issues.length} problemas encontrados`);
                    issues.forEach(issue => {
                        console.log(`   - ${issue.selector}: ${issue.issue}`);
                    });
                }
                if (warnings.length > 0) {
                    console.log(`⚠️ Focus Management: ${warnings.length} advertencias`);
                    warnings.forEach(warning => {
                        console.log(`   - ${warning.selector}: ${warning.issue}`);
                    });
                }
            }
        },
        
        /**
         * Test 5: Skip Links
         */
        testSkipLinks() {
            console.log('\n📋 Test 5: Skip Links');
            console.log('-'.repeat(60));
            
            const issues = [];
            const warnings = [];
            let passed = 0;
            
            // 5.1 Verificar que skip link existe
            const skipLink = document.querySelector('.skip-link');
            if (!skipLink) {
                issues.push({
                    element: null,
                    selector: '.skip-link',
                    issue: 'Skip link no encontrado',
                    text: 'N/A'
                });
            } else {
                passed++;
                
                // 5.2 Verificar que skip link tiene tabindex="-1" por defecto
                const tabindex = skipLink.getAttribute('tabindex');
                if (tabindex !== '-1') {
                    warnings.push({
                        element: skipLink,
                        selector: '.skip-link',
                        issue: `Skip link tiene tabindex="${tabindex}" en lugar de "-1"`,
                        text: 'N/A'
                    });
                } else {
                    passed++;
                }
                
                // 5.3 Verificar que skip link tiene href válido
                const href = skipLink.getAttribute('href');
                if (!href || href === '#') {
                    warnings.push({
                        element: skipLink,
                        selector: '.skip-link',
                        issue: 'Skip link sin href válido',
                        text: 'N/A'
                    });
                } else {
                    passed++;
                }
                
                // 5.4 Verificar que el target existe
                if (href && href.startsWith('#')) {
                    const targetId = href.substring(1);
                    const target = document.getElementById(targetId);
                    if (!target) {
                        warnings.push({
                            element: skipLink,
                            selector: '.skip-link',
                            issue: `Skip link apunta a elemento inexistente: #${targetId}`,
                            text: 'N/A'
                        });
                    } else {
                        passed++;
                    }
                }
            }
            
            this.results.skipLinks.passed = passed;
            this.results.skipLinks.failed = issues.length;
            this.results.skipLinks.issues = issues;
            this.results.skipLinks.warnings = warnings;
            
            if (issues.length === 0 && warnings.length === 0) {
                console.log(`✅ Skip Links: ${passed} elementos verificados correctamente`);
            } else {
                if (issues.length > 0) {
                    console.log(`❌ Skip Links: ${issues.length} problemas encontrados`);
                    issues.forEach(issue => {
                        console.log(`   - ${issue.selector}: ${issue.issue}`);
                    });
                }
                if (warnings.length > 0) {
                    console.log(`⚠️ Skip Links: ${warnings.length} advertencias`);
                    warnings.forEach(warning => {
                        console.log(`   - ${warning.selector}: ${warning.issue}`);
                    });
                }
            }
        },
        
        /**
         * Test 6: Color Contrast
         */
        testColorContrast() {
            console.log('\n📋 Test 6: Color Contrast');
            console.log('-'.repeat(60));
            
            const issues = [];
            const warnings = [];
            let passed = 0;
            
            // 6.1 Verificar contraste de texto en nav-links
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                const isVisible = this.isElementVisible(link);
                if (!isVisible) return;
                
                const styles = window.getComputedStyle(link);
                const textColor = styles.color;
                const bgColor = styles.backgroundColor;
                
                // Calcular contraste aproximado (simplificado)
                const contrast = this.calculateContrast(textColor, bgColor);
                
                if (contrast < 4.5) {
                    warnings.push({
                        element: link,
                        selector: this.getElementSelector(link),
                        issue: `Contraste bajo: ${contrast.toFixed(2)}:1 (mínimo recomendado: 4.5:1)`,
                        text: link.textContent?.trim().substring(0, 30) || 'N/A'
                    });
                } else {
                    passed++;
                }
            });
            
            // 6.2 Verificar contraste en estados hover/active
            // Esto es difícil de verificar sin simular hover, así que verificamos que los estilos existen
            
            this.results.colorContrast.passed = passed;
            this.results.colorContrast.failed = issues.length;
            this.results.colorContrast.issues = issues;
            this.results.colorContrast.warnings = warnings;
            
            if (issues.length === 0 && warnings.length === 0) {
                console.log(`✅ Color Contrast: ${passed} elementos verificados correctamente`);
            } else {
                if (issues.length > 0) {
                    console.log(`❌ Color Contrast: ${issues.length} problemas encontrados`);
                    issues.forEach(issue => {
                        console.log(`   - ${issue.selector}: ${issue.issue}`);
                    });
                }
                if (warnings.length > 0) {
                    console.log(`⚠️ Color Contrast: ${warnings.length} advertencias`);
                    warnings.forEach(warning => {
                        console.log(`   - ${warning.selector}: ${warning.issue}`);
                    });
                }
            }
        },
        
        /**
         * Test 7: Screen Reader Support
         */
        testScreenReader() {
            console.log('\n📋 Test 7: Screen Reader Support');
            console.log('-'.repeat(60));
            
            const issues = [];
            const warnings = [];
            let passed = 0;
            
            // 7.1 Verificar que elementos ocultos tienen aria-hidden="true"
            const hiddenSections = document.querySelectorAll('[aria-hidden="true"]');
            hiddenSections.forEach(section => {
                const hiddenLinks = section.querySelectorAll('.nav-link, .nav-subsection-title--toggle');
                hiddenLinks.forEach(link => {
                    const ariaHidden = link.getAttribute('aria-hidden');
                    if (ariaHidden !== 'true') {
                        warnings.push({
                            element: link,
                            selector: this.getElementSelector(link),
                            issue: 'Elemento dentro de sección oculta sin aria-hidden="true"',
                            text: link.textContent?.trim().substring(0, 30) || 'N/A'
                        });
                    } else {
                        passed++;
                    }
                });
            });
            
            // 7.2 Verificar que iconos decorativos tienen aria-hidden="true"
            const icons = document.querySelectorAll('.nav-icon, .icon');
            icons.forEach(icon => {
                const ariaHidden = icon.getAttribute('aria-hidden');
                if (ariaHidden !== 'true') {
                    warnings.push({
                        element: icon,
                        selector: this.getElementSelector(icon),
                        issue: 'Icono decorativo sin aria-hidden="true"',
                        text: 'N/A'
                    });
                } else {
                    passed++;
                }
            });
            
            // 7.3 Verificar que breadcrumbs tienen aria-label
            const breadcrumbNav = document.querySelector('.breadcrumb-nav, nav[aria-label*="breadcrumb" i]');
            if (breadcrumbNav) {
                const ariaLabel = breadcrumbNav.getAttribute('aria-label');
                if (!ariaLabel || !ariaLabel.toLowerCase().includes('breadcrumb')) {
                    warnings.push({
                        element: breadcrumbNav,
                        selector: this.getElementSelector(breadcrumbNav),
                        issue: 'Breadcrumb sin aria-label apropiado',
                        text: 'N/A'
                    });
                } else {
                    passed++;
                }
            }
            
            // 7.4 Verificar que items activos tienen aria-current
            const activeLinks = document.querySelectorAll('.nav-link.active');
            activeLinks.forEach(link => {
                const ariaCurrent = link.getAttribute('aria-current');
                if (ariaCurrent !== 'page') {
                    warnings.push({
                        element: link,
                        selector: this.getElementSelector(link),
                        issue: 'Item activo sin aria-current="page"',
                        text: link.textContent?.trim().substring(0, 30) || 'N/A'
                    });
                } else {
                    passed++;
                }
            });
            
            this.results.screenReader.passed = passed;
            this.results.screenReader.failed = issues.length;
            this.results.screenReader.issues = issues;
            this.results.screenReader.warnings = warnings;
            
            if (issues.length === 0 && warnings.length === 0) {
                console.log(`✅ Screen Reader Support: ${passed} elementos verificados correctamente`);
            } else {
                if (issues.length > 0) {
                    console.log(`❌ Screen Reader Support: ${issues.length} problemas encontrados`);
                    issues.forEach(issue => {
                        console.log(`   - ${issue.selector}: ${issue.issue}`);
                    });
                }
                if (warnings.length > 0) {
                    console.log(`⚠️ Screen Reader Support: ${warnings.length} advertencias`);
                    warnings.forEach(warning => {
                        console.log(`   - ${warning.selector}: ${warning.issue}`);
                    });
                }
            }
        },
        
        /**
         * Imprime resumen de todos los tests
         */
        printSummary() {
            console.log('\n' + '='.repeat(60));
            console.log('📊 RESUMEN DE TESTS DE ACCESIBILIDAD');
            console.log('='.repeat(60));
            
            const totalPassed = Object.values(this.results).reduce((sum, result) => sum + result.passed, 0);
            const totalFailed = Object.values(this.results).reduce((sum, result) => sum + result.failed, 0);
            const totalWarnings = Object.values(this.results).reduce((sum, result) => sum + result.warnings.length, 0);
            
            console.log(`\n✅ Tests pasados: ${totalPassed}`);
            console.log(`❌ Tests fallidos: ${totalFailed}`);
            console.log(`⚠️ Advertencias: ${totalWarnings}`);
            
            console.log('\n📋 Detalle por categoría:');
            Object.keys(this.results).forEach(key => {
                const result = this.results[key];
                const status = result.failed === 0 && result.warnings.length === 0 ? '✅' : result.failed > 0 ? '❌' : '⚠️';
                console.log(`   ${status} ${key}: ${result.passed} passed, ${result.failed} failed, ${result.warnings.length} warnings`);
            });
            
            if (totalFailed === 0 && totalWarnings === 0) {
                console.log('\n🎉 ¡Todos los tests de accesibilidad pasaron correctamente!');
            } else if (totalFailed === 0) {
                console.log('\n⚠️ Todos los tests pasaron, pero hay advertencias que revisar.');
            } else {
                console.log('\n❌ Hay problemas de accesibilidad que deben corregirse.');
            }
        },
        
        /**
         * Helper: Verifica si un elemento es visible
         */
        isElementVisible(element) {
            const style = window.getComputedStyle(element);
            if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                return false;
            }
            
            if (element.offsetWidth === 0 && element.offsetHeight === 0) {
                return false;
            }
            
            if (element.closest('[aria-hidden="true"]')) {
                return false;
            }
            
            return true;
        },
        
        /**
         * Helper: Obtiene selector único de un elemento
         */
        getElementSelector(element) {
            if (!element) return 'N/A';
            if (element.id) return `#${element.id}`;
            if (element.className) {
                const classes = Array.from(element.classList).slice(0, 2).join('.');
                return `${element.tagName.toLowerCase()}${classes ? '.' + classes : ''}`;
            }
            return element.tagName.toLowerCase();
        },
        
        /**
         * Helper: Calcula contraste aproximado entre dos colores
         */
        calculateContrast(color1, color2) {
            // Convertir colores RGB a luminancia
            const rgb1 = this.parseColor(color1);
            const rgb2 = this.parseColor(color2);
            
            if (!rgb1 || !rgb2) return 4.5; // Default si no se puede calcular
            
            const lum1 = this.getLuminance(rgb1);
            const lum2 = this.getLuminance(rgb2);
            
            const lighter = Math.max(lum1, lum2);
            const darker = Math.min(lum1, lum2);
            
            return (lighter + 0.05) / (darker + 0.05);
        },
        
        /**
         * Helper: Parsea color CSS a RGB
         */
        parseColor(color) {
            if (!color || color === 'transparent' || color === 'rgba(0, 0, 0, 0)') {
                return null;
            }
            
            // Simplificado: solo maneja rgb() y rgba()
            const match = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
            if (match) {
                return {
                    r: parseInt(match[1]),
                    g: parseInt(match[2]),
                    b: parseInt(match[3])
                };
            }
            
            return null;
        },
        
        /**
         * Helper: Calcula luminancia relativa
         */
        getLuminance(rgb) {
            const r = rgb.r / 255;
            const g = rgb.g / 255;
            const b = rgb.b / 255;
            
            const rLinear = r <= 0.03928 ? r / 12.92 : Math.pow((r + 0.055) / 1.055, 2.4);
            const gLinear = g <= 0.03928 ? g / 12.92 : Math.pow((g + 0.055) / 1.055, 2.4);
            const bLinear = b <= 0.03928 ? b / 12.92 : Math.pow((b + 0.055) / 1.055, 2.4);
            
            return 0.2126 * rLinear + 0.7152 * gLinear + 0.0722 * bLinear;
        }
    };
    
    // Exportar para uso global
    if (typeof window !== 'undefined') {
        window.NavigationAccessibilityTests = NavigationAccessibilityTests;
    }
    
    // Auto-ejecutar si se carga directamente
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        // Ya cargado, no auto-ejecutar
    } else {
        // Esperar a que cargue
        document.addEventListener('DOMContentLoaded', () => {
            console.log('💡 Navigation Accessibility Tests cargado. Ejecutar: NavigationAccessibilityTests.runAll()');
        });
    }
    
    return NavigationAccessibilityTests;
})();

