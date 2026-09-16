/**
 * LibreLiveTopology UI Improvements
 * Loading spinners, Toast notifications, Focus states, ARIA labels
 */

/**
 * Toast Notification System
 * Lightweight vanilla JS toast notifications using Bootstrap 4
 */
class LLTToastManager {
    constructor(options = {}) {
        this.container = null;
        this.defaultOptions = {
            duration: 5000,
            dismissible: true,
            animation: true,
            position: 'top-right'
        };
        this.options = { ...this.defaultOptions, ...options };
        this.init();
    }

    init() {
        if (!this.container) {
            this.container = this.createContainer();
            document.body.appendChild(this.container);
        }
    }

    createContainer() {
        const container = document.createElement('div');
        container.className = 'llt-toast-container';
        container.setAttribute('role', 'status');
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'true');
        return container;
    }

    show(message, type = 'info', options = {}) {
        // show(message, options) is supported via type detection
        let resolvedType = type;
        let resolvedOptions = options;

        if (type && typeof type === 'object') {
            resolvedOptions = type;
            resolvedType = resolvedOptions.type || 'info';
        }

        const settings = { ...this.options, ...resolvedOptions };
        const toast = this.createToast(message, resolvedType, settings);

        this.container.appendChild(toast);

        if (settings.dismissible) {
            this.autoDismiss(toast, settings.duration);
        }

        return toast;
    }

    createToast(message, type, settings) {
        const toast = document.createElement('div');
        toast.className = `alert llt-toast llt-toast-${type}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');

        const iconMap = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        let icon = '';
        if (settings.showIcon !== false) {
            icon = `<i class="fas ${iconMap[type]} llt-icon llt-icon-${type}"></i>`;
        }

        let closeBtn = '';
        if (settings.dismissible) {
            closeBtn = `
                <button type="button" class="close" aria-label="Close notification" onclick="this.parentElement.remove()">
                    <span aria-hidden="true">&times;</span>
                </button>
            `;
        }

        toast.innerHTML = `
            <div class="llt-toast-icon">
                ${icon}
                <div>
                    ${message}
                    ${settings.duration ? `<div class="llt-toast-timer" style="animation-duration: ${settings.duration}ms"></div>` : ''}
                </div>
                ${closeBtn}
            </div>
        `;

        return toast;
    }

    autoDismiss(toast, duration) {
        setTimeout(() => {
            this.dismiss(toast);
        }, duration);
    }

    dismiss(toast) {
        if (toast && toast.parentElement) {
            toast.classList.add('llt-dismissing');
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 300);
        }
    }

    // success(message)
    success(message, options) { return this.show(message, 'success', options); }
    // error(message)
    error(message, options) { return this.show(message, 'error', options); }
    warning(message, options) { return this.show(message, 'warning', options); }
    info(message, options) { return this.show(message, 'info', options); }
    clear() { this.container.innerHTML = ''; }
}

/**
 * Loading Spinner Manager
 */
class LLTLoadingManager {
    constructor() {
        this.overlay = null;
        this.init();
    }

    init() {
        this.overlay = document.createElement('div');
        this.overlay.className = 'spinner-overlay';
        this.overlay.setAttribute('aria-busy', 'false');
        this.overlay.innerHTML = `
            <div class="spinner-border-custom text-light" role="status" aria-live="polite">
                <span class="sr-only">Loading...</span>
            </div>
        `;
        document.body.appendChild(this.overlay);
    }

    show(message = 'Loading...') {
        this.overlay.classList.add('active');
        this.overlay.setAttribute('aria-busy', 'true');
        const textElement = this.overlay.querySelector('.sr-only');
        if (textElement) {
            textElement.textContent = message;
        }
        document.body.style.overflow = 'hidden';
    }

    hide() {
        this.overlay.classList.remove('active');
        this.overlay.setAttribute('aria-busy', 'false');
        document.body.style.overflow = '';
    }

    toggle(loading, message = 'Loading...') {
        if (loading) {
            this.show(message);
        } else {
            this.hide();
        }
    }
}

/**
 * Accessibility Helper
 */
class LLTA11y {
    static announce(message, priority = 'polite') {
        const announcer = document.getElementById('llt-announcer');

        if (!announcer) {
            const div = document.createElement('div');
            div.id = 'llt-announcer';
            div.className = 'sr-only';
            div.setAttribute('role', 'status');
            div.setAttribute('aria-live', priority);
            document.body.appendChild(div);
            div.textContent = message;
        } else {
            announcer.textContent = message;
            setTimeout(() => { announcer.textContent = ''; }, 1000);
        }
    }

    static setFocus(element) {
        if (element && typeof element.focus === 'function') {
            element.focus();
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    static addAriaLabel(element, label) {
        if (element) {
            element.setAttribute('aria-label', label);
        }
    }

    static addAriaDescribedby(element, describedby) {
        if (element) {
            element.setAttribute('aria-describedby', describedby);
        }
    }
}

// Initialize globally
window.LLTToast = new LLTToastManager();
window.LLTLoading = new LLTLoadingManager();
window.LLTA11y = LLTA11y;
