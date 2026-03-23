// assets/js/validation.js - Form Validation

class FormValidator {
    
    constructor(formId) {
        this.form = document.getElementById(formId);
        if (!this.form) return;
        
        this.errors = [];
        this.init();
    }
    
    init() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
                this.showErrors();
            }
        });
        
        // Real-time validation
        this.form.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('blur', () => {
                this.validateField(field);
            });
            
            field.addEventListener('input', () => {
                this.clearFieldError(field);
            });
        });
    }
    
    validate() {
        this.errors = [];
        const fields = this.form.querySelectorAll('[required]');
        
        fields.forEach(field => {
            this.validateField(field);
        });
        
        return this.errors.length === 0;
    }
    
    validateField(field) {
        const value = field.value.trim();
        const type = field.type;
        const name = field.name;
        
        this.clearFieldError(field);
        
        // Required validation
        if (field.hasAttribute('required') && !value) {
            this.addError(field, `${this.getFieldLabel(field)} is required`);
            return;
        }
        
        if (!value) return;
        
        // Type-specific validation
        switch (type) {
            case 'email':
                if (!this.isEmail(value)) {
                    this.addError(field, 'Please enter a valid email address');
                }
                break;
                
            case 'tel':
                if (!this.isPhone(value)) {
                    this.addError(field, 'Please enter a valid 11-digit phone number');
                }
                break;
                
            case 'number':
                const min = parseFloat(field.min);
                const max = parseFloat(field.max);
                const num = parseFloat(value);
                
                if (field.min && num < min) {
                    this.addError(field, `Minimum value is ${min}`);
                }
                if (field.max && num > max) {
                    this.addError(field, `Maximum value is ${max}`);
                }
                break;
        }
        
        // Password validation
        if (name === 'password' || name === 'new_password') {
            const errors = this.validatePassword(value);
            if (errors.length > 0) {
                this.addError(field, errors.join(', '));
            }
        }
        
        // Confirm password
        if (name === 'confirm_password') {
            const password = this.form.querySelector('[name="password"], [name="new_password"]')?.value;
            if (value !== password) {
                this.addError(field, 'Passwords do not match');
            }
        }
    }
    
    addError(field, message) {
        this.errors.push({ field, message });
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = message;
        errorDiv.style.color = '#ef4444';
        errorDiv.style.fontSize = '0.75rem';
        errorDiv.style.marginTop = '0.25rem';
        
        field.classList.add('error');
        field.style.borderColor = '#ef4444';
        
        const parent = field.closest('.form-group');
        if (parent) {
            parent.appendChild(errorDiv);
        }
    }
    
    clearFieldError(field) {
        field.classList.remove('error');
        field.style.borderColor = '';
        
        const parent = field.closest('.form-group');
        if (parent) {
            const errors = parent.querySelectorAll('.field-error');
            errors.forEach(e => e.remove());
        }
    }
    
    showErrors() {
        // Scroll to first error
        const firstError = document.querySelector('.error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    getFieldLabel(field) {
        const label = this.form.querySelector(`label[for="${field.id}"]`);
        return label ? label.textContent.replace('*', '').trim() : 
               field.placeholder || field.name;
    }
    
    isEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    isPhone(phone) {
        return /^[0-9]{11}$/.test(phone);
    }
    
    validatePassword(password) {
        const errors = [];
        if (password.length < 8) errors.push('8+ characters');
        if (!/[A-Z]/.test(password)) errors.push('uppercase');
        if (!/[a-z]/.test(password)) errors.push('lowercase');
        if (!/[0-9]/.test(password)) errors.push('number');
        return errors;
    }
}

// Initialize validators
document.addEventListener('DOMContentLoaded', function() {
    // Login form
    if (document.getElementById('loginForm')) {
        new FormValidator('loginForm');
    }
    
    // Register form
    if (document.getElementById('registerForm')) {
        new FormValidator('registerForm');
    }
    
    // Airtime form
    if (document.getElementById('airtimeForm')) {
        new FormValidator('airtimeForm');
    }
    
    // Data form
    if (document.getElementById('dataForm')) {
        new FormValidator('dataForm');
    }
    
    // Electricity form
    if (document.getElementById('electricityForm')) {
        new FormValidator('electricityForm');
    }
    
    // Cable form
    if (document.getElementById('cableForm')) {
        new FormValidator('cableForm');
    }
    
    // Fund wallet form
    if (document.getElementById('fundForm')) {
        new FormValidator('fundForm');
    }
    
    // Profile form
    if (document.getElementById('profileForm')) {
        new FormValidator('profileForm');
    }
});

// Password strength meter
function initPasswordStrength(inputId, meterId) {
    const input = document.getElementById(inputId);
    const meter = document.getElementById(meterId);
    
    if (!input || !meter) return;
    
    input.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        
        meter.className = 'password-strength-bar';
        
        if (strength <= 1) {
            meter.classList.add('strength-weak');
            meter.style.width = '25%';
        } else if (strength === 2) {
            meter.classList.add('strength-fair');
            meter.style.width = '50%';
        } else if (strength === 3) {
            meter.classList.add('strength-good');
            meter.style.width = '75%';
        } else if (strength === 4) {
            meter.classList.add('strength-strong');
            meter.style.width = '100%';
        }
    });
}