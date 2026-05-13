<?php
// views/receivables/ledger.php
// Combined Accounting Ledger for Buyers

require_once '../../config/config.php';
require_once '../../includes/auth.php';

requirePermission('view_receivables');

$page_title = 'Buyer Ledger - Ella POS';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Fetch buyers for the dropdown
$buyers = $conn->query("SELECT buyer_id, buyer_name, shop_name FROM buyers ORDER BY buyer_name")->fetchAll(PDO::FETCH_ASSOC);

$selected_buyer_id = isset($_GET['buyer_id']) ? intval($_GET['buyer_id']) : '';
?>

<style>
    .ledger-page { animation: ellaFadeIn 0.5s ease-out; }
    
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 32px; }
    .stat-card {
        background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 24px;
        padding: 24px; display: flex; align-items: center; gap: 20px; transition: all 0.3s;
        position: relative; overflow: hidden;
    }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.08); border-color: var(--primary-color); }
    .stat-card .stat-icon {
        width: 60px; height: 60px; border-radius: 18px; display: flex; align-items: center;
        justify-content: center; font-size: 1.5rem; flex-shrink: 0;
    }
    .stat-card .stat-value { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); }
    .stat-card .stat-label { font-size: 0.8rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

    .icon-debit { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .icon-credit { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .icon-balance { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }

    .ledger-table-wrapper { border-radius: 24px; border: 1px solid var(--border-color); overflow: hidden; background: var(--card-bg); box-shadow: 0 10px 30px rgba(0,0,0,0.04); }
    .table-ledger thead th { background: var(--bg-surface); padding: 16px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); border-bottom: 1px solid var(--border-color); }
    .table-ledger tbody td { padding: 16px 20px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
    
    .text-debit { color: #ef4444; font-weight: 700; }
    .text-credit { color: #10b981; font-weight: 700; }
    .text-balance { font-weight: 800; }

    .print-only { display: none; }
    @media print {
        .no-print { display: none !important; }
        .print-only { display: block; }
        body { background: white; color: black; }
        .ledger-table-wrapper { border: none; box-shadow: none; }
        .table-ledger thead th { background: #f8f9fa !important; color: black !important; border: 1px solid #dee2e6 !important; }
        .table-ledger td { border: 1px solid #dee2e6 !important; }
    }

    .buyer-info-card {
        background: var(--bg-surface);
        border-radius: 20px;
        padding: 20px;
        margin-bottom: 24px;
        border: 1px solid var(--border-color);
    }
</style>

<div class="ledger-page container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h2 class="fw-800 mb-1"><i class="fas fa-book-bookmark me-3 text-primary"></i>Buyer Account Ledger</h2>
            <p class="text-secondary mb-0">Detailed transaction history and account balance tracker</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary px-4" style="border-radius: 12px;" onclick="window.print()">
                <i class="fas fa-print me-2"></i> Print Ledger
            </button>
            <button class="btn btn-primary px-4" style="border-radius: 12px;" id="btnExportCSV">
                <i class="fas fa-file-csv me-2"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4 no-print" style="border-radius: 20px;">
        <div class="card-body p-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">SELECT BUYER</label>
                    <select class="form-select form-select-lg" id="buyerFilter" style="border-radius: 12px;">
                        <option value="">-- Select Buyer --</option>
                        <?php foreach($buyers as $b): ?>
                            <option value="<?= $b['buyer_id'] ?>" <?= $selected_buyer_id == $b['buyer_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['buyer_name']) ?> <?= $b['shop_name'] ? '('.htmlspecialchars($b['shop_name']).')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">FROM DATE</label>
                    <input type="date" class="form-control form-control-lg" id="dateFrom" style="border-radius: 12px;">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">TO DATE</label>
                    <input type="date" class="form-control form-control-lg" id="dateTo" style="border-radius: 12px;">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-lg w-100" style="border-radius: 12px;" onclick="loadLedger()">
                        <i class="fas fa-sync-alt me-2"></i> Update
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Header -->
    <div class="print-only mb-4">
        <h2 class="text-center fw-bold mb-1">BUYER ACCOUNT LEDGER</h2>
        <p class="text-center text-secondary" id="printBuyerName">Buyer Name</p>
        <div class="row mt-4">
            <div class="col-6">
                <p class="mb-0 small">Report Period: <span id="printPeriod">All Time</span></p>
                <p class="mb-0 small">Generated On: <?= date('M d, Y h:i A') ?></p>
            </div>
            <div class="col-6 text-end">
                <p class="mb-0 fw-bold">Current Balance: <span id="printBalance">₱0.00</span></p>
            </div>
        </div>
    </div>

    <div id="ledgerContent" style="display: none;">
        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon icon-debit"><i class="fas fa-arrow-up"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Debits</div>
                    <div class="stat-value text-danger" id="statDebit">₱0.00</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-credit"><i class="fas fa-arrow-down"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Credits</div>
                    <div class="stat-value text-success" id="statCredit">₱0.00</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-balance"><i class="fas fa-scale-balanced"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Outstanding Balance</div>
                    <div class="stat-value text-primary" id="statBalance">₱0.00</div>
                </div>
            </div>
        </div>

        <!-- Buyer Quick Info -->
        <div class="buyer-info-card no-print">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="fw-bold mb-1" id="buyerNameDisp">--</h5>
                    <p class="text-muted small mb-0" id="buyerContactDisp">--</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="badge bg-light text-dark border p-2" id="buyerLimitDisp">Credit Limit: None</span>
                </div>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="ledger-table-wrapper">
            <div class="table-responsive">
                <table class="table table-hover table-ledger mb-0" id="ledgerTable">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Date</th>
                            <th style="width: 15%;">Reference</th>
                            <th style="width: 30%;">Description</th>
                            <th style="width: 13%;" class="text-end">Debit (+)</th>
                            <th style="width: 13%;" class="text-end">Credit (-)</th>
                            <th style="width: 14%;" class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data injected via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div id="emptyLedger" class="text-center py-5">
        <i class="fas fa-file-invoice fa-4x text-muted opacity-25 mb-3"></i>
        <h5 class="text-muted">Select a buyer to view account ledger</h5>
        <p class="text-secondary small">Transaction history will appear here once a buyer is selected</p>
    </div>

    <!-- Loading State -->
    <div id="loadingLedger" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="text-muted mt-2">Compiling ledger data...</p>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    let ledgerData = [];
    let buyerInfo = null;

    async function loadLedger() {
        const buyerId = document.getElementById('buyerFilter').value;
        if (!buyerId) {
            document.getElementById('ledgerContent').style.display = 'none';
            document.getElementById('emptyLedger').style.display = 'block';
            return;
        }

        document.getElementById('emptyLedger').style.display = 'none';
        document.getElementById('ledgerContent').style.display = 'none';
        document.getElementById('loadingLedger').style.display = 'block';

        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;

        try {
            const url = `../../api/receivables/get_ledger_data.php?buyer_id=${buyerId}&date_from=${dateFrom}&date_to=${dateTo}`;
            const res = await fetch(url);
            const data = await res.json();

            document.getElementById('loadingLedger').style.display = 'none';

            if (data.success) {
                ledgerData = data.ledger;
                buyerInfo = data.buyer;
                renderLedger(data);
                document.getElementById('ledgerContent').style.display = 'block';
            } else {
                EllaToast.error(data.error);
                document.getElementById('emptyLedger').style.display = 'block';
            }
        } catch (e) {
            document.getElementById('loadingLedger').style.display = 'none';
            EllaToast.error('Network error');
        }
    }

    function renderLedger(data) {
        // Update Stats
        document.getElementById('statDebit').innerText = '₱' + formatMoney(data.summary.total_debit);
        document.getElementById('statCredit').innerText = '₱' + formatMoney(data.summary.total_credit);
        document.getElementById('statBalance').innerText = '₱' + formatMoney(data.summary.current_balance);
        
        // Print header updates
        document.getElementById('printBuyerName').innerText = data.buyer.buyer_name + (data.buyer.shop_name ? ` (${data.buyer.shop_name})` : '');
        document.getElementById('printBalance').innerText = '₱' + formatMoney(data.summary.current_balance);
        const df = document.getElementById('dateFrom').value;
        const dt = document.getElementById('dateTo').value;
        document.getElementById('printPeriod').innerText = (df || dt) ? `${df || 'Start'} to ${dt || 'End'}` : 'All Time';

        // Buyer Info
        document.getElementById('buyerNameDisp').innerText = data.buyer.buyer_name + (data.buyer.shop_name ? ` · ${data.buyer.shop_name}` : '');
        document.getElementById('buyerContactDisp').innerText = (data.buyer.contact_number || 'No contact info') + (data.buyer.shop_name ? '' : '');
        document.getElementById('buyerLimitDisp').innerText = 'Credit Limit: ' + (data.buyer.credit_limit ? '₱' + formatMoney(data.buyer.credit_limit) : 'None');

        const tbody = document.querySelector('#ledgerTable tbody');
        tbody.innerHTML = '';

        if (data.ledger.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No transactions found for this period.</td></tr>';
            return;
        }

        data.ledger.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="small fw-bold">${new Date(row.entry_date).toLocaleDateString()}</div>
                    <div class="text-muted x-small">${new Date(row.entry_date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                </td>
                <td><span class="badge bg-light text-dark border fw-normal">${row.reference}</span></td>
                <td>${row.description}</td>
                <td class="text-end text-debit">${row.debit > 0 ? '₱' + formatMoney(row.debit) : '—'}</td>
                <td class="text-end text-credit">${row.credit > 0 ? '₱' + formatMoney(row.credit) : '—'}</td>
                <td class="text-end text-balance">₱${formatMoney(row.running_balance)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function formatMoney(v) { return parseFloat(v).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

    // Initial Load if ID provided
    $(document).ready(() => {
        if (document.getElementById('buyerFilter').value) {
            loadLedger();
        }
    });

    // CSV Export
    document.getElementById('btnExportCSV').onclick = () => {
        if (!ledgerData.length) return EllaToast.error('No data to export');
        
        let csv = 'Date,Reference,Description,Debit,Credit,Balance\n';
        ledgerData.forEach(row => {
            csv += `"${row.entry_date}","${row.reference}","${row.description}",${row.debit},${row.credit},${row.running_balance}\n`;
        });
        
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        a.setAttribute('download', `ledger_${buyerInfo.buyer_name.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    };
</script>

<?php require_once '../../includes/footer.php'; ?>
