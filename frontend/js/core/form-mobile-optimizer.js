/**
 * Form Mobile Optimizer - Workee
 * 
 * Helper para optimizar formularios automáticamente en móvil
 * MOB-142: Optimizar Formularios para Móvil
 * 
 * Agrega automáticamente:
 * - inputmode apropiado según el tipo de input
 * - autocomplete attributes
 * - Sticky buttons en bottom para formularios largos
 * - Date pickers nativos en móvil
 */

class FormMobileOptimizer {
    /**
     * Optimiza todos los formularios en la página
     */
    static optimizeAll() {
        const forms = document.querySelectorAll('form, .form-modern');
        forms.forEach(form => {
            this.optimizeForm(form);
        });
    }

    /**
     * Optimiza un formulario específico
     * @param {HTMLElement} form - Elemento del formulario
     */
    static optimizeForm(form) {
        if (!form) return;

        // Optimizar inputs
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            this.optimizeInput(input);
        });

        // Agregar sticky buttons si es necesario
        if (window.innerWidth < 992) {
            this.addStickyButtons(form);
        }
    }

    /**
     * Optimiza un input específico
     * @param {HTMLElement} input - Elemento input
     */
    static optimizeInput(input) {
        if (!input) return;

        const type = input.type || input.getAttribute('type') || '';
        const name = input.name || input.id || '';
        const className = input.className || '';

        // MOB-142: Agregar inputmode apropiado
        if (!input.hasAttribute('inputmode')) {
            if (type === 'number' || type === 'tel') {
                input.setAttribute('inputmode', 'numeric');
            } else if (type === 'email') {
                input.setAttribute('inputmode', 'email');
            } else if (type === 'url') {
                input.setAttribute('inputmode', 'url');
            } else if (type === 'search') {
                input.setAttribute('inputmode', 'search');
            } else if (type === 'text' && (name.includes('phone') || name.includes('tel'))) {
                input.setAttribute('inputmode', 'tel');
            } else if (type === 'text' && (name.includes('number') || name.includes('num'))) {
                input.setAttribute('inputmode', 'numeric');
            }
        }

        // MOB-142: Agregar autocomplete apropiado
        if (!input.hasAttribute('autocomplete')) {
            const autocompleteValue = this.getAutocompleteValue(name, type);
            if (autocompleteValue) {
                input.setAttribute('autocomplete', autocompleteValue);
            }
        }

        // MOB-142: Asegurar que date pickers usen nativos en móvil
        if ((type === 'date' || type === 'time' || type === 'datetime-local') && window.innerWidth < 992) {
            // Forzar uso de date picker nativo
            input.setAttribute('type', type);
            // Asegurar que no haya librerías de terceros interfiriendo
            if (input.classList.contains('datepicker') || input.classList.contains('flatpickr-input')) {
                input.classList.remove('datepicker', 'flatpickr-input');
            }
        }

        // MOB-142: Asegurar altura mínima de 44px
        if (window.innerWidth < 767) {
            const currentHeight = input.offsetHeight || parseInt(getComputedStyle(input).height);
            if (currentHeight < 44) {
                input.style.minHeight = '44px';
            }

            // Asegurar font-size de 16px para evitar zoom en iOS
            const currentFontSize = parseInt(getComputedStyle(input).fontSize);
            if (currentFontSize < 16) {
                input.style.fontSize = '16px';
            }
        }
    }

    /**
     * Obtiene el valor de autocomplete apropiado según el nombre y tipo del input
     * @param {string} name - Nombre o ID del input
     * @param {string} type - Tipo del input
     * @returns {string|null} Valor de autocomplete o null
     */
    static getAutocompleteValue(name, type) {
        if (!name) return null;

        const nameLower = name.toLowerCase();

        // Email
        if (type === 'email' || nameLower.includes('email') || nameLower.includes('mail')) {
            return 'email';
        }

        // Teléfono
        if (type === 'tel' || nameLower.includes('phone') || nameLower.includes('tel') || nameLower.includes('telefono')) {
            return 'tel';
        }

        // URL
        if (type === 'url' || nameLower.includes('url') || nameLower.includes('website') || nameLower.includes('web')) {
            return 'url';
        }

        // Nombres
        if (nameLower.includes('firstname') || nameLower.includes('first_name') || nameLower.includes('nombre')) {
            return 'given-name';
        }
        if (nameLower.includes('lastname') || nameLower.includes('last_name') || nameLower.includes('apellido')) {
            return 'family-name';
        }
        if (nameLower.includes('name') && !nameLower.includes('user') && !nameLower.includes('company')) {
            return 'name';
        }

        // Dirección
        if (nameLower.includes('address') || nameLower.includes('direccion') || nameLower.includes('street')) {
            return 'street-address';
        }
        if (nameLower.includes('city') || nameLower.includes('ciudad')) {
            return 'address-level2';
        }
        if (nameLower.includes('state') || nameLower.includes('province') || nameLower.includes('provincia')) {
            return 'address-level1';
        }
        if (nameLower.includes('zip') || nameLower.includes('postal') || nameLower.includes('postcode') || nameLower.includes('codigo_postal')) {
            return 'postal-code';
        }
        if (nameLower.includes('country') || nameLower.includes('pais')) {
            return 'country';
        }

        // Fechas
        if (type === 'date' || nameLower.includes('date') || nameLower.includes('fecha')) {
            if (nameLower.includes('birth') || nameLower.includes('nacimiento')) {
                return 'bday';
            }
            return 'off'; // Desactivar autocomplete para fechas de negocio
        }

        // Contraseñas
        if (type === 'password' || nameLower.includes('password') || nameLower.includes('pass') || nameLower.includes('contraseña')) {
            if (nameLower.includes('current') || nameLower.includes('actual')) {
                return 'current-password';
            }
            if (nameLower.includes('new') || nameLower.includes('nueva')) {
                return 'new-password';
            }
            return 'current-password';
        }

        // Búsqueda
        if (type === 'search' || nameLower.includes('search') || nameLower.includes('buscar')) {
            return 'off';
        }

        // Números (desactivar autocomplete por defecto)
        if (type === 'number') {
            return 'off';
        }

        return null;
    }

    /**
     * Agrega botones sticky en bottom para formularios largos
     * @param {HTMLElement} form - Elemento del formulario
     */
    static addStickyButtons(form) {
        if (!form || window.innerWidth >= 992) return;

        // Buscar botones de acción (submit, guardar, etc.)
        const actionButtons = form.querySelectorAll('button[type="submit"], .btn-submit, .btn-save, .form-actions button, .modal-footer button');
        
        if (actionButtons.length === 0) return;

        // Verificar si el formulario es lo suficientemente largo para necesitar sticky buttons
        const formHeight = form.scrollHeight;
        const viewportHeight = window.innerHeight;
        
        // Si el formulario es más alto que el viewport, agregar sticky buttons
        if (formHeight > viewportHeight * 0.8) {
            // Crear contenedor de botones sticky
            let stickyContainer = form.querySelector('.form-actions-mobile');
            
            if (!stickyContainer) {
                stickyContainer = document.createElement('div');
                stickyContainer.className = 'form-actions-mobile form-actions-mobile--show';
                
                const buttonsContainer = document.createElement('div');
                buttonsContainer.className = 'form-actions-mobile__buttons';
                
                // Clonar botones de acción
                actionButtons.forEach(button => {
                    const clonedButton = button.cloneNode(true);
                    clonedButton.addEventListener('click', (e) => {
                        e.preventDefault();
                        button.click();
                    });
                    buttonsContainer.appendChild(clonedButton);
                });
                
                stickyContainer.appendChild(buttonsContainer);
                form.appendChild(stickyContainer);
                
                // Agregar clase al formulario para padding adicional
                form.classList.add('form-with-sticky-actions');
            }
        }
    }

    /**
     * Inicializa el optimizador para todos los formularios
     */
    static init() {
        // Optimizar formularios existentes
        this.optimizeAll();

        // Observar nuevos formularios agregados dinámicamente
        if (typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach(mutation => {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) { // Element node
                            if (node.tagName === 'FORM' || node.classList.contains('form-modern')) {
                                this.optimizeForm(node);
                            }
                            // También optimizar formularios dentro de nodos agregados
                            const forms = node.querySelectorAll && node.querySelectorAll('form, .form-modern');
                            if (forms) {
                                forms.forEach(form => this.optimizeForm(form));
                            }
                        }
                    });
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        // Re-optimizar en resize (por si cambia de desktop a móvil)
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                if (window.innerWidth < 992) {
                    this.optimizeAll();
                } else {
                    // Remover sticky buttons en desktop
                    document.querySelectorAll('.form-actions-mobile').forEach(el => {
                        el.remove();
                    });
                    document.querySelectorAll('.form-with-sticky-actions').forEach(el => {
                        el.classList.remove('form-with-sticky-actions');
                    });
                }
            }, 250);
        });
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.FormMobileOptimizer = FormMobileOptimizer;
}

