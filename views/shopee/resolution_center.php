<?php
// views/shopee/resolution_center.php
$page_title = 'Shopee Sync — Resolution Center';
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    denyAccess("You do not have permission to access Shopee Sync.");
}

$db = new Database();
$conn = $db->getConnection();

// --- 1. Pagination & Filter Configuration ---
$items_per_page = isset($_GET['limit']) ? filter_var($_GET['limit'], FILTER_VALIDATE_INT) : 10;
if (!$items_per_page || $items_per_page < 1) $items_per_page = 10;

$page_num = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : 1;
if (!$page_num || $page_num < 1) $page_num = 1;

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';

$where_clause = "status = 'open'";
$params = [];

if ($search_query !== '') {
    $where_clause .= " AND (sku LIKE ? OR error_message LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if ($type_filter !== '') {
    $where_clause .= " AND error_type = ?";
    $params[] = $type_filter;
}

// --- 2. Fetch global counts for KPI Cards ---
$statsStmt = $conn->query("SELECT error_type, COUNT(*) as cnt FROM shopee_error_logs WHERE status = 'open' GROUP BY error_type");
$statsRows = $statsStmt->fetchAll();

$missingCount = 0;
$otherCount = 0;

foreach ($statsRows as $row) {
    if ($row['error_type'] === 'missing_sku') {
        $missingCount += $row['cnt'];
    } elseif ($row['error_type'] !== 'duplicate_sku') {
        $otherCount += $row['cnt'];
    }
}

// Calculate unique duplicate SKU count globally
$dupStmt = $conn->query("SELECT COUNT(DISTINCT sku) FROM shopee_error_logs WHERE status = 'open' AND error_type = 'duplicate_sku'");
$duplicateCount = (int)$dupStmt->fetchColumn();

$totalOpen = $missingCount + $duplicateCount + $otherCount;

// Calculate paginated bounds
$countStmt = $conn->prepare("SELECT COUNT(*) FROM shopee_error_logs WHERE $where_clause");
$countStmt->execute($params);
$total_filtered = $countStmt->fetchColumn();

$total_pages = ceil($total_filtered / $items_per_page);
if ($total_pages < 1) $total_pages = 1;
if ($page_num > $total_pages) $page_num = $total_pages;

$offset = ($page_num - 1) * $items_per_page;

// Fetch filtered and paginated open errors with mapping details
$query = "
    SELECT e.*, 
           m.shopee_product_name, 
           m.shopee_variation_name
    FROM shopee_error_logs e
    LEFT JOIN shopee_product_mappings m 
      ON e.shopee_item_id = m.shopee_item_id 
      AND (
          (e.shopee_model_id IS NOT NULL AND e.shopee_model_id != 0 AND e.shopee_model_id = m.shopee_model_id)
          OR
          ((e.shopee_model_id IS NULL OR e.shopee_model_id = 0) AND (m.shopee_model_id IS NULL OR m.shopee_model_id = 0))
      )
    WHERE e.$where_clause 
    ORDER BY e.created_at DESC 
    LIMIT $items_per_page OFFSET $offset
