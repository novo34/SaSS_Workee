/**
 * Image Mobile Optimizer - Workee
 * 
 * Helper para optimizar imágenes automáticamente en móvil
 * MOB-149: Optimizar Videos e Imágenes para Móvil
 * 
 * Agrega automáticamente:
 * - srcset con tamaños móviles específicos
 * - WebP con fallback usando <picture>
 * - Lazy loading mejorado con Intersection Observer
 * - Aspect ratio para evitar CLS
 */

class ImageMobileOptimizer {
    /**
     * Optimiza todas las imágenes en la página
     */
    static optimizeAll() {
        if (window.innerWidth >= 992) return; // Solo en móvil
        
        const images = document.querySelectorAll('img:not([data-optimized])');
        images.forEach(img => {
            this.optimizeImage(img);
        });
    }

    /**
     * Optimiza una imagen específica
     * @param {HTMLImageElement} img - Elemento imagen
     */
    static optimizeImage(img) {
        if (!img || img.hasAttribute('data-optimized')) return;
        if (window.innerWidth >= 992) return; // Solo en móvil
        
        const src = img.src || img.getAttribute('src');
        if (!src) return;
        
        // Marcar como optimizada para evitar procesamiento duplicado
        img.setAttribute('data-optimized', 'true');
        
        // MOB-149: Agregar srcset con tamaños móviles específicos
        if (!img.hasAttribute('srcset') && !img.closest('picture')) {
            this.addSrcset(img, src);
        }
        
        // MOB-149: Agregar WebP con fallback usando <picture>
        if (!img.closest('picture') && this.supportsWebP()) {
            this.addWebPFallback(img, src);
        }
        
        // MOB-149: Lazy loading mejorado con Intersection Observer
        if (!img.hasAttribute('loading')) {
            // Solo agregar lazy loading si la imagen está fuera del viewport inicial
            if (img.offsetTop > window.innerHeight * 2) {
                img.setAttribute('loading', 'lazy');
                this.initLazyLoading(img);
            }
        } else if (img.getAttribute('loading') === 'lazy') {
            this.initLazyLoading(img);
        }
        
        // MOB-149: Agregar aspect ratio si no tiene
        if (!img.hasAttribute('data-aspect-ratio') && !img.style.aspectRatio) {
            const aspectRatio = this.detectAspectRatio(img);
            if (aspectRatio) {
                img.setAttribute('data-aspect-ratio', aspectRatio);
            }
        }
    }

    /**
     * Agrega srcset con tamaños móviles específicos
     * @param {HTMLImageElement} img - Elemento imagen
     * @param {string} src - URL de la imagen
     */
    static addSrcset(img, src) {
        // Generar srcset con tamaños móviles (400w, 800w, 1200w)
        const baseSrc = src.split('?')[0]; // Remover query params
        const sizes = [400, 800, 1200];
        
        const srcset = sizes
            .map(size => `${baseSrc}?w=${size} ${size}w`)
            .join(', ');
        
        img.setAttribute('srcset', srcset);
        
        // Agregar sizes attribute para mejor performance
        if (!img.hasAttribute('sizes')) {
            img.setAttribute('sizes', '(max-width: 400px) 100vw, (max-width: 800px) 50vw, 33vw');
        }
    }

    /**
     * Agrega WebP con fallback usando <picture>
     * @param {HTMLImageElement} img - Elemento imagen
     * @param {string} src - URL de la imagen
     */
    static addWebPFallback(img, src) {
        // Verificar si ya está dentro de un <picture>
        if (img.closest('picture')) return;
        
        // Crear elemento <picture>
        const picture = document.createElement('picture');
        
        // Crear <source> para WebP
        const webpSource = document.createElement('source');
        const webpSrc = src.replace(/\.(jpg|jpeg|png)$/i, '.webp');
        webpSource.setAttribute('srcset', webpSrc);
        webpSource.setAttribute('type', 'image/webp');
        
        // Agregar srcset al source WebP si la imagen tiene srcset
        if (img.hasAttribute('srcset')) {
            const srcset = img.getAttribute('srcset');
            const webpSrcset = srcset.replace(/\.(jpg|jpeg|png)/gi, '.webp');
            webpSource.setAttribute('srcset', webpSrcset);
        }
        
        // Agregar sizes al source WebP si la imagen tiene sizes
        if (img.hasAttribute('sizes')) {
            webpSource.setAttribute('sizes', img.getAttribute('sizes'));
        }
        
        // Envolver la imagen en <picture>
        img.parentNode.insertBefore(picture, img);
        picture.appendChild(webpSource);
        picture.appendChild(img);
    }

    /**
     * Inicializa lazy loading mejorado con Intersection Observer
     * @param {HTMLImageElement} img - Elemento imagen
     */
    static initLazyLoading(img) {
        if (typeof IntersectionObserver === 'undefined') {
            // Fallback: usar loading="lazy" nativo
            return;
        }
        
        // Si la imagen ya está cargada, no hacer nada
        if (img.complete && img.naturalHeight !== 0) {
            img.classList.add('loaded');
            return;
        }
        
        // Crear observer para lazy loading
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    
                    // Cargar la imagen
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        delete img.dataset.src;
                    }
                    
