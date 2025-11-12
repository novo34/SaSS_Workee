/**
 * Componente FormValidator - Workee
 * 
 * Validador de formularios reutilizable con validación en tiempo real
 * 
 * @class FormValidator
 * @description Validador de formularios con validación en tiempo real y múltiples reglas
 * 
 * @example
 * // Validación básica
 * const validator = new FormValidator(formElement, {
 *   rules: {
 *     email: { required: true, email: true },
 *     password: { required: true, minLength: 8 }
 *   },
 *   messages: {
 *     email: { required: 'El email es requerido', email: 'Email inválido' }
 *   },
 *   onSubmit: (data) => {
 *     console.log('Datos válidos:', data);
 *   }
 * });
 * 
 * @example
 * // Validación avanzada
 * const validator = new FormValidator(formElement, {
 *   rules: {
 *     password: { required: true, minLength: 8, pattern: /[A-Z]/ },
 *     confirmPassword: { required: true, match: 'password' }
 *   },
 *   validateOnBlur: true,
 *   validateOnInput: true
 * });
 * 
 * @param {HTMLFormElement} form - Elemento formulario a validar
 * @param {Object} options - Opciones de configuración
 * @param {Object} options.rules - Reglas de validación por campo
 * @param {Object} [options.messages={}] - Mensajes de error personalizados
 * @param {boolean} [options.validateOnBlur=true] - Validar al perder foco
 * @param {boolean} [options.validateOnInput=true] - Limpiar errores al escribir
 * @param {boolean} [options.showErrors=true] - Mostrar errores visualmente
 * @param {Function} [options.onSubmit] - Callback cuando el formulario es válido
 */

class FormValidator {
    constructor(form, options = {}) {
        this.form = form;
        this.options = {
            rules: options.rules || {},
            messages: options.messages || {},
            validateOnBlur: options.validateOnBlur !== undefined ? options.validateOnBlur : true,
            validateOnInput: options.validateOnInput !== undefined ? options.validateOnInput : true,
            showErrors: options.showErrors !== undefined ? options.showErrors : true,
            onSubmit: options.onSubmit || null,
            ...options
        };
        
        this.errors = {};
        this.init();
    }

