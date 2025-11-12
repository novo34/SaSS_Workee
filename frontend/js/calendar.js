/**
 * JavaScript para Calendario con FullCalendar (REFACTORIZADO)
 * Incluye drag & drop, creación/edición de turnos, y preasignación
 * 
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications
 * - Modal component (para confirmaciones si es necesario)
 */

// Asegurar que los componentes estén cargados
if (typeof API === 'undefined' || typeof Toast === 'undefined') {
    console.error('Componentes no cargados. Asegúrate de incluir components-loader.js');
}

// BASE_URL ya está definido en layout.js, solo usarlo
const API_BASE = `${window.BASE_URL || ''}/api/v1`;

let calendar;
let calendarEl;
let currentFilters = {
    group_id: null,
    status: null,
    view: 'dayGridMonth'
};
let groups = [];
let ownEmployees = [];
let agencyWorkers = [];

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    initializeCalendar();
    loadGroups();
    loadEmployees();
    setupEventListeners();
});

function initializeCalendar() {
    calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        editable: true,
        droppable: false,
        selectable: true,
        selectMirror: true,
        dayMaxEvents: true,
        weekends: true,
        select: handleDateSelect,
        eventClick: handleEventClick,
        eventDrop: handleEventDrop,
        eventResize: handleEventResize,
        events: loadShifts,
        eventContent: renderEventContent,
        height: 'auto',
        slotMinTime: '06:00:00',
        slotMaxTime: '24:00:00',
        allDaySlot: false
    });
    
    calendar.render();
}

function setupEventListeners() {
    // Botón nuevo turno
    const btnNewShift = document.getElementById('btn-new-shift');
    if (btnNewShift) {
        btnNewShift.addEventListener('click', () => {
            openShiftModal();
        });
    }
    
    // Filtros
    const filterGroup = document.getElementById('filter-group');
    const filterStatus = document.getElementById('filter-status');
    const filterView = document.getElementById('filter-view');
    const btnClearFilters = document.getElementById('btn-clear-filters');
    
    if (filterGroup) {
        filterGroup.addEventListener('change', (e) => {
            currentFilters.group_id = e.target.value || null;
            calendar.refetchEvents();
        });
    }
    
    if (filterStatus) {
        filterStatus.addEventListener('change', (e) => {
            currentFilters.status = e.target.value || null;
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
    
    // Formulario de turno
    const btnSaveShift = document.getElementById('btn-save-shift');
    const shiftEmployeeType = document.getElementById('shift-employee-type');
    
    if (btnSaveShift) btnSaveShift.addEventListener('click', saveShift);
    if (shiftEmployeeType) shiftEmployeeType.addEventListener('change', handleEmployeeTypeChange);
    
    // Modal events
    const modal = document.getElementById('modal-shift');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', () => {
            const form = document.getElementById('form-shift');
            const shiftId = document.getElementById('shift-id');
            const shiftConflicts = document.getElementById('shift-conflicts');
            const shiftWarnings = document.getElementById('shift-warnings');
            
            if (form) form.reset();
            if (shiftId) shiftId.value = '';
            if (shiftConflicts) shiftConflicts.classList.add('d-none');
            if (shiftWarnings) shiftWarnings.classList.add('d-none');
        });
        
        // Recargar empleados cada vez que se abre el modal (por si se agregaron nuevos)
        modal.addEventListener('show.bs.modal', () => {
            loadEmployees();
        });
    }
}

async function loadShifts(info, successCallback, failureCallback) {
    try {
        const params = {
            from: info.startStr,
            to: info.endStr
        };
        
        if (currentFilters.group_id) {
            params.group_id = currentFilters.group_id;
        }
        if (currentFilters.status) {
            params.status = currentFilters.status;
        }
        
        const response = await API.get('/shifts', params);
        
        if (response && response.success) {
            const events = (response.data || []).map(shift => ({
                id: shift.id,
                title: shift.employee_name || 'Sin asignar',
                start: shift.start_at,
                end: shift.end_at,
                backgroundColor: getStatusColor(shift.status),
                borderColor: getStatusColor(shift.status),
                extendedProps: {
                    shift: shift
                }
            }));
            
            successCallback(events);
        } else {
            failureCallback();
        }
    } catch (error) {
        console.error('Error loading shifts:', error);
        failureCallback();
    }
}

