<?php
// views/users/activity_log.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Security: Only Admins can view activity logs
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings')) {
    denyAccess("You do not have permission to view activity logs.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

// 1. Validate ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>window.location='index.php';</script>";
    exit;
}

$user_id = $_GET['id'];
$db = new Database();
$conn = $db->getConnection();

// 2. Fetch User Details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<div class='p-4'>User not found.</div>";
    require_once '../../includes/footer.php';
    exit;
}

// 3. Fetch Login History (Session Logs)
$stmtSession = $conn->prepare("
    SELECT * FROM user_sessions 
    WHERE user_id = ? 
    ORDER BY login_time DESC 
    LIMIT 50
");
$stmtSession->execute([$user_id]);
$sessions = $stmtSession->fetchAll(PDO::FETCH_ASSOC);

// 4. Get user ID for JS (sales loaded via AJAX now)
$user_id_js = $user_id;

// Stats - moved to AJAX for sales
$totalSessions = count($sessions);
$activeSessions = 0;

foreach ($sessions as $s) {
    if ($s['is_active'])
        $activeSessions++;
}
?>

<style>
    /* Activity Log Page Styles - Modernized & Theme Aware */
    .activity-page {
        display: flex;
        flex-direction: column;
        gap: 24px;
        animation: ellaFadeIn 0.4s ease-out;
    }

    .activity-header {
        margin-bottom: 8px;
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 20px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        color: var(--text-secondary);
        text-decoration: none !important;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: var(--card-shadow);
    }

    .back-btn:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
        transform: translateX(-4px);
        background: var(--primary-light);
    }

    .activity-content {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 24px;
        align-items: start;
    }

    /* User Profile Card */
    .profile-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        position: sticky;
        top: 100px;
        transition: transform 0.3s ease;
    }

    .profile-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, #8b5cf6 100%);
        padding: 40px 24px;
        text-align: center;
        position: relative;
    }

    .profile-avatar {
        width: 90px;
        height: 90px;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-radius: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 800;
        color: white;
        margin: 0 auto 20px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }

    .profile-name {
        color: white;
        font-size: 1.4rem;
        font-weight: 800;
        margin-bottom: 4px;
        letter-spacing: -0.02em;
    }

    .profile-username {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.95rem;
        font-weight: 500;
    }

    .profile-role {
        display: inline-block;
        margin-top: 16px;
        padding: 6px 18px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(5px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 100px;
        color: white;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .profile-stats {
        padding: 24px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        background: var(--card-bg);
    }

    .stat-item {
        text-align: center;
        padding: 16px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        transition: all 0.2s ease;
    }

    .stat-item:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
    }

    .stat-number {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-primary);
        line-height: 1;
    }

    .stat-label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-top: 6px;
        font-weight: 600;
    }

    .profile-info {
        padding: 24px;
        border-top: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .info-icon {
        width: 40px;
        height: 40px;
        background: var(--primary-light);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        font-size: 1.1rem;
    }

    .info-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .info-value {
        color: var(--text-primary);
        font-weight: 600;
        font-size: 0.95rem;
    }

    /* Activity Panel */
    .activity-panel {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        box-shadow: var(--card-shadow);
        display: flex;
        flex-direction: column;
        min-height: 600px;
    }

    .activity-tabs {
        display: flex;
        padding: 8px;
        background: var(--bg-surface);
        border-radius: 24px 24px 0 0;
        border-bottom: 1px solid var(--border-color);
        gap: 8px;
    }

    .activity-tab {
        flex: 1;
        padding: 14px 20px;
        border-radius: 16px;
        font-weight: 700;
        color: var(--text-secondary);
        background: transparent;
        border: none;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-size: 0.95rem;
    }

    .activity-tab:hover {
        color: var(--text-primary);
        background: var(--bg-surface-hover);
    }

    .activity-tab.active {
        color: white;
        background: var(--primary-color);
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }

    .tab-content {
        padding: 24px;
        flex: 1;
    }

    .tab-pane {
        display: none;
        animation: ellaFadeIn 0.3s ease-out;
    }

    .tab-pane.active {
        display: block;
    }

    /* Session List */
    .session-list {
        display: grid;
        gap: 12px;
    }

    .session-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 18px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        transition: all 0.2s ease;
    }

    .session-item:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
        background: var(--bg-surface-hover);
        box-shadow: var(--card-shadow);
    }

    .session-icon {
        width: 48px;
        height: 48px;
        background: var(--card-bg);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        font-size: 1.2rem;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    .session-date {
        font-weight: 700;
        color: var(--text-primary);
        font-size: 1rem;
    }

    .session-ip {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-top: 2px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .session-status {
        padding: 6px 14px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .session-status.active {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .session-status.ended {
        background: var(--bg-surface-hover);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
    }

    /* Sale Item Styling */
    .sale-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 18px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        transition: all 0.2s ease;
        margin-bottom: 12px;
    }

    .sale-item:hover {
        border-color: var(--primary-color);
        background: var(--bg-surface-hover);
        transform: translateY(-2px);
    }

    .sale-icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #10b981, #059669);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }

    .sale-ref {
        font-weight: 700;
        color: var(--text-primary);
        font-size: 1rem;
    }

    .sale-date {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-top: 2px;
    }

    .sale-amount {
        font-size: 1.15rem;
        font-weight: 800;
        color: #10b981;
    }

    /* Filters */
    .sales-filter-bar {
        background: var(--bg-surface);
        padding: 20px;
        border-radius: 18px;
        border: 1px solid var(--border-color);
        margin-bottom: 24px;
    }

    .filter-label {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
        display: block;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 80px 24px;
    }

    .empty-state-icon {
        font-size: 4rem;
        background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
        background-clip: text;
        -webkit-background-clip: text;
        color: transparent;
        -webkit-text-fill-color: transparent;
        margin-bottom: 24px;
        opacity: 0.4;
    }

    .empty-state h5 {
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 8px;
    }

    .empty-state p {
        color: var(--text-secondary);
        max-width: 300px;
        margin: 0 auto;
    }

    /* Responsive adjustments */
    @media (max-width: 1200px) {
        .activity-content {
            grid-template-columns: 280px 1fr;
        }
    }

    @media (max-width: 992px) {
        .activity-content {
            grid-template-columns: 1fr;
        }

        .profile-card {
            position: static;
        }

        .profile-stats {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    @media (max-width: 768px) {
        .profile-stats {
            grid-template-columns: 1fr 1fr;
        }

        .activity-header .d-flex {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 16px;
        }

        .back-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="activity-page px-md-2">
    <!-- Header -->
    <div class="activity-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-1 fw-bold text-primary">
                    <i class="fa-solid fa-clock-rotate-left me-2"></i>Activity Log
                </h3>
                <div class="text-secondary">Comprehensive history for <span
                        class="fw-bold text-primary"><?= htmlspecialchars($user['full_name']) ?></span></div>
            </div>
            <a href="index.php" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Users</span>
            </a>
        </div>
    </div>

    <!-- Content -->
    <div class="activity-content">
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
                <div class="profile-name"><?= htmlspecialchars($user['full_name']) ?></div>
                <div class="profile-username">@<?= htmlspecialchars($user['username']) ?></div>
                <div class="profile-role"><?= htmlspecialchars($user['role']) ?></div>
            </div>

            <div class="profile-stats">
                <div class="stat-item tooltip-trigger" title="Total log-in attempts recorded">
                    <div class="stat-number"><?= $totalSessions ?></div>
                    <div class="stat-label">Total Logins</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $activeSessions ?></div>
                    <div class="stat-label">Active Now</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="stat-sales">-</div>
                    <div class="stat-label">Sales Made</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="stat-revenue">₱-</div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>

            <div class="profile-info">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div>
                        <div class="info-label">Member Since</div>
                        <div class="info-value"><?= date('F j, Y', strtotime($user['created_at'])) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <div class="info-label">Current Role</div>
                        <div class="info-value text-capitalize"><?= htmlspecialchars($user['role']) ?></div>
                    </div>
                </div>
                <?php if ($user['status'] ?? false): ?>
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div>
                            <div class="info-label">Account Status</div>
                            <div class="info-value"><span class="badge bg-success">Active</span></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity Panel -->
        <div class="activity-panel">
            <div class="activity-tabs">
                <button class="activity-tab active" data-tab="sessions">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Login History</span>
                </button>
                <button class="activity-tab" data-tab="sales">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Sales Record</span>
                </button>
            </div>

            <div class="tab-content">
                <!-- Sessions Tab -->
                <div class="tab-pane active" id="sessionsTab">
                    <?php if (count($sessions) > 0): ?>
                        <div class="session-list">
                            <?php foreach ($sessions as $s): ?>
                                <div class="session-item">
                                    <div class="session-icon">
                                        <i class="fa-solid fa-shield"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="session-date"><?= date('M d, Y • h:i:s A', strtotime($s['login_time'])) ?>
                                        </div>
                                        <div class="session-ip">
                                            <i class="fa-solid fa-globe"></i>
                                            <span><?= htmlspecialchars($s['ip_address']) ?></span>
                                            <span class="mx-1">•</span>
                                            <span>Regular Login</span>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($s['is_active']): ?>
                                            <span class="session-status active">
                                                <i class="fa-solid fa-circle fa-beat-fade fa-xs me-1"></i>Active
                                            </span>
                                        <?php else: ?>
                                            <span class="session-status ended">Ended</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <h5>No Login Sessions</h5>
                            <p>We couldn't find any login records for this account yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sales Tab -->
                <div class="tab-pane" id="salesTab">
                    <!-- Date Filter -->
                    <div class="sales-filter-bar">
                        <form id="sales-filter-form" class="row g-3 align-items-end">
                            <div class="col-12 col-sm-4 col-md-3">
                                <label class="filter-label">From Date</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fa-solid fa-calendar"></i></span>
                                    <input type="date" id="sales-date-from" class="form-control" value="<?= date('Y-m-01') ?>">
                                </div>
                            </div>
                            <div class="col-12 col-sm-4 col-md-3">
                                <label class="filter-label">To Date</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fa-solid fa-calendar"></i></span>
                                    <input type="date" id="sales-date-to" class="form-control" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                            <div class="col-12 col-sm-4 col-md-4">
                                <label class="filter-label">Payment Method</label>
                                <select id="sales-method" class="form-select form-select-sm">
                                    <option value="all">All Methods</option>
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                    <option value="bank">Bank Transfer</option>
                                    <option value="financing">Financing</option>
                                    <option value="mix">Mix Payment</option>
                                    <option value="pay_later">Pay Later</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-12 col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100 py-2">
                                    <i class="fa-solid fa-magnifying-glass me-1"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Sales List Container -->
                    <div id="sales-list-container">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="text-secondary mt-3">Fetching sales history...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const USER_ID = <?= $user_id_js ?>;

    document.addEventListener('DOMContentLoaded', function () {
        const tabs = document.querySelectorAll('.activity-tab');
        const panes = document.querySelectorAll('.tab-pane');

        tabs.forEach(tab => {
            tab.addEventListener('click', function () {
                const targetTab = this.dataset.tab;

                // Update active tab
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                // Update active pane
                panes.forEach(p => p.classList.remove('active'));
                document.getElementById(targetTab + 'Tab').classList.add('active');

                // Load sales if switching to sales tab
                if (targetTab === 'sales') {
                    loadSales();
                }
            });
        });

        // Sales filter form
        document.getElementById('sales-filter-form').addEventListener('submit', function (e) {
            e.preventDefault();
            loadSales();
        });

        // Initial load
        loadSales();
    });

    async function loadSales() {
        const container = document.getElementById('sales-list-container');
        const dateFrom = document.getElementById('sales-date-from').value;
        const dateTo = document.getElementById('sales-date-to').value;
        const method = document.getElementById('sales-method').value;

        container.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2 small">Loading sales...</p>
            </div>
        `;

        try {
            const params = new URLSearchParams({
                user_id: USER_ID,
                date_from: dateFrom,
                date_to: dateTo,
                method: method
            });

            const res = await fetch(`../../api/users/get_user_sales.php?${params}`);
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to load');
            }

            // Update stats with animation if possible
            document.getElementById('stat-sales').textContent = data.stats.count || 0;
            document.getElementById('stat-revenue').textContent = '₱' + parseFloat(data.stats.total || 0).toLocaleString(undefined, {
                minimumFractionDigits: 0
            });

            if (!data.sales || data.sales.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <h5>No Sales Found</h5>
                        <p>No sales recorded for the selected date range (${dateFrom} to ${dateTo}).</p>
                    </div>
                `;
                return;
            }

            // Render sales
            container.innerHTML = data.sales.map(sale => {
                const isVoided = sale.status === 'voided';
                const isFilteredMethod = (method === 'cash' || method === 'gcash' || method === 'bank');
                let displayAmount = parseFloat(sale.grand_total);
                let amountLabel = '';

                if (isFilteredMethod && sale.filtered_method_amount !== undefined && sale.filtered_method_amount !== null) {
                    displayAmount = parseFloat(sale.filtered_method_amount);
                    amountLabel = `<div style="font-size: 0.65rem; color: var(--text-secondary); text-transform: uppercase; margin-top: 2px;">Method Portion</div>`;
                }

                return `
                <div class="sale-item">
                    <div class="sale-icon" style="${isVoided ? 'background: linear-gradient(135deg, #ef4444, #dc2626);' : ''}">
                        <i class="fa-solid ${isVoided ? 'fa-ban' : 'fa-file-invoice-dollar'}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="sale-ref d-flex align-items-center gap-2">
                            <a href="../pos/receipts.php?search=${encodeURIComponent(sale.sale_ref)}&view_id=${sale.sale_id}" class="text-decoration-none text-primary" target="_blank" title="View Transaction Receipt">
                                ${sale.sale_ref} <i class="fa-solid fa-arrow-up-right-from-square fa-xs ms-1"></i>
                            </a>
                            ${isVoided ? '<span class="badge bg-danger" style="font-size: 0.6rem;">VOIDED</span>' : ''}
                        </div>
                        <div class="sale-date">
                            <i class="fa-regular fa-clock me-1 text-secondary"></i>
                            ${formatDate(sale.created_at)}
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="sale-amount ${isVoided ? 'text-danger text-decoration-line-through opacity-75' : ''}">
                            ₱${displayAmount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                        </div>
                        ${amountLabel}
                    </div>
                </div>
            `}).join('');

        } catch (err) {
            console.error('Load sales error:', err);
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-exclamation-circle text-danger"></i>
                    <h5>Error Loading Sales</h5>
                    <p>${err.message}</p>
                </div>
            `;
        }
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        }) +
            ' • ' + d.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>