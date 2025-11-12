/**
 * Reports UI
 * 
 * Gestión de reportes avanzados con selector de tipo, filtros y export
 */

let currentReportData = null;
let currentReportType = null;
let reportsTable = null;

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    initializeFilters();
    setupEventListeners();
    setDefaultDates();
});

/**
 * Inicializa los filtros según el tipo de reporte
 */
function initializeFilters() {
    const reportType = document.getElementById('reportType');
    const container = document.getElementById('additionalFiltersContainer');
    
    reportType.addEventListener('change', () => {
        updateAdditionalFilters(reportType.value);
    });
    
    // Inicializar con el primer tipo
    updateAdditionalFilters(reportType.value);
}

/**
 * Actualiza los filtros adicionales según el tipo de reporte
 */
function updateAdditionalFilters(type) {
    const container = document.getElementById('additionalFiltersContainer');
    container.innerHTML = '';
    
    if (type === 'hours') {
        // Filtros para reporte de horas
        container.innerHTML = `
            <div class="col-md-6">
                <label for="employeeId" class="form-label" data-i18n="reports.employee">Empleado</label>
                <select class="form-select" id="employeeId" name="employee_id">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="projectId" class="form-label" data-i18n="reports.project">Proyecto</label>
                <select class="form-select" id="projectId" name="project_id">
                    <option value="">Todos</option>
                </select>
            </div>
        `;
        loadEmployees();
        loadProjects();
    } else if (type === 'planning') {
        // Filtros para reporte de planificación
        container.innerHTML = `
            <div class="col-md-6">
                <label for="projectId" class="form-label" data-i18n="reports.project">Proyecto</label>
                <select class="form-select" id="projectId" name="project_id">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="groupId" class="form-label" data-i18n="reports.group">Grupo</label>
                <select class="form-select" id="groupId" name="group_id">
                    <option value="">Todos</option>
                </select>
            </div>
        `;
        loadProjects();
        loadGroups();
    } else if (type === 'company-agency') {
        // No hay filtros adicionales para este tipo
        container.innerHTML = '';
    }
}

/**
 * Configura los event listeners
 */
function setupEventListeners() {
    const form = document.getElementById('reportFiltersForm');
    form.addEventListener('submit', handleGenerateReport);
    
    const exportBtn = document.getElementById('exportBtn');
    exportBtn.addEventListener('click', handleExportCSV);
}

/**
 * Establece fechas por defecto (mes actual)
 */
function setDefaultDates() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    document.getElementById('dateFrom').value = firstDay.toISOString().split('T')[0];
    document.getElementById('dateTo').value = lastDay.toISOString().split('T')[0];
}

/**
 * Maneja la generación del reporte
 */
async function handleGenerateReport(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const params = new URLSearchParams();
    
    // Agregar todos los campos del formulario
    for (const [key, value] of formData.entries()) {
        if (value) {
            params.append(key, value);
        }
    }
    
    const reportType = formData.get('type');
    currentReportType = reportType;
    
    try {
        const response = await API.get(`/api/reports/${reportType}?${params.toString()}`);
        
        if (response.success) {
            currentReportData = response;
            displayReport(response);
            document.getElementById('exportBtn').style.display = 'inline-block';
        } else {
            Toast.error(response.error_key || 'Error al generar reporte');
        }
    } catch (error) {
        console.error('Error generando reporte:', error);
        Toast.error('Error al generar reporte');
    }
}

/**
 * Muestra el reporte en la tabla
 */
function displayReport(data) {
    // Mostrar resumen
    displaySummary(data);
    
    // Configurar tabla según tipo de reporte
    const container = document.getElementById('reportsTableContainer');
    container.innerHTML = '';
    
    if (!data.data || data.data.length === 0) {
        const emptyState = new EmptyState(container, {
            icon: 'bi-graph-up',
            title: t('reports.no_data'),
            message: t('reports.no_results')
        });
        return;
    }
    
    // Configurar columnas según tipo
    let columns = [];
    if (currentReportType === 'hours') {
        columns = [
            { key: 'date', label: t('reports.date'), sortable: true },
            { key: 'employee', label: t('reports.employee'), render: (row) => row.employee?.name || row.worker?.name || '-' },
            { key: 'clock_in', label: t('reports.clock_in') },
            { key: 'clock_out', label: t('reports.clock_out') },
            { key: 'hours', label: t('reports.hours'), render: (row) => row.hours.toFixed(2) },
            { key: 'project', label: t('reports.project'), render: (row) => row.project?.name || '-' },
            { key: 'group', label: t('reports.group'), render: (row) => row.group?.name || '-' }
        ];
    } else if (currentReportType === 'planning') {
        columns = [
            { key: 'date', label: t('reports.date'), sortable: true },
            { key: 'start_time', label: t('reports.start_time') },
            { key: 'end_time', label: t('reports.end_time') },
            { key: 'project', label: t('reports.project'), render: (row) => row.project?.name || '-' },
            { key: 'group', label: t('reports.group'), render: (row) => row.group?.name || '-' },
            { key: 'assigned_count', label: t('reports.assigned_count') },
            { key: 'employees_count', label: t('reports.employees_count') },
            { key: 'workers_count', label: t('reports.workers_count') }
        ];
    } else if (currentReportType === 'company-agency') {
        columns = [
            { key: 'partner_name', label: t('reports.partner'), sortable: true },
            { key: 'link_status', label: t('reports.status') },
            { key: 'requests_count', label: t('reports.requests_count') },
            { key: 'proposals_count', label: t('reports.proposals_count') },
            { key: 'shifts_count', label: t('reports.shifts_count') },
            { key: 'work_logs_count', label: t('reports.work_logs_count') },
            { key: 'total_hours', label: t('reports.total_hours'), render: (row) => row.total_hours.toFixed(2) }
        ];
    }
    
    // Crear DataTable
    reportsTable = new DataTable(container, {
        columns: columns,
        data: data.data,
        pagination: false, // Los datos ya vienen paginados del servidor
        searchable: false
    });
}

