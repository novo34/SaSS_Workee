/**
 * Sistema de Logros y Milestones
 * 
 * Gestiona badges visuales para logros/milestones del usuario
 * 
 * @example
 * Achievements.init();
 * Achievements.showAchievement('first_request', 'Primera Solicitud Creada');
 */

class Achievements {
    constructor() {
        this.achievements = [];
        this.loadAchievements();
    }
    
    /**
     * Carga logros desde localStorage o API
     */
    loadAchievements() {
        const stored = localStorage.getItem('workee_achievements');
        if (stored) {
            try {
                this.achievements = JSON.parse(stored);
            } catch (e) {
                this.achievements = [];
            }
        }
    }
    
    /**
     * Guarda logros en localStorage
     */
    saveAchievements() {
        localStorage.setItem('workee_achievements', JSON.stringify(this.achievements));
    }
    
    /**
     * Verifica si un logro ya fue obtenido
     */
    hasAchievement(key) {
        return this.achievements.some(a => a.key === key);
    }
    
    /**
     * Obtiene un logro por clave
     */
    getAchievement(key) {
        return this.achievements.find(a => a.key === key);
    }
    
    /**
     * Muestra un logro obtenido
     */
    showAchievement(key, title, description = '', icon = '🎉') {
        // Verificar si ya se obtuvo
        if (this.hasAchievement(key)) {
            return;
        }
        
        // Agregar logro
        const achievement = {
            key,
            title,
            description,
            icon,
            obtainedAt: new Date().toISOString()
        };
        
        this.achievements.push(achievement);
        this.saveAchievements();
        
        // Mostrar notificación
        this.displayAchievementNotification(achievement);
        
        // Actualizar badges en UI
        this.updateAchievementBadges();
    }
    
    /**
     * Muestra notificación de logro obtenido
     */
    displayAchievementNotification(achievement) {
        // Mostrar banner especial (menos intrusivo que toast)
        this.showAchievementBanner(achievement);
        
        // Opcionalmente mostrar toast si el usuario tiene habilitadas las notificaciones
        if (typeof Toast !== 'undefined' && this.shouldShowNotification()) {
            Toast.success(`${achievement.icon} ${achievement.title}`, 5000);
        }
    }
    
    /**
     * Verifica si se deben mostrar notificaciones según preferencias
     */
    shouldShowNotification() {
        // Verificar preferencias de notificaciones desde localStorage
        const prefs = localStorage.getItem('workee_notification_prefs');
        if (prefs) {
            try {
                const prefsObj = JSON.parse(prefs);
                return prefsObj.showAchievements !== false; // Por defecto mostrar
            } catch (e) {
                return true; // Por defecto mostrar
            }
        }
        return true; // Por defecto mostrar
    }
    
    /**
     * Muestra banner de logro
     */
    showAchievementBanner(achievement) {
        const banner = document.createElement('div');
        banner.className = 'achievement-banner achievement-banner--show';
        banner.innerHTML = `
            <div class="achievement-banner__content">
                <div class="achievement-banner__icon">${achievement.icon}</div>
                <div class="achievement-banner__text">
                    <div class="achievement-banner__title">${this.escapeHtml(achievement.title)}</div>
                    ${achievement.description ? `<div class="achievement-banner__description">${this.escapeHtml(achievement.description)}</div>` : ''}
                </div>
                <button class="achievement-banner__close" aria-label="Cerrar">×</button>
            </div>
        `;
        
        document.body.appendChild(banner);
        
        // Auto-cerrar después de 5 segundos
        setTimeout(() => {
            banner.classList.remove('achievement-banner--show');
            setTimeout(() => banner.remove(), 300);
        }, 5000);
        
        // Cerrar manualmente
        const closeBtn = banner.querySelector('.achievement-banner__close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                banner.classList.remove('achievement-banner--show');
                setTimeout(() => banner.remove(), 300);
            });
        }
    }
    
    /**
     * Actualiza badges de logros en la UI
     */
    updateAchievementBadges() {
        // Buscar contenedores de badges
        const containers = document.querySelectorAll('[data-achievements-container]');
        containers.forEach(container => {
            this.renderAchievementBadges(container);
        });
    }
    
    /**
     * Renderiza badges de logros en un contenedor
     */
    renderAchievementBadges(container) {
        if (!container) return;
        
        container.innerHTML = '';
        
        if (this.achievements.length === 0) {
            container.innerHTML = '<span class="text-muted">Aún no has obtenido logros</span>';
            return;
        }
        
        this.achievements.forEach(achievement => {
            const badge = document.createElement('span');
            badge.className = 'badge-modern badge-modern--success achievement-badge';
            badge.setAttribute('data-tooltip', achievement.description || achievement.title);
            badge.setAttribute('data-tooltip-position', 'top');
            badge.innerHTML = `
                <span class="achievement-badge__icon">${achievement.icon}</span>
                <span class="achievement-badge__title">${this.escapeHtml(achievement.title)}</span>
            `;
            container.appendChild(badge);
        });
        
        // Reinicializar tooltips
        if (typeof Tooltip !== 'undefined') {
            Tooltip.init('[data-tooltip]');
        }
    }
    
    /**
     * Obtiene estadísticas de logros
     */
    getStats() {
        return {
            total: this.achievements.length,
            recent: this.achievements.slice(-5).reverse()
        };
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Inicializa el sistema de logros
     */
    static init() {
        if (!window.achievementsInstance) {
            window.achievementsInstance = new Achievements();
        }
        return window.achievementsInstance;
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.Achievements = Achievements;
    
    // Auto-inicializar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => Achievements.init());
    } else {
        Achievements.init();
    }
}

