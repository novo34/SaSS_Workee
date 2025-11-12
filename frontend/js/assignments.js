/**
 * JavaScript para Gestión de Asignaciones (REFACTORIZADO)
 * 
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications
 * - Modal component
 */

let currentPage = 1;
let currentFilters = {};
let assignModal = null;

/**
 * Espera a que los componentes estén cargados
 */
async function waitForComponents() {
    const maxAttempts = 50; // 5 segundos máximo
    let attempts = 0;
    
    while (attempts < maxAttempts) {
        if (typeof API !== 'undefined' && typeof Toast !== 'undefined' && typeof Modal !== 'undefined') {
            return true;
        }
        await new Promise(resolve => setTimeout(resolve, 100));
        attempts++;
    }
    
    console.error('Componentes no cargados después de 5 segundos. Asegúrate de incluir components-loader.js');
    return false;
}

document.addEventListener('DOMContentLoaded', async () => {
    // Esperar a que los componentes estén cargados
    const componentsReady = await waitForComponents();
    if (!componentsReady) {
        console.error('No se pueden cargar los componentes. Algunas funcionalidades pueden no estar disponibles.');
    }
    
    // Inicializar LoadingSpinner
    if (typeof LoadingSpinner !== 'undefined') {
        const spinnerContainer = document.getElementById('loading-spinner-container');
        if (spinnerContainer) {
            window.loadingSpinner = new LoadingSpinner(spinnerContainer);
        }
    }
    
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    loadAssignments();
    setupEventListeners();
    loadGroups();
    loadEmployees();
});

function setupEventListeners() {
    // Botón asignar trabajador
    const btnAssign = document.getElementById('btn-assign-worker');
    if (btnAssign) {
        btnAssign.addEventListener('click', () => {
            openAssignModal();
        });
    }
    
    // Confirmar asignación
    const btnConfirm = document.getElementById('btn-confirm-assign');
    if (btnConfirm) {
        btnConfirm.addEventListener('click', createAssignment);
    }
    
    // Tipo de empleado
    const employeeType = document.getElementById('assign-employee-type');
    if (employeeType) {
        employeeType.addEventListener('change', handleEmployeeTypeChange);
    }
    
    // Filtros
    const filterFrom = document.getElementById('filter-from');
    const filterTo = document.getElementById('filter-to');
    const filterStatus = document.getElementById('filter-status');
    const btnClearFilters = document.getElementById('btn-clear-filters');
    
    if (filterFrom) filterFrom.addEventListener('change', applyFilters);
    if (filterTo) filterTo.addEventListener('change', applyFilters);
    if (filterStatus) filterStatus.addEventListener('change', applyFilters);
    if (btnClearFilters) btnClearFilters.addEventListener('click', clearFilters);
}

async function loadAssignments(page = 1, filters = {}) {
    // Verificar que API esté disponible
    if (typeof API === 'undefined') {
        console.error('API no está disponible. Esperando componentes...');
        const componentsReady = await waitForComponents();
        if (!componentsReady) {
            const container = document.getElementById('assignments-container');
            if (container) {
                container.innerHTML = '<div class="alert alert-danger">Error: No se pudieron cargar los componentes necesarios. Por favor, recarga la página.</div>';
            }
            return;
        }
    }
    
    currentPage = page;
    currentFilters = filters;
    
    const container = document.getElementById('assignments-container');
    if (!container) return;
    
    // Mostrar loading spinner
    if (window.loadingSpinner) {
        window.loadingSpinner.show('Cargando asignaciones...');
    } else {
        container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
    }
    
    try {
        const params = {
            page: page,
            page_size: 20,
            ...filters
        };
        
        const response = await API.get('/assignments', params);
        
        if (response && response.success) {
            renderAssignments(response.data || []);
            renderPagination(response);
            
            // Ocultar loading spinner
            if (window.loadingSpinner) {
                window.loadingSpinner.hide();
            }
        } else {
            container.innerHTML = '<div class="alert alert-warning">No se pudieron cargar las asignaciones</div>';
            if (typeof Toast !== 'undefined') {
                Toast.error(response?.message || 'Error al cargar asignaciones');
            }
            
            // Ocultar loading spinner
            if (window.loadingSpinner) {
                window.loadingSpinner.hide();
            }
        }
    } catch (error) {
        console.error('Error loading assignments:', error);
        container.innerHTML = '<div class="alert alert-danger">Error al cargar asignaciones. Por favor, verifica la consola para más detalles.</div>';
        if (typeof Toast !== 'undefined') {
            Toast.error('Error al cargar asignaciones');
        }
        
        // Ocultar loading spinner
        if (window.loadingSpinner) {
            window.loadingSpinner.hide();
        }
    }
}

