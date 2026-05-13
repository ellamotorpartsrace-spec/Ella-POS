/* =====================================================
   OFFLINE QUEUE
   Queues failed sales for retry when connection restores
===================================================== */

const OfflineQueue = {
    STORAGE_KEY: 'pos_offline_queue',

    // Get all queued sales
    getQueue() {
        try {
            return JSON.parse(localStorage.getItem(this.STORAGE_KEY) || '[]');
        } catch (e) { return []; }
    },

    // Save queue to localStorage
    _saveQueue(queue) {
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(queue));
        this.updateBadge();
    },

    // Add a failed sale to the queue
    enqueue(payload) {
        const queue = this.getQueue();
        queue.push({
            id: Date.now() + '_' + Math.random().toString(36).substr(2, 6),
            payload: payload,
            timestamp: new Date().toISOString(),
            retries: 0
        });
        this._saveQueue(queue);
        EllaToast.warning('Sale saved offline — will sync when connection returns');
        console.log('📦 Sale queued offline. Total pending:', queue.length);
    },

    // Remove a sale from queue by id
    dequeue(id) {
        const queue = this.getQueue().filter(s => s.id !== id);
        this._saveQueue(queue);
    },

    // Retry all queued sales
    async syncAll() {
        const queue = this.getQueue();
        if (queue.length === 0) return;

        console.log(`🔄 Syncing ${queue.length} offline sale(s)...`);
        let synced = 0;
        let failed = 0;

        for (const sale of queue) {
            try {
                const res = await fetch('../../api/pos/save_sale.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(sale.payload)
                });
                const data = await res.json();

                if (data.success) {
                    this.dequeue(sale.id);
                    synced++;
                    console.log(`✅ Synced offline sale ${sale.id}`);
                } else {
                    // Server rejected — increment retry count
                    sale.retries++;
                    if (sale.retries >= 3) {
                        // Too many failures — keep in queue but log
                        console.error(`❌ Sale ${sale.id} failed after 3 retries:`, data.error);
                    }
                    failed++;
                }
            } catch (e) {
                // Still offline — stop trying
                console.warn('Still offline, stopping sync');
                failed++;
                break;
            }
        }

        if (synced > 0) {
            EllaToast.success(`${synced} offline sale${synced > 1 ? 's' : ''} synced successfully!`);
        }
        if (failed > 0) {
            this._saveQueue(this.getQueue()); // Re-save with updated retry counts
        }
    },

    // Update the pending sales badge count
    updateBadge() {
        const count = this.getQueue().length;
        let badge = document.getElementById('offline-queue-badge');

        if (count === 0) {
            if (badge) badge.classList.add('d-none');
            return;
        }

        if (!badge) {
            // Create badge near the PAY button area
            badge = document.createElement('div');
            badge.id = 'offline-queue-badge';
            badge.style.cssText = `
                position: fixed; bottom: 80px; right: 20px; z-index: 9999;
                background: #f59e0b; color: #000; padding: 8px 14px;
                border-radius: 10px; font-size: 12px; font-weight: 700;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                cursor: pointer; display: flex; align-items: center; gap: 6px;
            `;
            badge.onclick = () => this.showQueueDetails();
            document.body.appendChild(badge);
        }

        badge.innerHTML = `<i class="fa-solid fa-cloud-arrow-up"></i> ${count} pending sale${count > 1 ? 's' : ''}`;
        badge.classList.remove('d-none');
    },

    // Show details of queued sales
    async showQueueDetails() {
        const queue = this.getQueue();
        if (queue.length === 0) {
            EllaToast.info('No pending offline sales');
            return;
        }

        const items = queue.map(s => {
            const time = new Date(s.timestamp).toLocaleTimeString();
            const total = s.payload.grand_total || 0;
            const buyer = s.payload.buyer?.buyer_name || 'Walk-in';
            return `• ${buyer} — ₱${total.toFixed(2)} (${time}, ${s.retries} retries)`;
        }).join('\n');

        const confirmed = await EllaConfirm.show({
            title: 'Pending Offline Sales',
            message: `${queue.length} sale(s) waiting to sync:\n\n${items}`,
            confirmText: 'Retry Now',
            confirmClass: 'btn-primary',
            icon: 'fa-cloud-arrow-up',
            iconColor: 'text-warning'
        });

        if (confirmed) {
            await this.syncAll();
        }
    },

    // Initialize — update badge and check on load
    init() {
        this.updateBadge();
    }
};

// Expose globally
window.OfflineQueue = OfflineQueue;
