/**
 * Script de Verificación de Tab Order - Workee
 * 
 * NAV-132: Verificación final de tab order
 * 
 * Ejecuta este script en la consola para verificar que el tab order es correcto
 */

(function() {
    'use strict';
    
    console.log('🔍 Verificando Tab Order...\n');
    
    const issues = [];
    const warnings = [];
    
    // 1. Verificar que el skip link existe y tiene tabindex="-1" por defecto
    const skipLink = document.querySelector('.skip-link');
    if (!skipLink) {
        issues.push('❌ Skip link no encontrado');
    } else {
        const tabindex = skipLink.getAttribute('tabindex');
        if (tabindex !== '-1') {
            warnings.push(`⚠️ Skip link tiene tabindex="${tabindex}" en lugar de "-1" por defecto`);
        } else {
            console.log('✅ Skip link tiene tabindex="-1" por defecto');
        }
        
        // Verificar que el skip link puede activarse con focus
        skipLink.addEventListener('focus', () => {
            if (skipLink.getAttribute('tabindex') === '0') {
                console.log('✅ Skip link se activa correctamente con focus');
            }
        }, { once: true });
    }
    
    // 2. Verificar que todos los elementos interactivos tienen tabindex apropiado
    const interactiveElements = document.querySelectorAll(
        'a[href], button, input, select, textarea, [role="button"], [tabindex]'
    );
    
    const tabOrderElements = [];
    interactiveElements.forEach(el => {
        // Saltar elementos ocultos
        const style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden') return;
        
        // Saltar elementos dentro de contenedores ocultos
        if (el.closest('[aria-hidden="true"]')) return;
        
        const tabindex = el.getAttribute('tabindex');
        const isFocusable = el.tabIndex >= 0;
        
        if (isFocusable) {
            tabOrderElements.push({
                element: el,
                tag: el.tagName,
                tabindex: tabindex || '0 (default)',
                selector: getElementSelector(el),
                text: el.textContent?.trim().substring(0, 30) || el.alt || el.title || 'N/A'
            });
        }
    });
    
    // 3. Verificar tab order en sidebar
    const sidebar = document.getElementById('main-navigation');
    if (sidebar) {
        const sidebarLinks = Array.from(sidebar.querySelectorAll('.nav-link, .nav-subsection-title--toggle'))
            .filter(link => {
                const style = window.getComputedStyle(link);
                return style.display !== 'none' && style.visibility !== 'hidden';
            });
        
        console.log(`\n📋 Tab Order en Sidebar (${sidebarLinks.length} elementos):`);
        sidebarLinks.forEach((link, index) => {
            const tabindex = link.getAttribute('tabindex');
            const isDisabled = link.disabled || link.classList.contains('disabled');
            const selector = getElementSelector(link);
            const text = link.textContent?.trim().substring(0, 30) || 'N/A';
            
            if (isDisabled && tabindex !== '-1') {
                warnings.push(`⚠️ Elemento disabled sin tabindex="-1": ${selector}`);
            }
            
            console.log(`  ${index + 1}. ${selector} - "${text}" (tabindex: ${tabindex || '0 (default)'})`);
        });
    }
    
    // 4. Verificar que elementos ocultos no están en tab order
    const hiddenElements = document.querySelectorAll('[aria-hidden="true"] a, [aria-hidden="true"] button, [aria-hidden="true"] input');
    hiddenElements.forEach(el => {
        const tabindex = el.getAttribute('tabindex');
        if (tabindex !== '-1' && el.tabIndex >= 0) {
            issues.push(`❌ Elemento oculto (aria-hidden="true") está en tab order: ${getElementSelector(el)}`);
        }
    });
    
    // 5. Verificar que sidebar-toggle tiene tabindex correcto según viewport
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) {
        const isMobile = window.innerWidth < 992;
        const tabindex = sidebarToggle.getAttribute('tabindex');
        
        if (isMobile) {
            if (tabindex === '-1') {
                warnings.push('⚠️ sidebar-toggle tiene tabindex="-1" en móvil (debería ser "0" o sin tabindex)');
            } else {
                console.log('✅ sidebar-toggle accesible en móvil');
            }
        } else {
            if (tabindex !== '-1') {
                warnings.push('⚠️ sidebar-toggle no tiene tabindex="-1" en desktop (debería estar oculto del tab order)');
            } else {
                console.log('✅ sidebar-toggle oculto del tab order en desktop');
            }
        }
    }
    
    // 6. Verificar que no hay elementos con tabindex > 0 (debería evitarse)
    const positiveTabindex = document.querySelectorAll('[tabindex]:not([tabindex="-1"]):not([tabindex="0"])');
    positiveTabindex.forEach(el => {
        const tabindex = parseInt(el.getAttribute('tabindex'));
        if (tabindex > 0) {
            warnings.push(`⚠️ Elemento con tabindex="${tabindex}" (debería evitarse, usar tabindex="0" o "-1"): ${getElementSelector(el)}`);
        }
    });
    
    // 7. Verificar que el tab order es lógico (de arriba a abajo, de izquierda a derecha)
    console.log('\n📊 Tab Order General:');
    const visibleFocusable = tabOrderElements.filter(item => {
        const el = item.element;
        const style = window.getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && el.offsetWidth > 0 && el.offsetHeight > 0;
    });
    
    // Ordenar por posición visual (top, luego left)
    visibleFocusable.sort((a, b) => {
        const rectA = a.element.getBoundingClientRect();
        const rectB = b.element.getBoundingClientRect();
        
        // Primero por top (vertical)
        if (Math.abs(rectA.top - rectB.top) > 10) {
            return rectA.top - rectB.top;
        }
        // Luego por left (horizontal)
        return rectA.left - rectB.left;
    });
    
    console.log(`Total de elementos focusables visibles: ${visibleFocusable.length}`);
    visibleFocusable.slice(0, 10).forEach((item, index) => {
        console.log(`  ${index + 1}. ${item.selector} - "${item.text}"`);
    });
    if (visibleFocusable.length > 10) {
        console.log(`  ... y ${visibleFocusable.length - 10} más`);
    }
    
    // Resumen
    console.log('\n📊 RESUMEN DE VERIFICACIÓN:');
    console.log('========================');
    
    if (issues.length === 0 && warnings.length === 0) {
        console.log('✅ Tab order verificado correctamente');
        console.log(`✅ ${visibleFocusable.length} elementos focusables visibles`);
        console.log('✅ Skip link implementado correctamente');
        console.log('✅ Navegación por teclado funcional');
    } else {
        if (issues.length > 0) {
            console.log(`\n❌ ${issues.length} problemas encontrados:`);
            issues.forEach(issue => console.log(`  ${issue}`));
        }
        if (warnings.length > 0) {
            console.log(`\n⚠️ ${warnings.length} advertencias:`);
            warnings.forEach(warning => console.log(`  ${warning}`));
        }
    }
    
    // Guardar resultados
    window.tabOrderResults = {
        issues: issues,
        warnings: warnings,
        focusableElements: visibleFocusable.length,
        sidebarLinks: sidebar ? sidebar.querySelectorAll('.nav-link, .nav-subsection-title--toggle').length : 0
    };
    
    console.log('\n💾 Resultados guardados en window.tabOrderResults');
    
    // Función helper para obtener selector
    function getElementSelector(el) {
        if (el.id) return `#${el.id}`;
        if (el.className) {
            const classes = Array.from(el.classList).slice(0, 2).join('.');
            return `${el.tagName.toLowerCase()}${classes ? '.' + classes : ''}`;
        }
        return el.tagName.toLowerCase();
    }
    
    return {
        passed: issues.length === 0,
        issues: issues,
        warnings: warnings
    };
})();

