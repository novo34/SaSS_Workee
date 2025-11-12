/**
 * JavaScript para Wizard de Onboarding (REFACTORIZADO)
 * Mobile-first, modular
 * 
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications
 */

// Asegurar que los componentes estén cargados
if (typeof API === 'undefined' || typeof Toast === 'undefined') {
    console.error('Componentes no cargados. Asegúrate de incluir components-loader.js');
}

// Obtener base URL desde window.BASE_URL (inyectado por index.php) o usar default
const BASE_URL = (typeof window !== 'undefined' && window.BASE_URL) || '';

let currentStep = 1;
const totalSteps = 4;
let progressBar = null;

// Plantillas de datos
const templates = {
    empresa: {
        name: 'Construcciones ABC S.A.',
        timezone: 'Europe/Zurich',
        locale: 'es',
        plan: 'pro'
    },
    temporera: {
        name: 'Agencia Trabajo XYZ',
        timezone: 'Europe/Zurich',
        locale: 'es',
        plan: 'pro'
    }
};

// Inicialización
document.addEventListener('DOMContentLoaded', async () => {
    // Esperar a que los componentes estén cargados
    await waitForComponents();
    
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    } else {
        // Fallback si UIInitializer no está disponible
        initializeTooltips();
    }
    
    initializeWizard();
    setupEventListeners();
    setupTemplates();
});

/**
 * Espera a que los componentes estén cargados
 */
async function waitForComponents() {
    const maxAttempts = 50;
    let attempts = 0;
    
    while (attempts < maxAttempts) {
        if (typeof ProgressBar !== 'undefined' && typeof Tooltip !== 'undefined') {
            return;
        }
        await new Promise(resolve => setTimeout(resolve, 100));
        attempts++;
    }
    
    console.warn('Algunos componentes no se cargaron. Continuando de todas formas...');
}

/**
 * Inicializa el wizard
 */
function initializeWizard() {
    // Inicializar ProgressBar
    const progressContainer = document.getElementById('progress-bar-container');
    if (progressContainer && typeof ProgressBar !== 'undefined') {
        progressBar = new ProgressBar(progressContainer, {
            total: totalSteps,
            current: currentStep,
            showSteps: true,
            showPercentage: true,
            labels: [
                'Tipo',
                'Información',
                'Plan',
                'Resumen'
            ],
            size: 'md',
            color: 'primary',
            animated: true
        });
    }
    
    updateProgress();
    updateStepVisibility();
    setupPlanSelection();
}

/**
 * Configura event listeners
 */
function setupEventListeners() {
    const btnNext = document.getElementById('btn-next');
    const btnPrev = document.getElementById('btn-prev');
    const btnSubmit = document.getElementById('btn-submit');
    const btnSkip = document.getElementById('btn-skip');
    
    if (btnNext) btnNext.addEventListener('click', nextStep);
    if (btnPrev) btnPrev.addEventListener('click', prevStep);
    if (btnSubmit) btnSubmit.addEventListener('click', submitOnboarding);
    if (btnSkip) btnSkip.addEventListener('click', handleSkip);
}

/**
 * Inicializa tooltips
 */
function initializeTooltips() {
    if (typeof Tooltip !== 'undefined') {
        // Esperar un poco para que el DOM esté completamente renderizado
        setTimeout(() => {
            Tooltip.init();
        }, 100);
    }
}

/**
 * Configura plantillas rápidas
 */
function setupTemplates() {
    const templateButtons = document.querySelectorAll('.template-btn');
    templateButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const template = templates[btn.dataset.template];
            if (template) {
                applyTemplate(template);
                Toast.success('Plantilla aplicada');
            }
        });
    });
}

/**
 * Aplica una plantilla
 */
function applyTemplate(template) {
    const nameInput = document.getElementById('name');
    const timezoneSelect = document.getElementById('timezone');
    const localeSelect = document.getElementById('locale');
    const planInput = document.getElementById('plan');
    
    if (nameInput) nameInput.value = template.name;
    if (timezoneSelect) timezoneSelect.value = template.timezone;
    if (localeSelect) localeSelect.value = template.locale;
    if (planInput) planInput.value = template.plan;
    
    // Actualizar selección de plan visual
    const planCards = document.querySelectorAll('.plan-card');
    planCards.forEach(card => {
        card.classList.remove('border-primary', 'border-3');
        if (card.dataset.plan === template.plan) {
            card.classList.add('border-primary', 'border-3');
        }
    });
}

/**
 * Maneja el botón Skip
 */
async function handleSkip() {
    // Usar Modal.confirm si está disponible, sino usar confirm nativo como fallback
    let confirmed = false;
    if (typeof Modal !== "undefined") {
        confirmed = await Modal.confirm({
            title: 'Omitir Onboarding',
            message: '¿Estás seguro de que quieres omitir el onboarding? Podrás configurar tu organización más tarde desde la configuración.'
        });
    } else {
        confirmed = confirm('¿Estás seguro de que quieres omitir el onboarding? Podrás configurar tu organización más tarde desde la configuración.');
    }
    
    if (confirmed) {
        // Redirigir al login
        window.location.href = `${BASE_URL}/login`;
    }
}

/**
 * Selección de plan
 */
function setupPlanSelection() {
    const planCards = document.querySelectorAll('.plan-card');
    planCards.forEach(card => {
        card.addEventListener('click', () => {
            planCards.forEach(c => c.classList.remove('border-primary', 'border-3'));
            card.classList.add('border-primary', 'border-3');
            const plan = document.getElementById('plan');
            if (plan) plan.value = card.dataset.plan;
        });
    });
}

