/**
 * Swipe Gestures Helper - Workee
 * 
 * Helper reutilizable para implementar swipe gestures en diferentes componentes
 * Soporta swipe left/right para acciones y swipe to dismiss
 * 
 * @class SwipeGestures
 * @description Helper para detectar y manejar swipe gestures en elementos móviles
 */

class SwipeGestures {
    /**
     * Inicializa swipe gestures en un elemento
     * @param {HTMLElement} element - Elemento donde se detectarán los swipes
     * @param {Object} options - Opciones de configuración
     * @param {Function} [options.onSwipeLeft] - Callback cuando se hace swipe left
     * @param {Function} [options.onSwipeRight] - Callback cuando se hace swipe right
     * @param {Function} [options.onSwipeUp] - Callback cuando se hace swipe up
     * @param {Function} [options.onSwipeDown] - Callback cuando se hace swipe down
     * @param {number} [options.minSwipeDistance=50] - Distancia mínima en px para considerar swipe
     * @param {number} [options.maxVerticalDistance=100] - Máxima distancia vertical para ignorar swipe horizontal
     * @param {number} [options.maxHorizontalDistance=100] - Máxima distancia horizontal para ignorar swipe vertical
     * @param {boolean} [options.enabled=true] - Si los swipe gestures están habilitados
     * @returns {Object} Objeto con métodos para controlar los gestures
     */
    static init(element, options = {}) {
        if (!element) return null;

        const {
            onSwipeLeft = null,
            onSwipeRight = null,
            onSwipeUp = null,
            onSwipeDown = null,
            minSwipeDistance = 50,
            maxVerticalDistance = 100,
            maxHorizontalDistance = 100,
            enabled: initialEnabled = true
        } = options;

        let enabled = initialEnabled; // MOB-137: Variable mutable para control
        let touchStartX = 0;
        let touchStartY = 0;
        let touchEndX = 0;
        let touchEndY = 0;
        let isSwiping = false;
        let swipeStartTime = 0;

        const handleTouchStart = (e) => {
            if (!enabled) return;
            
            const touch = e.touches[0];
            touchStartX = touch.clientX;
            touchStartY = touch.clientY;
            swipeStartTime = Date.now();
            isSwiping = false;
        };

        const handleTouchMove = (e) => {
            if (!enabled) return;
            
            const touch = e.touches[0];
            const deltaX = Math.abs(touch.clientX - touchStartX);
            const deltaY = Math.abs(touch.clientY - touchStartY);
            
            // Si el movimiento es significativo, marcar como swiping
            if (deltaX > 10 || deltaY > 10) {
                isSwiping = true;
            }
        };

        const handleTouchEnd = (e) => {
            if (!enabled || !isSwiping) return;

            const touch = e.changedTouches[0];
            touchEndX = touch.clientX;
            touchEndY = touch.clientY;

            const deltaX = touchEndX - touchStartX;
            const deltaY = touchEndY - touchStartY;
            const absDeltaX = Math.abs(deltaX);
            const absDeltaY = Math.abs(deltaY);
            const swipeDuration = Date.now() - swipeStartTime;

            // Ignorar si el swipe es muy lento (probablemente scroll)
            if (swipeDuration > 500) return;

            // Determinar dirección del swipe
            if (absDeltaX > absDeltaY) {
                // Swipe horizontal
                if (absDeltaY > maxVerticalDistance) return; // Ignorar si hay mucho movimiento vertical
                
                if (absDeltaX >= minSwipeDistance) {
                    if (deltaX > 0 && onSwipeRight) {
                        onSwipeRight(e, { distance: absDeltaX, duration: swipeDuration });
                    } else if (deltaX < 0 && onSwipeLeft) {
                        onSwipeLeft(e, { distance: absDeltaX, duration: swipeDuration });
                    }
                }
            } else {
                // Swipe vertical
                if (absDeltaX > maxHorizontalDistance) return; // Ignorar si hay mucho movimiento horizontal
                
                if (absDeltaY >= minSwipeDistance) {
                    if (deltaY > 0 && onSwipeDown) {
                        onSwipeDown(e, { distance: absDeltaY, duration: swipeDuration });
                    } else if (deltaY < 0 && onSwipeUp) {
                        onSwipeUp(e, { distance: absDeltaY, duration: swipeDuration });
                    }
                }
            }

            isSwiping = false;
        };

        // Agregar event listeners
        element.addEventListener('touchstart', handleTouchStart, { passive: true });
        element.addEventListener('touchmove', handleTouchMove, { passive: true });
        element.addEventListener('touchend', handleTouchEnd, { passive: false });

        // Retornar objeto con métodos de control
        return {
            enable: () => { enabled = true; },
            disable: () => { enabled = false; },
            destroy: () => {
                element.removeEventListener('touchstart', handleTouchStart);
                element.removeEventListener('touchmove', handleTouchMove);
                element.removeEventListener('touchend', handleTouchEnd);
            }
        };
    }

