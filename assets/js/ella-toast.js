/**
 * ============================================================
 * ELLA TOAST — Global Notification System
 * ============================================================
 * Usage:
 *   EllaToast.success('Item added to cart');
 *   EllaToast.error('Failed to save');
 *   EllaToast.warning('Stock is low');
 *   EllaToast.info('Draft loaded');
 */

const EllaToast = (() => {
    const DEFAULTS = {
        duration: 3000,     // ms before auto-dismiss
        maxToasts: 5        // max stacked toasts
    };

    const ICONS = {
        success: 'fa-check',
        error: 'fa-xmark',
        warning: 'fa-exclamation',
        info: 'fa-info'
    };

    let container = null;

    /** Ensure the toast container exists in the DOM */
    function getContainer() {
        if (!container || !document.body.contains(container)) {
            container = document.createElement('div');
            container.id = 'ella-toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    /** Create and show a toast */
    function show(messageOrOpts, type = 'info', duration) {
        // Support options object: show({ message, type, duration, actionLabel, onAction })
        let message, actionLabel, onAction;
        if (typeof messageOrOpts === 'object' && messageOrOpts !== null) {
            ({ message, type = 'info', duration, actionLabel, onAction } = messageOrOpts);
        } else {
            message = messageOrOpts;
        }
        const dur = duration ?? DEFAULTS.duration;
        const c = getContainer();

        // Enforce max stacked toasts
        const activeToasts = Array.from(c.children).filter(t => !t._dismissed);
        while (activeToasts.length >= DEFAULTS.maxToasts) {
            dismiss(activeToasts.shift());
        }

        const toast = document.createElement('div');
        toast.className = `ella-toast ella-toast--${type}`;

        const actionHtml = actionLabel
            ? `<button class="ella-toast-action">${escapeHtml(actionLabel)}</button>`
            : '';

        toast.innerHTML = `
            <div class="ella-toast-icon">
                <i class="fa-solid ${ICONS[type] || ICONS.info}"></i>
            </div>
            <div class="ella-toast-message">${escapeHtml(message)}</div>
            ${actionHtml}
            <button class="ella-toast-close" title="Dismiss">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="ella-toast-progress" style="animation-duration: ${dur}ms"></div>
        `;

        // Action button handler
        if (actionLabel && onAction) {
            toast.querySelector('.ella-toast-action').addEventListener('click', () => {
                onAction();
                dismiss(toast);
            });
        }

        // Close on click
        toast.querySelector('.ella-toast-close').addEventListener('click', () => dismiss(toast));

        c.appendChild(toast);

        // Auto-dismiss
        const timer = setTimeout(() => dismiss(toast), dur);
        toast._timer = timer;

        return toast;
    }

    /** Dismiss a toast with exit animation */
    function dismiss(toast) {
        if (!toast || toast._dismissed) return;
        toast._dismissed = true;
        clearTimeout(toast._timer);
        toast.classList.add('removing');
        toast.addEventListener('animationend', () => {
            toast.remove();
        }, { once: true });
        // Fallback removal in case animationend doesn't fire
        setTimeout(() => toast.remove(), 400);
    }

    /** Simple HTML escape */
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Public API
    return {
        success: (msg, dur) => show(msg, 'success', dur),
        error: (msg, dur) => show(msg, 'error', dur),
        warning: (msg, dur) => show(msg, 'warning', dur),
        info: (msg, dur) => show(msg, 'info', dur),
        show,
        dismiss
    };
})();
