/**
 * JavaScript para Editor de Orden de Trabajo (REFACTORIZADO)
 * Sprint 3.5 - FE-040
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

let currentWorkOrder = {
    id: null,
    tasks: [],
    checklist: [],
    materials: []
};

let editingTaskId = null;
let editingChecklistId = null;
let editingMaterialId = null;

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    // Obtener ID de la URL
    const urlParams = new URLSearchParams(window.location.search);
    const workOrderId = urlParams.get('id');
    
    if (workOrderId) {
        loadWorkOrder(workOrderId);
    } else {
        // Nueva OT
        initializeNewWorkOrder();
    }
    
    setupEventListeners();
});

function setupEventListeners() {
    // Guardar OT
    const btnSave = document.getElementById('btn-save');
    const btnCancel = document.getElementById('btn-cancel');
    
    if (btnSave) btnSave.addEventListener('click', saveWorkOrder);
    if (btnCancel) {
        btnCancel.addEventListener('click', async () => {
            const confirmed = await Modal.confirm({
                title: 'Cancelar',
                message: '¿Estás seguro de que deseas cancelar? Los cambios no guardados se perderán.',
                confirmLabel: 'Cancelar',
                cancelLabel: 'Continuar editando'
            });
            
            if (confirmed) {
                window.location.href = '/work-orders';
            }
        });
    }
    
    // Tareas
    const btnAddTask = document.getElementById('btn-add-task');
    const btnSaveTask = document.getElementById('btn-save-task');
    
    if (btnAddTask) btnAddTask.addEventListener('click', () => openTaskModal());
    if (btnSaveTask) btnSaveTask.addEventListener('click', saveTask);
    
    // Checklist
    const btnAddChecklist = document.getElementById('btn-add-checklist');
    const btnSaveChecklist = document.getElementById('btn-save-checklist');
    
    if (btnAddChecklist) btnAddChecklist.addEventListener('click', () => openChecklistModal());
    if (btnSaveChecklist) btnSaveChecklist.addEventListener('click', saveChecklistItem);
    
    // Materiales
    const btnAddMaterial = document.getElementById('btn-add-material');
    const btnSaveMaterial = document.getElementById('btn-save-material');
    
    if (btnAddMaterial) btnAddMaterial.addEventListener('click', () => openMaterialModal());
    if (btnSaveMaterial) btnSaveMaterial.addEventListener('click', saveMaterial);
    
    // Adjuntos
    const btnAddAttachment = document.getElementById('btn-add-attachment');
    if (btnAddAttachment) {
        btnAddAttachment.addEventListener('click', () => {
            Toast.info('Funcionalidad de adjuntos próximamente');
        });
    }
}

function initializeNewWorkOrder() {
    currentWorkOrder = {
        id: null,
        code: null,
        title: '',
        status: 'draft',
        client_id: null,
        project_id: null,
        location: '',
        start_planned: null,
        end_planned: null,
        scope_text: '',
        safety_notes: '',
        tasks: [],
        checklist: [],
        materials: [],
        attachments: []
    };
    
    renderWorkOrder();
}

async function loadWorkOrder(id) {
    try {
        const response = await API.get(`/work-orders/${id}`);
        
        if (response && response.success) {
            currentWorkOrder = response.data;
            renderWorkOrder();
        } else {
            Toast.error(response?.message || 'Error al cargar orden de trabajo');
        }
    } catch (error) {
        console.error('Error loading work order:', error);
        Toast.error('Error al cargar orden de trabajo');
    }
}

function renderWorkOrder() {
    // Información básica
    const workOrderId = document.getElementById('work-order-id');
    const woCode = document.getElementById('wo-code');
    const woStatus = document.getElementById('wo-status');
    const woTitle = document.getElementById('wo-title');
    const woClient = document.getElementById('wo-client');
    const woProject = document.getElementById('wo-project');
    const woLocation = document.getElementById('wo-location');
    const woStartPlanned = document.getElementById('wo-start-planned');
    const woEndPlanned = document.getElementById('wo-end-planned');
    const woScopeText = document.getElementById('wo-scope-text');
    const woSafetyNotes = document.getElementById('wo-safety-notes');
    
    if (workOrderId) workOrderId.value = currentWorkOrder.id || '';
    if (woCode) woCode.value = currentWorkOrder.code || '';
    if (woStatus) woStatus.value = currentWorkOrder.status || 'draft';
    if (woTitle) woTitle.value = currentWorkOrder.title || '';
    if (woClient) woClient.value = currentWorkOrder.client_id || '';
    if (woProject) woProject.value = currentWorkOrder.project_id || '';
    if (woLocation) woLocation.value = currentWorkOrder.location || '';
    if (woStartPlanned) woStartPlanned.value = currentWorkOrder.start_planned || '';
    if (woEndPlanned) woEndPlanned.value = currentWorkOrder.end_planned || '';
    if (woScopeText) woScopeText.value = currentWorkOrder.scope_text || '';
    if (woSafetyNotes) woSafetyNotes.value = currentWorkOrder.safety_notes || '';
    
    // Fechas
    const woCreatedAt = document.getElementById('wo-created-at');
    const woUpdatedAt = document.getElementById('wo-updated-at');
    
    if (woCreatedAt && currentWorkOrder.created_at) {
        woCreatedAt.textContent = formatDate(currentWorkOrder.created_at);
    }
    if (woUpdatedAt && currentWorkOrder.updated_at) {
        woUpdatedAt.textContent = formatDate(currentWorkOrder.updated_at);
    }
    
    // Renderizar tareas, checklist y materiales
    renderTasks();
    renderChecklist();
    renderMaterials();
    renderAttachments();
}

function renderTasks() {
    const container = document.getElementById('tasks-container');
    if (!container) return;
    
    if (currentWorkOrder.tasks.length === 0) {
        container.innerHTML = '<div class="text-center py-3 text-muted" data-i18n="work_orders.no_tasks">No hay tareas. Haz clic en "Agregar Tarea" para comenzar.</div>';
        return;
    }
    
    container.innerHTML = currentWorkOrder.tasks.map((task, index) => `
        <div class="task-item card-modern mb-2" data-task-id="${task.id}">
            <div class="card-modern__body">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="fw-bold">${escapeHtml(task.name)}</div>
                        ${task.description ? `<div class="text-muted small mt-1">${escapeHtml(task.description)}</div>` : ''}
                        <div class="mt-2">
                            ${task.estimated_hours ? `<span class="badge-modern badge-modern--info me-2"><i class="bi bi-clock"></i> ${task.estimated_hours}h</span>` : ''}
                            ${task.required_skills && task.required_skills.length > 0 ? `
                                ${task.required_skills.map(skill => `<span class="badge-modern badge-modern--secondary me-1">${escapeHtml(skill)}</span>`).join('')}
                            ` : ''}
                        </div>
                    </div>
                    <div>
                        <button class="btn-modern btn-modern--sm btn-modern--primary me-1" onclick="editTask(${index})" aria-label="Editar tarea">
                            <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <button class="btn-modern btn-modern--sm btn-modern--danger" onclick="deleteTask(${task.id})" aria-label="Eliminar tarea">
                            <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function renderChecklist() {
    const container = document.getElementById('checklist-container');
    if (!container) return;
    
    if (currentWorkOrder.checklist.length === 0) {
        container.innerHTML = '<div class="text-center py-3 text-muted" data-i18n="work_orders.no_checklist">No hay items en el checklist.</div>';
        return;
    }
    
    container.innerHTML = currentWorkOrder.checklist.map((item, index) => `
        <div class="checklist-item d-flex align-items-center mb-2" data-checklist-id="${item.id}">
            <input type="checkbox" class="form-check-input me-2" ${item.is_required ? 'checked' : ''} disabled>
            <span class="flex-grow-1">${escapeHtml(item.item)}</span>
            <button class="btn-modern btn-modern--sm btn-modern--danger ms-2" onclick="deleteChecklistItem(${item.id})" aria-label="Eliminar item">
                <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </div>
    `).join('');
}

function renderMaterials() {
    const container = document.getElementById('materials-container');
    if (!container) return;
    
    if (currentWorkOrder.materials.length === 0) {
        container.innerHTML = '<div class="text-center py-3 text-muted" data-i18n="work_orders.no_materials">No hay materiales.</div>';
        return;
    }
    
    container.innerHTML = currentWorkOrder.materials.map((material, index) => `
        <div class="material-item card-modern mb-2" data-material-id="${material.id}">
            <div class="card-modern__body">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="fw-bold">${escapeHtml(material.name)}</div>
                        <div class="small text-muted mt-1">
                            ${material.unit ? `Unidad: ${escapeHtml(material.unit)}` : ''}
                            ${material.qty_planned ? ` | Planificado: ${material.qty_planned}` : ''}
                            ${material.qty_used ? ` | Usado: ${material.qty_used}` : ''}
                        </div>
                    </div>
                    <div>
                        <button class="btn-modern btn-modern--sm btn-modern--primary me-1" onclick="editMaterial(${index})" aria-label="Editar material">
                            <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <button class="btn-modern btn-modern--sm btn-modern--danger" onclick="deleteMaterial(${material.id})" aria-label="Eliminar material">
                            <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function renderAttachments() {
    const container = document.getElementById('attachments-container');
    if (!container) return;
    
    if (!currentWorkOrder.attachments || currentWorkOrder.attachments.length === 0) {
        container.innerHTML = '<div class="text-center py-3 text-muted small" data-i18n="work_orders.no_attachments">No hay adjuntos</div>';
        return;
    }
    
    container.innerHTML = currentWorkOrder.attachments.map((attachment, index) => `
        <div class="attachment-item d-flex align-items-center mb-2">
            <i class="bi bi-paperclip me-2"></i>
            <span class="flex-grow-1">${escapeHtml(attachment.name || 'Adjunto ' + (index + 1))}</span>
            <button class="btn-modern btn-modern--sm btn-modern--danger" onclick="deleteAttachment(${index})" aria-label="Eliminar adjunto">
                <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </div>
    `).join('');
}

function openTaskModal(taskIndex = null) {
    editingTaskId = null;
    const formTask = document.getElementById('form-task');
    const taskId = document.getElementById('task-id');
    const modalTaskTitle = document.getElementById('modal-task-title');
    
    if (formTask) formTask.reset();
    if (taskId) taskId.value = '';
    if (modalTaskTitle) modalTaskTitle.textContent = 'Agregar Tarea';
    
    if (taskIndex !== null && currentWorkOrder.tasks[taskIndex]) {
        const task = currentWorkOrder.tasks[taskIndex];
        editingTaskId = task.id;
        const taskName = document.getElementById('task-name');
        const taskDescription = document.getElementById('task-description');
        const taskEstimatedHours = document.getElementById('task-estimated-hours');
        const taskRequiredSkills = document.getElementById('task-required-skills');
        
        if (taskId) taskId.value = task.id;
        if (taskName) taskName.value = task.name;
        if (taskDescription) taskDescription.value = task.description || '';
        if (taskEstimatedHours) taskEstimatedHours.value = task.estimated_hours || '';
        if (taskRequiredSkills) taskRequiredSkills.value = task.required_skills ? task.required_skills.join(', ') : '';
        if (modalTaskTitle) modalTaskTitle.textContent = 'Editar Tarea';
    }
    
    const modalEl = document.getElementById('modal-task');
    if (modalEl) {
        const bootstrapModal = new bootstrap.Modal(modalEl);
        bootstrapModal.show();
    }
}

function openChecklistModal(itemId = null) {
    editingChecklistId = null;
    const formChecklist = document.getElementById('form-checklist');
    const checklistItemId = document.getElementById('checklist-item-id');
    const checklistIsRequired = document.getElementById('checklist-is-required');
    
    if (formChecklist) formChecklist.reset();
    if (checklistItemId) checklistItemId.value = '';
    if (checklistIsRequired) checklistIsRequired.checked = true;
    
    if (itemId !== null) {
        const item = currentWorkOrder.checklist.find(c => c.id === itemId);
        if (item) {
            editingChecklistId = item.id;
            const checklistItem = document.getElementById('checklist-item');
            
            if (checklistItemId) checklistItemId.value = item.id;
            if (checklistItem) checklistItem.value = item.item;
            if (checklistIsRequired) checklistIsRequired.checked = item.is_required;
        }
    }
    
    const modalEl = document.getElementById('modal-checklist');
    if (modalEl) {
        const bootstrapModal = new bootstrap.Modal(modalEl);
        bootstrapModal.show();
    }
}

function openMaterialModal(materialIndex = null) {
    editingMaterialId = null;
    const formMaterial = document.getElementById('form-material');
    const materialId = document.getElementById('material-id');
    const modalMaterialTitle = document.getElementById('modal-material-title');
    
    if (formMaterial) formMaterial.reset();
    if (materialId) materialId.value = '';
    if (modalMaterialTitle) modalMaterialTitle.textContent = 'Agregar Material';
    
    if (materialIndex !== null && currentWorkOrder.materials[materialIndex]) {
        const material = currentWorkOrder.materials[materialIndex];
        editingMaterialId = material.id;
        const materialName = document.getElementById('material-name');
        const materialUnit = document.getElementById('material-unit');
        const materialQtyPlanned = document.getElementById('material-qty-planned');
        const materialQtyUsed = document.getElementById('material-qty-used');
        
        if (materialId) materialId.value = material.id;
        if (materialName) materialName.value = material.name;
        if (materialUnit) materialUnit.value = material.unit || '';
        if (materialQtyPlanned) materialQtyPlanned.value = material.qty_planned || '';
        if (materialQtyUsed) materialQtyUsed.value = material.qty_used || '';
        if (modalMaterialTitle) modalMaterialTitle.textContent = 'Editar Material';
    }
    
    const modalEl = document.getElementById('modal-material');
    if (modalEl) {
        const bootstrapModal = new bootstrap.Modal(modalEl);
        bootstrapModal.show();
    }
}

async function saveTask() {
    const form = document.getElementById('form-task');
    if (!form || !form.checkValidity()) {
        if (form) form.reportValidity();
        return;
    }
    
    const workOrderId = currentWorkOrder.id;
    if (!workOrderId) {
        Toast.warning('Primero debes guardar la orden de trabajo');
        return;
    }
    
    const taskData = {
        name: document.getElementById('task-name')?.value,
        description: document.getElementById('task-description')?.value || null,
        estimated_hours: document.getElementById('task-estimated-hours')?.value ? parseFloat(document.getElementById('task-estimated-hours').value) : null,
        required_skills: document.getElementById('task-required-skills')?.value 
            ? document.getElementById('task-required-skills').value.split(',').map(s => s.trim()).filter(s => s)
            : []
    };
    
    try {
        let response;
        if (editingTaskId) {
            response = await API.put(`/work-orders/${workOrderId}/tasks/${editingTaskId}`, taskData);
        } else {
            response = await API.post(`/work-orders/${workOrderId}/tasks`, taskData);
        }
        
        if (response && response.success) {
            const modalEl = document.getElementById('modal-task');
            if (modalEl) {
                const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
                if (bootstrapModal) bootstrapModal.hide();
            }
            await loadWorkOrder(workOrderId);
            Toast.success('Tarea guardada correctamente');
        } else {
            Toast.error(response?.message || 'Error al guardar tarea');
        }
    } catch (error) {
        console.error('Error saving task:', error);
        Toast.error('Error al guardar tarea');
    }
}

async function saveChecklistItem() {
    const form = document.getElementById('form-checklist');
    if (!form || !form.checkValidity()) {
        if (form) form.reportValidity();
        return;
    }
    
    const workOrderId = currentWorkOrder.id;
    if (!workOrderId) {
        Toast.warning('Primero debes guardar la orden de trabajo');
        return;
    }
    
    const itemData = {
        item: document.getElementById('checklist-item')?.value,
        is_required: document.getElementById('checklist-is-required')?.checked ? 1 : 0
    };
    
    try {
        let response;
        if (editingChecklistId) {
            response = await API.put(`/work-orders/${workOrderId}/checklist/${editingChecklistId}`, itemData);
        } else {
            response = await API.post(`/work-orders/${workOrderId}/checklist`, itemData);
        }
        
        if (response && response.success) {
            const modalEl = document.getElementById('modal-checklist');
            if (modalEl) {
                const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
                if (bootstrapModal) bootstrapModal.hide();
            }
            await loadWorkOrder(workOrderId);
            Toast.success('Item del checklist guardado correctamente');
        } else {
            Toast.error(response?.message || 'Error al guardar item');
        }
    } catch (error) {
        console.error('Error saving checklist item:', error);
        Toast.error('Error al guardar item');
    }
}

async function saveMaterial() {
    const form = document.getElementById('form-material');
    if (!form || !form.checkValidity()) {
        if (form) form.reportValidity();
        return;
    }
    
    const workOrderId = currentWorkOrder.id;
    if (!workOrderId) {
        Toast.warning('Primero debes guardar la orden de trabajo');
        return;
    }
    
    const materialData = {
        name: document.getElementById('material-name')?.value,
        unit: document.getElementById('material-unit')?.value || null,
        qty_planned: document.getElementById('material-qty-planned')?.value ? parseFloat(document.getElementById('material-qty-planned').value) : null,
        qty_used: document.getElementById('material-qty-used')?.value ? parseFloat(document.getElementById('material-qty-used').value) : null
    };
    
    try {
        let response;
        if (editingMaterialId) {
            response = await API.put(`/work-orders/${workOrderId}/materials/${editingMaterialId}`, materialData);
        } else {
            response = await API.post(`/work-orders/${workOrderId}/materials`, materialData);
        }
        
        if (response && response.success) {
            const modalEl = document.getElementById('modal-material');
            if (modalEl) {
                const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
                if (bootstrapModal) bootstrapModal.hide();
            }
            await loadWorkOrder(workOrderId);
            Toast.success('Material guardado correctamente');
        } else {
            Toast.error(response?.message || 'Error al guardar material');
        }
    } catch (error) {
        console.error('Error saving material:', error);
        Toast.error('Error al guardar material');
    }
}

async function deleteTask(taskId) {
    const confirmed = await Modal.confirm({
        title: 'Eliminar Tarea',
        message: '¿Estás seguro de que deseas eliminar esta tarea?',
        confirmLabel: 'Eliminar',
        cancelLabel: 'Cancelar'
    });
    
    if (!confirmed) return;
    
    const workOrderId = currentWorkOrder.id;
    if (!workOrderId) return;
    
    try {
        const response = await API.delete(`/work-orders/${workOrderId}/tasks/${taskId}`);
        
        if (response && response.success) {
            await loadWorkOrder(workOrderId);
            Toast.success('Tarea eliminada correctamente');
        } else {
            Toast.error(response?.message || 'Error al eliminar tarea');
        }
    } catch (error) {
        console.error('Error deleting task:', error);
        Toast.error('Error al eliminar tarea');
    }
}

async function deleteChecklistItem(itemId) {
    const confirmed = await Modal.confirm({
        title: 'Eliminar Item',
        message: '¿Estás seguro de que deseas eliminar este item?',
        confirmLabel: 'Eliminar',
        cancelLabel: 'Cancelar'
    });
    
    if (!confirmed) return;
    
    const workOrderId = currentWorkOrder.id;
    if (!workOrderId) return;
    
    try {
        const response = await API.delete(`/work-orders/${workOrderId}/checklist/${itemId}`);
        
        if (response && response.success) {
            await loadWorkOrder(workOrderId);
            Toast.success('Item eliminado correctamente');
        } else {
            Toast.error(response?.message || 'Error al eliminar item');
        }
    } catch (error) {
        console.error('Error deleting checklist item:', error);
        Toast.error('Error al eliminar item');
    }
}

async function deleteMaterial(materialId) {
    const confirmed = await Modal.confirm({
        title: 'Eliminar Material',
        message: '¿Estás seguro de que deseas eliminar este material?',
        confirmLabel: 'Eliminar',
        cancelLabel: 'Cancelar'
    });
    
    if (!confirmed) return;
    
    const workOrderId = currentWorkOrder.id;
    if (!workOrderId) return;
    
    try {
        const response = await API.delete(`/work-orders/${workOrderId}/materials/${materialId}`);
        
        if (response && response.success) {
            await loadWorkOrder(workOrderId);
            Toast.success('Material eliminado correctamente');
        } else {
            Toast.error(response?.message || 'Error al eliminar material');
        }
    } catch (error) {
        console.error('Error deleting material:', error);
        Toast.error('Error al eliminar material');
    }
}

async function saveWorkOrder() {
    const form = document.getElementById('form-work-order');
    if (!form || !form.checkValidity()) {
        if (form) form.reportValidity();
        return;
    }
    
    const workOrderData = {
        title: document.getElementById('wo-title')?.value,
        status: document.getElementById('wo-status')?.value,
        client_id: document.getElementById('wo-client')?.value || null,
        project_id: document.getElementById('wo-project')?.value || null,
        location: document.getElementById('wo-location')?.value || null,
        start_planned: document.getElementById('wo-start-planned')?.value || null,
        end_planned: document.getElementById('wo-end-planned')?.value || null,
        scope_text: document.getElementById('wo-scope-text')?.value || null,
        safety_notes: document.getElementById('wo-safety-notes')?.value || null,
        attachments: currentWorkOrder.attachments || []
    };
    
    try {
        const workOrderId = currentWorkOrder.id;
        let response;
        
        if (workOrderId) {
            response = await API.put(`/work-orders/${workOrderId}`, workOrderData);
        } else {
            response = await API.post('/work-orders', workOrderData);
        }
        
        if (response && response.success) {
            Toast.success('Orden de trabajo guardada correctamente');
            if (!workOrderId) {
                // Redirigir a la edición de la nueva OT
                window.location.href = `/work-orders-edit?id=${response.data.id}`;
            } else {
                // Recargar datos
                await loadWorkOrder(workOrderId);
            }
        } else {
            Toast.error(response?.message || 'Error al guardar orden de trabajo');
        }
    } catch (error) {
        console.error('Error saving work order:', error);
        Toast.error('Error al guardar orden de trabajo');
    }
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Hacer funciones accesibles globalmente
window.editTask = (index) => openTaskModal(index);
window.deleteTask = deleteTask;
window.editMaterial = (index) => openMaterialModal(index);
window.deleteMaterial = deleteMaterial;
window.deleteChecklistItem = deleteChecklistItem;
window.deleteAttachment = (index) => {
    currentWorkOrder.attachments.splice(index, 1);
    renderAttachments();
};
