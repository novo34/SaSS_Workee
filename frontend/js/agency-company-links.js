/**
 * JavaScript para Gestión de Relaciones Empresa ↔ Temporera (REFACTORIZADO CON DATATABLE)
 * Mobile-first, modular
 * 
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications
 * - Modal component (para confirmaciones)
 * - DataTable component (Fase 4)
 */

let dataTable = null;
let currentFilters = {};

/**
 * Espera a que los componentes estén cargados
 */
async function waitForComponents() {
    const maxAttempts = 50; // 5 segundos máximo
    let attempts = 0;
    
    while (attempts < maxAttempts) {
        if (typeof API !== 'undefined' && typeof Toast !== 'undefined' && typeof Modal !== 'undefined' && typeof DataTable !== 'undefined') {
            return true;
        }
        await new Promise(resolve => setTimeout(resolve, 100));
        attempts++;
    }
    
    console.error('Componentes no cargados después de 5 segundos. Asegúrate de incluir components-loader.js');
    return false;
}

// Inicialización
document.addEventListener('DOMContentLoaded', async () => {
    // Esperar a que los componentes estén cargados
    const componentsReady = await waitForComponents();
    if (!componentsReady) {
        console.error('No se pueden cargar los componentes. Algunas funcionalidades pueden no estar disponibles.');
    }
    
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    initializeDataTable();
    setupEventListeners();
    
    // Cargar tenants cuando se abre el modal (mejor UX y evita problemas de timing)
    const modal = document.getElementById('modalCreateLink');
    if (modal) {
        modal.addEventListener('show.bs.modal', () => {
            loadTenantsForSelect();
        });
    }
});

/**
 * Configura event listeners
 */
function setupEventListeners() {
    const btnFilter = document.getElementById('btn-filter');
    const btnSubmitLink = document.getElementById('btn-submit-link');
    const filterSearch = document.getElementById('filter-search');
    
    if (btnFilter) btnFilter.addEventListener('click', applyFilters);
    if (btnSubmitLink) btnSubmitLink.addEventListener('click', createLink);
    if (filterSearch) {
        filterSearch.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    }
}

/**
 * Inicializa DataTable
 */
function initializeDataTable() {
    // Verificar que DataTable esté disponible
    if (typeof DataTable === 'undefined') {
        console.error('DataTable no está disponible. Esperando componentes...');
        // No podemos esperar aquí porque esta función no es async
        // La espera ya se hizo en DOMContentLoaded
        return;
    }
    
    const container = document.getElementById('links-container');
    if (!container) {
        // Si no existe links-container, usar el contenedor de la tabla existente
        const tableWrapper = document.querySelector('.modern-table-wrapper');
        if (tableWrapper) {
            // Crear nuevo contenedor
            const newContainer = document.createElement('div');
            newContainer.id = 'links-container';
            tableWrapper.parentNode.replaceChild(newContainer, tableWrapper);
            dataTable = new DataTable(newContainer, getDataTableOptions());
        }
        return;
    }
    
    dataTable = new DataTable(container, getDataTableOptions());
}

/**
 * Obtiene las opciones de DataTable
 */