                    // Marcar como cargada cuando termine de cargar
                    img.addEventListener('load', () => {
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }, { once: true });
                    
                    // Si hay error, también marcar como cargada para evitar reintentos infinitos
                    img.addEventListener('error', () => {
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }, { once: true });
                    
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px' // Cargar 50px antes de que sea visible
        });
        
        observer.observe(img);
    }

    /**
     * Detecta el aspect ratio de una imagen
     * @param {HTMLImageElement} img - Elemento imagen
     * @returns {string|null} Aspect ratio en formato "16/9" o null
     */
    static detectAspectRatio(img) {
        // Si la imagen ya tiene dimensiones, calcular aspect ratio
        if (img.naturalWidth && img.naturalHeight) {
            const ratio = img.naturalWidth / img.naturalHeight;
            // Redondear a ratios comunes
            if (Math.abs(ratio - 16/9) < 0.1) return '16/9';
            if (Math.abs(ratio - 4/3) < 0.1) return '4/3';
            if (Math.abs(ratio - 1) < 0.1) return '1/1';
            if (Math.abs(ratio - 21/9) < 0.1) return '21/9';
            return `${img.naturalWidth}/${img.naturalHeight}`;
        }
        
        // Si no tiene dimensiones, intentar detectar por contexto
        const parent = img.parentElement;
        if (parent) {
            // Avatar/user image
            if (parent.classList.contains('avatar') || 
                parent.classList.contains('user-avatar') ||
                img.classList.contains('avatar')) {
                return '1/1';
            }
            
            // Card image
            if (parent.classList.contains('card') || 
                parent.classList.contains('card-modern')) {
                return '16/9';
            }
            
            // Hero/banner
            if (parent.classList.contains('hero') || 
                parent.classList.contains('banner')) {
                return '21/9';
            }
        }
        
        return null;
    }

    /**
     * Verifica si el navegador soporta WebP
     * @returns {boolean} Si soporta WebP
     */
    static supportsWebP() {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
    }

    /**
     * Optimiza videos en la página
     */
    static optimizeVideos() {
        if (window.innerWidth >= 992) return; // Solo en móvil
        
        const videos = document.querySelectorAll('video:not([data-optimized])');
        videos.forEach(video => {
            this.optimizeVideo(video);
        });
    }

    /**
     * Optimiza un video específico
     * @param {HTMLVideoElement} video - Elemento video
     */
    static optimizeVideo(video) {
        if (!video || video.hasAttribute('data-optimized')) return;
        if (window.innerWidth >= 992) return; // Solo en móvil
        
        video.setAttribute('data-optimized', 'true');
        
        // MOB-149: Agregar aspect ratio si no tiene
        if (!video.hasAttribute('data-aspect-ratio') && !video.style.aspectRatio) {
            // Detectar aspect ratio del video
            video.addEventListener('loadedmetadata', () => {
                if (video.videoWidth && video.videoHeight) {
                    const ratio = video.videoWidth / video.videoHeight;
                    if (Math.abs(ratio - 16/9) < 0.1) {
                        video.setAttribute('data-aspect-ratio', '16/9');
                    } else if (Math.abs(ratio - 4/3) < 0.1) {
                        video.setAttribute('data-aspect-ratio', '4/3');
                    } else {
                        video.setAttribute('data-aspect-ratio', `${video.videoWidth}/${video.videoHeight}`);
                    }
                }
            }, { once: true });
        }
        
        // MOB-149: Agregar object-fit: cover
        if (!video.style.objectFit) {
            video.style.objectFit = 'cover';
        }
        
        // MOB-149: Agregar lazy loading si está fuera del viewport
        if (!video.hasAttribute('loading') && video.offsetTop > window.innerHeight * 2) {
            video.setAttribute('loading', 'lazy');
            video.setAttribute('preload', 'none');
        }
    }

    /**
     * Inicializa el optimizador para todas las imágenes y videos
     */
    static init() {
        // Optimizar imágenes y videos existentes
        this.optimizeAll();
        this.optimizeVideos();

        // Observar nuevas imágenes/videos agregados dinámicamente
        if (typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach(mutation => {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) { // Element node
                            if (node.tagName === 'IMG') {
                                this.optimizeImage(node);
                            } else if (node.tagName === 'VIDEO') {
                                this.optimizeVideo(node);
                            }
                            // También optimizar imágenes/videos dentro de nodos agregados
                            const images = node.querySelectorAll && node.querySelectorAll('img:not([data-optimized])');
                            if (images) {
                                images.forEach(img => this.optimizeImage(img));
                            }
                            const videos = node.querySelectorAll && node.querySelectorAll('video:not([data-optimized])');
                            if (videos) {
                                videos.forEach(video => this.optimizeVideo(video));
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
                    // Remover data-optimized para re-optimizar
                    document.querySelectorAll('[data-optimized]').forEach(el => {
                        el.removeAttribute('data-optimized');
                    });
                    this.optimizeAll();
                    this.optimizeVideos();
                }
            }, 250);
        });
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.ImageMobileOptimizer = ImageMobileOptimizer;
}