function getStatusColor(status) {
    const colors = {
        'planned': '#6c757d',
        'assigned': '#0d6efd',
        'in_progress': '#ffc107',
        'done': '#198754',
        'canceled': '#dc3545'
    };
    return colors[status] || '#6c757d';
}

function renderEventContent(eventInfo) {
    const shift = eventInfo.event.extendedProps.shift;
    const start = new Date(eventInfo.event.start);
    const end = new Date(eventInfo.event.end);
    
    return {
        html: `
            <div class="fc-event-title">
                <strong>${escapeHtml(eventInfo.event.title)}</strong>
            </div>
            <div class="fc-event-time">
                ${formatTime(start)} - ${formatTime(end)}
            </div>
            ${shift && shift.group_name ? `<div class="fc-event-group">${escapeHtml(shift.group_name)}</div>` : ''}
        `
    };
}

function formatTime(date) {
    return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
}

function handleDateSelect(selectInfo) {
    // Al seleccionar un rango de fechas, abrir modal para crear turno
    const start = selectInfo.start;
    const end = selectInfo.end || new Date(start.getTime() + 8 * 60 * 60 * 1000); // +8 horas por defecto
    
    openShiftModal({
        start: start,
        end: end
    });
    
    calendar.unselect();
}

function handleEventClick(clickInfo) {
    // Al hacer clic en un evento, abrir modal para editar
    const shift = clickInfo.event.extendedProps.shift;
    openShiftModal(shift);
}

async function handleEventDrop(dropInfo) {
    // Al arrastrar un evento, actualizar fechas
    const shift = dropInfo.event.extendedProps.shift;
    const newStart = dropInfo.event.start;
    const newEnd = dropInfo.event.end || new Date(newStart.getTime() + 8 * 60 * 60 * 1000);
    
    await updateShift(shift.id, {
        start_at: newStart.toISOString().slice(0, 19).replace('T', ' '),
        end_at: newEnd.toISOString().slice(0, 19).replace('T', ' ')
    });
}

async function handleEventResize(resizeInfo) {
    // Al redimensionar un evento, actualizar duración
    const shift = resizeInfo.event.extendedProps.shift;
    const newStart = resizeInfo.event.start;
    const newEnd = resizeInfo.event.end;
    
    await updateShift(shift.id, {
        start_at: newStart.toISOString().slice(0, 19).replace('T', ' '),
        end_at: newEnd.toISOString().slice(0, 19).replace('T', ' ')
    });
}

function openShiftModal(shiftData = null) {
    const modalEl = document.getElementById('modal-shift');
    if (!modalEl) return;
    
    const bootstrapModal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('form-shift');
    
    // Siempre recargar empleados antes de abrir el modal (por si se agregaron nuevos)
    // El evento 'show.bs.modal' también recarga, pero esto asegura que estén listos antes de mostrar
    loadEmployees().then(() => {
        populateModal(shiftData, bootstrapModal, form);
    }).catch(error => {
        console.error('Error cargando empleados antes de abrir modal:', error);
        // Abrir modal de todas formas, aunque no haya empleados
        populateModal(shiftData, bootstrapModal, form);
    });
}

function populateModal(shiftData, modal, form) {
    if (shiftData && shiftData.id) {
        // Editar turno existente
        const modalTitle = document.getElementById('modal-shift-title');
        const shiftId = document.getElementById('shift-id');
        const shiftStart = document.getElementById('shift-start');
        const shiftEnd = document.getElementById('shift-end');
        const shiftGroup = document.getElementById('shift-group');
        const shiftEmployeeType = document.getElementById('shift-employee-type');
        const shiftOwnEmployee = document.getElementById('shift-own-employee');
        const shiftAgencyWorker = document.getElementById('shift-agency-worker');
        const shiftLocation = document.getElementById('shift-location');
        const shiftStatus = document.getElementById('shift-status');
        
        if (modalTitle) modalTitle.textContent = 'Editar Turno';
        if (shiftId) shiftId.value = shiftData.id || '';
        if (shiftStart) shiftStart.value = formatDateTimeLocal(shiftData.start_at || shiftData.start);
        if (shiftEnd) shiftEnd.value = formatDateTimeLocal(shiftData.end_at || shiftData.end);
        if (shiftGroup) shiftGroup.value = shiftData.group_id || '';
        if (shiftEmployeeType) shiftEmployeeType.value = shiftData.employee_type || 'own_employee';
        if (shiftOwnEmployee) shiftOwnEmployee.value = shiftData.own_employee_id || '';
        if (shiftAgencyWorker) shiftAgencyWorker.value = shiftData.agency_worker_id || '';
        if (shiftLocation) shiftLocation.value = shiftData.location || '';
        if (shiftStatus) shiftStatus.value = shiftData.status || 'planned';
        
        handleEmployeeTypeChange();
    } else {
        // Nuevo turno
        const modalTitle = document.getElementById('modal-shift-title');
        const shiftStart = document.getElementById('shift-start');
        const shiftEnd = document.getElementById('shift-end');
        const shiftEmployeeType = document.getElementById('shift-employee-type');
        
        if (modalTitle) modalTitle.textContent = 'Nuevo Turno';
        if (form) form.reset();
        
        if (shiftData && shiftData.start) {
            if (shiftStart) shiftStart.value = formatDateTimeLocal(shiftData.start);
            if (shiftEnd) shiftEnd.value = formatDateTimeLocal(shiftData.end);
        }
        
        // Asegurar que el tipo de empleado esté en 'own_employee' por defecto
        if (shiftEmployeeType) {
            shiftEmployeeType.value = 'own_employee';
            handleEmployeeTypeChange();
        }
    }
    
    modal.show();
}

