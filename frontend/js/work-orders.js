/**
 * JavaScript para Listado de Órdenes de Trabajo (REFACTORIZADO)
 * 
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - DataTable component
 * - Toast notifications
 * - Modal component
 */

// Asegurar que los componentes estén cargados
if (typeof API === 'undefined' || typeof DataTable === 'undefined' || typeof Toast === 'undefined' || typeof Modal === 'undefined') {
    console.error('Componentes no cargados. Asegúrate de incluir components-loader.js');
}

let dataTable = null;

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    initializePage();
});

/**
 * Inicializa la página
 */
function initializePage() {
    setupEventListeners();
    initializeDataTable();
}

/**
 * Configura los event listeners
 */
function setupEventListeners() {
    // Nueva orden
    const btnNew = document.getElementById('btn-new-work-order');
    if (btnNew) {
        btnNew.addEventListener('click', () => {
            window.location.href = '/work-orders-edit';
        });
    }
    
    // Filtros
    const btnApplyFilters = document.getElementById('btn-apply-filters');
    if (btnApplyFilters) {
        btnApplyFilters.addEventListener('click', applyFilters);
    }
    
    const btnClearFilters = document.getElementById('btn-clear-filters');
    if (btnClearFilters) {
        btnClearFilters.addEventListener('click', clearFilters);
    }
    
    // Enter en búsqueda
    const filterSearch = document.getElementById('filter-search');
    if (filterSearch) {
        filterSearch.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    }
}

/**
 * Inicializa el DataTable
 */
function initializeDataTable() {
    const container = document.getElementById('work-orders-container');
    if (!container) {
        console.error('Container not found');
        return;
    }
    
    dataTable = new DataTable(container, {
        columns: [
            {
                key: 'code',
                label: 'Código',
                sortable: true,
                render: (row) => `<strong>${escapeHtml(row.code || `OT-${String(row.id).padStart(6, '0')}`)}</strong>`
            },
            {
                key: 'title',
                label: 'Título',
                sortable: true,
                render: (row) => escapeHtml(row.title || 'Sin título')
            },
            {
                key: 'client_name',
                label: 'Cliente',
                sortable: true,
                render: (row) => escapeHtml(row.client_name || 'N/A')
            },
            {
                key: 'start_planned',
                label: 'Inicio Planificado',
                sortable: true,
                render: (row) => formatDate(row.start_planned)
            },
            {
                key: 'end_planned',
                label: 'Fin Planificado',
                sortable: true,
                render: (row) => formatDate(row.end_planned)
            },
            {
                key: 'status',
                label: 'Estado',
                sortable: true,
                render: (row) => getStatusBadge(row.status)
            },
            {
                key: 'actions',
                label: 'Acciones',
                sortable: false,
                render: (row) => getActionsHtml(row)
            }
        ],
        fetchData: async (page, pageSize, sortBy, sortDir, filters) => {
            const params = {
                page,
                page_size: pageSize
            };
            
            if (sortBy) {
                params.sort_by = sortBy;
                params.sort_dir = sortDir || 'ASC';
            }
            
            // Agregar filtros
            if (filters.q) params.q = filters.q;
            if (filters.status) params.status = filters.status;
            if (filters.from) params.from = filters.from;
            if (filters.to) params.to = filters.to;
            
            const response = await API.get('/work-orders', params);
            
            if (response && response.success) {
                return {
                    data: response.data || [],
                    total: response.total || 0
                };
            } else {
                throw new Error(response?.message || 'Error al cargar órdenes de trabajo');
            }
        },
        pageSize: 20,
        searchable: false, // Usamos filtros personalizados
        emptyMessage: 'No hay órdenes de trabajo',
        emptyAction: {
            label: 'Crear Nueva Orden',
            callback: () => {
                window.location.href = '/work-orders-edit';
            }
        }
    });
}

/**
 * Aplica los filtros
 */
function applyFilters() {
    const filters = {
        status: document.getElementById('filter-status')?.value || '',
        from: document.getElementById('filter-from')?.value || '',
        to: document.getElementById('filter-to')?.value || '',
        q: document.getElementById('filter-search')?.value || ''
    };
    
    if (dataTable) {
        dataTable.setFilters(filters);
    }
}

/**
 * Limpia los filtros
 */
function clearFilters() {
    document.getElementById('filter-status').value = '';
    document.getElementById('filter-from').value = '';
    document.getElementById('filter-to').value = '';
    document.getElementById('filter-search').value = '';
    
    if (dataTable) {
        dataTable.clearFilters();
    }
}

/**
 * Obtiene el HTML de las acciones para una fila
 */
function getActionsHtml(row) {
    return `
        <div class="d-flex gap-2">
            <button class="btn-modern btn-modern--sm btn-modern--primary" onclick="viewWorkOrder(${row.id})" 
                    aria-label="Ver" title="Ver">
                <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
            </button>
            <button class="btn-modern btn-modern--sm btn-modern--secondary" onclick="editWorkOrder(${row.id})" 
                    aria-label="Editar" title="Editar">
                <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
            </button>
            <button class="btn-modern btn-modern--sm btn-modern--danger" onclick="deleteWorkOrder(${row.id})" 
                    aria-label="Eliminar" title="Eliminar">
                <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </div>
    `;
}

/**
 * Obtiene el badge de estado
 */
function getStatusBadge(status) {
    const badges = {
        draft: '<span class="badge-modern badge-modern--secondary">Borrador</span>',
        planned: '<span class="badge-modern badge-modern--info">Planificada</span>',
        in_progress: '<span class="badge-modern badge-modern--primary">En curso</span>',
        on_hold: '<span class="badge-modern badge-modern--warning">En pausa</span>',
        completed: '<span class="badge-modern badge-modern--success">Completada</span>',
        canceled: '<span class="badge-modern badge-modern--danger">Cancelada</span>'
    };
    
    return badges[status] || `<span class="badge-modern badge-modern--secondary">${escapeHtml(status)}</span>`;
}

/**
 * Formatea una fecha
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}

/**
 * Escapa HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Ver una orden de trabajo
 */
function viewWorkOrder(id) {
    window.location.href = `/work-orders-edit?id=${id}&view=true`;
}

/**
 * Editar una orden de trabajo
 */
function editWorkOrder(id) {
    window.location.href = `/work-orders-edit?id=${id}`;
}

/**
 * Eliminar una orden de trabajo
 */
async function deleteWorkOrder(id) {
    const confirmed = await Modal.confirm({
        title: 'Confirmar Eliminación',
        message: '¿Estás seguro de que deseas eliminar esta orden de trabajo? Esta acción no se puede deshacer.',
        confirmLabel: 'Eliminar',
        cancelLabel: 'Cancelar'
    });
    
    if (!confirmed) return;
    
    try {
        const response = await API.delete(`/work-orders/${id}`);
        
        if (response && response.success) {
            Toast.success('Orden de trabajo eliminada correctamente');
            if (dataTable) {
                dataTable.refresh();
            }
        } else {
            Toast.error(response?.message || 'Error al eliminar la orden de trabajo');
        }
    } catch (error) {
        console.error('Error deleting work order:', error);
        Toast.error('Error al eliminar la orden de trabajo');
    }
}

// Exportar funciones globales
window.viewWorkOrder = viewWorkOrder;
window.editWorkOrder = editWorkOrder;
window.deleteWorkOrder = deleteWorkOrder;
