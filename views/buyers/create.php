<?php
// views/buyers/create.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    denyAccess("You do not have permission to manage buyers.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark fw-bold">✨ Add New Buyer</h4>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left"></i> Cancel
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-bold py-3">
            Customer Information
        </div>
        <div class="card-body p-4">

            <form action="../../api/buyers/save_buyer.php" method="POST">

                <div class="row g-4">

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Buyer / Customer Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="buyer_name" class="form-control" placeholder="e.g. Juan Dela Cruz"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Shop / Business Name</label>
                            <input type="text" name="shop_name" class="form-control"
                                placeholder="e.g. JDC Motorparts (Optional)">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Number</label>
                                <input type="text" name="contact_number" class="form-control" placeholder="0912...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="name@email.com">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Price Tier (POS Setting)</label>
                            <select name="price_tier" class="form-select">
                                <option value="retail">Retail (Standard SRP)</option>
                                <option value="wholesale">Wholesale Price</option>
                                <option value="dealer">Dealer Price</option>
                            </select>
                            <div class="form-text small text-muted">
                                <i class="fa-solid fa-circle-info"></i> This determines the automatic price applied at
                                checkout.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Complete Address</label>
                            <textarea name="address" class="form-control" rows="3"
                                placeholder="House No, Street, City..."></textarea>
                        </div>

                        <div class="p-3 border rounded bg-white mb-3 shadow-sm">
                            <h6 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-credit-card me-2"></i>Credit
                                Account Settings</h6>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">Maximum Credit Limit (₱)</label>
                                <input type="number" name="credit_limit" class="form-control" step="0.01"
                                    placeholder="e.g. 5000.00 (Leave blank for no limit)">
                            </div>
                            <div class="mb-0">
                                <label class="form-label small fw-bold text-muted">Internal Credit Notes</label>
                                <textarea name="credit_notes" class="form-control" rows="2"
                                    placeholder="Notes about this buyer's credit worthiness..."></textarea>
                            </div>
                        </div>

                        <div class="p-3 border rounded bg-light">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_walkin" value="1"
                                    id="walkinCheck">
                                <label class="form-check-label fw-bold" for="walkinCheck">Mark as "Walk-in"
                                    Customer</label>
                            </div>
                            <div class="small text-muted mt-1 ms-0 ms-md-4">Walk-in customers are usually one-time
                                buyers with no credit line.</div>
                        </div>

                    </div>

                    <div class="col-12 text-end border-top pt-3">
                        <button type="submit" class="btn btn-success btn-lg px-5 shadow-sm">
                            <i class="fa-solid fa-save"></i> Save Customer
                        </button>
                    </div>

                </div>
            </form>

        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>