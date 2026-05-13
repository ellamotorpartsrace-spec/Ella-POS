<?php
// views/categories/index.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    denyAccess("You do not have permission to manage categories.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<!-- Include SortableJS for Drag and Drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold"><i class="fa-solid fa-tags text-primary"></i> Product Categories</h4>
            <div class="text-muted small">Manage product classifications and groups</div>
        </div>
        <div class="d-flex gap-2">
            <!-- Search Bar -->
            <div class="input-group" style="width: 300px;">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="fa-solid fa-search"></i>
                </span>
                <input type="text" id="categorySearch" class="form-control border-start-0 ps-0"
                    placeholder="Search categories...">
            </div>

            <!-- View Toggle -->
            <div class="btn-group shadow-sm me-2" role="group">
                <input type="radio" class="btn-check" name="viewToggle" id="btnGrid" value="grid" checked>
                <label class="btn btn-outline-primary" for="btnGrid" title="Grid View"><i
                        class="fa-solid fa-grid-2"></i> Grid</label>

                <input type="radio" class="btn-check" name="viewToggle" id="btnTable" value="table">
                <label class="btn btn-outline-primary" for="btnTable" title="Table View"><i
                        class="fa-solid fa-list mt-1"></i> List</label>
            </div>

            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fa-solid fa-plus me-2"></i>Add Category
            </button>
        </div>
    </div>

    <!-- Category Stats/Quick View -->
    <div class="row g-4 mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase mb-2"
                                style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Categories</h6>
                            <h2 class="mb-0 fw-bold" id="totalCategories">0</h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary">
                            <i class="fa-solid fa-layer-group fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories Grid (Cards) -->
    <div class="row g-4 view-container" id="categoriesGrid">
        <!-- Categories will be loaded here -->
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>

    <!-- Categories Table (List) -->
    <div class="card border-0 shadow-sm view-container d-none" id="categoriesTableCard">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="categoriesTable">
                    <thead style="background: var(--bg-surface);">
                        <tr>
                            <th style="width: 50px;" class="text-center text-muted"><i
                                    class="fa-solid fa-grip-lines"></i></th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Created</th>
                            <th class="text-end pe-4">Manage</th>
                        </tr>
                    </thead>
                    <tbody id="categoriesTbody">
                        <!-- Table rows loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addCategoryForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Category Name <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="category_name" required
                            placeholder="e.g. Engine Parts">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Description</label>
                        <textarea class="form-control" name="description" rows="3"
                            placeholder="Optional description..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Theme Color</label>
                        <input type="color" class="form-control form-control-color w-100" name="color" value="#0d6efd"
                            title="Choose your color">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary py-2 fw-bold">
                            <i class="fa-solid fa-save me-2"></i>Save Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editCategoryForm">
                    <input type="hidden" name="category_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Category Name <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Theme Color</label>
                        <input type="color" class="form-control form-control-color w-100" name="color"
                            title="Choose your color">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary py-2 fw-bold">
                            <i class="fa-solid fa-check me-2"></i>Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Products Modal -->
