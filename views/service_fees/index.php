<?php
// views/service_fees/index.php
// Service Fees / Shipping Fees Dashboard

require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check (Using dedicated permission for service fees)
requirePermission('manage_service_fees');


$page_title = 'Service Fees & Shipping - Ella POS';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    .service-fees-page {
        animation: ellaFadeIn 0.5s ease-out;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .status-pending {
        background: rgba(107, 114, 128, 0.1);
        color: #64748b;
        border: 1px solid rgba(107, 114, 128, 0.2);
    }

    .status-partial {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .status-paid {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .status-overdue {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.3);
        box-shadow: 0 0 10px rgba(239, 68, 68, 0.2);
        animation: status-glow-red 2s infinite;
    }

    .status-due-today {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }

    .status-voided {
        background: rgba(100, 116, 139, 0.1);
        color: #64748b;
        border: 1px solid rgba(100, 116, 139, 0.2);
        text-decoration: line-through;
        opacity: 0.7;
    }

    @keyframes status-glow-red {

        0%,
        100% {
            box-shadow: 0 0 5px rgba(239, 68, 68, 0.2);
        }

        50% {
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.4);
        }
    }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: radial-gradient(circle at top right, var(--primary-light), transparent 70%);
        opacity: 0.3;
        pointer-events: none;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08);
        border-color: var(--primary-color);
    }

    .stat-card .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.05);
    }

    .stat-card .stat-content {
        flex: 1;
    }

    .stat-card .stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-primary);
        line-height: 1.2;
    }

    .stat-card .stat-label {
        font-size: 0.8rem;
        color: var(--text-secondary);
        font-weight: 600;
        margin-top: 2px;
    }

    .x-small {
        font-size: 0.65rem !important;
    }

    .icon-total {
        background: rgba(139, 92, 246, 0.1);
        color: #8b5cf6;
    }

    .icon-pending {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }

    .icon-overdue {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .customer-type-card {
        border: 2px solid var(--border-color);
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        background: var(--bg-surface);
        height: 100%;
    }

    .customer-type-card.active {
        border-color: var(--primary-color);
        background: var(--primary-light);
        box-shadow: 0 8px 20px rgba(139, 92, 246, 0.1);
    }

    .customer-type-card .card-icon {
        width: 48px;
        height: 48px;
        background: var(--card-bg);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: var(--text-secondary);
        margin: 0 auto 12px;
        transition: all 0.3s;
    }

    .customer-type-card.active .card-icon {
        background: var(--primary-color);
        color: white;
    }

    .customer-type-card .card-title {
        font-weight: 800;
        color: var(--text-primary);
        font-size: 0.9rem;
        margin-bottom: 4px;
    }

    .customer-type-card .card-desc {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .card-table-wrapper {
        border-radius: 28px;
        border: 1px solid var(--border-color);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        background: var(--card-bg);
        position: relative;
        overflow: visible !important;
    }

    .table-responsive {
        overflow: visible !important;
    }

    .table-custom thead th {
        background: var(--bg-surface);
        padding: 20px 24px;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--text-secondary);
        border-bottom: 1px solid var(--border-color);
    }

    .table-custom tbody td {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
        position: relative;
        overflow: visible !important;
    }

    .table-custom tbody tr:last-child td {
        border-bottom: none;
    }

    .amount-cell {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .balance-cell {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.05);
        display: inline-block;
        padding: 4px 10px;
        border-radius: 8px;
    }

    .modal-content {
        border-radius: 32px;
        border: none;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 24px 32px;
    }

    .modal-body {
        padding: 32px;
    }

    .upload-area {
        border: 2px dashed var(--border-color);
        border-radius: 20px;
        padding: 32px;
        text-align: center;
        background: var(--bg-surface);
        transition: all 0.3s;
        cursor: pointer;
    }

    .upload-area:hover {
        border-color: var(--primary-color);
        background: var(--primary-light);
    }

    .history-timeline {
        position: relative;
        padding-left: 20px;
    }

    .history-timeline::before {
        content: '';
        position: absolute;
        left: 36px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--border-color);
    }

    .history-item {
        position: relative;
        padding: 20px 0 20px 48px;
    }

    .history-icon {
        position: absolute;
        left: 20px;
        top: 22px;
        width: 32px;
        height: 32px;
        z-index: 2;
        box-shadow: 0 0 0 4px var(--card-bg);
    }

    .search-result-item {
        padding: 10px 15px;
        cursor: pointer;
        border-bottom: 1px solid var(--border-color);
    }

    .search-result-item:hover {
        background: var(--bg-surface);
    }

    #buyerSearchResults {
        position: absolute;
        width: 100%;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        display: none;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    /* Mobile Responsive Adjustments */
    @media (max-width: 991.98px) {
        .stats-row {
            grid-template-columns: 1fr;
        }

        .stat-card {
            padding: 16px;
        }

        .card-table-wrapper {
            border: none;
            box-shadow: none;
            background: transparent;
        }

        .table-custom thead {
            display: none;
            /* Hide headers on mobile */
        }

        .table-custom tbody tr {
            display: block;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            margin-bottom: 16px;
            padding: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s;
        }

        .table-custom tbody tr:hover {
            transform: translateY(-2px);
        }

        .table-custom tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border: none;
            text-align: right;
            font-size: 0.85rem;
        }

        .table-custom tbody td::before {
            content: attr(data-label);
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.65rem;
            color: var(--text-secondary);
            text-align: left;
            margin-right: 16px;
        }

        .table-custom tbody td:first-child {
            padding-top: 0;
            margin-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
        }

        .table-custom tbody td:first-child::before {
            display: none;
        }

        .table-custom tbody td:first-child .badge {
            font-size: 0.8rem;
        }

        .table-custom tbody td:last-child {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
            justify-content: center;
        }

        .table-custom tbody td:last-child::before {
            display: none;
        }

        .amount-cell {
            font-size: 1rem;
        }

        .balance-cell {
            padding: 2px 8px;
        }

        .d-flex.gap-2.justify-content-end {
            width: 100%;
            justify-content: center !important;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.8rem;
            flex: 1;
        }

        /* Filter visibility */
        .col-md-3,
        .col-md-2,
        .col-md-1 {
            width: 100%;
        }

        #statusFilters {
            justify-content: center;
        }

        #statusFilters .btn {
            flex: 1 1 auto;
            font-size: 0.75rem;
            padding: 8px 12px;
        }

        .modal-body {
            padding: 20px;
        }

        .col-lg-5.border-end {
            border-right: none !important;
            border-bottom: 1px solid var(--border-color);
            padding-right: 0.75rem !important;
            padding-bottom: 24px !important;
            margin-bottom: 8px;
        }

        .col-lg-7.ps-4 {
            padding-left: 0.75rem !important;
        }

        .modal-header {
            padding: 16px 20px;
        }

        .history-timeline::before {
            left: 26px;
        }

        .history-item {
            padding-left: 40px;
        }

        .history-icon {
            left: 10px;
        }

        .service-fees-page>.d-flex>.btn-primary {
            width: 100%;
            padding: 12px !important;
        }

        .table-custom tbody td[data-label="Due Date"] input {
            width: 150px !important;
            max-width: 60%;
        }
    }
