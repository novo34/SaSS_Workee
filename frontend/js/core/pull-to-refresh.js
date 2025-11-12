/**
 * Pull to Refresh Helper - Workee
 * 
 * Helper reutilizable para implementar pull to refresh en listas y dashboards
 * MOB-140: Implementar Pull to Refresh
 * 
 * @class PullToRefresh
 * @description Helper para detectar y manejar pull to refresh gestures
 */

class PullToRefresh {
    /**
     * Inicializa pull to refresh en un elemento
     * @param {HTMLElement} element - Elemento donde se detectará el pull to refresh
     * @param {Object} options - Opciones de configuración
     * @param {Function} options.onRefresh - Callback cuando se activa el refresh
     * @param {number} [options.threshold=80] - Distancia mínima en px para activar refresh
     * @param {number} [options.resistance=2.5] - Resistencia del pull (mayor = más difícil)
     * @param {boolean} [options.enabled=true] - Si el pull to refresh está habilitado
     * @returns {Object} Objeto con métodos para controlar el pull to refresh
     */
    static init(element, options = {}) {
        if (!element) return null;

        const {
            onRefresh = null,
            threshold = 80,
            resistance = 2.5,
            enabled: initialEnabled = true
        } = options;

        let enabled = initialEnabled;
        let touchStartY = 0;
        let touchCurrentY = 0;
        let pullDistance = 0;
        let isPulling = false;
        let isRefreshing = false;
        let refreshIndicator = null;

        // Crear indicador visual
        const createIndicator = () => {
            if (refreshIndicator) return refreshIndicator;
            
            refreshIndicator = document.createElement('div');
            refreshIndicator.className = 'pull-to-refresh-indicator';
            refreshIndicator.innerHTML = `
                <div class="pull-to-refresh-indicator__spinner"></div>
                <div class="pull-to-refresh-indicator__text">Arrastra para actualizar</div>
            `;
            element.insertBefore(refreshIndicator, element.firstChild);
            return refreshIndicator;
        };

        const updateIndicator = (distance) => {
            if (!refreshIndicator) createIndicator();
            
            const progress = Math.min(distance / threshold, 1);
            const rotation = progress * 360;
            
            // Combinar translateX(-50%) para centrar horizontalmente con translateY para el pull
            refreshIndicator.style.transform = `translateX(-50%) translateY(${distance}px)`;
            refreshIndicator.style.opacity = Math.min(progress, 1);
            
            const spinner = refreshIndicator.querySelector('.pull-to-refresh-indicator__spinner');
            if (spinner) {
                if (distance >= threshold) {
                    spinner.classList.add('pull-to-refresh-indicator__spinner--ready');
                    refreshIndicator.querySelector('.pull-to-refresh-indicator__text').textContent = 'Suelta para actualizar';
                } else {
                    spinner.classList.remove('pull-to-refresh-indicator__spinner--ready');
                    spinner.style.transform = `rotate(${rotation}deg)`;
                    refreshIndicator.querySelector('.pull-to-refresh-indicator__text').textContent = 'Arrastra para actualizar';
                }
            }
        };

        const resetIndicator = () => {
            if (refreshIndicator) {
                // Mantener translateX(-50%) para centrar, solo resetear translateY
                refreshIndicator.style.transform = 'translateX(-50%) translateY(0px)';
                refreshIndicator.style.opacity = '0';
                const spinner = refreshIndicator.querySelector('.pull-to-refresh-indicator__spinner');
                if (spinner) {
                    spinner.classList.remove('pull-to-refresh-indicator__spinner--ready', 'pull-to-refresh-indicator__spinner--refreshing');
                    spinner.style.transform = '';
                }
                refreshIndicator.querySelector('.pull-to-refresh-indicator__text').textContent = 'Arrastra para actualizar';
            }
            pullDistance = 0;
            isPulling = false;
        };

        // Función helper para verificar si el scroll está en la parte superior
        const isAtTop = () => {
            // Verificar scroll del elemento si tiene scroll propio
            if (element.scrollHeight > element.clientHeight) {
                return element.scrollTop <= 0;
            }
            // Si el elemento no tiene scroll, verificar scroll del window/body
            return window.scrollY <= 0 || document.documentElement.scrollTop <= 0;
        };

        // Función helper para verificar si el touch está dentro del elemento
        const isTouchInElement = (touch) => {
            const rect = element.getBoundingClientRect();
            return touch.clientX >= rect.left && 
                   touch.clientX <= rect.right && 
                   touch.clientY >= rect.top && 
                   touch.clientY <= rect.bottom;
        };

        const handleTouchStart = (e) => {
            if (!enabled || isRefreshing) return;
            
            // Verificar que el touch esté dentro del elemento
            if (!isTouchInElement(e.touches[0])) {
                return;
            }
            
            // Solo si el scroll está en la parte superior
            const atTop = isAtTop();
            if (!atTop) {
                return;
            }
            
            touchStartY = e.touches[0].clientY;
            isPulling = false;
            
            // Debug en desarrollo
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                console.log('[PullToRefresh] Touch start detectado', {
                    touchY: touchStartY,
                    scrollTop: element.scrollTop,
                    windowScrollY: window.scrollY,
                    hasOwnScroll: element.scrollHeight > element.clientHeight
                });
            }
        };

