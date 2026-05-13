<?php
// views/inventory/stockin_records.php - Stock-In Records by Supplier
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    denyAccess("You do not have permission to view stock-in records.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get suppliers for dropdown
$suppliers = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name")->fetchAll();

$selected_supplier = $_GET['supplier_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
?>

<style>
    .supplier-card {
        transition: all 0.3s ease;
        cursor: pointer;
        border: 2px solid transparent;
    }

    .supplier-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
    }

    .supplier-card.active {
        border-color: var(--bs-primary);
        background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 100%);
    }

    .record-row {
        transition: all 0.2s ease;
    }

    .record-row:hover {
        background: #f8f9fa;
    }

    .stats-card {
        border-radius: 12px;
        transition: transform 0.2s;
    }

    .stats-card:hover {
        transform: scale(1.02);
    }

    .reference-link {
        text-decoration: none;
        font-family: monospace;
        font-size: 0.85rem;
    }

    .reference-link:hover {
        text-decoration: underline;
    }

    .date-group-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        font-weight: 600;
        font-size: 0.85rem;
    }

    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        border-radius: inherit;
    }

    .empty-state {
        padding: 3rem 1rem;
    }

    .empty-state i {
        font-size: 3rem;
        opacity: 0.2;
        margin-bottom: 1rem;
    }

    /* Mobile card view */
    @media (max-width: 991.98px) {
        .mobile-cards {
            display: block !important;
        }

        .desktop-table {
            display: none !important;
        }
    }

    @media (min-width: 992px) {
        .mobile-cards {
            display: none !important;
        }

        .desktop-table {
            display: block !important;
        }
    }

    .mobile-record-card {
        border-left: 4px solid var(--bs-success);
        transition: all 0.2s ease;
    }

    .mobile-record-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
</style>

<div class="container-fluid p-3 p-lg-4">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h4 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-file-invoice text-primary me-2"></i>Stock-In Records
            </h4>
            <p class="text-muted mb-0 small">View stock-in receipts and records by supplier</p>
        </div>
        <div class="d-flex gap-2">
            <a href="movements.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-right-arrow-left me-1"></i>Stock Movements
            </a>
            <a href="restock.php" class="btn btn-success btn-sm">
                <i class="fa-solid fa-plus me-1"></i>New Stock In
            </a>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="filter-form" class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">SUPPLIER</label>
                    <select name="supplier_id" id="supplier-select" class="form-select" required>
                        <option value="">-- Select Supplier --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['supplier_id'] ?>" <?= $selected_supplier == $s['supplier_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['supplier_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">FROM</label>
                    <input type="date" name="date_from" id="date-from" class="form-control" value="<?= $date_from ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">TO</label>
                    <input type="date" name="date_to" id="date-to" class="form-control" value="<?= $date_to ?>">
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fa-solid fa-filter me-1"></i>Load
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportCSV()" title="Export CSV"
                        id="export-btn" disabled>
                        <i class="fa-solid fa-file-csv"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()" title="Reset">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats Cards (hidden until data loads) -->
    <div class="row g-3 mb-4 d-none" id="stats-row">
        <div class="col-6 col-lg-3">
            <div class="card stats-card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3">
                            <i class="fa-solid fa-arrow-down text-success fa-lg"></i>
                        </div>
                        <div>
                            <div class="h4 fw-bold mb-0 text-success" id="stat-records">0</div>
                            <small class="text-muted">Total Records</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stats-card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                            <i class="fa-solid fa-cubes text-primary fa-lg"></i>
                        </div>
                        <div>
                            <div class="h4 fw-bold mb-0 text-primary" id="stat-quantity">0</div>
                            <small class="text-muted">Total Qty</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stats-card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-2 me-3">
                            <i class="fa-solid fa-peso-sign text-warning fa-lg"></i>
                        </div>
                        <div>
                            <div class="h5 fw-bold mb-0 text-warning" id="stat-cost">₱0</div>
                            <small class="text-muted">Total Cost</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stats-card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-info bg-opacity-10 p-2 me-3">
                            <i class="fa-solid fa-file-lines text-info fa-lg"></i>
                        </div>
                        <div>
                            <div class="h4 fw-bold mb-0 text-info" id="stat-references">0</div>
                            <small class="text-muted">References</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Records Card -->
    <div class="card shadow-sm border-0 position-relative" id="records-card">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">
                <i class="fa-solid fa-list text-primary me-2"></i>
                <span id="records-title">Stock-In Records</span>
            </h6>
            <span class="badge bg-secondary" id="records-count">Select a supplier</span>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay d-none" id="loading-overlay">
            <div class="text-center">
                <div class="spinner-border text-primary mb-2" role="status"></div>
                <div class="text-muted small">Loading records...</div>
            </div>
        </div>

        <!-- Desktop Table View -->
        <div class="card-body p-0 desktop-table" style="display: none;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Date & Time</th>
                            <th>Product</th>
                            <th class="text-end">Capital</th>
                            <th class="text-center">Qty Added</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Stock Level</th>
                            <th>Reference</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody id="records-tbody">
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="fa-solid fa-truck-ramp-box d-block"></i>
                                    <h6 class="text-muted">Select a Supplier</h6>
                                    <p class="small text-muted mb-0">Choose a supplier from the dropdown above to view
                                        their stock-in records</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile Card View -->
        <div class="card-body p-3 mobile-cards" style="display: none;">
            <div id="records-mobile">
                <div class="empty-state text-center">
                    <i class="fa-solid fa-truck-ramp-box d-block"></i>
                    <h6 class="text-muted">Select a Supplier</h6>
                    <p class="small text-muted mb-0">Choose a supplier from the dropdown above to view their stock-in
                        records</p>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="card-footer bg-white py-3 d-none" id="pagination-footer">
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted" id="pagination-info">Showing 0 records</small>
                <div class="d-flex gap-2" id="pagination-btns"></div>
            </div>
        </div>
    </div>

