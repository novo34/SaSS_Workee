/**
 * Componente Task Progress
 * 
 * Muestra progreso de tareas completadas usando ProgressBar
 * 
 * @example
 * const taskProgress = new TaskProgress(container, {
 *   total: 10,
 *   completed: 3,
 *   milestones: [5, 10, 20]
 * });
 */

class TaskProgress {
    constructor(container, options = {}) {
        this.container = typeof container === 'string' 
            ? document.querySelector(container) 
            : container;
        
        if (!this.container) {
            console.error('TaskProgress: Container no encontrado');
            return;
        }
        
        this.options = {
            total: options.total || 10,
            completed: options.completed || 0,
            milestones: options.milestones || [],
            title: options.title || 'Progreso de Tareas',
            showMilestones: options.showMilestones !== false,
            ...options
        };
        
        this.progressBar = null;
        this.render();
    }
    
    render() {
        const percentage = (this.options.completed / this.options.total) * 100;
        
        this.container.innerHTML = `
            <div class="achievement-progress">
                <div class="achievement-progress__header">
                    <span class="achievement-progress__title">${this.escapeHtml(this.options.title)}</span>
                    <span class="achievement-progress__value">${this.options.completed}/${this.options.total}</span>
                </div>
                <div class="achievement-progress__bar" id="task-progress-bar-${Date.now()}"></div>
                ${this.options.showMilestones && this.options.milestones.length > 0 ? this.renderMilestones() : ''}
            </div>
        `;
        
        // Inicializar ProgressBar
        const progressBarContainer = this.container.querySelector('.achievement-progress__bar');
        if (progressBarContainer && typeof ProgressBar !== 'undefined') {
            this.progressBar = new ProgressBar(progressBarContainer, {
                total: this.options.total,
                current: this.options.completed,
                showSteps: false,
                showPercentage: true,
                size: 'sm',
                color: 'success',
                animated: true
            });
        }
    }
    
    renderMilestones() {
        const milestones = this.options.milestones.map(milestone => {
            const isCompleted = this.options.completed >= milestone;
            return `
                <div class="achievement-progress__milestone ${isCompleted ? 'achievement-progress__milestone--completed' : ''}">
                    ${milestone}
                </div>
            `;
        }).join('');
        
        return `
            <div class="achievement-progress__milestones">
                ${milestones}
            </div>
        `;
    }
    
    /**
     * Actualiza el progreso
     */
    setProgress(completed) {
        this.options.completed = Math.min(completed, this.options.total);
        
        if (this.progressBar) {
            this.progressBar.setProgress(this.options.completed);
        }
        
        // Actualizar valor
        const valueEl = this.container.querySelector('.achievement-progress__value');
        if (valueEl) {
            valueEl.textContent = `${this.options.completed}/${this.options.total}`;
        }
        
        // Actualizar milestones
        if (this.options.showMilestones) {
            const milestoneEls = this.container.querySelectorAll('.achievement-progress__milestone');
            milestoneEls.forEach((el, index) => {
                const milestone = this.options.milestones[index];
                if (milestone && this.options.completed >= milestone) {
                    el.classList.add('achievement-progress__milestone--completed');
                } else {
                    el.classList.remove('achievement-progress__milestone--completed');
                }
            });
        }
        
        // Verificar si se completó
        if (this.options.completed >= this.options.total) {
            this.onComplete();
        }
    }
    
    /**
     * Incrementa el progreso
     */
    increment() {
        this.setProgress(this.options.completed + 1);
    }
    
    /**
     * Callback cuando se completa
     */
    onComplete() {
        if (this.options.onComplete) {
            this.options.onComplete();
        }
        
        // Mostrar logro si está disponible
        if (typeof Achievements !== 'undefined') {
            const achievements = Achievements.init();
            achievements.showAchievement(
                `task_completed_${this.options.title.toLowerCase().replace(/\s+/g, '_')}`,
                `¡${this.options.title} Completado!`,
                `Has completado todas las tareas de ${this.options.title.toLowerCase()}`
            );
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.TaskProgress = TaskProgress;
}

