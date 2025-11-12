/**
 * Cliente API Centralizado - Workee
 * 
 * OBLIGATORIO: Usar este cliente para TODAS las llamadas API
 * NUNCA usar fetch() directamente en el código
 * 
 * @class APIClient
 * @description Cliente API centralizado con autenticación automática, manejo de errores y traducción
 * 
 * @example
 * // GET request
 * const response = await API.get('/work-orders', { page: 1, page_size: 20 });
 * if (response && response.success) {
 *   const workOrders = response.data;
 * }
 * 
 * @example
 * // POST request
 * const response = await API.post('/work-orders', {
 *   title: 'Nueva OT',
 *   description: 'Descripción'
 * });
 * 
 * @example
 * // PUT request
 * const response = await API.put('/work-orders/123', { title: 'OT Actualizada' });
 * 
 * @example
 * // DELETE request
 * const response = await API.delete('/work-orders/123');
 * 
 * Características:
 * - Autenticación automática (JWT + refresh token)
 * - Manejo de errores global
 * - Traducción automática de claves de error
 * - Respuestas JSON consistentes
 * - Interceptores para errores
 */

console.log('[API] Cargando API Client...');

const BASE_URL = (typeof window !== 'undefined' && window.BASE_URL) || '';
const API_BASE = `${BASE_URL}/api/v1`;

console.log('[API] BASE_URL:', BASE_URL);
console.log('[API] API_BASE:', API_BASE);

class APIClient {
    constructor() {
        this.accessToken = null;
        this.refreshToken = null;
        this.tenantId = null;
        this.userId = null;
        this.locale = 'es';
        this.translations = {};
        this.errorInterceptor = null;
        this.autoRefreshInterval = null; // Guardar referencia al intervalo
        this._loadingTranslations = false; // Flag para evitar cargas duplicadas
        this.loadTokens();
        
        // Cargar traducciones después de que los tokens estén disponibles
        // Usar setTimeout para asegurar que se ejecute después de que todo esté cargado
        setTimeout(async () => {
            // Cargar traducciones si hay token disponible (solo una vez)
            if (this.accessToken && !this._loadingTranslations) {
                await this.loadTranslations();
            }
            
            // Inicializar auto-refresh si hay tokens disponibles
            if (this.accessToken && this.refreshToken) {
                console.log('[API] Inicializando auto-refresh del token...');
                this.initAutoRefresh();
            } else {
                console.log('[API] No hay tokens disponibles, auto-refresh no iniciado');
            }
        }, 1000);
    }

    /**
     * Carga tokens desde localStorage
     */
    loadTokens() {
        const oldAccessToken = this.accessToken;
        const oldRefreshToken = this.refreshToken;
        
        this.accessToken = localStorage.getItem('access_token');
        this.refreshToken = localStorage.getItem('refresh_token');
        this.tenantId = localStorage.getItem('tenant_id');
        const userStr = localStorage.getItem('user');
        if (userStr) {
            try {
                const user = JSON.parse(userStr);
                this.userId = user.id || null;
            } catch (e) {
                console.error('Error parsing user from localStorage:', e);
            }
        }
        
        console.log('[API] loadTokens() llamado');
        console.log('[API] Access token presente:', !!this.accessToken);
        console.log('[API] Refresh token presente:', !!this.refreshToken);
        console.log('[API] Tenant ID:', this.tenantId);
        console.log('[API] User ID:', this.userId);
        
        // Si acabamos de cargar tokens y no teníamos antes, inicializar auto-refresh
        // NOTA: No cargar traducciones aquí porque el constructor ya lo hace en setTimeout
        // Solo cargar traducciones si se llama loadTokens() DESPUÉS del constructor (ej: después de login)
        if (this.accessToken && this.refreshToken && !oldAccessToken) {
            console.log('[API] Tokens cargados por primera vez, inicializando auto-refresh...');
            // Solo inicializar auto-refresh, las traducciones se cargarán en el setTimeout del constructor
            this.initAutoRefresh();
        } else if (this.accessToken && this.refreshToken && oldAccessToken && !oldRefreshToken) {
            // Si teníamos access token pero no refresh token, y ahora tenemos ambos, inicializar auto-refresh
            console.log('[API] Refresh token cargado, inicializando auto-refresh...');
            this.initAutoRefresh();
        } else if (!this.accessToken && oldAccessToken) {
            // Si perdimos el token, detener auto-refresh
            console.warn('[API] Token perdido, deteniendo auto-refresh...');
            this.stopAutoRefresh();
        }
    }

