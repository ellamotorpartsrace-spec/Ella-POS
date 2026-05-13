<?php

require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
// Auth Check
requirePermission('make_sales');

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="../../assets/css/pos/checkout.css?v=<?= time() ?>">


<div class="pos-checkout-wrapper">
    <div class="row g-3">

        <!-- ================= PRODUCT SEARCH ================= -->
        <div class="col-md-7">
            <div class="card shadow-sm border-0 product-panel">
                <div class="card-header py-3 border-bottom"
                    style="background: var(--card-bg); border-color: var(--border-color) !important;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="fa-solid fa-boxes-stacked me-2"></i>Product Catalog
                        </h5>
                        <div class="d-flex align-items-center gap-3">
                            <div class="view-toggle-group d-flex rounded-pill">
                                <button class="btn view-btn active text-primary shadow-sm bg-white" id="view-grid"
                                    title="Grid View">
                                    <i class="fa-solid fa-grid-2"></i>
                                </button>
                                <button class="btn view-btn text-muted" id="view-compact" title="Compact View">
                                    <i class="fa-solid fa-list-ul" style="font-size: 0.8rem;"></i>
                                </button>
                                <button class="btn view-btn text-muted" id="view-list" title="List View">
                                    <i class="fa-solid fa-bars"></i>
                                </button>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="barcode-mode-toggle">
                                <label class="form-check-label small fw-bold text-muted me-2">
                                    <i class="fa-solid fa-barcode me-1"></i>Scanner Mode
                                </label>
                                <span id="mobile-relay-badge" class="badge bg-success d-none animated fadeIn"
                                    style="font-size: 10px; cursor: help;">
                                    <i class="fa-solid fa-mobile-screen me-1"></i>Mobile Active
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Category Quick Filter -->
                    <div class="category-filter-wrapper mb-3">
                        <div id="category-tabs" class="d-flex gap-2 overflow-auto pb-1"
                            style="scrollbar-width: none; -ms-overflow-style: none;">
                            <button class="btn btn-sm btn-primary rounded-pill px-3 active-category"
                                data-category-id="all">All</button>
                            <!-- Dynamic Categories -->
                        </div>
                    </div>

                    <div class="input-group input-group-lg shadow-sm">
                        <span class="input-group-text border-0"
                            style="background: var(--bg-surface); border-color: var(--border-color) !important;">
                            <i class="fa-solid fa-magnifying-glass text-muted"></i>
                        </span>
                        <input type="text" id="product-search" class="form-control border-0"
                            style="background: var(--bg-surface); color: var(--text-primary);"
                            placeholder="Scan barcode or type item name..." autofocus>
                    </div>
                </div>

                <div class="card-body p-3 overflow-auto" style="background: var(--bg-surface);">
                    <div id="search-results" class="search-results-grid grid-mode pt-2">
                        <div class="text-center text-muted mt-5 pt-5 w-100" id="catalog-empty-state"
                            style="grid-column: 1 / -1;">
                            <i class="fa-solid fa-magnifying-glass fa-3x mb-3 opacity-25"></i>
                            <h6>Search Catalog</h6>
                            <p class="small mb-0 text-muted">Type to search or select a category to view items</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= RIGHT COLUMN ================= -->
        <div class="col-md-5 d-flex flex-column">

            <!-- ================= CUSTOMER ================= -->
            <div class="card shadow-sm border-0 mb-2 customer-card">
                <div class="card-body p-3">

                    <label class="small fw-bold text-uppercase text-muted mb-2">
                        <i class="fa-solid fa-user me-1"></i> Customer
                    </label>

                    <!-- MODE -->
                    <div class="btn-group w-100 mb-2">
                        <input type="radio" class="btn-check" name="customer-mode" id="cust-walkin" value="walkin"
                            checked>
                        <label class="btn btn-outline-primary btn-sm" for="cust-walkin">
                            Walk-in
                        </label>

                        <input type="radio" class="btn-check" name="customer-mode" id="cust-existing" value="existing">
                        <label class="btn btn-outline-primary btn-sm" for="cust-existing">
                            Existing Buyer
                        </label>
                    </div>

                    <!-- WALK-IN -->
                    <input type="text" id="buyer-name" class="form-control"
                        style="background: var(--bg-surface); color: var(--text-primary); border: 1px solid var(--border-color);"
                        placeholder="Walk-in Customer">

                    <!-- EXISTING SEARCH -->
                    <div class="mt-2 d-none" id="buyer-search-wrapper">
                        <button type="button" class="btn btn-outline-primary w-100" id="btn-search-buyer">
                            <i class="fa-solid fa-search me-2"></i>Search Existing Buyer
                        </button>
                    </div>

                    <!-- SELECTED BUYER CARD (Shows after selection) -->
                    <div class="card border-primary mt-3 d-none" id="selected-buyer-card">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div class="fw-bold" id="selected-buyer-name"></div>
                                <button class="btn btn-sm btn-outline-danger" id="btn-change-buyer">
                                    Change
                                </button>
                            </div>

                            <div class="small text-muted" id="selected-buyer-shop"></div>
                            <div class="small" id="selected-buyer-contact"></div>
                            <div class="small text-muted" id="selected-buyer-address"></div>

                            <span class="badge bg-info mt-1" id="selected-buyer-tier"></span>

                            <!-- Wallet Balance -->
                            <div id="buyer-wallet-display" class="d-none mt-2 pt-2 border-top">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small fw-bold text-muted"><i
                                            class="fa-solid fa-wallet me-1"></i>Wallet:</span>
                                    <span class="fw-bold text-success" id="buyer-wallet-balance">₱0.00</span>
                                </div>
                            </div>

                            <!-- Purchase history stats (populated by JS) -->
                            <div id="buyer-history-stats" class="d-none mt-2 pt-2 border-top">
                                <div class="d-flex align-items-center gap-1">
                                    <i class="fa-solid fa-chart-line text-primary opacity-75"
                                        style="font-size:10px;"></i>
                                    <span id="buyer-history-text" class="small text-muted"
                                        style="font-size:11px;"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="buyer-id">
                </div>
            </div>

            <!-- ================= BUYER SEARCH MODAL ================= -->
            <div class="modal fade" id="buyerSearchModal" tabindex="-1" aria-labelledby="buyerSearchModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white py-3">
                            <h5 class="modal-title" id="buyerSearchModalLabel">
                                <i class="fa-solid fa-users me-2"></i>Search Buyer
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-3">
                            <div class="input-group mb-3">
                                <span class="input-group-text border-end-0"
                                    style="background: var(--bg-surface); border-color: var(--border-color) !important;">
                                    <i class="fa-solid fa-search text-muted"></i>
                                </span>
                                <input type="text" id="buyer-search" class="form-control border-start-0"
                                    style="background: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color) !important;"
                                    placeholder="Type buyer name, phone, or shop..." autocomplete="off">
                            </div>
                            <div id="buyer-results" class="buyer-modal-results">
                                <div class="text-center text-muted py-4">
                                    <i class="fa-solid fa-users fa-2x mb-3 opacity-25"></i>
                                    <p class="mb-0 small">Start typing to search buyers</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- ================= DRAFT UI ================= -->
            <div class="card shadow-sm border-0 mb-2">
                <div class="card-body p-2">
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm w-50" onclick="DraftUI.saveDraft()">
                            💾 Save Draft
                        </button>
                        <button class="btn btn-outline-primary btn-sm w-50" onclick="DraftUI.loadDraft()">
                            📂 Load Draft
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= HELD TRANSACTIONS ================= -->
            <div id="hold-badge-bar" class="held-badge-bar d-none mb-2 px-1"></div>

            <!-- ================= CART ================= -->
            <div class="card shadow-sm border-0 cart-card mb-2">
                <div class="card-header bg-dark text-white d-flex justify-content-between py-2">
                    <span class="small fw-bold text-uppercase">
                        <i class="fa-solid fa-cart-shopping me-2"></i>Current Order
                        <span id="cart-count-badge" class="badge bg-primary ms-2 d-none"
                            style="font-size:9px; vertical-align:middle;"></span>
                        <span id="cart-savings-badge" class="cart-savings-badge ms-1 d-none"></span>
                    </span>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-light py-0 px-2 fw-bold opacity-50" id="btn-cart-undo"
                            onclick="CartManager.undoLastAction()" title="Undo last action (Ctrl+Z)" disabled>
                            <i class="fa-solid fa-rotate-left me-1"></i>UNDO
                        </button>
                        <button class="btn btn-sm btn-warning py-0 px-2 fw-bold" onclick="HoldCart.hold()"
                            title="Hold transaction (F3)">
                            <i class="fa-solid fa-pause me-1"></i>HOLD
                        </button>
                        <button class="btn btn-sm btn-danger py-0" onclick="SimpleCheckout.clearCart()">
                            CLEAR
                        </button>
                    </div>
                </div>

                <div class="card-body p-0 overflow-auto">
                    <!-- Cart Filter -->
                    <div id="cart-filter-wrapper" class="d-none px-2 py-1 border-bottom"
                        style="background:var(--bg-surface,#f8f9fa);">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text border-0 bg-transparent px-2"><i
                                    class="fa-solid fa-filter text-muted" style="font-size:11px;"></i></span>
                            <input type="text" id="cart-filter-input" class="form-control border-0 bg-transparent py-1"
                                placeholder="Filter cart items..." style="font-size:12px; box-shadow:none;"
                                oninput="CartManager.filterCart(this.value)">
                            <button class="btn btn-sm border-0 px-2 text-muted" onclick="CartManager.clearCartFilter()"
                                title="Clear filter" style="font-size:11px;">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="small text-uppercase text-muted">
                                <th class="ps-3">Product</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end pe-3">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="cart-tbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- ================= PAYMENT ================= -->
            <div class="card shadow-sm border-0 payment-card">
                <div class="d-flex justify-content-between mb-3">
                    <small class="fw-bold text-muted">TOTAL</small>
                    <h2 class="fw-bold text-primary mb-0" id="cart-total">₱0.00</h2>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="small fw-bold text-muted">METHOD</label>
                        <select class="form-select form-select-sm fw-bold" id="payment-method">
                            <option value="cash">💵 Cash</option>
                            <option value="gcash">📱 GCash</option>
                            <option value="bank">🏦 Bank</option>
                            <option value="financing">💳 Financing</option>
                            <option value="mix">🔀 Mix Payment</option>
                            <option value="pay_later">📅 Pay Later</option>
                            <option value="wallet" class="d-none" disabled>💳 Wallet</option>
                        </select>
                        <div id="wallet-hint" class="small text-danger mt-1" style="font-size: 10px;">
                            Select existing buyer to use wallet
                        </div>
                    </div>

                    <!-- New Discount Section -->
                    <div class="col-6">
                        <label class="small fw-bold text-muted">DISCOUNT</label>
                        <div class="input-group input-group-sm">
                            <button class="btn btn-outline-secondary" type="button" id="btn-add-discount">
                                <i class="fa-solid fa-tag"></i>
                            </button>
                            <input type="text" class="form-control fw-bold text-end" id="discount-display" value="0.00"
                                readonly style="background-color: #f8f9fa; cursor: pointer;"
                                onclick="SimpleCheckout.openDiscountModal()">
                        </div>
                    </div>

                    <div class="col-6" id="tendered-wrapper">
                        <label class="small fw-bold text-muted">TENDERED</label>
                        <input type="number" id="amount-tendered" class="form-control form-control-sm fw-bold text-end"
                            placeholder="0.00">
                    </div>
                    <div class="col-12 text-end" id="change-wrapper">
                        <small class="fw-bold text-muted">
                            Change:
                            <span id="change-display" class="text-success">₱0.00</span>
                        </small>
                    </div>

                    <!-- Quick Cash Buttons -->
                    <div class="col-12" id="quick-cash-wrapper">
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <button class="btn btn-outline-secondary btn-sm flex-fill fw-bold"
                                style="font-size:11px; min-width:42px;" onclick="QuickCash.add(20)">₱20</button>
                            <button class="btn btn-outline-secondary btn-sm flex-fill fw-bold"
                                style="font-size:11px; min-width:42px;" onclick="QuickCash.add(50)">₱50</button>
                            <button class="btn btn-outline-secondary btn-sm flex-fill fw-bold"
                                style="font-size:11px; min-width:42px;" onclick="QuickCash.add(100)">₱100</button>
                            <button class="btn btn-outline-secondary btn-sm flex-fill fw-bold"
                                style="font-size:11px; min-width:42px;" onclick="QuickCash.add(500)">₱500</button>
                            <button class="btn btn-outline-secondary btn-sm flex-fill fw-bold"
                                style="font-size:11px; min-width:42px;" onclick="QuickCash.add(1000)">₱1K</button>
                            <button class="btn btn-outline-dark btn-sm flex-fill fw-bold"
                                style="font-size:11px; min-width:52px;" onclick="QuickCash.exact()"
                                title="Set tendered to exact total">
                                <i class="fa-solid fa-equals me-1"></i>EXACT
                            </button>
                        </div>
                    </div>

                    <!-- Mix Payment Fields (hidden by default) -->
                    <div class="col-12 d-none" id="mix-payment-wrapper">
                        <div class="card bg-light border-0 p-2 mb-2">
                            <label class="small fw-bold text-muted mb-2"><i
                                    class="fa-solid fa-arrows-split-up-and-left me-1"></i>MIX PAYMENT DETAILS</label>

                            <div class="row g-2 mb-2 align-items-center">
                                <div class="col-4 text-end small fw-bold">Cash:</div>
                                <div class="col-8">
                                    <input type="number" class="form-control form-control-sm text-end mix-amount-input"
                                        id="mix-cash-amount" placeholder="0.00" step="0.01">
                                </div>
                            </div>

                            <div class="row g-2 mb-2 align-items-center">
                                <div class="col-4 text-end small fw-bold">GCash:</div>
                                <div class="col-8">
                                    <input type="number" class="form-control form-control-sm text-end mix-amount-input"
                                        id="mix-gcash-amount" placeholder="0.00" step="0.01">
                                </div>
                            </div>

                            <div class="row g-2 mb-2 align-items-center">
                                <div class="col-4 text-end small fw-bold">Bank:</div>
                                <div class="col-8">
                                    <input type="number" class="form-control form-control-sm text-end mix-amount-input"
                                        id="mix-bank-amount" placeholder="0.00" step="0.01">
                                </div>
                            </div>

                            <div class="row g-2 mb-2 align-items-center">
                                <div class="col-4 text-end small fw-bold">Financing:</div>
                                <div class="col-8">
                                    <input type="number" class="form-control form-control-sm text-end mix-amount-input"
                                        id="mix-hc-amount" placeholder="0.00" step="0.01">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-2 pt-2 border-top">
                                <small class="text-muted fw-bold">Mix Total:</small>
                                <strong id="mix-total-display" class="text-primary">₱0.00</strong>
                            </div>

                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted fw-bold">Remaining:</small>
                                <strong id="mix-remaining-display" class="text-danger">₱0.00</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pay Later Fields (hidden by default) -->
                <div class="row g-2 mb-3 d-none" id="pay-later-wrapper">
                    <div class="col-12">
                        <div class="alert alert-warning py-1 mb-2 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fa-solid fa-clock me-1"></i>
                                <small class="fw-bold">Scheduled Payments</small>
                            </div>
                            <div class="d-flex gap-1" style="font-size: 11px;">
                                <button type="button" class="btn btn-sm btn-outline-dark bg-white py-0 px-2 fw-bold"
                                    onclick="PaymentMethodHandler.setQuickDate('1month')"
                                    title="Set first due date to 30 days">30 Days</button>
                                <button type="button" class="btn btn-sm btn-outline-dark bg-white py-0 px-2 fw-bold"
                                    onclick="PaymentMethodHandler.setQuickDate(45)"
                                    title="Set first due date to 45 days">45 Days</button>
                                <button type="button" class="btn btn-sm btn-dark py-0"
                                    onclick="PaymentMethodHandler.addPayLaterRow()">
                                    <i class="fa-solid fa-plus me-1"></i>Add
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-12" id="pay-later-rows-container"
                        style="max-height: 250px; overflow-y: auto; overflow-x: hidden; padding-right: 5px;">
                        <!-- Dynamic rows will be inserted here -->
                    </div>

                    <div class="col-12 mt-2">
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <small class="text-muted fw-bold">Scheduled Total:</small>
                            <strong id="pay-later-total-display" class="text-primary">₱0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted fw-bold">Remaining:</small>
                            <strong id="pay-later-remaining-display" class="text-danger">₱0.00</strong>
                        </div>
                    </div>
                </div>

                <!-- Financing Fields (hidden by default) -->
                <div class="row g-2 mb-3 d-none" id="home-credit-wrapper">
                    <div class="col-12">
                        <div class="alert alert-info py-2 mb-2">
                            <i class="fa-solid fa-building-columns me-1"></i>
                            <small class="fw-bold">Financing Details</small>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="small fw-bold text-muted">FINANCING PROVIDER</label>
                        <select id="hc-provider" class="form-select form-select-sm fw-bold">
                            <option value="Home Credit">💳 Home Credit</option>
                            <option value="Skyro">📱 Skyro</option>
                            <option value="Salmon">🐟 Salmon</option>
                            <option value="Billease">💰 Billease</option>
                            <option value="Other">🏷️ Other</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="small fw-bold text-muted">DP METHOD</label>
                        <select id="hc-dp-method" class="form-select form-select-sm fw-bold">
                            <option value="cash">💵 Cash</option>
                            <option value="gcash">📱 GCash</option>
                            <option value="bank">🏦 Bank</option>
                            <option value="mix">🔀 Mix Payment</option>
                        </select>
                    </div>

                    <div class="col-12 d-none" id="hc-mix-wrapper">
                        <div class="card bg-light border-0 p-2 mt-1 mb-2">
                            <label class="small fw-bold text-muted mb-2"><i
                                    class="fa-solid fa-arrows-split-up-and-left me-1"></i>MIX DOWNPAYMENT</label>
                            <div class="row g-2 mb-2 align-items-center">
                                <div class="col-4 text-end small fw-bold">Cash:</div>
                                <div class="col-8">
                                    <input type="number"
                                        class="form-control form-control-sm text-end hc-mix-amount-input"
                                        id="hc-mix-cash" placeholder="0.00" step="0.01">
                                </div>
                            </div>
                            <div class="row g-2 mb-2 align-items-center">
                                <div class="col-4 text-end small fw-bold">GCash:</div>
                                <div class="col-8">
                                    <input type="number"
                                        class="form-control form-control-sm text-end hc-mix-amount-input"
                                        id="hc-mix-gcash" placeholder="0.00" step="0.01">
                                </div>
                            </div>
                            <div class="row g-2 align-items-center">
                                <div class="col-4 text-end small fw-bold">Bank:</div>
                                <div class="col-8">
                                    <input type="number"
                                        class="form-control form-control-sm text-end hc-mix-amount-input"
                                        id="hc-mix-bank" placeholder="0.00" step="0.01">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold text-muted">DOWN PAYMENT</label>
                        <input type="number" id="hc-downpayment" class="form-control form-control-sm fw-bold text-end"
                            placeholder="0.00" step="0.01" value="0">
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold text-muted">REFERENCE NO.</label>
                        <input type="text" id="hc-reference" class="form-control form-control-sm fw-bold"
                            placeholder="Contract #">
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between mt-2 pt-2 border-top">
                            <small class="text-muted fw-bold">Grand Total:</small>
                            <strong id="hc-grandtotal-display" class="text-dark">₱0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted fw-bold">Financed Amount:</small>
                            <strong id="hc-financed-display" class="text-primary">₱0.00</strong>
                        </div>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="small fw-bold text-muted">
                        RECEIPT FORMAT
                    </label>
                    <select id="paper-size-select" class="form-select form-select-sm fw-bold">
                        <option value="thermal80">Thermal 80mm</option>
                        <option value="thermal80x3276">Thermal 80x3276mm</option>
                        <option value="a4" selected>A4 Invoice</option>
                    </select>
                </div>

                <?php
                $globalPreviewEnabled = getSetting($_hconn, 'enable_pos_preview', '1') === '1';
                $canPreviewReceipt = $globalPreviewEnabled && hasPermission('pos_preview_receipt');
                ?>
                <div class="row g-2">
                    <?php if ($canPreviewReceipt): ?>
                        <div class="col-6">
                            <button class="btn btn-outline-dark w-100 fw-bold shadow-sm" id="btn-preview" disabled
                                onclick="SimpleCheckout.previewReceipt()">
                                <i class="fa-solid fa-eye me-1"></i> Preview
                            </button>
                        </div>
                    <?php endif; ?>
                    <div class="<?= $canPreviewReceipt ? 'col-6' : 'col-12' ?>">
                        <button class="btn btn-success w-100 fw-bold shadow-sm" id="btn-pay" disabled
                            onclick="SimpleCheckout.processPayment()">
                            PAY <i class="fa-solid fa-chevron-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ================= DRAFTS MODAL ================= -->
