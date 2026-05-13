<?php
// views/inventory/price_history_records.php - Price History Logs
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    denyAccess("You do not have permission to view price history.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();
?>

<style>
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

    /* Price tier badges */
    .tier-badge {
        font-size: 0.75rem;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-weight: 600;
    }
    .tier-retail { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
    .tier-wholesale { background: rgba(25, 135, 84, 0.1); color: #198754; }
    .tier-dealer { background: rgba(111, 66, 193, 0.1); color: #6f42c1; }
    .tier-capital { background: rgba(108, 117, 125, 0.1); color: #6c757d; }

    .price-change {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-family: monospace;
    }
    .price-old { text-decoration: line-through; color: #adb5bd; }
    .price-new { font-weight: bold; }
    .price-up { color: #dc3545; }
    .price-down { color: #198754; }

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
        .mobile-cards { display: block !important; }
        .desktop-table { display: none !important; }
    }

    @media (min-width: 992px) {
        .mobile-cards { display: none !important; }
        .desktop-table { display: block !important; }
    }

    .mobile-record-card {
        border-left: 4px solid var(--bs-primary);
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
                <i class="fa-solid fa-chart-line text-primary me-2"></i>Price History
            </h4>
            <p class="text-muted mb-0 small">Track all historical changes to product prices</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Inventory
            </a>
            <a href="restock.php" class="btn btn-success btn-sm">
                <i class="fa-solid fa-plus me-1"></i>Update Prices/Stock
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4 d-none" id="stats-row">
        <div class="col-6 col-lg-3">
            <div class="card stats-card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                            <i class="fa-solid fa-tags text-primary fa-lg"></i>
                        </div>
                        <div>
                            <div class="h4 fw-bold mb-0 text-primary" id="stat-total">0</div>
                            <small class="text-muted">Total Changes</small>
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
                            <i class="fa-solid fa-boxes-stacked text-info fa-lg"></i>
                        </div>
                        <div>
                            <div class="h4 fw-bold mb-0 text-info" id="stat-products">0</div>
                            <small class="text-muted">Products Affected</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card stats-card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-2 me-3">
                            <i class="fa-solid fa-clock text-warning fa-lg"></i>
                        </div>
                        <div>
                            <div class="h5 fw-bold mb-0 text-dark" id="stat-last-change">--</div>
                            <small class="text-muted">Most Recent Update (Last 30 Days)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="filter-form" class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">SEARCH PRODUCT</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-search text-muted"></i></span>
                        <input type="text" name="search" id="search-input" class="form-control" 
                            placeholder="Product name, brand, SKU or barcode..." autocomplete="off">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">FROM</label>
                    <input type="date" name="date_from" id="date-from" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">TO</label>
                    <input type="date" name="date_to" id="date-to" class="form-control">
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fa-solid fa-filter me-1"></i>Search Records
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="reset-btn" title="Reset Filters">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Records Table -->
    <div class="card shadow-sm border-0 position-relative" id="records-card">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">
                <i class="fa-solid fa-list text-primary me-2"></i>History Records
            </h6>
            <span class="badge bg-secondary" id="records-count">0 records</span>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay d-none" id="loading-overlay">
            <div class="text-center">
                <div class="spinner-border text-primary mb-2" role="status"></div>
                <div class="text-muted small">Loading records...</div>
            </div>
        </div>

        <!-- Desktop View -->
        <div class="card-body p-0 desktop-table">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Date & Time</th>
                            <th>Product</th>
                            <?php if (hasPermission('view_profit')): ?>
                                <th>Capital</th>
                            <?php endif; ?>
                            <th>Retail (SRP)</th>
                            <th>Wholesale</th>
                            <th>Dealer</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody id="records-tbody">
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state">
                                    <h6 class="text-muted">Loading price history...</h6>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile View -->
        <div class="card-body p-3 mobile-cards" style="display: none;">
            <div id="records-mobile"></div>
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
    let currentPage = 1;

    document.addEventListener('DOMContentLoaded', function() {
        loadRecords();

        document.getElementById('filter-form').addEventListener('submit', function(e) {
            e.preventDefault();
            currentPage = 1;
            loadRecords();
        });

        document.getElementById('reset-btn').addEventListener('click', function() {
            document.getElementById('filter-form').reset();
            currentPage = 1;
            loadRecords();
        });

        // Theme sync or view sync if needed
        window.addEventListener('resize', updateViewDisplay);
        updateViewDisplay();
    });

    function updateViewDisplay() {
        const isMobile = window.innerWidth < 992;
        document.querySelector('.mobile-cards').style.display = isMobile ? 'block' : 'none';
        document.querySelector('.desktop-table').style.display = isMobile ? 'none' : 'block';
    }

    async function loadRecords(page = 1) {
        currentPage = page;
        const search = document.getElementById('search-input').value;
        const dateFrom = document.getElementById('date-from').value;
        const dateTo = document.getElementById('date-to').value;

        const overlay = document.getElementById('loading-overlay');
        overlay.classList.remove('d-none');

        try {
            const params = new URLSearchParams({
                search: search,
                date_from: dateFrom,
                date_to: dateTo,
                page: page,
                per_page: 50
            });

            const res = await fetch(`../../api/inventory/get_price_history.php?${params.toString()}`);
            const data = await res.json();

            if (data.success) {
                renderStats(data.stats);
                renderDesktopTable(data.records);
                renderMobileCards(data.records);
                renderPagination(data.pagination);
                document.getElementById('records-count').textContent = `${data.pagination.total} records`;
            } else {
                EllaToast.error(data.error || 'Failed to load records');
            }
        } catch (err) {
            console.error(err);
            EllaToast.error('Network error occurred');
        } finally {
            overlay.classList.add('d-none');
        }
    }

    function renderStats(stats) {
        if (!stats) return;
        document.getElementById('stats-row').classList.remove('d-none');
        document.getElementById('stat-total').textContent = parseInt(stats.total_changes || 0).toLocaleString();
        document.getElementById('stat-products').textContent = parseInt(stats.products_affected || 0).toLocaleString();
        
        if (stats.last_change) {
            const date = new Date(stats.last_change);
            document.getElementById('stat-last-change').textContent = date.toLocaleDateString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });
        }
    }

    function formatPriceChange(oldPrice, newPrice, labelClass) {
        const oldP = parseFloat(oldPrice || 0);
        const newP = parseFloat(newPrice || 0);
        
        if (oldP === newP) return `<span class="text-muted small">No change</span>`;
        
        const isUp = newP > oldP;
        const icon = isUp ? 'fa-arrow-up' : 'fa-arrow-down';
        const colorClass = isUp ? 'price-up' : 'price-down';
        
        return `
            <div class="price-change">
                <span class="price-old">₱${oldP.toFixed(2)}</span>
                <i class="fa-solid fa-arrow-right text-muted small mx-1"></i>
                <span class="price-new ${colorClass}">₱${newP.toFixed(2)}</span>
            </div>
        `;
    }

    function renderDesktopTable(records) {
        const tbody = document.getElementById('records-tbody');
        if (records.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5"><div class="empty-state"><i class="fa-solid fa-search d-block"></i><h6 class="text-muted">No records found</h6></div></td></tr>`;
            return;
        }

        let html = '';
        records.forEach(row => {
            const date = new Date(row.changed_at);
            const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const timeStr = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

            html += `
                <tr class="record-row">
                    <td class="ps-4">
                        <div class="fw-bold small">${dateStr}</div>
                        <small class="text-muted">${timeStr}</small>
                    </td>
                    <td>
                        <div class="fw-bold text-dark">${escapeHtml(row.product_name)}</div>
                        <small class="text-muted">${escapeHtml(row.brand_name || '')} | ${escapeHtml(row.variation_name || '')}</small>
                        <div class="small font-monospace text-muted" style="font-size: 0.75rem;">${escapeHtml(row.sku || row.barcode || '')}</div>
                    </td>
                    <?php if (hasPermission('view_profit')): ?>
                    <td>${formatPriceChange(row.old_capital, row.new_capital, 'tier-capital')}</td>
                    <?php endif; ?>
                    <td>${formatPriceChange(row.old_retail, row.new_retail, 'tier-retail')}</td>
                    <td>${formatPriceChange(row.old_wholesale, row.new_wholesale, 'tier-wholesale')}</td>
                    <td>${formatPriceChange(row.old_dealer, row.new_dealer, 'tier-dealer')}</td>
                    <td>
                        <small class="text-muted d-block">${escapeHtml(row.changed_by_name || 'System')}</small>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    function renderMobileCards(records) {
        const container = document.getElementById('records-mobile');
        if (records.length === 0) {
            container.innerHTML = `<div class="text-center py-5 text-muted">No records found</div>`;
            return;
        }

        let html = '<div class="d-flex flex-column gap-3">';
        records.forEach(row => {
            const date = new Date(row.changed_at);
            const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const timeStr = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

            html += `
                <div class="card mobile-record-card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                             <div class="flex-grow-1">
                                <div class="fw-bold text-dark">${escapeHtml(row.product_name)}</div>
                                <small class="text-muted">${escapeHtml(row.variation_name)}</small>
                             </div>
                             <div class="text-end">
                                <small class="text-muted d-block">${dateStr}</small>
                                <small class="text-muted d-block">${timeStr}</small>
                             </div>
                        </div>
                        
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <small class="text-muted d-block tier-badge tier-retail mb-1">Retail (SRP)</small>
                                ${formatPriceChange(row.old_retail, row.new_retail)}
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block tier-badge tier-wholesale mb-1">Wholesale</small>
                                ${formatPriceChange(row.old_wholesale, row.new_wholesale)}
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center border-top pt-2">
                            <small class="text-muted">By: <strong>${escapeHtml(row.changed_by_name || 'System')}</strong></small>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    function renderPagination(pagination) {
        const footer = document.getElementById('pagination-footer');
        const info = document.getElementById('pagination-info');
        const btns = document.getElementById('pagination-btns');

        if (pagination.total_pages <= 1) {
            footer.classList.add('d-none');
            return;
        }

        footer.classList.remove('d-none');
        const start = (pagination.current_page - 1) * pagination.per_page + 1;
        const end = Math.min(pagination.current_page * pagination.per_page, pagination.total);
        info.textContent = `Showing ${start}–${end} of ${pagination.total} records`;

        let html = '';
        if (pagination.current_page > 1) {
            html += `<button class="btn btn-sm btn-outline-primary" onclick="loadRecords(${pagination.current_page - 1})"><i class="fa-solid fa-chevron-left"></i></button>`;
        }

        const maxVisible = 5;
        let startPage = Math.max(1, pagination.current_page - Math.floor(maxVisible / 2));
        let endPage = Math.min(pagination.total_pages, startPage + maxVisible - 1);
        startPage = Math.max(1, endPage - maxVisible + 1);

        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="btn btn-sm ${i === pagination.current_page ? 'btn-primary' : 'btn-outline-primary'}" onclick="loadRecords(${i})">${i}</button>`;
        }

        if (pagination.current_page < pagination.total_pages) {
            html += `<button class="btn btn-sm btn-outline-primary" onclick="loadRecords(${pagination.current_page + 1})"><i class="fa-solid fa-chevron-right"></i></button>`;
        }
        btns.innerHTML = html;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
</script>

<?php require_once '../../includes/footer.php'; ?>
