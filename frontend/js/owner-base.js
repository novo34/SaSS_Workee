/**
 * JavaScript base para Owner Dashboard
 * Mobile-first, modular
 */

// Configuración
// Obtener base URL desde window.BASE_URL (inyectado por index.php) o usar default
const BASE_URL = (typeof window !== 'undefined' && window.BASE_URL) || '';
const API_BASE = `${BASE_URL}/api/v1`;
let accessToken = null;
let refreshToken = null;

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    loadTokens();
    loadDashboard();
});

/**
 * Carga tokens desde localStorage
 */
function loadTokens() {
    accessToken = localStorage.getItem('access_token');
    refreshToken = localStorage.getItem('refresh_token');
}

/**
 * Realiza petición autenticada
 * Usa API client si está disponible, sino usa fetch directo como fallback
 */
async function apiRequest(endpoint, options = {}) {
    // Intentar usar API client si está disponible
    if (typeof API !== "undefined") {
        try {
            // Mapear método HTTP
            const method = (options.method || 'GET').toUpperCase();
            const body = options.body ? JSON.parse(options.body) : undefined;
            
            let response;
            switch (method) {
                case 'GET':
                    response = await API.get(endpoint);
                    break;
                case 'POST':
                    response = await API.post(endpoint, body);
                    break;
                case 'PUT':
                    response = await API.put(endpoint, body);
                    break;
                case 'PATCH':
                    response = await API.patch(endpoint, body);
                    break;
                case 'DELETE':
                    response = await API.delete(endpoint);
                    break;
                default:
                    throw new Error(`Método HTTP no soportado: ${method}`);
            }
            
            return response;
        } catch (error) {
            console.error('[Owner Base] Error usando API client:', error);
            // Fallback a fetch directo
        }
    }
    
    // Fallback: usar fetch directo
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers
    };
    
    if (accessToken) {
        headers['Authorization'] = `Bearer ${accessToken}`;
    }
    
    const response = await fetch(`${API_BASE}${endpoint}`, {
        ...options,
        headers
    });
    
    if (response.status === 401) {
        // Intentar refresh token
        if (refreshToken) {
            const refreshed = await refreshAccessToken();
            if (refreshed) {
                return apiRequest(endpoint, options);
            }
        }
        // Redirigir a login
        window.location.href = `${BASE_URL}/login`;
        return null;
    }
    
    return response.json();
}

/**
 * Refresca el access token
 * Usa API client si está disponible, sino usa fetch directo
 */
async function refreshAccessToken() {
    if (!refreshToken) return false;
    
    try {
        // Intentar usar API client si está disponible
        if (typeof API !== "undefined" && API.refreshAccessToken) {
            const refreshed = await API.refreshAccessToken();
            if (refreshed) {
                // Actualizar tokens locales
                accessToken = API.accessToken;
                refreshToken = API.refreshToken;
                return true;
            }
            return false;
        }
        
        // Fallback: usar fetch directo
        const response = await fetch(`${API_BASE}/auth/refresh`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ refresh_token: refreshToken })
        });
        
        const data = await response.json();
        
        if (data.success && data.data.access_token) {
            accessToken = data.data.access_token;
            refreshToken = data.data.refresh_token || refreshToken;
            localStorage.setItem('access_token', accessToken);
            localStorage.setItem('refresh_token', refreshToken);
            return true;
        }
    } catch (error) {
        console.error('Error refreshing token:', error);
    }
    
    return false;
}

/**
 * Carga datos del dashboard
 */
async function loadDashboard() {
    try {
        // Cargar métricas
        const metrics = await apiRequest('/owner/metrics');
        if (metrics && metrics.success) {
            updateMetrics(metrics.data);
        }
        
        // Cargar actividad
        const activity = await apiRequest('/owner/activity?limit=10');
        if (activity && activity.success) {
            updateActivity(activity.data);
        }
        
        // Cargar distribución de planes
        const plans = await apiRequest('/owner/plans-distribution');
        if (plans && plans.success) {
            updatePlanDistribution(plans.data);
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

/**
 * Actualiza métricas en el DOM
 */
function updateMetrics(data) {
    document.getElementById('metric-tenants').textContent = data.total_tenants || 0;
    document.getElementById('metric-mrr').textContent = formatCurrency(data.mrr || 0);
    document.getElementById('metric-users').textContent = data.active_users || 0;
    document.getElementById('metric-churn').textContent = formatPercent(data.churn_rate || 0);
}

/**
 * Actualiza lista de actividad
 */
function updateActivity(data) {
    const container = document.getElementById('activity-list');
    
    if (!data || data.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay actividad reciente</p>';
        return;
    }
    
    const html = data.map(item => `
        <div class="d-flex justify-content-between align-items-start mb-2 pb-2 border-bottom">
            <div>
                <strong>${item.action}</strong>
                <p class="mb-0 text-muted small">${item.description}</p>
            </div>
            <small class="text-muted">${formatDate(item.created_at)}</small>
        </div>
    `).join('');
    
    container.innerHTML = html;
}

/**
 * Actualiza distribución de planes
 */
function updatePlanDistribution(data) {
    const container = document.getElementById('plan-distribution');
    
    if (!data || Object.keys(data).length === 0) {
        container.innerHTML = '<p class="text-muted">No hay datos</p>';
        return;
    }
    
    const html = Object.entries(data).map(([plan, count]) => `
        <div class="d-flex justify-content-between mb-2">
            <span>${plan}</span>
            <strong>${count}</strong>
        </div>
    `).join('');
    
    container.innerHTML = html;
}

/**
 * Formatea moneda
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'CHF'
    }).format(amount);
}

/**
 * Formatea porcentaje
 */
function formatPercent(value) {
    return `${(value * 100).toFixed(2)}%`;
}

/**
 * Formatea fecha
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(date);
}

