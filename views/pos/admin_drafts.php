<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requireLogin();
if (!hasPermission('manage_settings') && $_SESSION['role'] !== 'admin') {
    denyAccess("Admin privileges required to view all user drafts.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="container-fluid py-4 min-vh-100" style="background: #f8fafc;">
    <!-- Header Section -->
    <div class="row align-items-center mb-4">
        <div class="col-lg-6">
            <h4 class="mb-1 fw-bold text-dark d-flex align-items-center">
                <span class="p-2 bg-primary bg-opacity-10 rounded-3 me-3">
                    <i class="fa-solid fa-folder-tree text-primary"></i>
                </span>
                System Draft Management
            </h4>
            <p class="text-muted small mb-0 ps-5 ms-3 d-none d-md-block">Cross-user administrative view for pending
                transactions and restocks.</p>
        </div>
        <div class="col-lg-6 mt-3 mt-lg-0 text-lg-end">
            <button class="btn btn-white shadow-sm border-0 px-3 fw-bold rounded-pill transition-all hover-lift"
                onclick="refreshCurrentTab()">
                <i class="fa-solid fa-arrows-rotate me-1 text-primary"></i>Refresh Data
            </button>
        </div>
    </div>

    <!-- Stats Dashboard -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="flex-shrink-0 bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                        <i class="fa-solid fa-file-invoice-dollar text-primary fa-lg"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block text-uppercase fw-bold ls-1" style="font-size: 0.65rem;">Total
                            POS Value</small>
                        <h4 class="mb-0 fw-bold" id="stats-pos-value">₱0.00</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="flex-shrink-0 bg-success bg-opacity-10 p-3 rounded-3 me-3">
                        <i class="fa-solid fa-truck-ramp-box text-success fa-lg"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block text-uppercase fw-bold ls-1" style="font-size: 0.65rem;">Total
                            Restock Value</small>
                        <h4 class="mb-0 fw-bold" id="stats-restock-value">₱0.00</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="flex-shrink-0 bg-warning bg-opacity-10 p-3 rounded-3 me-3">
                        <i class="fa-solid fa-users text-warning fa-lg"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block text-uppercase fw-bold ls-1" style="font-size: 0.65rem;">Draft
                            Creators</small>
                        <h4 class="mb-0 fw-bold" id="stats-creator-count">0</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="flex-shrink-0 bg-info bg-opacity-10 p-3 rounded-3 me-3">
                        <i class="fa-solid fa-clock text-info fa-lg"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block text-uppercase fw-bold ls-1" style="font-size: 0.65rem;">Active
                            Drafts</small>
                        <h4 class="mb-0 fw-bold" id="stats-active-count">0</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Sort Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i
                                class="fa-solid fa-user-filter text-muted"></i></span>
                        <select id="staffFilter" class="form-select border-0 bg-light" onchange="filterAndSortDrafts()">
                            <option value="">All Staff Members</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i
                                class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="draftSearch" class="form-control border-0 bg-light"
                            placeholder="Search labels or IDs..." oninput="debouncedFilter()">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i
                                class="fa-solid fa-sort text-muted"></i></span>
                        <select id="draftSort" class="form-select border-0 bg-light" onchange="filterAndSortDrafts()">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="value-high">Highest Value</option>
                            <option value="value-low">Lowest Value</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i
                                class="fa-solid fa-calendar text-muted"></i></span>
                        <select id="dateRange" class="form-select border-0 bg-light" onchange="filterAndSortDrafts()">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Toolbar -->
    <div id="bulk-toolbar"
        class="d-none sticky-top mb-3 bg-white p-3 rounded-4 shadow-lg border border-primary-subtle animate__animated animate__fadeInDown"
        style="top: 1rem; z-index: 1040;">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="bg-primary text-white rounded-pill px-3 py-1 fw-bold me-3" id="selected-count">0</div>
                <span class="fw-bold text-dark">Drafts Selected for Action</span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-light btn-sm fw-bold border rounded-pill px-3"
                    onclick="toggleSelectAll(false)">Deselect All</button>
                <div class="vr mx-2"></div>
                <button class="btn btn-danger btn-sm fw-bold shadow-sm rounded-pill px-4" onclick="bulkDeleteDrafts()">
                    <i class="fa-solid fa-trash-can me-1"></i>Delete Permanently
                </button>
            </div>
        </div>
    </div>

    <!-- Tabs Row -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <ul class="nav nav-pills bg-white p-2 rounded-4 shadow-sm" id="draftTabs" role="tablist">
                <li class="nav-item flex-fill text-center" role="presentation">
                    <button class="nav-link active w-100 fw-bold py-2 rounded-3 transition-all" id="pos-tab"
                        data-bs-toggle="pill" data-bs-target="#pos-content" type="button" role="tab"
                        onclick="switchTab('pos')">
                        <i class="fa-solid fa-cash-register me-2"></i>POS Sales Drafts
                        <span class="badge bg-primary ms-2" id="badge-pos-count">0</span>
                    </button>
                </li>
                <li class="nav-item flex-fill text-center" role="presentation">
                    <button class="nav-link w-100 fw-bold py-2 rounded-3 transition-all" id="restock-tab"
                        data-bs-toggle="pill" data-bs-target="#restock-content" type="button" role="tab"
                        onclick="switchTab('restock')">
                        <i class="fa-solid fa-truck-ramp-box me-2"></i>Inventory Restock
                        <span class="badge bg-secondary ms-2" id="badge-restock-count">0</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Container -->
    <div class="tab-content" id="draftTabsContent">
        <!-- POS CONTENT -->
        <div class="tab-pane fade show active" id="pos-content" role="tabpanel">
            <div id="pos-loading" class="text-center py-5">
                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
                <p class="text-muted mt-3 fw-bold">Optimizing POS draft view...</p>
            </div>
            <div id="pos-empty" class="text-center py-5 d-none">
                <div class="opacity-10 mb-3"><i class="fa-solid fa-inbox fa-5x"></i></div>
                <h5 class="text-muted fw-bold">No Records Match Your Search</h5>
                <button class="btn btn-sm btn-outline-primary mt-2 rounded-pill" onclick="resetFilters()">Reset All
                    Filters</button>
            </div>
            <div id="pos-grid" class="row g-3 d-none"></div>
        </div>

        <!-- RESTOCK CONTENT -->
        <div class="tab-pane fade" id="restock-content" role="tabpanel">
            <div id="restock-loading" class="text-center py-5">
                <div class="spinner-border text-success" style="width: 3rem; height: 3rem;" role="status"></div>
                <p class="text-muted mt-3 fw-bold">Syncing inventory drafts...</p>
            </div>
            <div id="restock-empty" class="text-center py-5 d-none">
                <div class="opacity-10 mb-3"><i class="fa-solid fa-box-open fa-5x"></i></div>
                <h5 class="text-muted fw-bold">No Restock Drafts Found</h5>
                <button class="btn btn-sm btn-outline-success mt-2 rounded-pill" onclick="resetFilters()">Reset All
                    Filters</button>
            </div>
            <div id="restock-grid" class="row g-3 d-none"></div>
        </div>
    </div>
</div>

<!-- ITEM PREVIEW MODAL -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered shadow-lg">
        <div class="modal-content border-0 overflow-hidden rounded-4">
            <div class="modal-header border-0 bg-light py-3">
                <h5 class="modal-title fw-bold" id="previewLabel">
                    <i class="fa-solid fa-magnifying-glass me-2 text-primary"></i>Draft Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom">
                    <div class="mb-2">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Draft Label</small>
                        <div class="fw-bold text-dark fs-5" id="previewLabelText">--</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <small class="text-muted d-block text-uppercase fw-bold"
                                style="font-size: 0.65rem;">Creator</small>
                            <span class="fw-bold text-dark small" id="previewCreator">--</span>
                        </div>
                        <div class="col-md-3 col-6">
                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.65rem;">Buyer /
                                Supplier</small>
                            <span class="fw-bold text-dark small" id="previewBuyer">--</span>
                        </div>
                        <div class="col-md-3 col-6">
                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.65rem;">Price
                                Tier</small>
                            <span class="fw-bold text-dark small" id="previewTier">--</span>
                        </div>
                        <div class="col-md-3 col-6">
                            <small class="text-muted d-block text-uppercase fw-bold"
                                style="font-size: 0.65rem;">Date</small>
                            <span class="fw-bold text-dark small" id="previewDate">--</span>
                        </div>
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-white sticky-top shadow-sm">
                            <tr class="small text-muted">
                                <th class="ps-4">Item</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end pe-4">Line Total</th>
                            </tr>
                        </thead>
                        <tbody id="previewTableBody"></tbody>
                    </table>
                </div>
                <!-- Totals Footer inside Modal -->
                <div class="p-3 bg-white border-top">
                    <div class="row">
                        <div class="col-md-6 offset-md-6">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">Subtotal</span>
                                <span id="previewSubtotal" class="fw-bold text-dark">₱0.00</span>
                            </div>
                            <div class="d-flex justify-content-between small mb-2">
                                <span class="text-muted">Discount</span>
                                <span class="text-danger" id="previewDiscount">-₱0.00</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold border-top pt-2">
                                <span class="text-dark">GRAND TOTAL</span>
                                <span class="text-success fs-5" id="previewTotal">₱0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 bg-light bg-opacity-50">
                <button type="button" class="btn btn-outline-secondary fw-bold rounded-pill px-4"
                    data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm" id="previewCopyBtn">
                    <i class="fa-solid fa-copy me-1"></i>Copy & Checkout
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentType = 'pos';
    let selectedIds = new Set();
    let allDrafts = { pos: [], restock: [] };
    let searchTimeout = null;

    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));

    document.addEventListener('DOMContentLoaded', () => {
        console.log('Admin Drafts: Auto-triggering system refresh...');
        // Parallel initialization for maximum speed
        loadStaffFilter();
        refreshCurrentTab();
    });

    async function loadStaffFilter() {
        try {
            const response = await fetch(`${BASE_URL}api/pos/admin_list_draft_creators.php`, { cache: 'no-store' });
            const data = await response.json();
            if (data.success) {
                const select = document.getElementById('staffFilter');
                data.creators.forEach(staff => {
                    const opt = document.createElement('option');
                    opt.value = staff.username;
                    opt.textContent = staff.full_name || staff.username;
                    select.appendChild(opt);
                });
            }
        } catch (e) {
            console.error('Filter load error:', e);
        }
    }

    function switchTab(type) {
        if (currentType !== type) {
            currentType = type;
            toggleSelectAll(false);
            // If we already have data, just re-render to apply current filters
            if (allDrafts[type].length > 0) {
                renderDrafts(type);
            } else {
                loadAdminDrafts(type);
            }
        }
    }

    function refreshCurrentTab() {
        loadAdminDrafts(currentType);
        if (currentType === 'pos') loadAdminDrafts('restock', true);
        else loadAdminDrafts('pos', true);
    }

    async function loadAdminDrafts(type, background = false) {
        const loading = document.getElementById(`${type}-loading`);
        const grid = document.getElementById(`${type}-grid`);
        const badge = document.getElementById(`badge-${type}-count`);

        if (!background && loading) {
            loading.classList.remove('d-none');
            if (grid) grid.classList.add('d-none');
        }

        try {
            const endpoint = (type === 'pos') ? 'api/pos/admin_list_drafts.php' : 'api/inventory/admin_list_restock_drafts.php';
            const response = await fetch(`${BASE_URL}${endpoint}`, { cache: 'no-store' });
            const data = await response.json();

            if (!data.success) throw new Error(data.error || 'Server returned failure');

            allDrafts[type] = data.drafts || [];
            if (badge) badge.textContent = allDrafts[type].length;

            updateStats();

            if (!background) {
                renderDrafts(type);
            }
        } catch (err) {
            console.error(`Admin Drafts: [${type}] load error:`, err);
            if (!background && grid) {
                grid.innerHTML = `<div class="alert alert-danger mx-3"><i class="fa-solid fa-triangle-exclamation me-2"></i>${err.message}</div>`;
                grid.classList.remove('d-none');
            }
        } finally {
            if (!background && loading) {
                loading.classList.add('d-none');
            }
        }
    }

    function updateStats() {
        const posTotal = allDrafts.pos.reduce((sum, d) => sum + parseFloat(d.total_amount || 0), 0);
        const restockTotal = allDrafts.restock.reduce((sum, d) => sum + parseFloat(d.total_amount || d.total || 0), 0);

        const allUsers = new Set([...allDrafts.pos.map(d => d.creator_name), ...allDrafts.restock.map(d => d.creator_name)]);

        document.getElementById('stats-pos-value').textContent = posTotal.toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
        document.getElementById('stats-restock-value').textContent = restockTotal.toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
        document.getElementById('stats-creator-count').textContent = allUsers.size;
        document.getElementById('stats-active-count').textContent = allDrafts.pos.length + allDrafts.restock.length;
    }

    function debouncedFilter() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            filterAndSortDrafts();
        }, 300);
    }

    function filterAndSortDrafts() {
        renderDrafts(currentType);
    }

    function renderDrafts(type) {
        const grid = document.getElementById(`${type}-grid`);
        const empty = document.getElementById(`${type}-empty`);

        let drafts = [...allDrafts[type]];

        // 1. Filtering
        const staff = document.getElementById('staffFilter').value;
        const search = document.getElementById('draftSearch').value.toLowerCase();
        const dateRange = document.getElementById('dateRange').value;

        if (staff) {
            drafts = drafts.filter(d => d.creator_name === staff);
        }

        if (search) {
            drafts = drafts.filter(d =>
                (d.label || d.draft_label || '').toLowerCase().includes(search) ||
                (d.draft_id + '').includes(search) ||
                (d.buyer_name || d.supplier_name || '').toLowerCase().includes(search)
            );
        }

        if (dateRange !== 'all') {
            const now = new Date();
            const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());

            drafts = drafts.filter(d => {
                const dDate = new Date(d.created_at);
                if (dateRange === 'today') return dDate >= startOfToday;
                if (dateRange === 'week') {
                    const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                    return dDate >= weekAgo;
                }
                if (dateRange === 'month') {
                    const monthAgo = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate());
                    return dDate >= monthAgo;
                }
                return true;
            });
        }

        // 2. Sorting
        const sort = document.getElementById('draftSort').value;
        drafts.sort((a, b) => {
            if (sort === 'newest') return new Date(b.created_at) - new Date(a.created_at);
            if (sort === 'oldest') return new Date(a.created_at) - new Date(b.created_at);
            if (sort === 'value-high') return parseFloat(b.total_amount || b.total || 0) - parseFloat(a.total_amount || a.total || 0);
            if (sort === 'value-low') return parseFloat(a.total_amount || a.total || 0) - parseFloat(b.total_amount || b.total || 0);
            return 0;
        });

        // 3. Rendering
        grid.innerHTML = '';
        if (drafts.length === 0) {
            grid.classList.add('d-none');
            empty.classList.remove('d-none');
            return;
        }

        empty.classList.add('d-none');
        grid.classList.remove('d-none');

        drafts.forEach(draft => {
            const date = new Date(draft.created_at).toLocaleString('en-US', {
                month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
            });
            const amount = parseFloat(draft.total_amount || draft.total || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
            const label = draft.label || draft.draft_label || 'Untitled Draft';
            const isSelected = selectedIds.has(draft.draft_id);

            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4 animate__animated animate__fadeIn';

            col.innerHTML = `
            <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden draft-card-premium transition-all ${isSelected ? 'selected' : ''}" data-draft-id="${draft.draft_id}" onclick="toggleSelection(${draft.draft_id}, event)">
                <div class="selection-overlay ${isSelected ? '' : 'd-none'}">
                    <i class="fa-solid fa-circle-check text-white fa-2x"></i>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge rounded-pill bg-light text-primary px-3 py-2 border border-primary-subtle fs-xs">
                            <i class="fa-solid fa-user me-1"></i>${draft.creator_name}
                        </span>
                        <div class="text-end">
                            <small class="text-muted fw-bold d-block">#${draft.draft_id}</small>
                        </div>
                    </div>
                    
                    <h5 class="fw-bold mb-1 text-truncate" title="${label}">${label}</h5>
                    <p class="text-muted small mb-3">
                        <i class="fa-solid fa-user-tag me-1 text-warning"></i>
                        ${draft.buyer_name || draft.supplier_name || 'Walk-in'}
                    </p>

                    <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded-4 mb-3 border border-light-subtle">
                        <div>
                            <small class="d-block text-muted text-uppercase fw-bold ls-1" style="font-size: 0.6rem;">Items</small>
                            <span class="fw-bold h6 mb-0">${draft.item_count} SKU</span>
                        </div>
                        <div class="text-end">
                            <small class="d-block text-muted text-uppercase fw-bold ls-1" style="font-size: 0.6rem;">Total</small>
                            <span class="fw-bold h6 mb-0 text-success">${amount}</span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between pt-2 mt-auto">
                        <small class="text-muted" style="font-size: 0.75rem;"><i class="fa-solid fa-clock me-1 text-info"></i>${date}</small>
                        <div class="d-flex gap-1">
                            <button onclick="previewDraft(${draft.draft_id}, '${type}', event)" class="btn btn-sm btn-light border p-2 px-3 rounded-pill hover-primary" title="Preview">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <button onclick="copyDraft(${draft.draft_id}, '${type}', false, event)" class="btn btn-sm btn-primary p-2 px-4 rounded-pill fw-bold shadow-sm hover-lift">
                                <i class="fa-solid fa-copy me-1"></i>Copy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
            grid.appendChild(col);
        });
    }

    function resetFilters() {
        document.getElementById('staffFilter').value = '';
        document.getElementById('draftSearch').value = '';
        document.getElementById('draftSort').value = 'newest';
        document.getElementById('dateRange').value = 'all';
        filterAndSortDrafts();
    }

    function toggleSelection(id, event) {
        const card = event.currentTarget;
        const overlay = card.querySelector('.selection-overlay');

        if (selectedIds.has(id)) {
            selectedIds.delete(id);
            card.classList.remove('selected');
            overlay.classList.add('d-none');
        } else {
            selectedIds.add(id);
            card.classList.add('selected');
            overlay.classList.remove('d-none');
        }

        updateBulkToolbar();
    }

    function toggleSelectAll(select) {
        const cards = document.querySelectorAll('.draft-card-premium');

        if (!select) {
            selectedIds.clear();
            cards.forEach(card => {
                card.classList.remove('selected');
                card.querySelector('.selection-overlay').classList.add('d-none');
            });
        } else {
            // Only select what's currently visible
            const visibleIds = Array.from(document.querySelectorAll(`#${currentType}-grid .draft-card-premium`))
                .map(card => {
                    // Find ID from the onclick attribute or data-attribute if we add one
                    // Since we have the ID in the toggleSelection(ID, event) call
                    const match = card.getAttribute('onclick').match(/toggleSelection\((\d+)/);
                    return match ? parseInt(match[1]) : null;
                }).filter(id => id !== null);

            visibleIds.forEach(id => selectedIds.add(id));
            cards.forEach(card => {
                card.classList.add('selected');
                card.querySelector('.selection-overlay').classList.remove('d-none');
            });
        }
        updateBulkToolbar();
    }

    function updateBulkToolbar() {
        const toolbar = document.getElementById('bulk-toolbar');
        const count = document.getElementById('selected-count');

        if (selectedIds.size > 0) {
            toolbar.classList.remove('d-none');
            count.textContent = selectedIds.size;
        } else {
            toolbar.classList.add('d-none');
        }
    }

    async function bulkDeleteDrafts() {
        const confirmed = await EllaConfirm.danger(`Delete ${selectedIds.size} Selected Drafts?`,
            "This will permanently erase these drafts from the system. This action cannot be reversed.");

        if (!confirmed) return;

        try {
            const endpoint = currentType === 'pos' ? 'api/pos/admin_bulk_delete_drafts.php' : 'api/inventory/admin_bulk_delete_restock_drafts.php';
            const response = await fetch(`${BASE_URL}${endpoint}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: Array.from(selectedIds) })
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.error);

            EllaToast.success(data.message || 'Drafts deleted successfully');
            selectedIds.clear();
            updateBulkToolbar();
            refreshCurrentTab();
        } catch (err) {
            EllaToast.error(err.message);
        }
    }

    let currentPreviewId = null;

    function escapeHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    async function previewDraft(id, type, event = null) {
        if (event) event.stopPropagation();

        // Find draft data safely
        const headData = allDrafts[type].find(d => d.draft_id == id);
        if (!headData) {
            EllaToast.error("Could not load draft details.");
            return;
        }

        // Highlight active card
        document.querySelectorAll('.draft-card-premium').forEach(c => c.classList.remove('previewing'));
        const clickedCard = document.querySelector(`.draft-card-premium[data-draft-id="${id}"]`);
        if (clickedCard) clickedCard.classList.add('previewing');

        // Populate modal headers
        document.getElementById('previewLabel').innerHTML = `<i class="fa-solid fa-magnifying-glass me-2 text-primary"></i>Preview ${type === 'pos' ? 'POS' : 'Restock'} Draft #${id}`;
        document.getElementById('previewLabelText').textContent = headData.label || headData.draft_label || 'Untitled Draft';
        document.getElementById('previewCreator').textContent = headData.creator_name || '--';
        document.getElementById('previewBuyer').textContent = headData.buyer_name || headData.supplier_name || 'Walk-in';
        document.getElementById('previewTier').textContent = headData.price_tier || 'Standard';
        document.getElementById('previewDate').textContent = new Date(headData.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

        document.getElementById('previewCopyBtn').onclick = () => copyDraft(id, type, true);

        // Open Modal
        previewModal.show();

        // Load items
        const tbody = document.getElementById('previewTableBody');
        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4"><div class="spinner-border text-primary"></div><p class="small text-muted mt-2 mb-0">Loading items...</p></td></tr>`;

        try {
            let items = [];
            if (type === 'pos') {
                const res = await fetch(`${BASE_URL}api/pos/get_draft_items.php?id=${id}`);
                const data = await res.json();
                if (data.success) items = data.items;
            } else {
                items = JSON.parse(headData.items || '[]');
            }

            tbody.innerHTML = '';
            let subtotal = 0;

            if (items.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-5"><i class="fa-solid fa-box-open fa-2x opacity-25 d-block mb-2"></i>No items in this draft</td></tr>`;
            } else {
                items.forEach(item => {
                    const qty = parseFloat(item.qty || item.quantity || 0);
                    const unitPrice = parseFloat(item.price || item.cost_price || 0);
                    const lineTotal = qty * unitPrice;
                    subtotal += lineTotal;
                    const name = escapeHtml(item.product_name || item.name || 'Unknown Item');
                    const sku = escapeHtml(item.sku || '');

                    tbody.innerHTML += `
                    <tr>
                        <td class="ps-4 py-2">
                            <div class="fw-bold small text-dark">${name}</div>
                            ${sku ? `<small class="text-muted" style="font-size:0.65rem;">${sku}</small>` : ''}
                        </td>
                        <td class="text-center fw-bold text-muted">${qty}</td>
                        <td class="text-end small">${unitPrice.toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}</td>
                        <td class="text-end pe-4 fw-bold text-dark">${lineTotal.toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}</td>
                    </tr>`;
                });
            }

            const grandTotal = parseFloat(headData.total_amount || headData.total || 0);
            const discount = Math.max(0, Math.round((subtotal - grandTotal) * 100) / 100);

            document.getElementById('previewSubtotal').textContent = subtotal.toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
            document.getElementById('previewDiscount').textContent = `-${discount.toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}`;
            document.getElementById('previewTotal').textContent = grandTotal.toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });

        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-4"><i class="fa-solid fa-triangle-exclamation me-2"></i>Error: ${escapeHtml(err.message)}</td></tr>`;
        }
    }

    async function copyDraft(id, type, fromModal = false, event = null) {
        if (event) event.stopPropagation();

        const isRestock = type === 'restock';
        const endpoint = isRestock ? 'api/inventory/admin_copy_restock_draft.php' : 'api/pos/admin_copy_draft.php';
        const redirect = isRestock ? 'views/inventory/restock.php' : 'views/pos/simple_checkout.php';

        try {
            const response = await fetch(`${BASE_URL}${endpoint}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ draft_id: id })
            });
            const data = await response.json();
            if (!data.success) throw new Error(data.error);

            if (fromModal) previewModal.hide();

            EllaToast.show({
                message: 'Draft copied to your session!',
                type: 'success',
                actionLabel: 'Go to Checkout',
                onAction: () => {
                    window.location.href = `${BASE_URL}${redirect}`;
                }
            });

        } catch (err) {
            EllaToast.error(err.message);
        }
    }
</script>

<style>
    :root {
        --card-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.05);
        --card-shadow-hover: 0 20px 40px -10px rgba(0, 0, 0, 0.1);
    }

    .draft-card-premium {
        cursor: pointer;
        background: #ffffff;
        border-radius: 1.5rem !important;
        position: relative;
        border: 1px solid rgba(0, 0, 0, 0.03) !important;
        box-shadow: var(--card-shadow);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .draft-card-premium:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-shadow-hover) !important;
    }

    .draft-card-premium.selected {
        box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.25) !important;
        background: #f8fbff;
        border-color: #0d6efd !important;
    }

    /* Active preview highlight */
    .draft-card-premium.previewing {
        border-left: 4px solid #0d6efd !important;
        background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%) !important;
        box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.15), var(--card-shadow-hover) !important;
    }

    .selection-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(13, 110, 253, 0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 5;
        pointer-events: none;
        border-radius: 1.5rem;
    }

    .ls-1 {
        letter-spacing: 0.05rem;
    }

    .fs-xs {
        font-size: 0.7rem;
    }

    .btn-white {
        background: #ffffff;
        color: #444;
    }

    .hover-lift:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1) !important;
    }

    .hover-primary:hover i {
        color: #0d6efd !important;
    }

    .nav-pills .nav-link {
        color: #6c757d;
        border-radius: 1rem;
        border: 1px solid transparent;
    }

    .nav-pills .nav-link.active {
        background: #f0f7ff !important;
        color: #0d6efd !important;
        border-color: rgba(13, 110, 253, 0.15);
    }

    .transition-all {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-body {
        display: flex;
        flex-direction: column;
    }

    .animate__animated {
        animation-duration: 0.4s;
    }
</style>

<?php require_once '../../includes/footer.php'; ?>