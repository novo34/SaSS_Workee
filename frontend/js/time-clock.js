/**
 * Fichaje Individual (Time Clock)
 */

let currentStatus = null;
let clockInterval = null;
let projects = [];

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    initializeTimeClock();
});

function initializeTimeClock() {
    // Cargar estado inicial
    loadStatus();
    loadProjects();
    loadHistory();
    
    // Event listeners
    document.getElementById('btn-clock-in').addEventListener('click', clockIn);
    document.getElementById('btn-clock-out').addEventListener('click', clockOut);
    document.getElementById('btn-add-break').addEventListener('click', addBreak);
    document.getElementById('filter-from').addEventListener('change', loadHistory);
    document.getElementById('filter-to').addEventListener('change', loadHistory);
    
    // Establecer fechas por defecto
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    
    document.getElementById('filter-from').value = formatDate(thirtyDaysAgo);
    document.getElementById('filter-to').value = formatDate(today);
    
    // Actualizar reloj cada segundo
    clockInterval = setInterval(updateClock, 1000);
}

/**
 * Carga el estado actual del fichaje
 */
async function loadStatus() {
    try {
        const response = await API.get('/time-clock/status');
        
        if (response.success) {
            currentStatus = response.data;
            updateUI();
        } else {
            Toast.error(response.message || 'Error al cargar estado');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al cargar estado del fichaje');
    }
}

/**
 * Actualiza la UI según el estado
 */
function updateUI() {
    const isClockedIn = currentStatus.is_clocked_in;
    const workLog = currentStatus.current_work_log;
    
    // Mostrar/ocultar botones
    document.getElementById('btn-clock-in').style.display = isClockedIn ? 'none' : 'block';
    document.getElementById('btn-clock-out').style.display = isClockedIn ? 'block' : 'none';
    
    // Mostrar/ocultar secciones
    document.getElementById('break-section').style.display = isClockedIn ? 'block' : 'none';
    document.getElementById('project-section').style.display = !isClockedIn ? 'block' : 'none';
    document.getElementById('notes-section').style.display = !isClockedIn ? 'block' : 'none';
    
    // Actualizar estado
    if (isClockedIn && workLog) {
        document.getElementById('clock-status').textContent = 'Fichado - En trabajo';
        document.getElementById('clock-status').style.color = 'var(--color-success)';
        
        const clockInTime = new Date(workLog.clock_in);
        document.getElementById('clock-info').textContent = 
            `Entrada: ${formatDateTime(clockInTime)}${workLog.project ? ' - ' + workLog.project.name : ''}`;
        
        // Actualizar total de pausas
        document.getElementById('total-breaks-minutes').textContent = workLog.breaks_minutes || 0;
    } else {
        document.getElementById('clock-status').textContent = 'No fichado';
        document.getElementById('clock-status').style.color = 'var(--color-text-muted)';
        document.getElementById('clock-info').textContent = 'Listo para fichar';
    }
    
    updateClock();
}

/**
 * Actualiza el reloj
 */
function updateClock() {
    if (!currentStatus || !currentStatus.is_clocked_in || !currentStatus.current_work_log) {
        document.getElementById('clock-time').textContent = '00:00:00';
        return;
    }
    
    const workLog = currentStatus.current_work_log;
    const clockIn = new Date(workLog.clock_in);
    const now = new Date();
    
    // Calcular diferencia
    let diff = Math.floor((now - clockIn) / 1000); // segundos
    diff -= (workLog.breaks_minutes || 0) * 60; // restar pausas
    
    if (diff < 0) diff = 0;
    
    const hours = Math.floor(diff / 3600);
    const minutes = Math.floor((diff % 3600) / 60);
    const seconds = diff % 60;
    
    document.getElementById('clock-time').textContent = 
        `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

/**
 * Clock in
 */
async function clockIn() {
    const projectId = document.getElementById('select-project').value || null;
    const notes = document.getElementById('clock-notes').value.trim() || null;
    
    const data = {};
    if (projectId) {
        data.project_id = parseInt(projectId);
    }
    if (notes) {
        data.notes = notes;
    }
    
    try {
        const response = await API.post('/time-clock/in', data);
        
        if (response.success) {
            Toast.success(response.message || 'Fichaje de entrada registrado');
            document.getElementById('clock-notes').value = '';
            loadStatus();
        } else {
            Toast.error(response.message || 'Error al registrar entrada');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al registrar entrada');
    }
}

/**
 * Clock out
 */
async function clockOut() {
    // Usar Modal.confirm si está disponible, sino usar confirm nativo como fallback
    let confirmed = false;
    if (typeof Modal !== "undefined") {
        confirmed = await Modal.confirm({
            title: 'Fichar Salida',
            message: '¿Estás seguro de que deseas fichar la salida?'
        });
    } else {
        confirmed = confirm('¿Estás seguro de que deseas fichar la salida?');
    }
    
    if (!confirmed) {
        return;
    }
    
    const notes = document.getElementById('clock-notes').value.trim() || null;
    
    const data = {};
    if (notes) {
        data.notes = notes;
    }
    
    try {
        const response = await API.post('/time-clock/out', data);
        
        if (response.success) {
            Toast.success(response.message || 'Fichaje de salida registrado');
            document.getElementById('clock-notes').value = '';
            loadStatus();
            loadHistory(); // Recargar historial
        } else {
            Toast.error(response.message || 'Error al registrar salida');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al registrar salida');
    }
}

/**
 * Agrega una pausa
 */
async function addBreak() {
    const minutes = parseInt(document.getElementById('break-minutes').value);
    
    if (!minutes || minutes <= 0) {
        Toast.error('Ingresa un número válido de minutos');
        return;
    }
    
    if (!currentStatus || !currentStatus.is_clocked_in) {
        Toast.error('Debes estar fichado para agregar una pausa');
        return;
    }
    
    try {
        const response = await API.post('/time-clock/break/start', { minutes: minutes });
        
        if (response.success) {
            Toast.success(response.message || 'Pausa registrada');
            loadStatus();
        } else {
            Toast.error(response.message || 'Error al registrar pausa');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al registrar pausa');
    }
}

/**
 * Carga los proyectos disponibles
 */
async function loadProjects() {
    try {
        const response = await API.get('/projects', { page_size: 100, is_active: 1 });
        
        if (response.success && response.data) {
            projects = response.data;
            const select = document.getElementById('select-project');
            
            projects.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = project.name + (project.code ? ` (${project.code})` : '');
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error al cargar proyectos:', error);
    }
}

/**
 * Carga el historial de fichajes
 */
async function loadHistory() {
    const from = document.getElementById('filter-from').value;
    const to = document.getElementById('filter-to').value;
    
    if (!from || !to) {
        return;
    }
    
    const container = document.getElementById('history-container');
    container.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
    
    try {
        const response = await API.get('/time-clock/history', {
            from: from,
            to: to,
            page: 1,
            page_size: 50
        });
        
        if (response.success) {
            renderHistory(response.data);
        } else {
            container.innerHTML = '<div class="text-center text-muted py-4">Error al cargar historial</div>';
        }
    } catch (error) {
        console.error('Error:', error);
        container.innerHTML = '<div class="text-center text-muted py-4">Error al cargar historial</div>';
    }
}

/**
 * Renderiza el historial
 */
function renderHistory(workLogs) {
    const container = document.getElementById('history-container');
    
    if (workLogs.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">No hay fichajes en este período</div>';
        return;
    }
    
    container.innerHTML = workLogs.map(log => {
        const date = new Date(log.date);
        const clockIn = new Date(log.clock_in);
        const clockOut = log.clock_out ? new Date(log.clock_out) : null;
        
        return `
            <div class="history-item">
                <div class="history-item-header">
                    <span class="history-item-date">${formatDate(date)}</span>
                    <span class="history-item-hours">${log.total_hours ? log.total_hours + 'h' : 'En curso'}</span>
                </div>
                <div class="history-item-times">
                    <span>Entrada: ${formatTime(clockIn)}</span>
                    <span>${clockOut ? 'Salida: ' + formatTime(clockOut) : 'Sin salida'}</span>
                </div>
                ${log.breaks_minutes > 0 ? `<div class="text-muted small mt-1">Pausas: ${log.breaks_minutes} min</div>` : ''}
                ${log.project ? `<div class="text-muted small mt-1">Proyecto: ${log.project.name}</div>` : ''}
                ${log.notes ? `<div class="text-muted small mt-1">${log.notes}</div>` : ''}
            </div>
        `;
    }).join('');
}

/**
 * Formatea una fecha como YYYY-MM-DD
 */
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Formatea una fecha/hora
 */
function formatDateTime(date) {
    return date.toLocaleString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Formatea una hora
 */
function formatTime(date) {
    return date.toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