    /**
     * Carga traducciones desde el backend
     * IMPORTANTE: Solo carga si hay token disponible, sino se carga después
     * Evita cargar múltiples veces usando una flag
     * NO redirige a login si falla (las traducciones no son críticas)
     */
    async loadTranslations() {
        // Si no hay token, no intentar cargar traducciones (se cargarán después del login)
        if (!this.accessToken) {
            console.log('[API] No hay token disponible, traducciones se cargarán después del login');
            return;
        }
        
        // Evitar cargar traducciones múltiples veces simultáneamente
        if (this._loadingTranslations) {
            console.log('[API] Traducciones ya se están cargando, omitiendo llamada duplicada');
            return;
        }
        
        this._loadingTranslations = true;
        
        try {
            const locale = localStorage.getItem('locale') || 'es';
            this.locale = locale;
            
            // Usar fetch directamente para evitar que handleError() redirija a login
            // Las traducciones no son críticas y no deberían causar logout
            const headers = {
                'Content-Type': 'application/json'
            };
            
            if (this.accessToken) {
                headers['Authorization'] = `Bearer ${this.accessToken}`;
            }
            
            if (this.tenantId) {
                headers['X-Tenant-ID'] = this.tenantId;
            }
            
            const response = await fetch(`${API_BASE}/i18n/translations?locale=${locale}&namespace=common,errors,validation`, {
                method: 'GET',
                headers: headers
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data && data.success && data.data) {
                    this.translations = data.data || {};
                    console.log('[API] Traducciones cargadas correctamente');
                } else {
                    console.warn('[API] No se pudieron cargar traducciones:', data);
                }
            } else {
                // Si hay error (401, 403, etc.), solo loguear pero NO redirigir
                // Las traducciones no son críticas para el funcionamiento
                console.warn(`[API] Error al cargar traducciones: ${response.status} ${response.statusText}`);
                if (response.status === 401) {
                    console.warn('[API] Token inválido al cargar traducciones, pero no se redirige (traducciones no críticas)');
                }
            }
        } catch (error) {
            // No redirigir en caso de error de red al cargar traducciones
            console.warn('[API] Error de red al cargar traducciones:', error);
        } finally {
            this._loadingTranslations = false;
        }
    }

    /**
     * Traduce una clave de error
     */
    translate(key, params = {}) {
        if (!key) return 'Error desconocido';
        
        // Buscar en traducciones cargadas
        const keys = key.split('.');
        let translation = this.translations;
        
        for (const k of keys) {
            if (translation && typeof translation === 'object' && translation[k]) {
                translation = translation[k];
            } else {
                // Fallback: retornar la clave
                return key;
            }
        }
        
        if (typeof translation === 'string') {
            // Reemplazar parámetros {param}
            return translation.replace(/\{(\w+)\}/g, (match, param) => {
                return params[param] || match;
            });
        }
        
        return key;
    }

    /**
     * Decodifica un JWT token (sin verificar firma, solo para leer exp)
     */
    decodeToken(token) {
        if (!token) return null;
        try {
            const parts = token.split('.');
            if (parts.length !== 3) return null;
            const payload = JSON.parse(atob(parts[1].replace(/-/g, '+').replace(/_/g, '/')));
            return payload;
        } catch (e) {
            return null;
        }
    }

    /**
     * Verifica si el token está próximo a expirar (menos de 2 minutos)
     */
    isTokenExpiringSoon(token) {
        const payload = this.decodeToken(token);
        if (!payload || !payload.exp) return true; // Si no se puede decodificar, asumir que expira pronto
        
        const now = Math.floor(Date.now() / 1000);
        const timeUntilExpiry = payload.exp - now;
        // Refrescar si quedan menos de 2 minutos (120 segundos)
        return timeUntilExpiry < 120;
    }

