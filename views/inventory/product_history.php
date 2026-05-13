<?php
// views/inventory/product_history.php - Product Transaction History
$page_title = 'Product History | Ella Motor Parts';
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requirePermission('view_product_history');

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    .search-container {
        position: relative;
    }

    .search-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1050;
        max-height: 350px;
        overflow-y: auto;
        background: var(--bs-body-bg, #fff);
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-top: none;
        border-radius: 0 0 0.5rem 0.5rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        display: none;
    }

    .search-dropdown.show {
        display: block;
    }

    .search-item {
        padding: 0.65rem 1rem;
        cursor: pointer;
        border-bottom: 1px solid var(--bs-border-color-translucent, rgba(0, 0, 0, 0.05));
        transition: background 0.15s;
    }

    .search-item:hover {
        background: var(--bs-primary-bg-subtle, #e7f1ff);
    }

    .search-item:last-child {
        border-bottom: none;
    }

    .product-info-card {
        background: linear-gradient(135deg, var(--bs-primary) 0%, #4a90d9 100%);
        color: #fff;
        border-radius: 1rem;
    }

    .product-info-card .price-badge {
        background: rgba(255, 255, 255, 0.2);
        padding: 0.35rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.85rem;
    }

    .stat-card {
        border: none;
        border-radius: 0.75rem;
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }

    .tx-table tbody tr {
        transition: background 0.15s;
    }

    .tx-table tbody tr:hover {
        background: var(--bs-primary-bg-subtle, #e7f1ff);
    }

    .tx-table .voided-row {
        opacity: 0.55;
        text-decoration: line-through;
    }

    .suggestion-card {
        border: none;
        border-radius: 0.75rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .together-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px dashed var(--bs-border-color-translucent, #eee);
    }

    .together-item:last-child {
        border-bottom: none;
    }

    .mobile-tx-card {
        border-left: 4px solid var(--bs-primary);
        border-radius: 0.5rem;
        transition: all 0.2s;
    }

    .mobile-tx-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .mobile-tx-card.voided {
        border-left-color: var(--bs-danger);
        opacity: 0.6;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-in {
        animation: fadeInUp 0.4s ease forwards;
    }
</style>

<div class="container-fluid p-3 p-lg-4">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h4 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Product Transaction History
            </h4>
            <p class="text-muted mb-0 small">Search any product to see its complete sales history</p>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">SEARCH PRODUCT</label>
                    <div class="search-container">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-search text-muted"></i></span>
                            <input type="text" id="product-search" class="form-control"
                                placeholder="Type product name, brand, SKU, or barcode..." autocomplete="off">
                            <span class="input-group-text bg-white d-none" id="search-spinner">
                                <i class="fa-solid fa-spinner fa-spin text-primary"></i>
                            </span>
                        </div>
                        <div class="search-dropdown" id="search-dropdown"></div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">FROM</label>
                    <input type="date" id="filter-date-from" class="form-control form-control-sm"
                        value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">TO</label>
                    <input type="date" id="filter-date-to" class="form-control form-control-sm"
                        value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm flex-grow-1" id="btn-filter" disabled>
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-reset" title="Clear">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Selected Product Badge -->
    <div id="selected-badge" class="d-none mb-3 animate-in">
        <div class="d-inline-flex align-items-center bg-primary bg-opacity-10 rounded-pill px-3 py-2">
            <i class="fa-solid fa-box text-primary me-2"></i>
            <span class="fw-semibold text-primary" id="selected-label"></span>
            <button class="btn btn-sm text-primary ms-2 p-0" onclick="ProductHistory.clearSelection()" title="Clear">
                <i class="fa-solid fa-times-circle"></i>
            </button>
        </div>
    </div>

    <!-- Product Info Card (hidden until selected) -->
    <div id="product-info-section" class="d-none animate-in">
        <div class="product-info-card p-4 mb-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="fw-bold mb-1" id="info-name"></h5>
                    <p class="mb-1 opacity-75" id="info-details"></p>
                    <p class="mb-0 small opacity-50" id="info-sku"></p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                        <span class="price-badge"><i class="fa-solid fa-tag me-1"></i>SRP: <strong
                                id="info-srp">₱0</strong></span>
                        <span class="price-badge"><i class="fa-solid fa-store me-1"></i>Wholesale: <strong
                                id="info-wholesale">₱0</strong></span>
                        <span class="price-badge"><i class="fa-solid fa-handshake me-1"></i>Dealer: <strong
                                id="info-dealer">₱0</strong></span>
                    </div>
                    <div class="mt-2">
                        <span class="price-badge"><i class="fa-solid fa-boxes-stacked me-1"></i>Stock: <strong
                                id="info-stock">0</strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4" id="stats-row">
            <div class="col-6 col-lg-3">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                                <i class="fa-solid fa-cubes text-primary fa-lg"></i>
                            </div>
                            <div>
                                <div class="h4 fw-bold mb-0" id="stat-qty">0</div>
                                <small class="text-muted">Qty Sold</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3">
                                <i class="fa-solid fa-peso-sign text-success fa-lg"></i>
                            </div>
                            <div>
                                <div class="h5 fw-bold mb-0" id="stat-revenue">₱0</div>
                                <small class="text-muted">Revenue</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (hasPermission('view_profit')): ?>
                <div class="col-6 col-lg-3">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-info bg-opacity-10 p-2 me-3">
                                    <i class="fa-solid fa-chart-line text-info fa-lg"></i>
                                </div>
                                <div>
                                    <div class="h5 fw-bold mb-0" id="stat-profit">₱0</div>
                                    <small class="text-muted">Profit</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="col-6 col-lg-3">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-2 me-3">
                                <i class="fa-solid fa-receipt text-warning fa-lg"></i>
                            </div>
                            <div>
                                <div class="h4 fw-bold mb-0" id="stat-txcount">0</div>
                                <small class="text-muted">Transactions</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Price Range Insight -->
        <div class="alert alert-light border shadow-sm mb-4 d-flex align-items-center" id="price-insight">
            <i class="fa-solid fa-chart-bar text-primary me-3 fa-lg"></i>
            <div>
                <strong>Price Range:</strong>
                <span id="insight-prices">-</span>
                <span class="ms-3"><strong>Avg Price:</strong> <span id="insight-avg">-</span></span>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    <i class="fa-solid fa-list text-primary me-2"></i>Transaction Records
                </h6>
                <span class="badge bg-secondary" id="tx-count-badge">0 records</span>
            </div>
            <div class="card-body p-0">
                <!-- Loading -->
                <div id="tx-loading" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2">Loading transactions...</p>
                </div>
                <!-- Empty -->
                <div id="tx-empty" class="text-center py-5 d-none">
                    <i class="fa-solid fa-inbox fa-3x text-muted opacity-25 mb-3"></i>
                    <h6 class="text-muted">No transactions found</h6>
                    <p class="small text-muted">This product has not been sold in the selected date range</p>
                </div>
                <!-- Desktop Table -->
                <div class="table-responsive d-none d-lg-block">
                    <table class="table table-hover tx-table align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Reference</th>
                                <th>Customer</th>
                                <th>Price Tier</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Discount</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Subtotal</th>
                                <?php if (hasPermission('view_profit')): ?>
                                    <th class="text-end">Profit</th>
                                <?php endif; ?>
                                <th class="text-center pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody id="tx-tbody"></tbody>
                    </table>
                </div>
                <!-- Mobile Cards -->
                <div class="p-3 d-lg-none" id="tx-cards"></div>
            </div>
        </div>

        <!-- Suggestions Section -->
        <div class="row g-4 mb-4" id="suggestions-section">
            <!-- Frequently Bought Together -->
            <div class="col-md-6">
                <div class="card suggestion-card h-100">
                    <div class="card-header bg-transparent border-0 pt-3 pb-0">
                        <h6 class="fw-bold mb-0">
                            <i class="fa-solid fa-link text-primary me-2"></i>Frequently Bought Together
                        </h6>
                    </div>
                    <div class="card-body" id="bought-together-body">
                        <div class="text-muted small text-center py-3">No data yet</div>
                    </div>
                </div>
            </div>
            <!-- Best Customers -->
            <div class="col-md-6">
                <div class="card suggestion-card h-100">
                    <div class="card-header bg-transparent border-0 pt-3 pb-0">
                        <h6 class="fw-bold mb-0">
                            <i class="fa-solid fa-trophy text-warning me-2"></i>Top Buyers
                        </h6>
                    </div>
                    <div class="card-body" id="best-customers-body">
                        <div class="text-muted small text-center py-3">No data yet</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Initial State (before product selected) -->
    <div id="initial-state" class="text-center py-5">
        <i class="fa-solid fa-magnifying-glass-chart fa-4x text-muted opacity-25 mb-3"></i>
        <h5 class="text-muted">Search for a product above</h5>
        <p class="text-muted small">Select a product to view its complete transaction history</p>
    </div>
</div>

<script>
    const hasViewProfit = <?= json_encode(hasPermission('view_profit')) ?>;

    const ProductHistory = {
        selectedVariation: null,
        debounceTimer: null,

        init() {
            const searchInput = document.getElementById('product-search');
            const dropdown = document.getElementById('search-dropdown');

            // Search input with debounce
            searchInput.addEventListener('input', () => {
                clearTimeout(this.debounceTimer);
                const q = searchInput.value.trim();
                if (q.length < 1) {
                    dropdown.classList.remove('show');
                    dropdown.innerHTML = '';
                    return;
                }
                document.getElementById('search-spinner').classList.remove('d-none');
                this.debounceTimer = setTimeout(() => this.search(q), 300);
            });

            // Close dropdown on outside click
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.search-container')) {
                    dropdown.classList.remove('show');
                }
            });

            // Filter button
            document.getElementById('btn-filter').addEventListener('click', () => this.loadHistory());

            // Reset button
            document.getElementById('btn-reset').addEventListener('click', () => this.clearSelection());

            // Enter key on dates
            document.getElementById('filter-date-from').addEventListener('change', () => {
                if (this.selectedVariation) this.loadHistory();
            });
            document.getElementById('filter-date-to').addEventListener('change', () => {
                if (this.selectedVariation) this.loadHistory();
            });
        },

        async search(query) {
            const dropdown = document.getElementById('search-dropdown');
            try {
                const res = await fetch(`../../api/pos/simple_search.php?q=${encodeURIComponent(query)}`);
                const items = await res.json();
                document.getElementById('search-spinner').classList.add('d-none');

                if (!items.length) {
                    dropdown.innerHTML = '<div class="px-3 py-3 text-muted small text-center"><i class="fa-solid fa-search me-1"></i>No products found</div>';
                    dropdown.classList.add('show');
                    return;
                }

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

                dropdown.innerHTML = items.map(item => `
                <div class="search-item" onclick="ProductHistory.selectProduct(${item.variation_id}, ${JSON.stringify(item).replace(/"/g, '&quot;')})">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">${highlight(item.product_name)}</div>
                            <small class="text-muted">${highlight(item.brand_name || '')} ${highlight(item.variation_name || '')} ${item.unit_type ? '• ' + this.esc(item.unit_type) : ''}</small>
                            ${item.sku ? `<br><small class="text-muted opacity-75">SKU: ${highlight(item.sku)}</small>` : ''}
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-primary">₱${parseFloat(item.price_retail || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                            <small class="${parseInt(item.stock) > 0 ? 'text-success' : 'text-danger'}">${parseInt(item.stock)} in stock</small>
                        </div>
                    </div>
                </div>
            `).join('');
                dropdown.classList.add('show');
            } catch (err) {
                console.error('Search error:', err);
                document.getElementById('search-spinner').classList.add('d-none');
            }
        },

        selectProduct(variationId, item) {
            this.selectedVariation = variationId;
            document.getElementById('search-dropdown').classList.remove('show');
            document.getElementById('product-search').value = '';

            // Show selected badge
            const label = `${item.product_name} ${item.brand_name || ''} ${item.variation_name || ''}`.trim();
            document.getElementById('selected-label').textContent = label;
            document.getElementById('selected-badge').classList.remove('d-none');

            // Enable filter button
            document.getElementById('btn-filter').disabled = false;

            // Load history
            this.loadHistory();
        },

        clearSelection() {
            this.selectedVariation = null;
            document.getElementById('selected-badge').classList.add('d-none');
            document.getElementById('product-info-section').classList.add('d-none');
            document.getElementById('initial-state').classList.remove('d-none');
            document.getElementById('btn-filter').disabled = true;
            document.getElementById('product-search').value = '';
            document.getElementById('product-search').focus();
        },

        async loadHistory() {
            if (!this.selectedVariation) return;

            const dateFrom = document.getElementById('filter-date-from').value;
            const dateTo = document.getElementById('filter-date-to').value;

            // Show loading
            document.getElementById('initial-state').classList.add('d-none');
            document.getElementById('product-info-section').classList.remove('d-none');
            document.getElementById('tx-loading').classList.remove('d-none');
            document.getElementById('tx-empty').classList.add('d-none');
            document.getElementById('tx-tbody').innerHTML = '';
            document.getElementById('tx-cards').innerHTML = '';

            try {
                const params = new URLSearchParams({
                    variation_id: this.selectedVariation,
                    date_from: dateFrom,
                    date_to: dateTo
                });

                const res = await fetch(`../../api/inventory/product_transaction_history.php?${params}`);
                const data = await res.json();

                document.getElementById('tx-loading').classList.add('d-none');

                if (!data.success) {
                    throw new Error(data.error || 'Failed to load');
                }

                // Populate product info
                this.renderProductInfo(data.product);
                this.renderStats(data.stats);
                this.renderTransactions(data.transactions);
                this.renderSuggestions(data.suggestions);

            } catch (err) {
                console.error('Load error:', err);
                document.getElementById('tx-loading').classList.add('d-none');
                document.getElementById('tx-empty').classList.remove('d-none');
            }
        },

        renderProductInfo(product) {
            document.getElementById('info-name').textContent = product.product_name;
            document.getElementById('info-details').textContent =
                `${product.brand_name || ''} ${product.variation_name || ''} ${product.unit_type ? '• ' + product.unit_type : ''}`.trim();
            document.getElementById('info-sku').textContent = `SKU: ${product.sku || 'N/A'} ${product.barcode ? '| Barcode: ' + product.barcode : ''}`;
            document.getElementById('info-srp').textContent = '₱' + parseFloat(product.price_retail || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
            document.getElementById('info-wholesale').textContent = '₱' + parseFloat(product.price_wholesale || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
            document.getElementById('info-dealer').textContent = '₱' + parseFloat(product.price_dealer || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
            document.getElementById('info-stock').textContent = parseInt(product.current_stock || 0);
        },

        renderStats(stats) {
            document.getElementById('stat-qty').textContent = stats.total_qty_sold || 0;
            document.getElementById('stat-revenue').textContent = '₱' + parseFloat(stats.total_revenue || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });

            if (hasViewProfit && document.getElementById('stat-profit')) {
                document.getElementById('stat-profit').textContent = '₱' + parseFloat(stats.total_profit || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
            }

            document.getElementById('stat-txcount').textContent = stats.transaction_count || 0;

            // Price insight
            const min = parseFloat(stats.min_price || 0);
            const max = parseFloat(stats.max_price || 0);
            const avg = parseFloat(stats.avg_price || 0);

            if (min === max && min > 0) {
                document.getElementById('insight-prices').textContent = '₱' + min.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' (consistent)';
            } else if (min > 0) {
                document.getElementById('insight-prices').textContent = '₱' + min.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' – ₱' + max.toLocaleString(undefined, { minimumFractionDigits: 2 });
            } else {
                document.getElementById('insight-prices').textContent = 'No sales data';
            }
            document.getElementById('insight-avg').textContent = avg > 0
                ? '₱' + avg.toLocaleString(undefined, { minimumFractionDigits: 2 })
                : '-';
        },

        renderTransactions(transactions) {
            if (!transactions.length) {
                document.getElementById('tx-empty').classList.remove('d-none');
                document.getElementById('tx-count-badge').textContent = '0 records';
                return;
            }

            document.getElementById('tx-count-badge').textContent = transactions.length + ' records';

            // Desktop table
            const tbody = document.getElementById('tx-tbody');
            tbody.innerHTML = transactions.map(t => {
                const isVoided = t.status === 'voided';
                const tierBadge = this.getTierBadge(t.price_tier);
                const statusBadge = this.getStatusBadge(t.status);

                const discount = parseFloat(t.item_discount || 0);
                const origPrice = parseFloat(t.original_price || 0);
                const discPct = (origPrice > 0 && discount > 0) ? ((discount / origPrice) * 100).toFixed(1) : null;
                const discountHtml = discount > 0
                    ? `<span class="text-danger fw-semibold">-₱${discount.toLocaleString(undefined, { minimumFractionDigits: 2 })}${discPct ? ` <small>(${discPct}%)</small>` : ''}</span>`
                    : `<span class="text-muted">—</span>`;

                return `
                <tr class="${isVoided ? 'voided-row' : ''}">
                    <td class="ps-4">
                        <div class="small">${this.formatDate(t.created_at)}</div>
                        <small class="text-muted">${this.formatTime(t.created_at)}</small>
                    </td>
                    <td><span class="fw-bold text-primary">${this.esc(t.sale_ref)}</span></td>
                    <td>
                        <div class="fw-semibold">${this.esc(t.customer_name || 'Walk-in')}</div>
                        ${t.shop_name ? `<small class="text-muted">${this.esc(t.shop_name)}</small>` : ''}
                    </td>
                    <td>${tierBadge}</td>
                    <td class="text-end">₱${parseFloat(t.price_at_sale).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td class="text-end">${discountHtml}</td>
                    <td class="text-center fw-semibold">${t.quantity}</td>
                    <td class="text-end fw-bold">₱${parseFloat(t.subtotal).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    ${hasViewProfit ? `
                    <td class="text-end">
                        <span class="fw-bold ${parseFloat(t.item_profit) >= 0 ? 'text-success' : 'text-danger'}">
                            ₱${parseFloat(t.item_profit || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                        </span>
                    </td>
                    ` : ''}
                    <td class="text-center pe-4">${statusBadge}</td>
                </tr>
            `;
            }).join('');

            // Mobile cards
            const cards = document.getElementById('tx-cards');
            cards.innerHTML = transactions.map(t => {
                const isVoided = t.status === 'voided';
                const tierBadge = this.getTierBadge(t.price_tier);
                const statusBadge = this.getStatusBadge(t.status);

                return `
                <div class="card mobile-tx-card ${isVoided ? 'voided' : ''} mb-3 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="fw-bold text-primary small">${this.esc(t.sale_ref)}</span>
                                <div class="small text-muted">${this.formatDate(t.created_at)} ${this.formatTime(t.created_at)}</div>
                            </div>
                            <div class="text-end">
                                ${statusBadge}
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="small fw-semibold">${this.esc(t.customer_name || 'Walk-in')}</div>
                                <div class="small text-muted">${t.quantity}× @ ₱${parseFloat(t.price_at_sale).toLocaleString(undefined, { minimumFractionDigits: 2 })} ${tierBadge}</div>
                                ${parseFloat(t.item_discount || 0) > 0 ? `<div class="small text-danger">Discount: -₱${parseFloat(t.item_discount).toLocaleString(undefined, { minimumFractionDigits: 2 })}${parseFloat(t.original_price || 0) > 0 ? ` (${((parseFloat(t.item_discount) / parseFloat(t.original_price)) * 100).toFixed(1)}%)` : ''}</div>` : ''}
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">₱${parseFloat(t.subtotal).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                                ${hasViewProfit ? `<small class="text-success">Profit: ₱${parseFloat(t.item_profit || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</small>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            }).join('');
        },

        renderSuggestions(suggestions) {
            // Frequently bought together
            const togetherBody = document.getElementById('bought-together-body');
            if (suggestions.bought_together && suggestions.bought_together.length) {
                togetherBody.innerHTML = suggestions.bought_together.map((item, i) => `
                <div class="together-item">
                    <div>
                        <span class="badge bg-primary bg-opacity-10 text-primary me-2">${i + 1}</span>
                        <span class="fw-semibold small">${this.esc(item.product_name)}</span>
                        <span class="text-muted small"> ${this.esc(item.brand_name || '')} ${this.esc(item.variation_name || '')}</span>
                    </div>
                    <span class="badge bg-light text-dark">${item.times_together}× together</span>
                </div>
            `).join('');
            } else {
                togetherBody.innerHTML = '<div class="text-muted small text-center py-3"><i class="fa-solid fa-link-slash me-1"></i>No frequently bought together data</div>';
            }

            // Best customers
            const customersBody = document.getElementById('best-customers-body');
            if (suggestions.best_customers && suggestions.best_customers.length) {
                customersBody.innerHTML = suggestions.best_customers.map((c, i) => {
                    const medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
                    return `
                    <div class="together-item">
                        <div>
                            <span class="me-2">${medals[i] || ''}</span>
                            <span class="fw-semibold small">${this.esc(c.customer_name)}</span>
                            ${c.shop_name ? `<span class="text-muted small"> (${this.esc(c.shop_name)})</span>` : ''}
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success bg-opacity-10 text-success">${c.total_qty} pcs</span>
                            <small class="text-muted ms-1">₱${parseFloat(c.total_spent).toLocaleString(undefined, { minimumFractionDigits: 2 })}</small>
                        </div>
                    </div>
                `;
                }).join('');
            } else {
                customersBody.innerHTML = '<div class="text-muted small text-center py-3"><i class="fa-solid fa-users-slash me-1"></i>No customer data</div>';
            }
        },

        getTierBadge(tier) {
            const badges = {
                'retail': '<span class="badge bg-info bg-opacity-10 text-info">SRP</span>',
                'wholesale': '<span class="badge bg-warning bg-opacity-10 text-warning">Wholesale</span>',
                'dealer': '<span class="badge bg-success bg-opacity-10 text-success">Dealer</span>'
            };
            return badges[tier] || `<span class="badge bg-secondary bg-opacity-10 text-secondary">${tier || 'N/A'}</span>`;
        },

        getStatusBadge(status) {
            const badges = {
                'completed': '<span class="badge bg-success">Completed</span>',
                'voided': '<span class="badge bg-danger">Voided</span>',
                'not_completed': '<span class="badge bg-warning text-dark">Not Completed</span>'
            };
            return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
        },

        formatDate(dt) {
            if (!dt) return '';
            const d = new Date(dt);
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        formatTime(dt) {
            if (!dt) return '';
            const d = new Date(dt);
            return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        },

        esc(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    document.addEventListener('DOMContentLoaded', () => ProductHistory.init());
</script>

<?php require_once '../../includes/footer.php'; ?>