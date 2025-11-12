/**
 * JavaScript para Constructor de Grupos (REFACTORIZADO)
 * Incluye drag & drop, filtros de candidatos, y gestión de miembros
 * 
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications
 * - Modal component (para confirmaciones)
 */

// Asegurar que los componentes estén cargados
if (typeof API === 'undefined' || typeof Toast === 'undefined') {
    console.error('Componentes no cargados. Asegúrate de incluir components-loader.js');
}

let currentGroup = {
    id: null,
    name: '',
    leader_employee_id: null,
    project_id: null,
    notes: '',
    members: []
};

let candidates = [];
let ownEmployees = [];
let agencyWorkers = [];
let currentFilters = {
    type: null,
    skills: null,
    search: null
};

let membersSortable = null;
let candidatesSortable = null;

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    initializeLoadingSpinner();
    initializeDragAndDrop();
    loadOwnEmployees();
    loadAgencyWorkers();
    setupEventListeners();
});

function initializeLoadingSpinner() {
    const spinnerContainer = document.getElementById('loading-spinner-container');
    if (spinnerContainer && typeof LoadingSpinner !== 'undefined') {
        window.loadingSpinner = new LoadingSpinner(spinnerContainer);
    }
}

function initializeDragAndDrop() {
    const membersContainer = document.getElementById('group-members');
    const candidatesContainer = document.getElementById('candidates-list');
    
    // Hacer sortable el contenedor de miembros (para reordenar)
    if (membersContainer) {
        membersSortable = new Sortable(membersContainer, {
            group: 'shared',
            animation: 150,
            handle: '.drag-handle',
            onEnd: (evt) => {
                updateMemberCount();
            }
        });
    }
    
    // Hacer sortable el contenedor de candidatos (para arrastrar)
    if (candidatesContainer) {
        candidatesSortable = new Sortable(candidatesContainer, {
            group: {
                name: 'shared',
                pull: 'clone',
                put: false
            },
            sort: false,
            animation: 150
        });
    }
    
    // Permitir que los candidatos se puedan arrastrar a miembros
    if (membersContainer && candidatesContainer) {
        new Sortable(membersContainer, {
            group: 'shared',
            animation: 150,
            onAdd: (evt) => {
                const item = evt.item;
                const candidateId = item.dataset.candidateId;
                const candidateType = item.dataset.candidateType;
                
                if (candidateId && candidateType) {
                    addMemberToGroup(candidateId, candidateType, item);
                }
            }
        });
    }
}

function setupEventListeners() {
    // Guardar grupo
    const btnSave = document.getElementById('btn-save-group');
    const btnConfirmSave = document.getElementById('btn-confirm-save');
    const btnLoadGroup = document.getElementById('btn-load-group');
    
    if (btnSave) btnSave.addEventListener('click', saveGroup);
    if (btnConfirmSave) btnConfirmSave.addEventListener('click', confirmSaveGroup);
    if (btnLoadGroup) btnLoadGroup.addEventListener('click', openLoadGroupModal);
    
    // Filtros
    const filterType = document.getElementById('filter-candidate-type');
    const filterSkills = document.getElementById('filter-skills');
    const filterSearch = document.getElementById('filter-search');
    const btnClearFilters = document.getElementById('btn-clear-filters');
    
    if (filterType) filterType.addEventListener('change', applyFilters);
    if (filterSkills) filterSkills.addEventListener('input', debounce(applyFilters, 300));
    if (filterSearch) filterSearch.addEventListener('input', debounce(applyFilters, 300));
    if (btnClearFilters) btnClearFilters.addEventListener('click', clearFilters);
    
    // Formulario
    const groupName = document.getElementById('group-name');
    const groupLeader = document.getElementById('group-leader');
    const groupNotes = document.getElementById('group-notes');
    
    if (groupName) {
        groupName.addEventListener('input', (e) => {
            currentGroup.name = e.target.value;
        });
    }
    
    if (groupLeader) {
        groupLeader.addEventListener('change', (e) => {
            currentGroup.leader_employee_id = e.target.value ? parseInt(e.target.value) : null;
        });
    }
    
    if (groupNotes) {
        groupNotes.addEventListener('input', (e) => {
            currentGroup.notes = e.target.value;
        });
    }
}

