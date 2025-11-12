/**
 * JavaScript para Gestión de Propuestas (REFACTORIZADO)
 * 
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications
 * - Modal component
 */

// Asegurar que los componentes estén cargados
if (typeof API === 'undefined' || typeof Toast === 'undefined' || typeof Modal === 'undefined') {
    console.error('Componentes no cargados. Asegúrate de incluir components-loader.js');
}

let currentPage = 1;
let currentFilters = {};

document.addEventListener('DOMContentLoaded', async () => {
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
    
    loadProposals();
    
    // Event listeners
    const btnNew = document.getElementById('btn-new-proposal');
    const btnFilter = document.getElementById('btn-filter');
    const btnClearFilters = document.getElementById('btn-clear-filters');
    const formNewProposal = document.getElementById('form-new-proposal');
    const useAutoMatching = document.getElementById('use-auto-matching');
    
    if (btnNew) btnNew.addEventListener('click', openNewProposalModal);
    if (btnFilter) btnFilter.addEventListener('click', applyFilters);
    if (btnClearFilters) btnClearFilters.addEventListener('click', clearFilters);
    if (formNewProposal) formNewProposal.addEventListener('submit', handleSubmit);
    if (useAutoMatching) useAutoMatching.addEventListener('change', toggleMatchingMode);
});

