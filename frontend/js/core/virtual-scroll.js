/**
 * Virtual Scrolling Helper - Workee
 * 
 * Helper para implementar virtual scrolling en listas largas
 * MOB-143: Virtual scrolling en listas largas (>50 items)
 */

class VirtualScroll {
    /**
     * Inicializa virtual scrolling en un contenedor
     * @param {HTMLElement} container - Contenedor donde se renderizará la lista
     * @param {Array} items - Array de items a renderizar
     * @param {Function} renderItem - Función que renderiza un item (recibe item, index)
     * @param {Object} options - Opciones de configuración
     * @param {number} [options.itemHeight=50] - Altura estimada de cada item en px
     * @param {number} [options.visibleItems=10] - Número de items visibles
     * @param {number} [options.buffer=5] - Número de items adicionales a renderizar fuera del viewport
     * @returns {Object} Objeto con métodos para controlar el virtual scroll
     */
    static init(container, items, renderItem, options = {}) {
        if (!container || !items || !renderItem) return null;

        const {
            itemHeight = 50,
            visibleItems = 10,
            buffer = 5
        } = options;

        let scrollTop = 0;
        let startIndex = 0;
        let endIndex = Math.min(visibleItems + buffer, items.length);

        // Crear contenedor interno
        const innerContainer = document.createElement('div');
        innerContainer.style.position = 'relative';
        innerContainer.style.height = `${items.length * itemHeight}px`;
        
        const viewport = document.createElement('div');
        viewport.style.position = 'absolute';
        viewport.style.top = '0';
        viewport.style.left = '0';
        viewport.style.right = '0';
        viewport.style.overflow = 'visible';
        
        innerContainer.appendChild(viewport);
        container.appendChild(innerContainer);

        const render = () => {
            // Calcular índices visibles
            startIndex = Math.max(0, Math.floor(scrollTop / itemHeight) - buffer);
            endIndex = Math.min(items.length, Math.ceil((scrollTop + container.clientHeight) / itemHeight) + buffer);

            // Renderizar solo items visibles
            viewport.style.top = `${startIndex * itemHeight}px`;
            viewport.innerHTML = '';
            
            for (let i = startIndex; i < endIndex; i++) {
                const itemElement = document.createElement('div');
                itemElement.style.height = `${itemHeight}px`;
                itemElement.innerHTML = renderItem(items[i], i);
                viewport.appendChild(itemElement);
            }
        };

        // MOB-143: Debounce en scroll para mejor performance
        let scrollTimeout;
        const handleScroll = () => {
            if (scrollTimeout) {
                cancelAnimationFrame(scrollTimeout);
            }
            scrollTimeout = requestAnimationFrame(() => {
                scrollTop = container.scrollTop;
                render();
            });
        };

        container.addEventListener('scroll', handleScroll, { passive: true });
        
        // Renderizar inicialmente
        render();

        return {
            updateItems: (newItems) => {
                items = newItems;
                innerContainer.style.height = `${items.length * itemHeight}px`;
                render();
            },
            destroy: () => {
                container.removeEventListener('scroll', handleScroll);
                if (innerContainer.parentNode) {
                    innerContainer.remove();
                }
            }
        };
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.VirtualScroll = VirtualScroll;
}