        const handleTouchMove = (e) => {
            if (!enabled || isRefreshing) return;
            
            // Verificar que el touch esté dentro del elemento
            if (!isTouchInElement(e.touches[0])) {
                if (isPulling) {
                    resetIndicator();
                }
                return;
            }
            
            // Solo si el scroll está en la parte superior
            const atTop = isAtTop();
            if (!atTop) {
                if (isPulling) {
                    resetIndicator();
                }
                return;
            }
            
            touchCurrentY = e.touches[0].clientY;
            const deltaY = touchCurrentY - touchStartY;
            
            // Solo procesar si el movimiento es hacia abajo y es significativo
            if (deltaY > 10) { // Threshold mínimo para evitar activación accidental
                isPulling = true;
                e.preventDefault();
                e.stopPropagation();
                
                // Aplicar resistencia
                pullDistance = deltaY / resistance;
                updateIndicator(pullDistance);
                
                // Debug en desarrollo
                if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                    console.log('[PullToRefresh] Pulling', {
                        deltaY,
                        pullDistance,
                        threshold
                    });
                }
            } else if (deltaY < -10) {
                // Si el movimiento es hacia arriba, resetear
                if (isPulling) {
                    resetIndicator();
                }
            }
        };

        const handleTouchEnd = (e) => {
            if (!enabled || !isPulling) {
                resetIndicator();
                return;
            }
            
            // Si el pull es suficiente, activar refresh
            if (pullDistance >= threshold && onRefresh) {
                isRefreshing = true;
                const spinner = refreshIndicator?.querySelector('.pull-to-refresh-indicator__spinner');
                if (spinner) {
                    spinner.classList.add('pull-to-refresh-indicator__spinner--refreshing');
                }
                refreshIndicator.querySelector('.pull-to-refresh-indicator__text').textContent = 'Actualizando...';
                
                // Ejecutar callback
                Promise.resolve(onRefresh()).then(() => {
                    // Reset después de completar
                    setTimeout(() => {
                        resetIndicator();
                        isRefreshing = false;
                    }, 300);
                }).catch((error) => {
                    console.error('Pull to refresh error:', error);
                    resetIndicator();
                    isRefreshing = false;
                });
            } else {
                // Reset si no se alcanzó el threshold
                resetIndicator();
            }
        };

        // Agregar event listeners
        // Usar capture phase para asegurar que se capturen los eventos antes que otros handlers
        // Esto es importante para que funcione correctamente en móvil
        element.addEventListener('touchstart', handleTouchStart, { passive: true, capture: false });
        element.addEventListener('touchmove', handleTouchMove, { passive: false, capture: false });
        element.addEventListener('touchend', handleTouchEnd, { passive: true, capture: false });

        // Retornar objeto con métodos de control
        return {
            enable: () => { enabled = true; },
            disable: () => { 
                enabled = false;
                resetIndicator();
            },
            destroy: () => {
                element.removeEventListener('touchstart', handleTouchStart, { capture: false });
                element.removeEventListener('touchmove', handleTouchMove, { capture: false });
                element.removeEventListener('touchend', handleTouchEnd, { capture: false });
                
                if (refreshIndicator && refreshIndicator.parentNode) {
                    refreshIndicator.remove();
                }
                refreshIndicator = null;
            }
        };
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.PullToRefresh = PullToRefresh;
}

