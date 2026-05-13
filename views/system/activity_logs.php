<?php
// views/system/activity_logs.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings')) {
    denyAccess("You do not have permission to view activity logs.");
}

// Get list of all users for the filter dropdown
require_once '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, full_name, username FROM users ORDER BY full_name ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    .log-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }

    .table-hover tbody tr:hover {
        background-color: rgba(59, 130, 246, 0.05);
    }

    .badge-Auth {
        background-color: #6366f1;
    }

    /* Indigo */
    .badge-POS {
        background-color: #10b981;
    }

    /* Emerald */
    .badge-Inventory {
        background-color: #f59e0b;
    }

    /* Amber */
    .badge-Settings {
        background-color: #8b5cf6;
    }

    /* Violet */
    .badge-System {
        background-color: #64748b;
    }

    /* Slate */

    .log-action {
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.85rem;
        padding: 3px 6px;
        background-color: var(--bg-surface-hover);
        color: var(--text-primary);
        border-radius: 4px;
        border: 1px solid var(--border-color);
    }

    .filter-panel {
        background-color: var(--bg-surface);
        padding: 15px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
        margin-bottom: 20px;
    }

    .ip-address {
        font-size: 0.8rem;
        color: var(--text-secondary);
        font-family: monospace;
    }

    /* Skeleton Loader */
    @keyframes skeleton-loading {
        0% {
            background-color: var(--bg-surface-hover);
        }

        100% {
            background-color: var(--border-color);
        }
    }

    .skeleton {
        animation: skeleton-loading 1s linear infinite alternate;
        height: 16px;
        border-radius: 4px;
        width: 100%;
        margin: 4px 0;
    }

    /* Action Colors */
    .log-action.success {
        background-color: rgba(16, 185, 129, 0.1);
        color: #10b981;
        border-color: rgba(16, 185, 129, 0.2);
    }

    .log-action.warning {
        background-color: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
        border-color: rgba(245, 158, 11, 0.2);
    }

    .log-action.danger {
        background-color: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border-color: rgba(239, 68, 68, 0.2);
    }

    .log-action.info {
        background-color: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
        border-color: rgba(59, 130, 246, 0.2);
    }
</style>