    /**
     * Inicializa swipe gestures para acciones en filas de tabla (swipe left/right)
     * @param {HTMLElement} row - Fila de la tabla
     * @param {Object} options - Opciones
     * @param {Function} [options.onSwipeLeft] - Acción cuando se hace swipe left (ej: eliminar)
     * @param {Function} [options.onSwipeRight] - Acción cuando se hace swipe right (ej: editar)
     * @param {string} [options.leftActionLabel='Eliminar'] - Label para acción izquierda
     * @param {string} [options.rightActionLabel='Editar'] - Label para acción derecha
     * @param {string} [options.leftActionColor='danger'] - Color para acción izquierda
     * @param {string} [options.rightActionColor='primary'] - Color para acción derecha
     */
    static initTableRowSwipe(row, options = {}) {
        if (!row || window.innerWidth >= 992) return null; // Solo en móvil

        const {
            onSwipeLeft = null,
            onSwipeRight = null,
            leftActionLabel = 'Eliminar',
            rightActionLabel = 'Editar',
            leftActionColor = 'danger',
            rightActionColor = 'primary'
        } = options;

        let swipeOffset = 0;
        const maxSwipeDistance = 80; // Máxima distancia de swipe
        let isActionVisible = false;
        let currentAction = null; // 'left' o 'right'
        let touchStartX = 0;
        let touchStartY = 0;
        let isDragging = false;

        // Crear contenedor de acciones si no existe
        let actionsContainer = row.querySelector('.swipe-actions');
        if (!actionsContainer) {
            actionsContainer = document.createElement('div');
            actionsContainer.className = 'swipe-actions';
            
            // Acción izquierda (swipe right - mostrar acción derecha)
            if (onSwipeRight) {
                const rightAction = document.createElement('button');
                rightAction.className = `swipe-action swipe-action--right swipe-action--${rightActionColor}`;
                // MOB-137: Usar SVG icon en lugar de emoji
                const editIcon = typeof IconHelper !== 'undefined' ? IconHelper.get('edit', { className: 'swipe-action__icon', size: 'md' }) : '<span class="swipe-action__icon">✏️</span>';
                rightAction.innerHTML = `${editIcon}<span class="swipe-action__label">${rightActionLabel}</span>`;
                rightAction.addEventListener('click', (e) => {
                    e.stopPropagation();
                    onSwipeRight(row);
                    resetSwipe();
                });
                actionsContainer.appendChild(rightAction);
            }

            // Acción derecha (swipe left - mostrar acción izquierda)
            if (onSwipeLeft) {
                const leftAction = document.createElement('button');
                leftAction.className = `swipe-action swipe-action--left swipe-action--${leftActionColor}`;
                // MOB-137: Usar SVG icon en lugar de emoji
                const deleteIcon = typeof IconHelper !== 'undefined' ? IconHelper.get('delete', { className: 'swipe-action__icon', size: 'md' }) : '<span class="swipe-action__icon">🗑️</span>';
                leftAction.innerHTML = `${deleteIcon}<span class="swipe-action__label">${leftActionLabel}</span>`;
                leftAction.addEventListener('click', (e) => {
                    e.stopPropagation();
                    onSwipeLeft(row);
                    resetSwipe();
                });
                actionsContainer.appendChild(leftAction);
            }

            row.style.position = 'relative';
            row.style.overflow = 'hidden';
            row.appendChild(actionsContainer);
        }

        const resetSwipe = () => {
            swipeOffset = 0;
            isActionVisible = false;
            currentAction = null;
            row.style.transform = '';
            row.style.transition = 'transform 0.3s ease-out';
            actionsContainer.style.display = 'none';
            actionsContainer.classList.remove('swipe-actions--left-visible', 'swipe-actions--right-visible');
        };

        const handleTouchStart = (e) => {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            isDragging = false;
            row.style.transition = 'none';
        };

        const handleTouchMove = (e) => {
            if (!touchStartX) return;
            
            const touchCurrentX = e.touches[0].clientX;
            const touchCurrentY = e.touches[0].clientY;
            const deltaX = touchCurrentX - touchStartX;
            const deltaY = Math.abs(touchCurrentY - touchStartY);
            
            // Solo procesar si el movimiento horizontal es mayor que el vertical
            if (Math.abs(deltaX) > 10 && Math.abs(deltaX) > deltaY) {
                isDragging = true;
                e.preventDefault();
                
                // Limitar el swipe
                swipeOffset = Math.max(-maxSwipeDistance, Math.min(maxSwipeDistance, deltaX));
                
                // Aplicar transformación
                row.style.transform = `translateX(${swipeOffset}px)`;
                
                // Mostrar acciones según dirección
                if (swipeOffset < -20 && onSwipeLeft) {
                    // Swipe left - mostrar acción izquierda
                    actionsContainer.style.display = 'flex';
                    actionsContainer.classList.add('swipe-actions--left-visible');
                    actionsContainer.classList.remove('swipe-actions--right-visible');
                    currentAction = 'left';
                } else if (swipeOffset > 20 && onSwipeRight) {
                    // Swipe right - mostrar acción derecha
                    actionsContainer.style.display = 'flex';
                    actionsContainer.classList.add('swipe-actions--right-visible');
                    actionsContainer.classList.remove('swipe-actions--left-visible');
                    currentAction = 'right';
                } else {
                    actionsContainer.style.display = 'none';
                    currentAction = null;
                }
            }
        };

        const handleTouchEnd = (e) => {
            if (!isDragging) {
                touchStartX = 0;
                return;
            }
            
            const touchEndX = e.changedTouches[0].clientX;
            const finalDeltaX = touchEndX - touchStartX;
            
            // Si el swipe es suficiente, ejecutar acción o resetear
            if (Math.abs(finalDeltaX) >= 50) {
                if (finalDeltaX < -50 && onSwipeLeft && currentAction === 'left') {
                    // Swipe left completo - ejecutar acción
                    onSwipeLeft(row);
                    resetSwipe();
                } else if (finalDeltaX > 50 && onSwipeRight && currentAction === 'right') {
                    // Swipe right completo - ejecutar acción
                    onSwipeRight(row);
                    resetSwipe();
                } else {
                    // Swipe insuficiente - resetear
                    resetSwipe();
                }
            } else {
                // Swipe insuficiente - resetear
                resetSwipe();
            }
            
            touchStartX = 0;
            isDragging = false;
        };

        // Agregar event listeners
        row.addEventListener('touchstart', handleTouchStart, { passive: true });
        row.addEventListener('touchmove', handleTouchMove, { passive: false });
        row.addEventListener('touchend', handleTouchEnd, { passive: true });
        
        // También resetear si se hace click fuera
        document.addEventListener('click', (e) => {
            if (!row.contains(e.target) && isActionVisible) {
                resetSwipe();
            }
        });

        // Retornar objeto con métodos de control
        return {
            destroy: () => {
                row.removeEventListener('touchstart', handleTouchStart);
                row.removeEventListener('touchmove', handleTouchMove);
                row.removeEventListener('touchend', handleTouchEnd);
                resetSwipe();
            }
        };
    }