async function loadProposals(page = 1, filters = {}) {
    currentPage = page;
    currentFilters = filters;
    
    const container = document.getElementById('proposals-container');
    if (!container) return;
    
    // Mostrar loading spinner
    if (window.loadingSpinner) {
        window.loadingSpinner.show('Cargando propuestas...');
    } else {
        container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
    }
    
    try {
        const params = {
            page: page,
            page_size: 20,
            ...filters
        };
        
        const response = await API.get('/staffing-proposals', params);
        
        if (response && response.success) {
            renderProposals(response.data || [], response);
            renderPagination(response);
            
            // Ocultar loading spinner
            if (window.loadingSpinner) {
                window.loadingSpinner.hide();
            }
        } else {
            showError(response?.message || 'Error al cargar propuestas');
            Toast.error(response?.message || 'Error al cargar propuestas');
            
            // Ocultar loading spinner
            if (window.loadingSpinner) {
                window.loadingSpinner.hide();
            }
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Error de conexión');
        Toast.error('Error de conexión');
        
        // Ocultar loading spinner
        if (window.loadingSpinner) {
            window.loadingSpinner.hide();
        }
    }
}

function renderProposals(proposals, meta) {
    const container = document.getElementById('proposals-container');
    if (!container) return;
    
    // Ocultar loading spinner
    if (window.loadingSpinner) {
        window.loadingSpinner.hide();
    }
    
    if (proposals.length === 0) {
        // Usar EmptyState component si está disponible
        if (typeof EmptyState !== 'undefined') {
            const emptyState = new EmptyState(container, {
                icon: '📭',
                title: 'No hay propuestas',
                message: 'No se encontraron propuestas de candidatos.'
            });
            emptyState.show();
        } else {
            container.innerHTML = `
                <div class="text-center py-5">
                    <p class="text-muted">No hay propuestas</p>
                </div>
            `;
        }
        return;
    }
    
    container.innerHTML = proposals.map(proposal => {
        const proposalData = proposal.proposal || {};
        const candidates = proposalData.candidates || [];
        
        return `
        <div class="card-modern mb-3">
            <div class="card-modern__body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="flex-grow-1">
                        <h5 class="card-title mb-2">
                            Propuesta #${proposal.id}
                            ${getStatusBadge(proposal.status)}
                        </h5>
                        <p class="card-text mb-1">
                            <strong>Solicitud:</strong> #${proposal.request_id}
                        </p>
                        <p class="card-text mb-1">
                            <strong>Fecha:</strong> ${formatDateTime(proposal.date_from)} - ${formatDateTime(proposal.date_to)}
                        </p>
                        ${proposal.location ? `<p class="card-text mb-1"><strong>Ubicación:</strong> ${escapeHtml(proposal.location)}</p>` : ''}
                        ${proposal.company_name ? `<p class="card-text mb-1"><strong>Empresa:</strong> ${escapeHtml(proposal.company_name)}</p>` : ''}
                        ${proposal.agency_name ? `<p class="card-text mb-1"><strong>Temporera:</strong> ${escapeHtml(proposal.agency_name)}</p>` : ''}
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">${formatDate(proposal.created_at)}</small>
                        ${proposal.status === 'pending_company' && !proposal.agency_name ? `
                            <div class="d-flex gap-2 mt-2">
                                <button class="btn-modern btn-modern--sm btn-modern--success" onclick="acceptProposal(${proposal.id})" aria-label="Aceptar propuesta">
                                    <svg class="btn-modern__icon btn-modern__icon--left" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Aceptar
                                </button>
                                <button class="btn-modern btn-modern--sm btn-modern--danger" onclick="rejectProposal(${proposal.id})" aria-label="Rechazar propuesta">
                                    <svg class="btn-modern__icon btn-modern__icon--left" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Rechazar
                                </button>
                                <button class="btn-modern btn-modern--sm btn-modern--warning" onclick="changeProposal(${proposal.id})" aria-label="Solicitar cambios">
                                    <svg class="btn-modern__icon btn-modern__icon--left" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Cambios
                                </button>
                            </div>
                        ` : ''}
                    </div>
                </div>
                
                ${candidates.length > 0 ? `
                    <div class="mt-3">
                        <h6>Candidatos (${candidates.length})</h6>
                        <div class="row g-2">
                            ${candidates.map(candidate => `
                                <div class="col-12 col-md-6">
                                    <div class="candidate-item">
                                        <strong>${escapeHtml(candidate.first_name || '')} ${escapeHtml(candidate.last_name || '')}</strong>
                                        ${candidate.worker_code ? `<small class="text-muted d-block">Código: ${escapeHtml(candidate.worker_code)}</small>` : ''}
                                        ${candidate.score !== undefined ? `
                                            <div class="mt-1 d-flex gap-2 flex-wrap">
                                                <span class="badge-modern badge-modern--info">Score: ${(candidate.score * 100).toFixed(1)}%</span>
                                                ${candidate.details?.distance_km ? `<span class="badge-modern badge-modern--secondary">${candidate.details.distance_km} km</span>` : ''}
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
    }).join('');
}

function getStatusBadge(status) {
    const badges = {
        'pending_company': '<span class="badge-modern badge-modern--warning">Pendiente</span>',
        'accepted': '<span class="badge-modern badge-modern--success">Aceptada</span>',
        'rejected': '<span class="badge-modern badge-modern--danger">Rechazada</span>',
        'changed': '<span class="badge-modern badge-modern--info">Con cambios</span>'
    };
    return badges[status] || '';
}

function renderPagination(meta) {
    const container = document.getElementById('pagination-container');
    const pagination = document.getElementById('pagination');
    
    if (!container || !pagination) return;
    
    if (meta.total_pages <= 1) {
        container.classList.add('d-none');
        return;
    }
    
    container.classList.remove('d-none');
    
    let html = '';
    
    html += `
        <li class="page-item ${meta.page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadProposals(${meta.page - 1}, currentFilters); return false;">Anterior</a>
        </li>
    `;
    
    for (let i = 1; i <= meta.total_pages; i++) {
        if (i === 1 || i === meta.total_pages || (i >= meta.page - 2 && i <= meta.page + 2)) {
            html += `
                <li class="page-item ${i === meta.page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadProposals(${i}, currentFilters); return false;">${i}</a>
                </li>
            `;
        } else if (i === meta.page - 3 || i === meta.page + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    html += `
        <li class="page-item ${meta.page === meta.total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadProposals(${meta.page + 1}, currentFilters); return false;">Siguiente</a>
        </li>
    `;
    
    pagination.innerHTML = html;
}

function applyFilters() {
    const status = document.getElementById('filter-status')?.value;
    const requestId = document.getElementById('filter-request-id')?.value;
    
    const filters = {};
    if (status) filters.status = status;
    if (requestId) filters.request_id = requestId;
    
    loadProposals(1, filters);
}

function clearFilters() {
    const filterStatus = document.getElementById('filter-status');
    const filterRequestId = document.getElementById('filter-request-id');
    
    if (filterStatus) filterStatus.value = '';
    if (filterRequestId) filterRequestId.value = '';
    
    loadProposals(1, {});
}

function openNewProposalModal() {
    const modalEl = document.getElementById('modal-new-proposal');
    if (!modalEl) return;
    
    const bootstrapModal = new bootstrap.Modal(modalEl);
    bootstrapModal.show();
    loadRequests();
}

async function loadRequests() {
    try {
        const response = await API.get('/staffing-requests', { status: 'sent', page_size: 100 });
        
        if (response && response.success) {
            const select = document.getElementById('request-id');
            if (!select) return;
            
            select.innerHTML = '<option value="">Seleccionar solicitud...</option>';
            
            (response.data || []).forEach(request => {
                const option = document.createElement('option');
                option.value = request.id;
                option.textContent = `#${request.id} - ${formatDateTime(request.date_from)} (${request.qty_needed} trabajadores)`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error cargando solicitudes:', error);
        Toast.error('Error al cargar solicitudes');
    }
}

function toggleMatchingMode() {
    const useAuto = document.getElementById('use-auto-matching')?.checked;
    const autoOptions = document.getElementById('auto-matching-options');
    const manualProposal = document.getElementById('manual-proposal');
    
    if (autoOptions) autoOptions.style.display = useAuto ? 'block' : 'none';
    if (manualProposal) manualProposal.classList.toggle('d-none', useAuto);
}

async function handleSubmit(e) {
    e.preventDefault();
    
    const requestId = document.getElementById('request-id')?.value;
    if (!requestId) {
        Toast.error('Selecciona una solicitud');
        return;
    }
    
    const useAutoMatching = document.getElementById('use-auto-matching')?.checked;
    
    const formData = {
        request_id: parseInt(requestId),
        use_auto_matching: useAutoMatching
    };
    
    if (useAutoMatching) {
        formData.matching_options = {
            max_distance_km: parseInt(document.getElementById('max-distance')?.value || '50')
        };
    } else {
        // Propuesta manual (simplificado - en producción cargar trabajadores)
        formData.proposal = {
            candidates: []
        };
    }
    
    try {
        const response = await API.post('/staffing-proposals', formData);
        
        if (response && response.success) {
            const modalEl = document.getElementById('modal-new-proposal');
            if (modalEl) {
                const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
                if (bootstrapModal) bootstrapModal.hide();
            }
            loadProposals();
            Toast.success('Propuesta creada correctamente');
        } else {
            Toast.error(response?.message || 'Error al crear propuesta');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error de conexión');
    }
}

async function acceptProposal(id) {
    const confirmed = await Modal.confirm({
        title: 'Aceptar Propuesta',
        message: '¿Aceptar esta propuesta?',
        confirmLabel: 'Aceptar',
        cancelLabel: 'Cancelar'
    });
    
    if (!confirmed) return;
    
    await updateProposalStatus(id, 'accept');
}

async function rejectProposal(id) {
    const confirmed = await Modal.confirm({
        title: 'Rechazar Propuesta',
        message: '¿Rechazar esta propuesta?',
        confirmLabel: 'Rechazar',
        cancelLabel: 'Cancelar'
    });
    
    if (!confirmed) return;
    
    await updateProposalStatus(id, 'reject');
}

async function changeProposal(id) {
    const confirmed = await Modal.confirm({
        title: 'Solicitar Cambios',
        message: '¿Solicitar cambios a esta propuesta?',
        confirmLabel: 'Solicitar Cambios',
        cancelLabel: 'Cancelar'
    });
    
    if (!confirmed) return;
    
    await updateProposalStatus(id, 'change');
}

async function updateProposalStatus(id, action) {
    try {
        const response = await API.put(`/staffing-proposals/${id}/${action}`);
        
        if (response && response.success) {
            loadProposals(currentPage, currentFilters);
            Toast.success(response?.message || 'Propuesta actualizada correctamente');
        } else {
            Toast.error(response?.message || 'Error al actualizar propuesta');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error de conexión');
    }
}

function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showError(message) {
    const container = document.getElementById('proposals-container');
    if (!container) return;
    
    container.innerHTML = `
        <div class="alert alert-danger">
            <strong>Error:</strong> ${escapeHtml(message)}
        </div>
    `;
}

// Exponer funciones globalmente
window.loadProposals = loadProposals;
window.acceptProposal = acceptProposal;
window.rejectProposal = rejectProposal;
window.changeProposal = changeProposal;
