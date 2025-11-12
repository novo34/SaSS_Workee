/**
 * Utilidades de Performance - Workee
 * 
 * Funciones helper para optimizar performance del frontend
 */

/**
 * Debounce: Ejecuta una función después de un delay, cancelando ejecuciones previas
 */
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle: Ejecuta una función como máximo una vez por periodo
 */
function throttle(func, limit = 300) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Lazy load de imágenes
 * Soporta tanto data-src como loading="lazy" nativo
 */
function lazyLoadImages() {
    // Lazy loading nativo (loading="lazy") - el navegador lo maneja automáticamente
    // Solo agregamos clase 'loaded' cuando se carga
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    
                    // Manejar data-src (fallback para navegadores sin soporte nativo)
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                    
                    // Agregar clase 'loaded' cuando la imagen se carga
                    if (img.complete) {
                        img.classList.add('loaded');
                    } else {
                        img.addEventListener('load', () => {
                            img.classList.add('loaded');
                        });
                    }
                }
            });
        }, {
            rootMargin: '50px' // Cargar 50px antes de entrar en viewport
        });
        
        // Observar imágenes con data-src (fallback)
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
        
        // Observar imágenes con loading="lazy" para agregar clase 'loaded'
        document.querySelectorAll('img[loading="lazy"]').forEach(img => {
            imageObserver.observe(img);
        });
    }
}

/**
 * Preload de recursos críticos
 */
function preloadResource(href, as = 'script') {
    const link = document.createElement('link');
    link.rel = 'preload';
    link.href = href;
    link.as = as;
    document.head.appendChild(link);
}

/**
 * Cache de datos en localStorage con expiración
 */
const DataCache = {
    set(key, data, ttl = 3600000) { // 1 hora por defecto
        const item = {
            data,
            timestamp: Date.now(),
            ttl
        };
        try {
            localStorage.setItem(`cache_${key}`, JSON.stringify(item));
        } catch (e) {
            console.warn('Cache set failed:', e);
        }
    },
    
    get(key) {
        try {
            const itemStr = localStorage.getItem(`cache_${key}`);
            if (!itemStr) return null;
            
            const item = JSON.parse(itemStr);
            const now = Date.now();
            
            if (now - item.timestamp > item.ttl) {
                localStorage.removeItem(`cache_${key}`);
                return null;
            }
            
            return item.data;
        } catch (e) {
            console.warn('Cache get failed:', e);
            return null;
        }
    },
    
    clear(key) {
        if (key) {
            localStorage.removeItem(`cache_${key}`);
        } else {
            // Limpiar todos los caches
            Object.keys(localStorage).forEach(k => {
                if (k.startsWith('cache_')) {
                    localStorage.removeItem(k);
                }
            });
        }
    }
};

/**
 * Virtual scrolling para listas largas
 */
class VirtualScroller {
    constructor(container, itemHeight, renderItem, totalItems) {
        this.container = container;
        this.itemHeight = itemHeight;
        this.renderItem = renderItem;
        this.totalItems = totalItems;
        this.visibleItems = Math.ceil(container.clientHeight / itemHeight) + 2;
        this.scrollTop = 0;
        
        this.init();
    }
    
    init() {
        this.container.style.height = `${this.visibleItems * this.itemHeight}px`;
        this.container.style.overflow = 'auto';
        this.container.addEventListener('scroll', throttle(() => {
            this.scrollTop = this.container.scrollTop;
            this.render();
        }, 16));
        
        this.render();
    }
    
    render() {
        const startIndex = Math.floor(this.scrollTop / this.itemHeight);
        const endIndex = Math.min(startIndex + this.visibleItems, this.totalItems);
        
        const items = [];
        for (let i = startIndex; i < endIndex; i++) {
            items.push(this.renderItem(i));
        }
        
        this.container.innerHTML = items.join('');
        this.container.scrollTop = this.scrollTop;
    }
}

/**
 * Medir tiempo de ejecución
 */
function measurePerformance(name, fn) {
    const start = performance.now();
    const result = fn();
    const end = performance.now();
    console.log(`[Performance] ${name}: ${(end - start).toFixed(2)}ms`);
    return result;
}

/**
 * Code splitting: Cargar módulo dinámicamente
 */
async function loadModule(modulePath) {
    try {
        const module = await import(modulePath);
        return module;
    } catch (error) {
        console.error(`Error loading module: ${modulePath}`, error);
        return null;
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.debounce = debounce;
    window.throttle = throttle;
    window.lazyLoadImages = lazyLoadImages;
    window.preloadResource = preloadResource;
    window.DataCache = DataCache;
    window.VirtualScroller = VirtualScroller;
    window.measurePerformance = measurePerformance;
    window.loadModule = loadModule;
}

