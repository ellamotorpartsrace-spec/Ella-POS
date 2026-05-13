/**
 * ============================================================
 * ELLA CONFIRM — Styled Confirmation Dialogs
 * ============================================================
 * Usage:
 *   EllaConfirm.show({
 *       title: 'Clear Cart?',
 *       message: 'All items will be removed from your cart.',
 *       confirmText: 'Clear',
 *       confirmClass: 'btn-danger',
 *       onConfirm: () => { ... }
 *   });
 *
 *   // Or with async/await:
 *   const confirmed = await EllaConfirm.ask('Delete this draft?');
 *   if (confirmed) { ... }
 */

const EllaConfirm = (() => {
    let modalEl = null;
    let bsModal = null;
    let resolvePromise = null;

    /** Ensure the modal DOM exists */
    function ensureModal() {
        if (modalEl && document.body.contains(modalEl)) return;

        modalEl = document.createElement('div');
        modalEl.className = 'modal fade';
        modalEl.id = 'ellaConfirmModal';
        modalEl.setAttribute('tabindex', '-1');
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.innerHTML = `
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                    <div class="modal-body text-center p-4">
                        <div id="ella-confirm-icon" class="mb-3">
                            <i class="fa-solid fa-question-circle fa-3x text-warning"></i>
                        </div>
                        <h5 id="ella-confirm-title" class="fw-bold mb-2">Are you sure?</h5>
                        <p id="ella-confirm-message" class="text-muted small mb-0">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center gap-2 pb-4 pt-0">
                        <button type="button" class="btn btn-outline-secondary px-4" id="ella-confirm-cancel" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-danger px-4" id="ella-confirm-ok">
                            Confirm
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modalEl);

        // Confirm button handler
        modalEl.querySelector('#ella-confirm-ok').addEventListener('click', () => {
            if (bsModal) bsModal.hide();
            if (resolvePromise) resolvePromise(true);
            resolvePromise = null;
        });

        // Cancel / dismiss handler
        modalEl.addEventListener('hidden.bs.modal', () => {
            if (resolvePromise) resolvePromise(false);
            resolvePromise = null;
        });
    }

    /**
     * Show a confirmation dialog
     * @param {Object} options
     * @param {string} options.title
     * @param {string} options.message
     * @param {string} options.confirmText - Button text (default: "Confirm")
     * @param {string} options.cancelText  - Button text (default: "Cancel")
     * @param {string} options.confirmClass - Button class (default: "btn-danger")
     * @param {string} options.icon - Icon class (default: "fa-question-circle")
     * @param {string} options.iconColor - Icon color class (default: "text-warning")
     * @param {Function} options.onConfirm - Called when confirmed
     * @param {Function} options.onCancel  - Called when cancelled
     * @returns {Promise<boolean>}
     */
    function show(options = {}) {
        ensureModal();

        const {
            title = 'Are you sure?',
            message = 'This action cannot be undone.',
            confirmText = 'Confirm',
            cancelText = 'Cancel',
            confirmClass = 'btn-danger',
            icon = 'fa-question-circle',
            iconColor = 'text-warning',
            onConfirm = null,
            onCancel = null
        } = options;

        // Update DOM
        modalEl.querySelector('#ella-confirm-title').textContent = title;
        modalEl.querySelector('#ella-confirm-message').textContent = message;

        const okBtn = modalEl.querySelector('#ella-confirm-ok');
        okBtn.textContent = confirmText;
        okBtn.className = `btn ${confirmClass} px-4`;

        modalEl.querySelector('#ella-confirm-cancel').textContent = cancelText;
        modalEl.querySelector('#ella-confirm-icon').innerHTML =
            `<i class="fa-solid ${icon} fa-3x ${iconColor}"></i>`;

        // Create promise
        return new Promise((resolve) => {
            resolvePromise = (confirmed) => {
                if (confirmed && onConfirm) onConfirm();
                if (!confirmed && onCancel) onCancel();
                resolve(confirmed);
            };

            bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();
        });
    }

    /**
     * Shorthand for simple yes/no question
     * @param {string} message
     * @returns {Promise<boolean>}
     */
    function ask(message, title) {
        return show({ title: title || 'Confirm', message });
    }

    return { show, ask };
})();