";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$errors = $stmt->fetchAll();

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shopee-sync.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shopee-sync.css') ?>">
<style>
.style-option-card {
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.style-option-card:hover {
    background: var(--bg-body);
    border-color: var(--shopee-primary) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}
.style-option-card.selected {
    background: var(--shopee-light) !important;
    border-color: var(--shopee-primary) !important;
    box-shadow: 0 4px 16px rgba(238, 77, 45, 0.08);
}
.bg-success-light {
    background-color: rgba(40, 167, 69, 0.08) !important;
}
.bg-info-light {
    background-color: rgba(23, 162, 184, 0.08) !important;
}
.bg-warning-light {
    background-color: rgba(255, 193, 7, 0.08) !important;
}
</style>

<div class="sp-page sp-animate">
    <div class="sp-breadcrumb">
        <a href="<?= BASE_URL ?>views/shopee/index.php">Shopee Sync</a>
        <i class="fa-solid fa-chevron-right" style="font-size:0.6rem"></i>
        <span>Error Resolution Center</span>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="sp-title mb-0"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Error Resolution Center</h1>
            <p class="sp-subtitle mb-0">Detect and fix SKU conflicts, mapping errors, and sync failures</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-shopee" data-bs-toggle="modal" data-bs-target="#scanOptionsModal">
                <i class="fa-solid fa-radar me-2"></i>Scan for Conflicts
            </button>
        </div>
    </div>

    <!-- Premium KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: var(--sp-danger-bg); color: var(--sp-danger);">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <div class="sp-stat-value"><?= $totalOpen ?></div>
                    <div class="sp-stat-label">Total Issues</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: var(--sp-warning-bg); color: var(--sp-warning);">
                    <i class="fa-solid fa-xmark"></i>
                </div>
                <div>
                    <div class="sp-stat-value"><?= $missingCount ?></div>
                    <div class="sp-stat-label">Missing SKUs</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: var(--sp-info-bg); color: var(--sp-info);">
                    <i class="fa-solid fa-clone"></i>
                </div>
                <div>
                    <div class="sp-stat-value"><?= $duplicateCount ?></div>
                    <div class="sp-stat-label">Duplicate SKUs</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: var(--sp-success-bg); color: var(--sp-success);">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="sp-stat-value"><?= $otherCount ?></div>
                    <div class="sp-stat-label">Other Errors</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Toolbar -->
    <div class="sp-card mb-3">
        <div class="sp-card-body py-3">
            <form method="GET" action="" class="row g-2 align-items-center">
                <!-- Search Input -->
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-transparent border-end-0 text-secondary">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by SKU or error details..." value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                </div>
                <!-- Type Filter Dropdown -->
                <div class="col-md-3 col-6">
                    <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Issue Types</option>
                        <option value="missing_sku" <?= $type_filter === 'missing_sku' ? 'selected' : '' ?>>Missing SKU</option>
                        <option value="duplicate_sku" <?= $type_filter === 'duplicate_sku' ? 'selected' : '' ?>>Duplicate SKU</option>
                        <option value="sync_error" <?= $type_filter === 'sync_error' ? 'selected' : '' ?>>Other Sync Errors</option>
                    </select>
                </div>
                <!-- Limit Dropdown -->
                <div class="col-md-2 col-6">
                    <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="2" <?= $items_per_page == 2 ? 'selected' : '' ?>>2 per page</option>
                        <option value="5" <?= $items_per_page == 5 ? 'selected' : '' ?>>5 per page</option>
                        <option value="10" <?= $items_per_page == 10 ? 'selected' : '' ?>>10 per page</option>
                        <option value="25" <?= $items_per_page == 25 ? 'selected' : '' ?>>25 per page</option>
                        <option value="50" <?= $items_per_page == 50 ? 'selected' : '' ?>>50 per page</option>
                    </select>
                </div>
                <!-- Action Buttons -->
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-shopee w-100">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                    <?php if ($search_query !== '' || $type_filter !== ''): ?>
                        <a href="resolution_center.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Card -->
    <div class="sp-card">
        <div class="sp-card-body p-0">
            <div class="table-responsive">
                <table class="table sp-table mb-0">
                    <thead>
                        <tr>
                            <th>Error Type</th>
                            <th>Reference</th>
                            <th>Details</th>
                            <th>Detected On</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($errors)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="sp-empty text-center py-5">
                                        <i class="fa-solid fa-shield-check text-success d-block" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                                        <h5>System Healthy</h5>
                                        <p class="text-secondary mb-0">No open errors matching filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($errors as $e): ?>
                                <tr>
                                    <td>
                                        <?php if ($e['error_type'] === 'missing_sku'): ?>
                                            <?php if (!empty($e['shopee_model_id'])): ?>
                                                <span class="sp-badge sp-badge-danger"><i class="fa-solid fa-tags me-1"></i> Missing Variation SKU</span>
                                            <?php else: ?>
                                                <span class="sp-badge sp-badge-danger"><i class="fa-solid fa-box me-1"></i> Missing Parent SKU</span>
                                            <?php endif; ?>
                                        <?php elseif ($e['error_type'] === 'duplicate_sku'): ?>
                                            <span class="sp-badge sp-badge-warning"><i class="fa-solid fa-clone me-1"></i> Duplicate SKU</span>
                                        <?php else: ?>
                                            <span class="sp-badge sp-badge-info"><?= htmlspecialchars($e['error_type']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($e['error_type'] === 'duplicate_sku'): 
                                            $dupItemsStmt = $conn->prepare("
                                                SELECT shopee_item_id, shopee_model_id, shopee_product_name, shopee_variation_name 
                                                FROM shopee_product_mappings 
                                                WHERE (has_variation = 0 AND shopee_parent_sku = ?)
                                                   OR (has_variation = 1 AND shopee_variation_sku = ?)
                                            ");
                                            $dupItemsStmt->execute([$e['sku'], $e['sku']]);
                                            $dupItems = $dupItemsStmt->fetchAll();
                                        ?>
                                            <div class="d-flex flex-column gap-2">
                                                <div class="d-flex flex-column gap-2 ps-2 border-start border-danger" style="border-width: 3px !important;">
                                                    <?php foreach ($dupItems as $index => $item): ?>
                                                        <div class="<?= $index > 0 ? 'border-top pt-1 mt-1' : '' ?>" style="font-size: 0.82rem;">
                                                            <span class="fw-600 text-dark"><?= htmlspecialchars($item['shopee_product_name']) ?></span>
                                                            <?php if (!empty($item['shopee_variation_name'])): ?>
                                                                <span class="badge bg-light text-muted border py-0 px-1 font-monospace" style="font-size:0.68rem;"><?= htmlspecialchars($item['shopee_variation_name']) ?></span>
                                                            <?php endif; ?>
                                                            <div class="text-secondary font-monospace mt-1" style="font-size: 0.68rem; line-height: 1.2;">
                                                                Item ID: <strong><?= htmlspecialchars($item['shopee_item_id']) ?></strong>
                                                                <?php if (!empty($item['shopee_model_id'])): ?>
                                                                     | Model ID: <strong><?= htmlspecialchars($item['shopee_model_id']) ?></strong>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="fw-600 text-dark small" style="line-height:1.2;"><?= htmlspecialchars($e['shopee_product_name'] ?? 'Unknown Shopee Product') ?></span>
                                                <span class="text-secondary font-monospace" style="font-size: 0.72rem;">
                                                    Item ID: <strong><?= htmlspecialchars($e['shopee_item_id']) ?></strong>
                                                    <?php if (!empty($e['shopee_model_id'])): ?>
                                                        <br>Model ID: <strong><?= htmlspecialchars($e['shopee_model_id']) ?></strong>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1 py-1">
                                            <?php if ($e['error_type'] === 'duplicate_sku'): ?>
                                                <div class="small text-secondary">Conflict Status:</div>
                                                <div class="fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Duplicated SKU</div>
                                                <span class="sp-sku-code text-danger mt-1" style="width: fit-content;"><i class="fa-solid fa-clone me-1"></i><?= htmlspecialchars($e['sku']) ?></span>
                                                <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.3;">Shared by the <?= count($dupItems) ?> listings shown on the left. Click action to resolve.</div>
                                            <?php else: ?>
                                                <?php if (!empty($e['shopee_variation_name'])): ?>
                                                    <div class="small text-secondary">Variation Name:</div>
                                                    <div class="fw-bold text-dark"><i class="fa-solid fa-tags text-shopee me-1"></i><?= htmlspecialchars($e['shopee_variation_name']) ?></div>
                                                <?php else: ?>
                                                    <div class="small text-secondary">Product Type:</div>
                                                    <div class="text-muted small"><i class="fa-solid fa-box text-secondary me-1"></i>Standalone Product</div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="small text-secondary">
                                            <i class="fa-regular fa-clock me-1"></i>
                                            <?= date('M d, g:i A', strtotime($e['created_at'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($e['error_type'] === 'missing_sku'): ?>
                                            <button class="btn btn-sm btn-shopee" onclick="openFixMissingSkuModal(<?= $e['shopee_item_id'] ?>, <?= (int)$e['shopee_model_id'] ?>, '<?= htmlspecialchars(addslashes($e['error_message']), ENT_QUOTES) ?>')">
                                                <i class="fa-solid fa-wrench me-1"></i>Fix SKU
                                            </button>
                                        <?php elseif ($e['error_type'] === 'duplicate_sku'): ?>
                                            <button class="btn btn-sm btn-shopee" onclick="openResolveDuplicateModal('<?= htmlspecialchars(addslashes($e['sku']), ENT_QUOTES) ?>')">
                                                <i class="fa-solid fa-wrench me-1"></i>Resolve Duplicate
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="ignoreError(<?= $e['id'] ?>, this)">
                                                <i class="fa-solid fa-eye-slash me-1"></i>Dismiss
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Premium Pagination Footer -->
        <?php if ($total_filtered > 0): ?>
            <div class="sp-card-footer d-flex justify-content-between align-items-center p-3">
                <div class="small text-secondary">
                    Showing <?= $offset + 1 ?> to <?= min($offset + $items_per_page, $total_filtered) ?> of <?= $total_filtered ?> issues
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0 gap-1">
                        <!-- Previous Page -->
                        <li class="page-item <?= $page_num <= 1 ? 'disabled' : '' ?>">
                            <?php if ($page_num <= 1): ?>
                                <span class="page-link btn btn-outline-shopee-secondary disabled">
                                    <i class="fa-solid fa-chevron-left me-1"></i> Prev
                                </span>
                            <?php else: ?>
                                <a class="page-link btn btn-outline-shopee-secondary" href="resolution_center.php?page=<?= $page_num - 1 ?>&search=<?= urlencode($search_query) ?>&type=<?= urlencode($type_filter) ?>&limit=<?= $items_per_page ?>">
                                    <i class="fa-solid fa-chevron-left me-1"></i> Prev
                                </a>
                            <?php endif; ?>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php if ($total_pages > 1): ?>
                            <?php
                            $range = 2; // Pages to display on each side of active page
                            $start_page = max(1, $page_num - $range);
                            $end_page = min($total_pages, $page_num + $range);
                            
                            if ($start_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link btn btn-outline-shopee-secondary" href="resolution_center.php?page=1&search=<?= urlencode($search_query) ?>&type=<?= urlencode($type_filter) ?>&limit=<?= $items_per_page ?>">1</a>
                                </li>
                                <?php if ($start_page > 2): ?>
                                    <li class="page-item disabled"><span class="page-link btn btn-outline-shopee-secondary disabled">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $start_page; $p <= $end_page; $p++): ?>
                                <li class="page-item <?= $page_num == $p ? 'active' : '' ?>">
                                    <a class="page-link btn <?= $page_num == $p ? 'btn-shopee active' : 'btn-outline-shopee-secondary' ?>" href="resolution_center.php?page=<?= $p ?>&search=<?= urlencode($search_query) ?>&type=<?= urlencode($type_filter) ?>&limit=<?= $items_per_page ?>">
                                        <?= $p ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link btn btn-outline-shopee-secondary disabled">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link btn btn-outline-shopee-secondary" href="resolution_center.php?page=<?= $total_pages ?>&search=<?= urlencode($search_query) ?>&type=<?= urlencode($type_filter) ?>&limit=<?= $items_per_page ?>"><?= $total_pages ?></a>
                                </li>
                            <?php endif; ?>
                        <?php else: ?>
                            <li class="page-item active">
                                <span class="page-link btn btn-shopee active">1</span>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Next Page -->
                        <li class="page-item <?= $page_num >= $total_pages ? 'disabled' : '' ?>">
                            <?php if ($page_num >= $total_pages): ?>
                                <span class="page-link btn btn-outline-shopee-secondary disabled">
                                    Next <i class="fa-solid fa-chevron-right ms-1"></i>
                                </span>
                            <?php else: ?>
                                <a class="page-link btn btn-outline-shopee-secondary" href="resolution_center.php?page=<?= $page_num + 1 ?>&search=<?= urlencode($search_query) ?>&type=<?= urlencode($type_filter) ?>&limit=<?= $items_per_page ?>">
                                    Next <i class="fa-solid fa-chevron-right ms-1"></i>
                                </a>
                            <?php endif; ?>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scan Options Modal -->
<div class="modal fade" id="scanOptionsModal" tabindex="-1" aria-labelledby="scanOptionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; background: var(--bg-surface);">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="modal-title fw-bold" id="scanOptionsModalLabel"><i class="fa-solid fa-radar text-shopee me-2"></i>Scan for Conflicts</h5>
                    <p class="text-secondary small mb-0 mt-1" style="font-size: 0.78rem;">Choose how you want to detect product and SKU discrepancies</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.8rem;"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex flex-column gap-3">
                    <!-- Option 1: Quick Scan -->
                    <div class="p-3 rounded-3 style-option-card d-flex gap-3 align-items-start" onclick="selectScanOption('quick')" style="cursor: pointer;">
                        <div class="sp-stat-icon mt-1" style="background: var(--sp-success-bg); color: var(--sp-success); width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fa-solid fa-bolt"></i>
                        </div>
                        <div>
                            <div class="fw-bold d-flex align-items-center gap-2" style="font-size: 0.9rem;">
                                Quick Scan <span class="badge bg-success-light text-success fw-semibold" style="font-size: 0.65rem; border-radius: 4px; padding: 3px 6px;">Instant</span>
                            </div>
                            <p class="text-secondary small mb-0 mt-1" style="font-size: 0.74rem; line-height: 1.4;">Scans the local database mapping table. Perfect when you've just updated mappings or local products. Completes instantly.</p>
                        </div>
                    </div>

                    <!-- Option 2: Sync & Scan -->
                    <div class="p-3 rounded-3 style-option-card d-flex gap-3 align-items-start" onclick="selectScanOption('sync_quick')" style="cursor: pointer;">
                        <div class="sp-stat-icon mt-1" style="background: var(--sp-info-bg); color: var(--sp-info); width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fa-solid fa-cloud-arrow-down"></i>
                        </div>
                        <div>
                            <div class="fw-bold d-flex align-items-center gap-2" style="font-size: 0.9rem;">
                                Sync & Scan <span class="badge bg-info-light text-info fw-semibold" style="font-size: 0.65rem; border-radius: 4px; padding: 3px 6px;">Fast (2-5s)</span>
                            </div>
                            <p class="text-secondary small mb-0 mt-1" style="font-size: 0.74rem; line-height: 1.4;">Pulls recently modified items from Shopee first, then runs the conflict scan. Ensures fresh Shopee data without waiting.</p>
                        </div>
                    </div>

                    <!-- Option 3: Deep Scan -->
                    <div class="p-3 rounded-3 style-option-card d-flex gap-3 align-items-start" onclick="selectScanOption('sync_full')" style="cursor: pointer;">
                        <div class="sp-stat-icon mt-1" style="background: var(--sp-warning-bg); color: var(--sp-warning); width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fa-solid fa-arrows-spin"></i>
                        </div>
                        <div>
                            <div class="fw-bold d-flex align-items-center gap-2" style="font-size: 0.9rem;">
                                Deep Re-Sync & Scan <span class="badge bg-warning-light text-warning fw-semibold" style="font-size: 0.65rem; border-radius: 4px; padding: 3px 6px;">Slow (1-2m)</span>
                            </div>
                            <p class="text-secondary small mb-0 mt-1" style="font-size: 0.74rem; line-height: 1.4;">Re-fetches your entire Shopee product catalog from scratch, then scans. Only use if catalog cache is out of sync.</p>
                        </div>
                    </div>
                </div>

                <!-- Live Progress Display inside Modal -->
                <div id="modalProgressSection" class="mt-4 pt-3 border-top" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold small text-body" id="modalProgressLabel" style="font-size: 0.8rem;">Initializing...</span>
                        <span class="small text-secondary fw-semibold" id="modalProgressCount" style="font-size: 0.75rem;">0 processed</span>
                    </div>
                    <div class="sp-progress-wrap mb-2" style="height: 6px; background: rgba(0,0,0,0.06); border-radius: 3px; overflow: hidden;">
                        <div class="sp-progress-fill" id="modalProgressBar" style="width: 0%; height: 100%; transition: width 0.3s; background: var(--shopee-primary);"></div>
                    </div>
                    <div class="alert alert-info py-2 px-3 small mb-0" id="modalLogText" style="font-size: 0.75rem; border-radius: 8px;"><i class="fa-solid fa-spinner fa-spin me-2"></i>Starting sync...</div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0 pb-4 px-4 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal" id="btnCancelScan" style="border-radius: 8px;">Cancel</button>
                <button type="button" class="btn btn-sm btn-shopee px-4" id="btnStartSelectedScan" disabled onclick="startSelectedScan()" style="border-radius: 8px;">Start Scan</button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedScanMode = null;

function selectScanOption(mode) {
    selectedScanMode = mode;
    
    // Highlight the selected option card
    const cards = document.querySelectorAll('.style-option-card');
    cards.forEach(card => card.classList.remove('selected'));
    
    const cardMap = {
        'quick': 0,
        'sync_quick': 1,
        'sync_full': 2
    };
    
    if (cards[cardMap[mode]]) {
        cards[cardMap[mode]].classList.add('selected');
    }
    
    // Enable the Start Scan button
    const btn = document.getElementById('btnStartSelectedScan');
    btn.removeAttribute('disabled');
}

async function startSelectedScan() {
    if (!selectedScanMode) return;
    
    const btn = document.getElementById('btnStartSelectedScan');
    const cancelBtn = document.getElementById('btnCancelScan');
    const progressSection = document.getElementById('modalProgressSection');
    const progBar = document.getElementById('modalProgressBar');
    const progLbl = document.getElementById('modalProgressLabel');
    const progCnt = document.getElementById('modalProgressCount');
    const logText = document.getElementById('modalLogText');
    const closeBtn = document.querySelector('#scanOptionsModal .btn-close');
    
    // Disable interaction
    btn.disabled = true;
    if (cancelBtn) cancelBtn.disabled = true;
    if (closeBtn) closeBtn.style.display = 'none';
    
    // Reset Progress UI
    progressSection.style.display = 'block';
    progBar.style.width = '0%';
    progBar.style.background = 'var(--shopee-primary)';
    progLbl.textContent = 'Initializing Scan...';
    progCnt.textContent = '0 processed';
    logText.className = 'alert alert-info py-2 px-3 small mb-0';
    
    try {
        if (selectedScanMode === 'quick') {
            progBar.style.width = '40%';
            progLbl.textContent = 'Analyzing SKU conflicts...';
            logText.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Running database diagnostics...';
            
            const res = await fetch(`${window.BASE_URL}api/shopee/detect_conflicts.php`);
            const data = await res.json();
            if (data.success) {
                progBar.style.width = '100%';
                progBar.style.background = 'var(--sp-success)';
                progLbl.textContent = 'Scan Complete!';
                logText.className = 'alert alert-success py-2 px-3 small mb-0';
                logText.innerHTML = `<i class="fa-solid fa-circle-check me-2"></i><strong>Success!</strong> ${data.message}`;
                EllaToast.success('Scan complete! Fresh conflicts loaded.');
                setTimeout(() => window.location.reload(), 1200);
            } else {
                throw new Error(data.error || 'Failed to detect conflicts');
            }
        } else {
            // Either 'sync_quick' or 'sync_full'
            const syncMode = selectedScanMode === 'sync_quick' ? 'quick' : 'full';
            
            progLbl.textContent = 'Contacting Shopee API...';
            logText.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Initializing Shopee sync session...';
            
            let offset = 0;
            let hasNextPage = true;
            let pageCount = 0;
            let queueId = null;
            
            // Step A: Initialize sync queue
            const initRes = await fetch(`${window.BASE_URL}api/shopee/sync_init.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mode: syncMode })
            });
            const initData = await initRes.json();
            if (!initData.success) {
                throw new Error(initData.error || 'Failed to initialize sync queue');
            }
            queueId = initData.queue_id;
            
            // Step B: Fetch pages
            while (hasNextPage) {
                pageCount++;
                progLbl.textContent = `Syncing Catalog (Page ${pageCount})...`;
                progCnt.textContent = `${offset} processed`;
                logText.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-2"></i>Downloading products batch ${pageCount} (offset: ${offset})...`;
                
                // Update progress bar dynamically (estimated)
                progBar.style.width = Math.min(90, (pageCount * 15)) + '%';
                
                const res = await fetch(`${window.BASE_URL}api/shopee/fetch_products.php?offset=${offset}&mode=${syncMode}&queue_id=${queueId}`);
                const data = await res.json();
                if (!data.success) {
                    throw new Error(data.error || 'Failed to fetch products');
                }
                
                hasNextPage = data.has_next_page;
                offset = data.next_offset;
            }
            
            // Step C: Complete Queue and trigger conflict detection
            progBar.style.width = '95%';
            progLbl.textContent = 'Analyzing SKU conflicts...';
            logText.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Finalizing database conflict checks...';
            
            // Complete sync queue
            await fetch(`${window.BASE_URL}api/shopee/sync_complete.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ queue_id: queueId, status: 'completed' })
            });
            
            // Finalize analysis
            const res = await fetch(`${window.BASE_URL}api/shopee/detect_conflicts.php`);
            const data = await res.json();
            
            if (data.success) {
                progBar.style.width = '100%';
                progBar.style.background = 'var(--sp-success)';
                progLbl.textContent = 'Scan Complete!';
                logText.className = 'alert alert-success py-2 px-3 small mb-0';
                logText.innerHTML = `<i class="fa-solid fa-circle-check me-2"></i><strong>Success!</strong> ${data.message}`;
                EllaToast.success('Sync & Scan complete! Catalog and conflicts reloaded.');
                setTimeout(() => window.location.reload(), 1200);
            } else {
                throw new Error(data.error || 'Failed to run conflict analysis');
            }
        }
    } catch (e) {
        progBar.style.width = '100%';
        progBar.style.background = 'var(--sp-danger)';
        progLbl.textContent = 'Scan Failed';
        logText.className = 'alert alert-danger py-2 px-3 small mb-0';
        logText.innerHTML = `<i class="fa-solid fa-xmark me-2"></i>${e.message}`;
        EllaToast.error('Scan failed: ' + e.message);
        
        // Re-enable interactions on error
        btn.disabled = false;
        if (cancelBtn) cancelBtn.disabled = false;
        if (closeBtn) closeBtn.style.display = 'block';
    }
}

async function ignoreError(id, btn) {
    if (!confirm('Are you sure you want to dismiss this error?')) return;
    
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Dismissing...';
    
    try {
        const formData = new FormData();
        formData.append('id', id);
        
        const res = await fetch(`${window.BASE_URL}api/shopee/dismiss_error.php`, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (data.success) {
            EllaToast.success(data.message);
            // Fade out the row
            const row = btn.closest('tr');
            if (row) {
                row.style.transition = 'all 0.4s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    row.remove();
                    // Reload after quick row animation to keep pagination counts completely sync'd
                    window.location.reload();
                }, 400);
            }
        } else {
            EllaToast.error(data.error || 'Failed to dismiss error');
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    } catch (e) {
        EllaToast.error('Network error: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
}

// Inline Resolution Center Modal Javascript Logic
let missingSkuModalInstance = null;
let duplicateModalInstance = null;

function openFixMissingSkuModal(itemId, modelId, errorMessage) {
    document.getElementById('missingSkuItemId').value = itemId;
    document.getElementById('missingSkuModelId').value = modelId;
    
    let warningHtml = `<i class="fa-solid fa-triangle-exclamation me-1"></i>${errorMessage}`;
    warningHtml += `<span class="small font-monospace opacity-75 mt-1 d-block" style="font-size: 0.72rem; line-height: 1.3;">`;
    warningHtml += `Shopee Item ID: <strong>${itemId}</strong>`;
    if (modelId && modelId !== 0) {
        warningHtml += ` | Model ID: <strong>${modelId}</strong>`;
    }
    warningHtml += `</span>`;
    document.getElementById('missingSkuWarningText').innerHTML = warningHtml;
    
    document.getElementById('newSkuInput').value = '';
    
    const label = document.getElementById('missingSkuInputLabel');
    const desc = document.getElementById('missingSkuInputDesc');
    const title = document.getElementById('fixMissingSkuModalLabel');
    
    if (modelId && modelId !== 0) {
        if (title) title.innerHTML = '<i class="fa-solid fa-circle-question text-shopee me-2"></i>Assign Variation SKU on Shopee';
        if (label) label.textContent = 'New Variation SKU for Shopee';
        if (desc) desc.textContent = 'This variation SKU will be pushed to Shopee instantly. If it matches a POS variation SKU, it will auto-link!';
    } else {
        if (title) title.innerHTML = '<i class="fa-solid fa-circle-question text-shopee me-2"></i>Assign Parent SKU on Shopee';
        if (label) label.textContent = 'New Parent SKU for Shopee';
        if (desc) desc.textContent = 'This parent product SKU will be pushed to Shopee instantly. If it matches a POS product SKU, it will auto-link!';
    }
    
    if (!missingSkuModalInstance) {
        missingSkuModalInstance = new bootstrap.Modal(document.getElementById('fixMissingSkuModal'));
    }
    missingSkuModalInstance.show();
}

async function submitFixMissingSku(e) {
    e.preventDefault();
    const itemId = document.getElementById('missingSkuItemId').value;
    const modelId = document.getElementById('missingSkuModelId').value;
    const newSku = document.getElementById('newSkuInput').value.trim();
    const btn = document.getElementById('btnSubmitMissingSku');
    
    if (!newSku) return;
    
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Syncing to Shopee...';
    
    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/update_shopee_sku.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId, model_id: modelId, new_sku: newSku })
        });
        const data = await res.json();
        
        if (data.success) {
            EllaToast.success(data.message + (data.auto_matched ? " (Automatically linked to POS variation!)" : ""));
            missingSkuModalInstance.hide();
            setTimeout(() => window.location.reload(), 1200);
        } else {
            EllaToast.error(data.error || 'Failed to update SKU');
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    } catch (err) {
        EllaToast.error('Network error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
}

async function openResolveDuplicateModal(sku) {
    document.getElementById('duplicateSkuTitleText').textContent = sku;
    const container = document.getElementById('duplicateItemsContainer');
    container.innerHTML = '<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin text-shopee fs-3"></i><p class="small text-secondary mt-2">Fetching duplicate listings...</p></div>';
    
    if (!duplicateModalInstance) {
        duplicateModalInstance = new bootstrap.Modal(document.getElementById('resolveDuplicateModal'));
    }
    duplicateModalInstance.show();
    
    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/get_shopee_items_by_sku.php?sku=${encodeURIComponent(sku)}`);
        const data = await res.json();
        
        if (data.success && data.items.length) {
            container.innerHTML = data.items.map(item => {
                const title = item.shopee_product_name;
                const subtitle = item.has_variation ? `Variation: <strong>${item.shopee_variation_name}</strong>` : 'Simple Product';
                const currentSku = item.has_variation ? item.shopee_variation_sku : item.shopee_parent_sku;
                
                return `
                <div class="p-3 border rounded-3 bg-light d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-bold text-dark" style="font-size:0.88rem;">${title}</div>
                            <div class="small text-secondary mt-1">${subtitle}</div>
                            <div class="small text-muted font-monospace mt-1" style="font-size:0.75rem;">Shopee Item ID: ${item.shopee_item_id} ${item.shopee_model_id ? `| Model ID: ${item.shopee_model_id}` : ''}</div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary text-white">Stock: ${item.shopee_stock}</span>
                            <div class="small fw-semibold text-shopee mt-1">₱${Number(item.shopee_price).toFixed(2)}</div>
                        </div>
                    </div>
                    <hr class="my-2 text-muted" style="opacity:0.1;">
                    <div class="row align-items-center g-2">
                        <div class="col-sm-8">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white font-monospace text-secondary" style="font-size:0.75rem;">SKU</span>
                                <input type="text" class="form-control form-control-sm font-monospace" id="dupInput_${item.id}" value="${currentSku}" style="font-size:0.8rem;">
                            </div>
                        </div>
                        <div class="col-sm-4 text-end">
                            <button class="btn btn-sm btn-shopee w-100 py-1" onclick="updateDuplicateItemSku(${item.id}, ${item.shopee_item_id}, ${item.shopee_model_id || 0}, this)">
                                <i class="fa-solid fa-floppy-disk me-1"></i>Update SKU
                            </button>
                        </div>
                    </div>
                </div>
                `;
            }).join('');
        } else {
            container.innerHTML = '<div class="alert alert-warning text-center py-3 m-0"><i class="fa-solid fa-triangle-exclamation me-1"></i>No active Shopee listings found for this SKU. It might have been cleared or re-scanned.</div>';
        }
    } catch (err) {
        container.innerHTML = `<div class="alert alert-danger text-center py-3 m-0"><i class="fa-solid fa-circle-exclamation me-1"></i>Error fetching details: ${err.message}</div>`;
    }
}

async function updateDuplicateItemSku(mappingId, itemId, modelId, btn) {
    const input = document.getElementById(`dupInput_${mappingId}`);
    const newSku = input ? input.value.trim() : '';
    
    if (!newSku) {
        EllaToast.error('SKU cannot be empty');
        return;
    }
    
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Updating...';
    
    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/update_shopee_sku.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId, model_id: modelId, new_sku: newSku })
        });
        const data = await res.json();
        
        if (data.success) {
            EllaToast.success(data.message + (data.auto_matched ? " (Automatically linked to POS variation!)" : ""));
            duplicateModalInstance.hide();
            setTimeout(() => window.location.reload(), 1200);
        } else {
            EllaToast.error(data.error || 'Failed to update SKU');
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    } catch (err) {
        EllaToast.error('Network error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
}
</script>

<!-- Fix Missing SKU Modal -->
<div class="modal fade" id="fixMissingSkuModal" tabindex="-1" aria-labelledby="fixMissingSkuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; background: var(--bg-surface);">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="modal-title fw-bold" id="fixMissingSkuModalLabel"><i class="fa-solid fa-circle-question text-shopee me-2"></i>Assign SKU on Shopee</h5>
                    <p class="text-secondary small mb-0 mt-1" style="font-size: 0.78rem;">Resolve the missing SKU conflict by assigning a unique SKU</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.8rem;"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning py-2 px-3 small mb-3" id="missingSkuWarningText" style="font-size:0.75rem; border-radius:8px;"></div>
                
                <form id="formFixMissingSku" onsubmit="submitFixMissingSku(event)">
                    <input type="hidden" id="missingSkuItemId">
                    <input type="hidden" id="missingSkuModelId">
                    
                    <div class="mb-3">
                        <label for="newSkuInput" class="form-label small fw-bold" id="missingSkuInputLabel">New SKU for Shopee</label>
                        <input type="text" class="form-control" id="newSkuInput" placeholder="e.g. SLV-001" required style="border-radius: 8px;">
                        <div class="form-text small" style="font-size: 0.72rem;" id="missingSkuInputDesc">This SKU will be pushed to Shopee instantly. If it matches a POS SKU, it will auto-link!</div>
                    </div>
                    
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-shopee px-4" id="btnSubmitMissingSku" style="border-radius: 8px;">Update Shopee SKU</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Resolve Duplicate SKU Modal -->
<div class="modal fade" id="resolveDuplicateModal" tabindex="-1" aria-labelledby="resolveDuplicateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; background: var(--bg-surface);">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="modal-title fw-bold" id="resolveDuplicateModalLabel"><i class="fa-solid fa-clone text-warning me-2"></i>Resolve Duplicate SKU</h5>
                    <p class="text-secondary small mb-0 mt-1" style="font-size: 0.78rem;">Multiple Shopee listings share the duplicate SKU: <strong id="duplicateSkuTitleText" class="text-dark"></strong></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.8rem;"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-secondary mb-3">To resolve this conflict, edit the SKU of one or more of the Shopee listings below to make them unique. Clicking update will push the new SKU to Shopee instantly.</p>
                
                <div id="duplicateItemsContainer" class="d-flex flex-column gap-3">
                    <div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin text-shopee fs-3"></i></div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary px-4" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
