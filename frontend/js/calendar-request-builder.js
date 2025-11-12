/**
 * Generación de Solicitud desde Huecos del Calendario
 * 
 * Detecta déficit de personal en el calendario y genera una solicitud
 */

// BASE_URL ya está definido en layout.js, solo usarlo
// Calcular API_BASE directamente (no podemos acceder a la constante de calendar.js)
function getApiBase() {
    return `${window.BASE_URL || ''}/api/v1`;
}

let selectedDateRange = null;
let detectedGaps = [];

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    setupRequestBuilder();
});

function setupRequestBuilder() {
    const btnCreateRequest = document.getElementById('btn-create-request');
    if (btnCreateRequest) {
        btnCreateRequest.addEventListener('click', () => {
            detectGapsAndCreateRequest();
        });
    }
}

/**
 * Detecta huecos en el calendario y abre wizard para crear solicitud
 */
async function detectGapsAndCreateRequest() {
    try {
        // Obtener rango de fechas visible en el calendario
        const view = window.calendar?.view;
        if (!view) {
            showToast('Por favor, selecciona un rango de fechas en el calendario', 'warning');
            return;
        }
        
        const start = view.activeStart;
        const end = view.activeEnd;
        
        // Cargar turnos en el rango
        const shifts = await loadShiftsInRange(start, end);
        
        // Analizar grupos y detectar huecos
        const gaps = analyzeGaps(shifts, start, end);
        
        if (gaps.length === 0) {
            showToast('No se detectaron huecos de personal en el rango seleccionado', 'info');
            return;
        }
        
        // Abrir wizard de solicitud con los huecos detectados
        openRequestWizard(gaps);
        
    } catch (error) {
        console.error('Error detecting gaps:', error);
        showToast('Error al detectar huecos', 'error');
    }
}

/**
 * Carga turnos en un rango de fechas
 */
async function loadShiftsInRange(start, end) {
    try {
        // Esperar a que el API client esté disponible
        if (typeof API === "undefined") {
            console.warn('[Calendar Request Builder] API client no disponible, usando fetch directo como fallback');
            const params = new URLSearchParams({
                from: formatDate(start),
                to: formatDate(end)
            });
            
            const token = localStorage.getItem('access_token');
            const response = await fetch(`${getApiBase()}/shifts?${params}`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'X-Tenant-ID': localStorage.getItem('tenant_id'),
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error('Error al cargar turnos');
            }
            
            const data = await response.json();
            return data.success ? data.data : [];
        }
        
        // Usar API client
        const response = await API.get('/shifts', {
            from: formatDate(start),
            to: formatDate(end)
        });
        
        if (response && response.success) {
            return response.data || [];
        }
        
        return [];
    } catch (error) {
        console.error('Error loading shifts:', error);
        return [];
    }
}

/**
 * Analiza turnos y detecta huecos (déficit de personal)
 */
function analyzeGaps(shifts, startDate, endDate) {
    const gaps = [];
    
    // Agrupar turnos por grupo y fecha
    const shiftsByGroup = {};
    const shiftsByDate = {};
    
    shifts.forEach(shift => {
        if (!shift.group_id) return;
        
        const date = shift.start_at.split(' ')[0];
        const groupId = shift.group_id;
        
        if (!shiftsByGroup[groupId]) {
            shiftsByGroup[groupId] = {};
        }
        if (!shiftsByGroup[groupId][date]) {
            shiftsByGroup[groupId][date] = [];
        }
        shiftsByGroup[groupId][date].push(shift);
        
        if (!shiftsByDate[date]) {
            shiftsByDate[date] = [];
        }
        shiftsByDate[date].push(shift);
    });
    
    // Analizar cada grupo para detectar huecos
    Object.keys(shiftsByGroup).forEach(groupId => {
        const groupShifts = shiftsByGroup[groupId];
        
        // Obtener capacidad esperada del grupo (TODO: cargar desde API de grupos)
        const expectedCapacity = 5; // Valor por defecto
        
        // Analizar cada fecha
        Object.keys(groupShifts).forEach(date => {
            const dayShifts = groupShifts[date];
            const assignedCount = dayShifts.length;
            
            if (assignedCount < expectedCapacity) {
                // Hay déficit
                const gap = {
                    group_id: parseInt(groupId),
                    date: date,
                    expected: expectedCapacity,
                    assigned: assignedCount,
                    needed: expectedCapacity - assignedCount,
                    shifts: dayShifts
                };
                
                gaps.push(gap);
            }
        });
    });
    
    // También detectar días sin turnos asignados
    const currentDate = new Date(startDate);
    const end = new Date(endDate);
    
    while (currentDate < end) {
        const dateStr = formatDate(currentDate);
        if (!shiftsByDate[dateStr] || shiftsByDate[dateStr].length === 0) {
            // Día sin turnos - podría ser un hueco
            gaps.push({
                group_id: null,
                date: dateStr,
                expected: 1,
                assigned: 0,
                needed: 1,
                shifts: []
            });
        }
        currentDate.setDate(currentDate.getDate() + 1);
    }
    
    return gaps;
}

/**
 * Abre wizard de solicitud con huecos detectados
 */
function openRequestWizard(gaps) {
    // Agrupar huecos por fecha y grupo
    const groupedGaps = groupGapsByDate(gaps);
    
    // Redirigir a página de solicitudes con datos prellenados
    const params = new URLSearchParams({
        from_gaps: '1',
        gaps: JSON.stringify(groupedGaps)
    });
    
    window.location.href = `${window.BASE_URL}/staffing-requests?${params}`;
}

/**
 * Agrupa huecos por fecha para crear solicitud
 */
function groupGapsByDate(gaps) {
    const grouped = {};
    
    gaps.forEach(gap => {
        const date = gap.date;
        if (!grouped[date]) {
            grouped[date] = {
                date: date,
                groups: [],
                total_needed: 0
            };
        }
        
        if (gap.group_id) {
            grouped[date].groups.push({
                group_id: gap.group_id,
                needed: gap.needed
            });
        }
        
        grouped[date].total_needed += gap.needed;
    });
    
    return Object.values(grouped);
}

/**
 * Formatea fecha para API (YYYY-MM-DD)
 */
function formatDate(date) {
    const d = new Date(date);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function showToast(message, type = 'info') {
    // Implementación simple de toast
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : type} position-fixed top-0 end-0 m-3`;
    toast.style.zIndex = '9999';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

