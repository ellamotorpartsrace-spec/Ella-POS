/**
 * ============================================================
 * ELLA HOTKEYS — Global Keyboard Shortcuts
 * ============================================================
 * Press F1 or click the "?" badge to view all shortcuts.
 */

const EllaHotkeys = (() => {
    // Detect current page
    const currentPage = window.location.pathname;
    const isPOS = currentPage.includes('simple_checkout');

    // Shortcut definitions
    const GLOBAL_SHORTCUTS = [
        { key: 'F1', desc: 'Show keyboard shortcuts', action: () => toggleHelp() },
        { key: 'Alt+Shift+S', desc: 'Go to POS Terminal', action: () => navigateTo('views/pos/simple_checkout.php') },
        { key: 'Alt+Shift+I', desc: 'Go to Inventory', action: () => navigateTo('views/inventory/index.php') },
        { key: 'Alt+Shift+D', desc: 'Go to Dashboard', action: () => navigateTo('views/dashboard/index.php') },
        { key: 'Escape', desc: 'Close modal / dismiss', action: () => closeTopModal() },
    ];

    const POS_SHORTCUTS = [
        { key: 'Alt+Shift+S', desc: 'Focus product search' },
        { key: 'Alt+Shift+F', desc: 'Focus cart filter' },
        { key: 'Alt+Shift+P', desc: 'Process Payment (Pay)' },
        { key: 'Alt+Shift+E', desc: 'Set Exact Amount' },
        { key: 'Alt+Shift+H', desc: 'Hold current cart' },
        { key: 'Alt+Shift+B', desc: 'Backup/Save Draft' },
        { key: 'Alt+Shift+L', desc: 'Load Drafts' },
        { key: 'Alt+Shift+Z', desc: 'Undo last action' },
    ];

    const CART_NAV_SHORTCUTS = [
        { key: 'Alt+Up/Down', desc: 'Navigate between items' },
        { key: 'Alt+Enter', desc: 'Open Qty Pad' },
        { key: 'Alt+Delete', desc: 'Remove highlighted item' },
    ];

    let helpVisible = false;
    let overlayEl = null;
    let modalEl = null;

    /** Initialize on DOM ready */
    function init() {
        document.addEventListener('keydown', handleKeydown);
        createBadge();
        createHelpModal();
    }

    /** Parse a shortcut key string like "Alt+S" into a matcher */
    function matchKey(e, keyStr) {
        const parts = keyStr.split('+');
        const mainKey = parts[parts.length - 1];
        const needAlt = parts.includes('Alt');
        const needCtrl = parts.includes('Ctrl');
        const needShift = parts.includes('Shift');

        if (needAlt !== e.altKey) return false;
        if (needCtrl !== e.ctrlKey) return false;
        if (needShift !== e.shiftKey) return false;

        // Match the main key
        if (e.key === mainKey || e.key === mainKey.toUpperCase() || e.key === mainKey.toLowerCase()) return true;
        if (e.code === mainKey) return true;
        return false;
    }

    /** Main keydown handler */
    function handleKeydown(e) {
        // Always allow F1 and Escape regardless of focus
        if (e.key === 'F1') {
            e.preventDefault();
            toggleHelp();
            return;
        }

        if (e.key === 'Escape') {
            if (helpVisible) {
                toggleHelp();
                return;
            }
            closeTopModal();
            return;
        }

        // Don't intercept if typing in an input
        const tag = e.target.tagName;
        const isEditable = e.target.isContentEditable;
        const isInput = (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') && !e.target.readOnly;
        if (isInput || isEditable) return;

        // Check global shortcuts (Navigation)
        for (const s of GLOBAL_SHORTCUTS) {
            if (s.key === 'F1' || s.key === 'Escape') continue;
            if (matchKey(e, s.key) && s.action) {
                e.preventDefault();
                s.action();
                return;
            }
        }

        // NOTE: POS-specific shortcuts are now handled by views/pos/pos-init.js
        // to ensure deep integration with the cart state.
    }

    /** Navigate to a page using BASE_URL */
    function navigateTo(path) {
        const base = (typeof BASE_URL !== 'undefined') ? BASE_URL : '/ella-pos/';
        window.location.href = base + path;
    }

    /** Focus an element by selector */
    function focusEl(selector) {
        const el = document.querySelector(selector);
        if (el) {
            el.focus();
            el.select && el.select();
        }
    }

    /** Click an element by selector */
    function clickEl(selector) {
        const el = document.querySelector(selector);
        if (el && !el.disabled) el.click();
    }

    /** Close the topmost open Bootstrap modal */
    function closeTopModal() {
        const openModals = document.querySelectorAll('.modal.show');
        if (openModals.length > 0) {
            const last = openModals[openModals.length - 1];
            const bsModal = bootstrap.Modal.getInstance(last);
            if (bsModal) bsModal.hide();
        }
    }

    /** Create the floating "?" badge */
    function createBadge() {
        const badge = document.createElement('button');
        badge.className = 'ella-hotkey-badge';
        badge.innerHTML = '?';
        badge.title = 'Keyboard Shortcuts (F1)';
        badge.addEventListener('click', () => toggleHelp());
        document.body.appendChild(badge);
    }

    /** Create the help modal */
    function createHelpModal() {
        // Overlay
        overlayEl = document.createElement('div');
        overlayEl.className = 'ella-hotkey-overlay';
        overlayEl.addEventListener('click', () => toggleHelp());
        document.body.appendChild(overlayEl);

        // Modal
        modalEl = document.createElement('div');
        modalEl.className = 'ella-hotkey-modal';

        let html = `
            <button class="ella-hotkey-close" title="Close"><i class="fa-solid fa-xmark"></i></button>
            <h5><i class="fa-solid fa-keyboard text-primary"></i> Keyboard Shortcuts</h5>

            <div class="ella-hotkey-section">
                <h6>🌐 Global (Navigation)</h6>
                ${GLOBAL_SHORTCUTS.map(s => shortcutRow(s.key, s.desc)).join('')}
            </div>
        `;

        if (isPOS) {
            html += `
                <div class="ella-hotkey-section">
                    <h6>💰 POS Actions</h6>
                    ${POS_SHORTCUTS.map(s => shortcutRow(s.key, s.desc)).join('')}
                </div>
                <div class="ella-hotkey-section">
                    <h6>🛒 Cart Navigation</h6>
                    ${CART_NAV_SHORTCUTS.map(s => shortcutRow(s.key, s.desc)).join('')}
                </div>
            `;
        }

        modalEl.innerHTML = html;
        modalEl.querySelector('.ella-hotkey-close').addEventListener('click', () => toggleHelp());
        document.body.appendChild(modalEl);
    }

    /** Render a shortcut row */
    function shortcutRow(key, desc) {
        const keys = key.split('+').map(k => `<span class="ella-kbd">${k}</span>`).join(' + ');
        return `
            <div class="ella-hotkey-row">
                <span class="ella-hotkey-label">${desc}</span>
                <span>${keys}</span>
            </div>
        `;
    }

    /** Toggle help modal */
    function toggleHelp() {
        helpVisible = !helpVisible;
        if (helpVisible) {
            overlayEl?.classList.add('active');
            modalEl?.classList.add('active');
        } else {
            overlayEl?.classList.remove('active');
            modalEl?.classList.remove('active');
        }
    }

    // Auto-init when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return { toggleHelp };
})();
