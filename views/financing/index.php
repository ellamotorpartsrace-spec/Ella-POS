<?php
// views/financing/index.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Auth Check
requirePermission('view_finance');

$page_title = 'Financing Monitoring - Ella POS';
require_once '../../includes/header.php';
?>
<style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f8f9fa;
    }

    .stat-card {
        transition: transform 0.2s;
        border-radius: 12px;
    }

    .stat-card:hover {
        transform: translateY(-3px);
    }

    .table-responsive {
        border-radius: 8px;
        overflow: hidden;
    }

    .table th {
        background-color: #f1f3f5;
        color: #495057;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #dee2e6;
        padding: 12px 16px;
    }

    .table td {
        vertical-align: middle;
        color: #343a40;
        font-size: 0.9rem;
        padding: 12px 16px;
        border-bottom: 1px solid #edf2f9;
    }

    .badge-method {
        font-size: 0.70rem;
        padding: 3px 6px;
        border-radius: 4px;
    }

    .badge-provider {
        font-size: 0.72rem;
        padding: 4px 8px;
        border-radius: 6px;
        font-weight: 600;
    }

    .items-list {
        font-size: 0.8rem;
        color: #6c757d;
        max-width: 250px;
    }
</style>

<?php include '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold text-dark"><i class="fa-solid fa-building-columns text-primary me-2"></i>Financing
            Monitoring</h4>
        <p class="text-muted mb-0 small">Track all sales processed via financing installment (Home Credit, Skyro, Billease, etc.).</p>
    </div>
</div>

