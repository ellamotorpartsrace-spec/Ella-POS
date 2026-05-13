<?php
// views/system/scanner_devices.php - Scanner Device Management
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requireLogin();
requirePermission('manage_settings');

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    .device-card {
        border: none;
        border-radius: 16px;
        transition: all 0.3s ease;
    }
    .device-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .status-active { color: #22c55e; }
    .status-inactive { color: #ef4444; }
    .hwid-token {
        font-family: 'Courier New', monospace;
        background: #f1f5f9;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.9rem;
    }
    .qr-container {
        padding: 20px;
        background: white;
        border-radius: 12px;
        display: inline-block;
    }
</style>

<div class="container-fluid p-3 p-lg-4">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="fa-solid fa-mobile-screen text-primary me-2"></i>Scanner Devices
            </h4>
            <p class="text-muted mb-0 small">Register and manage mobile devices used as wireless hardware scanners</p>
        </div>
        <button class="btn btn-primary px-4 shadow-sm" onclick="ScannerManager.openAddModal()">
            <i class="fa-solid fa-plus me-2"></i>Register New Device
        </button>
    </div>

    <div class="row g-4">
        <!-- Main Devices List -->
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="text-muted small text-uppercase">
                                    <th class="ps-4">Device Name</th>
                                    <th>HWID Token</th>
                                    <th>Status</th>
                                    <th>Registered At</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="device-table-body">
                                <!-- Loaded via JS -->
                            </tbody>
                        </table>
                    </div>
                    <div id="empty-state" class="text-center py-5 d-none">
                        <i class="fa-solid fa-mobile-screen fa-3x text-muted opacity-25 mb-3"></i>
                        <h6 class="text-muted">No devices registered yet</h6>
                        <p class="small text-muted">Register a phone to start scanning wirelessly</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info & Setup Card -->
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-circle-info text-info me-2"></i>How it works</h6>
                    <ol class="small text-muted ps-3 mb-4">
                        <li class="mb-2">Register a device here to get a unique <strong>HWID Token</strong>.</li>
                        <li class="mb-2">Open the <strong>Mobile Scanner URL</strong> on the target phone/tablet.</li>
                        <li class="mb-2">Enter the token and the <strong>Terminal ID</strong> of the POS station.</li>
                        <li>Connect a <strong>Type-C Scanner</strong> to the phone. Every scan will relay to the POS!</li>
                    </ol>
                    
                    <div class="bg-light p-3 rounded-3 mb-3 text-center">
                        <p class="small fw-bold mb-2">Mobile Scanner URL:</p>
                        <code class="d-block mb-3 p-2 bg-white border rounded" id="scanner-url"></code>
                        <div id="qrcode" class="qr-container shadow-sm mb-2"></div>
                        <p class="small text-muted mt-2">Scan this QR to open the mobile wedge</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Device Modal -->
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 ps-4 pt-4">
                <h5 class="modal-title fw-bold">Register Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">DEVICE NAME</label>
                    <input type="text" id="new-device-name" class="form-control form-control-lg border-2" placeholder="e.g. Warehouse Samsung A54">
                </div>
                <div id="registration-success" class="d-none">
                    <div class="alert alert-success border-0 small mb-3">
                        <i class="fa-solid fa-check-circle me-2"></i>Device registered! Copy the token below:
                    </div>
                    <div class="bg-light p-3 rounded-3 text-center mb-3">
                        <p class="small fw-bold text-muted mb-1">HWID TOKEN</p>
                        <h4 class="fw-bold text-primary mb-2" id="generated-hwid" style="word-break: break-all; font-family: monospace;"></h4>
                        <button class="btn btn-sm btn-dark px-3" onclick="ScannerManager.copyToken()">
                            <i class="fa-solid fa-copy me-1"></i> Copy Token
                        </button>
                    </div>
                    <p class="small text-danger"><i class="fa-solid fa-warning me-1"></i> This token is only shown once. Save it!</p>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" id="btn-close-modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="btn-register" onclick="ScannerManager.register()">Register</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    const ScannerManager = {
        modal: null,
        scannerBaseUrl: window.location.origin + '/ella-pos/views/pos/mobile_scanner.php',

        init() {
            this.modal = new bootstrap.Modal(document.getElementById('addDeviceModal'));
            document.getElementById('scanner-url').innerText = this.scannerBaseUrl;
            new QRCode(document.getElementById("qrcode"), {
                text: this.scannerBaseUrl,
                width: 150,
                height: 150
            });
            this.loadDevices();
        },

        async loadDevices() {
            try {
                const res = await fetch('../../api/pos/scanner_register.php?action=list');
                const data = await res.json();
                if (data.success) {
                    this.renderDevices(data.data);
                }
            } catch (err) { console.error(err); }
        },

        renderDevices(devices) {
            const tbody = document.getElementById('device-table-body');
            const empty = document.getElementById('empty-state');
            tbody.innerHTML = '';
            
            if (devices.length === 0) {
                empty.classList.remove('d-none');
                return;
            }
            
            empty.classList.add('d-none');
            devices.forEach(d => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-4">
                        <div class="fw-bold">${this.escapeHtml(d.device_name)}</div>
                    </td>
                    <td><code class="hwid-token">${d.hwid_masked}</code></td>
                    <td>
                        <span class="badge ${d.status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'} px-3">
                            ${d.status.toUpperCase()}
                        </span>
                    </td>
                    <td class="text-muted small">${new Date(d.created_at).toLocaleDateString()}</td>
                    <td class="text-end pe-4">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                <li><a class="dropdown-item" href="#" onclick="ScannerManager.toggleStatus(${d.id})">
                                    <i class="fa-solid fa-power-off me-2"></i> ${d.status === 'active' ? 'Deactivate' : 'Activate'}
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="ScannerManager.delete(${d.id})">
                                    <i class="fa-solid fa-trash me-2"></i> Remove Device
                                </a></li>
                            </ul>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        },

        openAddModal() {
            document.getElementById('registration-success').classList.add('d-none');
            document.getElementById('btn-register').classList.remove('d-none');
            document.getElementById('new-device-name').value = '';
            this.modal.show();
        },

        async register() {
            const name = document.getElementById('new-device-name').value.trim();
            if (!name) return alert('Name is required');

            const btn = document.getElementById('btn-register');
            btn.disabled = true;

            try {
                const res = await fetch('../../api/pos/scanner_register.php?action=register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ device_name: name })
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('generated-hwid').innerText = data.hwid;
                    document.getElementById('registration-success').classList.remove('d-none');
                    btn.classList.add('d-none');
                    this.loadDevices();
                } else {
                    alert(data.error);
                }
            } catch (err) { alert('Error registering'); }
            finally { btn.disabled = false; }
        },

        async toggleStatus(id) {
            if (!confirm('Toggle device status?')) return;
            await fetch('../../api/pos/scanner_register.php?action=toggle', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            this.loadDevices();
        },

        async delete(id) {
            if (!confirm('Are you sure you want to remove this device? It will stop working immediately.')) return;
            await fetch('../../api/pos/scanner_register.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            this.loadDevices();
        },

        copyToken() {
            const token = document.getElementById('generated-hwid').innerText;
            navigator.clipboard.writeText(token).then(() => {
                const btn = event.target.closest('button');
                const old = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Copied!';
                btn.classList.replace('btn-dark', 'btn-success');
                setTimeout(() => {
                    btn.innerHTML = old;
                    btn.classList.replace('btn-success', 'btn-dark');
                }, 2000);
            });
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    document.addEventListener('DOMContentLoaded', () => ScannerManager.init());
</script>

<?php require_once '../../includes/footer.php'; ?>
