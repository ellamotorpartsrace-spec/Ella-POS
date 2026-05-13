<?php
// views/buyers/transactions.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    denyAccess("You do not have permission to manage buyers.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

// Validate ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>window.location='index.php';</script>";
    exit;
}

$buyer_id = $_GET['id'];
$db = new Database();
$conn = $db->getConnection();

// Fetch Buyer Details
$stmt = $conn->prepare("SELECT * FROM buyers WHERE buyer_id = ?");
$stmt->execute([$buyer_id]);
$buyer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$buyer) {
    echo "<div class='p-4'>Buyer not found.</div>";
    require_once '../../includes/footer.php';
    exit;
}

$buyer_id_js = $buyer_id;
?>

<style>
    /* Transaction Page Styles */
    .transaction-page {
        height: calc(100vh - 120px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .transaction-header {
        flex-shrink: 0;
        margin-bottom: 20px;
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: var(--card-bg);
        border: 2px solid var(--border-color);
        border-radius: 12px;
        color: var(--text-secondary);
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .back-btn:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
        transform: translateX(-4px);
    }

    .transaction-content {
        flex: 1;
        display: flex;
        gap: 20px;
        overflow: hidden;
    }

    /* Buyer Profile Card */
    .buyer-profile-card {
        width: 280px;
        flex-shrink: 0;
        background: var(--card-bg);
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .buyer-profile-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
        padding: 30px 24px;
        text-align: center;
        position: relative;
    }

    .buyer-profile-avatar {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.2);
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 700;
        color: white;
        margin: 0 auto 16px;
    }

    .buyer-profile-name {
        color: white;
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .buyer-profile-shop {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9rem;
    }

    .buyer-profile-tier {
        display: inline-block;
        margin-top: 12px;
        padding: 6px 16px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 20px;
        color: white;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .buyer-profile-stats {
        padding: 20px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .stat-item {
        text-align: center;
        padding: 16px 12px;
        background: var(--bg-surface);
        border-radius: 12px;
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .stat-label {
        font-size: 0.75rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }

    .buyer-profile-info {
        padding: 20px;
        border-top: 1px solid var(--border-color);
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 0;
    }

    .info-item:not(:last-child) {
        border-bottom: 1px solid var(--border-color);
    }

    .info-icon {
        width: 36px;
        height: 36px;
        background: rgba(139, 92, 246, 0.1);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
    }

    .info-text {
        flex: 1;
    }

    .info-label {
        font-size: 0.75rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-value {
        color: var(--text-primary);
        font-weight: 500;
    }

    /* Transaction Panel */
    .transaction-panel {
        flex: 1;
        background: var(--card-bg);
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .transaction-panel-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        background: var(--bg-surface);
    }

    .transaction-panel-header h5 {
        margin: 0;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .transaction-filter {
        margin-bottom: 0;
        padding: 16px 24px;
        background: var(--bg-surface);
        border-bottom: 1px solid var(--border-color);
    }

    .transaction-list {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
    }

    /* Transaction Item */
    .transaction-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        background: var(--bg-surface);
        border-radius: 12px;
        margin-bottom: 12px;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .transaction-item:hover {
        background: var(--bg-surface-hover);
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .transaction-icon {
        width: 44px;
        height: 44px;
        background: linear-gradient(135deg, #10b981, #059669);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .transaction-info {
        flex: 1;
    }

    .transaction-ref {
        font-weight: 600;
        color: var(--text-primary);
    }

    .transaction-date {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .transaction-amount {
        font-size: 1.1rem;
        font-weight: 700;
        color: #10b981;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-secondary);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 16px;
        opacity: 0.3;
    }

    .empty-state h5 {
        color: var(--text-secondary);
        margin-bottom: 8px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .transaction-page {
            height: auto;
            overflow: visible;
            padding-bottom: 20px;
        }

        .transaction-content {
            flex-direction: column;
            overflow: visible;
        }

        .buyer-profile-card {
            width: 100%;
        }

        .buyer-profile-stats {
            grid-template-columns: repeat(2, 1fr);
        }

        .buyer-profile-header {
            padding: 20px 16px;
        }

        .buyer-profile-avatar {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }

        .transaction-panel {
            min-height: 400px;
            overflow: visible;
        }

        .transaction-list {
            max-height: none;
            overflow: visible;
        }

        .transaction-header .d-flex {
            flex-direction: column;
            gap: 12px;
        }

        .back-btn {
            align-self: flex-start;
        }
    }

    /* Scrollbar */
    .transaction-list::-webkit-scrollbar {
        width: 6px;
    }

    .transaction-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .transaction-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }

    /* Modal Styles */
    .detail-modal .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }

    .item-row {
        padding: 0.75rem 0;
        border-bottom: 1px dashed #eee;
    }

    .item-row:last-child {
        border-bottom: none;
    }
</style>

<div class="transaction-page">
    <!-- Header -->
    <div class="transaction-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0 fw-bold">
                    <i class="fa-solid fa-receipt text-primary"></i> Transaction History
                </h4>
                <div class="text-muted small">Viewing transactions for <?= htmlspecialchars($buyer['buyer_name']) ?>
                </div>
            </div>
            <a href="index.php" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> Back to Buyers
            </a>
        </div>
    </div>

    <!-- Content -->
    <div class="transaction-content">
        <!-- Buyer Profile Card -->
        <div class="buyer-profile-card">
            <div class="buyer-profile-header">
                <div class="buyer-profile-avatar">
                    <?= strtoupper(substr($buyer['buyer_name'], 0, 1)) ?>
                </div>
                <div class="buyer-profile-name"><?= htmlspecialchars($buyer['buyer_name']) ?></div>
                <?php if (!empty($buyer['shop_name'])): ?>
                    <div class="buyer-profile-shop">
                        <i class="fa-solid fa-store me-1"></i><?= htmlspecialchars($buyer['shop_name']) ?>
                    </div>
                <?php endif; ?>
                <div class="buyer-profile-tier"><?= ucfirst($buyer['price_tier']) ?></div>
            </div>

            <div class="buyer-profile-stats">
                <div class="stat-item">
                    <div class="stat-number" id="stat-sales">-</div>
                    <div class="stat-label">Sales</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="stat-revenue">₱-</div>
                    <div class="stat-label">Revenue</div>
                </div>
                <?php if (hasPermission('view_profit')): ?>
                    <div class="stat-item">
                        <div class="stat-number" id="stat-profit">₱-</div>
                        <div class="stat-label">Profit</div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="buyer-profile-info">
                <?php if (!empty($buyer['contact_number'])): ?>
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fa-solid fa-phone"></i>
                        </div>
                        <div class="info-text">
                            <div class="info-label">Contact</div>
                            <div class="info-value"><?= htmlspecialchars($buyer['contact_number']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($buyer['email'])): ?>
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div class="info-text">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?= htmlspecialchars($buyer['email']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fa-solid fa-tag"></i>
                    </div>
                    <div class="info-text">
                        <div class="info-label">Price Tier</div>
                        <div class="info-value"><?= ucfirst($buyer['price_tier']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction Panel -->
        <div class="transaction-panel">
            <div class="transaction-panel-header">
                <h5>
                    <i class="fa-solid fa-list"></i> Sales History
                </h5>
            </div>

            <!-- Date Filter -->
            <!-- Filter Form -->
            <div class="transaction-filter">
                <form id="transaction-filter-form" class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">SEARCH</label>
                        <div class="input-group input-group-sm">
                            <select class="form-select" id="transaction-search-type" style="max-width: 90px;">
                                <option value="sales">Sales</option>
                                <option value="items">Items</option>
                            </select>
                            <input type="text" id="transaction-search" class="form-control" placeholder="Search...">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">FROM</label>
                        <input type="date" id="transaction-date-from" class="form-control form-control-sm"
                            value="<?= date('Y-01-01') ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">TO</label>
                        <input type="date" id="transaction-date-to" class="form-control form-control-sm"
                            value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">METHOD</label>
                        <select class="form-select form-select-sm" id="transaction-payment-method">
                            <option value="">All Methods</option>
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="bank">Bank</option>
                            <option value="financing">Financing</option>
                            <option value="mix">Mix Payment</option>
                            <option value="paylater">Pay Later</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">STATUS</label>
                        <select class="form-select form-select-sm" id="transaction-payment-status">
                            <option value="">All Status</option>
                            <option value="completed">Fully Paid</option>
                            <option value="partial">Partial Payment</option>
                            <option value="unpaid">Unpaid / Balance</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 d-flex align-items-center mb-1">
                        <div class="form-check form-switch pb-1">
                            <input class="form-check-input" type="checkbox" id="transaction-exclude-voided" checked>
                            <label class="form-check-label small fw-bold text-muted ms-1" for="transaction-exclude-voided">HIDE VOIDED</label>
                        </div>
                    </div>
                    <div class="col-6 col-md-1">
                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1" title="Apply Filter">
                                <i class="fa-solid fa-filter"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" title="All Time" onclick="viewAllTime()">
                                <i class="fa-solid fa-infinity"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Transaction List Container -->
            <div class="transaction-list" id="transaction-list-container">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2 small">Loading transactions...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Detail Modal -->
<div class="modal fade detail-modal" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title">
                    <i class="fa-solid fa-receipt me-2"></i>Transaction Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="detail-content">
                <!-- Loaded dynamically -->
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<script>
    const BUYER_ID = <?= $buyer_id_js ?>;
    const hasViewProfit = <?= json_encode(hasPermission('view_profit')) ?>;
    let detailModal = null;

    document.addEventListener('DOMContentLoaded', function () {
        // Initialize modal
        detailModal = new bootstrap.Modal(document.getElementById('detailModal'));

        // Transaction filter form
        document.getElementById('transaction-filter-form').addEventListener('submit', function (e) {
            e.preventDefault();
            loadTransactions();
        });

        // Initial load
        loadTransactions();
    });

    const methodLabels = {
        'cash': 'Cash',
        'gcash': 'GCash',
        'bank': 'Bank',
        'pay_later': 'Pay Later',
        'mix': 'Mix Payment',
        'financing': 'Financing',
        'home_credit': 'Home Credit'
    };

    async function loadTransactions() {
        const container = document.getElementById('transaction-list-container');
        const dateFrom = document.getElementById('transaction-date-from').value;
        const dateTo = document.getElementById('transaction-date-to').value;
        const search = document.getElementById('transaction-search').value;

        container.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2 small">Loading transactions...</p>
            </div>
        `;

        try {
            const searchType = document.getElementById('transaction-search-type').value;
            const paymentMethod = document.getElementById('transaction-payment-method').value;
            const paymentStatus = document.getElementById('transaction-payment-status').value;
            const excludeVoided = document.getElementById('transaction-exclude-voided').checked ? '1' : '0';

            const params = new URLSearchParams({
                buyer_id: BUYER_ID,
                date_from: dateFrom,
                date_to: dateTo,
                search: search,
                type: searchType,
                payment_method: paymentMethod,
                payment_status: paymentStatus,
                exclude_voided: excludeVoided
            });

            const res = await fetch(`../../api/buyers/get_buyer_sales.php?${params}`);
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to load');
            }

            // Update stats - Use global_stats for persistent profile totals
            const stats = data.global_stats || data.stats;
            document.getElementById('stat-sales').textContent = stats.count || 0;
            document.getElementById('stat-revenue').textContent = '₱' + parseFloat(stats.total || 0).toLocaleString(undefined, {
                minimumFractionDigits: 0
            });
            
            if (hasViewProfit && document.getElementById('stat-profit')) {
                document.getElementById('stat-profit').textContent = '₱' + parseFloat(stats.profit || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 0
                });
            }

            if (!data.data && (!data.sales || data.sales.length === 0)) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-calendar-xmark"></i>
                        <h5>No Transactions Found</h5>
                        <p class="mb-3">No sales in the selected date range.</p>
                        <button type="button" class="btn btn-outline-primary btn-sm px-4" onclick="viewAllTime()">
                           <i class="fa-solid fa-history me-1"></i> View All Time History
                        </button>
                    </div>
                `;
                return;
            }

            const safeQuery = search ? search.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&').split(/\\s+/).filter(Boolean) : [];
            const highlight = (text) => {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                let hlText = div.innerHTML;

                if (safeQuery.length === 0) return hlText;
                safeQuery.forEach(q => {
                    const regex = new RegExp(`(${q})`, 'gi');
                    hlText = hlText.replace(regex, '<mark class="bg-warning bg-opacity-50 p-0 rounded text-dark">$1</mark>');
                });
                return hlText;
            };

            // Render transactions based on type
            if (data.type === 'items') {
                container.innerHTML = data.data.map(item => `
                    <div class="transaction-item" onclick="viewTransactionDetails(${item.sale_id})">
                        <div class="transaction-icon" style="background: var(--bg-surface); border: 1px solid var(--border-color); color: var(--text-primary);">
                            <i class="fa-solid fa-box-open text-primary"></i>
                        </div>
                        <div class="transaction-info">
                            <div class="transaction-ref">${highlight(item.product_name)}</div>
                            <div class="transaction-date">
                                <span class="badge bg-light text-dark border me-1">${item.quantity} pcs @ ₱${parseFloat(item.price_at_sale).toLocaleString()}</span>
                                <span class="text-muted small">• ${formatDate(item.created_at)}</span>
                                <div class="mt-1">
                                    <span class="badge bg-light text-dark border" style="font-size: 0.65rem;">${(methodLabels[item.payment_method] || item.payment_method || 'Cash').toUpperCase()}</span>
                                    ${(() => {
                                        const paid = parseFloat(item.total_paid || 0);
                                        const total = parseFloat(item.subtotal);
                                        if (paid >= total) return `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.65rem;">FULLY PAID</span>`;
                                        if (paid > 0) return `<span class="badge bg-warning text-dark border border-warning" style="font-size: 0.65rem;">PARTIAL</span>`;
                                        return `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size: 0.65rem;">UNPAID</span>`;
                                    })()}
                                </div>
                                ${item.payment_status !== 'paid' && item.payment_status !== 'completed' && item.status !== 'voided' ? `
                                <div class="mt-1 small text-primary fw-bold" style="font-size: 0.7rem;">
                                    <i class="fa-solid fa-circle-info me-1"></i> Paid: ₱${parseFloat(item.total_paid || 0).toLocaleString()} / ₱${parseFloat(item.subtotal).toLocaleString()}
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        <div>
                            <div class="transaction-amount">
                                ${item.item_discount > 0 ? `<div class="text-decoration-line-through text-muted small">₱${parseFloat(item.original_price * item.quantity).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>` : ''}
                                ₱${parseFloat(item.subtotal).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                            </div>
                            ${item.item_discount > 0 ? (() => { const totalDisc = parseFloat(item.item_discount) * parseInt(item.quantity); const pct = parseFloat(item.original_price) > 0 ? ((parseFloat(item.item_discount) / parseFloat(item.original_price)) * 100).toFixed(1) : null; return `<div class="text-end small text-danger fw-semibold"><i class="fa-solid fa-tag me-1"></i>-₱${totalDisc.toLocaleString(undefined, { minimumFractionDigits: 2 })}${pct ? ` <span class="text-muted fw-normal">(${pct}%)</span>` : ''}</div>`; })() : ''}
                            ${hasViewProfit ? `
                            <div class="text-end small text-success fw-bold" style="font-size: 0.75rem;">
                                Profit: ₱${parseFloat(item.item_profit).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                            </div>
                            ` : ''}
                            <div class="text-end small text-muted">${item.sale_ref}</div>
                        </div>
                    </div>
                `).join('');
            } else {
                // Default Sales View
                container.innerHTML = (data.sales || data.data).map(sale => `
                    <div class="transaction-item ${sale.status === 'voided' ? 'opacity-50' : ''}" onclick="viewTransactionDetails(${sale.sale_id})">
                        <div class="transaction-icon" style="${sale.status === 'voided' ? 'background: linear-gradient(135deg, #64748b, #475569);' : ''}">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <div class="transaction-info">
                            <div class="transaction-ref d-flex align-items-center gap-2">
                                ${highlight(sale.sale_ref)}
                                ${sale.status === 'voided' ? '<span class="badge bg-danger" style="font-size: 0.6rem;">VOIDED</span>' : ''}
                            </div>
                            <div class="transaction-date">${formatDate(sale.created_at)}</div>
                            <div class="mt-1">
                                <span class="badge bg-light text-dark border" style="font-size: 0.65rem;">${(methodLabels[sale.payment_method] || sale.payment_method || 'Cash').toUpperCase()}</span>
                                ${(() => {
                                    const paid = parseFloat(sale.total_paid || 0);
                                    const total = parseFloat(sale.grand_total);
                                    if (paid >= total) return `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.65rem;">FULLY PAID</span>`;
                                    if (paid > 0) return `<span class="badge bg-warning text-dark border border-warning" style="font-size: 0.65rem;">PARTIAL</span>`;
                                    return `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size: 0.65rem;">UNPAID</span>`;
                                })()}
                            </div>
                            ${sale.payment_status !== 'paid' && sale.status !== 'voided' ? `
                            <div class="mt-1 small text-primary fw-bold" style="font-size: 0.7rem;">
                                <i class="fa-solid fa-circle-info me-1"></i> Paid: ₱${parseFloat(sale.total_paid || 0).toLocaleString()} / ₱${parseFloat(sale.grand_total).toLocaleString()}
                            </div>
                            ` : ''}
                        </div>
                        <div>
                            <div class="transaction-amount" style="${sale.status === 'voided' ? 'color: #64748b; text-decoration: line-through;' : ''}">
                                ₱${parseFloat(sale.grand_total).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                            </div>
                            ${sale.discount_amount > 0 ? (() => { const base = parseFloat(sale.subtotal || sale.grand_total) + parseFloat(sale.discount_amount); const pct = base > 0 ? ((parseFloat(sale.discount_amount) / base) * 100).toFixed(1) : null; return `<div class="text-end small text-danger fw-semibold mt-1"><i class="fa-solid fa-tag me-1"></i>-₱${parseFloat(sale.discount_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}${pct ? ` <span class="text-muted fw-normal">(${pct}%)</span>` : ''}</div>`; })() : ''}
                            ${hasViewProfit && sale.status !== 'voided' ? `<div class="text-end small text-success fw-bold mt-1" style="font-size: 0.75rem;">Profit: ₱${parseFloat(sale.total_profit || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>` : ''}
                        </div>
                    </div>
                `).join('');
            }

        } catch (err) {
            console.error('Load transactions error:', err);
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-exclamation-circle text-danger"></i>
                    <h5>Error Loading Transactions</h5>
                    <p>${err.message}</p>
                </div>
            `;
        }
    }

    function viewAllTime() {
        document.getElementById('transaction-date-from').value = '2020-01-01'; // Broad enough
        document.getElementById('transaction-date-to').value = '<?= date('Y-12-31') ?>';
        document.getElementById('transaction-search').value = '';
        loadTransactions();
    }

    async function viewTransactionDetails(saleId) {
        const content = document.getElementById('detail-content');
        content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
        detailModal.show();

        try {
            const res = await fetch(`../../api/pos/get_transaction_details_enhanced.php?id=${saleId}`);
            const data = await res.json();

            if (data.error) throw new Error(data.error);

            content.innerHTML = renderDetailContent(data);

        } catch (err) {
            content.innerHTML = `<div class="text-center py-5 text-danger"><i class="fa-solid fa-exclamation-circle fa-2x mb-2"></i><p>${err.message}</p></div>`;
        }
    }

    function renderDetailContent(data) {
        const s = data.sale;
        const items = data.items;
        const statusBadge = getStatusBadge(s.status);
        const paymentBadge = getPaymentBadge(s.payment_method);

        let itemsHtml = items.map(item => `
            <div class="item-row d-flex justify-content-between">
                <div class="flex-grow-1">
                    <div class="fw-semibold">${item.product_name}</div>
                    <small class="text-muted">${item.brand_name || ''} ${item.variation_name || ''}</small>
                </div>
                <div class="text-end">
                    ${item.item_discount > 0 ? `<div class="small text-muted text-decoration-line-through">${item.quantity} × ₱${parseFloat(item.original_price).toFixed(2)}</div>` : ''}
                    <div class="small ${item.item_discount > 0 ? 'text-danger' : 'text-muted'}">${item.quantity} × ₱${parseFloat(item.price_at_sale).toFixed(2)}${item.item_discount > 0 ? ` <span class="fw-semibold">(-₱${parseFloat(item.item_discount).toFixed(2)}${parseFloat(item.original_price) > 0 ? ' · ' + ((parseFloat(item.item_discount) / parseFloat(item.original_price)) * 100).toFixed(1) + '%' : ''})</span>` : ''}</div>
                    ${hasViewProfit ? `<div class="small text-success">Profit: ₱${parseFloat(item.item_profit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>` : ''}
                    <div class="fw-bold">₱${parseFloat(item.subtotal).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                </div>
            </div>
        `).join('');

        return `
            <div class="p-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h5 class="fw-bold text-primary mb-1">${s.sale_ref}</h5>
                        <small class="text-muted">${formatDate(s.created_at)} at ${formatTime(s.created_at)}</small>
                    </div>
                    <div class="text-end">
                        ${statusBadge}
                        ${paymentBadge}
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="card bg-light border-0 mb-4">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted d-block">Customer</small>
                                <span class="fw-semibold">${s.customer_name || 'Walk-in'}</span>
                                ${s.shop_name ? `<small class="text-muted d-block">${s.shop_name}</small>` : ''}
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Cashier</small>
                                <span class="fw-semibold">${s.cashier_name || 'Staff'}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items -->
                <h6 class="fw-bold mb-3"><i class="fa-solid fa-box me-2"></i>Items (${items.length})</h6>
                <div class="mb-4">
                    ${itemsHtml}
                </div>

                <!-- Totals -->
                <div class="border-top pt-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span>₱${parseFloat(s.subtotal).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>
                    ${s.discount_amount > 0 ? `
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Discount</span>
                            <span>-₱${parseFloat(s.discount_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                        </div>
                    ` : ''}
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-bold">Grand Total</span>
                        <span class="fw-bold h5 mb-0 text-primary">₱${parseFloat(s.grand_total).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>
                    ${hasViewProfit && data.total_profit !== undefined ? `
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Total Profit</span>
                            <span class="fw-bold">₱${parseFloat(data.total_profit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                        </div>
                    ` : ''}
                    <hr>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Amount Tendered</span>
                        <span>₱${parseFloat(s.amount_tendered || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Change</span>
                        <span class="text-success">₱${parseFloat(s.change_due || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>
                </div>

                ${s.remarks ? `
                    <div class="alert alert-info mt-3 mb-0 py-2">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        <small>${s.remarks}</small>
                    </div>
                ` : ''}
            </div>
        `;
    }

    function getStatusBadge(status) {
        const badges = {
            'completed': '<span class="badge bg-success">Completed</span>',
            'voided': '<span class="badge bg-danger">Voided</span>',
            'refunded': '<span class="badge bg-warning text-dark">Refunded</span>',
            'not_completed': '<span class="badge bg-warning text-dark">Not Completed</span>'
        };
        return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
    }

    function getPaymentBadge(method) {
        const badges = {
            'cash': '<span class="badge bg-success-subtle text-success">💵 Cash</span>',
            'gcash': '<span class="badge bg-primary-subtle text-primary">📱 GCash</span>',
            'bank': '<span class="badge bg-info-subtle text-info">🏦 Bank</span>',
            'pay_later': '<span class="badge bg-warning-subtle text-warning">📅 Pay Later</span>',
            'home_credit': '<span class="badge bg-danger-subtle text-danger">💳 Home Credit</span>',
            'financing': '<span class="badge bg-danger-subtle text-danger">🏦 Financing</span>',
            'mix': '<span class="badge bg-secondary-subtle text-secondary">🔀 Mix Payment</span>'
        };
        return badges[method] || `<span class="badge bg-secondary">${method || 'Cash'}</span>`;
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function formatTime(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>