</style>

<div class="service-fees-page">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-800 mb-1">
                <i class="fas fa-truck-fast me-2 text-primary"></i>Service & Shipping Fees
            </h2>
            <p class="text-secondary mb-0 small">Track shipping, delivery, and service charges billed to buyers</p>
        </div>
        <button class="btn btn-primary px-4 py-2" style="border-radius: 12px; font-weight: 700;"
            onclick="openCreateModal()">
            <i class="fas fa-plus me-2"></i>New Fee
        </button>
    </div>

    <!-- Stats -->
    <div class="stats-row" id="statsRow">
        <div class="stat-card">
            <div class="stat-icon icon-total"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-content">
                <div class="stat-value" id="statTotal">0</div>
                <div class="stat-label">Total Records</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-pending"><i class="fas fa-clock-rotate-left"></i></div>
            <div class="stat-content">
                <div class="stat-value text-warning" id="statPending">₱0.00</div>
                <div class="stat-label">Total Outstanding</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-overdue"><i class="fas fa-circle-exclamation"></i></div>
            <div class="stat-content">
                <div class="stat-value text-danger" id="statOverdue">0</div>
                <div class="stat-label">Overdue Items</div>
            </div>
        </div>
    </div>

    <!-- Advanced Filters -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 20px; background: var(--bg-surface);">
        <div class="card-body p-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0" style="border-radius: 12px 0 0 12px;">
                            <i class="fas fa-search text-secondary"></i>
                        </span>
                        <input type="text" id="searchInput" class="form-control border-start-0"
                            placeholder="Search buyer or ref..." style="border-radius: 0 12px 12px 0; box-shadow: none;"
                            oninput="applyFilters()">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label x-small fw-bold text-secondary text-uppercase mb-1">Fee Type</label>
                    <select id="typeFilter" class="form-select form-select-sm" style="border-radius: 10px;"
                        onchange="applyFilters()">
                        <option value="all">All Types</option>
                        <option value="shipping">Shipping</option>
                        <option value="delivery">Delivery</option>
                        <option value="handling">Handling</option>
                        <option value="service">Service</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label x-small fw-bold text-secondary text-uppercase mb-1">From Date</label>
                    <input type="date" id="dateFrom" class="form-control form-control-sm" style="border-radius: 10px;"
                        onchange="applyFilters()">
                </div>

                <div class="col-md-3">
                    <label class="form-label x-small fw-bold text-secondary text-uppercase mb-1">To Date</label>
                    <input type="date" id="dateTo" class="form-control form-control-sm" style="border-radius: 10px;"
                        onchange="applyFilters()">
                </div>

                <div class="col-md-1">
                    <button class="btn btn-outline-secondary btn-sm w-100" style="border-radius: 10px; padding: 7px;"
                        title="Export CSV" onclick="exportFilteredCSV()">
                        <i class="fas fa-file-csv"></i>
                    </button>
                </div>
            </div>

            <!-- Status Pills -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex flex-wrap gap-2" id="statusFilters">
                    <button class="btn btn-outline-secondary rounded-pill px-4" data-status="all"
                        onclick="setStatusFilter('all')">All</button>
                    <button class="btn btn-primary rounded-pill px-4 active" data-status="active"
                        onclick="setStatusFilter('active')">Active (Pending/Partial)</button>
                    <button class="btn btn-outline-secondary rounded-pill px-4" data-status="overdue"
                        onclick="setStatusFilter('overdue')">Overdue</button>
                    <button class="btn btn-outline-secondary rounded-pill px-4" data-status="paid"
                        onclick="setStatusFilter('paid')">Paid</button>
                    <button class="btn btn-outline-secondary rounded-pill px-4" data-status="voided"
                        onclick="setStatusFilter('voided')">Voided</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card-table-wrapper mb-5">
        <div class="table-responsive">
            <table class="table table-hover table-custom mb-0" id="feesTable">
                <thead>
                    <tr>
                        <th>Fee Ref</th>
                        <th>Buyer / Customer</th>
                        <th>Fee Type</th>
                        <th>Due Date</th>
                        <th>Total Fee</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="spinner-border text-primary me-2"></div> Loading...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Fee Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-800"><i class="fas fa-plus-circle me-2"></i>Create Service Fee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createFeeForm">
                    <!-- Customer Type Selection Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="customer-type-card active" id="typeExisting"
                                onclick="setCustomerType('existing')">
                                <div class="card-icon"><i class="fas fa-user-check"></i></div>
                                <div class="card-title">Existing Buyer</div>
                                <div class="card-desc">Registered Account</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="customer-type-card" id="typeWalkin" onclick="setCustomerType('walkin')">
                                <div class="card-icon"><i class="fas fa-user-plus"></i></div>
                                <div class="card-title">Walk-in / New</div>
                                <div class="card-desc">No Account Required</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-12 position-relative" id="buyerSelectorWrapper">
                            <label class="form-label text-secondary fw-bold small text-uppercase" id="buyerLabel">Select
                                Existing Buyer</label>
                            <input type="text" class="form-control form-control-lg" id="searchBuyerInput"
                                placeholder="Start typing name or shop..." required autocomplete="off">
                            <input type="hidden" id="selectedBuyerId" name="buyer_id">
                            <input type="hidden" id="walkinName" name="buyer_name">
                            <div id="buyerSearchResults"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-bold small text-uppercase">Fee Type</label>
                            <select class="form-select form-select-lg" name="fee_type" required>
                                <option value="shipping">Shipping Fee</option>
                                <option value="delivery">Delivery Charge</option>
                                <option value="handling">Handling Fee</option>
                                <option value="service">Service Charge</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-bold small text-uppercase">Amount</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" name="amount" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-bold small text-uppercase">Due Date</label>
                            <input type="date" class="form-control form-control-lg" name="due_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-bold small text-uppercase">Linked POS Sale Ref
                                (Optional)</label>
                            <input type="text" class="form-control form-control-lg" name="sale_ref"
                                placeholder="e.g. POS-2026...">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary fw-bold small text-uppercase">Description</label>
                            <input type="text" class="form-control" name="description"
                                placeholder="Short description of the charge...">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary fw-bold small text-uppercase">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"
                                placeholder="Internal notes..."></textarea>
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="button" class="btn btn-light px-4 py-2 rounded-3 me-2"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-bold"
                            id="btnCreateFee">Create Fee Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Payment Settlement Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-800"><i class="fas fa-wallet me-2"></i>Settle Service Fee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Left: Summary -->
                    <div class="col-lg-5 border-end pe-4">
                        <h4 class="fw-800 mb-1" id="lblBuyerName">Buyer Name</h4>
                        <span class="badge bg-light text-dark border mb-4" id="lblFeeRef">REF</span>

                        <div class="p-3 bg-light rounded-4 mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-secondary fw-bold">Total Fee:</span>
                                <span class="fw-bold">₱<span id="lblTotal">0.00</span></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-secondary fw-bold">Paid:</span>
                                <span class="text-success fw-bold">₱<span id="lblPaid">0.00</span></span>
                            </div>
                            <div class="d-flex justify-content-between pt-2 border-top">
                                <span class="text-secondary fw-bold">Outstanding:</span>
                                <span class="text-danger fw-bold fs-5">₱<span id="lblBalance">0.00</span></span>
                            </div>
                        </div>

                        <div id="lblDescription" class="small text-secondary mb-2"></div>
                    </div>

                    <!-- Right: Action/History Tabs -->
                    <div class="col-lg-7 ps-4">
                        <ul class="nav nav-pills mb-4 gap-2" role="tablist">
                            <li class="nav-item"><button class="nav-link active rounded-pill px-4" data-bs-toggle="pill"
                                    data-bs-target="#tab-pay">Add Payment</button></li>
                            <li class="nav-item"><button class="nav-link rounded-pill px-4" data-bs-toggle="pill"
                                    data-bs-target="#tab-history">History</button></li>
                        </ul>

                        <div class="tab-content">
                            <!-- Pay Tab -->
                            <div class="tab-pane fade show active" id="tab-pay">
                                <form id="addPaymentForm">
                                    <input type="hidden" id="payFeeId">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-secondary">Amount to Pay</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" class="form-control" id="payAmount" step="0.01"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-secondary">Method</label>
                                            <select class="form-select" id="payMethod">
                                                <option value="cash">Cash</option>
                                                <option value="gcash">GCash</option>
                                                <option value="bank">Bank Transfer</option>
                                                <option value="check">Check</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-secondary">Reference No
                                                (Optional)</label>
                                            <input type="text" class="form-control" id="payRef">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-secondary">Notes</label>
                                            <textarea class="form-control" id="payNotes" rows="2"></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-secondary">Proof of
                                                Payment</label>
                                            <div class="upload-area"
                                                onclick="document.getElementById('payImages').click()">
                                                <i class="fas fa-camera text-primary mb-2 fs-3"></i>
                                                <p class="small text-secondary mb-0">Click to upload receipts</p>
                                                <input type="file" id="payImages" class="d-none" multiple
                                                    accept="image/*">
                                            </div>
                                            <div id="payImagesPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 mt-4 py-3 rounded-3 fw-bold"
                                        id="btnSubmitPayment">Confirm Payment</button>
                                </form>
                            </div>

                            <!-- History Tab -->
                            <div class="tab-pane fade" id="tab-history">
                                <div class="history-timeline" id="historyContainer">
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/css/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script>
    const BASE_URL = "<?php echo BASE_URL; ?>";
    let showAll = false;
    let allData = [];
    let currentFeeId = null;

    const createModal = new bootstrap.Modal(document.getElementById('createModal'));
    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));

    function setCustomerType(type) {
        const existingCard = document.getElementById('typeExisting');
        const walkinCard = document.getElementById('typeWalkin');
        const searchInput = document.getElementById('searchBuyerInput');
        const buyerLabel = document.getElementById('buyerLabel');
        const selectedId = document.getElementById('selectedBuyerId');
        const walkinNameField = document.getElementById('walkinName');

        if (type === 'existing') {
            existingCard.classList.add('active');
            walkinCard.classList.remove('active');
            buyerLabel.innerText = "Select Existing Buyer";
            searchInput.placeholder = "Start typing name or shop...";
            walkinNameField.value = '';
            selectedId.value = '';
            searchInput.value = '';
        } else {
            walkinCard.classList.add('active');
            existingCard.classList.remove('active');
            buyerLabel.innerText = "Walk-in Customer Name";
            searchInput.placeholder = "Enter buyer name for the record...";
            selectedId.value = '';
            walkinNameField.value = '';
            searchInput.value = '';
        }
    }

    let currentStatus = 'active'; // Default to active (pending+partial)

    function setStatusFilter(status) {
        currentStatus = status;

        // Update button styles
        document.querySelectorAll('#statusFilters button').forEach(btn => {
            btn.classList.remove('btn-primary', 'active');
            btn.classList.add('btn-outline-secondary');
        });

        const activeBtn = document.querySelector(`#statusFilters button[data-status="${status}"]`);
        if (activeBtn) {
            activeBtn.classList.remove('btn-outline-secondary');
            activeBtn.classList.add('btn-primary', 'active');
        }

        applyFilters();
    }

    // Load Data (Always fetch all, filter client-side)
    async function loadData() {
        const tbody = document.querySelector('#feesTable tbody');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>';

        try {
            const url = `${BASE_URL}api/service_fees/service_fees.php?all=1`;
            const res = await fetch(url);
            const data = await res.json();
            if (data.status === 'success') {
                allData = data.data;
                applyFilters();
                updateStats(data.data);
            } else {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">${data.message}</td></tr>`;
            }
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Connection error</td></tr>`;
        }
    }

    let filteredData = []; // Store for CSV export

    function applyFilters() {
        const query = (document.getElementById('searchInput').value || '').toLowerCase();
        const typeFilter = document.getElementById('typeFilter').value;
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;

        filteredData = allData.filter(item => {
            // 1. Status Filter
            if (currentStatus !== 'all') {
                if (currentStatus === 'active') {
                    if (item.payment_status === 'paid' || item.payment_status === 'voided') return false;
                } else if (currentStatus === 'overdue') {
                    if (item.status_label !== 'overdue') return false;
                } else if (currentStatus === 'paid') {
                    if (item.payment_status !== 'paid') return false;
                } else if (currentStatus === 'voided') {
                    if (item.payment_status !== 'voided') return false;
                }
            }

            // 2. Type Filter
            if (typeFilter !== 'all' && item.fee_type !== typeFilter) return false;

            // 3. Date Filter (using created_at)
            if (dateFrom && item.created_at < dateFrom + ' 00:00:00') return false;
            if (dateTo && item.created_at > dateTo + ' 23:59:59') return false;

            // 4. Search Query
            if (query) {
                const str = `${item.display_name} ${item.fee_ref} ${item.sale_ref || ''} ${item.fee_type}`.toLowerCase();
                if (!str.includes(query)) return false;
            }

            return true;
        });

        renderTable(filteredData);
    }

    function exportFilteredCSV() {
        if (filteredData.length === 0) {
            EllaToast.error('No data to export');
            return;
        }

        let csv = 'Fee Ref,Buyer/Customer,Type,Due Date,Total Fee,Balance,Status\n';
        filteredData.forEach(item => {
            const safeName = (item.display_name || '').replace(/"/g, '""');
            const safeType = item.fee_type.toUpperCase();
            const safeStatus = item.status_label.toUpperCase();
            csv += `"${item.fee_ref}","${safeName}","${safeType}","${item.due_date || ''}",${item.amount},${item.balance},"${safeStatus}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        a.setAttribute('download', `service_fees_export_${new Date().toISOString().slice(0, 10)}.csv`);
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function renderTable(data) {
        const tbody = document.querySelector('#feesTable tbody');
        tbody.innerHTML = '';
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No records found.</td></tr>';
            return;
        }

        data.forEach((item, idx) => {
            const badge = getStatusBadge(item.status_label);
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td data-label="Ref"><span class="badge bg-light text-dark border font-monospace">${item.fee_ref}</span></td>
                <td data-label="Buyer">
                    <div class="fw-bold text-primary">
                        ${!item.buyer_id || item.buyer_id == 0 ? '<i class="fas fa-walking me-1 text-secondary" title="Walk-in Customer"></i>' : ''}
                        ${escapeHtml(item.display_name)}
                    </div>
                    ${item.shop_name ? `<small class="text-secondary"><i class="fas fa-store me-1"></i>${escapeHtml(item.shop_name)}</small>` : ''}
                </td>
                <td data-label="Type"><span class="badge bg-secondary">${item.fee_type.toUpperCase()}</span></td>
                <td data-label="Due Date">
                    <input type="date" class="form-control form-control-sm border-secondary border-opacity-25" 
                        value="${item.due_date || ''}" onchange="updateDueDate(${item.fee_id}, this.value)" style="width: 130px;">
                </td>
                <td data-label="Total" class="amount-cell">₱${formatMoney(item.amount)}</td>
                <td data-label="Balance" class="amount-cell">
                    ${item.payment_status === 'voided' ?
                    '<span class="text-muted opacity-50">₱0.00</span>' :
                    `<span class="balance-cell">₱${formatMoney(item.balance)}</span>`}
                </td>
                <td data-label="Status">${badge}</td>
                <td class="text-end">
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-sm ${item.payment_status === 'paid' || item.payment_status === 'voided' ? 'btn-outline-secondary' : 'btn-primary'}" 
                                onclick="openPaymentModal(${item.fee_id})" 
                                title="${item.payment_status === 'paid' ? 'View Details' : (item.payment_status === 'voided' ? 'View Voided' : 'Settle Payment')}"
                                style="border-radius: 8px;">
                            <i class="fas ${item.payment_status === 'paid' || item.payment_status === 'voided' ? 'fa-eye' : 'fa-wallet'}"></i> 
                            ${item.payment_status === 'paid' || item.payment_status === 'voided' ? 'View' : 'Settle'}
                        </button>
                        
                        ${item.payment_status !== 'voided' && item.payment_status !== 'paid' ? `
                        <button class="btn btn-sm btn-warning text-dark" 
                                onclick="editAmount(${item.fee_id}, ${item.amount})" 
                                title="Edit Amount"
                                style="border-radius: 8px;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" 
                                onclick="voidFee(${item.fee_id})" 
                                title="Void Record"
                                style="border-radius: 8px;">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        ` : ''}
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    async function updateDueDate(id, date) {
        if (!date) return;
        try {
            const res = await fetch(`${BASE_URL}api/service_fees/service_fees.php`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_due_date', fee_id: id, due_date: date })
            });
            const data = await res.json();
            if (data.status === 'success') {
                EllaToast.success('Due date updated');
                loadData();
            } else EllaToast.error(data.message);
        } catch (e) { EllaToast.error('Error updating date'); }
    }

    function updateStats(data) {
        document.getElementById('statTotal').innerText = data.length;
        let pending = 0, overdue = 0;
        data.forEach(d => {
            if (d.payment_status !== 'paid' && d.payment_status !== 'voided') {
                pending += parseFloat(d.balance);
                if (d.status_label === 'overdue') overdue++;
            }
        });
        document.getElementById('statPending').innerText = '₱' + formatMoney(pending);
        document.getElementById('statOverdue').innerText = overdue;
    }

    function getStatusBadge(label) {
        let cls = 'status-pending', icon = 'fa-clock';
        if (label === 'overdue') { cls = 'status-overdue'; icon = 'fa-circle-exclamation'; }
        else if (label === 'due_today') { cls = 'status-due-today'; icon = 'fa-calendar-day'; }
        else if (label === 'paid') { cls = 'status-paid'; icon = 'fa-check-circle'; }
        else if (label === 'partial') { cls = 'status-partial'; icon = 'fa-hourglass-half'; }
        else if (label === 'voided') { cls = 'status-voided'; icon = 'fa-ban'; }
        return `<span class="status-badge ${cls}"><i class="fas ${icon}"></i> ${label.replace('_', ' ').toUpperCase()}</span>`;
    }

    async function voidFee(id) {
        if (!confirm('Are you sure you want to void this fee record? This cannot be undone and will only work if no payments have been made.')) return;
        try {
            const res = await fetch(`${BASE_URL}api/service_fees/service_fees.php`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'void', fee_id: id })
            });
            const data = await res.json();
            if (data.status === 'success') {
                EllaToast.success('Fee voided');
                loadData();
            } else EllaToast.error(data.message);
        } catch (e) { EllaToast.error('Network error'); }
    }

    async function editAmount(id, current) {
        const newAmount = prompt('Enter new fee amount:', current);
        if (newAmount === null || newAmount === '' || isNaN(newAmount) || parseFloat(newAmount) <= 0) return;

        try {
            const res = await fetch(`${BASE_URL}api/service_fees/service_fees.php`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'edit_amount', fee_id: id, amount: parseFloat(newAmount) })
            });
            const data = await res.json();
            if (data.status === 'success') {
                EllaToast.success('Amount updated');
                loadData();
            } else EllaToast.error(data.message);
        } catch (e) { EllaToast.error('Network error'); }
    }

    // Search Buyer Logic
    let searchTimeout;
    const searchInput = document.getElementById('searchBuyerInput');
    const searchResults = document.getElementById('buyerSearchResults');

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        const val = this.value.trim();
        const isWalkin = document.getElementById('typeWalkin').classList.contains('active');

        if (isWalkin) {
            document.getElementById('walkinName').value = val;
            document.getElementById('selectedBuyerId').value = '';
            searchResults.style.display = 'none';
            return;
        }

        document.getElementById('selectedBuyerId').value = '';
        document.getElementById('walkinName').value = val;

        if (val.length < 2) { searchResults.style.display = 'none'; return; }

        searchTimeout = setTimeout(async () => {
            try {
                const res = await fetch(`${BASE_URL}api/pos/search_buyer.php?q=${encodeURIComponent(val)}`);
                const data = await res.json();
                if (data.success && data.data.length > 0) {
                    searchResults.innerHTML = data.data.map(b => `
                        <div class="search-result-item" onclick="selectBuyer(${b.buyer_id}, '${escapeHtml(b.buyer_name)}', '${escapeHtml(b.shop_name || '')}')">
                            <div class="fw-bold">${escapeHtml(b.buyer_name)}</div>
                            ${b.shop_name ? `<small class="text-secondary">${escapeHtml(b.shop_name)}</small>` : ''}
                        </div>
                    `).join('');
                    searchResults.style.display = 'block';
                } else {
                    searchResults.innerHTML = `<div class="p-3 text-muted small">No registered buyers found. Will save as walk-in: "${escapeHtml(val)}"</div>`;
                    searchResults.style.display = 'block';
                }
            } catch (e) { }
        }, 300);
    });

    function selectBuyer(id, name, shop) {
        document.getElementById('selectedBuyerId').value = id;
        document.getElementById('walkinName').value = '';
        searchInput.value = name + (shop ? ` (${shop})` : '');
        searchResults.style.display = 'none';
    }

    document.addEventListener('click', e => { if (!e.target.closest('#searchBuyerInput')) searchResults.style.display = 'none'; });

    function openCreateModal() {
        document.getElementById('createFeeForm').reset();
        setCustomerType('existing');
        createModal.show();
    }

    document.getElementById('createFeeForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnCreateFee');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

        const fd = new FormData(this);
        const data = Object.fromEntries(fd.entries());
        data.action = 'create';

        try {
            const res = await fetch(`${BASE_URL}api/service_fees/service_fees.php`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.status === 'success') {
                EllaToast.success('Service Fee created');
                createModal.hide();
                loadData();
            } else EllaToast.error(result.message);
        } catch (e) { EllaToast.error('Network error'); }
        btn.disabled = false; btn.innerHTML = 'Create Fee Record';
    });

    // Payment Logic
    async function openPaymentModal(id) {
        currentFeeId = id;
        document.getElementById('payFeeId').value = id;
        document.getElementById('addPaymentForm').reset();
        document.getElementById('payImagesPreview').innerHTML = '';

        const fee = allData.find(f => f.fee_id == id);
        if (!fee) return;

        document.getElementById('lblBuyerName').innerText = fee.display_name;
        document.getElementById('lblFeeRef').innerText = fee.fee_ref;
        document.getElementById('lblTotal').innerText = formatMoney(fee.amount);
        document.getElementById('lblPaid').innerText = formatMoney(fee.paid_amount);
        document.getElementById('lblBalance').innerText = formatMoney(fee.balance);
        document.getElementById('lblDescription').innerText = fee.description || fee.fee_type.toUpperCase();

        document.getElementById('payAmount').value = parseFloat(fee.balance).toFixed(2);
        document.getElementById('payAmount').max = parseFloat(fee.balance);

        // Load History
        const histContainer = document.getElementById('historyContainer');
        histContainer.innerHTML = '<div class="text-center py-4"><div class="spinner-border"></div></div>';
        try {
            const res = await fetch(`${BASE_URL}api/service_fees/service_fees.php?fee_id=${id}`);
            const hdata = await res.json();
            if (hdata.status === 'success' && hdata.data.length > 0) {
                histContainer.innerHTML = hdata.data.map(h => `
                    <div class="history-item">
                        <div class="history-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm"><i class="fas fa-check fa-xs"></i></div>
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-bold text-success fs-5">₱${formatMoney(h.amount)}</span>
                                <span class="badge bg-secondary">${h.payment_method.toUpperCase()}</span>
                            </div>
                            <div class="small text-secondary mb-2">
                                <i class="fas fa-user me-1"></i> ${escapeHtml(h.collector_name)} | ${new Date(h.paid_at).toLocaleString()}
                                ${h.reference_no ? `<br><i class="fas fa-hashtag me-1"></i> Ref: ${escapeHtml(h.reference_no)}` : ''}
                            </div>
                            ${h.notes ? `<div class="small border-start border-3 border-primary ps-2 text-muted">"${escapeHtml(h.notes)}"</div>` : ''}
                        </div>
                    </div>
                `).join('');
            } else {
                histContainer.innerHTML = '<div class="text-center py-4 text-muted">No payments recorded yet.</div>';
            }
        } catch (e) { histContainer.innerHTML = '<div class="text-danger">Failed to load history</div>'; }

        // Hide payment tab if voided or fully paid
        const payTabBtn = document.querySelector('[data-bs-target="#tab-pay"]').parentElement;
        if (fee.payment_status === 'voided' || fee.payment_status === 'paid') {
            payTabBtn.style.display = 'none';
            bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#tab-history"]')).show();
        } else {
            payTabBtn.style.display = 'block';
            bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#tab-pay"]')).show();
        }

        paymentModal.show();
    }

    document.getElementById('payImages').addEventListener('change', function () {
        const p = document.getElementById('payImagesPreview'); p.innerHTML = '';
        Array.from(this.files).forEach(f => {
            const r = new FileReader();
            r.onload = e => { p.innerHTML += `<img src="${e.target.result}" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:2px solid #ddd;">`; };
            r.readAsDataURL(f);
        });
    });

    document.getElementById('addPaymentForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitPayment');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        try {
            const payload = {
                fee_id: document.getElementById('payFeeId').value,
                amount: document.getElementById('payAmount').value,
                payment_method: document.getElementById('payMethod').value,
                reference_no: document.getElementById('payRef').value,
                notes: document.getElementById('payNotes').value
            };

            const res = await fetch(`${BASE_URL}api/service_fees/service_fees.php`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await res.json();

            if (result.status === 'success') {
                const files = document.getElementById('payImages').files;
                if (files.length > 0) {
                    const fd = new FormData();
                    fd.append('fee_id', payload.fee_id);
                    fd.append('history_id', result.history_id);
                    Array.from(files).forEach(f => fd.append('images[]', f));
                    await fetch(`${BASE_URL}api/service_fees/upload_proof.php`, { method: 'POST', body: fd });
                }
                EllaToast.success('Payment recorded');
                paymentModal.hide();
                loadData();
            } else EllaToast.error(result.message);
        } catch (e) { EllaToast.error('Network error'); }

        btn.disabled = false; btn.innerHTML = 'Confirm Payment';
    });

    function formatMoney(num) { return parseFloat(num).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

    document.addEventListener('DOMContentLoaded', loadData);
</script>

<?php require_once '../../includes/footer.php'; ?>