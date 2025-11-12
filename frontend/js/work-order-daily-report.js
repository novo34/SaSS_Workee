/**
 * JavaScript para Parte Diario de Orden de Trabajo (REFACTORIZADO)
 * Permite crear y gestionar partes diarios de OT con tabla de miembros
 * 
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications
 * - Modal component (para confirmaciones)
 */

// Asegurar que los componentes estén cargados
if (typeof API === 'undefined' || typeof Toast === 'undefined' || typeof Modal === 'undefined') {
    console.error('Componentes no cargados. Asegúrate de incluir components-loader.js');
}

let currentReportId = null;
let workOrders = [];
let groups = [];
let ownEmployees = [];
let agencyWorkers = [];
let reportLines = [];

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    loadWorkOrders();
    loadEmployees();
    setupEventListeners();
    
    // Si hay ID en la URL, cargar el parte
    const urlParams = new URLSearchParams(window.location.search);
    const reportId = urlParams.get('id');
    const workOrderId = urlParams.get('work_order_id');
    const date = urlParams.get('date');
    
    if (reportId) {
        loadReport(reportId);
    } else if (workOrderId && date) {
        const reportWorkOrderId = document.getElementById('report-work-order-id');
        const reportDate = document.getElementById('report-date');
        if (reportWorkOrderId) reportWorkOrderId.value = workOrderId;
        if (reportDate) reportDate.value = date;
        loadWorkOrderAssignments(workOrderId);
    }
});

function setupEventListeners() {
    // Cambio de orden de trabajo
    const reportWorkOrderId = document.getElementById('report-work-order-id');
    if (reportWorkOrderId) {
        reportWorkOrderId.addEventListener('change', (e) => {
            const workOrderId = e.target.value;
            if (workOrderId) {
                loadWorkOrderAssignments(workOrderId);
                loadGroupsForWorkOrder(workOrderId);
            } else {
                clearMembersTable();
            }
        });
    }
    
    // Formulario
    const formReport = document.getElementById('form-report');
    if (formReport) {
        formReport.addEventListener('submit', (e) => {
            e.preventDefault();
            submitReport();
        });
    }
    
    // Botones
    const btnSaveDraft = document.getElementById('btn-save-draft');
    const btnCancel = document.getElementById('btn-cancel');
    const btnAddMember = document.getElementById('btn-add-member');
    const btnSaveMember = document.getElementById('btn-save-member');
    const memberType = document.getElementById('member-type');
    
    if (btnSaveDraft) {
        btnSaveDraft.addEventListener('click', () => {
            saveReport(true);
        });
    }
    
    if (btnCancel) {
        btnCancel.addEventListener('click', async () => {
            const confirmed = await Modal.confirm({
                title: 'Cancelar',
                message: '¿Deseas cancelar? Los cambios no guardados se perderán.',
                confirmLabel: 'Cancelar',
                cancelLabel: 'Continuar editando'
            });
            
            if (confirmed) {
                window.location.href = '/work-orders';
            }
        });
    }
    
    if (btnAddMember) {
        btnAddMember.addEventListener('click', () => {
            openMemberModal();
        });
    }
    
    if (btnSaveMember) {
        btnSaveMember.addEventListener('click', saveMember);
    }
    
    if (memberType) {
        memberType.addEventListener('change', handleMemberTypeChange);
    }
    
    // Modal events
    const modal = document.getElementById('modal-member');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', () => {
            const formMember = document.getElementById('form-member');
            const memberLineId = document.getElementById('member-line-id');
            if (formMember) formMember.reset();
            if (memberLineId) memberLineId.value = '';
        });
    }
}