/**
 * Avanza al siguiente paso
 */
function nextStep() {
    if (!validateCurrentStep()) {
        return;
    }
    
    if (currentStep < totalSteps) {
        currentStep++;
        updateProgress();
        updateStepVisibility();
        
        if (currentStep === totalSteps) {
            updateSummary();
        }
    }
}

/**
 * Retrocede al paso anterior
 */
function prevStep() {
    if (currentStep > 1) {
        currentStep--;
        updateProgress();
        updateStepVisibility();
    }
}

/**
 * Valida el paso actual
 */
function validateCurrentStep() {
    switch (currentStep) {
        case 1:
            // Tipo de organización siempre tiene valor por defecto
            return true;
            
        case 2:
            const name = document.getElementById('name');
            const nameValue = name?.value.trim();
            if (!nameValue) {
                Toast.error('El nombre de la organización es requerido');
                if (name) name.classList.add('is-invalid');
                return false;
            }
            if (name) name.classList.remove('is-invalid');
            return true;
            
        case 3:
            // Plan siempre tiene valor por defecto
            return true;
            
        case 4:
            return true;
            
        default:
            return true;
    }
}

/**
 * Actualiza la barra de progreso
 */
function updateProgress() {
    if (progressBar) {
        progressBar.setProgress(currentStep);
    } else {
        // Fallback al método antiguo si ProgressBar no está disponible
        const progress = (currentStep / totalSteps) * 100;
        const progressBarEl = document.getElementById('progress-bar');
        if (progressBarEl) {
            progressBarEl.style.width = progress + '%';
        }
    }
}

/**
 * Actualiza la visibilidad de los pasos
 */
function updateStepVisibility() {
    // Ocultar todos los pasos
    for (let i = 1; i <= totalSteps; i++) {
        const step = document.getElementById(`step-${i}`);
        if (step) step.classList.add('d-none');
    }
    
    // Mostrar paso actual
    const currentStepEl = document.getElementById(`step-${currentStep}`);
    if (currentStepEl) currentStepEl.classList.remove('d-none');
    
    // Mostrar/ocultar botones
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const btnSubmit = document.getElementById('btn-submit');
    
    if (btnPrev) btnPrev.style.display = currentStep > 1 ? 'block' : 'none';
    if (btnNext) btnNext.style.display = currentStep < totalSteps ? 'block' : 'none';
    if (btnSubmit) btnSubmit.classList.toggle('d-none', currentStep !== totalSteps);
}

/**
 * Actualiza el resumen
 */
function updateSummary() {
    const orgTypeRadio = document.querySelector('input[name="org_type"]:checked');
    const name = document.getElementById('name');
    const plan = document.getElementById('plan');
    const timezone = document.getElementById('timezone');
    const locale = document.getElementById('locale');
    
    const orgType = orgTypeRadio?.value;
    const nameValue = name?.value;
    const planValue = plan?.value;
    const timezoneValue = timezone?.value;
    const localeValue = locale?.value;
    
    const summaryOrgType = document.getElementById('summary-org-type');
    const summaryName = document.getElementById('summary-name');
    const summaryPlan = document.getElementById('summary-plan');
    const summaryTimezone = document.getElementById('summary-timezone');
    const summaryLocale = document.getElementById('summary-locale');
    
    if (summaryOrgType) summaryOrgType.textContent = orgType === 'empresa' ? 'Empresa' : 'Temporera';
    if (summaryName) summaryName.textContent = nameValue || '';
    if (summaryPlan) summaryPlan.textContent = planValue ? planValue.charAt(0).toUpperCase() + planValue.slice(1) : '';
    if (summaryTimezone) summaryTimezone.textContent = timezoneValue || '';
    if (summaryLocale) summaryLocale.textContent = localeValue || '';
}

/**
 * Envía el formulario de onboarding
 */
async function submitOnboarding() {
    if (!validateCurrentStep()) {
        return;
    }
    
    const name = document.getElementById('name');
    const orgTypeRadio = document.querySelector('input[name="org_type"]:checked');
    const plan = document.getElementById('plan');
    const timezone = document.getElementById('timezone');
    const locale = document.getElementById('locale');
    
    const data = {
        name: name?.value.trim(),
        org_type: orgTypeRadio?.value,
        plan: plan?.value,
        timezone: timezone?.value,
        locale: locale?.value,
        currency: 'CHF'
    };
    
    // Mostrar loading
    const loading = document.getElementById('loading');
    const btnSubmit = document.getElementById('btn-submit');
    
    if (loading) loading.classList.remove('d-none');
    if (btnSubmit) btnSubmit.disabled = true;
    hideAlerts();
    
    try {
        const response = await API.post('/tenants', data);
        
        if (response && response.success) {
            Toast.success('Tenant creado correctamente. Redirigiendo...');
            setTimeout(() => {
                window.location.href = `${BASE_URL}/login`;
            }, 2000);
        } else {
            Toast.error(response?.message || 'Error al crear tenant');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('Error de conexión. Intenta nuevamente.');
    } finally {
        if (loading) loading.classList.add('d-none');
        if (btnSubmit) btnSubmit.disabled = false;
    }
}

/**
 * Oculta alertas
 */
function hideAlerts() {
    const alertContainer = document.getElementById('alert-container');
    if (alertContainer) alertContainer.innerHTML = '';
}
