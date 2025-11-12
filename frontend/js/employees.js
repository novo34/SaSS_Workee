/**
 * Gestión de Empleados
 */

let employeesTable = null;
let currentEmployeeId = null;
let skills = [];

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    initializeEmployees();
});

function initializeEmployees() {
    // Inicializar tabla
    loadEmployees();
    
    // Event listeners
    document.getElementById('btn-create-employee').addEventListener('click', showCreateModal);
    document.getElementById('btn-save-employee').addEventListener('click', saveEmployee);
    document.getElementById('btn-reset-filters').addEventListener('click', resetFilters);
    document.getElementById('filter-search').addEventListener('input', debounce(loadEmployees, 300));
    document.getElementById('filter-active').addEventListener('change', loadEmployees);
    document.getElementById('btn-add-skill').addEventListener('click', addSkill);
    document.getElementById('skill-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addSkill();
        }
    });
    document.getElementById('form-upload-document').addEventListener('submit', uploadDocument);
    
    // Modal events
    const modalEmployee = new bootstrap.Modal(document.getElementById('modal-employee'));
    document.getElementById('modal-employee').addEventListener('hidden.bs.modal', function() {
        resetForm();
    });
}

/**
 * Carga la lista de empleados
 */
function loadEmployees() {
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
    
    API.get(`/employees?${params.toString()}`)
        .then(response => {
            if (response.success) {
                renderTable(response.data, response.total, response.page, response.page_size);
            } else {
                Toast.error(response.message || 'Error al cargar empleados');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Toast.error('Error al cargar empleados');
        });
}

/**
 * Renderiza la tabla de empleados
 */
function renderTable(employees, total, page, pageSize) {
    const container = document.getElementById('employees-table-container');
    
    if (employees.length === 0) {
        container.innerHTML = '';
        new EmptyState(container, {
            icon: 'bi-people',
            title: 'No hay empleados',
            message: 'Comienza creando tu primer empleado',
            action: {
                label: 'Crear Empleado',
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
            key: 'employee_code',
            label: 'Código',
            render: (row) => row.employee_code || '-'
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
                    <button class="btn btn-outline-primary" onclick="editEmployee(${row.id})" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-info" onclick="showDocuments(${row.id})" title="Documentos">
                        <i class="bi bi-file-earmark"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="deleteEmployee(${row.id})" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `
        }
    ];
    
    employeesTable = new DataTable(container, {
        columns: columns,
        data: employees,
        page: page,
        pageSize: pageSize,
        total: total,
        onPageChange: (newPage) => {
            // Implementar paginación si es necesario
            loadEmployees();
        }
    });
}

/**
 * Muestra el modal para crear empleado
 */
function showCreateModal() {
    currentEmployeeId = null;
    skills = [];
    document.getElementById('modal-employee-title').textContent = 'Crear Empleado';
    document.getElementById('form-employee').reset();
    document.getElementById('employee-id').value = '';
    renderSkills();
    
    const modal = new bootstrap.Modal(document.getElementById('modal-employee'));
    modal.show();
}

/**
 * Edita un empleado
 */
async function editEmployee(id) {
    try {
        const response = await API.get(`/employees/${id}`);
        
        if (response.success) {
            currentEmployeeId = id;
            const employee = response.data;
            
            document.getElementById('modal-employee-title').textContent = 'Editar Empleado';
            document.getElementById('employee-id').value = employee.id;
            document.getElementById('employee-first-name').value = employee.first_name || '';
            document.getElementById('employee-last-name').value = employee.last_name || '';
            document.getElementById('employee-code').value = employee.employee_code || '';
            document.getElementById('employee-email').value = employee.email || '';
            document.getElementById('employee-phone').value = employee.phone || '';
            document.getElementById('employee-address').value = employee.address || '';
            document.getElementById('employee-active').value = employee.is_active ? '1' : '0';
            
            skills = employee.skills || [];
            renderSkills();
            
            const modal = new bootstrap.Modal(document.getElementById('modal-employee'));
            modal.show();
        } else {
            Toast.error(response.message || 'Error al cargar empleado');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al cargar empleado');
    }
}

/**
 * Guarda un empleado
 */
async function saveEmployee() {
    const form = document.getElementById('form-employee');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const data = {
        first_name: document.getElementById('employee-first-name').value.trim(),
        last_name: document.getElementById('employee-last-name').value.trim(),
        employee_code: document.getElementById('employee-code').value.trim() || null,
        email: document.getElementById('employee-email').value.trim() || null,
        phone: document.getElementById('employee-phone').value.trim() || null,
        address: document.getElementById('employee-address').value.trim() || null,
        is_active: document.getElementById('employee-active').value === '1',
        skills: skills
    };
    
    try {
        let response;
        
        if (currentEmployeeId) {
            response = await API.put(`/employees/${currentEmployeeId}`, data);
        } else {
            response = await API.post('/employees', data);
        }
        
        if (response.success) {
            Toast.success(response.message || 'Empleado guardado correctamente');
            bootstrap.Modal.getInstance(document.getElementById('modal-employee')).hide();
            loadEmployees();
        } else {
            Toast.error(response.message || 'Error al guardar empleado');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al guardar empleado');
    }
}

/**
 * Elimina un empleado
 */
async function deleteEmployee(id) {
    // Usar Modal.confirm si está disponible, sino usar confirm nativo como fallback
    let confirmed = false;
    if (typeof Modal !== "undefined") {
        confirmed = await Modal.confirm({
            title: 'Eliminar Empleado',
            message: '¿Estás seguro de que deseas eliminar este empleado?'
        });
    } else {
        confirmed = confirm('¿Estás seguro de que deseas eliminar este empleado?');
    }
    
    if (!confirmed) {
        return;
    }
    
    try {
        const response = await API.delete(`/employees/${id}`);
        
        if (response.success) {
            Toast.success(response.message || 'Empleado eliminado correctamente');
            loadEmployees();
        } else {
            Toast.error(response.message || 'Error al eliminar empleado');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al eliminar empleado');
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
 * Muestra los documentos de un empleado
 */
async function showDocuments(id) {
    try {
        const response = await API.get(`/employees/${id}`);
        
        if (response.success) {
            currentEmployeeId = id;
            document.getElementById('documents-employee-id').value = id;
            renderDocuments(response.data.documents || []);
            
            const modal = new bootstrap.Modal(document.getElementById('modal-documents'));
            modal.show();
        } else {
            Toast.error(response.message || 'Error al cargar documentos');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al cargar documentos');
    }
}

/**
 * Renderiza los documentos
 */
function renderDocuments(documents) {
    const container = document.getElementById('documents-list');
    
    if (documents.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay documentos</p>';
        return;
    }
    
    container.innerHTML = documents.map((doc, index) => `
        <div class="card-modern mb-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">${doc.name || doc.original_filename}</h6>
                    <small class="text-muted">${doc.uploaded_at ? new Date(doc.uploaded_at).toLocaleDateString() : ''}</small>
                </div>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteDocument(${index})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

/**
 * Sube un documento
 */
async function uploadDocument(e) {
    e.preventDefault();
    
    const employeeId = document.getElementById('documents-employee-id').value;
    const name = document.getElementById('document-name').value.trim();
    const file = document.getElementById('document-file').files[0];
    
    if (!file) {
        Toast.error('Selecciona un archivo');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('name', name);
    
    try {
        // Usar API client si está disponible (ahora soporta FormData)
        let data;
        if (typeof API !== "undefined") {
            const response = await API.post(`/employees/${employeeId}/documents`, formData);
            data = response;
        } else {
            // Fallback a fetch directo
            const token = localStorage.getItem('access_token');
            const tenantId = localStorage.getItem('tenant_id');
            
            const response = await fetch(`${window.BASE_URL || ''}/api/v1/employees/${employeeId}/documents`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'X-Tenant-ID': tenantId
                    // No establecer Content-Type para FormData
                },
                body: formData
            });
            
            data = await response.json();
        }
        
        if (data.success) {
            Toast.success(data.message || 'Documento subido correctamente');
            document.getElementById('form-upload-document').reset();
            showDocuments(employeeId);
        } else {
            Toast.error(data.message || 'Error al subir documento');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al subir documento');
    }
}

/**
 * Elimina un documento
 */
async function deleteDocument(index) {
    const employeeId = document.getElementById('documents-employee-id').value;
    
    // Usar Modal.confirm si está disponible, sino usar confirm nativo como fallback
    let confirmed = false;
    if (typeof Modal !== "undefined") {
        confirmed = await Modal.confirm({
            title: 'Eliminar Documento',
            message: '¿Estás seguro de que deseas eliminar este documento?'
        });
    } else {
        confirmed = confirm('¿Estás seguro de que deseas eliminar este documento?');
    }
    
    if (!confirmed) {
        return;
    }
    
    try {
        const response = await API.delete(`/employees/${employeeId}/documents/${index}`);
        
        if (response.success) {
            Toast.success('Documento eliminado correctamente');
            showDocuments(employeeId);
        } else {
            Toast.error(response.message || 'Error al eliminar documento');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error al eliminar documento');
    }
}

/**
 * Resetea los filtros
 */
function resetFilters() {
    document.getElementById('filter-search').value = '';
    document.getElementById('filter-active').value = '1';
    loadEmployees();
}

/**
 * Resetea el formulario
 */
function resetForm() {
    document.getElementById('form-employee').reset();
    currentEmployeeId = null;
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

