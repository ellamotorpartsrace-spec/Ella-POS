<?php
// views/pos/receipts.php - Enhanced Sales History with Filters & Void
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
// Auth Check
requirePermission('view_sales');

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Fetch filters data
$buyers = $conn->query("SELECT buyer_id, buyer_name, shop_name FROM buyers ORDER BY buyer_name")->fetchAll();
?>

<style>
    .transaction-card {
        transition: all 0.2s ease;
        cursor: pointer;
        border-left: 4px solid var(--bs-primary);
    }

    .transaction-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .transaction-card.voided {
        border-left-color: var(--bs-danger);
        opacity: 0.7;
    }

    .transaction-card.pay-later {
        border-left-color: var(--bs-warning);
    }

    .transaction-card.not-completed {
        border-left-color: var(--bs-orange, orange);
        background-color: rgba(255, 165, 0, 0.05);
    }

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

    .return-item-row {
        padding: 0.6rem 0.75rem;
        border-bottom: 1px solid var(--bs-border-color-translucent, #eee);
        border-radius: 0.4rem;
        transition: background 0.15s;
    }

    .return-item-row:last-child {
        border-bottom: none;
    }

    .return-item-row.disabled-row {
        opacity: 0.45;
        pointer-events: none;
    }

    .return-item-row:hover:not(.disabled-row) {
        background: var(--bs-primary-bg-subtle, #e7f1ff);
    }

    @media (max-width: 576px) {
        .stat-card-icon {
            display: none !important;
        }

        .container-fluid {
            padding: 0.5rem !important;
        }
    }

    @media (max-width: 768px) {
        .filter-section .row>div {
            margin-bottom: 0.5rem;
        }
    }
</style>

<div class="container-fluid p-3 p-lg-4">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h4 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-receipt text-primary me-2"></i>Sales History
            </h4>
            <p class="text-muted mb-0 small">View, filter, and manage all transactions</p>
        </div>
        <a href="simple_checkout.php" class="btn btn-success">
            <i class="fa-solid fa-plus me-1"></i>New Sale
        </a>
    </div>

    <!-- Stats Summary -->
    <div class="row g-2 g-md-3 mb-3 mb-md-4" id="stats-row">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-2 p-md-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-2 me-md-3 stat-card-icon">
                            <i class="fa-solid fa-receipt text-primary fa-lg"></i>
                        </div>
                        <div>
                            <div class="h5 h4-md fw-bold mb-0" id="stat-count">-</div>
                            <small class="text-muted" style="font-size: 0.75rem;">Transactions</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-2 p-md-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-2 me-2 me-md-3 stat-card-icon">
                            <i class="fa-solid fa-peso-sign text-success fa-lg"></i>
                        </div>
                        <div>
                            <div class="h6 h5-md fw-bold mb-0" id="stat-total">₱0</div>
                            <small class="text-muted" style="font-size: 0.75rem;">Total Sales</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if (hasPermission('view_profit')): ?>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-2 p-md-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success bg-opacity-10 p-2 me-2 me-md-3 stat-card-icon">
                                <i class="fa-solid fa-chart-line text-success fa-lg"></i>
                            </div>
                            <div>
                                <div class="h6 h5-md fw-bold mb-0" id="stat-profit">₱0</div>
                                <small class="text-muted" style="font-size: 0.75rem;">Total Profit</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-2 p-md-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-danger bg-opacity-10 p-2 me-2 me-md-3 stat-card-icon">
                            <i class="fa-solid fa-ban text-danger fa-lg"></i>
                        </div>
                        <div>
                            <div class="h5 h4-md fw-bold mb-0" id="stat-voided">-</div>
                            <small class="text-muted" style="font-size: 0.75rem;">Voided</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-2 p-md-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-2 me-2 me-md-3 stat-card-icon">
                            <i class="fa-solid fa-clock text-warning fa-lg"></i>
                        </div>
                        <div>
                            <div class="h5 h4-md fw-bold mb-0" id="stat-pending">-</div>
                            <small class="text-muted" style="font-size: 0.75rem;">Pay Later</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-4 filter-section">
        <div class="card-body p-3">
            <form id="filter-form" class="row g-2 align-items-end">
                <div class="col-12 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">FROM</label>
                    <input type="date" name="date_from" id="filter-date-from" class="form-control form-control-sm"
                        value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">TO</label>
                    <input type="date" name="date_to" id="filter-date-to" class="form-control form-control-sm"
                        value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">STATUS</label>
                    <select name="status" id="filter-status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="voided">Voided</option>
                        <option value="not_completed">Not Completed</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">PAYMENT</label>
                    <select name="payment" id="filter-payment" class="form-select form-select-sm">
                        <option value="">All Methods</option>
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="bank">Bank</option>
                        <option value="pay_later">Pay Later</option>
                        <option value="financing">Financing</option>
                        <option value="mix">Mix Payment</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">SEARCH</label>
                    <div class="input-group">
                        <input type="text" name="search" id="filter-search" class="form-control form-control-sm"
                            placeholder="Ref, customer, product..." autocomplete="off">
                        <span class="input-group-text d-none bg-white" id="search-spinner">
                            <i class="fa-solid fa-spinner fa-spin text-primary"></i>
                        </span>
                    </div>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="SalesHistory.reset()"
                        title="Reset Filters">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="SalesHistory.exportData()"
                        title="Export to CSV">
                        <i class="fa-solid fa-file-export"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">
                <i class="fa-solid fa-list text-primary me-2"></i>Transactions
            </h6>
            <span class="badge bg-secondary" id="result-count">0 records</span>
        </div>

        <div class="card-body p-0">
            <!-- Loading -->
            <div id="loading-state" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2">Loading transactions...</p>
            </div>

            <!-- Empty -->
            <div id="empty-state" class="text-center py-5 d-none">
                <i class="fa-solid fa-inbox fa-3x text-muted opacity-25 mb-3"></i>
                <h6 class="text-muted">No transactions found</h6>
                <p class="small text-muted">Try adjusting your filters</p>
            </div>

            <!-- Desktop Table -->
            <div class="table-responsive d-none d-lg-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Reference</th>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th>Payment</th>
                            <th class="text-end">Subtotal</th>
                            <?php if (hasPermission('view_profit')): ?>
                                <th class="text-end">Profit</th>
                            <?php endif; ?>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="transactions-tbody"></tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="p-3 d-lg-none" id="transactions-cards"></div>
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
            <div class="modal-footer bg-light py-2 d-flex flex-wrap gap-2 justify-content-end" id="detail-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <select id="reprint-paper-size" class="form-select form-select-sm" style="width: auto;">
                    <option value="thermal80">Thermal 80mm</option>
                    <option value="thermal80x3276">Thermal 80x3276mm</option>
                    <option value="a4" selected>A4 Invoice</option>
                </select>
                <button type="button" class="btn btn-info btn-sm" id="btn-print-receipt">
                    <i class="fa-solid fa-print me-1"></i>Print
                </button>
                <button type="button" class="btn btn-success btn-sm d-none" id="btn-wallet-adjust">
                    <i class="fa-solid fa-wallet me-1"></i>Wallet Adjust
                </button>
                <button type="button" class="btn btn-warning btn-sm d-none" id="btn-return-items" style="color:#000;">
                    <i class="fa-solid fa-rotate-left me-1"></i>Return Items
                </button>
                <button type="button" class="btn btn-primary btn-sm d-none" id="btn-adjust-transaction">
                    <i class="fa-solid fa-sliders me-1"></i>Adjust
                </button>
                <button type="button" class="btn btn-danger btn-sm d-none" id="btn-void-sale">
                    <i class="fa-solid fa-ban me-1"></i>Void Sale
                </button>
                <button type="button" class="btn btn-success btn-sm d-none" id="btn-recover-sale">
                    <i class="fa-solid fa-recycle me-1"></i>Recover to Cart
                </button>
                <?php if (hasPermission('view_profit')): ?>
                    <button type="button" class="btn btn-warning btn-sm text-dark d-none" id="btn-refresh-profit"
                        title="Recalculate profit based on current capital">
                        <i class="fa-solid fa-sync me-1"></i>Refresh Profit
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Return Items Modal -->
<div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-3" style="background: linear-gradient(135deg,#f59e0b,#d97706); color:#fff;">
                <h5 class="modal-title"><i class="fa-solid fa-rotate-left me-2"></i>Return Items</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Return history (previously returned) -->
                <div id="return-history-section" class="d-none px-4 pt-3 pb-1">
                    <div class="alert alert-warning py-2 small mb-2">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i>
                        <strong>Previously Returned:</strong> <span id="return-history-summary"></span>
                    </div>
                </div>
                <!-- Items to return -->
                <div class="p-4">
                    <p class="text-muted small mb-3">Select items and quantities to return. Only items not yet fully
                        returned are available.</p>
                    <div id="return-items-list"></div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">REFUND METHOD</label>
                            <select id="return-refund-method" class="form-select form-select-sm">
                                <option value="cash">💵 Cash</option>
                                <option value="gcash">📱 GCash</option>
                                <option value="bank">🏦 Bank Transfer</option>
                                <option value="store_credit">🎫 Store Credit</option>
                                <option value="financing">🏦 Financing</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">REASON</label>
                            <input type="text" id="return-reason" class="form-control form-control-sm"
                                placeholder="e.g., Defective, Wrong item...">
                        </div>
                    </div>

                    <div class="mt-3 p-3 bg-light rounded d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Total Refund:</span>
                        <span class="fw-bold h5 mb-0 text-warning" id="return-total-refund">₱0.00</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm" id="btn-confirm-return" style="color:#000;">
                    <i class="fa-solid fa-rotate-left me-1"></i>Process Return
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Adjust Transaction Modal -->
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title"><i class="fa-solid fa-sliders me-2"></i>Adjust Transaction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <!-- Left: Return/Add Section -->
                    <div class="col-lg-8 border-end">
                        <!-- Return Items Section -->
                        <div class="p-4 border-bottom">
                            <h6 class="fw-bold mb-3 text-warning"><i class="fa-solid fa-rotate-left me-2"></i>Items to
                                Refund</h6>
                            <div id="adjust-return-list" class="mb-3"></div>
                        </div>

                        <!-- Add Items Section -->
                        <div class="p-4 bg-light bg-opacity-50">
                            <h6 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-plus me-2"></i>Add New Items
                            </h6>
                            <div class="mb-3 position-relative">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i
                                            class="fa-solid fa-search text-muted"></i></span>
                                    <input type="text" id="adjust-product-search" class="form-control border-start-0"
                                        placeholder="Search product by name, brand, or barcode...">
                                </div>
                                <div id="adjust-search-results"
                                    class="position-absolute w-100 shadow-sm rounded-bottom d-none"
                                    style="z-index:1060; max-height:300px; overflow-y:auto; background:#fff; border:1px solid #dee2e6; top:100%;">
                                </div>
                            </div>
                            <div id="adjust-add-list">
                                <!-- Items added will appear here -->
                                <div class="text-center py-4 text-muted" id="adjust-add-empty">
                                    <i class="fa-solid fa-cart-plus fa-2x mb-2 opacity-25"></i>
                                    <p class="small mb-0">Search and select items to add</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Summary Section -->
                    <div class="col-lg-4 bg-light">
                        <div class="p-4 h-100 d-flex flex-column">
                            <h6 class="fw-bold mb-4">Adjustment Summary</h6>

                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Original Total:</span>
                                    <span id="adjust-summary-original">₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 text-danger">
                                    <span>Total Refunds:</span>
                                    <span id="adjust-summary-refunds">-₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 text-success">
                                    <span>Total Additions:</span>
                                    <span id="adjust-summary-additions">+₱0.00</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-4">
                                    <span class="fw-bold">Net Difference:</span>
                                    <span class="fw-bold h5 mb-0" id="adjust-summary-net">₱0.00</span>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">PAYMENT/REFUND METHOD</label>
                                    <select id="adjust-payment-method" class="form-select form-select-sm">
                                        <option value="cash">💵 Cash</option>
                                        <option value="gcash">📱 GCash</option>
                                        <option value="bank">🏦 Bank Transfer</option>
                                        <option value="financing">🏦 Financing</option>
                                        <option value="pay_later">⏳ Pay Later (Credit)</option>
                                    </select>
                                    <small class="text-muted d-block mt-1">Method for the net difference</small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted">REASON FOR ADJUSTMENT</label>
                                    <textarea id="adjust-reason" class="form-control form-control-sm" rows="2"
                                        placeholder="e.g., Exchange for different item..."></textarea>
                                </div>
                            </div>

                            <div class="mt-auto">
                                <div class="alert alert-info py-2 small mb-3">
                                    <i class="fa-solid fa-circle-info me-1"></i>
                                    Original purchase date will be preserved.
                                </div>
                                <button type="button" class="btn btn-primary w-100 py-2 fw-bold"
                                    id="btn-confirm-adjust">
                                    Confirm Adjustment
                                </button>
                                <button type="button" class="btn btn-outline-secondary w-100 mt-2 btn-sm"
                                    data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Void Confirmation Modal -->
<div class="modal fade" id="voidModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title"><i class="fa-solid fa-triangle-exclamation me-2"></i>Void Transaction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-3">Are you sure you want to void this transaction? This will:</p>
                <ul class="mb-3">
                    <li>Mark the sale as voided</li>
                    <li>Restore all item quantities to inventory</li>
                    <li>This action cannot be undone</li>
                </ul>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Reason for voiding:</label>
                    <input type="text" id="void-reason" class="form-control"
                        placeholder="e.g., Customer cancelled, Wrong items...">
                </div>
                <input type="hidden" id="void-sale-id">
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-confirm-void">
                    <i class="fa-solid fa-ban me-1"></i>Confirm Void
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Wallet Adjustment Modal -->
<div class="modal fade" id="walletAdjustModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#16a34a,#15803d);">
                <h5 class="modal-title"><i class="fa-solid fa-wallet me-2"></i>Wallet Adjustment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Buyer & Transaction Info -->
                <div class="card bg-light border-0 mb-3 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted fw-bold">BUYER</small>
                        <span class="fw-bold" id="wa-buyer-name">—</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted fw-bold">TRANSACTION</small>
                        <span class="fw-bold text-primary" id="wa-sale-ref">—</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted fw-bold">CURRENT WALLET</small>
                        <span class="fw-bold text-success" id="wa-current-balance">₱0.00</span>
                    </div>
                </div>

                <!-- Adjustment Type -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">ADJUSTMENT TYPE</label>
                    <div class="d-flex gap-2">
                        <div class="flex-fill">
                            <input type="radio" class="btn-check" name="wa-type" id="wa-type-credit" value="credit"
                                checked>
                            <label class="btn btn-outline-success w-100 fw-bold" for="wa-type-credit">
                                <i class="fa-solid fa-plus me-1"></i>Add to Wallet
                            </label>
                        </div>
                        <div class="flex-fill">
                            <input type="radio" class="btn-check" name="wa-type" id="wa-type-debit" value="debit">
                            <label class="btn btn-outline-danger w-100 fw-bold" for="wa-type-debit">
                                <i class="fa-solid fa-minus me-1"></i>Deduct from Wallet
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Amount -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">ADJUSTMENT AMOUNT</label>
                    <div class="input-group">
                        <span class="input-group-text fw-bold">₱</span>
                        <input type="number" id="wa-amount" class="form-control fw-bold text-end" placeholder="0.00"
                            step="0.01" min="0.01" oninput="SalesHistory.updateWalletPreview()">
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted">New Balance After Adjustment:</small>
                        <strong id="wa-new-balance" class="text-primary">₱0.00</strong>
                    </div>
                </div>

                <!-- Reason -->
                <div class="mb-1">
                    <label class="form-label small fw-bold text-muted">REASON <span class="text-danger">*</span></label>
                    <textarea id="wa-reason" class="form-control form-control-sm" rows="2"
                        placeholder="e.g., Wrong price corrected — overpayment credited, underpayment collected..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm fw-bold" id="btn-confirm-wallet-adjust">
                    <i class="fa-solid fa-check me-1"></i>Apply Adjustment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const SalesHistory = {
        detailModal: null,
        voidModal: null,
        walletAdjustModal: null,
        _walletAdjustSale: null,
        currentSaleId: null,

        init() {
            this.detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
            this.voidModal = new bootstrap.Modal(document.getElementById('voidModal'));
            this.adjustModal = new bootstrap.Modal(document.getElementById('adjustModal'));
            this.walletAdjustModal = new bootstrap.Modal(document.getElementById('walletAdjustModal'));


            // Form submit
            document.getElementById('filter-form').addEventListener('submit', (e) => {
                e.preventDefault();
                this.load();
            });

            // Progressive search with debounce
            const searchInput = document.getElementById('filter-search');
            const spinner = document.getElementById('search-spinner');
            let searchTimeout = null;

            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    spinner?.classList.remove('d-none');

                    searchTimeout = setTimeout(() => {
                        spinner?.classList.add('d-none');
                        this.load();
                    }, 400);
                });

                // Immediate search on Enter
                searchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(searchTimeout);
                        spinner?.classList.add('d-none');
                        this.load();
                    }
                });
            }

            // Void confirmation
            document.getElementById('btn-confirm-void').addEventListener('click', () => this.confirmVoid());

            // Adjust search
            const adjustSearch = document.getElementById('adjust-product-search');
            if (adjustSearch) {
                adjustSearch.addEventListener('input', (e) => this.searchAdjustProducts(e.target.value));
            }

            // Adjust confirm
            document.getElementById('btn-confirm-adjust').addEventListener('click', () => this.submitAdjustment());

            // Initial load
            this.load();
        },

        async load() {
            const params = new URLSearchParams({
                date_from: document.getElementById('filter-date-from').value,
                date_to: document.getElementById('filter-date-to').value,
                status: document.getElementById('filter-status').value,
                payment: document.getElementById('filter-payment').value,
                search: document.getElementById('filter-search').value
            });

            document.getElementById('loading-state').classList.remove('d-none');
            document.getElementById('empty-state').classList.add('d-none');
            document.getElementById('transactions-tbody').innerHTML = '';
            document.getElementById('transactions-cards').innerHTML = '';

            try {
                const res = await fetch(`../../api/pos/list_transactions_enhanced.php?${params}`);
                const data = await res.json();

                document.getElementById('loading-state').classList.add('d-none');

                if (!data.transactions || data.transactions.length === 0) {
                    document.getElementById('empty-state').classList.remove('d-none');
                    this.updateStats({
                        count: 0,
                        total: 0,
                        voided: 0,
                        pending: 0
                    });
                    document.getElementById('result-count').textContent = '0 records';
                    return;
                }

                this.updateStats(data.stats);
                document.getElementById('result-count').textContent = `${data.transactions.length} records`;
                this.renderTable(data.transactions);
                this.renderCards(data.transactions);

            } catch (err) {
                console.error('Load error:', err);
                document.getElementById('loading-state').classList.add('d-none');
                document.getElementById('empty-state').classList.remove('d-none');
            }
        },

        updateStats(stats) {
            document.getElementById('stat-count').textContent = stats.count || 0;
            document.getElementById('stat-total').textContent = '₱' + parseFloat(stats.total || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2
            });
            const hasViewProfit = <?= json_encode(hasPermission('view_profit')) ?>;
            if (hasViewProfit && document.getElementById('stat-profit')) {
                document.getElementById('stat-profit').textContent = '₱' + parseFloat(stats.total_profit || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2
                });
            }
            document.getElementById('stat-voided').textContent = stats.voided || 0;
            document.getElementById('stat-pending').textContent = stats.pending || 0;
        },

        renderTable(transactions) {
            const tbody = document.getElementById('transactions-tbody');
            tbody.innerHTML = transactions.map(t => {
                const statusBadge = this.getStatusBadge(t.status);
                const paymentBadge = this.getPaymentBadge(t.payment_method);
                const isVoided = t.status === 'voided';
                const hasViewProfit = <?= json_encode(hasPermission('view_profit')) ?>;

                return `
                <tr class="${isVoided ? 'table-danger opacity-75' : ''}">
                    <td class="ps-4">
                        <span class="fw-bold text-primary">${t.sale_ref}</span>
                    </td>
                    <td>
                        <div class="small fw-semibold text-dark">${this.formatDate(t.created_at)}</div>
                        <div class="x-small text-muted mb-1">${this.formatTime(t.created_at)}</div>
                        ${t.due_date ? `<div class="x-small fw-bold text-warning"><i class="fa-solid fa-calendar-day me-1"></i>DUE: ${this.formatDate(t.due_date)}</div>` : ''}
                    </td>
                    <td>
                        <div class="fw-semibold">${t.customer_name || 'Walk-in'}</div>
                        ${t.shop_name ? `<small class="text-muted">${t.shop_name}</small>` : ''}
                    </td>
                    <td>${paymentBadge}</td>
                    <td class="text-end">
                        <span class="fw-bold">₱${parseFloat(t.grand_total).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                        ${parseFloat(t.discount_amount || 0) > 0 ? (() => { const disc = parseFloat(t.discount_amount); const base = parseFloat(t.subtotal || 0) + disc; const pct = base > 0 ? ((disc / base) * 100).toFixed(1) : null; return `<div class="small text-danger"><i class="fa-solid fa-tag me-1"></i>-₱${disc.toLocaleString(undefined, { minimumFractionDigits: 2 })}${pct ? ` (${pct}%)` : ''}</div>`; })() : ''}
                        ${t.filtered_method_amount && parseFloat(t.filtered_method_amount) < parseFloat(t.grand_total) ? `
                            <div class="x-small fw-bold text-success mt-1">
                                <i class="fa-solid fa-hand-holding-dollar me-1"></i>Portion: ₱${parseFloat(t.filtered_method_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                            </div>
                        ` : ''}
                    </td>
                    ${hasViewProfit ? `
                    <td class="text-end">
                        <span class="fw-bold text-success">₱${parseFloat(t.profit || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </td>
                    ` : ''}
                    <td class="text-center">${statusBadge}</td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-info text-white me-1" onclick="SalesHistory.viewDetails(${t.sale_id})" title="View Details">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        ${!isVoided ? `
                            <button class="btn btn-sm btn-outline-danger" onclick="SalesHistory.showVoidModal(${t.sale_id})" title="Void">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                        ` : ''}
                    </td>
                </tr>
            `;
            }).join('');
        },

        renderCards(transactions) {
            const container = document.getElementById('transactions-cards');
            container.innerHTML = transactions.map(t => {
                const statusBadge = this.getStatusBadge(t.status);
                const paymentBadge = this.getPaymentBadge(t.payment_method);
                const isVoided = t.status === 'voided';
                const isNotCompleted = t.status === 'not_completed';
                const cardClass = isVoided ? 'voided' : (isNotCompleted ? 'not-completed' : (t.payment_method === 'pay_later' ? 'pay-later' : ''));
                const hasViewProfit = <?= json_encode(hasPermission('view_profit')) ?>;

                return `
                <div class="card transaction-card ${cardClass} mb-3 border-0 shadow-sm" onclick="SalesHistory.viewDetails(${t.sale_id})">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="fw-bold text-primary">${t.sale_ref}</span>
                                <div class="small text-muted">${this.formatDate(t.created_at)} ${this.formatTime(t.created_at)}</div>
                                ${t.due_date ? `<div class="x-small fw-bold text-warning"><i class="fa-solid fa-calendar-day me-1"></i>DUE: ${this.formatDate(t.due_date)}</div>` : ''}
                            </div>
                            <div class="text-end">
                                <span class="h5 fw-bold mb-0 d-block">₱${parseFloat(t.grand_total).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                                ${parseFloat(t.discount_amount || 0) > 0 ? (() => { const disc = parseFloat(t.discount_amount); const base = parseFloat(t.subtotal || 0) + disc; const pct = base > 0 ? ((disc / base) * 100).toFixed(1) : null; return `<div class="small text-danger fw-semibold"><i class="fa-solid fa-tag me-1"></i>-₱${disc.toLocaleString(undefined, { minimumFractionDigits: 2 })}${pct ? ` (${pct}%)` : ''}</div>`; })() : ''}
                                ${hasViewProfit ? `<small class="text-success fw-bold">Profit: ₱${parseFloat(t.profit || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</small>` : ''}
                                ${t.filtered_method_amount && parseFloat(t.filtered_method_amount) < parseFloat(t.grand_total) ? `
                                    <div class="small fw-bold text-success mt-1">
                                        <i class="fa-solid fa-hand-holding-dollar me-1"></i>Portion: ₱${parseFloat(t.filtered_method_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <span class="fw-semibold">${t.customer_name || 'Walk-in'}</span>
                                ${t.shop_name ? `<small class="text-muted d-block">${t.shop_name}</small>` : ''}
                            </div>
                            <div class="d-flex gap-1 flex-wrap">
                                ${paymentBadge}
                                ${statusBadge}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            }).join('');
        },

        async viewDetails(saleId) {
            this.currentSaleId = saleId;
            const content = document.getElementById('detail-content');
            content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
            this.detailModal.show();

            try {
                const res = await fetch(`../../api/pos/get_transaction_details_enhanced.php?id=${saleId}`);
                const data = await res.json();

                if (data.error) throw new Error(data.error);

                content.innerHTML = this.renderDetailContent(data);

                // Show/hide void/recover/return buttons
                const voidBtn = document.getElementById('btn-void-sale');
                const recoverBtn = document.getElementById('btn-recover-sale');
                const returnBtn = document.getElementById('btn-return-items');
                const hasViewProfit = <?= json_encode(hasPermission('view_profit')) ?>;

                if (data.sale.status === 'voided') {
                    voidBtn.classList.add('d-none');
                    returnBtn.classList.add('d-none');
                    recoverBtn.classList.remove('d-none');
                    recoverBtn.onclick = () => {
                        window.location.href = `simple_checkout.php?recover=${saleId}`;
                    };
                } else {
                    recoverBtn.classList.add('d-none');
                    voidBtn.classList.remove('d-none');
                    returnBtn.classList.remove('d-none');
                    voidBtn.onclick = () => this.showVoidModal(saleId);
                    returnBtn.onclick = () => this.openReturnModal(saleId, data.items);

                    // Show Adjust button
                    const adjustBtn = document.getElementById('btn-adjust-transaction');
                    if (adjustBtn) {
                        adjustBtn.classList.remove('d-none');
                        adjustBtn.onclick = () => this.openAdjustModal(saleId, data.items, data.sale.grand_total, data.sale.buyer_id);
                    }

                    // Show Wallet Adjust button (only for transactions with a buyer_id)
                    const walletAdjBtn = document.getElementById('btn-wallet-adjust');
                    if (walletAdjBtn) {
                        const hasBuyer = !!data.sale.buyer_id;
                        walletAdjBtn.classList.toggle('d-none', !hasBuyer);
                        if (hasBuyer) {
                            walletAdjBtn.onclick = () => this.openWalletAdjustModal(data.sale);
                        }
                    }

                    // Show Refresh Profit button
                    const refreshBtn = document.getElementById('btn-refresh-profit');
                    if (refreshBtn && hasViewProfit) {
                        refreshBtn.classList.toggle('d-none', data.sale.status === 'voided');
                        refreshBtn.onclick = () => this.recalculateProfit(saleId);
                    }
                }

                // Hide wallet adjust for voided sales if not already handled
                if (data.sale.status === 'voided') {
                    const walletAdjBtn = document.getElementById('btn-wallet-adjust');
                    if (walletAdjBtn) walletAdjBtn.classList.add('d-none');
                }

                // Print button - pass watermark based on status and selected paper size
                document.getElementById('btn-print-receipt').onclick = () => {
                    if (typeof ReceiptPreview !== 'undefined') {
                        const watermark = data.sale.status === 'voided' ? 'VOIDED' : 'COPY';
                        const paperSize = document.getElementById('reprint-paper-size').value || 'thermal80';
                        ReceiptPreview.openWindow(data.receiptData, paperSize, watermark);
                    }
                };

            } catch (err) {
                content.innerHTML = `<div class="text-center py-5 text-danger"><i class="fa-solid fa-exclamation-circle fa-2x mb-2"></i><p>${err.message}</p></div>`;
            }
        },

        renderDetailContent(data) {
            const s = data.sale;
            const items = data.items;
            const statusBadge = this.getStatusBadge(s.status);
            const paymentBadge = this.getPaymentBadge(s.payment_method);
            const hasViewProfit = <?= json_encode(hasPermission('view_profit')) ?>;

            let itemsHtml = items.map(item => {
                const hasDiscount = item.item_discount && parseFloat(item.item_discount) > 0;
                const origPrice = item.original_price ? parseFloat(item.original_price) : null;
                const returnedQty = parseInt(item.returned_quantity || 0);
                const netQty = item.quantity - returnedQty;
                const isFullyReturned = returnedQty >= item.quantity;

                return `
                <div class="item-row d-flex justify-content-between ${isFullyReturned ? 'text-muted opacity-50' : ''}" 
                     style="${isFullyReturned ? 'text-decoration: line-through;' : ''}">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">${item.product_name}</div>
                        <small class="text-muted">${item.brand_name || ''} ${item.variation_name || ''}</small>
                        ${hasDiscount ? (() => { const pct = origPrice > 0 ? ((parseFloat(item.item_discount) / origPrice) * 100).toFixed(1) : null; return `<span class="badge bg-danger bg-opacity-10 text-danger ms-1" style="font-size:10px;"><i class="fa-solid fa-tag me-1"></i>-₱${parseFloat(item.item_discount).toFixed(2)}${pct ? ` (${pct}%)` : ''}</span>`; })() : ''}
                        ${returnedQty > 0 ? `<span class="badge bg-warning text-dark ms-1" style="font-size:10px;">Returned: ${returnedQty}</span>` : ''}
                    </div>
                    <div class="text-end">
                        <div class="small text-muted">
                            ${netQty} × ${hasDiscount && origPrice ? `<span style="text-decoration:line-through;color:#999;">₱${origPrice.toFixed(2)}</span> ` : ''}₱${parseFloat(item.price_at_sale).toFixed(2)}
                        </div>
                        <div class="fw-semibold">₱${(parseFloat(item.price_at_sale) * netQty).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                        ${hasViewProfit ? `<div class="small text-success">Profit: ₱${parseFloat(item.item_profit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>` : ''}
                    </div>
                </div>
            `}).join('');

            // --- PAYMENT BREAKDOWN (Always Show) ---
            let paymentBreakdownHtml = '';
            if (data.payments && data.payments.length > 0) {
                const isMix = s.payment_method === 'mix';
                const isFin = (s.payment_method === 'financing' || s.payment_method === 'home_credit');
                
                let bgColor = 'bg-light';
                let textColor = 'text-dark';
                let icon = 'fa-receipt';
                
                if (isMix) { bgColor = 'bg-primary bg-opacity-10'; textColor = 'text-primary'; icon = 'fa-money-bill-transfer'; }
                else if (isFin) { bgColor = 'bg-danger bg-opacity-10'; textColor = 'text-danger'; icon = 'fa-building-columns'; }
                else if (s.payment_method === 'pay_later') { bgColor = 'bg-warning bg-opacity-10'; textColor = 'text-warning-emphasis'; icon = 'fa-clock-rotate-left'; }

                paymentBreakdownHtml = `<div class="${bgColor} p-3 rounded-4 mb-3 mt-3 border border-opacity-25 border-secondary">
                    <div class="small fw-800 mb-2 ${textColor} text-uppercase letter-spacing-1">
                        <i class="fa-solid ${icon} me-2"></i>Payment Information
                    </div>`;
                
                data.payments.forEach(p => {
                    let label = (p.payment_type || 'cash').toUpperCase();
                    if (label === 'BANK_TRANSFER') label = 'BANK';
                    
                    // Special labels for financing/adjustments
                    if (p.reference_no && p.reference_no.startsWith('DP-')) label = `DOWNPAYMENT (${label})`;
                    else if (p.reference_no && p.reference_no.startsWith('ADJ-')) label = `ADJUSTMENT (${label})`;
                    
                    paymentBreakdownHtml += `
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div>
                            <span class="fw-bold small">${label}</span>
                            ${p.financing_provider ? `<span class="badge bg-danger ms-1" style="font-size:9px;">${p.financing_provider}</span>` : ''}
                            ${p.reference_no ? `<div class="x-small text-muted" style="font-size:10px;">Ref: ${p.reference_no}</div>` : ''}
                        </div>
                        <div class="text-end">
                            <div class="fw-800">₱${parseFloat(p.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                            <div class="x-small ${p.payment_status === 'paid' ? 'text-success' : 'text-danger'}" style="font-size:9px; font-weight:700;">${p.payment_status.toUpperCase()}</div>
                        </div>
                    </div>`;
                });
                paymentBreakdownHtml += `</div>`;
            }

            // Wallet Activity Panel
            let walletHtml = '';
            const ws = data.wallet_summary || {};
            const hasWalletActivity = (ws.supplement_used > 0 || ws.saved_to_wallet > 0 || ws.paid_by_wallet > 0 || ws.shortfall_deducted > 0);
            if (hasWalletActivity) {
                walletHtml = `
                <div class="bg-success bg-opacity-10 border border-success border-opacity-25 rounded p-2 mb-2 mt-2">
                    <div class="small fw-bold mb-2 text-success"><i class="fa-solid fa-wallet me-1"></i>WALLET ACTIVITY</div>
                    ${ws.supplement_used > 0 ? `
                    <div class="d-flex justify-content-between small align-items-center">
                        <span><i class="fa-solid fa-arrow-right-to-bracket text-success me-1"></i>Wallet Used (Supplement)</span>
                        <span class="badge bg-success">-₱${parseFloat(ws.supplement_used).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>` : ''}
                    ${ws.paid_by_wallet > 0 ? `
                    <div class="d-flex justify-content-between small align-items-center mt-1">
                        <span><i class="fa-solid fa-wallet text-primary me-1"></i>Paid by Wallet</span>
                        <span class="badge bg-primary">-₱${parseFloat(ws.paid_by_wallet).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>` : ''}
                    ${ws.shortfall_deducted > 0 ? `
                    <div class="d-flex justify-content-between small align-items-center mt-1">
                        <span><i class="fa-solid fa-circle-minus text-warning me-1"></i>Shortfall Deducted</span>
                        <span class="badge bg-warning text-dark">-₱${parseFloat(ws.shortfall_deducted).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>` : ''}
                    ${ws.saved_to_wallet > 0 ? `
                    <div class="d-flex justify-content-between small align-items-center mt-1">
                        <span><i class="fa-solid fa-piggy-bank text-info me-1"></i>Change Saved to Wallet</span>
                        <span class="badge bg-info text-dark">+₱${parseFloat(ws.saved_to_wallet).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>` : ''}
                </div>`;
            }

            return `
            <div class="p-3 p-md-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
                    <div>
                        <h5 class="fw-bold text-primary mb-1">${s.sale_ref}</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <small class="text-muted"><i class="fa-solid fa-clock me-1"></i>${this.formatDate(s.created_at)} at ${this.formatTime(s.created_at)}</small>
                            ${(function () {
                    const payLater = data.payments.find(p => p.payment_type === 'pay_later');
                    return payLater && payLater.due_date ? `<span class="badge bg-warning text-dark" style="font-size: 11px;"><i class="fa-solid fa-calendar-day me-1"></i>DUE BY: ${SalesHistory.formatDate(payLater.due_date)}</span>` : '';
                })()}
                        </div>
                    </div>
                    <div class="text-end d-flex gap-1 flex-wrap justify-content-end">
                        ${statusBadge}
                        ${paymentBadge}
                        ${data.payment && data.payment.financing_provider ? `<span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25">${data.payment.financing_provider}</span>` : ''}
                        ${hasWalletActivity ? `<span class="badge bg-success"><i class="fa-solid fa-wallet me-1"></i>Wallet</span>` : ''}
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
                    ${s.discount_amount > 0 ? (() => {
                    const disc = parseFloat(s.discount_amount); const base = parseFloat(s.subtotal || 0) + disc; const pct = base > 0 ? ((disc / base) * 100).toFixed(1) : null; return `
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span><i class="fa-solid fa-tag me-1"></i>Discount</span>
                            <span class="fw-semibold">-₱${disc.toLocaleString(undefined, { minimumFractionDigits: 2 })}${pct ? ` <small class="fw-normal text-muted">(${pct}%)</small>` : ''}</span>
                        </div>
                    `;
                })() : ''}
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-bold">Grand Total</span>
                        <span class="fw-bold h5 mb-0 text-primary">₱${parseFloat(s.grand_total).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>
                    ${paymentBreakdownHtml}
                    ${walletHtml}
                    <hr>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Amount Tendered</span>
                        <span>₱${parseFloat(s.amount_tendered || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Change</span>
                        <span class="text-success">₱${parseFloat(s.change_due || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Total Cost</span>
                        <span class="text-muted">₱${parseFloat(data.total_cost || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>
                    ${hasViewProfit && data.total_profit !== undefined ? `
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-bold text-success">Total Profit</span>
                        <span class="fw-bold text-success">₱${parseFloat(data.total_profit || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Profit Margin</span>
                        <span class="text-muted small">${parseFloat(data.profit_margin || 0).toFixed(2)}%</span>
                    </div>
                    ` : ''}
                </div>

                ${(function () {
                    const payLaterPayments = data.payments.filter(p => p.payment_type === 'pay_later');
                    let remarksHtml = '';
                    let displayRemarks = s.remarks || '';

                    // 1. Pay Later Installment Box (Live)
                    if (payLaterPayments.length > 0) {
                        const now = new Date();
                        now.setHours(0, 0, 0, 0); // Reset time for date comparison

                        const installList = payLaterPayments.map((p, idx) => {
                            const dueDate = new Date(p.due_date);
                            dueDate.setHours(0, 0, 0, 0);
                            const isPaid = p.payment_status === 'paid';
                            const isOverdue = !isPaid && dueDate < now;

                            let badgeClass = 'bg-warning text-dark';
                            let icon = '<i class="fa-regular fa-clock me-1"></i>';
                            let statusText = 'Pending';

                            if (isPaid) {
                                badgeClass = 'bg-success text-white';
                                icon = '<i class="fa-solid fa-check-circle me-1"></i>';
                                statusText = 'Paid';
                            } else if (isOverdue) {
                                badgeClass = 'bg-danger text-white';
                                icon = '<i class="fa-solid fa-triangle-exclamation me-1"></i>';
                                statusText = 'Overdue';
                            }

                            return `
                                <div class="d-flex justify-content-between align-items-center py-1 ${idx > 0 ? 'border-top border-info border-opacity-10 mt-1' : ''}">
                                    <div class="small">
                                        <span class="fw-bold">Term ${idx + 1}:</span> 
                                        ${SalesHistory.formatDate(p.due_date)} 
                                        <span class="text-secondary fw-bold ms-1">(₱${parseFloat(p.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })})</span>
                                    </div>
                                    <span class="badge ${badgeClass}" style="font-size: 9px; min-width: 60px;">${icon} ${statusText}</span>
                                </div>
                            `;
                        }).join('');

                        remarksHtml += `
                            <div class="alert alert-info mt-3 mb-2 py-2">
                                <div class="d-flex align-items-center fw-bold mb-2 border-bottom border-info border-opacity-25 pb-1">
                                    <i class="fa-solid fa-clock-rotate-left me-2"></i>
                                    PAY LATER SCHEDULES ${payLaterPayments.length > 1 ? `(${payLaterPayments.length} Terms)` : ''}
                                </div>
                                <div>${installList}</div>
                            </div>
                        `;
                        // Remove "Schedules: ..." from remarks if it's already there to avoid redundancy
                        displayRemarks = displayRemarks.replace(/\|? ?Schedules: .*?(?=\||$)/i, '').trim();
                        if (displayRemarks.startsWith('|')) displayRemarks = displayRemarks.substring(1).trim();
                        if (displayRemarks.endsWith('|')) displayRemarks = displayRemarks.substring(0, displayRemarks.length - 1).trim();
                    }

                    // 2. Original Remarks (Cleaned)
                    if (displayRemarks) {
                        remarksHtml += `
                            <div class="alert alert-secondary mt-1 mb-0 py-2">
                                <i class="fa-solid fa-info-circle me-1"></i>
                                <small>${displayRemarks}</small>
                            </div>
                        `;
                    }
                    return remarksHtml;
                })()}
            </div>
        `;
        },

        async openReturnModal(saleId, saleItems) {
            // Fetch already-returned qty map
            let returnedQtyMap = {};
            let returnHistoryText = '';
            try {
                const res = await fetch(`../../api/pos/get_return_history.php?sale_id=${saleId}`);
                const rData = await res.json();
                if (rData.success) {
                    returnedQtyMap = rData.returned_qty_map || {};
                    if (rData.returns && rData.returns.length > 0) {
                        returnHistoryText = rData.returns.map(r =>
                            `${r.return_ref} (₱${parseFloat(r.refund_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })} via ${r.refund_method})`
                        ).join(', ');
                    }
                }
            } catch (e) { /* non-fatal */ }

            // Show/hide history section
            const histSec = document.getElementById('return-history-section');
            if (returnHistoryText) {
                document.getElementById('return-history-summary').textContent = returnHistoryText;
                histSec.classList.remove('d-none');
            } else {
                histSec.classList.add('d-none');
            }

            // Build items list
            const listEl = document.getElementById('return-items-list');
            listEl.innerHTML = saleItems.map(item => {
                const alreadyReturned = returnedQtyMap[item.sale_item_id] || 0;
                const maxQty = item.quantity - alreadyReturned;
                const disabled = maxQty <= 0;
                const pricePerUnit = parseFloat(item.price_at_sale);

                return `
                <div class="return-item-row ${disabled ? 'disabled-row' : ''}" data-item-id="${item.sale_item_id}" data-variation="${item.variation_id}" data-price="${pricePerUnit}" data-max="${maxQty}">
                    <div class="d-flex align-items-center gap-3">
                        <input type="checkbox" class="form-check-input return-item-check" style="width:1.2rem;height:1.2rem;" ${disabled ? 'disabled' : ''}>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small">${this.esc(item.product_name)}</div>
                            <small class="text-muted">${this.esc(item.brand_name || '')} ${this.esc(item.variation_name || '')} &bull; ₱${pricePerUnit.toLocaleString(undefined, { minimumFractionDigits: 2 })} each</small>
                            ${disabled ? '<span class="badge bg-secondary ms-1" style="font-size:10px;">Fully Returned</span>' : `<span class="badge bg-light text-dark border ms-1" style="font-size:10px;">Returnable: ${maxQty}</span>`}
                        </div>
                        <div style="width:80px;">
                            <input type="number" class="form-control form-control-sm return-qty-input" min="1" max="${maxQty}" value="1" ${disabled ? 'disabled' : ''}>
                        </div>
                    </div>
                </div>`;
            }).join('');

            // Wire up total calculation
            const updateTotal = () => {
                let total = 0;
                listEl.querySelectorAll('.return-item-row').forEach(row => {
                    const chk = row.querySelector('.return-item-check');
                    const qty = row.querySelector('.return-qty-input');
                    if (chk && chk.checked && qty) {
                        total += parseFloat(row.dataset.price) * parseInt(qty.value || 1);
                    }
                });
                document.getElementById('return-total-refund').textContent = '₱' + total.toLocaleString(undefined, { minimumFractionDigits: 2 });
            };

            listEl.addEventListener('change', updateTotal);
            listEl.addEventListener('input', updateTotal);

            // Store sale_id for submit
            document.getElementById('btn-confirm-return').dataset.saleId = saleId;

            // Reset
            document.getElementById('return-reason').value = '';
            document.getElementById('return-refund-method').value = 'cash';
            document.getElementById('return-total-refund').textContent = '₱0.00';

            new bootstrap.Modal(document.getElementById('returnModal')).show();
        },

        async submitReturn() {
            const saleId = document.getElementById('btn-confirm-return').dataset.saleId;
            const reason = document.getElementById('return-reason').value.trim() || 'Return';
            const refundMethod = document.getElementById('return-refund-method').value;
            const listEl = document.getElementById('return-items-list');

            const items = [];
            listEl.querySelectorAll('.return-item-row').forEach(row => {
                const chk = row.querySelector('.return-item-check');
                const qty = parseInt(row.querySelector('.return-qty-input')?.value || 0);
                if (chk && chk.checked && qty > 0) {
                    items.push({
                        sale_item_id: parseInt(row.dataset.itemId),
                        variation_id: parseInt(row.dataset.variation),
                        quantity: qty,
                        refund_amount: parseFloat(row.dataset.price) * qty,
                        product_name: row.querySelector('.fw-semibold')?.textContent?.trim() || '',
                    });
                }
            });

            if (!items.length) {
                this.showToast('Please select at least one item to return.', 'warning');
                return;
            }

            const btn = document.getElementById('btn-confirm-return');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Processing...';

            try {
                const res = await fetch('../../api/pos/process_return.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sale_id: saleId, items, reason, refund_method: refundMethod })
                });
                const data = await res.json();

                bootstrap.Modal.getInstance(document.getElementById('returnModal'))?.hide();

                if (data.success) {
                    this.showToast(`Return processed! Ref: ${data.return_ref} &bull; Refund: ₱${parseFloat(data.refund_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}`, 'success');
                    this.load();
                } else {
                    this.showToast('Return failed: ' + (data.error || 'Unknown error'), 'danger');
                }
            } catch (err) {
                this.showToast('Network error during return', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-rotate-left me-1"></i>Process Return';
            }
        },

        esc(str) {
            if (!str) return '';
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        },

        // =====================================================================
        // WALLET ADJUSTMENT
        // =====================================================================
        async openWalletAdjustModal(sale) {
            this._walletAdjustSale = sale;

            // Fetch live wallet balance from API
            let currentBalance = 0;
            try {
                const res = await fetch(`../../api/buyers/get_wallet_balance.php?buyer_id=${sale.buyer_id}`);
                const d = await res.json();
                if (d.success) currentBalance = parseFloat(d.wallet_balance || 0);
            } catch (e) { /* fallback: 0 */ }

            this._walletAdjustSale._currentBalance = currentBalance;

            document.getElementById('wa-buyer-name').textContent = sale.customer_name || '—';
            document.getElementById('wa-sale-ref').textContent = sale.sale_ref || '—';
            document.getElementById('wa-current-balance').textContent = '₱' + currentBalance.toLocaleString(undefined, { minimumFractionDigits: 2 });
            document.getElementById('wa-amount').value = '';
            document.getElementById('wa-reason').value = '';
            document.getElementById('wa-new-balance').textContent = '₱' + currentBalance.toLocaleString(undefined, { minimumFractionDigits: 2 });
            document.getElementById('wa-type-credit').checked = true;

            // Wire confirm button
            const confirmBtn = document.getElementById('btn-confirm-wallet-adjust');
            confirmBtn.onclick = () => this.submitWalletAdjust();

            // Wire amount radio buttons to update preview
            document.querySelectorAll('input[name="wa-type"]').forEach(r =>
                r.addEventListener('change', () => this.updateWalletPreview())
            );

            this.detailModal.hide();
            setTimeout(() => this.walletAdjustModal.show(), 200);
        },

        updateWalletPreview() {
            const sale = this._walletAdjustSale;
            if (!sale) return;
            const currentBalance = sale._currentBalance || 0;
            const amount = parseFloat(document.getElementById('wa-amount').value || 0);
            const type = document.querySelector('input[name="wa-type"]:checked')?.value || 'credit';
            const newBalance = type === 'credit' ? currentBalance + amount : currentBalance - amount;

            const el = document.getElementById('wa-new-balance');
            if (el) {
                el.textContent = '₱' + newBalance.toLocaleString(undefined, { minimumFractionDigits: 2 });
                el.className = newBalance < 0 ? 'text-danger fw-bold' : 'text-primary fw-bold';
            }
        },

        async submitWalletAdjust() {
            const sale = this._walletAdjustSale;
            const amount = parseFloat(document.getElementById('wa-amount').value || 0);
            const type = document.querySelector('input[name="wa-type"]:checked')?.value || 'credit';
            const reason = document.getElementById('wa-reason').value.trim();

            if (!amount || amount <= 0) {
                EllaToast.error('Enter a valid adjustment amount.');
                return;
            }
            if (!reason) {
                EllaToast.error('A reason is required for wallet adjustments.');
                document.getElementById('wa-reason').focus();
                return;
            }

            // Warn if deducting more than balance
            const currentBalance = sale._currentBalance || 0;
            if (type === 'debit' && amount > currentBalance) {
                const proceed = await EllaConfirm.show({
                    title: 'Negative Balance Warning',
                    message: `This deduction of ₱${amount.toFixed(2)} exceeds the current balance of ₱${currentBalance.toFixed(2)}. The wallet will go negative. Continue?`,
                    confirmText: 'Yes, Apply',
                    confirmClass: 'btn-danger',
                    icon: 'fa-triangle-exclamation',
                    iconColor: 'text-warning'
                });
                if (!proceed) return;
            }

            const btn = document.getElementById('btn-confirm-wallet-adjust');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Applying...';

            try {
                const res = await fetch('../../api/pos/wallet_adjustment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        buyer_id: sale.buyer_id,
                        sale_ref: sale.sale_ref,
                        amount: amount,
                        type: type,
                        reason: reason
                    })
                });
                const data = await res.json();

                if (data.success) {
                    this.walletAdjustModal.hide();
                    const action = type === 'credit' ? 'credited' : 'deducted';
                    EllaToast.success(`₱${amount.toFixed(2)} ${action} — New balance: ₱${parseFloat(data.new_balance).toFixed(2)}`);
                } else {
                    EllaToast.error('Adjustment failed: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                EllaToast.error('Network error. Please try again.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Apply Adjustment';
            }
        },

        showVoidModal(saleId) {
            document.getElementById('void-sale-id').value = saleId;
            document.getElementById('void-reason').value = '';
            this.voidModal.show();
        },

        async confirmVoid() {
            const saleId = document.getElementById('void-sale-id').value;
            const reason = document.getElementById('void-reason').value || 'No reason provided';

            const btn = document.getElementById('btn-confirm-void');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Processing...';

            try {
                const res = await fetch('../../api/pos/void_sale.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        sale_id: saleId,
                        reason: reason
                    })
                });
                const data = await res.json();

                this.voidModal.hide();

                if (data.success) {
                    this.showToast('Sale voided successfully. Stock restored.', 'success');
                    this.load();
                } else {
                    this.showToast('Failed to void: ' + data.error, 'danger');
                }
            } catch (err) {
                this.showToast('Network error', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-ban me-1"></i>Confirm Void';
            }
        },

        // --- ADJUSTMENT LOGIC ---
        adjustCart: [],
        adjustOriginalItems: [],
        adjustOriginalTotal: 0,

        async openAdjustModal(saleId, items, total, buyerId) {
            this.currentSaleId = saleId;
            this.adjustOriginalItems = items;
            this.adjustOriginalTotal = parseFloat(total);
            this.adjustCart = []; // New additions

            // Fetch already-returned qty to know what's refundable
            let returnedQtyMap = {};
            try {
                const res = await fetch(`../../api/pos/get_return_history.php?sale_id=${saleId}`);
                const rData = await res.json();
                if (rData.success) returnedQtyMap = rData.returned_qty_map || {};
            } catch (e) { }

            // Build refund list (checkboxes)
            const listEl = document.getElementById('adjust-return-list');
            listEl.innerHTML = items.map(item => {
                const alreadyReturned = returnedQtyMap[item.sale_item_id] || 0;
                const maxQty = item.quantity - alreadyReturned;
                const disabled = maxQty <= 0;
                return `
                        <div class="return-item-row mb-2 ${disabled ? 'disabled-row' : ''}" 
                             data-item-id="${item.sale_item_id}" data-variation="${item.variation_id}" 
                             data-price="${item.price_at_sale}" data-max="${maxQty}">
                            <div class="d-flex align-items-center gap-2">
                                <input type="checkbox" class="form-check-input adjust-refund-check" ${disabled ? 'disabled' : ''}>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small">${this.esc(item.product_name)}</div>
                                    <small class="text-muted d-block">${this.esc(item.brand_name || '')} ${item.variation_name || ''}</small>
                                    <div class="d-flex align-items-center gap-1 mt-1">
                                        <small class="text-primary fw-bold">₱${parseFloat(item.price_at_sale).toFixed(2)}</small>
                                        ${disabled ? '<span class="badge bg-secondary" style="font-size:10px;">Fully Returned</span>' : `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:10px;">Adjustable: ${maxQty}</span>`}
                                        ${!disabled ? `<button class="btn btn-sm btn-link p-0 ms-2 text-primary" title="Correct Price" onclick="SalesHistory.correctAdjustPrice(this, ${JSON.stringify(item).replace(/"/g, '&quot;')})"><i class="fa-solid fa-pencil" style="font-size: 11px;"></i></button>` : ''}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1" style="width:100px;">
                                    <button class="btn btn-sm btn-outline-secondary px-1 py-0" onclick="SalesHistory.updateAdjustRefundQty(this, -1)" ${disabled ? 'disabled' : ''}>-</button>
                                    <input type="number" class="form-control form-control-sm text-center p-0 adjust-refund-qty" 
                                           style="width:40px;" min="1" max="${maxQty}" value="1" ${disabled ? 'disabled' : ''} 
                                           onchange="SalesHistory.validateRefundQty(this)" oninput="SalesHistory.updateAdjustSummary()">
                                    <button class="btn btn-sm btn-outline-secondary px-1 py-0" onclick="SalesHistory.updateAdjustRefundQty(this, 1)" ${disabled ? 'disabled' : ''}>+</button>
                                </div>
                            </div>
                        </div>`;
            }).join('');

            // Search results cleanup
            document.getElementById('adjust-product-search').value = '';
            document.getElementById('adjust-search-results').classList.add('d-none');
            document.getElementById('adjust-add-list').innerHTML = `
                        <div class="text-center py-4 text-muted" id="adjust-add-empty">
                            <i class="fa-solid fa-cart-plus fa-2x mb-2 opacity-25"></i>
                            <p class="small mb-0">Search and select items to add</p>
                        </div>`;

            // Reset fields
            document.getElementById('adjust-reason').value = '';
            document.getElementById('adjust-payment-method').value = 'cash';

            // Handle Pay Later option
            const plOption = document.querySelector('#adjust-payment-method option[value="pay_later"]');
            if (plOption) {
                if (buyerId) {
                    plOption.disabled = false;
                    plOption.style.display = 'block';
                } else {
                    plOption.disabled = true;
                    plOption.style.display = 'none';
                }
            }

            // Initial summary
            this.updateAdjustSummary();

            // Listeners for refund changes
            listEl.querySelectorAll('.adjust-refund-check, .adjust-refund-qty').forEach(el => {
                el.addEventListener('change', () => this.updateAdjustSummary());
            });

            this.adjustModal.show();
        },

        updateAdjustRefundQty(btn, delta) {
            const row = btn.closest('.return-item-row');
            const input = row.querySelector('.adjust-refund-qty');
            const max = parseInt(row.dataset.max);
            let val = parseInt(input.value) + delta;
            if (val < 1) val = 1;
            if (val > max) val = max;
            input.value = val;
            this.updateAdjustSummary();
        },

        validateRefundQty(input) {
            const row = input.closest('.return-item-row');
            const max = parseInt(row.dataset.max);
            let val = parseInt(input.value);
            if (isNaN(val) || val < 1) val = 1;
            if (val > max) val = max;
            input.value = val;
            this.updateAdjustSummary();
        },

        correctAdjustPrice(btn, item) {
            const row = btn.closest('.return-item-row');
            const chk = row.querySelector('.adjust-refund-check');
            const qtyInput = row.querySelector('.adjust-refund-qty');

            // 1. Mark for refund
            chk.checked = true;
            qtyInput.value = item.quantity; // Default to full quantity for correction

            // 2. Add as new item for price correction
            this.addToAdjustCart({
                variation_id: item.variation_id,
                product_name: item.product_name,
                brand_name: item.brand_name,
                variation_name: item.variation_name,
                price_retail: parseFloat(item.price_retail || item.price_at_sale),
                price_wholesale: parseFloat(item.price_wholesale || item.price_at_sale),
                price_dealer: parseFloat(item.price_dealer || item.price_at_sale),
                unit_type: item.unit_type,
                barcode: item.barcode,
                multiplier: 1
            });

            // 3. Highlight the addition
            this.showToast(`Correcting price for ${item.product_name}. Edit the price below.`, 'info');
            this.updateAdjustSummary();
        },

        async searchAdjustProducts(query) {
            const resultsEl = document.getElementById('adjust-search-results');
            if (query.length < 2) {
                resultsEl.classList.add('d-none');
                return;
            }

            try {
                const res = await fetch(`../../api/pos/simple_search.php?q=${encodeURIComponent(query)}`);
                const data = await res.json();
                console.log('Adjust Search Results:', data);

                if (Array.isArray(data) && data.length > 0) {
                    resultsEl.innerHTML = '';
                    const safeQuery = query ? query.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&').split(/\\s+/).filter(Boolean) : [];
                    const highlight = (text) => {
                        if (!text) return '';
                        let hlText = this.esc(text);
                        if (safeQuery.length === 0) return hlText;
                        safeQuery.forEach(q => {
                            const regex = new RegExp(`(${q})`, 'gi');
                            hlText = hlText.replace(regex, '<mark class="bg-warning bg-opacity-50 p-0 rounded text-dark">$1</mark>');
                        });
                        return hlText;
                    };

                    data.forEach(p => {
                        const row = document.createElement('div');
                        row.className = 'p-2 border-bottom cursor-pointer hover-bg';
                        row.innerHTML = `
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small text-primary">${highlight(p.product_name)}</div>
                                            <div class="small text-muted">
                                                <span class="fw-semibold text-dark">${highlight(p.brand_name || 'Generic')}</span> &bull; 
                                                ${highlight(p.variation_name || '')} &bull; 
                                                <span class="text-success fw-bold">₱${parseFloat(p.price_retail).toFixed(2)}</span>
                                            </div>
                                            <div class="x-small text-muted italic">Stock: <strong>${p.stock}</strong> ${this.esc(p.unit_type || 'pc')}</div>
                                        </div>
                                    </div>
                                `;
                        row.onclick = () => this.addToAdjustCart(p);
                        resultsEl.appendChild(row);
                    });
                    resultsEl.classList.remove('d-none');
                } else {
                    resultsEl.innerHTML = '<div class="p-3 text-center text-muted small">No products found</div>';
                    resultsEl.classList.remove('d-none');
                }
            } catch (e) {
                console.error('Search error:', e);
                resultsEl.innerHTML = `<div class="p-3 text-center text-danger small">Error: ${e.message}</div>`;
                resultsEl.classList.remove('d-none');
            }
        },

        addToAdjustCart(product) {
            const existing = this.adjustCart.find(i => i.variation_id === product.variation_id);
            if (existing) {
                existing.quantity++;
            } else {
                this.adjustCart.push({
                    variation_id: product.variation_id,
                    unit_id: product.unit_id,
                    product_name: product.product_name,
                    brand_name: product.brand_name,
                    variation_name: product.variation_name,
                    price: parseFloat(product.price_retail),
                    price_retail: parseFloat(product.price_retail),
                    price_wholesale: parseFloat(product.price_wholesale),
                    price_dealer: parseFloat(product.price_dealer),
                    multiplier: product.multiplier || 1,
                    unit_type: product.unit_type,
                    barcode: product.barcode,
                    quantity: 1
                });
            }

            document.getElementById('adjust-search-results').classList.add('d-none');
            document.getElementById('adjust-product-search').value = '';
            this.renderAdjustAddList();
            this.updateAdjustSummary();
        },

        renderAdjustAddList() {
            const listEl = document.getElementById('adjust-add-list');
            if (this.adjustCart.length === 0) {
                listEl.innerHTML = `
                        <div class="text-center py-4 text-muted" id="adjust-add-empty">
                            <i class="fa-solid fa-cart-plus fa-2x mb-2 opacity-25"></i>
                            <p class="small mb-0">Search and select items to add</p>
                        </div>`;
                return;
            }

            listEl.innerHTML = this.adjustCart.map((item, index) => `
                        <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-white rounded border border-primary border-opacity-10">
                            <div class="flex-grow-1">
                                <div class="fw-semibold small text-primary">${this.esc(item.product_name)}</div>
                                <small class="text-muted d-block">${this.esc(item.brand_name || '')} ${item.variation_name || ''}</small>
                                <div class="d-flex align-items-center gap-1 mt-1">
                                    <span class="small text-muted">Price: ₱</span>
                                    <input type="number" class="form-control form-control-sm p-0 text-success fw-bold border-0 bg-light" 
                                           style="width:70px; border-bottom: 2px solid #198754 !important; border-radius:0;" 
                                           value="${item.price.toFixed(2)}" step="0.01" min="0" 
                                           onchange="SalesHistory.validateAddPrice(${index}, this)" oninput="SalesHistory.updateAdjustSummary()">
                                </div>
                                <div class="d-flex gap-1 mt-1 flex-wrap">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary cursor-pointer border" style="font-size:9px;" onclick="SalesHistory.setAdjustPrice(${index}, ${item.price_retail}, this)">SRP: ₱${item.price_retail.toFixed(2)}</span>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary cursor-pointer border" style="font-size:9px;" onclick="SalesHistory.setAdjustPrice(${index}, ${item.price_wholesale}, this)">WS: ₱${item.price_wholesale.toFixed(2)}</span>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary cursor-pointer border" style="font-size:9px;" onclick="SalesHistory.setAdjustPrice(${index}, ${item.price_dealer}, this)">DL: ₱${item.price_dealer.toFixed(2)}</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <button class="btn btn-sm btn-outline-secondary px-1 py-0" onclick="SalesHistory.updateAdjustAddQty(${index}, -1)">-</button>
                                <input type="number" class="form-control form-control-sm text-center p-0 adjust-add-qty" style="width:40px;" 
                                       value="${item.quantity}" onchange="SalesHistory.validateAddQty(${index}, this)" oninput="SalesHistory.updateAdjustSummary()">
                                <button class="btn btn-sm btn-outline-secondary px-1 py-0" onclick="SalesHistory.updateAdjustAddQty(${index}, 1)">+</button>
                                <button class="btn btn-sm btn-link text-danger p-0 ms-1" onclick="SalesHistory.removeAdjustAddItem(${index})"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </div>
                    `).join('');
        },

        updateAdjustAddQty(index, delta) {
            this.adjustCart[index].quantity += delta;
            if (this.adjustCart[index].quantity <= 0) {
                this.adjustCart.splice(index, 1);
            }
            this.renderAdjustAddList();
            this.updateAdjustSummary();
        },

        validateAddQty(index, input) {
            let val = parseInt(input.value);
            if (isNaN(val) || val <= 0) {
                this.adjustCart.splice(index, 1);
                this.renderAdjustAddList();
            } else {
                this.adjustCart[index].quantity = val;
            }
            this.updateAdjustSummary();
        },

        validateAddPrice(index, input) {
            let val = parseFloat(input.value);
            if (isNaN(val) || val < 0) val = 0;
            this.adjustCart[index].price = val;
            input.value = val.toFixed(2);
            this.updateAdjustSummary();
        },

        setAdjustPrice(index, price, badge) {
            this.adjustCart[index].price = price;
            // Find the input in the same row
            const row = badge.closest('.flex-grow-1');
            const input = row.querySelector('input[type="number"]');
            if (input) input.value = price.toFixed(2);
            this.updateAdjustSummary();

            // Visual feedback: highlight selected badge? (Maybe too much for now)
        },

        removeAdjustAddItem(index) {
            this.adjustCart.splice(index, 1);
            this.renderAdjustAddList();
            this.updateAdjustSummary();
        },

        updateAdjustSummary() {
            // Calculate refunds
            let totalRefund = 0;
            document.querySelectorAll('.adjust-refund-check').forEach(chk => {
                if (chk.checked) {
                    const row = chk.closest('.return-item-row');
                    const price = parseFloat(row.dataset.price);
                    const qty = parseInt(row.querySelector('.adjust-refund-qty').value || 0);
                    totalRefund += price * qty;
                }
            });

            // Calculate additions
            const totalAddition = this.adjustCart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

            const net = totalAddition - totalRefund;

            document.getElementById('adjust-summary-original').textContent = '₱' + this.adjustOriginalTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
            document.getElementById('adjust-summary-refunds').textContent = '-₱' + totalRefund.toLocaleString(undefined, { minimumFractionDigits: 2 });
            document.getElementById('adjust-summary-additions').textContent = '+₱' + totalAddition.toLocaleString(undefined, { minimumFractionDigits: 2 });

            const netEl = document.getElementById('adjust-summary-net');
            netEl.textContent = (net >= 0 ? '+' : '-') + '₱' + Math.abs(net).toLocaleString(undefined, { minimumFractionDigits: 2 });
            netEl.className = 'fw-bold h5 mb-0 ' + (net > 0 ? 'text-primary' : (net < 0 ? 'text-danger' : 'text-dark'));
        },

        async submitAdjustment() {
            const returnItems = [];
            document.querySelectorAll('.adjust-refund-check').forEach(chk => {
                if (chk.checked) {
                    const row = chk.closest('.return-item-row');
                    returnItems.push({
                        sale_item_id: parseInt(row.dataset.itemId),
                        variation_id: parseInt(row.dataset.variation),
                        quantity: parseInt(row.querySelector('.adjust-refund-qty').value)
                    });
                }
            });

            if (returnItems.length === 0 && this.adjustCart.length === 0) {
                this.showToast('No changes made to adjust.', 'warning');
                return;
            }

            const confirmed = await EllaConfirm.show({
                title: 'Confirm Adjustment',
                message: 'This will modify the original transaction and update stock levels. Proceed?',
                confirmText: 'Yes, Adjust',
                confirmClass: 'btn-primary'
            });
            if (!confirmed) return;

            const btn = document.getElementById('btn-confirm-adjust');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Processing...';

            try {
                const res = await fetch('../../api/pos/adjust_transaction.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        sale_id: this.currentSaleId,
                        return_items: returnItems,
                        add_items: this.adjustCart,
                        reason: document.getElementById('adjust-reason').value.trim() || 'Manual Adjustment',
                        payment_method: document.getElementById('adjust-payment-method').value
                    })
                });
                const data = await res.json();

                if (data.success) {
                    this.showToast('Adjustment completed successfully!', 'success');
                    this.adjustModal.hide();
                    this.detailModal.hide();
                    this.load();
                } else {
                    this.showToast('Adjustment failed: ' + (data.error || 'Unknown error'), 'danger');
                }
            } catch (e) {
                this.showToast('Network error during adjustment', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Confirm Adjustment';
            }
        },

        async recalculateProfit(saleId) {
            if (!confirm('Are you sure you want to recalculate the profit for this transaction based on CURRENT capital prices? This will update the historical cost record.')) {
                return;
            }

            const btn = document.getElementById('btn-refresh-profit');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Updating...';

            try {
                const res = await fetch('../../api/pos/recalculate_profit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sale_id: saleId })
                });
                const data = await res.json();

                if (data.success) {
                    this.showToast('Profit recalculated successfully', 'success');
                    // Reload details to show updated figures
                    this.viewDetails(saleId);
                    // Also reload the main list to update totals there
                    this.load();
                } else {
                    this.showToast('Failed to recalculate: ' + (data.error || 'Unknown error'), 'danger');
                }
            } catch (err) {
                console.error(err);
                this.showToast('Network error during recalculation', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        },

        reset() {
            document.getElementById('filter-date-from').value = new Date().toISOString().split('T')[0];
            document.getElementById('filter-date-to').value = new Date().toISOString().split('T')[0];
            document.getElementById('filter-status').value = '';
            document.getElementById('filter-payment').value = '';
            document.getElementById('filter-search').value = '';
            this.load();
        },

        exportData() {
            const params = new URLSearchParams({
                date_from: document.getElementById('filter-date-from').value,
                date_to: document.getElementById('filter-date-to').value,
                status: document.getElementById('filter-status').value,
                payment: document.getElementById('filter-payment').value,
                search: document.getElementById('filter-search').value
            });

            // Open export URL in new window to trigger download
            window.location.href = `../../api/pos/export_receipts.php?${params}`;
        },

        getStatusBadge(status) {
            const badges = {
                'completed': '<span class="badge bg-success">Completed</span>',
                'voided': '<span class="badge bg-danger">Voided</span>',
                'refunded': '<span class="badge bg-warning text-dark">Refunded</span>',
                'not_completed': '<span class="badge bg-warning text-dark">Not Completed</span>'
            };
            return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
        },

        getPaymentBadge(method) {
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
        },

        formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        },

        formatTime(dateStr) {
            return new Date(dateStr).toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        },

        showToast(message, type = 'info') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'position-fixed bottom-0 end-0 p-3';
                container.style.zIndex = '1100';
                document.body.appendChild(container);
            }

            const toastEl = document.createElement('div');
            toastEl.className = 'toast align-items-center border-0 shadow-lg';
            toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
            container.appendChild(toastEl);
            const toast = new bootstrap.Toast(toastEl, {
                autohide: true,
                delay: 3000
            });
            toast.show();
            toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        SalesHistory.init();
        document.getElementById('btn-confirm-return').addEventListener('click', () => SalesHistory.submitReturn());

        // Check for URL parameters to auto-view a specific transaction
        const urlParams = new URLSearchParams(window.location.search);
        const searchParam = urlParams.get('search');
        const viewIdParam = urlParams.get('view_id');

        if (searchParam) {
            const searchInput = document.getElementById('filter-search');
            if (searchInput) {
                searchInput.value = searchParam;
                // The load() is usually called by init(), but if searchParam is present,
                // we'll fetch again or let init() handle it. We can simply call load().
                SalesHistory.load();
            }
        }

        if (viewIdParam) {
            // Delay slightly to ensure modals and resources are fully ready
            setTimeout(() => {
                SalesHistory.viewDetails(viewIdParam);
            }, 500);
        }
    });
</script>

<script src="../../assets/js/pos/receipt-preview.js?v=<?= time() ?>"></script>
<?php require_once '../../includes/footer.php'; ?>