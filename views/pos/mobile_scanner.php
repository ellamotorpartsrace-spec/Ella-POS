<?php
// views/pos/mobile_scanner.php
// The "Mobile Wedge" page for Type-C / Bluetooth hardware scanners
require_once '../../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ella POS - Mobile Scanner Wedge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f8fafc;
            --accent-color: #3b82f6;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .scanner-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
            gap: 20px;
        }

        .status-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .scan-history {
            flex: 1;
            background: var(--card-bg);
            border-radius: 16px;
            padding: 15px;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .history-item {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .history-item:last-child {
            border-bottom: none;
        }

        /* THE KEY COMPONENT: Hidden input that captures HWID/HID scanner input */
        #scanner-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .pulse-active {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.05);
                opacity: 0.8;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .setup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg-color);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        #last-scanned {
            font-size: 2rem;
            font-weight: 800;
            color: var(--accent-color);
            margin: 10px 0;
            letter-spacing: 2px;
        }
    </style>
</head>

<body>

    <!-- Setup Overlay (shown if no HWID/Terminal) -->
    <div id="setup-overlay" class="setup-overlay d-none">
        <div class="card bg-dark text-white border-0 shadow-lg w-100" style="max-width: 400px; border-radius: 20px;">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-3"><i class="fa-solid fa-mobile-screen me-2"></i>Scanner Setup</h4>
                <p class="small text-muted">Enter the registration token and terminal ID provided by your POS
                    administrator.</p>

                <div class="mb-3">
                    <label class="form-label small fw-bold">HWID TOKEN</label>
                    <input type="text" id="setup-hwid" class="form-control bg-secondary text-white border-0"
                        placeholder="Paste token here...">
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">TERMINAL ID</label>
                    <input type="text" id="setup-terminal" class="form-control bg-secondary text-white border-0"
                        placeholder="e.g., POS-001">
                </div>

                <button class="btn btn-primary w-100 fw-bold py-3" onclick="MobileScanner.saveSetup()">
                    <i class="fa-solid fa-check-circle me-2"></i>ACTIVATE SCANNER
                </button>
            </div>
        </div>
    </div>

    <div class="scanner-container">
        <!-- Status & Last Scan -->
        <div class="status-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-success" id="connection-status">
                    <i class="fa-solid fa-circle-check me-1"></i>CONNECTED
                </span>
                <span class="small text-muted" id="device-info">Device: ---</span>
            </div>

            <p class="small text-uppercase fw-bold text-muted mb-1">Ready to Scan</p>
            <div id="last-scanned">---</div>
            <p class="small text-muted mb-0" id="last-item-name">Waiting for hardware input...</p>

            <div class="mt-3">
                <button class="btn btn-sm btn-outline-secondary opacity-50" onclick="MobileScanner.resetSetup()">
                    <i class="fa-solid fa-gear me-1"></i>Change Settings
                </button>
            </div>
        </div>

        <!-- History -->
        <div class="scan-history" id="scan-history">
            <div class="text-center py-5 text-muted opacity-50">
                <i class="fa-solid fa-history fa-2x mb-2"></i>
                <p class="small">Scan history will appear here</p>
            </div>
        </div>

        <!-- Feedback Toaster -->
        <div id="toast-container" class="position-fixed bottom-0 start-50 translate-middle-x mb-4"
            style="z-index: 1050;"></div>
    </div>

    <!-- The invisible focus field for Type-C Scanner -->
    <input type="text" id="scanner-input" inputmode="none" autocomplete="off">

    <script>
        const MobileScanner = {
            hwid: null,
            terminalId: null,
            deviceName: null,
            inputField: null,
            isProcessing: false,

            init() {
                this.hwid = localStorage.getItem('scanner_hwid');
                this.terminalId = localStorage.getItem('scanner_terminal');
                this.inputField = document.getElementById('scanner-input');

                if (!this.hwid || !this.terminalId) {
                    document.getElementById('setup-overlay').classList.remove('d-none');
                    return;
                }

                this.verifyDevice();
                this.startFocusLock();
                this.setupInputListeners();

                // Prevent screen sleep if supported
                if ('wakeLock' in navigator) {
                    try { navigator.wakeLock.request('screen'); } catch (err) { }
                }
            },

            async verifyDevice() {
                try {
                    const res = await fetch(`../../api/pos/scanner_register.php?action=verify&hwid=${this.hwid}`);
                    const data = await res.json();
                    if (data.success) {
                        this.deviceName = data.device_name;
                        document.getElementById('device-info').innerText = `Device: ${this.deviceName} | Terminal: ${this.terminalId}`;
                    } else {
                        alert(data.error || 'Device verification failed');
                        this.resetSetup();
                    }
                } catch (e) {
                    console.error('Verify error:', e);
                }
            },

            saveSetup() {
                const hwid = document.getElementById('setup-hwid').value.trim();
                const terminal = document.getElementById('setup-terminal').value.trim();

                if (!hwid || !terminal) return alert('Both fields are required');

                localStorage.setItem('scanner_hwid', hwid);
                localStorage.setItem('scanner_terminal', terminal);
                window.location.reload();
            },

            resetSetup() {
                localStorage.removeItem('scanner_hwid');
                localStorage.removeItem('scanner_terminal');
                window.location.reload();
            },

            startFocusLock() {
                // Keep the input focused at all times
                setInterval(() => {
                    if (document.activeElement !== this.inputField) {
                        this.inputField.focus();
                    }
                }, 500);
                this.inputField.focus();

                // Also focus on click anywhere
                document.body.addEventListener('click', () => this.inputField.focus());
            },

            setupInputListeners() {
                this.inputField.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        const barcode = this.inputField.value.trim();
                        if (barcode) {
                            this.sendScan(barcode);
                            this.inputField.value = '';
                        }
                    }
                });
            },

            async sendScan(barcode) {
                if (this.isProcessing) return;
                this.isProcessing = true;

                // Update UI
                document.getElementById('last-scanned').innerText = barcode;
                document.getElementById('last-scanned').classList.add('pulse-active');

                try {
                    const res = await fetch('../../api/pos/scanner_relay.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            hwid: this.hwid,
                            terminal_id: this.terminalId,
                            barcode: barcode
                        })
                    });
                    const data = await res.json();

                    if (data.success) {
                        this.addToHistory(barcode, 'Success');
                        this.showToast('Sent to POS', 'success');
                    } else {
                        this.showToast(data.error || 'Failed to relay', 'danger');
                    }
                } catch (e) {
                    this.showToast('Network error', 'danger');
                } finally {
                    this.isProcessing = false;
                    setTimeout(() => {
                        document.getElementById('last-scanned').classList.remove('pulse-active');
                    }, 500);
                }
            },

            addToHistory(barcode, status) {
                const history = document.getElementById('scan-history');
                // Remove empty state if present
                if (history.querySelector('.opacity-50')) history.innerHTML = '';

                const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                const item = document.createElement('div');
                item.className = 'history-item animated fadeIn';
                item.innerHTML = `
                    <div>
                        <div class="fw-bold text-accent">${barcode}</div>
                        <div class="small text-muted">${time}</div>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Relayed</span>
                `;
                history.prepend(item);

                // Keep only last 20
                if (history.children.length > 20) history.lastChild.remove();
            },

            showToast(msg, type) {
                const container = document.getElementById('toast-container');
                const toast = document.createElement('div');
                toast.className = `alert alert-${type} shadow-lg py-2 px-3 mb-0 animated fadeInUp`;
                toast.style.borderRadius = '30px';
                toast.innerHTML = `<i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${msg}`;
                container.appendChild(toast);
                setTimeout(() => {
                    toast.classList.add('fadeOutDown');
                    setTimeout(() => toast.remove(), 500);
                }, 2000);
            }
        };

        document.addEventListener('DOMContentLoaded', () => MobileScanner.init());
    </script>
</body>

</html>