<div class="modal fade" id="draftsModal" tabindex="-1" aria-labelledby="draftsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title" id="draftsModalLabel">
                    <i class="fa-solid fa-folder-open me-2"></i>Saved Drafts
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-3" style="min-height: 300px; max-height: 60vh;">
                <!-- Loading State -->
                <div id="drafts-loading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2">Loading drafts...</p>
                </div>

                <!-- Empty State -->
                <div id="drafts-empty" class="text-center py-5 d-none">
                    <i class="fa-solid fa-folder-open fa-4x text-muted opacity-25 mb-3"></i>
                    <h6 class="text-muted">No Saved Drafts</h6>
                    <p class="small text-muted">Your saved orders will appear here.</p>
                </div>

                <!-- Drafts Grid -->
                <div id="drafts-grid" class="row g-3 d-none"></div>
            </div>
            <div class="modal-footer bg-light py-2">
                <small class="text-muted me-auto">
                    <i class="fa-solid fa-info-circle me-1"></i>
                    Click a draft card to load it
                </small>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================= DISCOUNT MODAL (Tabbed) ================= -->
<div class="modal fade" id="discountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark py-2">
                <h6 class="modal-title fw-bold">
                    <i class="fa-solid fa-tags me-2"></i>Discounts
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Tabs -->
                <ul class="nav nav-tabs nav-fill px-3 pt-2" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold small" data-bs-toggle="tab"
                            data-bs-target="#tab-brand-disc" type="button">
                            <i class="fa-solid fa-cubes me-1"></i>Brand
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold small" data-bs-toggle="tab" data-bs-target="#tab-total-disc"
                            type="button">
                            <i class="fa-solid fa-receipt me-1"></i>Total
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold small" data-bs-toggle="tab" data-bs-target="#tab-items-disc"
                            type="button" id="tab-items-disc-btn">
                            <i class="fa-solid fa-list-check me-1"></i>Items
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-3">
                    <!-- ===== TAB: Brand Discount ===== -->
                    <div class="tab-pane fade show active" id="tab-brand-disc">
                        <p class="small text-muted mb-2">Apply a discount to all items of a specific brand in this
                            transaction.</p>

                        <div class="mb-2">
                            <label class="form-label small fw-bold text-muted mb-1">SELECT BRAND</label>
                            <select class="form-select form-select-sm" id="brand-discount-select">
                                <option value="">-- Select Brand --</option>
                            </select>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">TYPE</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="brand-disc-type" id="brand-disc-percent"
                                        value="percent" checked>
                                    <label class="btn btn-outline-dark btn-sm" for="brand-disc-percent">%</label>
                                    <input type="radio" class="btn-check" name="brand-disc-type" id="brand-disc-fixed"
                                        value="fixed" disabled>
                                    <label class="btn btn-outline-dark btn-sm opacity-50" for="brand-disc-fixed"
                                        title="Fixed amount discount is only available for the Transaction Total.">₱</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">VALUE</label>
                                <input type="number" id="brand-discount-value"
                                    class="form-control form-control-sm fw-bold text-center" placeholder="0"
                                    step="0.01">
                            </div>
                        </div>
                        <button class="btn btn-warning btn-sm w-100 fw-bold mb-3"
                            onclick="SimpleCheckout.applyBrandDiscount()">
                            <i class="fa-solid fa-check me-1"></i>APPLY BRAND DISCOUNT
                        </button>

                        <label class="form-label small fw-bold text-muted mb-1">ACTIVE BRAND DISCOUNTS</label>
                        <div id="active-brand-discounts" class="border rounded p-2"
                            style="max-height: 120px; overflow-y: auto;">
                            <div class="text-muted small text-center py-2">No brand discounts applied</div>
                        </div>
                    </div>

                    <!-- ===== TAB: Total Discount ===== -->
                    <div class="tab-pane fade" id="tab-total-disc">
                        <p class="small text-muted mb-2">Apply a discount to the entire transaction total.</p>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">DISCOUNT TYPE</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="discount-type" id="disc-percent"
                                    value="percent" checked>
                                <label class="btn btn-outline-dark btn-sm" for="disc-percent">% Percent</label>
                                <input type="radio" class="btn-check" name="discount-type" id="disc-fixed"
                                    value="fixed">
                                <label class="btn btn-outline-dark btn-sm" for="disc-fixed">₱ Fixed</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">VALUE</label>
                            <input type="number" id="discount-value"
                                class="form-control form-control-lg fw-bold text-center" placeholder="0" step="0.01">
                        </div>
                        <button class="btn btn-warning fw-bold w-100 mb-2" onclick="SimpleCheckout.applyDiscount()">
                            APPLY TOTAL DISCOUNT
                        </button>
                    </div>

                    <!-- ===== TAB: Items Discount ===== -->
                    <div class="tab-pane fade" id="tab-items-disc">
                        <p class="small text-muted mb-2">Select items from the cart and apply a discount to all of them.
                        </p>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-bold text-muted mb-0">CART ITEMS</label>
                            <button class="btn btn-link btn-sm p-0 small" id="items-disc-toggle-all"
                                onclick="SimpleCheckout.toggleAllItemDisc()">
                                Select All
                            </button>
                        </div>
                        <div id="items-disc-list" class="border rounded p-2 mb-3"
                            style="max-height: 180px; overflow-y: auto;">
                            <div class="text-muted small text-center py-3">Cart is empty</div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">TYPE</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="items-disc-type" id="items-disc-percent"
                                        value="percent" checked>
                                    <label class="btn btn-outline-dark btn-sm" for="items-disc-percent">%
                                        Percent</label>
                                    <input type="radio" class="btn-check" name="items-disc-type" id="items-disc-fixed"
                                        value="fixed" disabled>
                                    <label class="btn btn-outline-dark btn-sm opacity-50" for="items-disc-fixed"
                                        title="Fixed amount discount is only available for the Transaction Total.">₱
                                        Fixed</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">VALUE</label>
                                <input type="number" id="items-disc-value"
                                    class="form-control form-control-sm fw-bold text-center" placeholder="0"
                                    step="0.01">
                            </div>
                        </div>
                        <button class="btn btn-warning btn-sm w-100 fw-bold"
                            onclick="SimpleCheckout.applyItemsDiscount()">
                            <i class="fa-solid fa-check me-1"></i>APPLY TO SELECTED
                        </button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="border-top p-2 text-center">
                    <button class="btn btn-outline-danger btn-sm" onclick="SimpleCheckout.removeDiscount()">
                        <i class="fa-solid fa-trash me-1"></i>Remove All Discounts
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= SAVE DRAFT MODAL ================= -->
<div class="modal fade" id="saveDraftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Save Draft
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">DRAFT LABEL (Optional)</label>
                    <input type="text" id="draft-label-input" class="form-control"
                        placeholder="e.g., Morning order, Reserve for John...">
                    <small class="text-muted">Give this draft a name to easily identify it later</small>
                </div>
                <div class="alert alert-info py-2 mb-0">
                    <i class="fa-solid fa-shopping-cart me-1"></i>
                    <span id="save-draft-summary">0 items • ₱0.00</span>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm" id="btn-confirm-save-draft">
                    <i class="fa-solid fa-check me-1"></i>Save Draft
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Auto-hide sidebar for POS Terminal to maximize workspace -->
<script>
    // Toggle sidebar on page load for better POS experience
    document.addEventListener('DOMContentLoaded', () => {
        const wrapper = document.getElementById('wrapper');
        if (wrapper && !wrapper.classList.contains('toggled')) {
            wrapper.classList.add('toggled');
        }
    });