function getDataTableOptions() {
    return {
        columns: [
            {
                key: 'company',
                label: 'Empresa',
                sortable: true,
                render: (row) => {
                    return `
                        <div class="d-flex align-items-center">
                            <div class="table-avatar">${escapeHtml((row.company_name || 'E').charAt(0).toUpperCase())}</div>
                            <span>${escapeHtml(row.company_name || 'N/A')}</span>
                        </div>
                    `;
                }
            },
            {
                key: 'agency',
                label: 'Temporera',
                sortable: true,
                render: (row) => {
                    return `
                        <div class="d-flex align-items-center">
                            <div class="table-avatar">${escapeHtml((row.agency_name || 'T').charAt(0).toUpperCase())}</div>
                            <span>${escapeHtml(row.agency_name || 'N/A')}</span>
                        </div>
                    `;
                }
            },
            {
                key: 'status',
                label: 'Estado',
                sortable: true,
                render: (row) => {
                    return getStatusBadge(row.status);
                }
            },
            {
                key: 'created_at',
                label: 'Fecha Creación',
                sortable: true,
                render: (row) => {
                    return formatDate(row.created_at);
                }
            },
            {
                key: 'actions',
                label: 'Acciones',
                sortable: false,
                render: (row) => {
                    return `
                        <div class="table-actions">
                            <button class="action-btn action-btn-view" type="button" onclick="viewLink(${row.id}); return false;" aria-label="Ver" title="Ver">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 3C4.5 3 1.73 5.11 0 8c1.73 2.89 4.5 5 8 5s6.27-2.11 8-5c-1.73-2.89-4.5-5-8-5zM8 12.5c-2.48 0-4.5-2.02-4.5-4.5S5.52 3.5 8 3.5s4.5 2.02 4.5 4.5S10.48 12.5 8 12.5z"/>
                                    <path d="M8 6.5c-1.38 0-2.5 1.12-2.5 2.5s1.12 2.5 2.5 2.5 2.5-1.12 2.5-2.5S9.38 6.5 8 6.5z"/>
                                </svg>
                            </button>
                            <button class="action-btn action-btn-edit" type="button" onclick="editLink(${row.id}); return false;" aria-label="Editar" title="Editar">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M11.5 2.5a2.121 2.121 0 0 1 3 3L6.5 13.5l-4 1 1-4L11.5 2.5zM12.5 3.5L3.5 12.5l-1 1 1-1 9-9 1 1z"/>
                                </svg>
                            </button>
                            <button class="action-btn action-btn-delete" type="button" onclick="deleteLink(${row.id}); return false;" aria-label="Eliminar" title="Eliminar">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M5.5 5.5a.5.5 0 0 1 1 0v3a.5.5 0 0 1-1 0v-3zM8 5.5a.5.5 0 0 1 1 0v3a.5.5 0 0 1-1 0v-3zM10.5 5.5a.5.5 0 0 1 1 0v3a.5.5 0 0 1-1 0v-3z"/>
                                    <path d="M2 2h12a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1zm1 4v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6H3z"/>
                                </svg>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        fetchData: async (page, pageSize, sortBy, sortDir, filters) => {
            const params = {
                page: page,
                page_size: pageSize,
                ...currentFilters
            };
            
            // Agregar ordenamiento
            if (sortBy) {
                params.sort_by = sortBy;
                params.sort_dir = sortDir || 'ASC';
            }
            
            try {
                if (typeof API === 'undefined') {
                    console.error('API no está disponible');
                    return { data: [], total: 0, page: 1, total_pages: 1 };
                }
                
                const response = await API.get('/agency-company-links', params);
                
                if (response && response.success) {
                    return {
                        data: response.data || [],
                        total: response.total || 0,
                        page: response.page || page,
                        total_pages: response.total_pages || 1
                    };
                } else {
                    if (typeof Toast !== 'undefined') {
                        Toast.error(response?.message || 'Error al cargar vínculos');
                    }
                    return { data: [], total: 0, page: 1, total_pages: 1 };
                }
            } catch (error) {
                console.error('Error:', error);
                if (typeof Toast !== 'undefined') {
                    Toast.error('Error de conexión');
                }
                return { data: [], total: 0, page: 1, total_pages: 1 };
            }
        },
        pageSize: 20,
        selectable: false,
        searchable: false, // Los filtros están en la parte superior
        emptyMessage: 'No hay vínculos creados'
    };
}

/**
 * Aplica filtros
 */
function applyFilters() {
    currentFilters = {};
    
    const filterStatus = document.getElementById('filter-status');
    const filterSearch = document.getElementById('filter-search');
    
    if (filterStatus && filterStatus.value) {
        currentFilters.status = filterStatus.value;
    }
    
    if (filterSearch) {
        const search = filterSearch.value.trim();
        if (search) {
            currentFilters.q = search;
        }
    }
    
    if (dataTable) {
        dataTable.currentPage = 1;
        dataTable.loadData();
    }
}

/**
 * Carga tenants para los selects
 */
async function loadTenantsForSelect() {
    if (typeof API === 'undefined') {
        console.error('API no está disponible');
        return;
    }
    
    try {
        const response = await API.get('/tenants', { page_size: 100 });
        
        if (response && response.success && response.data) {
            const companies = response.data.filter(t => t.org_type === 'empresa');
            const agencies = response.data.filter(t => t.org_type === 'temporera');
            
            const companySelect = document.getElementById('link-company-id');
            if (companySelect) {
                // Limpiar opciones existentes (excepto la primera)
                companySelect.innerHTML = '<option value="">Seleccionar empresa...</option>';
                companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = escapeHtml(company.name || `Empresa ${company.id}`);
                    companySelect.appendChild(option);
                });
            }
            
            const agencySelect = document.getElementById('link-agency-id');
            if (agencySelect) {
                // Limpiar opciones existentes (excepto la primera)
                agencySelect.innerHTML = '<option value="">Seleccionar temporera...</option>';
                agencies.forEach(agency => {
                    const option = document.createElement('option');
                    option.value = agency.id;
                    option.textContent = escapeHtml(agency.name || `Temporera ${agency.id}`);
                    agencySelect.appendChild(option);
                });
            }
            
            if (companies.length === 0 && agencies.length === 0) {
                if (typeof Toast !== 'undefined') {
                    Toast.warning('No se encontraron empresas o temporeras. Asegúrate de tener datos de prueba.');
                }
            }
        } else {
            if (typeof Toast !== 'undefined') {
                Toast.error(response?.message || 'Error al cargar empresas y temporeras');
            }
        }
    } catch (error) {
        console.error('Error cargando tenants:', error);
        if (typeof Toast !== 'undefined') {
            Toast.error('Error de conexión al cargar datos');
        }
    }
}

/**
 * Crea un nuevo vínculo
 */
async function createLink() {
    if (typeof API === 'undefined' || typeof Toast === 'undefined') {
        console.error('API o Toast no están disponibles');
        return;
    }
    
    const linkCompanyId = document.getElementById('link-company-id');
    const linkAgencyId = document.getElementById('link-agency-id');
    const linkStatus = document.getElementById('link-status');
    
    const companyId = linkCompanyId?.value;
    const agencyId = linkAgencyId?.value;
    const status = linkStatus?.value;
    
    if (!companyId || !agencyId) {
        Toast.error('Debe seleccionar empresa y temporera');
        return;
    }
    
    const data = {
        tenant_company_id: parseInt(companyId),
        tenant_agency_id: parseInt(agencyId),
        status: status
    };
    
    try {
        const response = await API.post('/agency-company-links', data);
        
        if (response && response.success) {
            // Cerrar modal
            const modalEl = document.getElementById('modalCreateLink');
            if (modalEl) {
                const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
                if (bootstrapModal) bootstrapModal.hide();
            }
            
            // Limpiar formulario
            const formCreateLink = document.getElementById('form-create-link');
            if (formCreateLink) formCreateLink.reset();
            
            // Recargar tabla
            if (dataTable) dataTable.loadData();
            
            Toast.success('Vínculo creado correctamente');
        } else {
            Toast.error(response?.message || 'Error al crear vínculo');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error de conexión');
    }
}

/**
 * Edita un vínculo
 */
function editLink(id) {
    // TODO: Implementar edición
    if (typeof Toast !== 'undefined') {
        Toast.info('Edición de vínculo - Por implementar');
    }
}

/**
 * Elimina un vínculo
 */
async function deleteLink(id) {
    if (typeof Modal === 'undefined' || typeof API === 'undefined' || typeof Toast === 'undefined') {
        console.error('Modal, API o Toast no están disponibles');
        return;
    }
    
    const confirmed = await Modal.confirm({
        title: 'Eliminar Vínculo',
        message: '¿Está seguro de eliminar este vínculo?',
        confirmLabel: 'Eliminar',
        cancelLabel: 'Cancelar'
    });
    
    if (!confirmed) return;
    
    try {
        const response = await API.delete(`/agency-company-links/${id}`);
        
        if (response && response.success) {
            if (dataTable) dataTable.loadData();
            Toast.success('Vínculo eliminado correctamente');
        } else {
            Toast.error(response?.message || 'Error al eliminar vínculo');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error de conexión');
    }
}

/**
 * Utilidades
 */
function getStatusColor(status) {
    const colors = {
        'active': 'success',
        'paused': 'warning',
        'blocked': 'danger'
    };
    return colors[status] || 'secondary';
}

function getStatusLabel(status) {
    const labels = {
        'active': 'Activo',
        'paused': 'Pausado',
        'blocked': 'Bloqueado'
    };
    return labels[status] || status;
}

function getStatusBadge(status) {
    const badges = {
        'active': '<span class="table-badge active">Activo</span>',
        'paused': '<span class="table-badge pending">Pausado</span>',
        'blocked': '<span class="table-badge inactive">Bloqueado</span>'
    };
    return badges[status] || `<span class="table-badge">${escapeHtml(status)}</span>`;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    }).format(date);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Ver un vínculo
 */
function viewLink(id) {
    // TODO: Implementar vista de detalle
    if (typeof Toast !== 'undefined') {
        Toast.info('Vista de vínculo - Por implementar');
    }
}

// Exponer funciones globalmente para onclick
window.viewLink = viewLink;
window.editLink = editLink;
window.deleteLink = deleteLink;