    /**
     * Inicializa swipe to dismiss en un elemento (notificaciones, modales)
     * @param {HTMLElement} element - Elemento que se puede dismissar
     * @param {Function} onDismiss - Callback cuando se hace dismiss
     * @param {string} [direction='up'] - Dirección del swipe ('up', 'down', 'left', 'right')
     */
    static initSwipeToDismiss(element, onDismiss, direction = 'up') {
        if (!element) return null;

        let dismissThreshold = 100; // Distancia mínima para dismiss
        let dismissOffset = 0;

        const handleSwipe = (e, { distance }) => {
            if (direction === 'up' || direction === 'down') {
                dismissOffset = direction === 'up' ? -distance : distance;
                element.style.transform = `translateY(${dismissOffset}px)`;
                element.style.opacity = Math.max(0, 1 - Math.abs(dismissOffset) / 200);
            } else {
                dismissOffset = direction === 'left' ? -distance : distance;
                element.style.transform = `translateX(${dismissOffset}px)`;
                element.style.opacity = Math.max(0, 1 - Math.abs(dismissOffset) / 200);
            }

            // Si el swipe es suficiente, ejecutar dismiss
            if (Math.abs(dismissOffset) >= dismissThreshold) {
                element.style.transition = 'transform 0.3s ease-out, opacity 0.3s ease-out';
                if (direction === 'up' || direction === 'down') {
                    element.style.transform = `translateY(${direction === 'up' ? '-100%' : '100%'})`;
                } else {
                    element.style.transform = `translateX(${direction === 'left' ? '-100%' : '100%'})`;
                }
                element.style.opacity = '0';
                
                setTimeout(() => {
                    if (onDismiss) onDismiss();
                }, 300);
            } else {
                // Resetear si no se alcanzó el threshold
                element.style.transition = 'transform 0.2s ease-out, opacity 0.2s ease-out';
                element.style.transform = '';
                element.style.opacity = '';
            }
        };

        const swipeOptions = {
            minSwipeDistance: 30
        };

        if (direction === 'up') {
            swipeOptions.onSwipeUp = handleSwipe;
        } else if (direction === 'down') {
            swipeOptions.onSwipeDown = handleSwipe;
        } else if (direction === 'left') {
            swipeOptions.onSwipeLeft = handleSwipe;
        } else if (direction === 'right') {
            swipeOptions.onSwipeRight = handleSwipe;
        }

        return this.init(element, swipeOptions);
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.SwipeGestures = SwipeGestures;
}