function renderAssignments(assignments) {
    const container = document.getElementById('assignments-container');
    if (!container) return;
    
    // Ocultar loading spinner
    if (window.loadingSpinner) {
        window.loadingSpinner.hide();
    }
    
    if (assignments.length === 0) {
        // Usar EmptyState component si está disponible
        if (typeof EmptyState !== 'undefined') {
            const emptyState = new EmptyState(container, {
                icon: '📭',
                title: 'No hay asignaciones',
                message: 'No se encontraron asignaciones para mostrar.'
            });
            emptyState.show();
        } else {
            container.innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="text-muted mt-3" data-i18n="assignments.no_assignments">No hay asignaciones</p>
                </div>
            `;
        }
        return;
    }
    
    container.innerHTML = assignments.map(assignment => `
        <div class="card-modern mb-3">
            <div class="card-modern__body">
                <div class="row align-items-center">
                    <div class="col-12 col-md-3">
                        <div class="fw-bold">${escapeHtml(assignment.employee_name || 'Sin asignar')}</div>
                        <div class="small text-muted">
                            ${assignment.employee_type === 'own_employee' ? 'Empleado Propio' : 'Trabajador Temporero'}
                        </div>
                        ${assignment.agency_name ? `<div class="small text-info">${escapeHtml(assignment.agency_name)}</div>` : ''}
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="small text-muted" data-i18n="assignments.start">Inicio</div>
                        <div>${formatDateTime(assignment.start_at)}</div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="small text-muted" data-i18n="assignments.end">Fin</div>
                        <div>${formatDateTime(assignment.end_at)}</div>
                    </div>
                    <div class="col-12 col-md-2">
                        <span class="badge-modern badge-modern--${getStatusColor(assignment.status)}">
                            ${getStatusText(assignment.status)}
                        </span>
                        ${assignment.group_name ? `<div class="small mt-1">${escapeHtml(assignment.group_name)}</div>` : ''}
                    </div>
                    <div class="col-12 col-md-1 text-end">
                        <button class="btn-modern btn-modern--sm btn-modern--danger" onclick="cancelAssignment(${assignment.id})" aria-label="Cancelar asignación">
                            <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                ${assignment.location ? `
                    <div class="row mt-2">
                        <div class="col-12">
                            <small class="text-muted">
                                <i class="bi bi-geo-alt"></i> ${escapeHtml(assignment.location)}
                            </small>
                        </div>
                    </div>
                ` : ''}
            </div>
        </div>
    `).join('');
}

function renderPagination(data) {
    const container = document.getElementById('pagination-container');
    const pagination = document.getElementById('pagination');
    
    if (!container || !pagination) return;
    
    if (data.total_pages <= 1) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'block';
    
    let html = '';
    
    // Botón anterior
    html += `
        <li class="page-item ${data.page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadAssignments(${data.page - 1}); return false;">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
    `;
    
    // Páginas
    for (let i = 1; i <= data.total_pages; i++) {
        if (i === 1 || i === data.total_pages || (i >= data.page - 2 && i <= data.page + 2)) {
            html += `
                <li class="page-item ${i === data.page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadAssignments(${i}); return false;">${i}</a>
                </li>
            `;
        } else if (i === data.page - 3 || i === data.page + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Botón siguiente
    html += `
        <li class="page-item ${data.page === data.total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadAssignments(${data.page + 1}); return false;">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    `;
    
    pagination.innerHTML = html;
}

function openAssignModal() {
    const modalEl = document.getElementById('modal-assign');
    if (!modalEl) return;
    
    // Usar Modal component si está disponible
    if (typeof Modal !== 'undefined') {
        // El modal HTML ya existe, solo mostrarlo con Bootstrap
        // En una refactorización futura, podríamos crear el modal dinámicamente
        const bootstrapModal = new bootstrap.Modal(modalEl);
        bootstrapModal.show();
    } else {
        // Fallback a Bootstrap directo
        const bootstrapModal = new bootstrap.Modal(modalEl);
        bootstrapModal.show();
    }
}

function handleEmployeeTypeChange() {
    const type = document.getElementById('assign-employee-type')?.value;
    const ownContainer = document.getElementById('container-own-employee');
    const agencyContainer = document.getElementById('container-agency-worker');
    
    if (!ownContainer || !agencyContainer) return;
    
    if (type === 'own_employee') {
        ownContainer.style.display = 'block';
        agencyContainer.style.display = 'none';
        const ownSelect = document.getElementById('assign-own-employee');
        const agencySelect = document.getElementById('assign-agency-worker');
        if (ownSelect) ownSelect.required = true;
        if (agencySelect) agencySelect.required = false;
    } else {
        ownContainer.style.display = 'none';
        agencyContainer.style.display = 'block';
        const ownSelect = document.getElementById('assign-own-employee');
        const agencySelect = document.getElementById('assign-agency-worker');
        if (ownSelect) ownSelect.required = false;
        if (agencySelect) agencySelect.required = true;
    }
}

async function createAssignment() {
    const form = document.getElementById('form-assign');
    if (!form || !form.checkValidity()) {
        if (form) form.reportValidity();
        return;
    }
    
    const employeeType = document.getElementById('assign-employee-type')?.value;
    
    const assignmentData = {
        start_at: document.getElementById('assign-start')?.value.replace('T', ' ') + ':00',
        end_at: document.getElementById('assign-end')?.value.replace('T', ' ') + ':00',
        group_id: document.getElementById('assign-group')?.value || null,
        employee_type: employeeType,
        own_employee_id: employeeType === 'own_employee' ? document.getElementById('assign-own-employee')?.value : null,
        agency_worker_id: employeeType === 'agency_worker' ? document.getElementById('assign-agency-worker')?.value : null,
        location: document.getElementById('assign-location')?.value || null
    };
    
    try {
        const response = await API.post('/assignments', assignmentData);
        
        if (response && response.success) {
            // Cerrar modal
            const modalEl = document.getElementById('modal-assign');
            if (modalEl) {
                const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
                if (bootstrapModal) bootstrapModal.hide();
            }
            
            loadAssignments();
            Toast.success('Trabajador asignado correctamente');
        } else {
            Toast.error(response?.message || 'Error al asignar trabajador');
        }
    } catch (error) {
        console.error('Error creating assignment:', error);
        Toast.error('Error al asignar trabajador');
    }
}

async function cancelAssignment(assignmentId) {
    const confirmed = await Modal.confirm({
        title: 'Cancelar Asignación',
        message: '¿Estás seguro de que deseas cancelar esta asignación?',
        confirmLabel: 'Cancelar Asignación',
        cancelLabel: 'No'
    });
    
    if (!confirmed) return;
    
    try {
        const response = await API.delete(`/assignments/${assignmentId}`);
        
        if (response && response.success) {
            loadAssignments();
            Toast.success('Asignación cancelada correctamente');
        } else {
            Toast.error(response?.message || 'Error al cancelar asignación');
        }
    } catch (error) {
        console.error('Error canceling assignment:', error);
        Toast.error('Error al cancelar asignación');
    }
}

function applyFilters() {
    const filters = {};
    
    const from = document.getElementById('filter-from')?.value;
    if (from) filters.from = from;
    
    const to = document.getElementById('filter-to')?.value;
    if (to) filters.to = to;
    
    const status = document.getElementById('filter-status')?.value;
    if (status) filters.status = status;
    
    loadAssignments(1, filters);
}

function clearFilters() {
    const filterFrom = document.getElementById('filter-from');
    const filterTo = document.getElementById('filter-to');
    const filterStatus = document.getElementById('filter-status');
    
    if (filterFrom) filterFrom.value = '';
    if (filterTo) filterTo.value = '';
    if (filterStatus) filterStatus.value = '';
    
    loadAssignments(1, {});
}

async function loadGroups() {
    try {
        // TODO: Cargar grupos desde API cuando esté disponible
        const select = document.getElementById('assign-group');
        // Por ahora, dejar vacío
    } catch (error) {
        console.error('Error loading groups:', error);
    }
}

async function loadEmployees() {
    try {
        // TODO: Cargar empleados y trabajadores desde API cuando esté disponible
        const ownSelect = document.getElementById('assign-own-employee');
        const agencySelect = document.getElementById('assign-agency-worker');
        // Por ahora, dejar vacío
    } catch (error) {
        console.error('Error loading employees:', error);
    }
}

function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getStatusColor(status) {
    const colors = {
        'assigned': 'primary',
        'in_progress': 'warning',
        'done': 'success',
        'canceled': 'danger'
    };
    return colors[status] || 'secondary';
}

function getStatusText(status) {
    const texts = {
        'assigned': 'Asignado',
        'in_progress': 'En curso',
        'done': 'Completado',
        'canceled': 'Cancelado'
    };
    return texts[status] || status;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Hacer funciones accesibles globalmente
window.cancelAssignment = cancelAssignment;
window.loadAssignments = loadAssignments;
