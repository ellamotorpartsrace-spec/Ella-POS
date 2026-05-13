<?php
// views/suppliers/index.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings') && !in_array($_SESSION['role'], ['manager', 'stockman', 'cashier'])) {
    denyAccess("You do not have permission to manage suppliers.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Fetch all suppliers
$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* Supplier Page Styles */
    .supplier-page {
        padding: 1.5rem;
        max-width: 1600px;
        margin: 0 auto;
    }

    .supplier-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .supplier-header-info h4 {
        margin: 0;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .supplier-header-info p {
        margin: 0.25rem 0 0 0;
        font-size: 0.875rem;
    }

    .supplier-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    /* Search Box */
    .supplier-search-box {
        position: relative;
        flex: 1;
        min-width: 250px;
        max-width: 400px;
    }

    .supplier-search-box input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.75rem;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--bg-surface);
        color: var(--text-primary);
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }

    .supplier-search-box input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
    }

    .supplier-search-box i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
    }

    /* Add Button */
    .btn-add-supplier {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
        color: #fff;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.25s ease;
        box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
    }

    .btn-add-supplier:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
    }

    /* Stats Bar */
    .supplier-stats {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .stat-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .stat-badge strong {
        color: var(--primary-color);
        font-weight: 700;
    }

    /* Supplier Cards Grid */
    .supplier-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1.25rem;
    }

    /* Individual Supplier Card */
    .supplier-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 1.25rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .supplier-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--primary-hover));
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .supplier-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--card-shadow);
        border-color: var(--primary-color);
    }

    [data-theme="light"] .supplier-card:hover {
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    }

    .supplier-card:hover::before {
        opacity: 1;
    }

    .supplier-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .supplier-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .supplier-id {
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-family: monospace;
    }

    .supplier-avatar {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.25rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .supplier-card-body {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .supplier-info-row {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        font-size: 0.9rem;
    }

    .supplier-info-row i {
        width: 20px;
        color: var(--primary-color);
        margin-top: 2px;
        text-align: center;
    }

    .supplier-info-row span {
        color: var(--text-secondary);
        line-height: 1.4;
    }

    .supplier-info-row a {
        color: var(--primary-color);
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .supplier-info-row a:hover {
        color: var(--primary-hover);
        text-decoration: underline;
    }

    .supplier-card-footer {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
    }

    .btn-card-action {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.6rem 0.75rem;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        text-decoration: none;
    }

    .btn-view {
        background: rgba(139, 92, 246, 0.1);
        color: var(--primary-color);
    }

    .btn-view:hover {
        background: rgba(139, 92, 246, 0.2);
        color: var(--primary-color);
    }

    .btn-edit {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }

    .btn-edit:hover {
        background: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
    }

    /* Empty State */
    .supplier-empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 4rem 2rem;
        background: var(--card-bg);
        border: 2px dashed var(--border-color);
        border-radius: 16px;
    }

    .supplier-empty-state i {
        font-size: 3rem;
        color: var(--text-secondary);
        margin-bottom: 1rem;
    }

    .supplier-empty-state h5 {
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .supplier-empty-state p {
        color: var(--text-secondary);
        margin-bottom: 1.5rem;
    }

    /* Modal Enhancements */
    .modal-content {
        background: var(--card-bg) !important;
        border: 1px solid var(--border-color);
    }

    .modal-header {
        border-bottom: 1px solid var(--border-color);
    }

    .modal-footer {
        border-top: 1px solid var(--border-color);
    }

    .modal .form-label {
        color: var(--text-secondary);
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .supplier-page {
            padding: 1rem;
        }

        .supplier-header {
            flex-direction: column;
            align-items: stretch;
        }

        .supplier-search-box {
            max-width: 100%;
            order: 2;
        }

        .supplier-actions {
            order: 1;
            justify-content: space-between;
        }

        .btn-add-supplier {
            flex: 1;
            justify-content: center;
        }

        .supplier-grid {
            grid-template-columns: 1fr;
        }

        .supplier-card {
            padding: 1rem;
        }

        .supplier-stats {
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .supplier-header-info h4 {
            font-size: 1.1rem;
        }

        .supplier-name {
            font-size: 1rem;
        }

        .supplier-avatar {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }
    }
</style>

<div class="supplier-page">
    <!-- Header Section -->
    <div class="supplier-header">
        <div class="supplier-header-info">
            <h4>
                <i class="fa-solid fa-truck-field text-primary"></i>
                Supplier Management
            </h4>
            <p class="text-muted">Manage motor parts vendors and bulk procurement</p>
        </div>
        <div class="supplier-actions">
            <div class="supplier-search-box">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="supplierSearch" placeholder="Search suppliers..." autocomplete="off">
            </div>
            <button class="btn-add-supplier" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                <i class="fa-solid fa-plus"></i>
                <span>Add Supplier</span>
            </button>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="supplier-stats">
        <div class="stat-badge">
            <i class="fa-solid fa-building"></i>
            <span>Total: <strong><?= count($suppliers) ?></strong> Suppliers</span>
        </div>
    </div>

    <!-- Supplier Cards Grid -->
    <div class="supplier-grid" id="supplierGrid">
        <?php if (count($suppliers) > 0): ?>
            <?php foreach ($suppliers as $s): ?>
                <div class="supplier-card" data-name="<?= strtolower(htmlspecialchars($s['supplier_name'])) ?>"
                    data-contact="<?= strtolower(htmlspecialchars($s['contact_person'] ?? '')) ?>">
                    <div class="supplier-card-header">
                        <div>
                            <h5 class="supplier-name"><?= htmlspecialchars($s['supplier_name']) ?></h5>
                            <span class="supplier-id">ID: #<?= $s['supplier_id'] ?></span>
                        </div>
                        <div class="supplier-avatar">
                            <?= strtoupper(substr($s['supplier_name'], 0, 1)) ?>
                        </div>
                    </div>
                    <div class="supplier-card-body">
                        <div class="supplier-info-row">
                            <i class="fa-solid fa-user"></i>
                            <span><?= htmlspecialchars($s['contact_person'] ?: 'No contact person') ?></span>
                        </div>
                        <div class="supplier-info-row">
                            <i class="fa-solid fa-phone"></i>
                            <span>
                                <?php if (!empty($s['phone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($s['phone']) ?>"><?= htmlspecialchars($s['phone']) ?></a>
                                <?php else: ?>
                                    No phone number
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="supplier-info-row">
                            <i class="fa-solid fa-envelope"></i>
                            <span>
                                <?php if (!empty($s['email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($s['email']) ?>"><?= htmlspecialchars($s['email']) ?></a>
                                <?php else: ?>
                                    No email address
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="supplier-info-row">
                            <i class="fa-solid fa-location-dot"></i>
                            <span><?= htmlspecialchars($s['address'] ?: 'No address saved') ?></span>
                        </div>
                    </div>
                    <div class="supplier-card-footer">
                        <button class="btn-card-action btn-view" onclick="viewSupplier(<?= $s['supplier_id'] ?>)">
                            <i class="fa-solid fa-eye"></i> View Details
                        </button>
                        <a href="edit.php?id=<?= $s['supplier_id'] ?>" class="btn-card-action btn-edit">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="supplier-empty-state">
                <i class="fa-solid fa-truck-ramp-box"></i>
                <h5>No Suppliers Found</h5>
                <p>Get started by adding your first supplier</p>
                <button class="btn-add-supplier" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                    <i class="fa-solid fa-plus"></i> Add Supplier
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-plus-circle me-2"></i>Add New Supplier
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="../../api/suppliers/save_supplier.php" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Company Name *</label>
                        <input type="text" name="supplier_name" class="form-control" required
                            placeholder="Enter company name">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" placeholder="Primary contact">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="e.g. 09XX-XXX-XXXX">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="supplier@example.com">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3"
                            placeholder="Complete address"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="fa-solid fa-check me-1"></i> Save Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Supplier Modal -->
<div class="modal fade" id="viewSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-address-card me-2"></i>Supplier Overview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="supplier-details-loader" class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2 text-muted">Retrieving profile...</p>
                </div>

                <div id="supplier-details-content" class="d-none">
                    <div class="p-4 bg-light border-bottom">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-uppercase text-muted fw-bold small">Company Name</small>
                                <h4 class="fw-bold text-primary mb-3" id="v-name"></h4>

                                <small class="text-uppercase text-muted fw-bold small">Contact Person</small>
                                <p class="text-dark mb-0" id="v-contact"></p>
                            </div>
                            <div class="col-md-6 border-start ps-4">
                                <div class="mb-2">
                                    <small class="text-uppercase text-muted fw-bold small d-block">Phone & Email</small>
                                    <span id="v-phone" class="d-block"></span>
                                    <span id="v-email" class="d-block text-primary"></span>
                                </div>
                                <div>
                                    <small class="text-uppercase text-muted fw-bold small d-block">Address</small>
                                    <p id="v-address" class="small mb-0"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0 text-uppercase small"><i
                                    class="fa-solid fa-boxes-stacked me-2"></i>Linked Products</h6>
                            <span class="badge bg-secondary" id="v-product-count">0 Items</span>
                        </div>
                        <div class="table-responsive" style="max-height: 350px;">
                            <table class="table table-hover table-sm border">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th class="ps-3">Item Description</th>
                                        <th>SKU/Part #</th>
                                        <th class="text-end pe-3">Current Cost</th>
                                    </tr>
                                </thead>
                                <tbody id="v-products-body">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <a href="" id="v-link-page" class="btn btn-outline-primary shadow-sm">
                    <i class="fa-solid fa-link me-2"></i>Manage Linked Items
                </a>
                <a href="" id="v-edit-link" class="btn btn-primary px-4 shadow-sm">
                    <i class="fa-solid fa-user-pen me-2"></i>Edit Profile
                </a>
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Search functionality
    document.getElementById('supplierSearch').addEventListener('input', function (e) {
        const searchTerm = e.target.value.toLowerCase().trim();
        const cards = document.querySelectorAll('.supplier-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const name = card.dataset.name || '';
            const contact = card.dataset.contact || '';
            const matches = name.includes(searchTerm) || contact.includes(searchTerm);

            card.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });

        // Show empty state if no results
        const grid = document.getElementById('supplierGrid');
        let emptyMsg = grid.querySelector('.search-empty-state');

        if (visibleCount === 0 && searchTerm.length > 0) {
            if (!emptyMsg) {
                emptyMsg = document.createElement('div');
                emptyMsg.className = 'supplier-empty-state search-empty-state';
                emptyMsg.innerHTML = `
                <i class="fa-solid fa-search"></i>
                <h5>No Results Found</h5>
                <p>No suppliers match "${searchTerm}"</p>
            `;
                grid.appendChild(emptyMsg);
            }
        } else if (emptyMsg) {
            emptyMsg.remove();
        }
    });

    function viewSupplier(id) {
        const modalEl = document.getElementById('viewSupplierModal');
        const modal = new bootstrap.Modal(modalEl);
        const loader = document.getElementById('supplier-details-loader');
        const content = document.getElementById('supplier-details-content');

        loader.classList.remove('d-none');
        content.classList.add('d-none');
        modal.show();

        fetch(`../../api/suppliers/get_supplier_details.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Map Supplier Info
                    document.getElementById('v-name').innerText = data.supplier.supplier_name;
                    document.getElementById('v-contact').innerText = data.supplier.contact_person || 'No primary contact';
                    document.getElementById('v-phone').innerText = data.supplier.phone || 'None';
                    document.getElementById('v-email').innerText = data.supplier.email || 'None';
                    document.getElementById('v-address').innerText = data.supplier.address || 'No address registered';
                    document.getElementById('v-link-page').href = `mass_link.php?id=${id}`;
                    document.getElementById('v-edit-link').href = `edit.php?id=${id}`;

                    // Map Linked Products
                    const tbody = document.getElementById('v-products-body');
                    tbody.innerHTML = '';
                    document.getElementById('v-product-count').innerText = `${data.products.length} Items`;

                    if (data.products && data.products.length > 0) {
                        data.products.forEach(p => {
                            tbody.innerHTML += `
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">${p.product_name}</div>
                                    <div class="small text-muted">${p.variation_name}</div>
                                </td>
                                <td class="align-middle small font-monospace">${p.sku || '---'}</td>
                                <td class="text-end pe-3 align-middle fw-bold text-success">
                                    ₱${parseFloat(p.price_capital).toFixed(2)}
                                </td>
                            </tr>`;
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">This supplier is not linked to any products yet.</td></tr>';
                    }

                    loader.classList.add('d-none');
                    content.classList.remove('d-none');
                } else {
                    EllaToast.error("Error: " + data.message);
                    modal.hide();
                }
            })
            .catch(err => {
                console.error(err);
                loader.innerHTML = '<div class="text-danger p-5">Connection error. Please try again.</div>';
            });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>