async function loadOwnEmployees() {
    if (window.loadingSpinner) {
        window.loadingSpinner.show('Cargando empleados...');
    }
    
    try {
        // TODO: Implementar API de empleados cuando esté disponible
        // Por ahora, usar datos mock o cargar desde shifts/otros endpoints
        ownEmployees = [];
        renderCandidates();
    } catch (error) {
        console.error('Error loading own employees:', error);
        ownEmployees = [];
        renderCandidates();
    } finally {
        if (window.loadingSpinner) {
            window.loadingSpinner.hide();
        }
    }
}

async function loadAgencyWorkers() {
    if (window.loadingSpinner) {
        window.loadingSpinner.show('Cargando trabajadores...');
    }
    
    try {
        // TODO: Implementar API de trabajadores cuando esté disponible
        // Por ahora, usar datos mock o cargar desde propuestas aceptadas
        agencyWorkers = [];
        renderCandidates();
    } catch (error) {
        console.error('Error loading agency workers:', error);
        agencyWorkers = [];
        renderCandidates();
    } finally {
        if (window.loadingSpinner) {
            window.loadingSpinner.hide();
        }
    }
}

function renderCandidates() {
    const container = document.getElementById('candidates-list');
    if (!container) return;
    
    // Combinar empleados propios y trabajadores temporeros
    let allCandidates = [];
    
    ownEmployees.forEach(emp => {
        allCandidates.push({
            id: emp.id,
            type: 'own_employee',
            name: `${emp.first_name} ${emp.last_name}`,
            code: emp.employee_code || `EMP${emp.id}`,
            skills: emp.skills_json ? JSON.parse(emp.skills_json) : []
        });
    });
    
    agencyWorkers.forEach(worker => {
        allCandidates.push({
            id: worker.id,
            type: 'agency_worker',
            name: `${worker.first_name} ${worker.last_name}`,
            code: worker.worker_code || `WRK${worker.id}`,
            skills: worker.skills_json ? JSON.parse(worker.skills_json) : [],
            agency_name: worker.agency_name
        });
    });
    
    // Aplicar filtros
    let filtered = allCandidates;
    
    if (currentFilters.type) {
        filtered = filtered.filter(c => c.type === currentFilters.type);
    }
    
    if (currentFilters.skills) {
        const skillsFilter = currentFilters.skills.toLowerCase().split(',').map(s => s.trim());
        filtered = filtered.filter(c => {
            return skillsFilter.some(skill => 
                c.skills.some(s => s.toLowerCase().includes(skill))
            );
        });
    }
    
    if (currentFilters.search) {
        const searchLower = currentFilters.search.toLowerCase();
        filtered = filtered.filter(c => 
            c.name.toLowerCase().includes(searchLower) ||
            c.code.toLowerCase().includes(searchLower)
        );
    }
    
    candidates = filtered;
    
    // Renderizar
    if (candidates.length === 0) {
        // Usar EmptyState component si está disponible
        if (typeof EmptyState !== 'undefined') {
            EmptyState.show(container, {
                icon: '📭',
                title: 'No hay candidatos',
                message: 'No hay candidatos disponibles'
            });
        } else {
            container.innerHTML = `
                <div class="text-center py-3 text-muted">
                    <i class="bi bi-inbox"></i>
                    <p class="mt-2 mb-0" data-i18n="groups.no_candidates">No hay candidatos disponibles</p>
                </div>
            `;
        }
    } else {
        container.innerHTML = candidates.map(candidate => `
            <div class="candidate-item" 
                 data-candidate-id="${candidate.id}" 
                 data-candidate-type="${candidate.type}"
                 draggable="true">
                <div class="d-flex align-items-center">
                    <div class="drag-handle me-2">
                        <i class="bi bi-grip-vertical"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold">${escapeHtml(candidate.name)}</div>
                        <div class="small text-muted">${escapeHtml(candidate.code)}</div>
                        ${candidate.agency_name ? `<div class="small text-info">${escapeHtml(candidate.agency_name)}</div>` : ''}
                        ${candidate.skills.length > 0 ? `
                            <div class="mt-1">
                                ${candidate.skills.slice(0, 3).map(skill => 
                                    `<span class="badge-modern badge-modern--secondary me-1">${escapeHtml(skill)}</span>`
                                ).join('')}
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    const candidateCount = document.getElementById('candidate-count');
    if (candidateCount) candidateCount.textContent = candidates.length;
}

function addMemberToGroup(candidateId, candidateType, element) {
    // Buscar candidato
    let candidate = null;
    if (candidateType === 'own_employee') {
        candidate = ownEmployees.find(e => e.id == candidateId);
    } else {
        candidate = agencyWorkers.find(w => w.id == candidateId);
    }
    
    if (!candidate) {
        element.remove();
        return;
    }
    
    // Verificar si ya está en el grupo
    const existing = currentGroup.members.find(m => 
        (candidateType === 'own_employee' && m.own_employee_id == candidateId) ||
        (candidateType === 'agency_worker' && m.agency_worker_id == candidateId)
    );
    
    if (existing) {
        element.remove();
        Toast.warning('Este trabajador ya está en el grupo');
        return;
    }
    
    // Agregar a miembros
    const member = {
        id: null, // Nuevo miembro
        member_type: candidateType,
        own_employee_id: candidateType === 'own_employee' ? candidate.id : null,
        agency_worker_id: candidateType === 'agency_worker' ? candidate.id : null,
        tenant_agency_id: candidate.tenant_id || null,
        start_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
        end_at: null,
        name: `${candidate.first_name} ${candidate.last_name}`,
        code: candidate.employee_code || candidate.worker_code || `ID${candidate.id}`
    };
    
    currentGroup.members.push(member);
    
    // Actualizar UI
    updateMembersDisplay();
    updateMemberCount();
    
    // Remover elemento temporal
    element.remove();
}

function updateMembersDisplay() {
    const container = document.getElementById('group-members');
    if (!container) return;
    
    if (currentGroup.members.length === 0) {
        // Usar EmptyState component si está disponible
        if (typeof EmptyState !== 'undefined') {
            EmptyState.show(container, {
                icon: '👥',
                title: 'Sin miembros',
                message: 'Arrastra trabajadores aquí para agregarlos al grupo'
            });
        } else {
            container.innerHTML = `
                <div class="empty-state text-center py-5">
                    <i class="bi bi-people" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="text-muted mt-3" data-i18n="groups.no_members">Arrastra trabajadores aquí para agregarlos al grupo</p>
                </div>
            `;
        }
        return;
    }
    
    container.innerHTML = currentGroup.members.map((member, index) => `
        <div class="member-item" data-member-index="${index}">
            <div class="d-flex align-items-center">
                <div class="drag-handle me-2">
                    <i class="bi bi-grip-vertical"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-bold">${escapeHtml(member.name)}</div>
                    <div class="small text-muted">${escapeHtml(member.code)}</div>
                    <div class="small">
                        <span class="badge-modern ${member.member_type === 'own_employee' ? 'badge-modern--primary' : 'badge-modern--info'}">
                            ${member.member_type === 'own_employee' ? 'Empleado Propio' : 'Trabajador Temporero'}
                        </span>
                    </div>
                </div>
                <button class="btn-modern btn-modern--sm btn-modern--danger" onclick="removeMember(${index})" aria-label="Eliminar miembro">
                    <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    `).join('');
    
    // Reinicializar sortable
    if (membersSortable) {
        membersSortable.destroy();
    }
    membersSortable = new Sortable(container, {
        group: 'shared',
        animation: 150,
        handle: '.drag-handle',
        onEnd: (evt) => {
            // Reordenar array de miembros según nueva posición
            const items = Array.from(container.children);
            currentGroup.members = items.map(item => {
                const index = parseInt(item.dataset.memberIndex);
                return currentGroup.members[index];
            });
            updateMemberCount();
        }
    });
}

function removeMember(index) {
    currentGroup.members.splice(index, 1);
    updateMembersDisplay();
    updateMemberCount();
}

function updateMemberCount() {
    const memberCount = document.getElementById('member-count');
    if (memberCount) memberCount.textContent = currentGroup.members.length;
}

function applyFilters() {
    const filterType = document.getElementById('filter-candidate-type');
    const filterSkills = document.getElementById('filter-skills');
    const filterSearch = document.getElementById('filter-search');
    
    currentFilters.type = filterType?.value || null;
    currentFilters.skills = filterSkills?.value || null;
    currentFilters.search = filterSearch?.value || null;
    
    renderCandidates();
}

function clearFilters() {
    const filterType = document.getElementById('filter-candidate-type');
    const filterSkills = document.getElementById('filter-skills');
    const filterSearch = document.getElementById('filter-search');
    
    if (filterType) filterType.value = '';
    if (filterSkills) filterSkills.value = '';
    if (filterSearch) filterSearch.value = '';
    
    currentFilters = { type: null, skills: null, search: null };
    renderCandidates();
}

async function saveGroup() {
    // Validar
    if (!currentGroup.name) {
        Toast.error('El nombre del grupo es requerido');
        const groupName = document.getElementById('group-name');
        if (groupName) groupName.focus();
        return;
    }
    
    if (currentGroup.members.length === 0) {
        Toast.warning('El grupo debe tener al menos un miembro');
        return;
    }
    
    // Mostrar resumen
    const summary = document.getElementById('save-summary');
    if (summary) {
        summary.innerHTML = `
            <li><strong>Nombre:</strong> ${escapeHtml(currentGroup.name)}</li>
            <li><strong>Miembros:</strong> ${currentGroup.members.length}</li>
            <li><strong>Líder:</strong> ${currentGroup.leader_employee_id ? 'Asignado' : 'Sin asignar'}</li>
        `;
    }
    
    const modalEl = document.getElementById('modal-save-group');
    if (modalEl) {
        const bootstrapModal = new bootstrap.Modal(modalEl);
        bootstrapModal.show();
    }
}

async function confirmSaveGroup() {
    try {
        // Preparar datos del grupo
        const groupData = {
            name: currentGroup.name,
            leader_employee_id: currentGroup.leader_employee_id || null,
            project_id: currentGroup.project_id || null,
            notes: currentGroup.notes || null
        };
        
        // Crear o actualizar grupo
        let response;
        if (currentGroup.id) {
            response = await API.put(`/work-groups/${currentGroup.id}`, groupData);
        } else {
            response = await API.post('/work-groups', groupData);
        }
        
        if (!response || !response.success) {
            throw new Error(response?.message || 'Error al guardar grupo');
        }
        
        const groupId = response.data?.id || currentGroup.id;
        
        // Agregar miembros al grupo
        for (const member of currentGroup.members) {
            await addMemberToGroupAPI(groupId, member);
        }
        
        const modalEl = document.getElementById('modal-save-group');
        if (modalEl) {
            const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
            if (bootstrapModal) bootstrapModal.hide();
        }
        
        Toast.success('Grupo guardado correctamente');
        
        // Resetear formulario
        resetForm();
        
    } catch (error) {
        console.error('Error saving group:', error);
        Toast.error('Error al guardar grupo: ' + (error.message || 'Error desconocido'));
    }
}

async function addMemberToGroupAPI(groupId, member) {
    try {
        const memberData = {
            member_type: member.member_type,
            own_employee_id: member.own_employee_id,
            agency_worker_id: member.agency_worker_id,
            tenant_agency_id: member.tenant_agency_id,
            start_at: member.start_at,
            end_at: member.end_at
        };
        
        const response = await API.post(`/work-groups/${groupId}/members`, memberData);
        
        if (!response || !response.success) {
            console.error('Error adding member:', response);
        }
    } catch (error) {
        console.error('Error adding member to group:', error);
    }
}

function openLoadGroupModal() {
    // TODO: Implementar modal para cargar grupo existente
    Toast.info('Funcionalidad de cargar grupo próximamente');
}

function resetForm() {
    currentGroup = {
        id: null,
        name: '',
        leader_employee_id: null,
        project_id: null,
        notes: '',
        members: []
    };
    
    const form = document.getElementById('form-group');
    const groupId = document.getElementById('group-id');
    
    if (form) form.reset();
    if (groupId) groupId.value = '';
    
    updateMembersDisplay();
    updateMemberCount();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Hacer removeMember accesible globalmente
window.removeMember = removeMember;