<!-- Stats Summary -->
<div class="row g-3 mb-4" id="stats-row">
    <div class="col-12 col-md-4">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                        <i class="fa-solid fa-receipt text-primary fa-lg"></i>
                    </div>
                    <div>
                        <div class="h4 fw-bold mb-0" id="stat-count">-</div>
                        <small class="text-muted">Total Transactions</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-info bg-opacity-10 p-2 me-3">
                        <i class="fa-solid fa-money-bill-transfer text-info fa-lg"></i>
                    </div>
                    <div>
                        <div class="h5 fw-bold mb-0" id="stat-financed">₱0</div>
                        <small class="text-muted">Total Financed Amount</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3">
                        <i class="fa-solid fa-hand-holding-dollar text-success fa-lg"></i>
                    </div>
                    <div>
                        <div class="h5 fw-bold mb-0" id="stat-downpayment">₱0</div>
                        <small class="text-muted">Total DP Collected</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-4 filter-section">
    <div class="card-body p-3">
        <form id="filter-form" class="row g-3 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">FROM <span class="text-muted fw-normal">(Last 30
                        days)</span></label>
                <input type="date" name="date_from" id="filter-date-from" class="form-control form-control-sm"
                    value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">TO</label>
                <input type="date" name="date_to" id="filter-date-to" class="form-control form-control-sm"
                    value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">PROVIDER</label>
                <select name="provider" id="filter-provider" class="form-select form-select-sm">
                    <option value="">All Providers</option>
                    <option value="Home Credit">Home Credit</option>
                    <option value="Skyro">Skyro</option>
                    <option value="Salmon">Salmon</option>
                    <option value="Billease">Billease</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">SEARCH</label>
                <div class="input-group">
                    <input type="text" name="search" id="filter-search" class="form-control form-control-sm"
                        placeholder="Ref no, customer..." autocomplete="off">
                </div>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="FinancingTracker.reset()"
                    title="Reset Filters">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <button type="button" class="btn btn-success btn-sm" onclick="FinancingTracker.exportCSV()"
                    title="Export CSV">
                    <i class="fa-solid fa-file-export"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Results Table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
        <h6 class="mb-0 fw-bold">
            <i class="fa-solid fa-list text-primary me-2"></i>Records
        </h6>
    </div>
    <div class="card-body p-0">
        <div id="loading-state" class="text-center py-5 d-none">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2">Loading records...</p>
        </div>

        <div id="empty-state" class="text-center py-5 d-none">
            <i class="fa-solid fa-inbox fa-3x text-muted opacity-25 mb-3"></i>
            <h6 class="text-muted">No financing sales found.</h6>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="records-table">
                <thead>
                    <tr>
                        <th>Date & POS Ref</th>
                        <th>Customer</th>
                        <th>Provider</th>
                        <th>Items Sold</th>
                        <th>Contract Ref</th>
                        <th class="text-end">Down Payment</th>
                        <th class="text-end">Financed</th>
                        <th class="text-end">Grand Total</th>
                    </tr>
                </thead>
                <tbody id="financing-tbody">
                    <!-- Filled via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const formatCurrency = (val) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(val || 0);
    const formatDate = (dateStr) => {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
    };

    const getMethodBadge = (method) => {
        if (!method) return '';
        method = method.toLowerCase();
        switch (method) {
            case 'cash': return '<span class="badge bg-success badge-method">CASH</span>';
            case 'gcash': return '<span class="badge bg-primary badge-method">GCASH</span>';
            case 'bank_transfer': return '<span class="badge bg-info badge-method">BANK</span>';
            default: return `<span class="badge bg-secondary badge-method">${method.toUpperCase()}</span>`;
        }
    };

    const getProviderBadge = (provider) => {
        if (!provider) provider = 'Home Credit';
        const colors = {
            'Home Credit': 'bg-danger-subtle text-danger',
            'Skyro': 'bg-primary-subtle text-primary',
            'Salmon': 'bg-warning-subtle text-warning',
            'Billease': 'bg-success-subtle text-success',
            'Other': 'bg-secondary-subtle text-secondary'
        };
        const colorClass = colors[provider] || 'bg-warning-subtle text-warning';
        return `<span class="badge badge-provider ${colorClass}">${provider}</span>`;
    };

    const FinancingTracker = {
        init() {
            this.form = document.getElementById('filter-form');
            this.tbody = document.getElementById('financing-tbody');
            this.loading = document.getElementById('loading-state');
            this.empty = document.getElementById('empty-state');
            this.table = document.getElementById('records-table');

            this.bindEvents();
            this.fetchData();
        },

        bindEvents() {
            this.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.fetchData();
            });
        },

        reset() {
            document.getElementById('filter-search').value = '';
            document.getElementById('filter-provider').value = '';
            this.fetchData();
        },

        async fetchData() {
            this.loading.classList.remove('d-none');
            this.table.classList.add('d-none');
            this.empty.classList.add('d-none');

            const params = new URLSearchParams(new FormData(this.form));

            try {
                const response = await fetch(`<?= BASE_URL ?>api/financing/list.php?${params.toString()}`);
                const data = await response.json();

                if (data.success) {
                    this.renderStats(data.stats);
                    this.renderTable(data.transactions);
                } else {
                    EllaToast.error('Error fetching data: ' + data.error);
                }
            } catch (error) {
                console.error(error);
                EllaToast.error('A network error occurred.');
            } finally {
                this.loading.classList.add('d-none');
            }
        },

        renderStats(stats) {
            document.getElementById('stat-count').textContent = stats.count;
            document.getElementById('stat-financed').textContent = formatCurrency(stats.total_financed);
            document.getElementById('stat-downpayment').textContent = formatCurrency(stats.total_downpayment);
        },

        renderTable(transactions) {
            if (!transactions || transactions.length === 0) {
                this.empty.classList.remove('d-none');
                return;
            }

            this.table.classList.remove('d-none');
            this.tbody.innerHTML = transactions.map(t => {

                const dpAmount = parseFloat(t.down_payment_amount || 0);
                const financedAmount = parseFloat(t.financed_amount || 0);
                const statusBadge = t.status === 'voided' ? '<span class="badge bg-danger ms-2">VOIDED</span>' : '';

                return `
                <tr class="${t.status === 'voided' ? 'opacity-50' : ''}">
                    <td>
                        <div class="fw-bold text-dark">${t.sale_ref}</div>
                        <div class="text-muted small">${formatDate(t.created_at)}</div>
                    </td>
                    <td>
                        <div class="fw-bold text-dark">${t.customer_name}</div>
                        ${statusBadge}
                    </td>
                    <td>
                        ${getProviderBadge(t.financing_provider)}
                    </td>
                    <td>
                        <div class="items-list text-break">${t.items_sold ? t.items_sold : '<em class="text-muted">No items</em>'}</div>
                    </td>
                    <td>
                        <div class="fw-bold text-primary font-monospace">${t.contract_ref || 'N/A'}</div>
                    </td>
                    <td class="text-end">
                        <div class="fw-bold text-success">${formatCurrency(dpAmount)}</div>
                        ${dpAmount > 0 ? getMethodBadge(t.down_payment_method) : ''}
                    </td>
                    <td class="text-end fw-bold text-info">
                        ${formatCurrency(financedAmount)}
                    </td>
                    <td class="text-end fw-bold text-dark fs-6">
                        ${formatCurrency(t.grand_total)}
                    </td>
                </tr>
            `;
            }).join('');
        },

        exportCSV() {
            const params = new URLSearchParams(new FormData(this.form));
            window.location.href = `<?= BASE_URL ?>api/financing/export.php?${params.toString()}`;
        }
    };

    document.addEventListener('DOMContentLoaded', () => FinancingTracker.init());
</script>

<?php require_once '../../includes/footer.php'; ?>