/**
 * Muestra el resumen del reporte
 */
function displaySummary(data) {
    const summaryContainer = document.getElementById('reportSummary');
    summaryContainer.style.display = 'grid';
    summaryContainer.innerHTML = '';
    
    if (!data.summary) return;
    
    // Crear KPI cards según tipo
    if (currentReportType === 'hours') {
        summaryContainer.innerHTML = `
            <div class="kpi-card kpi-card--primary">
                <div class="kpi-card__header">
                    <div>
                        <div class="kpi-card__value">${data.summary.total_hours.toFixed(2)}</div>
                        <div class="kpi-card__label">${t('reports.total_hours')}</div>
                    </div>
                </div>
            </div>
            <div class="kpi-card kpi-card--info">
                <div class="kpi-card__header">
                    <div>
                        <div class="kpi-card__value">${data.summary.total_records}</div>
                        <div class="kpi-card__label">${t('reports.total_records')}</div>
                    </div>
                </div>
            </div>
        `;
    } else if (currentReportType === 'planning') {
        summaryContainer.innerHTML = `
            <div class="kpi-card kpi-card--primary">
                <div class="kpi-card__header">
                    <div>
                        <div class="kpi-card__value">${data.summary.total_shifts}</div>
                        <div class="kpi-card__label">${t('reports.total_shifts')}</div>
                    </div>
                </div>
            </div>
        `;
    } else if (currentReportType === 'company-agency') {
        summaryContainer.innerHTML = `
            <div class="kpi-card kpi-card--primary">
                <div class="kpi-card__header">
                    <div>
                        <div class="kpi-card__value">${data.summary.total_partners}</div>
                        <div class="kpi-card__label">${t('reports.total_partners')}</div>
                    </div>
                </div>
            </div>
            <div class="kpi-card kpi-card--info">
                <div class="kpi-card__header">
                    <div>
                        <div class="kpi-card__value">${data.summary.total_hours.toFixed(2)}</div>
                        <div class="kpi-card__label">${t('reports.total_hours')}</div>
                    </div>
                </div>
            </div>
        `;
    }
}

/**
 * Maneja la exportación a CSV
 */
function handleExportCSV() {
    if (!currentReportData || !currentReportType) {
        Toast.error('No hay datos para exportar');
        return;
    }
    
    const formData = new FormData(document.getElementById('reportFiltersForm'));
    const params = new URLSearchParams();
    
    for (const [key, value] of formData.entries()) {
        if (value) {
            params.append(key, value);
        }
    }
    
    params.append('format', 'csv');
    
    // Abrir en nueva ventana para descargar
    window.open(`/api/reports/${currentReportType}?${params.toString()}`, '_blank');
}

/**
 * Carga lista de empleados
 */
async function loadEmployees() {
    try {
        const response = await API.get('/api/own-employees?page_size=1000');
        const select = document.getElementById('employeeId');
        
        if (response.success && response.data) {
            response.data.forEach(emp => {
                const option = document.createElement('option');
                option.value = emp.id;
                option.textContent = `${emp.first_name} ${emp.last_name}`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error cargando empleados:', error);
    }
}

/**
 * Carga lista de proyectos
 */
async function loadProjects() {
    try {
        // Intentar obtener proyectos desde work-orders o usar endpoint alternativo
        const response = await API.get('/api/work-orders?page_size=1000');
        const select = document.getElementById('projectId');
        
        if (response.success && response.data) {
            // Extraer proyectos únicos de work orders
            const projectsMap = new Map();
            response.data.forEach(wo => {
                if (wo.project && wo.project.id && !projectsMap.has(wo.project.id)) {
                    projectsMap.set(wo.project.id, wo.project);
                }
            });
            
            projectsMap.forEach(proj => {
                const option = document.createElement('option');
                option.value = proj.id;
                option.textContent = proj.name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error cargando proyectos:', error);
    }
}

/**
 * Carga lista de grupos
 */
async function loadGroups() {
    try {
        const response = await API.get('/api/work-groups?page_size=1000');
        const select = document.getElementById('groupId');
        
        if (response.success && response.data) {
            response.data.forEach(group => {
                const option = document.createElement('option');
                option.value = group.id;
                option.textContent = group.name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error cargando grupos:', error);
    }
}

