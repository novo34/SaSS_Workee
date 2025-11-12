/**
 * JavaScript para Gestión de Solicitudes de Personal (REFACTORIZADO)
 * 
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications
 * - Modal component
 */

let currentPage = 1;
let currentFilters = {};

/**
 * Espera a que los componentes estén cargados
 */
async function waitForComponents() {
    const maxAttempts = 50; // 5 segundos máximo
    let attempts = 0;
    
    while (attempts < maxAttempts) {
        if (typeof API !== 'undefined' && typeof Toast !== 'undefined' && typeof Modal !== 'undefined') {
            return true;
        }
        await new Promise(resolve => setTimeout(resolve, 100));
        attempts++;
    }
    
    console.error('Componentes no cargados después de 5 segundos. Asegúrate de incluir components-loader.js');
    return false;
}

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
    
    loadRequests();
    
    // Event listeners
    const btnNew = document.getElementById('btn-new-request');
    const btnFilter = document.getElementById('btn-filter');
    const btnClearFilters = document.getElementById('btn-clear-filters');
    const formNewRequest = document.getElementById('form-new-request');
    const btnNext = document.getElementById('btn-next-step');
    const btnPrev = document.getElementById('btn-prev-step');
    const modal = document.getElementById('modal-new-request');
    
    if (btnNew) btnNew.addEventListener('click', openNewRequestModal);
    if (btnFilter) btnFilter.addEventListener('click', applyFilters);
    if (btnClearFilters) btnClearFilters.addEventListener('click', clearFilters);
    if (formNewRequest) formNewRequest.addEventListener('submit', handleSubmit);
    if (btnNext) btnNext.addEventListener('click', nextStep);
    if (btnPrev) btnPrev.addEventListener('click', prevStep);
    if (modal) modal.addEventListener('hidden.bs.modal', resetWizard);
});