async function loadWorkOrders() {
    try {
        const response = await API.get('/work-orders', { page_size: 100 });
        
        if (response && response.success) {
            workOrders = response.data || [];
            const select = document.getElementById('report-work-order-id');
            if (select) {
                workOrders.forEach(wo => {
                    const option = document.createElement('option');
                    option.value = wo.id;
                    option.textContent = `${wo.code || ''} - ${escapeHtml(wo.title)}`.trim();
                    select.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading work orders:', error);
        Toast.error('Error al cargar órdenes de trabajo');
    }
}

async function loadGroupsForWorkOrder(workOrderId) {
    try {
        const response = await API.get('/work-order-assignments', { work_order_id: workOrderId });
        
        if (response && response.success) {
            // Extraer grupos únicos de las asignaciones
            const groupsMap = new Map();
            (response.data || []).forEach(assignment => {
                if (assignment.group_id && assignment.group_name) {
                    if (!groupsMap.has(assignment.group_id)) {
                        groupsMap.set(assignment.group_id, {
                            id: assignment.group_id,
                            name: assignment.group_name
                        });
                    }
                }
            });
            groups = Array.from(groupsMap.values());
            
            const select = document.getElementById('report-group-id');
            if (select) {
                select.innerHTML = '<option value="">Sin grupo específico</option>';
                groups.forEach(group => {
                    const option = document.createElement('option');
                    option.value = group.id;
                    option.textContent = escapeHtml(group.name);
                    select.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading groups for work order:', error);
    }
}

async function loadWorkOrderAssignments(workOrderId) {
    try {
        const response = await API.get('/work-order-assignments', { work_order_id: workOrderId });
        
        if (response && response.success) {
            // Pre-llenar tabla con miembros de las asignaciones
            const tbody = document.getElementById('members-tbody');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            if (!response.data || response.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted"><em>La orden de trabajo no tiene asignaciones</em></td></tr>';
            } else {
                const membersMap = new Map();
                (response.data || []).forEach(assignment => {
                    const key = assignment.employee_type === 'own_employee' 
                        ? `emp-${assignment.own_employee_id}`
                        : `worker-${assignment.agency_worker_id}`;
                    
                    if (!membersMap.has(key)) {
                        membersMap.set(key, {
                            id: null,
                            member_type: assignment.employee_type,
                            own_employee_id: assignment.own_employee_id,
                            agency_worker_id: assignment.agency_worker_id,
                            attended: true,
                            clock_in: null,
                            clock_out: null,
                            breaks_minutes: 0,
                            notes: null,
                            member_name: assignment.employee_name || 'Sin nombre'
                        });
                    }
                });
                
                Array.from(membersMap.values()).forEach(member => {
                    addMemberToTable(member);
                });
            }
        }
    } catch (error) {
        console.error('Error loading work order assignments:', error);
        Toast.error('Error al cargar asignaciones de la OT');
    }
}

async function loadEmployees() {
    try {
        const response = await API.get('/own-employees', { page_size: 100 });
        
        if (response && response.success) {
            ownEmployees = response.data || [];
            const select = document.getElementById('member-employee-id');
            if (select) {
                select.innerHTML = '<option value="">Seleccionar empleado</option>';
                ownEmployees.forEach(emp => {
                    const option = document.createElement('option');
                    option.value = emp.id;
                    option.textContent = `${escapeHtml(emp.first_name)} ${escapeHtml(emp.last_name)} (${escapeHtml(emp.employee_code || '')})`;
                    select.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading employees:', error);
        Toast.error('Error al cargar empleados');
    }
    
    // Cargar trabajadores de agencias vinculadas
    await loadAgencyWorkers();
}

async function loadAgencyWorkers() {
    try {
        // Obtener trabajadores desde asignaciones de OT
        const reportWorkOrderId = document.getElementById('report-work-order-id');
        const workOrderId = reportWorkOrderId?.value;
        if (!workOrderId) {
            return;
        }
        
        const response = await API.get('/work-order-assignments', { work_order_id: workOrderId });
        
        if (response && response.success) {
            // Extraer trabajadores únicos de las asignaciones
            const workersMap = new Map();
            (response.data || []).forEach(assignment => {
                if (assignment.employee_type === 'agency_worker' && assignment.agency_worker_id && assignment.employee_name) {
                    if (!workersMap.has(assignment.agency_worker_id)) {
                        workersMap.set(assignment.agency_worker_id, {
                            id: assignment.agency_worker_id,
                            first_name: assignment.employee_name.split(' ')[0] || '',
                            last_name: assignment.employee_name.split(' ').slice(1).join(' ') || '',
                            agency_name: assignment.agency_name
                        });
                    }
                }
            });
            agencyWorkers = Array.from(workersMap.values());
            
            const select = document.getElementById('member-worker-id');
            if (select) {
                select.innerHTML = '<option value="">Seleccionar trabajador</option>';
                agencyWorkers.forEach(worker => {
                    const option = document.createElement('option');
                    option.value = worker.id;
                    option.textContent = `${escapeHtml(worker.first_name)} ${escapeHtml(worker.last_name)}${worker.agency_name ? ` (${escapeHtml(worker.agency_name)})` : ''}`;
                    select.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading agency workers:', error);
    }
}

async function loadReport(reportId) {
    try {
        const response = await API.get(`/work-order-daily-reports/${reportId}`);
        
        if (response && response.success) {
            const report = response.data;
            currentReportId = report.id;
            const reportIdInput = document.getElementById('report-id');
            const reportWorkOrderId = document.getElementById('report-work-order-id');
            const reportDate = document.getElementById('report-date');
            const reportGroupId = document.getElementById('report-group-id');
            const reportProgressPct = document.getElementById('report-progress-pct');
            const reportNotes = document.getElementById('report-notes');
            
            if (reportIdInput) reportIdInput.value = report.id;
            if (reportWorkOrderId) reportWorkOrderId.value = report.work_order_id;
            if (reportDate) reportDate.value = report.date;
            if (reportGroupId) reportGroupId.value = report.group_id || '';
            if (reportProgressPct) reportProgressPct.value = report.progress_pct || '';
            if (reportNotes) reportNotes.value = report.notes || '';
            
            // Cargar líneas
            reportLines = report.lines || [];
            renderMembersTable();
        }
    } catch (error) {
        console.error('Error loading report:', error);
        Toast.error('Error al cargar el parte');
    }
}

function renderMembersTable() {
    const tbody = document.getElementById('members-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (reportLines.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted"><em>No hay miembros en el parte</em></td></tr>';
        return;
    }
    
    reportLines.forEach(line => {
        addMemberToTable(line);
    });
}

function addMemberToTable(line) {
    const tbody = document.getElementById('members-tbody');
    if (!tbody) return;
    
    // Eliminar mensaje de "no hay miembros" si existe
    if (tbody.querySelector('td[colspan]')) {
        tbody.innerHTML = '';
    }
    
    const row = document.createElement('tr');
    row.dataset.lineId = line.id || `temp-${Date.now()}`;
    
    const clockIn = line.clock_in ? formatDateTimeLocal(line.clock_in) : '';
    const clockOut = line.clock_out ? formatDateTimeLocal(line.clock_out) : '';
    
    row.innerHTML = `
        <td>${escapeHtml(line.member_name || 'Sin nombre')}</td>
        <td>
            <span class="badge ${line.attended ? 'bg-success' : 'bg-danger'}">
                ${line.attended ? 'Sí' : 'No'}
            </span>
        </td>
        <td>${escapeHtml(clockIn || '-')}</td>
        <td>${escapeHtml(clockOut || '-')}</td>
        <td>${line.breaks_minutes || 0}</td>
        <td>${escapeHtml(line.notes || '-')}</td>
        <td>
            <button class="btn-modern btn-modern--sm btn-modern--primary" onclick="editMember('${row.dataset.lineId}')" aria-label="Editar miembro">
                <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
            </button>
            <button class="btn-modern btn-modern--sm btn-modern--danger" onclick="deleteMember('${row.dataset.lineId}')" aria-label="Eliminar miembro">
                <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
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

function openMemberModal(lineId = null) {
    const modalEl = document.getElementById('modal-member');
    if (!modalEl) return;
    
    const bootstrapModal = new bootstrap.Modal(modalEl);
    
    if (lineId) {
        const line = reportLines.find(l => (l.id && l.id.toString() === lineId) || (l.tempId === lineId));
        if (line) {
            const memberLineId = document.getElementById('member-line-id');
            const memberType = document.getElementById('member-type');
            const memberEmployeeId = document.getElementById('member-employee-id');
            const memberWorkerId = document.getElementById('member-worker-id');
            const memberAttended = document.getElementById('member-attended');
            const memberClockIn = document.getElementById('member-clock-in');
            const memberClockOut = document.getElementById('member-clock-out');
            const memberBreaks = document.getElementById('member-breaks');
            const memberNotes = document.getElementById('member-notes');
            
            if (memberLineId) memberLineId.value = line.id || line.tempId || '';
            if (memberType) {
                memberType.value = line.member_type;
                handleMemberTypeChange();
            }
            if (memberEmployeeId) memberEmployeeId.value = line.own_employee_id || '';
            if (memberWorkerId) memberWorkerId.value = line.agency_worker_id || '';
            if (memberAttended) memberAttended.checked = line.attended !== false;
            if (memberClockIn) memberClockIn.value = line.clock_in ? formatDateTimeLocal(line.clock_in) : '';
            if (memberClockOut) memberClockOut.value = line.clock_out ? formatDateTimeLocal(line.clock_out) : '';
            if (memberBreaks) memberBreaks.value = line.breaks_minutes || 0;
            if (memberNotes) memberNotes.value = line.notes || '';
        }
    } else {
        const formMember = document.getElementById('form-member');
        const memberLineId = document.getElementById('member-line-id');
        if (formMember) formMember.reset();
        if (memberLineId) memberLineId.value = '';
    }
    
    bootstrapModal.show();
}

function handleMemberTypeChange() {
    const type = document.getElementById('member-type')?.value;
    const employeeContainer = document.getElementById('member-employee-container');
    const workerContainer = document.getElementById('member-worker-container');
    
    if (!employeeContainer || !workerContainer) return;
    
    if (type === 'own_employee') {
        employeeContainer.classList.remove('d-none');
        workerContainer.classList.add('d-none');
        const memberEmployeeId = document.getElementById('member-employee-id');
        const memberWorkerId = document.getElementById('member-worker-id');
        if (memberEmployeeId) memberEmployeeId.required = true;
        if (memberWorkerId) memberWorkerId.required = false;
    } else {
        employeeContainer.classList.add('d-none');
        workerContainer.classList.remove('d-none');
        const memberEmployeeId = document.getElementById('member-employee-id');
        const memberWorkerId = document.getElementById('member-worker-id');
        if (memberEmployeeId) memberEmployeeId.required = false;
        if (memberWorkerId) memberWorkerId.required = true;
    }
}

function saveMember() {
    const form = document.getElementById('form-member');
    if (!form || !form.checkValidity()) {
        if (form) form.reportValidity();
        return;
    }
    
    const memberLineId = document.getElementById('member-line-id');
    const memberType = document.getElementById('member-type');
    const memberEmployeeId = document.getElementById('member-employee-id');
    const memberWorkerId = document.getElementById('member-worker-id');
    const memberAttended = document.getElementById('member-attended');
    const memberClockIn = document.getElementById('member-clock-in');
    const memberClockOut = document.getElementById('member-clock-out');
    const memberBreaks = document.getElementById('member-breaks');
    const memberNotes = document.getElementById('member-notes');
    
    const lineId = memberLineId?.value;
    const memberTypeValue = memberType?.value;
    const employeeId = memberEmployeeId?.value;
    const workerId = memberWorkerId?.value;
    const attended = memberAttended?.checked;
    const clockIn = memberClockIn?.value;
    const clockOut = memberClockOut?.value;
    const breaks = parseInt(memberBreaks?.value) || 0;
    const notes = memberNotes?.value;
    
    // Obtener nombre del miembro
    let memberName = '';
    if (memberTypeValue === 'own_employee' && employeeId) {
        const emp = ownEmployees.find(e => e.id.toString() === employeeId);
        memberName = emp ? `${emp.first_name} ${emp.last_name}` : '';
    } else if (memberTypeValue === 'agency_worker' && workerId) {
        const worker = agencyWorkers.find(w => w.id.toString() === workerId);
        memberName = worker ? `${worker.first_name} ${worker.last_name}` : '';
    }
    
    const line = {
        id: lineId || null,
        tempId: lineId && !lineId.match(/^\d+$/) ? lineId : null,
        member_type: memberTypeValue,
        own_employee_id: memberTypeValue === 'own_employee' ? parseInt(employeeId) : null,
        agency_worker_id: memberTypeValue === 'agency_worker' ? parseInt(workerId) : null,
        attended: attended,
        clock_in: clockIn || null,
        clock_out: clockOut || null,
        breaks_minutes: breaks,
        notes: notes || null,
        member_name: memberName
    };
    
    if (lineId) {
        // Actualizar línea existente
        const index = reportLines.findIndex(l => (l.id && l.id.toString() === lineId) || (l.tempId === lineId));
        if (index !== -1) {
            reportLines[index] = line;
        }
    } else {
        // Agregar nueva línea
        line.tempId = `temp-${Date.now()}`;
        reportLines.push(line);
    }
    
    renderMembersTable();
    const modalEl = document.getElementById('modal-member');
    if (modalEl) {
        const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
        if (bootstrapModal) bootstrapModal.hide();
    }
}

function editMember(lineId) {
    openMemberModal(lineId);
}

async function deleteMember(lineId) {
    const confirmed = await Modal.confirm({
        title: 'Eliminar Miembro',
        message: '¿Deseas eliminar este miembro del parte?',
        confirmLabel: 'Eliminar',
        cancelLabel: 'Cancelar'
    });
    
    if (!confirmed) return;
    
    reportLines = reportLines.filter(l => (l.id && l.id.toString() !== lineId) && (l.tempId !== lineId));
    renderMembersTable();
}

function clearMembersTable() {
    reportLines = [];
    const tbody = document.getElementById('members-tbody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted"><em>No hay miembros. Selecciona una orden de trabajo para cargar miembros.</em></td></tr>';
    }
}

async function saveReport(isDraft = false) {
    const reportWorkOrderId = document.getElementById('report-work-order-id');
    const reportDate = document.getElementById('report-date');
    const reportGroupId = document.getElementById('report-group-id');
    const reportProgressPct = document.getElementById('report-progress-pct');
    const reportNotes = document.getElementById('report-notes');
    
    const workOrderId = reportWorkOrderId?.value;
    const date = reportDate?.value;
    const groupId = reportGroupId?.value;
    const progressPct = reportProgressPct?.value;
    const notes = reportNotes?.value;
    
    if (!workOrderId || !date) {
        Toast.error('Orden de trabajo y fecha son requeridos');
        return;
    }
    
    if (reportLines.length === 0) {
        Toast.error('Debes agregar al menos un miembro al parte');
        return;
    }
    
    try {
        const reportData = {
            work_order_id: parseInt(workOrderId),
            date: date,
            group_id: groupId ? parseInt(groupId) : null,
            progress_pct: progressPct ? parseFloat(progressPct) : null,
            notes: notes
        };
        
        let response;
        if (currentReportId) {
            response = await API.put(`/work-order-daily-reports/${currentReportId}`, reportData);
        } else {
            response = await API.post('/work-order-daily-reports', reportData);
        }
        
        if (response && response.success) {
            currentReportId = response.data?.id || currentReportId;
            const reportIdInput = document.getElementById('report-id');
            if (reportIdInput) reportIdInput.value = currentReportId;
            
            // Guardar líneas
            await saveReportLines();
            
            Toast.success('Parte guardado correctamente');
            
            if (!isDraft) {
                setTimeout(() => {
                    window.location.href = '/work-orders';
                }, 1500);
            }
        } else {
            Toast.error(response?.message || 'Error al guardar el parte');
        }
    } catch (error) {
        console.error('Error saving report:', error);
        Toast.error('Error al guardar el parte');
    }
}

async function saveReportLines() {
    if (!currentReportId) return;
    
    // Primero, obtener líneas existentes para eliminar las que ya no están
    try {
        const response = await API.get(`/work-order-daily-reports/${currentReportId}/lines`);
        
        if (response && response.success) {
            const existingLines = response.data || [];
            
            // Eliminar líneas que ya no están en reportLines
            for (const existing of existingLines) {
                const stillExists = reportLines.some(l => l.id && l.id === existing.id);
                if (!stillExists) {
                    await API.delete(`/work-order-daily-reports/${currentReportId}/lines/${existing.id}`);
                }
            }
        }
    } catch (error) {
        console.error('Error loading existing lines:', error);
    }
    
    // Guardar/actualizar líneas
    for (const line of reportLines) {
        try {
            const lineData = {
                member_type: line.member_type,
                own_employee_id: line.own_employee_id,
                agency_worker_id: line.agency_worker_id,
                attended: line.attended,
                clock_in: line.clock_in,
                clock_out: line.clock_out,
                breaks_minutes: line.breaks_minutes,
                notes: line.notes
            };
            
            let response;
            if (line.id && !line.tempId) {
                response = await API.put(`/work-order-daily-reports/${currentReportId}/lines/${line.id}`, lineData);
            } else {
                response = await API.post(`/work-order-daily-reports/${currentReportId}/lines`, lineData);
            }
            
            if (response && response.success && !line.id) {
                // Actualizar ID de la línea
                const index = reportLines.findIndex(l => l.tempId === line.tempId);
                if (index !== -1 && response.data && response.data.length > 0) {
                    const savedLine = response.data.find(l => 
                        l.member_type === line.member_type &&
                        (l.own_employee_id === line.own_employee_id || l.agency_worker_id === line.agency_worker_id)
                    );
                    if (savedLine) {
                        reportLines[index].id = savedLine.id;
                        reportLines[index].tempId = null;
                    }
                }
            }
        } catch (error) {
            console.error('Error saving line:', error);
        }
    }
}

async function submitReport() {
    // Primero guardar como borrador
    await saveReport(true);
    
    if (!currentReportId) {
        Toast.error('Error: No se pudo crear el parte');
        return;
    }
    
    const confirmed = await Modal.confirm({
        title: 'Enviar Parte',
        message: '¿Estás seguro de que deseas enviar este parte? Se generarán work logs para todos los miembros que asistieron.',
        confirmLabel: 'Enviar',
        cancelLabel: 'Cancelar'
    });
    
    if (!confirmed) return;
    
    try {
        const response = await API.put(`/work-order-daily-reports/${currentReportId}/submit`);
        
        if (response && response.success) {
            Toast.success('Parte enviado correctamente. Se generaron los work logs.');
            setTimeout(() => {
                window.location.href = '/work-orders';
            }, 2000);
        } else {
            Toast.error(response?.message || 'Error al enviar el parte');
        }
    } catch (error) {
        console.error('Error submitting report:', error);
        Toast.error('Error al enviar el parte');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Hacer funciones accesibles globalmente
window.editMember = editMember;
window.deleteMember = deleteMember;