</script>

<script>
    const CURRENT_USER_NAME = "<?= $_SESSION['username'] ?? 'Staff' ?>";

    const DraftUI = {
        modals: {
            drafts: null,
            save: null
        },

        init() {
            // Initialize Bootstrap modals
            const draftsModalEl = document.getElementById('draftsModal');
            const saveModalEl = document.getElementById('saveDraftModal');

            if (draftsModalEl) {
                this.modals.drafts = new bootstrap.Modal(draftsModalEl);
            }
            if (saveModalEl) {
                this.modals.save = new bootstrap.Modal(saveModalEl);
            }

            // Bind save draft confirm button
            document.getElementById('btn-confirm-save-draft')?.addEventListener('click', () => {
                this.confirmSaveDraft();
            });
        },

        // Show save draft modal
        saveDraft() {
            const cart = POS.cart || [];
            if (!cart.length) {
                this.showToast('Cart is empty. Nothing to save.', 'warning');
                return;
            }

            // Update summary in modal
            const total = cart.reduce((sum, item) => sum + (item.qty * item.price), 0);
            const summaryEl = document.getElementById('save-draft-summary');
            if (summaryEl) {
                summaryEl.textContent = `${cart.length} item(s) • ₱${total.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
            }

            // Clear previous label
            document.getElementById('draft-label-input').value = '';

            // Show modal
            this.modals.save?.show();
        },

        // Confirm and save draft
        async confirmSaveDraft() {
            const cart = POS.cart || [];
            const label = document.getElementById('draft-label-input')?.value?.trim() || null;

            const buyer = window.POS_BUYER || {
                buyer_id: null,
                buyer_name: document.getElementById('buyer-name')?.value || 'Walk-in Customer',
                shop_name: '',
                address: '',
                contact_number: '',
                price_tier: 'retail',
                is_walkin: true
            };

            const totalAmount = cart.reduce((sum, item) => sum + (item.qty * item.price), 0);

            // Disable button during save
            const btn = document.getElementById('btn-confirm-save-draft');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Saving...';

            try {
                const res = await fetch('../../api/pos/save_draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        buyer: buyer,
                        items: cart,
                        total_amount: totalAmount,
                        draft_label: label,
                        discount_data: {
                            brandDiscounts: POS.brandDiscounts || {},
                            globalDiscount: (typeof SimpleCheckout !== 'undefined' && SimpleCheckout.globalDiscount)
                                ? SimpleCheckout.globalDiscount
                                : { type: 'percent', value: 0 }
                        }
                    })
                });

                const data = await res.json();

                this.modals.save?.hide();

                if (data.success) {
                    this.showToast('Draft saved successfully!', 'success');
                } else {
                    this.showToast('Failed to save draft: ' + (data.error || 'Unknown error'), 'danger');
                }
            } catch (err) {
                console.error('Save draft error:', err);
                this.showToast('Network error saving draft', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        },

        // Show drafts modal and load drafts
        async loadDraft() {
            // Show modal first
            this.modals.drafts?.show();

            // Show loading state
            this.setModalState('loading');

            try {
                const res = await fetch('../../api/pos/list_drafts.php');
                const data = await res.json();

                if (!data.success || !data.drafts || data.drafts.length === 0) {
                    this.setModalState('empty');
                    return;
                }

                this.renderDraftCards(data.drafts);
                this.setModalState('grid');

            } catch (err) {
                console.error('Load drafts error:', err);
                this.setModalState('empty');
                this.showToast('Failed to load drafts', 'danger');
            }
        },

        setModalState(state) {
            const loading = document.getElementById('drafts-loading');
            const empty = document.getElementById('drafts-empty');
            const grid = document.getElementById('drafts-grid');

            loading?.classList.add('d-none');
            empty?.classList.add('d-none');
            grid?.classList.add('d-none');

            if (state === 'loading') loading?.classList.remove('d-none');
            if (state === 'empty') empty?.classList.remove('d-none');
            if (state === 'grid') grid?.classList.remove('d-none');
        },

        renderDraftCards(drafts) {
            const grid = document.getElementById('drafts-grid');
            if (!grid) return;

            grid.innerHTML = '';

            drafts.forEach(draft => {
                const customerName = draft.shop_name || draft.buyer_name || 'Walk-in Customer';
                const isShop = !!draft.shop_name;
                const icon = isShop ? 'fa-store' : 'fa-user';
                const tierBadge = draft.price_tier ? draft.price_tier.toUpperCase() : 'RETAIL';
                const total = parseFloat(draft.total || 0);
                const itemCount = parseInt(draft.item_count || 0);
                const outOfStockCount = parseInt(draft.out_of_stock_count || 0);
                const partialStockCount = parseInt(draft.partial_stock_count || 0);
                const label = draft.label || '';
                const createdAt = this.formatDate(draft.created_at);

                let stockWarningIcon = '';
                if (outOfStockCount > 0) {
                    stockWarningIcon += `<span class="text-danger ms-1" style="font-size: 0.85rem;" title="Contains out of stock items" data-bs-toggle="tooltip">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </span>`;
                }
                if (partialStockCount > 0) {
                    stockWarningIcon += `<span class="text-warning text-dark ms-1" style="font-size: 0.85rem;" title="Contains items with reduced stock vs draft" data-bs-toggle="tooltip">
                        <i class="fa-solid fa-circle-exclamation"></i>
                    </span>`;
                }

                const col = document.createElement('div');
                col.className = 'col-md-6';
                col.innerHTML = `
                    <div class="card draft-card h-100 border-0 shadow-sm" 
                         data-draft-id="${draft.draft_id}"
                         style="cursor: pointer; transition: all 0.2s ease;">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-2">
                                        <i class="fa-solid ${icon} text-primary"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="line-height: 1.2;">
                                            ${this.escapeHtml(customerName)}
                                        </div>
                                        ${label ? `<span class="badge bg-secondary bg-opacity-25 text-secondary small">${this.escapeHtml(label)}</span>` : ''}
                                    </div>
                                </div>
                                <span class="badge bg-info-subtle text-info-emphasis" style="font-size: 0.65rem;">
                                    ${tierBadge}
                                </span>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-end">
                                <div>
                                    <div class="h5 fw-bold text-primary mb-0">
                                        ₱${total.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                    </div>
                                    <small class="text-muted d-flex align-items-center">
                                        <i class="fa-solid fa-box me-1"></i>${itemCount} item(s) ${stockWarningIcon}
                                    </small>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">${createdAt}</small>
                                    <button class="btn btn-sm btn-outline-danger mt-1 btn-delete-draft" 
                                            data-draft-id="${draft.draft_id}"
                                            onclick="event.stopPropagation(); DraftUI.confirmDeleteDraft(${draft.draft_id})">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Add click handler to load draft
                const card = col.querySelector('.draft-card');
                card.addEventListener('click', () => this.selectDraft(draft.draft_id));

                // Add hover effect
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-2px)';
                    card.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                });
                card.addEventListener('mouseleave', () => {
                    card.style.transform = '';
                    card.style.boxShadow = '';
                });

                grid.appendChild(col);
            });
        },

        async selectDraft(draftId) {
            try {
                const loadRes = await fetch(`../../api/pos/load_draft.php?id=${draftId}`);
                const loadData = await loadRes.json();

                if (!loadData.success) {
                    this.showToast('Failed to load draft: ' + (loadData.error || 'Not found'), 'danger');
                    return;
                }

                // Close modal
                this.modals.drafts?.hide();

                // Restore buyer
                if (loadData.buyer) {
                    window.POS_BUYER = loadData.buyer;
                    this.restoreBuyerUI(loadData.buyer);
                }

                // Reset global and brand discounts before loading the new state
                if (typeof POS !== 'undefined') POS.brandDiscounts = {};
                if (typeof SimpleCheckout !== 'undefined') SimpleCheckout.globalDiscount = { type: 'percent', value: 0 };

                // Restore cart and validate stock
                let stockWarnings = [];
                let removedItems = [];

                POS.cart = (loadData.items || []).filter(item => {
                    const currentStock = parseInt(item.stock) || 0;
                    const loadedQty = parseInt(item.qty) || 1;

                    if (currentStock <= 0) {
                        removedItems.push(item.name);
                        return false; // Remove from cart
                    }

                    if (loadedQty > currentStock) {
                        item.qty = currentStock;
                        stockWarnings.push(`${item.name} (max ${currentStock})`);
                    }

                    return true;
                });

                // Restore discount state from draft
                if (loadData.discount_data) {
                    const dd = loadData.discount_data;
                    if (dd.brandDiscounts && typeof POS !== 'undefined') {
                        POS.brandDiscounts = dd.brandDiscounts;
                    }
                    if (dd.globalDiscount && typeof SimpleCheckout !== 'undefined') {
                        SimpleCheckout.globalDiscount = dd.globalDiscount;
                    }
                }

                CartManager.renderCart();

                if (removedItems.length > 0 || stockWarnings.length > 0) {
                    let parts = [];
                    if (removedItems.length > 0) parts.push(`Removed (Out of Stock): ${removedItems.join(', ')}`);
                    if (stockWarnings.length > 0) parts.push(`Qty Capped: ${stockWarnings.join(', ')}`);
                    this.showToast(parts.join(' | '), 'warning');
                } else {
                    this.showToast('Draft loaded successfully!', 'success');
                }

                // Ask to delete the loaded draft
                setTimeout(async () => {
                    const del = await EllaConfirm.show({
                        title: 'Delete Draft?',
                        message: 'Delete this draft from saved list?',
                        confirmText: 'Delete',
                        confirmClass: 'btn-danger',
                        icon: 'fa-trash-can',
                        iconColor: 'text-danger'
                    });
                    if (del) {
                        this.deleteDraft(draftId);
                    }
                }, 500);

            } catch (err) {
                console.error('Select draft error:', err);
                this.showToast('Network error loading draft', 'danger');
            }
        },

        async confirmDeleteDraft(draftId) {
            const del = await EllaConfirm.show({
                title: 'Delete Draft?',
                message: 'Are you sure you want to delete this draft?',
                confirmText: 'Delete',
                confirmClass: 'btn-danger',
                icon: 'fa-trash-can',
                iconColor: 'text-danger'
            });
            if (del) {
                this.deleteDraft(draftId, true);
            }
        },

        async deleteDraft(draftId, refreshList = false) {
            try {
                await fetch('../../api/pos/delete_draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        draft_id: draftId
                    })
                });

                if (refreshList) {
                    // Refresh the drafts list
                    this.loadDraft();
                    this.showToast('Draft deleted', 'success');
                }
            } catch (err) {
                console.error('Delete draft error:', err);
            }
        },

        restoreBuyerUI(buyer) {
            const card = document.getElementById('selected-buyer-card');
            const nameEl = document.getElementById('selected-buyer-name');
            const shopEl = document.getElementById('selected-buyer-shop');
            const contactEl = document.getElementById('selected-buyer-contact');
            const addressEl = document.getElementById('selected-buyer-address');
            const tierEl = document.getElementById('selected-buyer-tier');
            const buyerIdInput = document.getElementById('buyer-id');

            if (buyer.is_walkin || !buyer.buyer_id) {
                document.getElementById('cust-walkin').checked = true;
                document.getElementById('buyer-name').value = buyer.buyer_name || 'Walk-in Customer';
                document.getElementById('buyer-search-wrapper')?.classList.add('d-none');
                card?.classList.add('d-none');
            } else {
                document.getElementById('cust-existing').checked = true;
                document.getElementById('buyer-search-wrapper')?.classList.remove('d-none');

                if (nameEl) nameEl.textContent = buyer.buyer_name;
                if (shopEl) shopEl.textContent = buyer.shop_name || buyer.shop || '';
                if (contactEl) contactEl.textContent = buyer.contact_number || '';
                if (addressEl) addressEl.textContent = buyer.address || '';
                if (tierEl) tierEl.textContent = buyer.price_tier?.toUpperCase() || 'RETAIL';
                if (buyerIdInput) buyerIdInput.value = buyer.buyer_id;

                card?.classList.remove('d-none');
            }
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            if (diffDays === 1) return 'Yesterday';
            if (diffDays < 7) return `${diffDays} days ago`;

            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric'
            });
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showToast(message, type = 'info') {
            // Delegate to global EllaToast
            const typeMap = { success: 'success', danger: 'error', warning: 'warning', info: 'info' };
            const method = typeMap[type] || 'info';
            if (typeof EllaToast !== 'undefined') {
                EllaToast[method](message);
            }
        }
    };

    // Initialize DraftUI when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        DraftUI.init();

        // Check for 'recover' parameter (Recover Voided/Past Sale)
        // Check for 'draft_id' parameter (from Admin Draft Management)
        const urlParams = new URLSearchParams(window.location.search);
        const recoverId = urlParams.get('recover');
        const draftId = urlParams.get('draft_id');

        if (recoverId) {
            recoverSale(recoverId);
        } else if (draftId) {
            // Clear param from URL without reload
            const url = new URL(window.location);
            url.searchParams.delete('draft_id');
            window.history.replaceState({}, '', url);

            DraftUI.showToast('Loading draft...', 'info');
            DraftUI.selectDraft(draftId);
        }
    });


    // Recover Sale Logic
    async function recoverSale(saleId) {
        // Clear param from URL without reload
        const url = new URL(window.location);
        url.searchParams.delete('recover');
        window.history.replaceState({}, '', url);

        try {
            // Show loading
            DraftUI.showToast('Recovering items...', 'info');

            const res = await fetch(`../../api/pos/recover_sale_data.php?id=${saleId}`);
            const data = await res.json();

            if (!data.success) {
                DraftUI.showToast('Failed to recover: ' + (data.error || 'Unknown error'), 'danger');
                return;
            }

            // Restore Buyer
            if (data.buyer) {
                window.POS_BUYER = data.buyer;
                // Reuse DraftUI's helper to update UI
                DraftUI.restoreBuyerUI(data.buyer);
            }

            // Reset current brand and global discounts before loading recovered data
            if (typeof POS !== 'undefined') POS.brandDiscounts = {};
            if (typeof SimpleCheckout !== 'undefined') {
                SimpleCheckout.globalDiscount = {
                    type: 'fixed',
                    value: data.discount_amount || 0
                };
            }

            // Restore Cart & Attempt to reconstruct brand discount rules
            if (data.items && typeof POS !== 'undefined') {
                POS.cart = data.items;

                // Group items by brand to check for uniform discounts
                const brandGroups = {};
                data.items.forEach(item => {
                    if (!item.brand) return;
                    const b = item.brand.toUpperCase();
                    if (!brandGroups[b]) brandGroups[b] = [];
                    brandGroups[b].push(item);
                });

                for (const b in brandGroups) {
                    const groupItems = brandGroups[b];
                    if (groupItems.length === 0) continue;

                    let suspectedPct = null;
                    let isUniform = true;

                    groupItems.forEach(item => {
                        if (item.original_price > 0 && item.item_discount > 0) {
                            // Calculate effective percentage
                            const pct = Math.round((item.item_discount / item.original_price) * 100);
                            if (suspectedPct === null) suspectedPct = pct;
                            else if (suspectedPct !== pct) isUniform = false;
                        } else if (item.item_discount === 0) {
                            isUniform = false;
                        }
                    });

                    // If all items of this brand have the same discount percentage, 
                    // reconstruct it as a Brand (Batch) Discount rule
                    if (isUniform && suspectedPct > 0) {
                        POS.brandDiscounts[b] = { type: 'percent', value: suspectedPct };

                        // Clear the 'custom' type flag so the UI recognizes these as rule-based
                        groupItems.forEach(item => {
                            item.manual_discount = 0;
                            item.manual_discount_type = 'fixed';
                        });
                    }
                }
            }

            CartManager.renderCart();
            DraftUI.showToast(`Recovered items from ${data.sale_ref}`, 'success');

        } catch (err) {
            console.error('Recover error:', err);
            DraftUI.showToast('Network error recovering sale', 'danger');
        }
    }