    /**
     * Inicializa el validador
     */
    init() {
        // Event listener para submit
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (this.validate()) {
                const formData = this.getFormData();
                if (this.options.onSubmit) {
                    this.options.onSubmit(formData, this);
                }
            }
        });
        
        // Event listeners para validación en tiempo real
        const inputs = this.form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (this.options.validateOnBlur) {
                input.addEventListener('blur', () => {
                    this.validateField(input.name);
                });
            }
            
            if (this.options.validateOnInput) {
                input.addEventListener('input', () => {
                    // Limpiar error al escribir
                    if (this.errors[input.name]) {
                        this.clearFieldError(input.name);
                    }
                });
            }
        });
    }

    /**
     * Valida todo el formulario
     */
    validate() {
        this.errors = {};
        let isValid = true;
        
        Object.keys(this.options.rules).forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (!field) return;
            
            const fieldErrors = this.validateField(fieldName);
            if (fieldErrors.length > 0) {
                isValid = false;
                this.errors[fieldName] = fieldErrors;
            }
        });
        
        if (this.options.showErrors) {
            this.showErrors();
        }
        
        return isValid;
    }

    /**
     * Valida un campo específico
     */
    validateField(fieldName) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        if (!field) return [];
        
        const value = this.getFieldValue(field);
        const rules = this.options.rules[fieldName] || {};
        const errors = [];
        
        // Required
        if (rules.required && (!value || value.trim() === '')) {
            errors.push(this.getMessage(fieldName, 'required'));
        }
        
        // Solo validar otros rules si el campo tiene valor o no es required
        if (value || !rules.required) {
            // Email
            if (rules.email && value && !this.isValidEmail(value)) {
                errors.push(this.getMessage(fieldName, 'email'));
            }
            
            // Min length
            if (rules.minLength && value && value.length < rules.minLength) {
                errors.push(this.getMessage(fieldName, 'minLength', { min: rules.minLength }));
            }
            
            // Max length
            if (rules.maxLength && value && value.length > rules.maxLength) {
                errors.push(this.getMessage(fieldName, 'maxLength', { max: rules.maxLength }));
            }
            
            // Pattern (regex)
            if (rules.pattern && value && !rules.pattern.test(value)) {
                errors.push(this.getMessage(fieldName, 'pattern'));
            }
            
            // Custom validator
            if (rules.validator && typeof rules.validator === 'function') {
                const customError = rules.validator(value, this.getFormData());
                if (customError) {
                    errors.push(customError);
                }
            }
            
            // Match (debe coincidir con otro campo)
            if (rules.match) {
                const matchField = this.form.querySelector(`[name="${rules.match}"]`);
                if (matchField && value !== this.getFieldValue(matchField)) {
                    errors.push(this.getMessage(fieldName, 'match'));
                }
            }
        }
        
        if (errors.length > 0) {
            this.errors[fieldName] = errors;
            if (this.options.showErrors) {
                this.showFieldError(fieldName, errors[0]);
            }
        } else {
            this.clearFieldError(fieldName);
        }
        
        return errors;
    }

    /**
     * Obtiene el valor de un campo
     */
    getFieldValue(field) {
        if (field.type === 'checkbox') {
            return field.checked;
        }
        if (field.type === 'radio') {
            const checked = this.form.querySelector(`[name="${field.name}"]:checked`);
            return checked ? checked.value : '';
        }
        return field.value || '';
    }

    /**
     * Obtiene todos los datos del formulario
     */
    getFormData() {
        const formData = new FormData(this.form);
        const data = {};
        
        for (const [key, value] of formData.entries()) {
            // Si hay múltiples valores (checkboxes), convertirlos a array
            if (data[key]) {
                if (!Array.isArray(data[key])) {
                    data[key] = [data[key]];
                }
                data[key].push(value);
            } else {
                data[key] = value;
            }
        }
        
        // Agregar checkboxes no marcados como false
        this.form.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            if (!formData.has(checkbox.name)) {
                data[checkbox.name] = false;
            }
        });
        
        return data;
    }

    /**
     * Obtiene un mensaje de error
     */
    getMessage(fieldName, rule, params = {}) {
        if (this.options.messages[fieldName] && this.options.messages[fieldName][rule]) {
            let message = this.options.messages[fieldName][rule];
            // Reemplazar parámetros
            Object.keys(params).forEach(key => {
                message = message.replace(`{${key}}`, params[key]);
            });
            return message;
        }
        
        // Mensajes por defecto
        const defaultMessages = {
            required: 'Este campo es requerido',
            email: 'Email inválido',
            minLength: `Mínimo {min} caracteres`,
            maxLength: `Máximo {max} caracteres`,
            pattern: 'Formato inválido',
            match: 'Los valores no coinciden'
        };
        
        let message = defaultMessages[rule] || 'Valor inválido';
        Object.keys(params).forEach(key => {
            message = message.replace(`{${key}}`, params[key]);
        });
        
        return message;
    }

    /**
     * Muestra errores de un campo
     */
    showFieldError(fieldName, errorMessage) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        if (!field) return;
        
        // Remover error previo
        this.clearFieldError(fieldName);
        
        // Agregar clase de error
        field.classList.add('is-invalid');
        
        // Crear elemento de error
        const errorElement = document.createElement('div');
        errorElement.className = 'invalid-feedback';
        errorElement.textContent = errorMessage;
        errorElement.setAttribute('data-field', fieldName);
        
        // Insertar después del campo
        field.parentNode.appendChild(errorElement);
    }

    /**
     * Limpia el error de un campo
     */
    clearFieldError(fieldName) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        if (!field) return;
        
        field.classList.remove('is-invalid');
        
        const errorElement = field.parentNode.querySelector(`.invalid-feedback[data-field="${fieldName}"]`);
        if (errorElement) {
            errorElement.remove();
        }
        
        delete this.errors[fieldName];
    }

    /**
     * Muestra todos los errores
     */
    showErrors() {
        Object.keys(this.errors).forEach(fieldName => {
            const errors = this.errors[fieldName];
            if (errors && errors.length > 0) {
                this.showFieldError(fieldName, errors[0]);
            }
        });
    }

    /**
     * Limpia todos los errores
     */
    clearErrors() {
        Object.keys(this.errors).forEach(fieldName => {
            this.clearFieldError(fieldName);
        });
        this.errors = {};
    }

    /**
     * Valida email
     */
    isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    /**
     * Resetea el formulario
     */
    reset() {
        this.form.reset();
        this.clearErrors();
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.FormValidator = FormValidator;
}

