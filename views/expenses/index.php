<?php
// views/expenses/index.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requirePermission('view_expenses');

$page_title = 'Expense Tracking - Ella POS';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid p-3 p-lg-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h4 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-money-bill-transfer text-primary me-2"></i>Expense Tracking
            </h4>
            <p class="text-muted mb-0 small">Record, analyze, and manage business expenses</p>
        </div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="fa-solid fa-plus me-1"></i> Record Expense
        </button>
    </div>

    <!-- Stats and Charts -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                                    <i class="fa-solid fa-chart-line text-danger fa-lg"></i>
                                </div>
                                <div>
                                    <div class="h4 fw-bold mb-0 text-danger" id="stat-total-expenses">₱0.00</div>
                                    <small class="text-muted fw-bold text-uppercase">Total Expenses</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                                    <i class="fa-solid fa-receipt text-primary fa-lg"></i>
                                </div>
                                <div>
                                    <div class="h4 fw-bold mb-0 text-primary" id="stat-expense-count">0</div>
                                    <small class="text-muted fw-bold text-uppercase">Records Found</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-center">
                    <div style="height: 180px; width: 100%;">
                        <canvas id="expenseCategoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body p-3">
            <form id="filter-form" class="row g-2 align-items-end">
                <div class="col-12 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">FROM</label>
                    <input type="date" name="date_from" id="filter-date-from" class="form-control form-control-sm"
                        value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">TO</label>
                    <input type="date" name="date_to" id="filter-date-to" class="form-control form-control-sm"
                        value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">CATEGORY</label>
                    <select name="category" id="filter-category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <option value="Supplies">Supplies</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Rent">Rent</option>
                        <option value="Payroll">Payroll</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Transportation">Transportation</option>
                        <option value="Shipping">Shipping</option>
                        <option value="Miscellaneous">Miscellaneous</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">SEARCH</label>
                    <input type="text" id="filter-search" class="form-control form-control-sm"
                        placeholder="Search reference, description..." autocomplete="off">
                </div>
                <div class="col-12 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1" title="Filter">
                        <i class="fa-solid fa-filter"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                        onclick="ExpenseTracker.resetFilters()" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="ExpenseTracker.exportCSV()"
                        title="Export to CSV">
                        <i class="fa-solid fa-file-csv"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <!-- Bulk Action Bar -->
            <div class="bg-light p-2 border-bottom d-none align-items-center justify-content-between"
                id="bulk-actions-bar">
                <span class="small fw-bold text-muted ms-2"><span id="selected-count">0</span> records selected</span>
                <button class="btn btn-sm btn-danger fw-bold" onclick="ExpenseTracker.bulkDelete()">
                    <i class="fa-solid fa-trash me-1"></i>Delete Selected
                </button>
            </div>

            <div id="loading-state" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2">Loading expenses...</p>
            </div>

            <div id="empty-state" class="text-center py-5 d-none">
                <i class="fa-solid fa-money-bill-transfer fa-3x text-muted opacity-25 mb-3"></i>
                <h6 class="text-muted">No expenses found</h6>
                <p class="small text-muted">Try adjusting your filters or record a new expense.</p>
            </div>

            <div class="table-responsive d-none" id="table-container">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-3 text-center" style="width: 40px;">
                                <input class="form-check-input" type="checkbox" id="selectAllCheckbox"
                                    onclick="ExpenseTracker.toggleSelectAll(this)">
                            </th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Details</th>
                            <th>Reference / Image</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="expenses-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle me-2"></i>Record Expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="add-expense-form">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-muted small">AMOUNT (₱) <span
                                    class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-lg fw-bold text-danger"
                                id="exp-amount" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-muted small">DATE <span
                                    class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-lg" id="exp-date"
                                value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-muted small">CATEGORY <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" id="exp-category" required>
                                <option value="">Select Category...</option>
                                <option value="Supplies">Supplies</option>
                                <option value="Utilities">Utilities</option>
                                <option value="Rent">Rent</option>
                                <option value="Payroll">Payroll</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Transportation">Transportation</option>
                                <option value="Shipping">Shipping</option>
                                <option value="Miscellaneous">Miscellaneous</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-muted small">PAYMENT SOURCE</label>
                            <select class="form-select" id="exp-payment-source">
                                <option value="">Select Source...</option>
                                <option value="Cash Drawer">💵 Cash Drawer</option>
                                <option value="Bank Transfer">🏦 Bank</option>
                                <option value="GCash">📱 GCash</option>
                                <option value="Owner Pocket">👤 Owner Pocket</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-muted small">REFERENCE/OR NO.</label>
                            <input type="text" class="form-control" id="exp-reference-no"
                                placeholder="Optional reference">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-muted small">RECEIPT/DOCUMENT (Optional)</label>
                            <input type="file" class="form-control" id="exp-receipt-image"
                                accept=".jpg,.jpeg,.png,.pdf">
                            <div class="form-text small" style="font-size:0.7rem;">Accepted: JPG, PNG, PDF.</div>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <div class="form-check mt-md-4">
                                <input class="form-check-input" type="checkbox" id="exp-is-recurring" value="1">
                                <label class="form-check-label fw-bold text-muted small" for="exp-is-recurring">
                                    Is Recurring Expense?
                                </label>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-muted small">RECURRENCE PERIOD</label>
                            <select class="form-select" id="exp-recurrence-period">
                                <option value="">None (One-time)</option>
                                <option value="Daily">Daily</option>
                                <option value="Weekly">Weekly</option>
                                <option value="Monthly">Monthly</option>
                                <option value="Yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">DESCRIPTION</label>
                        <textarea class="form-control" id="exp-description" rows="2"
                            placeholder="Brief details about this expense..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary px-4 fw-bold"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="btn-save-exp">
                        <i class="fa-solid fa-save me-1"></i>Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 bg-transparent">
            <div class="modal-header border-0 pb-0 justify-content-end">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body text-center pt-0">
                <img id="preview-image" src="" class="img-fluid rounded shadow" style="max-height: 85vh;" alt="Receipt">
                <iframe id="preview-pdf" src="" class="d-none w-100 rounded shadow" style="height: 85vh;"
                    title="Receipt PDF"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
    const ExpenseTracker = {
        modal: null,
        previewModal: null,
        allExpenses: [],
        chartInstance: null,

        init() {
            this.modal = new bootstrap.Modal(document.getElementById('addExpenseModal'));
            this.previewModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));

            document.getElementById('filter-form').addEventListener('submit', (e) => {
                e.preventDefault();
                this.load();
            });

            document.getElementById('filter-search').addEventListener('input', () => {
                this.renderTable();
            });

            document.getElementById('add-expense-form').addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveExpense();
            });

            this.load();
        },

        resetFilters() {
            document.getElementById('filter-date-from').value = '<?= date("Y-m-01") ?>';
            document.getElementById('filter-date-to').value = '<?= date("Y-m-d") ?>';
            document.getElementById('filter-category').value = '';
            document.getElementById('filter-search').value = '';
            this.load();
        },

        exportCSV() {
            const dateFrom = document.getElementById('filter-date-from').value;
            const dateTo = document.getElementById('filter-date-to').value;
            const category = document.getElementById('filter-category').value;

            const url = `../../api/expenses/export.php?date_from=${dateFrom}&date_to=${dateTo}&category=${category}`;
            window.location.href = url;
        },

        async load() {
            const loading = document.getElementById('loading-state');
            const empty = document.getElementById('empty-state');
            const container = document.getElementById('table-container');

            loading.classList.remove('d-none');
            empty.classList.add('d-none');
            container.classList.add('d-none');

            document.getElementById('bulk-actions-bar').classList.add('d-none');
            document.getElementById('selectAllCheckbox').checked = false;

            const params = new URLSearchParams({
                date_from: document.getElementById('filter-date-from').value,
                date_to: document.getElementById('filter-date-to').value,
                category: document.getElementById('filter-category').value
            });

            try {
                const res = await fetch(`../../api/expenses/get_expenses.php?${params}`);
                const data = await res.json();

                if (!data.success) throw new Error(data.message);

                this.allExpenses = data.expenses;

                document.getElementById('stat-total-expenses').textContent = '₱' + data.stats.total.toLocaleString(undefined, { minimumFractionDigits: 2 });
                document.getElementById('stat-expense-count').textContent = data.stats.count.toLocaleString();

                this.updateChart(data.expenses);

                if (data.expenses.length === 0) {
                    loading.classList.add('d-none');
                    empty.classList.remove('d-none');
                    return;
                }

                this.renderTable();
                loading.classList.add('d-none');
                container.classList.remove('d-none');

            } catch (error) {
                console.error(error);
                loading.innerHTML = `<div class="alert alert-danger m-3">Error loading expenses: ${error.message}</div>`;
            }
        },

        renderTable() {
            const tbody = document.getElementById('expenses-tbody');
            const searchQuery = document.getElementById('filter-search').value.toLowerCase();

            const filtered = this.allExpenses.filter(exp => {
                if (!searchQuery) return true;
                const desc = (exp.description || '').toLowerCase();
                const ref = (exp.reference_no || '').toLowerCase();
                return desc.includes(searchQuery) || ref.includes(searchQuery);
            });

            // Make sure we uncheck 'select all' on render
            document.getElementById('selectAllCheckbox').checked = false;
            this.updateBulkActionBar();

            const safeQuery = searchQuery ? searchQuery.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&').split(/\\s+/).filter(Boolean) : [];
            const highlight = (text) => {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                let hlText = div.innerHTML;

                if (safeQuery.length === 0) return hlText;
                safeQuery.forEach(q => {
                    const regex = new RegExp(`(${q})`, 'gi');
                    hlText = hlText.replace(regex, '<mark class="bg-warning bg-opacity-50 p-0 rounded text-dark">$1</mark>');
                });
                return hlText;
            };

            tbody.innerHTML = filtered.map(exp => {
                let receiptHtml = '';
                if (exp.receipt_image) {
                    receiptHtml = `
                        <button class="btn btn-sm btn-light border p-1 opacity-75 mt-1" onclick="ExpenseTracker.previewImage('${exp.receipt_image}')" title="View Document">
                          <i class="fa-solid fa-paperclip text-primary"></i> <small style="font-size:0.6rem;">Attached</small>
                        </button>
                     `;
                }

                return `
                <tr>
                    <td class="ps-3 text-center">
                        <input class="form-check-input row-checkbox" type="checkbox" value="${exp.id}" onclick="ExpenseTracker.updateBulkActionBar()">
                    </td>
                    <td class="fw-bold text-dark">
                        ${new Date(exp.expense_date).toLocaleDateString()}
                    </td>
                    <td>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">${exp.category}</span>
                        ${exp.is_recurring == "1" ? `<span class="badge bg-info-subtle text-info border border-info-subtle ms-1"><i class="fa-solid fa-rotate"></i> ${exp.recurrence_period || 'Recurring'}</span>` : ''}
                        <div class="small text-muted mt-1" style="font-size: 0.7rem;"><i class="fa-solid fa-wallet me-1"></i>${exp.payment_source || 'Unspecified'}</div>
                    </td>
                    <td>
                        <div class="d-inline-block text-truncate lh-sm" style="max-width: 200px;" title="${exp.description || 'No description'}">
                            ${exp.description ? highlight(exp.description) : '<em class="text-muted">No description</em>'}
                        </div>
                        <div class="small fw-normal text-muted" style="font-size: 0.65rem;">Recorded by: ${exp.created_by_name || 'System'}</div>
                    </td>
                    <td>
                        <div class="small fw-bold text-dark">${exp.reference_no ? 'Ref: ' + highlight(exp.reference_no) : '<span class="text-muted small">No Ref</span>'}</div>
                        ${receiptHtml}
                    </td>
                    <td class="text-end fw-bold text-danger">₱${parseFloat(exp.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-outline-danger py-1 px-2" onclick="ExpenseTracker.deleteExpense(${exp.id})" title="Delete Expense">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `}).join('');
        },

        previewImage(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const url = `../../assets/uploads/receipts/${filename}`;
            const imgEl = document.getElementById('preview-image');
            const pdfEl = document.getElementById('preview-pdf');

            if (ext === 'pdf') {
                imgEl.classList.add('d-none');
                pdfEl.classList.remove('d-none');
                pdfEl.src = url;
            } else {
                pdfEl.classList.add('d-none');
                imgEl.classList.remove('d-none');
                imgEl.src = url;
            }

            this.previewModal.show();
        },

        updateChart(expenses) {
            const ctx = document.getElementById('expenseCategoryChart');
            if (!ctx) return;

            // Group by category
            const categories = {};
            expenses.forEach(e => {
                categories[e.category] = (categories[e.category] || 0) + parseFloat(e.amount);
            });

            const labels = Object.keys(categories);
            const data = Object.values(categories);

            if (this.chartInstance) {
                this.chartInstance.destroy();
            }

            if (labels.length === 0) return; // No data, hide chart

            this.chartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            '#4361ee', '#f72585', '#7209b7', '#3a0ca3', '#4cc9f0', '#f8961e', '#e9c46a'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 12, font: { size: 10 } } }
                    },
                    cutout: '65%'
                }
            });
        },

        toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            this.updateBulkActionBar();
        },

        updateBulkActionBar() {
            const selected = document.querySelectorAll('.row-checkbox:checked').length;
            const bar = document.getElementById('bulk-actions-bar');
            document.getElementById('selected-count').textContent = selected;

            if (selected > 0) {
                bar.classList.remove('d-none');
                bar.classList.add('d-flex');
            } else {
                bar.classList.add('d-none');
                bar.classList.remove('d-flex');
            }
        },

        async bulkDelete() {
            const checkboxes = document.querySelectorAll('.row-checkbox:checked');
            if (checkboxes.length === 0) return;

            const ids = Array.from(checkboxes).map(cb => parseInt(cb.value));

            const confirmed = typeof EllaConfirm !== "undefined"
                ? await EllaConfirm.show({
                    title: 'Delete Multiple Expenses',
                    message: `Are you sure you want to delete ${ids.length} expense records? This action is permanent.`,
                    confirmText: 'Delete All',
                    confirmClass: 'btn-danger',
                    icon: 'fa-trash'
                })
                : confirm(`Are you sure you want to delete ${ids.length} recording(s)?`);

            if (!confirmed) return;

            try {
                const res = await fetch('../../api/expenses/bulk_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: ids })
                });
                const data = await res.json();

                if (!data.success) throw new Error(data.message);

                if (typeof EllaToast === "undefined") { alert(data.message); } else { EllaToast.show('Success', data.message, 'success'); }
                this.load();

            } catch (error) {
                console.error(error);
                if (typeof EllaToast === "undefined") { alert(error.message); } else { EllaToast.error(error.message); }
            }
        },

        async saveExpense() {
            const btn = document.getElementById('btn-save-exp');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            // Create form data for file upload
            const formData = new FormData();
            formData.append('amount', document.getElementById('exp-amount').value);
            formData.append('category', document.getElementById('exp-category').value);
            formData.append('expense_date', document.getElementById('exp-date').value);
            formData.append('description', document.getElementById('exp-description').value);
            formData.append('payment_source', document.getElementById('exp-payment-source').value);
            formData.append('reference_no', document.getElementById('exp-reference-no').value);

            if (document.getElementById('exp-is-recurring').checked) {
                formData.append('is_recurring', '1');
            }
            formData.append('recurrence_period', document.getElementById('exp-recurrence-period').value);

            const fileInput = document.getElementById('exp-receipt-image');
            if (fileInput.files.length > 0) {
                formData.append('receipt_image', fileInput.files[0]);
            }

            try {
                const res = await fetch('../../api/expenses/create_expense.php', {
                    method: 'POST',
                    body: formData // Body is FormData, Content-Type is set automatically
                });
                const data = await res.json();

                if (!data.success) throw new Error(data.message);

                if (typeof EllaToast === "undefined") {
                    alert("Expense saved successfully!");
                } else {
                    EllaToast.show('Success', 'Expense recorded successfully', 'success');
                }

                document.getElementById('add-expense-form').reset();
                document.getElementById('exp-date').value = '<?= date("Y-m-d") ?>';
                this.modal.hide();
                this.load();

            } catch (error) {
                console.error(error);
                if (typeof EllaToast === "undefined") { alert(error.message); } else { EllaToast.error(error.message); }
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        },

        async deleteExpense(id) {
            const confirmed = typeof EllaConfirm !== "undefined"
                ? await EllaConfirm.show({
                    title: 'Delete Expense',
                    message: 'Are you sure you want to delete this expense record? This action cannot be undone.',
                    confirmText: 'Delete',
                    confirmClass: 'btn-danger',
                    icon: 'fa-trash'
                })
                : confirm('Are you sure you want to delete this expense record?');

            if (!confirmed) return;

            try {
                const res = await fetch('../../api/expenses/delete_expense.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const data = await res.json();

                if (!data.success) throw new Error(data.message);

                if (typeof EllaToast === "undefined") { alert("Expense deleted successfully!"); } else { EllaToast.show('Success', 'Expense deleted', 'success'); }
                this.load();

            } catch (error) {
                console.error(error);
                if (typeof EllaToast === "undefined") { alert(error.message); } else { EllaToast.error(error.message); }
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => ExpenseTracker.init());
</script>

<?php require_once '../../includes/footer.php'; ?>