<div class="modal fade" id="viewProductsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Products in Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <div id="viewProductsLoader" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div class="table-responsive d-none" id="viewProductsContent">
                    <table class="table table-hover align-middle mb-0">
                        <thead style="background: var(--bg-surface);">
                            <tr>
                                <th>Product Name</th>
                                <th>Brand</th>
                                <th>SKU</th>
                                <th class="text-end">Current Stock</th>
                            </tr>
                        </thead>
                        <tbody id="viewProductsTbody">
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        loadCategories();

        // Add Category
        document.getElementById('addCategoryForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            fetch('../../api/categories/create.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        bootstrap.Modal.getInstance(document.getElementById('addCategoryModal')).hide();
                        this.reset();
                        loadCategories();
                        // Optionally show toast success
                        EllaToast.success('Category added successfully!');
                    } else {
                        EllaToast.error(res.message);
                    }
                })
                .catch(err => console.error(err));
        });

        // Edit Category (Event Delegation)
        document.addEventListener('click', function (e) {
            if (e.target.closest('.edit-btn')) {
                const btn = e.target.closest('.edit-btn');
                const id = btn.dataset.id;
                const name = btn.dataset.name;
                const desc = btn.dataset.desc;
                const color = btn.dataset.color || '#0d6efd';

                const form = document.getElementById('editCategoryForm');
                form.elements['category_id'].value = id;
                form.elements['category_name'].value = name;
                form.elements['description'].value = desc || '';
                form.elements['color'].value = color;

                new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
            }
        });

        document.getElementById('editCategoryForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            fetch('../../api/categories/update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        bootstrap.Modal.getInstance(document.getElementById('editCategoryModal')).hide();
                        loadCategories();
                        EllaToast.success('Category updated successfully!');
                    } else {
                        EllaToast.error(res.message);
                    }
                })
                .catch(err => console.error(err));
        });

        // Delete (Archive)
        document.addEventListener('click', function (e) {
            if (e.target.closest('.delete-btn')) {
                if (!confirm('Are you sure you want to delete this category? Products in this category will become uncategorized.')) return;

                const btn = e.target.closest('.delete-btn');
                const id = btn.dataset.id;

                fetch(`../../api/categories/delete.php?id=${id}`)
                    .then(response => response.json())
                    .then(res => {
                        if (res.success) {
                            loadCategories();
                        } else {
                            EllaToast.error(res.message);
                        }
                    })
                    .catch(err => console.error(err));
            }
        });

        // View Products List
        document.addEventListener('click', function (e) {
            if (e.target.closest('.view-products-btn')) {
                const btn = e.target.closest('.view-products-btn');
                const categoryId = btn.dataset.id;
                const categoryName = btn.dataset.name;

                const modal = document.getElementById('viewProductsModal');
                const title = modal.querySelector('.modal-title');
                const loader = document.getElementById('viewProductsLoader');
                const content = document.getElementById('viewProductsContent');
                const tbody = document.getElementById('viewProductsTbody');

                title.innerHTML = `Products in <span class="text-primary">${categoryName}</span>`;
                loader.classList.remove('d-none');
                content.classList.add('d-none');
                tbody.innerHTML = '';

                new bootstrap.Modal(modal).show();

                fetch(`../../api/categories/get_products.php?category_id=${categoryId}`)
                    .then(response => response.json())
                    .then(res => {
                        loader.classList.add('d-none');
                        content.classList.remove('d-none');

                        if (res.success) {
                            if (res.data.length === 0) {
                                tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted">No products found in this category.</td></tr>`;
                            } else {
                                let html = '';
                                res.data.forEach(prod => {
                                    const stock = parseInt(prod.total_stock);
                                    const stockBadge = stock > 0 ? `<span class="badge bg-success bg-opacity-10 text-success">${stock}</span>` : `<span class="badge bg-danger bg-opacity-10 text-danger">Out of Stock</span>`;

                                    html += `
                                    <tr>
                                        <td class="fw-bold text-dark">${prod.product_name}</td>
                                        <td class="text-muted">${prod.brand || '-'}</td>
                                        <td class="text-muted"><small>${prod.sku || '-'}</small></td>
                                        <td class="text-end">${stockBadge}</td>
                                    </tr>
                                    `;
                                });
                                tbody.innerHTML = html;
                            }
                        } else {
                            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">Error: ${res.message}</td></tr>`;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        loader.classList.add('d-none');
                        content.classList.remove('d-none');
                        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">Failed to load products.</td></tr>`;
                    });
            }
        });

        // View Toggling
        const viewRadios = document.querySelectorAll('input[name="viewToggle"]');
        viewRadios.forEach(radio => {
            radio.addEventListener('change', function (e) {
                if (e.target.value === 'grid') {
                    document.getElementById('categoriesGrid').classList.remove('d-none');
                    document.getElementById('categoriesTableCard').classList.add('d-none');
                } else {
                    document.getElementById('categoriesGrid').classList.add('d-none');
                    document.getElementById('categoriesTableCard').classList.remove('d-none');
                }
            });
        });

        let gridSortable, tableSortable;

        function loadCategories() {
            const gridContainer = document.getElementById('categoriesGrid');
            const tbody = document.getElementById('categoriesTbody');

            fetch('../../api/categories/read.php')
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        const categories = res.data;
                        document.getElementById('totalCategories').innerText = categories.length;

                        if (categories.length === 0) {
                            gridContainer.innerHTML = `
                        <div class="col-12 text-center py-5 text-muted">
                            <i class="fa-solid fa-box-open fa-3x mb-3"></i>
                            <h5>No categories found</h5>
                        </div>`;
                            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No categories found</td></tr>`;
                            return;
                        }

                        let gridHtml = '';
                        let tableHtml = '';

                        categories.forEach(cat => {
                            const desc = cat.description ? cat.description : '<span class="text-muted fst-italic">No description</span>';
                            const icon = cat.icon ? cat.icon : 'fa-tag';
                            const color = cat.color ? cat.color : '#0d6efd';
                            const productCount = cat.product_count ? cat.product_count : '0';

                            // GRID HTML
                            gridHtml += `
                        <div class="col-md-6 col-lg-4 col-xl-3 category-item" data-id="${cat.category_id}">
                            <div class="card h-100 border-0 shadow-sm category-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-2 rounded-circle text-white drag-handle" style="background-color: ${color}; cursor: move; width: 15px; height: 15px;" title="Drag to reorder">
                                            </div>
                                            <span class="badge border text-secondary bg-light">ID: ${cat.category_id}</span>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                                                <li>
                                                    <a class="dropdown-item edit-btn" href="#" 
                                                       data-id="${cat.category_id}" 
                                                       data-name="${cat.category_name}" 
                                                       data-desc="${cat.description || ''}"
                                                       data-color="${color}">
                                                       <i class="fa-solid fa-pen me-2 text-primary"></i> Edit
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item delete-btn text-danger" href="#" 
                                                       data-id="${cat.category_id}">
                                                       <i class="fa-solid fa-trash me-2"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <h5 class="fw-bold text-dark mb-1">${cat.category_name}</h5>
                                    <p class="text-muted small mb-2 single-line-truncate">${desc}</p>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border view-products-btn" role="button" data-id="${cat.category_id}" data-name="${cat.category_name}" title="View Products">
                                        <i class="fa-solid fa-box me-1"></i> ${productCount} Products
                                    </span>
                                </div>
                                <div class="card-footer bg-white border-top-0 pt-0 pb-3">
                                    <small class="text-muted" style="font-size: 0.7rem;">
                                        Created: ${new Date(cat.created_at).toLocaleDateString()}
                                    </small>
                                </div>
                            </div>
                        </div>`;

                            // TABLE HTML
                            tableHtml += `
                        <tr class="category-item" data-id="${cat.category_id}">
                            <td class="text-center text-muted drag-handle" style="cursor: move;"><i class="fa-solid fa-grip-dots-vertical"></i></td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle text-white text-center" style="background-color: ${color}; width: 12px; height: 12px;">
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">${cat.category_name}</div>
                                        <div class="small text-muted">ID: ${cat.category_id}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-muted small" style="max-width: 250px;"><div class="single-line-truncate">${desc}</div></td>
                            <td>
                                <span class="badge bg-light text-dark border view-products-btn" role="button" data-id="${cat.category_id}" data-name="${cat.category_name}" title="View Products">${productCount} Items</span>
                            </td>
                            <td class="text-muted small">${new Date(cat.created_at).toLocaleDateString()}</td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-light border edit-btn" title="Edit" 
                                    data-id="${cat.category_id}" 
                                    data-name="${cat.category_name}" 
                                    data-desc="${cat.description || ''}"
                                    data-color="${color}">
                                    <i class="fa-solid fa-pen text-primary"></i>
                                </button>
                                <button class="btn btn-sm btn-light border delete-btn" title="Delete" data-id="${cat.category_id}">
                                    <i class="fa-solid fa-trash text-danger"></i>
                                </button>
                            </td>
                        </tr>`;
                        });

                        gridContainer.innerHTML = gridHtml;
                        tbody.innerHTML = tableHtml;

                        // Initialize search functionality
                        initCategorySearch();

                        // Initialize Sortable logic
                        initSortable();
                    }
                });
        }

        function initCategorySearch() {
            const searchInput = document.getElementById('categorySearch');
            if (!searchInput) return;

            searchInput.addEventListener('input', function (e) {
                const term = e.target.value.toLowerCase();

                // Filter Grid
                const cards = document.querySelectorAll('#categoriesGrid .category-item');
                let visibleCount = 0;

                cards.forEach(card => {
                    const content = card.textContent.toLowerCase();
                    if (content.includes(term)) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Filter Table
                const rows = document.querySelectorAll('#categoriesTbody .category-item');
                rows.forEach(row => {
                    const content = row.textContent.toLowerCase();
                    if (content.includes(term)) {
                        row.style.display = 'table-row';
                    } else {
                        row.style.display = 'none';
                    }
                });

                document.getElementById('totalCategories').innerText = visibleCount;
            });
        }

        function initSortable() {
            const onReorder = function () {
                // Get new order from current visible view
                const container = document.getElementById('categoriesGrid').classList.contains('d-none') ?
                    document.getElementById('categoriesTbody') :
                    document.getElementById('categoriesGrid');

                const items = container.querySelectorAll('.category-item');
                const newOrder = [];
                items.forEach((item, index) => {
                    newOrder.push({ id: item.dataset.id, sort_order: index });
                });

                // Send to backend
                fetch('../../api/categories/reorder.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order: newOrder })
                })
                    .then(res => res.json())
                    .then(res => {
                        if (!res.success) console.error("Reorder failed", res.message);
                    });
            };

            // Grid Sortable
            if (gridSortable) gridSortable.destroy();
            gridSortable = new Sortable(document.getElementById('categoriesGrid'), {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'opacity-50',
                onEnd: onReorder
            });

            // Table Sortable
            if (tableSortable) tableSortable.destroy();
            tableSortable = new Sortable(document.getElementById('categoriesTbody'), {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'table-active',
                onEnd: onReorder
            });
        }
    });
</script>

<style>
    .category-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
    }

    .category-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08) !important;
    }

    .single-line-truncate {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>

<?php require_once '../../includes/footer.php'; ?>