    /**
     * Refresca el access token
     */
    async refreshAccessToken() {
        console.log('[API] refreshAccessToken() llamado');
        console.log('[API] Refresh token presente:', !!this.refreshToken);
        console.log('[API] Tenant ID antes de refresh:', this.tenantId);
        
        if (!this.refreshToken) {
            console.warn('[API] No hay refresh token, no se puede refrescar');
            return false;
        }
        
        try {
            console.log('[API] Haciendo petición POST /auth/refresh...');
            const response = await fetch(`${API_BASE}/auth/refresh`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ refresh_token: this.refreshToken })
            });
            
            console.log('[API] Respuesta de refresh, status:', response.status);
            
            const data = await response.json();
            console.log('[API] Datos de respuesta de refresh:', data);
            
            if (data.success && data.data && data.data.access_token) {
                console.log('[API] Token refrescado exitosamente');
                console.log('[API] Nuevo access token (primeros 20 chars):', data.data.access_token.substring(0, 20) + '...');
                console.log('[API] Nuevo tenant_id:', data.data.tenant_id || this.tenantId);
                
                this.accessToken = data.data.access_token;
                this.refreshToken = data.data.refresh_token || this.refreshToken;
                
                // Actualizar tenant_id si viene en la respuesta
                // IMPORTANTE: Solo actualizar si el tenant_id cambió, no sobrescribir si no viene
                if (data.data.tenant_id) {
                    const newTenantId = data.data.tenant_id;
                    if (newTenantId !== this.tenantId) {
                        console.warn(`[API] ⚠️ Tenant ID cambió durante refresh: ${this.tenantId} → ${newTenantId}`);
                        console.warn('[API] Esto NO debería ocurrir normalmente. Verificar en backend.');
                    }
                    console.log('[API] Actualizando tenant_id desde respuesta:', newTenantId);
                    this.tenantId = newTenantId;
                    localStorage.setItem('tenant_id', this.tenantId);
                } else {
                    // Si no viene tenant_id en la respuesta, mantener el actual
                    console.log('[API] Respuesta de refresh no incluye tenant_id, manteniendo el actual:', this.tenantId);
                }
                
                localStorage.setItem('access_token', this.accessToken);
                if (data.data.refresh_token) {
                    localStorage.setItem('refresh_token', this.refreshToken);
                }
                
                console.log('[API] Tokens actualizados en localStorage');
                console.log('[API] Tenant ID después de refresh:', this.tenantId);
                return true;
            } else {
                console.error('[API] Respuesta de refresh no exitosa:', data);
            }
        } catch (error) {
            console.error('[API] Error refreshing token:', error);
            console.error('[API] Stack trace:', error.stack);
        }
        
        console.warn('[API] No se pudo refrescar el token');
        return false;
    }

    /**
     * Inicializa el sistema de refresh automático del token
     */
    initAutoRefresh() {
        // Si ya hay un intervalo activo, no crear otro
        if (this.autoRefreshInterval) {
            console.log('[API] Auto-refresh ya está activo, no se creará otro intervalo');
            return;
        }
        
        if (!this.accessToken || !this.refreshToken) {
            console.warn('[API] No se puede iniciar auto-refresh: faltan tokens');
            return;
        }
        
        console.log('[API] Iniciando auto-refresh del token (verificación cada 30 segundos)');
        
        // Verificar cada 30 segundos si el token necesita refrescarse
        this.autoRefreshInterval = setInterval(async () => {
            console.log('[API] Auto-refresh: Verificando token...');
            console.log('[API] Auto-refresh: Tenant ID:', this.tenantId);
            
            // Recargar tokens por si cambiaron
            this.loadTokens();
            
            if (!this.accessToken || !this.refreshToken) {
                console.warn('[API] Auto-refresh: Tokens perdidos, deteniendo auto-refresh');
                console.warn('[API] Auto-refresh: Access token:', !!this.accessToken);
                console.warn('[API] Auto-refresh: Refresh token:', !!this.refreshToken);
                this.stopAutoRefresh();
                return;
            }
            
            // Decodificar token para ver expiración
            const payload = this.decodeToken(this.accessToken);
            if (payload && payload.exp) {
                const now = Math.floor(Date.now() / 1000);
                const timeUntilExpiry = payload.exp - now;
                console.log(`[API] Auto-refresh: Token expira en ${timeUntilExpiry} segundos`);
            }
            
            if (this.isTokenExpiringSoon(this.accessToken)) {
                console.log('[API] Auto-refresh: Token próximo a expirar, refrescando automáticamente...');
                console.log('[API] Auto-refresh: Tenant ID antes de refresh:', this.tenantId);
                const refreshed = await this.refreshAccessToken();
                if (refreshed) {
                    console.log('[API] Auto-refresh: Token refrescado automáticamente con éxito');
                    console.log('[API] Auto-refresh: Tenant ID después de refresh:', this.tenantId);
                } else {
                    console.error('[API] Auto-refresh: No se pudo refrescar el token automáticamente');
                    console.error('[API] Auto-refresh: NO se detendrá el intervalo (el token puede seguir siendo válido)');
                    // NO detener el intervalo si falla el refresh - el token puede seguir siendo válido
                    // Solo detener si realmente no hay tokens
                }
            } else {
                console.log('[API] Auto-refresh: Token aún válido, no necesita refrescarse');
            }
        }, 30000); // Verificar cada 30 segundos
    }

    /**
     * Detiene el sistema de refresh automático
     */
    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    }

    /**
     * Maneja errores de respuesta
     */
    async handleError(response, endpoint) {
        console.log(`[API] handleError() llamado para endpoint: ${endpoint}, status: ${response.status}`);
        console.log(`[API] Tenant ID actual: ${this.tenantId}`);
        console.log(`[API] Access token presente: ${!!this.accessToken}`);
        console.log(`[API] Refresh token presente: ${!!this.refreshToken}`);
        
        // Si hay interceptor, usarlo
        if (this.errorInterceptor) {
            const handled = await this.errorInterceptor(response, endpoint);
            if (handled) return;
        }

        // Manejo estándar de errores
        if (response.status === 401) {
            console.warn('[API] ========================================');
            console.warn('[API] Error 401 - Token rechazado. Intentando refrescar...');
            console.warn(`[API] Endpoint que falló: ${endpoint}`);
            console.warn(`[API] Tenant ID: ${this.tenantId}`);
            console.warn(`[API] Access token presente: ${!!this.accessToken}`);
            console.warn(`[API] Refresh token presente: ${!!this.refreshToken}`);
            
            // Intentar obtener el mensaje de error del backend
            try {
                const errorText = await response.clone().text();
                console.log('[API] Respuesta de error del backend:', errorText.substring(0, 200));
            } catch (e) {
                console.warn('[API] No se pudo leer respuesta de error:', e);
            }
            
            // Verificar si el token está expirado antes de intentar refrescar
            if (this.accessToken && this.isTokenExpiringSoon(this.accessToken)) {
                console.log('[API] Token próximo a expirar, intentando refrescar...');
            } else if (this.accessToken) {
                const payload = this.decodeToken(this.accessToken);
                if (payload && payload.exp) {
                    const now = Math.floor(Date.now() / 1000);
                    const timeUntilExpiry = payload.exp - now;
                    console.log(`[API] Token rechazado pero no está próximo a expirar. Quedan ${timeUntilExpiry} segundos.`);
                }
            }
            
            // Intentar refresh token
            const refreshed = await this.refreshAccessToken();
            if (refreshed) {
                console.log('[API] Token refrescado exitosamente después de 401');
                // Retornar null para que el llamador reintente (pero no automáticamente)
                // El llamador debe manejar el null y decidir si reintentar
                return {
                    success: false,
                    error: 'auth.token_refreshed',
                    error_key: 'auth.token_refreshed',
                    message: 'Token refrescado. Por favor, reintente la operación.',
                    needsRetry: true
                };
            }
            
            console.error('[API] ========================================');
            console.error('[API] No se pudo refrescar el token. Redirigiendo a login...');
            console.error(`[API] Endpoint que causó el logout: ${endpoint}`);
            console.error(`[API] Tenant ID antes de limpiar: ${this.tenantId}`);
            console.error(`[API] Access token antes de limpiar: ${!!this.accessToken}`);
            console.error(`[API] Refresh token antes de limpiar: ${!!this.refreshToken}`);
            console.error('[API] Limpiando localStorage...');
            
            // Si no se pudo refrescar, limpiar tokens y redirigir a login
            this.stopAutoRefresh(); // Detener auto-refresh
            localStorage.removeItem('access_token');
            localStorage.removeItem('refresh_token');
            localStorage.removeItem('tenant_id');
            localStorage.removeItem('user');
            localStorage.removeItem('user_role');
            
            console.error('[API] localStorage limpiado');
            console.error(`[API] Redirigiendo a: ${BASE_URL}/login`);
            console.error('[API] ========================================');
            
            window.location.href = `${BASE_URL}/login`;
            return null;
        }

        // Intentar parsear error
        let errorData = null;
        try {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                errorData = await response.json();
            } else {
                const text = await response.text();
                errorData = {
                    success: false,
                    error: 'internal_error',
                    error_key: 'internal_error',
                    message: text.substring(0, 200)
                };
            }
        } catch (e) {
            errorData = {
                success: false,
                error: 'internal_error',
                error_key: 'internal_error',
                message: `Error ${response.status}: ${response.statusText}`
            };
        }

        return errorData;
    }

    /**
     * Realiza una petición GET
     */
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = `${API_BASE}${endpoint}${queryString ? '?' + queryString : ''}`;
        
        return this.request(url, {
            method: 'GET'
        });
    }

    /**
     * Realiza una petición POST
     * Soporta tanto objetos JSON como FormData para uploads
     */
    async post(endpoint, data = {}) {
        // Si data es FormData, pasarlo directamente; sino, stringify
        const body = data instanceof FormData ? data : JSON.stringify(data);
        
        return this.request(`${API_BASE}${endpoint}`, {
            method: 'POST',
            body: body
        });
    }

    /**
     * Realiza una petición PUT
     */
    async put(endpoint, data = {}) {
        return this.request(`${API_BASE}${endpoint}`, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    /**
     * Realiza una petición PATCH
     */
    async patch(endpoint, data = {}) {
        return this.request(`${API_BASE}${endpoint}`, {
            method: 'PATCH',
            body: JSON.stringify(data)
        });
    }

    /**
     * Realiza una petición DELETE
     */
    async delete(endpoint) {
        return this.request(`${API_BASE}${endpoint}`, {
            method: 'DELETE'
        });
    }

    /**
     * Realiza una petición HTTP genérica
     */
    async request(url, options = {}) {
        // Recargar tokens por si cambiaron
        this.loadTokens();

        // Detectar si el body es FormData (para uploads de archivos)
        const isFormData = options.body instanceof FormData;

        const headers = {
            ...options.headers
        };

        // Solo establecer Content-Type si NO es FormData (el navegador lo hace automáticamente con boundary)
        if (!isFormData) {
            headers['Content-Type'] = 'application/json';
        }

        if (this.accessToken) {
            headers['Authorization'] = `Bearer ${this.accessToken}`;
            console.log('[API] Enviando petición con token (primeros 20 chars):', this.accessToken.substring(0, 20) + '...');
        } else {
            console.warn('[API] No hay access token disponible para la petición');
        }

        if (this.tenantId) {
            headers['X-Tenant-ID'] = this.tenantId;
        }

        if (this.userId) {
            headers['X-User-ID'] = this.userId;
        }

        try {
            const response = await fetch(url, {
                ...options,
                headers
            });

            // Manejar errores HTTP
            if (!response.ok) {
                console.log(`[API] Respuesta no OK: ${response.status} ${response.statusText} para ${url}`);
                const errorData = await this.handleError(response, url);
                if (errorData === null) {
                    // Si retornó null, significa que se manejó (refresh token, redirect, etc.)
                    return null;
                }
                return errorData;
            }

            // Parsear respuesta JSON o Blob (para descargas)
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                const data = await response.json();
                
                // Traducir mensajes de error si tienen error_key
                if (!data.success && data.error_key) {
                    data.message = this.translate(data.error_key, data.error_params || {});
                }
                
                return data;
            } else if (contentType && (contentType.includes('application/octet-stream') || contentType.includes('application/vnd.openxmlformats'))) {
                // Respuesta es un archivo (blob)
                const blob = await response.blob();
                return {
                    success: true,
                    data: blob,
                    contentType: contentType
                };
            } else {
                // Respuesta no JSON
                const text = await response.text();
                return {
                    success: true,
                    data: text
                };
            }
        } catch (error) {
            console.error('API Request Error:', error);
            return {
                success: false,
                error: 'network_error',
                error_key: 'errors.network_error',
                message: this.translate('errors.network_error') || 'Error de conexión'
            };
        }
    }

    /**
     * Establece un interceptor de errores global
     */
    setErrorInterceptor(interceptor) {
        this.errorInterceptor = interceptor;
    }

    /**
     * Actualiza las traducciones
     * NO redirige a login si falla (las traducciones no son críticas)
     */
    async updateTranslations(namespaces = ['common', 'errors', 'validation']) {
        // Si no hay token, no intentar actualizar traducciones
        if (!this.accessToken) {
            console.warn('[API] No hay token disponible para actualizar traducciones');
            return;
        }
        
        try {
            const locale = localStorage.getItem('locale') || 'es';
            const namespacesStr = namespaces.join(',');
            
            // Usar fetch directamente para evitar que handleError() redirija a login
            // Las traducciones no son críticas y no deberían causar logout
            const headers = {
                'Content-Type': 'application/json'
            };
            
            if (this.accessToken) {
                headers['Authorization'] = `Bearer ${this.accessToken}`;
            }
            
            if (this.tenantId) {
                headers['X-Tenant-ID'] = this.tenantId;
            }
            
            const response = await fetch(`${API_BASE}/i18n/translations?locale=${locale}&namespace=${namespacesStr}`, {
                method: 'GET',
                headers: headers
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data && data.success && data.data) {
                    this.translations = { ...this.translations, ...(data.data || {}) };
                    console.log('[API] Traducciones actualizadas correctamente');
                } else {
                    console.warn('[API] No se pudieron actualizar traducciones:', data);
                }
            } else {
                // Si hay error, solo loguear pero NO redirigir
                console.warn(`[API] Error al actualizar traducciones: ${response.status} ${response.statusText}`);
            }
        } catch (error) {
            // No redirigir en caso de error
            console.warn('[API] Error de red al actualizar traducciones:', error);
        }
    }
}

// Crear instancia global
console.log('[API] ========================================');
console.log('[API] Creando instancia de APIClient...');
console.log('[API] BASE_URL:', BASE_URL);
console.log('[API] API_BASE:', API_BASE);
console.log('[API] Verificando localStorage antes de crear instancia...');
console.log('[API] access_token en localStorage:', !!localStorage.getItem('access_token'));
console.log('[API] refresh_token en localStorage:', !!localStorage.getItem('refresh_token'));
console.log('[API] tenant_id en localStorage:', localStorage.getItem('tenant_id'));

const API = new APIClient();

console.log('[API] Instancia de APIClient creada');
console.log('[API] API.accessToken después de constructor:', !!API.accessToken);
console.log('[API] API.refreshToken después de constructor:', !!API.refreshToken);
console.log('[API] API.tenantId después de constructor:', API.tenantId);
console.log('[API] API.userId después de constructor:', API.userId);

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.API = API;
    console.log('[API] API client exportado a window.API');
    console.log('[API] ========================================');
}

// Exportar para módulos ES6 (si se usa)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
}