<div class="container-fluid p-3 p-lg-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h4 class="fw-bold mb-1">
                <i class="fa-solid fa-list-check text-primary me-2"></i>Activity Logs
            </h4>
            <p class="text-muted mb-0 small">Audit trail of system actions performed by users.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <div class="form-check form-switch d-flex align-items-center me-2">
                <input class="form-check-input mt-0 me-2" type="checkbox" id="auto-refresh-toggle"
                    onchange="ActivityLogs.toggleAutoRefresh()">
                <label class="form-check-label small text-muted mb-0" for="auto-refresh-toggle">Live Updates</label>
            </div>
            <button class="btn btn-outline-secondary btn-sm" onclick="ActivityLogs.exportLogs()">
                <i class="fa-solid fa-download"></i> Export
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="ActivityLogs.load(1)">
                <i class="fa-solid fa-rotate-right"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-panel shadow-sm">
        <form id="filter-form" onsubmit="event.preventDefault(); ActivityLogs.load(1);">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Search</label>
                    <input type="text" class="form-control form-control-sm" id="filter-search"
                        placeholder="Search desc or action...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Module</label>
                    <select class="form-select form-select-sm" id="filter-module">
                        <option value="">All</option>
                        <option value="Auth">Auth</option>
                        <option value="POS">POS Sales</option>
                        <option value="Inventory">Inventory</option>
                        <option value="Settings">Settings</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">User</label>
                    <select class="form-select form-select-sm" id="filter-user">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= htmlspecialchars($u['full_name'] . ' (' . $u['username'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">From Date</label>
                    <input type="date" class="form-control form-control-sm" id="filter-start">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">To Date</label>
                    <input type="date" class="form-control form-control-sm" id="filter-end">
                </div>
                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Table Card -->
    <div class="card log-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="logs-table">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-4">Timestamp</th>
                            <th scope="col">User</th>
                            <th scope="col">Module</th>
                            <th scope="col">Action</th>
                            <th scope="col">Description</th>
                            <th scope="col" class="text-end pe-4">IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="logs-body">
                        <tr>
                            <td colspan="6" class="text-center py-4">Loading logs...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div class="d-flex justify-content-between align-items-center p-3 border-top">
                <div class="text-muted small" id="pagination-info">
                    Showing 0 to 0 of 0 entries
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination-controls">
                        <!-- Filled by JS -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>

</div>

<!-- View Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Description</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre id="log-full-description" class="bg-light p-3 rounded"
                    style="white-space: pre-wrap; word-wrap: break-word; font-family: monospace; font-size: 0.9rem;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    const ActivityLogs = {
        currentPage: 1,
        autoRefreshInterval: null,
        isAutoRefresh: false,

        init() {
            // Default Date Ranges (Last 30 days to today)
            const today = new Date();
            const lastMonth = new Date();
            lastMonth.setDate(today.getDate() - 30);

            document.getElementById('filter-end').value = today.toISOString().split('T')[0];
            document.getElementById('filter-start').value = lastMonth.toISOString().split('T')[0];

            this.load(1);
        },

        getFilters() {
            return {
                module: document.getElementById('filter-module').value,
                user_id: document.getElementById('filter-user').value,
                search: document.getElementById('filter-search').value.trim(),
                action_type: '', // Covered by search
                start_date: document.getElementById('filter-start').value,
                end_date: document.getElementById('filter-end').value,
                page: this.currentPage,
                limit: 50
            };
        },

        toggleAutoRefresh() {
            this.isAutoRefresh = document.getElementById('auto-refresh-toggle').checked;
            if (this.isAutoRefresh) {
                this.load(this.currentPage, true);
                this.autoRefreshInterval = setInterval(() => {
                    this.load(this.currentPage, true);
                }, 10000); // 10 seconds
            } else {
                clearInterval(this.autoRefreshInterval);
            }
        },

        exportLogs() {
            const filters = this.getFilters();
            // Remove pagination for export
            delete filters.page;
            delete filters.limit;
            const params = new URLSearchParams(filters).toString();
            window.location.href = `../../api/system/export_activity_logs.php?${params}`;
        },

        async load(page = 1, isSilentRefresh = false) {
            this.currentPage = page;
            const tbody = document.getElementById('logs-body');

            if (!isSilentRefresh) {
                // Skeleton Loader
                let skeletons = '';
                for (let i = 0; i < 5; i++) {
                    skeletons += `<tr>
                        <td><div class="skeleton" style="width: 120px;"></div></td>
                        <td><div class="skeleton" style="width: 100px;"></div><div class="skeleton" style="width: 70px; height: 12px;"></div></td>
                        <td><div class="skeleton" style="width: 80px;"></div></td>
                        <td><div class="skeleton" style="width: 90px;"></div></td>
                        <td><div class="skeleton" style="width: 100%;"></div></td>
                        <td class="text-end"><div class="skeleton" style="width: 100px; display: inline-block;"></div></td>
                    </tr>`;
                }
                tbody.innerHTML = skeletons;
            }

            try {
                const filters = this.getFilters();
                const params = new URLSearchParams(filters).toString();

                const res = await fetch(`../../api/system/get_activity_logs.php?${params}`);
                const data = await res.json();

                if (data.success) {
                    this.render(data.logs);
                    this.renderPagination(data.pagination);
                } else {
                    if (!isSilentRefresh) tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">Error: ${data.error}</td></tr>`;
                }
            } catch (err) {
                console.error(err);
                if (!isSilentRefresh) tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">Network error loading logs.</td></tr>`;
            }
        },

        getActionColorClass(action) {
            const a = action.toUpperCase();
            if (a.includes('SUCCESS') || a.includes('COMPLETE') || a.includes('CREATE') || a.includes('ADD')) return 'success';
            if (a.includes('WARN') || a.includes('FAIL') || a.includes('UPDATE') || a.includes('EDIT')) return 'warning';
            if (a.includes('DELETE') || a.includes('REMOVE') || a.includes('VOID') || a.includes('ERROR')) return 'danger';
            return 'info';
        },

        showFullDesc(encodedDesc) {
            const desc = decodeURIComponent(encodedDesc);
            document.getElementById('log-full-description').textContent = desc;
            new bootstrap.Modal(document.getElementById('logDetailsModal')).show();
        },

        render(logs) {
            const tbody = document.getElementById('logs-body');

            if (!logs || logs.length === 0) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-inbox fs-1 mb-3 opacity-25"></i><br>
                        No activity logs found for the selected criteria.
                    </td>
                </tr>
            `;
                return;
            }

            tbody.innerHTML = logs.map(log => {
                const date = new Date(log.created_at);
                const formattedDate = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) +
                    ' ' + date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                const moduleBadgeClass = `badge badge-${log.module} text-white px-2 py-1 rounded-pill small`;

                const userDisplay = log.full_name ?
                    `<strong>${this.escapeHtml(log.full_name)}</strong><br><small class="text-muted">@${this.escapeHtml(log.username)}</small>` :
                    '<span class="text-muted fst-italic">System/Unknown</span>';

                const colorClass = this.getActionColorClass(log.action_type);

                let descDisplay = this.escapeHtml(log.description);
                if (descDisplay.length > 80) {
                    descDisplay = descDisplay.substring(0, 80) + '... ' +
                        `<a href="#" onclick="event.preventDefault(); ActivityLogs.showFullDesc('${encodeURIComponent(log.description)}')">View Full</a>`;
                }

                // Deep linking for references
                let refDisplay = '';
                if (log.item_id) {
                    const itemIdStr = String(log.item_id);
                    if (log.module === 'Inventory') {
                        refDisplay = `<br><a href="../../views/inventory/edit.php?id=${itemIdStr}" class="small text-primary text-decoration-none"><i class="fa-solid fa-link"></i> Ref ID: ${this.escapeHtml(itemIdStr)}</a>`;
                    } else if (log.module === 'POS' && itemIdStr.startsWith('TRN-')) {
                        refDisplay = `<br><small class="text-muted"><i class="fa-solid fa-hashtag"></i> ${this.escapeHtml(itemIdStr)}</small>`;
                    } else {
                        refDisplay = `<br><small class="text-muted">Ref ID: ${this.escapeHtml(itemIdStr)}</small>`;
                    }
                }

                return `
                <tr>
                    <td class="ps-4 text-nowrap" style="font-size: 0.9rem;">
                        ${formattedDate}
                    </td>
                    <td>${userDisplay}</td>
                    <td>
                        <span class="${moduleBadgeClass}">${this.escapeHtml(log.module)}</span>
                    </td>
                    <td>
                        <span class="log-action ${colorClass}">${this.escapeHtml(log.action_type)}</span>
                    </td>
                    <td>
                        <div style="max-width: 400px; white-space: normal;">
                            ${descDisplay}
                            ${refDisplay}
                        </div>
                    </td>
                    <td class="text-end pe-4 ip-address">
                        ${this.escapeHtml(log.ip_address || '-')}
                    </td>
                </tr>
            `;
            }).join('');
        },

        renderPagination(p) {
            const info = document.getElementById('pagination-info');
            const controls = document.getElementById('pagination-controls');

            if (p.total_records === 0) {
                info.textContent = 'Showing 0 entries';
                controls.innerHTML = '';
                return;
            }

            const start = ((p.current_page - 1) * p.limit) + 1;
            const end = Math.min(p.current_page * p.limit, p.total_records);
            info.innerHTML = `Showing <strong>${start}</strong> to <strong>${end}</strong> of <strong>${p.total_records}</strong> entries`;

            let html = '';

            // Prev button
            html += `
            <li class="page-item ${p.current_page === 1 ? 'disabled' : ''}">
                <button class="page-link" onclick="ActivityLogs.load(${p.current_page - 1})">Previous</button>
            </li>
        `;

            // Page numbers
            let startPage = Math.max(1, p.current_page - 2);
            let endPage = Math.min(p.total_pages, p.current_page + 2);

            if (startPage > 1) {
                html += `<li class="page-item"><button class="page-link" onclick="ActivityLogs.load(1)">1</button></li>`;
                if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `
                <li class="page-item ${i === p.current_page ? 'active' : ''}">
                    <button class="page-link" onclick="ActivityLogs.load(${i})">${i}</button>
                </li>
            `;
            }

            if (endPage < p.total_pages) {
                if (endPage < p.total_pages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                html += `<li class="page-item"><button class="page-link" onclick="ActivityLogs.load(${p.total_pages})">${p.total_pages}</button></li>`;
            }

            // Next button
            html += `
            <li class="page-item ${p.current_page === p.total_pages ? 'disabled' : ''}">
                <button class="page-link" onclick="ActivityLogs.load(${p.current_page + 1})">Next</button>
            </li>
        `;

            controls.innerHTML = html;
        },

        escapeHtml(text) {
            if (text !== null && typeof text === 'object') {
                return JSON.stringify(text);
            }
            if (!text && text !== 0) return '';
            const strText = String(text);
            const div = document.createElement('div');
            div.textContent = strText;
            return div.innerHTML;
        }
    };

    document.addEventListener('DOMContentLoaded', () => ActivityLogs.init());
</script>

<?php require_once '../../includes/footer.php'; ?>