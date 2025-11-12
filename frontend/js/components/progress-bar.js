/**
 * Componente ProgressBar Reutilizable
 * 
 * Muestra una barra de progreso con opciones de personalización
 * 
 * @example
 * const progressBar = new ProgressBar(container, {
 *   total: 4,
 *   current: 1,
 *   showSteps: true,
 *   showPercentage: true
 * });
 * 
 * progressBar.setProgress(2); // Avanzar al paso 2
 */

class ProgressBar {
    constructor(container, options = {}) {
        this.container = typeof container === 'string' 
            ? document.querySelector(container) 
            : container;
        
        if (!this.container) {
            console.error('ProgressBar: Container no encontrado');
            return;
        }
        
        this.options = {
            total: options.total || 4,
            current: options.current || 1,
            showSteps: options.showSteps !== false,
            showPercentage: options.showPercentage !== false,
            showLabels: options.showLabels !== false,
            labels: options.labels || [],
            size: options.size || 'md', // sm, md, lg
            color: options.color || 'primary',
            animated: options.animated !== false,
            ...options
        };
        
        this.render();
    }
    
    render() {
        const percentage = (this.options.current / this.options.total) * 100;
        
        this.container.innerHTML = `
            <div class="progress-bar-container progress-bar-container--${this.options.size}">
                ${this.options.showSteps ? this.renderSteps() : ''}
                <div class="progress progress-bar-modern progress-bar-modern--${this.options.size}">
                    <div 
                        class="progress-bar progress-bar-modern__bar progress-bar-modern__bar--${this.options.color} ${this.options.animated ? 'progress-bar-modern__bar--animated' : ''}" 
                        role="progressbar" 
                        style="width: ${percentage}%"
                        aria-valuenow="${this.options.current}"
                        aria-valuemin="1"
                        aria-valuemax="${this.options.total}"
                    >
                        ${this.options.showPercentage ? `<span class="progress-bar-modern__percentage">${Math.round(percentage)}%</span>` : ''}
                    </div>
                </div>
                ${this.options.showSteps && this.options.labels.length > 0 ? this.renderStepLabels() : ''}
            </div>
        `;
        
        this.progressBarElement = this.container.querySelector('.progress-bar');
    }
    
    renderSteps() {
        const steps = [];
        for (let i = 1; i <= this.options.total; i++) {
            const isActive = i <= this.options.current;
            const isCurrent = i === this.options.current;
            steps.push(`
                <div 
                    class="progress-bar-modern__step ${isActive ? 'progress-bar-modern__step--active' : ''} ${isCurrent ? 'progress-bar-modern__step--current' : ''}"
                    data-step="${i}"
                >
                    <span class="progress-bar-modern__step-number">${i}</span>
                </div>
            `);
        }
        
        return `
            <div class="progress-bar-modern__steps">
                ${steps.join('')}
            </div>
        `;
    }
    
    renderStepLabels() {
        const labels = this.options.labels.map((label, index) => {
            const step = index + 1;
            const isActive = step <= this.options.current;
            return `
                <div class="progress-bar-modern__label ${isActive ? 'progress-bar-modern__label--active' : ''}" data-step="${step}">
                    ${label}
                </div>
            `;
        }).join('');
        
        return `
            <div class="progress-bar-modern__labels">
                ${labels}
            </div>
        `;
    }
    
    /**
     * Actualiza el progreso
     */
    setProgress(current) {
        if (current < 1 || current > this.options.total) {
            console.warn(`ProgressBar: Valor fuera de rango (1-${this.options.total})`);
            return;
        }
        
        this.options.current = current;
        const percentage = (current / this.options.total) * 100;
        
        if (this.progressBarElement) {
            this.progressBarElement.style.width = `${percentage}%`;
            this.progressBarElement.setAttribute('aria-valuenow', current);
            
            if (this.options.showPercentage) {
                const percentageEl = this.progressBarElement.querySelector('.progress-bar-modern__percentage');
                if (percentageEl) {
                    percentageEl.textContent = `${Math.round(percentage)}%`;
                }
            }
        }
        
        // Actualizar steps
        const steps = this.container.querySelectorAll('.progress-bar-modern__step');
        steps.forEach((step, index) => {
            const stepNum = index + 1;
            step.classList.toggle('progress-bar-modern__step--active', stepNum <= current);
            step.classList.toggle('progress-bar-modern__step--current', stepNum === current);
        });
        
        // Actualizar labels
        const labels = this.container.querySelectorAll('.progress-bar-modern__label');
        labels.forEach((label, index) => {
            const stepNum = index + 1;
            label.classList.toggle('progress-bar-modern__label--active', stepNum <= current);
        });
    }
    
    /**
     * Avanza un paso
     */
    next() {
        if (this.options.current < this.options.total) {
            this.setProgress(this.options.current + 1);
        }
    }
    
    /**
     * Retrocede un paso
     */
    prev() {
        if (this.options.current > 1) {
            this.setProgress(this.options.current - 1);
        }
    }
    
    /**
     * Resetea el progreso
     */
    reset() {
        this.setProgress(1);
    }
    
    /**
     * Completa el progreso
     */
    complete() {
        this.setProgress(this.options.total);
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.ProgressBar = ProgressBar;
}

