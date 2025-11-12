/**
 * Gestión de Trabajadores Temporeros
 */

let workersTable = null;
let currentWorkerId = null;
let skills = [];

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    initializeWorkers();
});

function initializeWorkers() {
    // Inicializar tabla
    loadWorkers();
    
    // Event listeners
    document.getElementById('btn-create-worker').addEventListener('click', showCreateModal);
    document.getElementById('btn-save-worker').addEventListener('click', saveWorker);
    document.getElementById('btn-reset-filters').addEventListener('click', resetFilters);
    document.getElementById('filter-search').addEventListener('input', debounce(loadWorkers, 300));
    document.getElementById('filter-active').addEventListener('change', loadWorkers);
    document.getElementById('btn-add-skill').addEventListener('click', addSkill);
    document.getElementById('skill-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addSkill();
        }
    });
    document.getElementById('btn-save-availability').addEventListener('click', saveAvailability);
    
    // Modal events
    const modalWorker = new bootstrap.Modal(document.getElementById('modal-worker'));
    document.getElementById('modal-worker').addEventListener('hidden.bs.modal', function() {
        resetForm();
    });
}

/**
 * Carga la lista de trabajadores
 */
function loadWorkers() {
    const search = document.getElementById('filter-search').value;
    const isActive = document.getElementById('filter-active').value;
    
    const params = new URLSearchParams({
        page: 1,
        page_size: 20
    });
    
    if (search) {
        params.append('q', search);
    }
    
    if (isActive !== '') {
        params.append('is_active', isActive);
    }
    
    API.get(`/agency-workers?${params.toString()}`)
        .then(response => {
            if (response.success) {
                renderTable(response.data, response.total, response.page, response.page_size);
            } else {
                Toast.error(response.message || 'Error al cargar trabajadores');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Toast.error('Error al cargar trabajadores');
        });
}

/**
 * Renderiza la tabla de trabajadores
 */
function renderTable(workers, total, page, pageSize) {
    const container = document.getElementById('workers-table-container');
    
    if (workers.length === 0) {
        container.innerHTML = '';
        new EmptyState(container, {
            icon: 'bi-people',
            title: 'No hay trabajadores',
            message: 'Comienza creando tu primer trabajador',
            action: {
                label: 'Crear Trabajador',
                callback: showCreateModal
            }
        });
        return;
    }
    
    const columns = [
        {
            key: 'full_name',
            label: 'Nombre',
            render: (row) => `${row.first_name} ${row.last_name}`
        },
        {
            key: 'worker_code',
            label: 'Código',
            render: (row) => row.worker_code || '-'
        },
        {
            key: 'email',
            label: 'Email',
            render: (row) => row.email || '-'
        },
        {
            key: 'phone',
            label: 'Teléfono',
            render: (row) => row.phone || '-'
        },
        {
            key: 'skills',
            label: 'Habilidades',
            render: (row) => {
                if (!row.skills || row.skills.length === 0) {
                    return '<span class="text-muted">-</span>';
                }
                return row.skills.slice(0, 3).map(skill => 
                    `<span class="badge bg-secondary me-1">${skill}</span>`
                ).join('') + (row.skills.length > 3 ? ` <span class="text-muted">+${row.skills.length - 3}</span>` : '');
            }
        },
        {
            key: 'rating_avg',
            label: 'Valoración',
            render: (row) => row.rating_avg 
                ? `<span class="badge bg-warning">${parseFloat(row.rating_avg).toFixed(1)} ⭐</span>`
                : '<span class="text-muted">-</span>'
        },
        {
            key: 'is_active',
            label: 'Estado',
            render: (row) => row.is_active 
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>'
        },
        {
            key: 'actions',
            label: 'Acciones',
            render: (row) => `
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="editWorker(${row.id})" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-info" onclick="showAvailability(${row.id})" title="Disponibilidad">
                        <i class="bi bi-calendar-check"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="deleteWorker(${row.id})" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `
        }
    ];
    
    workersTable = new DataTable(container, {
        columns: columns,
        data: workers,
        page: page,
        pageSize: pageSize,
        total: total,
        onPageChange: (newPage) => {
            loadWorkers();
        }
    });
}

/**
 * Muestra el modal para crear trabajador
 */
function showCreateModal() {
    currentWorkerId = null;
    skills = [];
    document.getElementById('modal-worker-title').textContent = 'Crear Trabajador';
    document.getElementById('form-worker').reset();
    document.getElementById('worker-id').value = '';
    renderSkills();
    
    const modal = new bootstrap.Modal(document.getElementById('modal-worker'));
    modal.show();
}

/**
 * Edita un trabajador
 */
async function editWorker(id) {
    try {
        const response = await API.get(`/agency-workers/${id}`);
        
        if (response.success) {
            currentWorkerId = id;
            const worker = response.data;
            
            document.getElementById('modal-worker-title').textContent = 'Editar Trabajador';
            document.getElementById('worker-id').value = worker.id;
            document.getElementById('worker-first-name').value = worker.first_name || '';
            document.getElementById('worker-last-name').value = worker.last_name || '';
            document.getElementById('worker-code').value = worker.worker_code || '';
            document.getElementById('worker-email').value = worker.email || '';
            document.getElementById('worker-phone').value = worker.phone || '';
            document.getElementById('worker-address').value = worker.address || '';
            document.getElementById('worker-active').value = worker.is_active ? '1' : '0';
            
            skills = worker.skills || [];
            renderSkills();
            
            const modal = new bootstrap.Modal(document.getElementById('modal-worker'));
            modal.show();
        } else {
            Toast.error(response.message || 'Error al cargar trabajador');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al cargar trabajador');
    }
}

/**
 * Guarda un trabajador
 */
async function saveWorker() {
    const form = document.getElementById('form-worker');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const data = {
        first_name: document.getElementById('worker-first-name').value.trim(),
        last_name: document.getElementById('worker-last-name').value.trim(),
        worker_code: document.getElementById('worker-code').value.trim() || null,
        email: document.getElementById('worker-email').value.trim() || null,
        phone: document.getElementById('worker-phone').value.trim() || null,
        address: document.getElementById('worker-address').value.trim() || null,
        is_active: document.getElementById('worker-active').value === '1',
        skills: skills
    };
    
    try {
        let response;
        
        if (currentWorkerId) {
            response = await API.put(`/agency-workers/${currentWorkerId}`, data);
        } else {
            response = await API.post('/agency-workers', data);
        }
        
        if (response.success) {
            Toast.success(response.message || 'Trabajador guardado correctamente');
            bootstrap.Modal.getInstance(document.getElementById('modal-worker')).hide();
            loadWorkers();
        } else {
            Toast.error(response.message || 'Error al guardar trabajador');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al guardar trabajador');
    }
}

/**
 * Elimina un trabajador
 */
async function deleteWorker(id) {
    // Usar Modal.confirm si está disponible, sino usar confirm nativo como fallback
    let confirmed = false;
    if (typeof Modal !== "undefined") {
        confirmed = await Modal.confirm({
            title: 'Eliminar Trabajador',
            message: '¿Estás seguro de que deseas eliminar este trabajador?'
        });
    } else {
        confirmed = confirm('¿Estás seguro de que deseas eliminar este trabajador?');
    }
    
    if (!confirmed) {
        return;
    }
    
    try {
        const response = await API.delete(`/agency-workers/${id}`);
        
        if (response.success) {
            Toast.success(response.message || 'Trabajador eliminado correctamente');
            loadWorkers();
        } else {
            Toast.error(response.message || 'Error al eliminar trabajador');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al eliminar trabajador');
    }
}

/**
 * Agrega un skill
 */
function addSkill() {
    const input = document.getElementById('skill-input');
    const skill = input.value.trim();
    
    if (!skill) {
        return;
    }
    
    if (skills.includes(skill)) {
        Toast.warning('Esta habilidad ya está agregada');
        return;
    }
    
    skills.push(skill);
    skills.sort();
    input.value = '';
    renderSkills();
}

/**
 * Elimina un skill
 */
function removeSkill(skill) {
    skills = skills.filter(s => s !== skill);
    renderSkills();
}

/**
 * Renderiza los skills
 */
function renderSkills() {
    const container = document.getElementById('skills-container');
    
    if (skills.length === 0) {
        container.innerHTML = '<p class="text-muted mb-0">No hay habilidades agregadas</p>';
        return;
    }
    
    container.innerHTML = skills.map(skill => `
        <span class="badge bg-primary me-2 mb-2">
            ${skill}
            <button type="button" class="btn-close btn-close-white ms-2" onclick="removeSkill('${skill}')" style="font-size: 0.7em;"></button>
        </span>
    `).join('');
}

/**
 * Muestra la disponibilidad de un trabajador
 */
async function showAvailability(id) {
    try {
        const response = await API.get(`/agency-workers/${id}/availability`);
        
        if (response.success) {
            currentWorkerId = id;
            document.getElementById('availability-worker-id').value = id;
            
            const availability = response.data || {};
            document.getElementById('availability-json').value = JSON.stringify(availability, null, 2);
            
            const modal = new bootstrap.Modal(document.getElementById('modal-availability'));
            modal.show();
        } else {
            Toast.error(response.message || 'Error al cargar disponibilidad');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al cargar disponibilidad');
    }
}

/**
 * Guarda la disponibilidad
 */
async function saveAvailability() {
    const workerId = document.getElementById('availability-worker-id').value;
    const jsonText = document.getElementById('availability-json').value.trim();
    
    if (!jsonText) {
        Toast.error('El JSON de disponibilidad no puede estar vacío');
        return;
    }
    
    let availability;
    try {
        availability = JSON.parse(jsonText);
    } catch (e) {
        Toast.error('JSON inválido. Por favor, verifica el formato.');
        return;
    }
    
    try {
        const response = await API.post(`/agency-workers/${workerId}/availability`, {
            availability: availability
        });
        
        if (response.success) {
            Toast.success('Disponibilidad guardada correctamente');
            bootstrap.Modal.getInstance(document.getElementById('modal-availability')).hide();
        } else {
            Toast.error(response.message || 'Error al guardar disponibilidad');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al guardar disponibilidad');
    }
}

/**
 * Resetea los filtros
 */
function resetFilters() {
    document.getElementById('filter-search').value = '';
    document.getElementById('filter-active').value = '1';
    loadWorkers();
}

/**
 * Resetea el formulario
 */
function resetForm() {
    document.getElementById('form-worker').reset();
    currentWorkerId = null;
    skills = [];
    renderSkills();
}

/**
 * Debounce helper
 */
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