</script>

<!-- Old/Missing Scripts (Replaced by modular scripts below) -->
<!--
<script src="../../assets/js/pos/receipt-preview.js?v=<?= time() ?>"></script>
<script src="../../assets/js/pos/tes2.js"></script>
<script src="../../assets/js/pos/new.js"></script>
-->

<!-- POS Modular Scripts (Load in order of dependencies) -->
<script src="../../assets/js/pos/pos-config.js?v=<?= time() ?>"></script>
<script src="../../assets/js/pos/customer-selector.js?v=<?= time() ?>"></script>
<script src="../../assets/js/pos/product-search.js?v=<?= time() ?>"></script>
<script src="../../assets/js/pos/scanner-mode.js?v=<?= time() ?>"></script>
<script src="../../assets/js/pos/payment-handler.js?v=<?= time() ?>"></script>
<script src="../../assets/js/pos/cart-manager.js?v=<?= time() ?>"></script>
<script src="../../assets/js/pos/offline-queue.js?v=<?= time() ?>"></script>
<script src="../../assets/js/pos/pos-init.js?v=<?= time() ?>"></script>
<script src="../../assets/js/pos/receipt-preview.js?v=<?= time() ?>"></script>
<script src="../../assets/js/pos/receipt-functions.js?v=<?= time() ?>"></script>
<script src="../../assets/js/pos/hold-cart.js?v=<?= time() ?>"></script>

<?php require_once '../../includes/footer.php'; ?>