function formatDateTimeLocal(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function handleEmployeeTypeChange() {
    const type = document.getElementById('shift-employee-type')?.value;
    const ownContainer = document.getElementById('container-own-employee');
    const agencyContainer = document.getElementById('container-agency-worker');
    
    if (!ownContainer || !agencyContainer) return;
    
    if (type === 'own_employee') {
        ownContainer.classList.remove('d-none');
        agencyContainer.classList.add('d-none');
        const ownSelect = document.getElementById('shift-own-employee');
        const agencySelect = document.getElementById('shift-agency-worker');
        if (ownSelect) ownSelect.required = true;
        if (agencySelect) agencySelect.required = false;
    } else {
        ownContainer.classList.add('d-none');
        agencyContainer.classList.remove('d-none');
        const ownSelect = document.getElementById('shift-own-employee');
        const agencySelect = document.getElementById('shift-agency-worker');
        if (ownSelect) ownSelect.required = false;
        if (agencySelect) agencySelect.required = true;
    }
}

async function saveShift() {
    const form = document.getElementById('form-shift');
    if (!form || !form.checkValidity()) {
        if (form) form.reportValidity();
        return;
    }
    
    const shiftId = document.getElementById('shift-id')?.value;
    const employeeType = document.getElementById('shift-employee-type')?.value;
    
    const shiftData = {
        start_at: document.getElementById('shift-start')?.value.replace('T', ' ') + ':00',
        end_at: document.getElementById('shift-end')?.value.replace('T', ' ') + ':00',
        group_id: document.getElementById('shift-group')?.value || null,
        employee_type: employeeType,
        own_employee_id: employeeType === 'own_employee' ? document.getElementById('shift-own-employee')?.value : null,
        agency_worker_id: employeeType === 'agency_worker' ? document.getElementById('shift-agency-worker')?.value : null,
        location: document.getElementById('shift-location')?.value || null,
        status: document.getElementById('shift-status')?.value
    };
    
    // Validar antes de guardar
    const validation = await validateShift(shiftData, shiftId);
    
    if (!validation.valid) {
        showConflicts(validation.conflicts, validation.warnings);
        return;
    }
    
    try {
        let response;
        if (shiftId) {
            response = await API.put(`/shifts/${shiftId}`, shiftData);
        } else {
            response = await API.post('/shifts', shiftData);
        }
        
        if (response && response.success) {
            const modalEl = document.getElementById('modal-shift');
            if (modalEl) {
                const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
                if (bootstrapModal) bootstrapModal.hide();
            }
            calendar.refetchEvents();
            Toast.success('Turno guardado correctamente');
        } else {
            Toast.error(response?.message || 'Error al guardar turno');
        }
    } catch (error) {
        console.error('Error saving shift:', error);
        Toast.error('Error al guardar turno');
    }
}

async function validateShift(shiftData, shiftId = null) {
    try {
        const url = shiftId 
            ? `/shifts/${shiftId}/validate`
            : '/shifts/validate';
        
        const response = await API.post(url, shiftData);
        return response || { valid: true, conflicts: [], warnings: [] };
    } catch (error) {
        console.error('Error validating shift:', error);
        return { valid: true, conflicts: [], warnings: [] };
    }
}

function showConflicts(conflicts, warnings) {
    const conflictsEl = document.getElementById('shift-conflicts');
    const warningsEl = document.getElementById('shift-warnings');
    const conflictsList = document.getElementById('conflicts-list');
    const warningsList = document.getElementById('warnings-list');
    
    if (!conflictsList || !warningsList) return;
    
    conflictsList.innerHTML = '';
    warningsList.innerHTML = '';
    
    if (conflicts && conflicts.length > 0) {
        conflicts.forEach(conflict => {
            const li = document.createElement('li');
            li.textContent = conflict.message || conflict.type;
            conflictsList.appendChild(li);
        });
        if (conflictsEl) conflictsEl.classList.remove('d-none');
    } else {
        if (conflictsEl) conflictsEl.classList.add('d-none');
    }
    
    if (warnings && warnings.length > 0) {
        warnings.forEach(warning => {
            const li = document.createElement('li');
            li.textContent = warning;
            warningsList.appendChild(li);
        });
        if (warningsEl) warningsEl.classList.remove('d-none');
    } else {
        if (warningsEl) warningsEl.classList.add('d-none');
    }
}

async function updateShift(shiftId, updates) {
    try {
        const response = await API.put(`/shifts/${shiftId}`, updates);
        
        if (response && response.success) {
            calendar.refetchEvents();
            Toast.success('Turno actualizado correctamente');
        } else {
            // Revertir cambio en el calendario
            calendar.refetchEvents();
            Toast.error(response?.message || 'Error al actualizar turno');
        }
    } catch (error) {
        console.error('Error updating shift:', error);
        calendar.refetchEvents();
        Toast.error('Error al actualizar turno');
    }
}

async function loadGroups() {
    try {
        // TODO: Implementar API de grupos cuando esté disponible
        // Por ahora, usar datos mock o vacío
        const select = document.getElementById('filter-group');
        const shiftSelect = document.getElementById('shift-group');
        
        // Limpiar opciones existentes (excepto la primera)
        if (select) {
            while (select.options.length > 1) {
                select.remove(1);
            }
        }
        if (shiftSelect) {
            while (shiftSelect.options.length > 1) {
                shiftSelect.remove(1);
            }
        }
        
        // TODO: Cargar grupos reales desde API
        groups = [];
    } catch (error) {
        console.error('Error loading groups:', error);
    }
}

async function loadEmployees() {
    try {
        const token = localStorage.getItem('access_token');
        const tenantId = localStorage.getItem('tenant_id');
        
        if (!token || !tenantId) {
            console.warn('No hay token o tenant_id, no se pueden cargar empleados');
            return;
        }
        
        // Cargar empleados propios usando API Client
        const ownEmployeesResponse = await API.get('/own-employees', { is_active: 1, page_size: 100 });
        
        if (ownEmployeesResponse && ownEmployeesResponse.success && ownEmployeesResponse.data) {
            ownEmployees = ownEmployeesResponse.data;
            populateEmployeeSelect('shift-own-employee', ownEmployees);
        }
        
        // TODO: Cargar trabajadores temporeros cuando esté disponible la API
        // Por ahora, dejar vacío
        const agencySelect = document.getElementById('shift-agency-worker');
        if (agencySelect) {
            while (agencySelect.options.length > 1) {
                agencySelect.remove(1);
            }
        }
        agencyWorkers = [];
        
    } catch (error) {
        console.error('Error loading employees:', error);
    }
}

function populateEmployeeSelect(selectId, employees) {
    const select = document.getElementById(selectId);
    if (!select) {
        console.error(`Select con id ${selectId} no encontrado`);
        return;
    }
    
    // Limpiar opciones existentes (excepto la primera)
    while (select.options.length > 1) {
        select.remove(1);
    }
    
    // Agregar empleados
    employees.forEach(employee => {
        const option = document.createElement('option');
        option.value = employee.id;
        option.textContent = employee.full_name || `${employee.first_name} ${employee.last_name}`;
        select.appendChild(option);
    });
    
    console.log(`Cargados ${employees.length} empleados en ${selectId}`);
}

function clearFilters() {
    const filterGroup = document.getElementById('filter-group');
    const filterStatus = document.getElementById('filter-status');
    
    if (filterGroup) filterGroup.value = '';
    if (filterStatus) filterStatus.value = '';
    
    currentFilters.group_id = null;
    currentFilters.status = null;
    calendar.refetchEvents();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
