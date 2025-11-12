/**
 * JavaScript para Calendario/Gantt de Órdenes de Trabajo (REFACTORIZADO)
 * Muestra asignaciones de OT en un calendario interactivo
 * 
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications (si es necesario)
 */

// Asegurar que los componentes estén cargados
if (typeof API === 'undefined') {
    console.error('API Client no cargado. Asegúrate de incluir components-loader.js');
}

let calendar;
let calendarEl;
let currentFilters = {
    work_order_id: null,
    group_id: null,
    view: 'dayGridMonth'
};
let workOrders = [];
let groups = [];

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    initializeCalendar();
    loadWorkOrders();
    loadGroups();
    setupEventListeners();
});

function initializeCalendar() {
    calendarEl = document.getElementById('calendar-ot');
    if (!calendarEl) return;
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        editable: false,
        selectable: true,
        dayMaxEvents: true,
        weekends: true,
        eventClick: handleEventClick,
        events: loadAssignments,
        eventContent: renderEventContent,
        height: 'auto',
        slotMinTime: '06:00:00',
        slotMaxTime: '24:00:00',
        allDaySlot: false
    });
    
    calendar.render();
}

function setupEventListeners() {
    // Filtros
    const filterWorkOrder = document.getElementById('filter-work-order');
    const filterGroup = document.getElementById('filter-group');
    const filterView = document.getElementById('filter-view');
    const btnClearFilters = document.getElementById('btn-clear-filters');
    
    if (filterWorkOrder) {
        filterWorkOrder.addEventListener('change', (e) => {
            currentFilters.work_order_id = e.target.value || null;
            calendar.refetchEvents();
        });
    }
    
    if (filterGroup) {
        filterGroup.addEventListener('change', (e) => {
            currentFilters.group_id = e.target.value || null;
            calendar.refetchEvents();
        });
    }
    
    if (filterView) {
        filterView.addEventListener('change', (e) => {
            currentFilters.view = e.target.value;
            calendar.changeView(e.target.value);
        });
    }
    
    if (btnClearFilters) {
        btnClearFilters.addEventListener('click', clearFilters);
    }
}