async function loadRequests(page = 1, filters = {}) {
    // Verificar que API esté disponible
    if (typeof API === 'undefined') {
        console.error('API no está disponible. Esperando componentes...');
        const componentsReady = await waitForComponents();
        if (!componentsReady) {
            const container = document.getElementById('requests-container');
            if (container) {
                container.innerHTML = '<div class="alert alert-danger">Error: No se pudieron cargar los componentes necesarios. Por favor, recarga la página.</div>';
            }
            return;
        }
    }
    
    currentPage = page;
    currentFilters = filters;
    
    const container = document.getElementById('requests-container');
    if (!container) return;
    
    // Mostrar loading spinner
    if (window.loadingSpinner) {
        window.loadingSpinner.show('Cargando solicitudes...');
    } else {
        // LoadingSpinner se maneja en loadRequests()
    }
    
    try {
        const params = {
            page: page,
            page_size: 20,
            ...filters
        };
        
        const response = await API.get('/staffing-requests', params);
        
        if (response && response.success) {
            renderRequests(response.data || [], response);
            renderPagination(response);
            
            // Ocultar loading spinner
            if (window.loadingSpinner) {
                window.loadingSpinner.hide();
            }
        } else {
            showError(response?.message || 'Error al cargar solicitudes');
            if (typeof Toast !== 'undefined') {
                Toast.error(response?.message || 'Error al cargar solicitudes');
            }
            
            // Ocultar loading spinner
            if (window.loadingSpinner) {
                window.loadingSpinner.hide();
            }
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Error de conexión. Por favor, verifica la consola para más detalles.');
        if (typeof Toast !== 'undefined') {
            Toast.error('Error de conexión');
        }
    }
}

function renderRequests(requests, meta) {
    const container = document.getElementById('requests-container');
    if (!container) return;
    
    if (requests.length === 0) {
        // Usar EmptyState component si está disponible
        if (typeof EmptyState !== 'undefined') {
            const emptyState = new EmptyState(container, {
                icon: '📭',
                title: 'No hay solicitudes',
                message: 'No se encontraron solicitudes de personal.',
                action: {
                    label: 'Crear Primera Solicitud',
                    callback: () => {
                        const btn = document.getElementById('btn-new-request');
                        if (btn) btn.click();
                    }
                }
            });
            emptyState.show();
        } else {
            container.innerHTML = `
                <div class="text-center py-5">
                    <p class="text-muted">No hay solicitudes</p>
                    <button class="btn-modern btn-modern--primary" onclick="document.getElementById('btn-new-request').click()">
                        Crear Primera Solicitud
                    </button>
                </div>
            `;
        }
        return;
    }
    
    container.innerHTML = requests.map(request => `
        <div class="card-modern card-modern--clickable mb-3" onclick="viewRequest(${request.id})">
            <div class="card-modern__body">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h5 class="card-title mb-2">
                            Solicitud #${request.id}
                            ${getStatusBadge(request.status)}
                        </h5>
                        <p class="card-text mb-1">
                            <strong>Fecha:</strong> ${formatDateTime(request.date_from)} - ${formatDateTime(request.date_to)}
                        </p>
                        ${request.location ? `<p class="card-text mb-1"><strong>Ubicación:</strong> ${escapeHtml(request.location)}</p>` : ''}
                        <p class="card-text mb-1">
                            <strong>Cantidad:</strong> ${request.qty_needed} trabajador(es)
                        </p>
                        ${request.project_name ? `<p class="card-text mb-0"><strong>Proyecto:</strong> ${escapeHtml(request.project_name)}</p>` : ''}
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">${formatDate(request.created_at)}</small>
                        <button class="btn-modern btn-modern--sm btn-modern--primary mt-2" onclick="event.stopPropagation(); sendToAgencies(${request.id})" aria-label="Enviar a temporeras">
                            <svg class="btn-modern__icon btn-modern__icon--left" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            Enviar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function getStatusBadge(status) {
    const badges = {
        'open': '<span class="badge-modern badge-modern--warning">Abierta</span>',
        'partially_filled': '<span class="badge-modern badge-modern--info">Parcial</span>',
        'filled': '<span class="badge-modern badge-modern--success">Cubierta</span>',
        'canceled': '<span class="badge-modern badge-modern--secondary">Cancelada</span>'
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
    
    // Botón anterior
    html += `
        <li class="page-item ${meta.page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadRequests(${meta.page - 1}, currentFilters); return false;">Anterior</a>
        </li>
    `;
    
    // Páginas
    for (let i = 1; i <= meta.total_pages; i++) {
        if (i === 1 || i === meta.total_pages || (i >= meta.page - 2 && i <= meta.page + 2)) {
            html += `
                <li class="page-item ${i === meta.page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadRequests(${i}, currentFilters); return false;">${i}</a>
                </li>
            `;
        } else if (i === meta.page - 3 || i === meta.page + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Botón siguiente
    html += `
        <li class="page-item ${meta.page === meta.total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadRequests(${meta.page + 1}, currentFilters); return false;">Siguiente</a>
        </li>
    `;
    
    pagination.innerHTML = html;
}

function applyFilters() {
    const status = document.getElementById('filter-status')?.value;
    const search = document.getElementById('filter-search')?.value;
    
    const filters = {};
    if (status) filters.status = status;
    if (search) filters.q = search;
    
    loadRequests(1, filters);
}

function clearFilters() {
    const filterStatus = document.getElementById('filter-status');
    const filterSearch = document.getElementById('filter-search');
    
    if (filterStatus) filterStatus.value = '';
    if (filterSearch) filterSearch.value = '';
    
    loadRequests(1, {});
}

function openNewRequestModal() {
    const modalEl = document.getElementById('modal-new-request');
    if (!modalEl) return;
    
    const bootstrapModal = new bootstrap.Modal(modalEl);
    bootstrapModal.show();
    loadAgencies();
}

let currentStep = 1;
const totalSteps = 3;

function nextStep() {
    if (currentStep < totalSteps) {
        // Validar paso actual
        if (!validateStep(currentStep)) {
            return;
        }
        
        const currentStepEl = document.getElementById(`step-${currentStep}`);
        if (currentStepEl) currentStepEl.classList.add('d-none');
        currentStep++;
        const nextStepEl = document.getElementById(`step-${currentStep}`);
        if (nextStepEl) nextStepEl.classList.remove('d-none');
        
        updateStepButtons();
    }
}

function prevStep() {
    if (currentStep > 1) {
        const currentStepEl = document.getElementById(`step-${currentStep}`);
        if (currentStepEl) currentStepEl.classList.add('d-none');
        currentStep--;
        const prevStepEl = document.getElementById(`step-${currentStep}`);
        if (prevStepEl) prevStepEl.classList.remove('d-none');
        
        updateStepButtons();
    }
}

function updateStepButtons() {
    const prevBtn = document.getElementById('btn-prev-step');
    const nextBtn = document.getElementById('btn-next-step');
    const submitBtn = document.getElementById('btn-submit');
    
    if (prevBtn) {
        if (currentStep > 1) {
            prevBtn.classList.remove('d-none');
        } else {
            prevBtn.classList.add('d-none');
        }
    }
    
    if (currentStep === totalSteps) {
        if (nextBtn) nextBtn.classList.add('d-none');
        if (submitBtn) submitBtn.classList.remove('d-none');
    } else {
        if (nextBtn) nextBtn.classList.remove('d-none');
        if (submitBtn) submitBtn.classList.add('d-none');
    }
}

function validateStep(step) {
    if (step === 1) {
        const dateFrom = document.getElementById('date-from')?.value;
        const dateTo = document.getElementById('date-to')?.value;
        
        if (!dateFrom || !dateTo) {
            Toast.error('Las fechas son requeridas');
            return false;
        }
        
        if (new Date(dateFrom) >= new Date(dateTo)) {
            Toast.error('La fecha de inicio debe ser anterior a la fecha de fin');
            return false;
        }
        
        return true;
    }
    
    if (step === 2) {
        const qty = document.getElementById('qty-needed')?.value;
        if (!qty || parseInt(qty) <= 0) {
            Toast.error('La cantidad debe ser mayor a 0');
            return false;
        }
        return true;
    }
    
    return true;
}

async function loadAgencies() {
    try {
        const response = await API.get('/agency-company-links');
        
        if (response && response.success) {
            const container = document.getElementById('agencies-list');
            if (!container) return;
            
            if (response.data.length === 0) {
                container.innerHTML = '<div class="text-muted">No hay temporeras vinculadas</div>';
                return;
            }
            
            container.innerHTML = response.data
                .filter(link => link.status === 'active' && link.agency_name)
                .map(link => `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="${link.tenant_agency_id}" id="agency-${link.tenant_agency_id}" name="agencies">
                        <label class="form-check-label" for="agency-${link.tenant_agency_id}">
                            ${escapeHtml(link.agency_name)}
                        </label>
                    </div>
                `).join('');
        }
    } catch (error) {
        console.error('Error cargando temporeras:', error);
        const container = document.getElementById('agencies-list');
        if (container) {
            container.innerHTML = '<div class="text-danger">Error al cargar temporeras</div>';
        }
        Toast.error('Error al cargar temporeras');
    }
}

async function handleSubmit(e) {
    e.preventDefault();
    
    if (!validateStep(currentStep)) {
        return;
    }
    
    const formData = {
        date_from: document.getElementById('date-from')?.value,
        date_to: document.getElementById('date-to')?.value,
        location: document.getElementById('location')?.value || null,
        latitude: document.getElementById('latitude')?.value || null,
        longitude: document.getElementById('longitude')?.value || null,
        qty_needed: parseInt(document.getElementById('qty-needed')?.value || '0'),
        project_id: document.getElementById('project-id')?.value || null,
        requirements: {
            skills: document.getElementById('skills')?.value ? document.getElementById('skills').value.split(',').map(s => s.trim()) : [],
            languages: document.getElementById('languages')?.value ? document.getElementById('languages').value.split(',').map(l => l.trim()) : [],
            other: document.getElementById('other-requirements')?.value || null
        },
        agency_ids: Array.from(document.querySelectorAll('input[name="agencies"]:checked')).map(cb => parseInt(cb.value))
    };
    
    try {
        const response = await API.post('/staffing-requests', formData);
        
        if (response && response.success) {
            const modalEl = document.getElementById('modal-new-request');
            if (modalEl) {
                const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
                if (bootstrapModal) bootstrapModal.hide();
            }
            loadRequests();
            Toast.success('Solicitud creada correctamente');
        } else {
            Toast.error(response?.message || 'Error al crear solicitud');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error de conexión');
    }
}

async function sendToAgencies(requestId) {
    // Usar Modal para obtener IDs de temporeras
    const agencyIdsInput = await Modal.alert({
        title: 'Enviar a Temporeras',
        message: `
            <label for="agency-ids-input" class="form-label">Ingrese los IDs de las temporeras separados por comas:</label>
            <input type="text" class="form-control" id="agency-ids-input" placeholder="1, 2, 3">
        `,
        buttonLabel: 'Enviar'
    });
    
    // Por ahora, usar prompt como fallback (en una versión mejorada, el modal podría retornar el valor)
    const agencyIds = prompt('Ingrese los IDs de las temporeras separados por comas:');
    if (!agencyIds) return;
    
    const ids = agencyIds.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id));
    
    if (ids.length === 0) {
        Toast.error('Debe ingresar al menos un ID válido');
        return;
    }
    
    try {
        const response = await API.post(`/staffing-requests/${requestId}/send`, { agency_ids: ids });
        
        if (response && response.success) {
            Toast.success(`Solicitud enviada a ${response.data?.agencies_sent || ids.length} temporera(s)`);
            loadRequests(currentPage, currentFilters);
        } else {
            Toast.error(response?.message || 'Error al enviar solicitud');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error de conexión');
    }
}

function viewRequest(id) {
    window.location.href = `/staffing-requests/${id}`;
}

function resetWizard() {
    currentStep = 1;
    const form = document.getElementById('form-new-request');
    if (form) form.reset();
    
    document.querySelectorAll('.step').forEach((step, index) => {
        step.classList.toggle('d-none', index !== 0);
    });
    updateStepButtons();
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
    const container = document.getElementById('requests-container');
    if (!container) return;
    
    container.innerHTML = `
        <div class="alert alert-danger">
            <strong>Error:</strong> ${escapeHtml(message)}
        </div>
    `;
}

// Exponer funciones globalmente para onclick
window.loadRequests = loadRequests;
window.viewRequest = viewRequest;
window.sendToAgencies = sendToAgencies;