</div>

<script>
    const BASE_URL = '<?= BASE_URL ?>';
    let currentPage = 1;

    document.addEventListener('DOMContentLoaded', function () {
        updateView();
        window.addEventListener('resize', updateView);

        // Auto-load if supplier is pre-selected
        const supplierId = document.getElementById('supplier-select').value;
        if (supplierId) {
            loadRecords();
        }

        // Form submit
        document.getElementById('filter-form').addEventListener('submit', function (e) {
            e.preventDefault();
            currentPage = 1;
            loadRecords();
        });

        // Auto-load on supplier change
        document.getElementById('supplier-select').addEventListener('change', function () {
            if (this.value) {
                currentPage = 1;
                loadRecords();
            }
        });
    });

    function updateView() {
        const isMobile = window.innerWidth < 992;
        document.querySelectorAll('.mobile-cards').forEach(el => {
            el.style.display = isMobile ? 'block' : 'none';
        });
        document.querySelectorAll('.desktop-table').forEach(el => {
            el.style.display = isMobile ? 'none' : 'block';
        });
    }

    async function loadRecords(page = 1) {
        const supplierId = document.getElementById('supplier-select').value;
        if (!supplierId) {
            EllaToast.warning('Please select a supplier');
            return;
        }

        currentPage = page;
        const dateFrom = document.getElementById('date-from').value;
        const dateTo = document.getElementById('date-to').value;

        // Show loading
        document.getElementById('loading-overlay').classList.remove('d-none');
        document.getElementById('export-btn').disabled = true;

        try {
            const params = new URLSearchParams({
                supplier_id: supplierId,
                date_from: dateFrom,
                date_to: dateTo,
                page: page
            });

            const res = await fetch(`../../api/inventory/get_stockin_records.php?${params}`);
            const data = await res.json();

            if (data.success) {
                renderStats(data.stats);
                renderDesktopTable(data.records);
                renderMobileCards(data.records);
                renderPagination(data.pagination);

                document.getElementById('records-title').textContent =
                    `Stock-In Records — ${escapeHtml(data.supplier_name)}`;
                document.getElementById('records-count').textContent =
                    `${data.pagination.total} records`;

                document.getElementById('export-btn').disabled = data.records.length === 0;

                // Update URL without reload
                const url = new URL(window.location);
                url.searchParams.set('supplier_id', supplierId);
                if (dateFrom) url.searchParams.set('date_from', dateFrom);
                else url.searchParams.delete('date_from');
                if (dateTo) url.searchParams.set('date_to', dateTo);
                else url.searchParams.delete('date_to');
                window.history.replaceState({}, '', url);
            } else {
                EllaToast.error('Error: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            console.error('Load error:', err);
            EllaToast.success('Failed to load records');
        } finally {
            document.getElementById('loading-overlay').classList.add('d-none');
        }
    }

    function renderStats(stats) {
        document.getElementById('stats-row').classList.remove('d-none');
        document.getElementById('stat-records').textContent = parseInt(stats.total_records).toLocaleString();
        document.getElementById('stat-quantity').textContent = parseInt(stats.total_quantity).toLocaleString();
        document.getElementById('stat-cost').textContent = '₱' + parseFloat(stats.total_cost).toLocaleString(undefined, {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
        document.getElementById('stat-references').textContent = parseInt(stats.unique_references).toLocaleString();
    }

    function renderDesktopTable(records) {
        const tbody = document.getElementById('records-tbody');

        if (records.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox d-block"></i>
                            <h6 class="text-muted">No Stock-In Records Found</h6>
                            <p class="small text-muted mb-0">No records found for this supplier with the selected filters</p>
                        </div>
                    </td>
                </tr>`;
            return;
        }

        let lastDate = '';
        let html = '';

        records.forEach(row => {
            const dateStr = new Date(row.created_at).toLocaleDateString('en-US', {
                year: 'numeric', month: 'short', day: 'numeric'
            });
            const timeStr = new Date(row.created_at).toLocaleTimeString('en-US', {
                hour: 'numeric', minute: '2-digit', hour12: true
            });

            // Date group separator
            if (dateStr !== lastDate) {
                html += `
                    <tr>
                        <td colspan="8" class="date-group-header ps-4 py-2">
                            <i class="fa-solid fa-calendar-day me-1 text-primary"></i>${dateStr}
                        </td>
                    </tr>`;
                lastDate = dateStr;
            }

            const refHtml = row.reference
                ? `<div class="d-flex flex-column align-items-start">
                        <a href="reference.php?ref=${encodeURIComponent(row.reference)}&from=stockin_records" class="reference-link text-primary fw-bold">
                            ${escapeHtml(row.reference)}
                        </a>
                        ${parseInt(row.has_attachment) > 0
                    ? `<a href="javascript:void(0)" 
                                  onclick="viewAttachment('${row.reference_image}', '${escapeHtml(row.reference)}')" 
                                  class="text-primary text-decoration-none mt-1 d-block small">
                                    <i class="fa-solid fa-paperclip me-1"></i>View Receipt
                                    ${parseInt(row.has_attachment) > 1 ? `<span class="badge bg-secondary rounded-pill small ms-1" style="font-size: 0.7em;">+${parseInt(row.has_attachment) - 1}</span>` : ''}
                               </a>`
                    : `<button class="btn btn-sm btn-link p-0 text-decoration-none mt-1" onclick="openRetroUpload(${row.movement_id}, '${escapeHtml(row.reference)}')">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Add Photo
                               </button>`
                }
                   </div>`
                : `<div class="d-flex align-items-center">
                        <span class="text-muted">—</span>
                        <button class="btn btn-sm btn-link p-0 text-decoration-none ms-2" onclick="openRetroUpload(${row.movement_id}, 'No Reference')">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Add Photo
                        </button>
                   </div>`;

            html += `
                <tr class="record-row">
                    <td class="ps-4">
                        <div class="fw-bold small">${dateStr}</div>
                        <small class="text-muted">${timeStr}</small>
                    </td>
                    <td>
                        <div class="fw-bold text-dark">${escapeHtml(row.product_name)}</div>
                        <small class="text-muted">
                            ${escapeHtml(row.brand_name || '')} | ${escapeHtml(row.variation_name || '')}
                        </small>
                        ${row.sku ? `<div class="small text-muted font-monospace">${escapeHtml(row.sku)}</div>` : ''}
                    </td>
                    <td class="text-end">
                        <div class="fw-medium text-secondary">₱${parseFloat(row.price_capital).toFixed(2)}</div>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success-subtle text-success fs-6">
                            +${Math.abs(row.quantity)}
                        </span>
                    </td>
                    <td class="text-end">
                        <div class="fw-bold text-dark">₱${(Math.abs(row.quantity) * parseFloat(row.price_capital)).toFixed(2)}</div>
                    </td>
                    <td class="text-center">
                        <small class="text-muted">${row.previous_stock}</small>
                        <i class="fa-solid fa-arrow-right mx-1 text-muted" style="font-size:0.7em;"></i>
                        <span class="fw-bold">${row.new_stock}</span>
                    </td>
                    <td>
                        ${refHtml}
                    </td>
                    <td>
                        <small class="text-muted">${escapeHtml(row.created_by_name || 'System')}</small>
                    </td>
                </tr>`;
        });

        tbody.innerHTML = html;
    }

    function renderMobileCards(records) {
        const container = document.getElementById('records-mobile');

        if (records.length === 0) {
            container.innerHTML = `
                <div class="empty-state text-center">
                    <i class="fa-solid fa-inbox d-block"></i>
                    <h6 class="text-muted">No Stock-In Records Found</h6>
                    <p class="small text-muted mb-0">No records found for this supplier</p>
                </div>`;
            return;
        }

        let lastDate = '';
        let html = '<div class="d-flex flex-column gap-3">';

        records.forEach(row => {
            const dateStr = new Date(row.created_at).toLocaleDateString('en-US', {
                year: 'numeric', month: 'short', day: 'numeric'
            });
            const timeStr = new Date(row.created_at).toLocaleTimeString('en-US', {
                hour: 'numeric', minute: '2-digit', hour12: true
            });

            if (dateStr !== lastDate) {
                html += `
                    <div class="fw-bold small text-muted bg-light rounded p-2">
                        <i class="fa-solid fa-calendar-day me-1 text-primary"></i>${dateStr}
                    </div>`;
                lastDate = dateStr;
            }

            html += `
                <div class="card mobile-record-card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <div class="fw-bold text-dark">${escapeHtml(row.product_name)}</div>
                                <small class="text-muted">
                                    ${escapeHtml(row.brand_name || '')} | ${escapeHtml(row.variation_name || '')}
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success-subtle text-success fs-6 mb-1 d-block">
                                    +${Math.abs(row.quantity)}
                                </span>
                                <div class="fw-bold text-dark small">₱${(Math.abs(row.quantity) * parseFloat(row.price_capital)).toFixed(2)}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                ${row.reference
                    ? `<div class="d-flex align-items-center mb-1">
                                            <a href="reference.php?ref=${encodeURIComponent(row.reference)}&from=stockin_records" class="reference-link text-primary fw-bold">${escapeHtml(row.reference)}</a>
                                            ${parseInt(row.has_attachment) > 0
                        ? ''
                        : `<button class="btn btn-sm btn-link p-0 text-decoration-none ms-2" onclick="openRetroUpload(${row.movement_id}, '${escapeHtml(row.reference)}')">
                                                    <i class="fa-solid fa-cloud-arrow-up"></i> Add Photo
                                                   </button>`
                    }
                                       </div>`
                    : `<div class="d-flex align-items-center mb-1">
                                            <span class="text-muted small">No reference</span>
                                            <button class="btn btn-sm btn-link p-0 text-decoration-none ms-2" onclick="openRetroUpload(${row.movement_id}, 'No Reference')">
                                                <i class="fa-solid fa-cloud-arrow-up"></i> Add Photo
                                            </button>
                                       </div>`
                }
                                <small class="text-muted d-block">
                                    Cap: ₱${parseFloat(row.price_capital).toFixed(2)} | 
                                    Stock: ${row.previous_stock} → <strong>${row.new_stock}</strong>
                                </small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                ${parseInt(row.has_attachment) > 0
                    ? `<a href="javascript:void(0)" 
                                          onclick="viewAttachment('${row.reference_image}', '${escapeHtml(row.reference)}')" 
                                          class="text-primary me-2">
                                            <i class="fa-solid fa-paperclip"></i>
                                            ${parseInt(row.has_attachment) > 1 ? `<span class="badge bg-secondary rounded-pill small ms-1" style="font-size: 0.7em;">+${parseInt(row.has_attachment) - 1}</span>` : ''}
                                       </a>`
                    : ''
                }
                                <small class="text-muted">${timeStr}</small>
                            </div>
                        </div>
                    </div>
                </div>`;
        });

        html += '</div>';
        container.innerHTML = html;
    }

    function renderPagination(pagination) {
        const footer = document.getElementById('pagination-footer');
        const info = document.getElementById('pagination-info');
        const btns = document.getElementById('pagination-btns');

        if (pagination.total <= pagination.per_page) {
            footer.classList.add('d-none');
            return;
        }

        footer.classList.remove('d-none');
        const start = (pagination.current_page - 1) * pagination.per_page + 1;
        const end = Math.min(pagination.current_page * pagination.per_page, pagination.total);
        info.textContent = `Showing ${start}–${end} of ${pagination.total} records`;

        let btnsHtml = '';
        if (pagination.current_page > 1) {
            btnsHtml += `<button class="btn btn-sm btn-outline-primary" onclick="loadRecords(${pagination.current_page - 1})">
                <i class="fa-solid fa-chevron-left"></i> Prev
            </button>`;
        }

        // Page numbers
        const maxVisible = 5;
        let startPage = Math.max(1, pagination.current_page - Math.floor(maxVisible / 2));
        let endPage = Math.min(pagination.total_pages, startPage + maxVisible - 1);
        startPage = Math.max(1, endPage - maxVisible + 1);

        for (let i = startPage; i <= endPage; i++) {
            btnsHtml += `<button class="btn btn-sm ${i === pagination.current_page ? 'btn-primary' : 'btn-outline-primary'}" 
                onclick="loadRecords(${i})">${i}</button>`;
        }

        if (pagination.current_page < pagination.total_pages) {
            btnsHtml += `<button class="btn btn-sm btn-outline-primary" onclick="loadRecords(${pagination.current_page + 1})">
                Next <i class="fa-solid fa-chevron-right"></i>
            </button>`;
        }

        btns.innerHTML = btnsHtml;
    }

    function resetFilters() {
        document.getElementById('supplier-select').value = '';
        document.getElementById('date-from').value = '';
        document.getElementById('date-to').value = '';
        document.getElementById('stats-row').classList.add('d-none');
        document.getElementById('pagination-footer').classList.add('d-none');
        document.getElementById('records-title').textContent = 'Stock-In Records';
        document.getElementById('records-count').textContent = 'Select a supplier';
        document.getElementById('export-btn').disabled = true;

        document.getElementById('records-tbody').innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <div class="empty-state">
                        <i class="fa-solid fa-truck-ramp-box d-block"></i>
                        <h6 class="text-muted">Select a Supplier</h6>
                        <p class="small text-muted mb-0">Choose a supplier from the dropdown above to view their stock-in records</p>
                    </div>
                </td>
            </tr>`;
        document.getElementById('records-mobile').innerHTML = `
            <div class="empty-state text-center">
                <i class="fa-solid fa-truck-ramp-box d-block"></i>
                <h6 class="text-muted">Select a Supplier</h6>
                <p class="small text-muted mb-0">Choose a supplier from the dropdown above to view their stock-in records</p>
            </div>`;

        // Clear URL params
        window.history.replaceState({}, '', window.location.pathname);
    }

    function exportCSV() {
        const supplierId = document.getElementById('supplier-select').value;
        if (!supplierId) {
            EllaToast.warning('Please select a supplier');
            return;
        }

        const dateFrom = document.getElementById('date-from').value;
        const dateTo = document.getElementById('date-to').value;

        const params = new URLSearchParams({
            supplier_id: supplierId,
            date_from: dateFrom,
            date_to: dateTo
        });

        window.location.href = `../../api/inventory/export_stockin_records_csv.php?${params}`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function viewAttachment(imagesDataStr, ref) {
        const items = imagesDataStr.split(',');
        const container = document.getElementById('modal-carousel-inner');
        const prevBtn = document.querySelector('#attachmentCarousel .carousel-control-prev');
        const nextBtn = document.querySelector('#attachmentCarousel .carousel-control-next');
        const indicatorText = document.getElementById('carousel-indicator-text');

        container.innerHTML = '';
        document.getElementById('attachment-ref-title').textContent = ref;
        document.getElementById('total-img-count').textContent = items.length;
        document.getElementById('current-img-idx').textContent = '1';

        items.forEach((data, idx) => {
            const parts = data.split(':');
            const id = parts[0];
            const path = parts.slice(1).join(':');

            const item = document.createElement('div');
            item.className = `carousel-item ${idx === 0 ? 'active' : ''}`;
            item.innerHTML = `
                <div class="d-flex flex-column align-items-center justify-content-center p-4" style="min-height: 300px;">
                    <img src="${BASE_URL}${path}" class="img-fluid rounded shadow-sm" style="max-height: 70vh;" alt="Receipt">
                    <div class="mt-3" style="position: relative; z-index: 10;">
                        <a href="${BASE_URL}${path}" target="_blank" class="btn btn-sm btn-link text-decoration-none">
                            <i class="fa-solid fa-expand me-1"></i>View Full Size
                        </a>
                    </div>
                </div>
            `;
            // Store data for deletion
            item.dataset.id = id;
            item.dataset.path = path;
            container.appendChild(item);
        });

        if (items.length > 1) {
            prevBtn.classList.remove('d-none');
            nextBtn.classList.remove('d-none');
            indicatorText.classList.remove('d-none');
        } else {
            prevBtn.classList.add('d-none');
            nextBtn.classList.add('d-none');
            indicatorText.classList.add('d-none');
        }

        const modal = new bootstrap.Modal(document.getElementById('attachmentModal'));
        modal.show();

        // Handle index update
        const carouselEl = document.getElementById('attachmentCarousel');
        carouselEl.addEventListener('slide.bs.carousel', function (e) {
            document.getElementById('current-img-idx').textContent = e.to + 1;
        });
    }

    function deleteCurrentAttachment() {
        const activeItem = document.querySelector('#modal-carousel-inner .carousel-item.active');
        const id = activeItem ? activeItem.dataset.id : null;
        const path = activeItem ? activeItem.dataset.path : null;
        if (!path) return;

        if (!confirm('Are you sure you want to delete this specific photo? This action cannot be undone.')) return;

        const btn = document.querySelector('#attachmentModal .btn-outline-danger');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Deleting...';
        }

        const formData = new FormData();
        if (id) formData.append('id', id);
        formData.append('image_path', path);

        fetch('../../api/inventory/delete_attachment.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (typeof EllaToast !== 'undefined') EllaToast.success(data.message);
                    else alert(data.message);
                    setTimeout(() => {
                        const supplierId = document.getElementById('supplier-select').value;
                        if (supplierId) loadRecords(currentPage);
                        const modalObj = bootstrap.Modal.getInstance(document.getElementById('attachmentModal'));
                        modalObj.hide();
                    }, 1000);
                } else {
                    if (typeof EllaToast !== 'undefined') EllaToast.error('Error: ' + data.error);
                    else alert('Error: ' + data.error);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-trash me-1"></i>Delete Photo';
                    }
                }
            })
            .catch(err => {
                console.error(err);
                if (typeof EllaToast !== 'undefined') EllaToast.error('An error occurred during deletion.');
                else alert('An error occurred during deletion.');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-trash me-1"></i>Delete Photo';
                }
            });
    }

    function openRetroUpload(movementId, refLabel) {
        document.getElementById('upload-movement-id').value = movementId;
        document.getElementById('upload-ref-label').textContent = refLabel;
        document.getElementById('uploadForm').reset();
        const modal = new bootstrap.Modal(document.getElementById('uploadModal'));
        modal.show();
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Handle Retroactive Upload Form
        document.getElementById('uploadForm')?.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('btn-upload');
            const fileInput = document.getElementById('upload-file');

            if (!fileInput.files.length) {
                if (typeof EllaToast !== 'undefined') EllaToast.warning('Please select an image first.');
                else alert('Please select an image first.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Uploading...';

            const formData = new FormData(this);

            try {
                const res = await fetch('../../api/inventory/upload_retroactive_attachment.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();

                if (data.success) {
                    if (typeof EllaToast !== 'undefined') EllaToast.success(data.message);
                    else alert(data.message);

                    // Reload current records
                    const supplierId = document.getElementById('supplier-select').value;
                    if (supplierId) loadRecords(1);

                    const modalObj = bootstrap.Modal.getInstance(document.getElementById('uploadModal'));
                    modalObj.hide();
                } else {
                    if (typeof EllaToast !== 'undefined') EllaToast.error('Error: ' + data.error);
                    else alert('Error: ' + data.error);
                }
            } catch (err) {
                console.error(err);
                if (typeof EllaToast !== 'undefined') EllaToast.error('An error occurred during upload.');
                else alert('An error occurred during upload.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-upload me-1"></i>Upload Photo';
            }
        });
    });
</script>

<!-- Upload Retroactive Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-cloud-arrow-up me-2 text-primary"></i>Upload
                    Reference Photo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="uploadForm">
                <div class="modal-body">
                    <input type="hidden" id="upload-movement-id" name="movement_id">
                    <p class="small text-muted mb-3">Uploading photo for Reference: <strong id="upload-ref-label"
                            class="text-dark"></strong></p>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Images (Max 8) <span
                                class="text-danger">*</span></label>
                        <input type="file" name="reference_images[]" id="upload-file" class="form-control"
                            accept="image/*" multiple required>
                        <div class="form-text small">You can select up to 8 images at once.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-upload">
                        <i class="fa-solid fa-upload me-1"></i>Upload Photo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Attachment Modal -->
<div class="modal fade" id="attachmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-paperclip me-2 text-primary"></i>Reference: <span
                        id="attachment-ref-title"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="attachmentCarousel" class="carousel slide" data-bs-ride="false">
                    <div class="carousel-inner" id="modal-carousel-inner">
                        <!-- Images will be injected here -->
                    </div>
                    <button class="carousel-control-prev d-none" type="button" data-bs-target="#attachmentCarousel"
                        data-bs-slide="prev" style="width: 10%;">
                        <span class="carousel-control-prev-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next d-none" type="button" data-bs-target="#attachmentCarousel"
                        data-bs-slide="next" style="width: 10%;">
                        <span class="carousel-control-next-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
                <div class="text-center p-2 bg-light border-top d-none" id="carousel-indicator-text">
                    <small class="text-muted">Image <span id="current-img-idx">1</span> of <span
                            id="total-img-count">1</span></small>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-between">
                <div>
                    <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCurrentAttachment()">
                            <i class="fa-solid fa-trash me-1"></i>Delete Photo
                        </button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <a id="attachment-download" href="" download class="btn btn-sm btn-outline-primary"
                        onclick="this.href=document.getElementById('attachment-img').src">
                        <i class="fa-solid fa-download me-1"></i>Download
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>