/**
 * Settings Notifications UI
 * 
 * Gestión de preferencias de notificaciones
 */

let currentPreferences = [];
let eventTypes = [
    { key: 'request.created', label: 'Solicitud Creada' },
    { key: 'proposal.received', label: 'Propuesta Recibida' },
    { key: 'proposal.accepted', label: 'Propuesta Aceptada' },
    { key: 'shift.assigned', label: 'Turno Asignado' },
    { key: 'shift.changed', label: 'Cambio en Turno' },
    { key: 'report.submitted', label: 'Parte Enviado' },
    { key: 'dispute.created', label: 'Disputa Creada' },
    { key: 'dispute.resolved', label: 'Disputa Resuelta' },
    { key: 'invoice.issued', label: 'Factura Emitida' },
    { key: 'worklog.approved', label: 'Work Log Aprobado' },
    { key: 'worklog.rejected', label: 'Work Log Rechazado' }
];

const channels = [
    { key: 'email', label: 'Email', icon: 'bi-envelope' },
    { key: 'sms', label: 'SMS', icon: 'bi-phone' },
    { key: 'whatsapp', label: 'WhatsApp', icon: 'bi-whatsapp' },
    { key: 'webpush', label: 'WebPush', icon: 'bi-bell' }
];

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    loadPreferences();
    setupEventListeners();
    renderEventPreferences();
});

/**
 * Configura los event listeners
 */
function setupEventListeners() {
    const saveBtn = document.getElementById('savePreferencesBtn');
    saveBtn.addEventListener('click', handleSavePreferences);
}

/**
 * Carga las preferencias actuales
 */
async function loadPreferences() {
    try {
        const response = await API.get('/api/notification-preferences');
        
        if (response.success) {
            currentPreferences = response.data || [];
            applyPreferencesToUI();
        } else {
            Toast.error('Error al cargar preferencias');
        }
    } catch (error) {
        console.error('Error cargando preferencias:', error);
        Toast.error('Error al cargar preferencias');
    }
}

/**
 * Aplica las preferencias cargadas a la UI
 */
function applyPreferencesToUI() {
    // Aplicar quiet hours
    const quietHoursStart = currentPreferences.find(p => p.quiet_hours_start)?.quiet_hours_start;
    const quietHoursEnd = currentPreferences.find(p => p.quiet_hours_end)?.quiet_hours_end;
    
    if (quietHoursStart) {
        document.getElementById('quietHoursStart').value = quietHoursStart;
    }
    if (quietHoursEnd) {
        document.getElementById('quietHoursEnd').value = quietHoursEnd;
    }
    
    // Aplicar preferencias por evento y canal
    eventTypes.forEach(event => {
        channels.forEach(channel => {
            const pref = currentPreferences.find(
                p => p.event_type === event.key && p.channel === channel.key
            );
            
            const checkbox = document.getElementById(`pref-${event.key}-${channel.key}`);
            if (checkbox) {
                checkbox.checked = pref ? pref.enabled : (channel.key === 'email' || channel.key === 'webpush');
            }
        });
    });
}

/**
 * Renderiza las preferencias por evento
 */
function renderEventPreferences() {
    const container = document.getElementById('eventPreferencesContainer');
    container.innerHTML = '';
    
    eventTypes.forEach(event => {
        const eventCard = document.createElement('div');
        eventCard.className = 'mb-4 p-3 border rounded';
        eventCard.innerHTML = `
            <h6 class="mb-3">${event.label}</h6>
            <div class="row g-3">
                ${channels.map(channel => `
                    <div class="col-md-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   id="pref-${event.key}-${channel.key}"
                                   data-event="${event.key}" 
                                   data-channel="${channel.key}">
                            <label class="form-check-label" for="pref-${event.key}-${channel.key}">
                                <i class="bi ${channel.icon}"></i> ${channel.label}
                            </label>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
        container.appendChild(eventCard);
    });
}

/**
 * Maneja el guardado de preferencias
 */
async function handleSavePreferences() {
    const saveBtn = document.getElementById('savePreferencesBtn');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Guardando...';
    
    try {
        const quietHoursStart = document.getElementById('quietHoursStart').value || null;
        const quietHoursEnd = document.getElementById('quietHoursEnd').value || null;
        
        const preferencesToSave = [];
        
        // Recopilar todas las preferencias de los checkboxes
        eventTypes.forEach(event => {
            channels.forEach(channel => {
                const checkbox = document.getElementById(`pref-${event.key}-${channel.key}`);
                if (checkbox) {
                    preferencesToSave.push({
                        channel: channel.key,
                        event_type: event.key,
                        enabled: checkbox.checked,
                        quiet_hours_start: quietHoursStart,
                        quiet_hours_end: quietHoursEnd
                    });
                }
            });
        });
        
        // Guardar cada preferencia
        let saved = 0;
        let failed = 0;
        
        for (const pref of preferencesToSave) {
            try {
                const response = await API.post('/api/notification-preferences', pref);
                if (response.success) {
                    saved++;
                } else {
                    failed++;
                }
            } catch (error) {
                console.error('Error guardando preferencia:', error);
                failed++;
            }
        }
        
        // Guardar preferencias en localStorage para Toast
        const prefsObj = {
            quietMode: false, // Por ahora no implementado en UI
            quietHoursStart: quietHoursStart,
            quietHoursEnd: quietHoursEnd,
            showAchievements: true // Por defecto mostrar logros
        };
        localStorage.setItem('workee_notification_prefs', JSON.stringify(prefsObj));
        
        // Actualizar Toast con nuevas preferencias
        if (typeof Toast !== 'undefined') {
            Toast.loadPreferences();
        }
        
        if (saved > 0) {
            Toast.success(`Preferencias guardadas: ${saved} actualizadas`);
            await loadPreferences(); // Recargar para sincronizar
        }
        
        if (failed > 0) {
            Toast.error(`Error al guardar ${failed} preferencias`);
        }
        
    } catch (error) {
        console.error('Error guardando preferencias:', error);
        Toast.error('Error al guardar preferencias');
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Guardar';
    }
}

