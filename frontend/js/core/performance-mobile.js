/**
 * Performance Mobile Optimizations - Workee
 * 
 * Helpers para optimizar performance en móvil
 * MOB-143: Mejorar Performance Móvil
 */

class PerformanceMobile {
    /**
     * Debounce para eventos de scroll
     * @param {Function} func - Función a ejecutar
     * @param {number} wait - Tiempo de espera en ms
     * @param {boolean} immediate - Si ejecutar inmediatamente
     * @returns {Function} Función debounced
     */
    static debounce(func, wait = 100, immediate = false) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                timeout = null;
                if (!immediate) func(...args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func(...args);
        };
    }

    /**
     * Throttle para eventos de scroll
     * @param {Function} func - Función a ejecutar
     * @param {number} limit - Tiempo límite en ms
     * @returns {Function} Función throttled
     */
    static throttle(func, limit = 100) {
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
     * Cache de datos en localStorage con expiración
     * @param {string} key - Clave del cache
     * @param {*} data - Datos a cachear
     * @param {number} ttl - Tiempo de vida en ms (default: 5 minutos)
     */
    static setCache(key, data, ttl = 5 * 60 * 1000) {
        if (!window.localStorage) return;
        
        const item = {
            data: data,
            timestamp: Date.now(),
            ttl: ttl
        };
        
        try {
            localStorage.setItem(`cache_${key}`, JSON.stringify(item));
        } catch (e) {
            // Si localStorage está lleno, limpiar cache antiguo
            if (e.name === 'QuotaExceededError') {
                this.clearOldCache();
                try {
                    localStorage.setItem(`cache_${key}`, JSON.stringify(item));
                } catch (e2) {
                    console.warn('Could not cache data:', e2);
                }
            }
        }
    }

    /**
     * Obtiene datos del cache si no han expirado
     * @param {string} key - Clave del cache
     * @returns {*} Datos cacheados o null si expiraron
     */
    static getCache(key) {
        if (!window.localStorage) return null;
        
        try {
            const itemStr = localStorage.getItem(`cache_${key}`);
            if (!itemStr) return null;
            
            const item = JSON.parse(itemStr);
            const now = Date.now();
            
            // Verificar si expiró
            if (now - item.timestamp > item.ttl) {
                localStorage.removeItem(`cache_${key}`);
                return null;
            }
            
            return item.data;
        } catch (e) {
            console.error('Error reading cache:', e);
            return null;
        }
    }

    /**
     * Limpia cache antiguo (más de 1 hora)
     */
    static clearOldCache() {
        if (!window.localStorage) return;
        
        const oneHourAgo = Date.now() - (60 * 60 * 1000);
        const keysToRemove = [];
        
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith('cache_')) {
                try {
                    const itemStr = localStorage.getItem(key);
                    if (itemStr) {
                        const item = JSON.parse(itemStr);
                        if (item.timestamp < oneHourAgo) {
                            keysToRemove.push(key);
                        }
                    }
                } catch (e) {
                    // Si hay error parseando, eliminar la clave
                    keysToRemove.push(key);
                }
            }
        }
        
        keysToRemove.forEach(key => localStorage.removeItem(key));
    }

    /**
     * Cache agresivo para datos de dashboard
     * @param {string} endpoint - Endpoint de la API
     * @param {Function} fetchFunction - Función async que obtiene los datos
     * @param {number} ttl - Tiempo de vida del cache en ms (default: 2 minutos)
     * @returns {Promise} Datos del cache o de la API
     */
    static async getCachedData(endpoint, fetchFunction, ttl = 2 * 60 * 1000) {
        const cacheKey = `dashboard_${endpoint}`;
        
        // Intentar obtener del cache
        const cached = this.getCache(cacheKey);
        if (cached !== null) {
            return cached;
        }
        
        // Si no hay cache, obtener de la API
        try {
            const data = await fetchFunction();
            this.setCache(cacheKey, data, ttl);
            return data;
        } catch (error) {
            console.error('Error fetching data:', error);
            throw error;
        }
    }

    /**
     * Cache agresivo para listas (DataTable)
     * @param {string} endpoint - Endpoint de la API
     * @param {Object} params - Parámetros de la query
     * @param {Function} fetchFunction - Función async que obtiene los datos
     * @param {number} ttl - Tiempo de vida del cache en ms (default: 1 minuto)
     * @returns {Promise} Datos del cache o de la API
     */
    static async getCachedList(endpoint, params, fetchFunction, ttl = 60 * 1000) {
        const paramsKey = JSON.stringify(params);
        const cacheKey = `list_${endpoint}_${paramsKey}`;
        
        // Intentar obtener del cache
        const cached = this.getCache(cacheKey);
        if (cached !== null) {
            return cached;
        }
        
        // Si no hay cache, obtener de la API
        try {
            const data = await fetchFunction();
            this.setCache(cacheKey, data, ttl);
            return data;
        } catch (error) {
            console.error('Error fetching list data:', error);
            throw error;
        }
    }

    /**
     * Invalida cache de un endpoint específico
     * @param {string} endpoint - Endpoint a invalidar
     */
    static invalidateCache(endpoint) {
        if (!window.localStorage) return;
        
        const keysToRemove = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && (key.startsWith(`cache_dashboard_${endpoint}`) || key.startsWith(`cache_list_${endpoint}`))) {
                keysToRemove.push(key);
            }
        }
        
        keysToRemove.forEach(key => localStorage.removeItem(key));
    }

    /**
     * Verifica que todas las imágenes usen lazy loading
     */
    static verifyImageLazyLoading() {
        if (window.innerWidth >= 992) return; // Solo en móvil
        
        const images = document.querySelectorAll('img:not([loading])');
        images.forEach(img => {
            // Solo agregar lazy loading si la imagen no está en viewport inicial
            if (img.offsetTop > window.innerHeight * 2) {
                img.setAttribute('loading', 'lazy');
            }
        });
    }

    /**
     * Inicializa optimizaciones de performance móvil
     */
    static init() {
        // Limpiar cache antiguo al iniciar
        this.clearOldCache();
        
        // Verificar lazy loading de imágenes
        if (window.innerWidth < 992) {
            this.verifyImageLazyLoading();
            
            // Observar nuevas imágenes agregadas dinámicamente
            if (typeof MutationObserver !== 'undefined') {
                const imageObserver = new MutationObserver((mutations) => {
                    mutations.forEach(mutation => {
                        mutation.addedNodes.forEach(node => {
                            if (node.nodeType === 1) { // Element node
                                if (node.tagName === 'IMG' && !node.hasAttribute('loading')) {
                                    if (node.offsetTop > window.innerHeight * 2) {
                                        node.setAttribute('loading', 'lazy');
                                    }
                                }
                                // También verificar imágenes dentro de nodos agregados
                                const images = node.querySelectorAll && node.querySelectorAll('img:not([loading])');
                                if (images) {
                                    images.forEach(img => {
                                        if (img.offsetTop > window.innerHeight * 2) {
                                            img.setAttribute('loading', 'lazy');
                                        }
                                    });
                                }
                            }
                        });
                    });
                });
                
                imageObserver.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        }
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.PerformanceMobile = PerformanceMobile;
}