async function loadWorkOrders() {
    try {
        const response = await API.get('/work-orders', { page_size: 100 });
        
        if (response && response.success) {
            workOrders = response.data || [];
            const select = document.getElementById('filter-work-order');
            if (!select) return;
            
            select.innerHTML = '<option value="">Todas las órdenes</option>';
            workOrders.forEach(wo => {
                const option = document.createElement('option');
                option.value = wo.id;
                option.textContent = `${wo.code || `OT-${String(wo.id).padStart(6, '0')}`} - ${escapeHtml(wo.title || 'Sin título')}`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading work orders:', error);
        if (typeof Toast !== 'undefined') {
            Toast.error('Error al cargar órdenes de trabajo');
        }
    }
}

async function loadGroups() {
    try {
        const response = await API.get('/work-groups', { page_size: 100 });
        
        if (response && response.success) {
            groups = response.data || [];
            const select = document.getElementById('filter-group');
            if (!select) return;
            
            select.innerHTML = '<option value="">Todos los grupos</option>';
            groups.forEach(group => {
                const option = document.createElement('option');
                option.value = group.id;
                option.textContent = escapeHtml(group.name);
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading groups:', error);
        if (typeof Toast !== 'undefined') {
            Toast.error('Error al cargar grupos');
        }
    }
}

async function loadAssignments(info, successCallback, failureCallback) {
    try {
        const params = {};
        
        if (currentFilters.work_order_id) {
            params.work_order_id = currentFilters.work_order_id;
        }
        if (currentFilters.group_id) {
            params.group_id = currentFilters.group_id;
        }
        
        const response = await API.get('/work-order-assignments', params);
        
        if (response && response.success) {
            const events = [];
            
            // Agrupar asignaciones por OT y crear eventos
            const assignmentsByWO = {};
            (response.data || []).forEach(assignment => {
                if (!assignmentsByWO[assignment.work_order_id]) {
                    assignmentsByWO[assignment.work_order_id] = [];
                }
                assignmentsByWO[assignment.work_order_id].push(assignment);
            });
            
            // Crear eventos para cada asignación
            (response.data || []).forEach(assignment => {
                if (assignment.start_at && assignment.end_at) {
                    events.push({
                        id: `assignment-${assignment.id}`,
                        title: assignment.work_order_code || `OT-${String(assignment.work_order_id).padStart(6, '0')}`,
                        start: assignment.start_at,
                        end: assignment.end_at,
                        backgroundColor: getWorkOrderColor(assignment.work_order_id),
                        borderColor: getWorkOrderColor(assignment.work_order_id),
                        extendedProps: {
                            assignment: assignment,
                            type: 'assignment'
                        }
                    });
                } else {
                    // Si no tiene fechas, usar las fechas de la OT
                    const wo = workOrders.find(w => w.id === assignment.work_order_id);
                    if (wo && wo.start_planned && wo.end_planned) {
                        events.push({
                            id: `wo-${assignment.work_order_id}`,
                            title: wo.code || `OT-${String(wo.id).padStart(6, '0')}`,
                            start: wo.start_planned,
                            end: wo.end_planned,
                            backgroundColor: getWorkOrderColor(assignment.work_order_id),
                            borderColor: getWorkOrderColor(assignment.work_order_id),
                            extendedProps: {
                                work_order: wo,
                                assignments: [assignment],
                                type: 'work_order'
                            }
                        });
                    }
                }
            });
            
            successCallback(events);
        } else {
            failureCallback();
        }
    } catch (error) {
        console.error('Error loading assignments:', error);
        failureCallback();
    }
}

function getWorkOrderColor(workOrderId) {
    // Generar color consistente basado en el ID
    const colors = [
        '#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0',
        '#6610f2', '#6f42c1', '#d63384', '#fd7e14', '#20c997'
    ];
    return colors[workOrderId % colors.length];
}

function renderEventContent(eventInfo) {
    const assignment = eventInfo.event.extendedProps.assignment;
    const workOrder = eventInfo.event.extendedProps.work_order;
    const start = new Date(eventInfo.event.start);
    const end = new Date(eventInfo.event.end);
    
    let title = eventInfo.event.title;
    let subtitle = '';
    
    if (assignment) {
        if (assignment.group_name) {
            subtitle = escapeHtml(assignment.group_name);
        } else if (assignment.employee_name) {
            subtitle = escapeHtml(assignment.employee_name);
        }
    } else if (workOrder) {
        subtitle = escapeHtml(workOrder.title || '');
    }
    
    return {
        html: `
            <div class="fc-event-title">
                <strong>${escapeHtml(title)}</strong>
            </div>
            ${subtitle ? `<div class="fc-event-subtitle">${subtitle}</div>` : ''}
            <div class="fc-event-time">
                ${formatTime(start)} - ${formatTime(end)}
            </div>
        `
    };
}

function formatTime(date) {
    return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
}

function handleEventClick(info) {
    const assignment = info.event.extendedProps.assignment;
    const workOrder = info.event.extendedProps.work_order;
    
    if (assignment) {
        showAssignmentDetails(assignment);
    } else if (workOrder) {
        showWorkOrderDetails(workOrder, info.event.extendedProps.assignments || []);
    }
}

function showAssignmentDetails(assignment) {
    const modal = document.getElementById('modal-assignment');
    const body = document.getElementById('assignment-details');
    
    if (!modal || !body) return;
    
    body.innerHTML = `
        <div class="row">
            <div class="col-12 mb-3">
                <h6>Orden de Trabajo</h6>
                <p class="mb-0">
                    <strong>${escapeHtml(assignment.work_order_code || `OT-${String(assignment.work_order_id).padStart(6, '0')}`)}</strong><br>
                    ${escapeHtml(assignment.work_order_title || 'Sin título')}
                </p>
            </div>
            ${assignment.group_name ? `
                <div class="col-12 mb-3">
                    <h6>Grupo</h6>
                    <p class="mb-0">${escapeHtml(assignment.group_name)}</p>
                </div>
            ` : ''}
            ${assignment.employee_name ? `
                <div class="col-12 mb-3">
                    <h6>${assignment.employee_type === 'own_employee' ? 'Empleado' : 'Trabajador'}</h6>
                    <p class="mb-0">${escapeHtml(assignment.employee_name)}</p>
                    ${assignment.agency_name ? `<small class="text-muted">${escapeHtml(assignment.agency_name)}</small>` : ''}
                </div>
            ` : ''}
            ${assignment.start_at ? `
                <div class="col-12 mb-3">
                    <h6>Inicio</h6>
                    <p class="mb-0">${formatDateTime(assignment.start_at)}</p>
                </div>
            ` : ''}
            ${assignment.end_at ? `
                <div class="col-12 mb-3">
                    <h6>Fin</h6>
                    <p class="mb-0">${formatDateTime(assignment.end_at)}</p>
                </div>
            ` : ''}
        </div>
    `;
    
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
}

function showWorkOrderDetails(workOrder, assignments) {
    const modal = document.getElementById('modal-assignment');
    const body = document.getElementById('assignment-details');
    
    if (!modal || !body) return;
    
    body.innerHTML = `
        <div class="row">
            <div class="col-12 mb-3">
                <h6>Orden de Trabajo</h6>
                <p class="mb-0">
                    <strong>${escapeHtml(workOrder.code || `OT-${String(workOrder.id).padStart(6, '0')}`)}</strong><br>
                    ${escapeHtml(workOrder.title || 'Sin título')}
                </p>
            </div>
            ${workOrder.start_planned ? `
                <div class="col-12 mb-3">
                    <h6>Inicio Planificado</h6>
                    <p class="mb-0">${formatDate(workOrder.start_planned)}</p>
                </div>
            ` : ''}
            ${workOrder.end_planned ? `
                <div class="col-12 mb-3">
                    <h6>Fin Planificado</h6>
                    <p class="mb-0">${formatDate(workOrder.end_planned)}</p>
                </div>
            ` : ''}
            ${assignments.length > 0 ? `
                <div class="col-12 mb-3">
                    <h6>Asignaciones (${assignments.length})</h6>
                    <ul class="list-group">
                        ${assignments.map(a => `
                            <li class="list-group-item">
                                ${escapeHtml(a.group_name || a.employee_name || 'Sin asignar')}
                                ${a.start_at ? `<br><small class="text-muted">${formatDateTime(a.start_at)}</small>` : ''}
                            </li>
                        `).join('')}
                    </ul>
                </div>
            ` : ''}
        </div>
    `;
    
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
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

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function clearFilters() {
    const filterWorkOrder = document.getElementById('filter-work-order');
    const filterGroup = document.getElementById('filter-group');
    
    if (filterWorkOrder) filterWorkOrder.value = '';
    if (filterGroup) filterGroup.value = '';
    
    currentFilters.work_order_id = null;
    currentFilters.group_id = null;
    calendar.refetchEvents();
}
