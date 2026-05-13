<?php
// views/users/index.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check - Admin Only
requireLogin();
requirePermission('manage_users'); // Allows admins and custom roles with this permission

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Fetch Users + Get Last Login from Sessions table
$sql = "
    SELECT u.*, 
           (SELECT MAX(login_time) FROM user_sessions WHERE user_id = u.id) as last_login_time,
           (SELECT is_active FROM user_sessions WHERE user_id = u.id ORDER BY last_activity DESC LIMIT 1) as is_online
    FROM users u 
    ORDER BY u.created_at DESC
";
$users = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Count users by status
$statusCounts = ['active' => 0, 'inactive' => 0];
foreach ($users as $u) {
    $s = $u['status'] ?? 'active';
    if (isset($statusCounts[$s])) {
        $statusCounts[$s]++;
    }
}

// Count users by role (Only Active ones for counts)
$roleCounts = ['all' => 0, 'admin' => 0, 'manager' => 0, 'cashier' => 0, 'stockman' => 0];
foreach ($users as $u) {
    $s = $u['status'] ?? 'active';
    if ($s === 'active') {
        $roleCounts['all']++;
        if (isset($roleCounts[$u['role']])) {
            $roleCounts[$u['role']]++;
        }
    }
}
?>

<style>
    /* Users Page - Modernized & Theme Aware */
    .users-page {
        display: flex;
        flex-direction: column;
        gap: 24px;
        animation: ellaFadeIn 0.4s ease-out;
    }

    .users-header {
        margin-bottom: 8px;
    }

    .search-filter-bar {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        padding: 12px;
        box-shadow: var(--card-shadow);
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        transition: all 0.3s ease;
    }

    .search-filter-bar:focus-within {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px var(--primary-light);
    }

    .search-box {
        flex: 1;
        min-width: 250px;
        position: relative;
    }

    .search-box input {
        width: 100%;
        padding: 12px 16px 12px 48px;
        border: 1px solid transparent;
        border-radius: 18px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: var(--card-bg);
        color: var(--text-primary);
        font-weight: 500;
    }

    .search-box input:focus {
        outline: none;
        background: var(--bg-surface);
    }

    .search-box i {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 1.1rem;
    }

    .role-pills {
        display: flex;
        gap: 8px;
        background: var(--card-bg);
        padding: 6px;
        border-radius: 20px;
    }

    .role-pill {
        padding: 8px 16px;
        border-radius: 14px;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        color: var(--text-secondary);
    }

    .role-pill:hover {
        color: var(--text-primary);
        background: var(--bg-surface-hover, rgba(0, 0, 0, 0.02));
    }

    .role-pill.active {
        background: var(--bg-surface);
        color: var(--primary-color);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .status-badge {
        font-size: 0.65rem;
        padding: 2px 8px;
        border-radius: 6px;
        font-weight: 800;
        text-transform: uppercase;
        margin-left: 8px;
    }

    .status-badge.inactive {
        background: #fee2e2;
        color: #ef4444;
    }

    .status-filter {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 4px;
        display: flex;
        gap: 4px;
    }

    .status-btn {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        color: var(--text-secondary);
        transition: all 0.2s;
    }

    .status-btn.active {
        background: var(--bg-surface-hover);
        color: var(--text-primary);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .user-card.inactive-user {
        opacity: 0.8;
        background: #fdfdfd;
    }

    .user-card.inactive-user .user-avatar {
        filter: grayscale(1);
        opacity: 0.7;
    }

    .user-card.inactive-user .user-name {
        color: var(--text-secondary);
    }

    /* Users Grid */
    .users-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 24px;
        padding-bottom: 40px;
    }

    .user-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        box-shadow: var(--card-shadow);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .user-card:hover {
        transform: translateY(-4px);
        border-color: var(--primary-color);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
    }

    .user-card.expanded {
        border-color: var(--primary-color);
        box-shadow: 0 15px 40px var(--primary-light);
    }

    .user-card-main {
        padding: 24px;
    }

    .user-card-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 20px;
    }

    .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.5rem;
        color: white;
        position: relative;
        flex-shrink: 0;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }

    .user-avatar.admin {
        background: linear-gradient(135deg, #ef4444, #b91c1c);
    }

    .user-avatar.manager {
        background: linear-gradient(135deg, #6366f1, #4338ca);
    }

    .user-avatar.cashier {
        background: linear-gradient(135deg, #06b6d4, #0e7490);
    }

    .user-avatar.stockman {
        background: linear-gradient(135deg, #f59e0b, #b45309);
    }

    .online-indicator {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 18px;
        height: 18px;
        background: #10b981;
        border: 4px solid var(--card-bg);
        border-radius: 50%;
    }

    .online-indicator::after {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: 50%;
        border: 2px solid #10b981;
        animation: pulse-ring 1.5s infinite;
    }

    @keyframes pulse-ring {
        0% {
            transform: scale(0.7);
            opacity: 1;
        }

        100% {
            transform: scale(1.6);
            opacity: 0;
        }
    }

    .user-info {
        flex: 1;
        min-width: 0;
    }

    .user-name {
        font-weight: 800;
        font-size: 1.1rem;
        color: var(--text-primary);
        letter-spacing: -0.01em;
        margin-bottom: 2px;
    }

    .user-username {
        font-size: 0.85rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .expand-indicator {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        background: var(--bg-surface);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        transition: all 0.3s ease;
    }

    .user-card.expanded .expand-indicator {
        background: var(--primary-color);
        color: white;
        transform: rotate(180deg);
        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
    }

    .user-card-body {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 18px;
        border-top: 1px solid var(--border-color);
    }

    .role-badge {
        padding: 6px 14px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }

    .role-badge.admin {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
    }

    .role-badge.manager {
        background: rgba(99, 102, 241, 0.15);
        color: #6366f1;
    }

    .role-badge.cashier {
        background: rgba(6, 182, 212, 0.15);
        color: #06b6d4;
    }

    .role-badge.stockman {
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
    }

    .last-login {
        text-align: right;
    }

    .last-login-label {
        color: var(--text-secondary);
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 700;
        margin-bottom: 2px;
    }

    .last-login-time {
        color: var(--text-primary);
        font-weight: 600;
        font-size: 0.8rem;
    }

    /* Action Panel */
    .user-actions-panel {
        max-height: 0;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        background: var(--bg-surface);
        border-top: 1px solid var(--border-color);
        opacity: 0;
    }

    .user-card.expanded .user-actions-panel {
        max-height: 80px;
        opacity: 1;
    }

    .action-buttons {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        padding: 16px 24px;
    }

    .action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px;
        border-radius: 14px;
        font-size: 0.8rem;
        font-weight: 700;
        text-decoration: none !important;
        transition: all 0.25s ease;
        border: none;
        cursor: pointer;
    }

    .action-btn.edit {
        background: var(--primary-light);
        color: var(--primary-color);
    }

    .action-btn.edit:hover {
        background: var(--primary-color);
        color: white;
        transform: translateY(-2px);
    }

    .action-btn.history {
        background: rgba(6, 182, 212, 0.1);
        color: #06b6d4;
    }

    .action-btn.history:hover {
        background: #06b6d4;
        color: white;
        transform: translateY(-2px);
    }

    .action-btn.delete {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .action-btn.delete:hover {
        background: #ef4444;
        color: white;
        transform: translateY(-2px);
    }

    /* Empty States */
    .no-results {
        grid-column: 1 / -1;
        text-align: center;
        padding: 80px 24px;
        background: var(--bg-surface);
        border-radius: 24px;
        border: 2px dashed var(--border-color);
    }

    .no-results i {
        font-size: 4rem;
        background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
        background-clip: text;
        -webkit-background-clip: text;
        color: transparent;
        -webkit-text-fill-color: transparent;
        margin-bottom: 24px;
        opacity: 0.3;
    }

    .no-results h5 {
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 8px;
    }

    .no-results p {
        color: var(--text-secondary);
        max-width: 300px;
        margin: 0 auto;
    }

    @media (max-width: 992px) {
        .users-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .search-filter-bar {
            padding: 16px;
            flex-direction: column;
            align-items: stretch;
        }

        .role-pills {
            overflow-x: auto;
            padding-bottom: 4px;
            border-radius: 12px;
        }

        .role-pill {
            white-space: nowrap;
        }

        .action-btn span {
            display: none;
        }

        .action-buttons {
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding: 12px 16px;
        }
    }
</style>

<div class="users-page px-md-2">
    <!-- Header -->
    <div class="users-header">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-1 fw-bold text-primary">
                    <i class="fa-solid fa-users-gear me-2"></i>User Management
                </h3>
                <div class="text-secondary">Control staff access and system permissions</div>
            </div>
            <a href="add.php" class="btn btn-primary px-4 py-2" style="border-radius: 14px; font-weight: 700;">
                <i class="fa-solid fa-user-plus me-2"></i>Create New User
            </a>
        </div>

        <!-- Search + Filters -->
        <div class="search-filter-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="userSearch" placeholder="Search by name, username, or role...">
            </div>
            <div class="status-filter me-2">
                <div class="status-btn active" data-status="active">Active (<?= $statusCounts['active'] ?>)</div>
                <div class="status-btn" data-status="inactive">Archived (<?= $statusCounts['inactive'] ?>)</div>
            </div>
            <div class="role-pills" id="roleFilterContainer">
                <div class="role-pill active" data-role="all">
                    <span>All Roles</span> <span class="count"><?= $roleCounts['all'] ?></span>
                </div>
                <div class="role-pill" data-role="admin">
                    <span>Admin</span> <span class="count"><?= $roleCounts['admin'] ?></span>
                </div>
                <div class="role-pill" data-role="manager">
                    <span>Manager</span> <span class="count"><?= $roleCounts['manager'] ?></span>
                </div>
                <div class="role-pill" data-role="cashier">
                    <span>Cashier</span> <span class="count"><?= $roleCounts['cashier'] ?></span>
                </div>
                <div class="role-pill" data-role="stockman">
                    <span>Stockman</span> <span class="count"><?= $roleCounts['stockman'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Grid Container -->
    <div id="usersContainer" class="users-grid">
        <?php if (count($users) > 0): ?>
            <?php foreach ($users as $row): ?>
                <?php
                $uid = $row['id'];
                $username = $row['username'];
                $fullname = $row['full_name'];
                $role = $row['role'];
                $last_log = $row['last_login_time'];
                $online = $row['is_online'];
                ?>
                <div class="user-card <?= $row['status'] === 'inactive' ? 'inactive-user' : '' ?>" data-role="<?= $role ?>"
                    data-status="<?= $row['status'] ?>" data-name="<?= strtolower($fullname . ' ' . $username) ?>">
                    <div class="user-card-main">
                        <div class="user-card-header">
                            <div class="user-avatar <?= $role ?>">
                                <?= strtoupper(substr($username, 0, 1)) ?>
                                <?php if ($online == 1 && $row['status'] === 'active'): ?>
                                    <div class="online-indicator" title="Active now"></div>
                                <?php endif; ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name">
                                    <?= htmlspecialchars($fullname) ?>
                                    <?php if ($row['status'] === 'inactive'): ?>
                                        <span class="status-badge inactive">Archived</span>
                                    <?php endif; ?>
                                </div>
                                <div class="user-username">@<?= htmlspecialchars($username) ?></div>
                            </div>
                            <div class="expand-indicator">
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                        </div>
                        <div class="user-card-body">
                            <span class="role-badge <?= $role ?>"><?= ucfirst($role) ?></span>
                            <div class="last-login">
                                <?php if ($last_log): ?>
                                    <div class="last-login-label">Last Activity</div>
                                    <div class="last-login-time"><?= date('M d, h:i A', strtotime($last_log)) ?></div>
                                <?php else: ?>
                                    <div class="last-login-time text-secondary opacity-50">Never Active</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Action Panel -->
                    <div class="user-actions-panel">
                        <div class="action-buttons">
                            <a href="edit.php?id=<?= $uid ?>" class="action-btn edit" title="Edit User">
                                <i class="fa-solid fa-pen-nib"></i>
                                <span>Edit</span>
                            </a>
                            <a href="activity_log.php?id=<?= $uid ?>" class="action-btn history" title="View Logs">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                                <span>Logs</span>
                            </a>
                            <?php if ($row['status'] === 'active'): ?>
                                <a href="../../api/users/delete_user.php?id=<?= $uid ?>" class="action-btn delete"
                                    title="Archive User"
                                    onclick="event.stopPropagation(); return confirm('Archiving this user will prevent them from logging in, but preserve their sales and transaction history. Proceed?')">
                                    <i class="fa-solid fa-box-archive"></i>
                                    <span>Archive</span>
                                </a>
                            <?php else: ?>
                                <a href="../../api/users/activate_user.php?id=<?= $uid ?>" class="action-btn edit"
                                    title="Restore User"
                                    onclick="event.stopPropagation(); return confirm('Restore this user account to active status?')">
                                    <i class="fa-solid fa-rotate-left"></i>
                                    <span>Restore</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-results">
                <i class="fa-solid fa-users-slash"></i>
                <h5>No Users Found</h5>
                <p>Start by adding staff members to the system.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- No results for search -->
    <div id="noSearchResults" style="display: none;">
        <div class="no-results">
            <i class="fa-solid fa-magnifying-glass"></i>
            <h5>Nothing Matches</h5>
            <p>We couldn't find any users meeting your search criteria.</p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('userSearch');
        const rolePills = document.querySelectorAll('.role-pill');
        const userCards = document.querySelectorAll('.user-card');
        const noResults = document.getElementById('noSearchResults');
        const usersContainer = document.getElementById('usersContainer');

        let activeRole = 'all';

        // Click on card to expand/collapse
        userCards.forEach(card => {
            card.addEventListener('click', function (e) {
                // If clicking an action button, don't toggle
                if (e.target.closest('.action-btn')) return;

                const isExpanded = this.classList.contains('expanded');

                // Close other expanded cards
                userCards.forEach(c => c.classList.remove('expanded'));

                // Toggle this card if it wasn't expanded
                if (!isExpanded) {
                    this.classList.add('expanded');
                }
            });
        });

        // Search filter
        searchInput.addEventListener('input', filterUsers);

        // Role filter pills
        rolePills.forEach(pill => {
            pill.addEventListener('click', function () {
                rolePills.forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                activeRole = this.dataset.role;
                filterUsers();
            });
        });

        const statusBtns = document.querySelectorAll('.status-btn');
        let activeStatus = 'active';

        // Status filter
        statusBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                statusBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                activeStatus = this.dataset.status;
                filterUsers();
            });
        });

        function filterUsers() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;

            userCards.forEach(card => {
                const name = card.dataset.name;
                const role = card.dataset.role;
                const status = card.dataset.status;

                const matchesSearch = name.includes(searchTerm) || role.includes(searchTerm);
                const matchesRole = activeRole === 'all' || role === activeRole;
                const matchesStatus = status === activeStatus;

                if (matchesSearch && matchesRole && matchesStatus) {
                    card.style.display = '';
                    visibleCount++;
                    // Basic staggered entrance effect
                    card.style.animation = 'none';
                    card.offsetHeight; // trigger reflow
                    card.style.animation = 'ellaFadeIn 0.3s ease-out forwards';
                } else {
                    card.style.display = 'none';
                    card.classList.remove('expanded');
                }
            });

            // Show/hide no results message
            if (visibleCount === 0) {
                noResults.style.display = 'block';
                usersContainer.style.display = 'none';
            } else {
                noResults.style.display = 'none';
                usersContainer.style.display = 'grid';
            }
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>