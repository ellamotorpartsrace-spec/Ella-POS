<?php
// views/buyers/index.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requirePermission('view_buyers');

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Fetch Buyers with Statistics
$buyers = $conn->query("
    SELECT b.*, 
    (SELECT MAX(created_at) FROM pos_sales WHERE buyer_id = b.buyer_id) as last_sale,
    (SELECT COUNT(*) FROM pos_sales WHERE buyer_id = b.buyer_id) as total_sales,
    COALESCE((SELECT SUM(grand_total) FROM pos_sales WHERE buyer_id = b.buyer_id), 0) as total_spent
    FROM buyers b 
    ORDER BY buyer_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* Buyers Page - Modernized & Theme Aware */
    .buyer-page {
        display: flex;
        flex-direction: column;
        gap: 24px;
        animation: ellaFadeIn 0.4s ease-out;
    }

    .buyer-header {
        margin-bottom: 8px;
    }

    .search-filter-section {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        padding: 16px;
        box-shadow: var(--card-shadow);
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .search-row {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .buyer-search-box {
        flex: 1;
        min-width: 300px;
        position: relative;
    }

    .buyer-search-box input {
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

    .buyer-search-box input:focus {
        outline: none;
        border-color: var(--primary-color);
        background: var(--bg-surface);
        box-shadow: 0 0 0 4px var(--primary-light);
    }

    .buyer-search-box i {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 1.1rem;
    }

    .filter-row {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
    }

    .filter-label {
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-right: 4px;
    }

    .filter-pills {
        display: flex;
        gap: 8px;
        background: var(--card-bg);
        padding: 4px;
        border-radius: 16px;
    }

    .filter-pill {
        padding: 6px 14px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.25s ease;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .filter-pill:hover {
        color: var(--text-primary);
        background: var(--bg-surface-hover);
    }

    .filter-pill.active {
        background: var(--primary-color);
        color: white;
        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2);
    }

    /* Stats Row */
    .buyer-stats {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .stat-badge {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 10px 20px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--text-secondary);
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        transition: all 0.3s ease;
    }

    .stat-badge:hover {
        transform: translateY(-2px);
        border-color: var(--primary-color);
        box-shadow: 0 10px 20px rgba(59, 130, 246, 0.1);
    }

    .stat-badge i {
        color: var(--primary-color);
        font-size: 1.1rem;
        background: var(--primary-light);
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
    }

    .stat-badge strong {
        color: var(--text-primary);
        font-weight: 800;
    }

    /* Grid & Cards - Premium Plus Upgrade */
    .buyer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 32px;
        padding: 40px 0 60px;
    }

    .buyer-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 32px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
        overflow: visible;
        display: flex;
        flex-direction: column;
        z-index: 1;
    }

    .buyer-card::after {
        content: '';
        position: absolute;
        inset: -2px;
        border-radius: 34px;
        background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
        z-index: -1;
        opacity: 0;
        transition: opacity 0.4s ease;
    }

    .buyer-card:hover {
        transform: translateY(-12px);
        border-color: transparent;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.12);
    }

    .buyer-card:hover::after {
        opacity: 0.15;
    }

    /* Loyalty Badges */
    .loyalty-badge {
        position: absolute;
        top: -15px;
        right: 32px;
        padding: 8px 20px;
        border-radius: 14px;
        font-size: 0.75rem;
        font-weight: 900;
        color: white;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        z-index: 10;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .loyalty-badge.vip {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .loyalty-badge.frequent {
        background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    }

    .loyalty-badge.new {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .loyalty-badge i {
        font-size: 0.85rem;
    }

    .buyer-card-header {
        padding: 32px 32px 20px;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .avatar-container {
        position: relative;
    }

    .active-status-dot {
        position: absolute;
        bottom: 2px;
        right: 2px;
        width: 16px;
        height: 16px;
        background: #10b981;
        border: 3.5px solid var(--card-bg);
        border-radius: 50%;
        box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
        z-index: 2;
    }

    .active-status-dot.inactive {
        background: #94a3b8;
        box-shadow: none;
    }

    .buyer-avatar {
        width: 68px;
        height: 68px;
        border-radius: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 1.8rem;
        flex-shrink: 0;
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
    }

    .buyer-avatar.walkin {
        background: linear-gradient(135deg, #64748b, #334155);
    }

    .buyer-avatar.retail {
        background: linear-gradient(135deg, #10b981, #065f46);
    }

    .buyer-avatar.wholesale {
        background: linear-gradient(135deg, #3b82f6, #1e40af);
    }

    .buyer-avatar.dealer {
        background: linear-gradient(135deg, #f59e0b, #9a3412);
    }

    .buyer-badges {
        display: flex;
        gap: 10px;
        padding: 0 32px 20px;
    }

    .tier-badge {
        padding: 6px 14px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 1px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .tier-badge.retail {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .tier-badge.wholesale {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }

    .tier-badge.dealer {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .type-badge {
        padding: 6px 14px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 900;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .buyer-card-body {
        padding: 0 32px 32px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        flex: 1;
    }

    /* Stats Grid Custom */
    .buyer-stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        padding: 20px;
        background: var(--bg-surface);
        border-radius: 24px;
        border: 1px solid var(--border-color);
        margin-bottom: 4px;
    }

    .stat-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .stat-label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }

    .stat-value {
        font-size: 1rem;
        font-weight: 900;
        color: var(--text-primary);
    }

    .stat-value.currency {
        color: var(--primary-color);
    }

    .last-purchase-info {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-weight: 600;
    }

    .last-purchase-info i {
        color: var(--primary-color);
        opacity: 0.6;
    }

    .info-item {
        display: flex;
        gap: 14px;
        font-size: 0.95rem;
        line-height: 1.5;
    }

    .info-item i {
        width: 38px;
        height: 38px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        flex-shrink: 0;
        font-size: 0.9rem;
    }

    .info-item span {
        color: var(--text-secondary);
        font-weight: 500;
        padding-top: 8px;
    }

    .info-item a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 700;
    }

    .info-item a:hover {
        text-decoration: underline;
    }

    .buyer-card-footer {
        padding: 24px 32px;
        background: var(--bg-surface);
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        border-top: 1px solid var(--border-color);
        border-bottom-left-radius: 32px;
        border-bottom-right-radius: 32px;
    }

    .action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 10px 5px;
        border-radius: 14px;
        font-size: 0.65rem;
        font-weight: 700;
        text-decoration: none !important;
        transition: all 0.25s ease;
        border: none;
    }

    .action-btn.ledger {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }

    .action-btn.ledger:hover {
        background: #3b82f6;
        color: white;
        transform: translateY(-2px);
    }

    .action-btn.transactions {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }

    .action-btn.transactions:hover {
        background: #10b981;
        color: white;
        transform: translateY(-2px);
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
    .buyer-empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 80px 24px;
        background: var(--bg-surface);
        border-radius: 24px;
        border: 2px dashed var(--border-color);
    }

    .buyer-empty-state i {
        font-size: 4rem;
        background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
        background-clip: text;
        -webkit-background-clip: text;
        color: transparent;
        -webkit-text-fill-color: transparent;
        margin-bottom: 24px;
        opacity: 0.3;
    }

    .buyer-empty-state h5 {
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 8px;
    }

    .buyer-empty-state p {
        color: var(--text-secondary);
        margin-bottom: 24px;
    }

    @media (max-width: 768px) {
        .search-filter-section {
            border-radius: 16px;
        }

        .buyer-grid {
            grid-template-columns: 1fr;
        }

        .buyer-card-footer span {
            display: none;
        }
    }
</style>

<div class="buyer-page px-md-2">
    <!-- Header Section -->
    <div class="buyer-header">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-1 fw-bold text-primary">
                    <i class="fa-solid fa-address-book me-2"></i>Buyers & Customers
                </h3>
                <div class="text-secondary">Manage customer interactions and price tiers</div>
            </div>
            <div class="d-flex gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-primary px-4 py-2 dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" style="border-radius: 14px; font-weight: 700;">
                        <i class="fa-solid fa-file-csv me-2"></i>CSV Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end p-2 border-0 shadow-lg" style="border-radius: 16px;">
                        <li>
                            <a class="dropdown-item py-2" href="../../api/buyers/export_csv.php"
                                style="border-radius: 8px;">
                                <i class="fa-solid fa-file-export me-2 text-primary"></i>Export to CSV
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="../../api/buyers/download_template.php"
                                style="border-radius: 8px;">
                                <i class="fa-solid fa-file-arrow-down me-2 text-success"></i>Download Template
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <button class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#importModal"
                                style="border-radius: 8px;">
                                <i class="fa-solid fa-file-import me-2 text-warning"></i>Import / Update CSV
                            </button>
                        </li>
                    </ul>
                </div>
                <a href="create.php" class="btn btn-primary px-4 py-2" style="border-radius: 14px; font-weight: 700;">
                    <i class="fa-solid fa-user-plus me-2"></i>Add Buyer
                </a>
            </div>
        </div>

        <!-- Search + Filters -->
        <div class="search-filter-section">
            <div class="search-row">
                <div class="buyer-search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="buyerSearch" placeholder="Search by name, shop, or location address...">
                </div>
            </div>
            <div class="filter-row">
                <div>
                    <span class="filter-label">Buyer Tier</span>
                    <div class="filter-pills" id="tierFilterContainer">
                        <div class="filter-pill active" data-tier="all">
                            <span>All</span>
                        </div>
                        <div class="filter-pill" data-tier="retail">
                            <i class="fa-solid fa-tag"></i> <span>Retail</span>
                        </div>
                        <div class="filter-pill" data-tier="wholesale">
                            <i class="fa-solid fa-boxes-stacked"></i> <span>Wholesale</span>
                        </div>
                        <div class="filter-pill" data-tier="dealer">
                            <i class="fa-solid fa-handshake"></i> <span>Dealer</span>
                        </div>
                    </div>
                </div>
                <div class="ms-md-4">
                    <span class="filter-label">Type</span>
                    <div class="filter-pills" id="typeFilterContainer">
                        <div class="filter-pill active" data-type="all"><span>All</span></div>
                        <div class="filter-pill" data-type="account"><span>Account</span></div>
                        <div class="filter-pill" data-type="walkin"><span>Walk-in</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Alert -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-check-circle me-2"></i>
            <strong>Success!</strong> <?= htmlspecialchars($_GET['msg'] ?? 'Operation completed successfully.') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <strong>Error!</strong> <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Bar -->
    <div class="buyer-stats mb-4">
        <div class="stat-badge">
            <i class="fa-solid fa-user-group"></i>
            <span>Total: <strong><?= count($buyers) ?></strong></span>
        </div>
        <div class="stat-badge">
            <i class="fa-solid fa-id-card"></i>
            <span>Accounts: <strong><?= count(array_filter($buyers, fn($b) => !$b['is_walkin'])) ?></strong></span>
        </div>
        <div class="stat-badge">
            <i class="fa-solid fa-person-walking"></i>
            <span>Walk-ins: <strong><?= count(array_filter($buyers, fn($b) => $b['is_walkin'])) ?></strong></span>
        </div>
    </div>

    <!-- Buyer Cards Grid - Premium Plus UPGRADE -->
    <div class="buyer-grid" id="buyerGrid">
        <?php if (count($buyers) > 0): ?>
            <?php foreach ($buyers as $row): ?>
                <?php
                // Data Processing for Premium Features
                $totalSpent = floatval($row['total_spent']);
                $totalSales = intval($row['total_sales']);
                $lastSale = $row['last_sale'];

                // Loyalty Logic
                $isVIP = ($totalSpent >= 20000 || $totalSales >= 15);
                $isFrequent = (!$isVIP && $totalSales >= 5);
                $isNew = ($totalSales <= 1);

                // Activity Status
                $isActive = false;
                $lastSaleFormatted = 'No transactions';
                if ($lastSale) {
                    $lastSaleDate = new DateTime($lastSale);
                    $now = new DateTime();
                    $interval = $now->diff($lastSaleDate);
                    $isActive = ($interval->days <= 30);

                    if ($interval->days == 0)
                        $lastSaleFormatted = 'Today';
                    elseif ($interval->days == 1)
                        $lastSaleFormatted = 'Yesterday';
                    elseif ($interval->days < 7)
                        $lastSaleFormatted = $interval->days . ' days ago';
                    else
                        $lastSaleFormatted = $lastSaleDate->format('d M Y');
                }

                // Avatar logic
                $avatarChar = strtoupper(substr($row['buyer_name'], 0, 1));
                $avatarClass = strtolower($row['price_tier']);
                if ($row['is_walkin'])
                    $avatarClass = 'walkin';
                ?>

                <div class="buyer-card" data-name="<?= strtolower(htmlspecialchars($row['buyer_name'])) ?>"
                    data-shop="<?= strtolower(htmlspecialchars($row['shop_name'] ?? '')) ?>"
                    data-address="<?= strtolower(htmlspecialchars($row['address'] ?? '')) ?>"
                    data-tier="<?= $row['price_tier'] ?>" data-type="<?= $row['is_walkin'] ? 'walkin' : 'account' ?>">

                    <!-- Loyalty Badge -->
                    <?php if ($isVIP): ?>
                        <div class="loyalty-badge vip"><i class="fa-solid fa-crown"></i> VIP</div>
                    <?php elseif ($isFrequent): ?>
                        <div class="loyalty-badge frequent"><i class="fa-solid fa-fire"></i> Frequent</div>
                    <?php elseif ($isNew && $totalSales > 0): ?>
                        <div class="loyalty-badge new">New Guest</div>
                    <?php endif; ?>

                    <div class="buyer-card-header">
                        <div class="avatar-container">
                            <div class="buyer-avatar <?= $avatarClass ?>"><?= $avatarChar ?></div>
                            <div class="active-status-dot <?= $isActive ? '' : 'inactive' ?>"
                                title="<?= $isActive ? 'Active customer' : 'Inactive' ?>"></div>
                        </div>
                        <div class="buyer-main-info">
                            <h5 class="buyer-name" title="<?= htmlspecialchars($row['buyer_name']) ?>">
                                <?= htmlspecialchars($row['buyer_name']) ?>
                            </h5>
                            <?php if (!empty($row['shop_name'])): ?>
                                <span class="buyer-shop">
                                    <i class="fa-solid fa-store"></i><?= htmlspecialchars($row['shop_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="buyer-shop opacity-50">
                                    <i class="fa-solid fa-user"></i>Personal
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="buyer-badges">
                        <span class="tier-badge <?= $row['price_tier'] ?>">
                            <i
                                class="fa-solid <?= $row['price_tier'] == 'retail' ? 'fa-tag' : ($row['price_tier'] == 'wholesale' ? 'fa-boxes-stacked' : 'fa-handshake') ?>"></i>
                            <?= ucfirst($row['price_tier']) ?>
                        </span>
                        <span class="type-badge"><?= $row['is_walkin'] ? 'Walk-in' : 'Account' ?></span>
                    </div>

                    <div class="buyer-card-body">
                        <!-- Stats Grid -->
                        <div class="buyer-stats-grid">
                            <div class="stat-item">
                                <span class="stat-label">Total Spent</span>
                                <span class="stat-value currency">₱<?= number_format($totalSpent, 0) ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Sale Count</span>
                                <span class="stat-value"><?= $totalSales ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Credit Limit</span>
                                <span class="stat-value text-<?= $row['credit_limit'] ? 'primary' : 'muted' ?>"
                                    style="font-size: 0.8rem;">
                                    <?= $row['credit_limit'] ? '₱' . number_format($row['credit_limit'], 0) : 'No Limit' ?>
                                </span>
                            </div>
                        </div>

                        <div class="last-purchase-info mb-3">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <span>Last Active: <strong><?= $lastSaleFormatted ?></strong></span>
                        </div>

                        <div class="info-item">
                            <i class="fa-solid fa-phone"></i>
                            <span>
                                <?php if (!empty($row['contact_number'])): ?>
                                    <a
                                        href="tel:<?= htmlspecialchars($row['contact_number']) ?>"><?= htmlspecialchars($row['contact_number']) ?></a>
                                <?php else: ?>
                                    <span class="opacity-50">No phone contact</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="info-item">
                            <i class="fa-solid fa-location-dot"></i>
                            <span>
                                <?php if (!empty($row['address'])): ?>
                                    <?= htmlspecialchars($row['address']) ?>
                                <?php else: ?>
                                    <span class="opacity-50">No address recorded</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <div class="buyer-card-footer">
                        <a href="transactions.php?id=<?= $row['buyer_id'] ?>" class="action-btn transactions"
                            title="View History">
                            <i class="fa-solid fa-receipt"></i>
                            <span>Sales</span>
                        </a>
                        <a href="../receivables/ledger.php?buyer_id=<?= $row['buyer_id'] ?>" class="action-btn ledger"
                            title="Account Ledger">
                            <i class="fa-solid fa-book-bookmark"></i>
                            <span>Ledger</span>
                        </a>
                        <a href="edit.php?id=<?= $row['buyer_id'] ?>" class="action-btn edit" title="Edit Details">
                            <i class="fa-solid fa-user-pen"></i>
                            <span>Edit</span>
                        </a>
                        <a href="../../api/buyers/delete_buyer.php?id=<?= $row['buyer_id'] ?>" class="action-btn delete"
                            title="Delete Buyer" onclick="return confirm('Are you sure you want to delete this buyer?');">
                            <i class="fa-solid fa-trash-can"></i>
                            <span>Delete</span>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="buyer-empty-state">
                <i class="fa-solid fa-users-slash"></i>
                <h5>No Buyers Linked</h5>
                <p>You haven't added any customers to your database yet.</p>
                <a href="create.php" class="btn btn-primary px-4 py-2" style="border-radius: 12px;">
                    <i class="fa-solid fa-plus me-2"></i>Add First Buyer
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- No results for search -->
    <div id="noSearchResults" style="display: none;">
        <div class="buyer-empty-state">
            <i class="fa-solid fa-magnifying-glass"></i>
            <h5>Nothing Matches</h5>
            <p>We couldn't find any buyers meeting your search criteria.</p>
        </div>
    </div>
</div>
<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
            <form action="../../api/buyers/process_import.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header border-0 bg-primary text-white p-4">
                    <h5 class="modal-title fw-bold" id="importModalLabel">
                        <i class="fa-solid fa-file-import me-2"></i>Import / Update Buyers
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <i class="fa-solid fa-file-csv fa-3x text-primary opacity-25 mb-3"></i>
                        <p class="text-secondary small">
                            Upload a CSV file to bulk add or update buyers.
                            <br>Include <strong>Buyer ID</strong> to update existing records.
                        </p>
                    </div>

                    <div class="mb-4">
                        <label for="csv_file" class="form-label fw-bold small text-uppercase letter-spacing-1">Select
                            CSV File</label>
                        <input type="file" class="form-control rounded-3" id="csv_file" name="csv_file" accept=".csv"
                            required>
                    </div>

                    <div class="bg-light p-3 rounded-4 mb-3 border border-dashed border-primary border-opacity-25">
                        <h6 class="fw-bold small mb-2 text-primary">Instructions:</h6>
                        <ul class="small text-secondary mb-0 ps-3">
                            <li>Use the provided template for best results.</li>
                            <li><strong>Buyer Name</strong> is required.</li>
                            <li><strong>Price Tier</strong> must be: <code>retail</code>, <code>wholesale</code>, or
                                <code>dealer</code>.
                            </li>
                            <li><strong>Is Walk-in</strong>: <code>1</code> for yes, <code>0</code> for no.</li>
                        </ul>
                    </div>

                    <div class="d-flex justify-content-center">
                        <a href="../../api/buyers/download_template.php"
                            class="btn btn-link btn-sm text-decoration-none fw-bold">
                            <i class="fa-solid fa-download me-1"></i>Download Template First
                        </a>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light px-4 py-2 fw-bold" data-bs-dismiss="modal"
                        style="border-radius: 12px;">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-bold" style="border-radius: 12px;">
                        <i class="fa-solid fa-upload me-2"></i>Process Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('buyerSearch');
        const tierPills = document.querySelectorAll('#tierFilterContainer .filter-pill');
        const typePills = document.querySelectorAll('#typeFilterContainer .filter-pill');
        const buyerCards = document.querySelectorAll('.buyer-card');
        const noResults = document.getElementById('noSearchResults');
        const grid = document.getElementById('buyerGrid');

        let activeTier = 'all';
        let activeType = 'all';

        // Search filter
        searchInput.addEventListener('input', filterBuyers);

        // Tier filter pills
        tierPills.forEach(pill => {
            pill.addEventListener('click', function () {
                tierPills.forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                activeTier = this.dataset.tier;
                filterBuyers();
            });
        });

        // Type filter pills
        typePills.forEach(pill => {
            pill.addEventListener('click', function () {
                typePills.forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                activeType = this.dataset.type;
                filterBuyers();
            });
        });

        function filterBuyers() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const safeQuery = searchTerm ? searchTerm.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&').split(/\\s+/).filter(Boolean) : [];
            
            const highlightDOM = (container, text) => {
                const div = document.createElement('div');
                div.textContent = text;
                let hlText = div.innerHTML;

                if (safeQuery.length > 0) {
                    safeQuery.forEach(q => {
                        const regex = new RegExp(`(${q})`, 'gi');
                        hlText = hlText.replace(regex, '<mark class="bg-warning bg-opacity-50 p-0 rounded text-dark">$1</mark>');
                    });
                }
                container.innerHTML = hlText;
            };

            let visibleCount = 0;

            buyerCards.forEach(card => {
                const name = card.dataset.name;
                const shop = card.dataset.shop;
                const address = card.dataset.address;
                const tier = card.dataset.tier;
                const type = card.dataset.type;

                const nameEl = card.querySelector('.buyer-name');
                const shopEl = card.querySelector('.buyer-shop');
                
                // Get original text on first run, store it
                if (nameEl && !nameEl.hasAttribute('data-original')) {
                    nameEl.setAttribute('data-original', nameEl.textContent.trim());
                }
                
                if (shopEl && !shopEl.hasAttribute('data-original')) {
                    const plainText = shopEl.textContent.trim();
                    // Don't mark "Personal" as something to highlight if it's just a placeholder icon text
                    if (plainText !== 'Personal') {
                        shopEl.setAttribute('data-original', plainText);
                    }
                }

                const matchesSearch = name.includes(searchTerm) || shop.includes(searchTerm) || address.includes(searchTerm);
                const matchesTier = activeTier === 'all' || tier === activeTier;
                const matchesType = activeType === 'all' || type === activeType;

                if (matchesSearch && matchesTier && matchesType) {
                    card.style.display = '';
                    visibleCount++;
                    // Staggered entrance
                    card.style.animation = 'none';
                    card.offsetHeight; // trigger reflow
                    card.style.animation = 'ellaFadeIn 0.3s ease-out forwards';
                    
                    highlightDOM(nameEl, nameEl.getAttribute('data-original'));
                    if (shopEl && shopEl.hasAttribute('data-original')) {
                       const originalShopText = shopEl.getAttribute('data-original');
                       const div = document.createElement('div');
                       div.textContent = originalShopText;
                       let hlText = div.innerHTML;
                       if (safeQuery.length > 0) {
                           safeQuery.forEach(q => {
                               const regex = new RegExp(`(${q})`, 'gi');
                               hlText = hlText.replace(regex, '<mark class="bg-warning bg-opacity-50 p-0 rounded text-dark">$1</mark>');
                           });
                       }
                       shopEl.innerHTML = `<i class="fa-solid fa-store"></i>${hlText}`;
                    }

                } else {
                    card.style.display = 'none';
                }
            });

            // Show/hide no results message
            if (visibleCount === 0) {
                noResults.style.display = 'block';
                grid.style.display = 'none';
            } else {
                noResults.style.display = 'none';
                grid.style.display = 'grid';
            }
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>