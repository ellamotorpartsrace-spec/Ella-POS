<?php
// views/inventory/movements.php - Stock Movements History
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    denyAccess("You do not have permission to view stock movements.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Filters
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build Query
$sql = "
    SELECT 
        sm.movement_id,
        sm.type,
        sm.quantity,
        sm.previous_stock,
        sm.new_stock,
        sm.reference,
        sm.remarks,
        sm.created_at,
        pv.variation_name,
        pv.barcode,
        p.product_name,
        p.brand_name,
        u.full_name as created_by_name,
        ra.all_images_data,
        COALESCE(ra.attachment_count, 0) as attachment_count
    FROM stock_movements sm
    JOIN product_variations pv ON sm.variation_id = pv.variation_id
    JOIN products p ON pv.product_id = p.product_id
    LEFT JOIN users u ON sm.created_by = u.id
    LEFT JOIN (
        SELECT reference_number, 
               GROUP_CONCAT(CONCAT(id, ':', image_path) ORDER BY id ASC) as all_images_data,
               COUNT(*) as attachment_count 
        FROM reference_attachments 
        GROUP BY reference_number
    ) ra ON sm.reference = ra.reference_number
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (p.product_name LIKE ? OR p.brand_name LIKE ? OR pv.barcode LIKE ? OR sm.reference LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($type_filter)) {
    $sql .= " AND sm.type = ?";
    $params[] = $type_filter;
}

if (!empty($date_from)) {
    $sql .= " AND DATE(sm.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND DATE(sm.created_at) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY sm.created_at DESC LIMIT 500";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll();

// Stats
$stats_sql = "
    SELECT 
        type,
        COUNT(*) as count
    FROM stock_movements
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY type
";
$stats = $conn->query($stats_sql)->fetchAll(PDO::FETCH_KEY_PAIR);

// Type labels and colors
$type_config = [
    'stock_in' => ['label' => 'Stock In', 'icon' => 'fa-arrow-down', 'color' => 'success', 'bg' => 'bg-success'],
    'stock_out' => ['label' => 'Stock Out', 'icon' => 'fa-arrow-up', 'color' => 'danger', 'bg' => 'bg-danger'],
    'sales' => ['label' => 'Sales', 'icon' => 'fa-shopping-cart', 'color' => 'primary', 'bg' => 'bg-primary'],
    'adjustment' => ['label' => 'Adjustment', 'icon' => 'fa-sliders', 'color' => 'warning', 'bg' => 'bg-warning'],
    'return' => ['label' => 'Return', 'icon' => 'fa-rotate-left', 'color' => 'info', 'bg' => 'bg-info'],
    'allocation_to_online' => ['label' => 'Alloc to Online', 'icon' => 'fa-globe', 'color' => 'info', 'bg' => 'bg-info'],
    'allocation_to_physical' => ['label' => 'Alloc to Physical', 'icon' => 'fa-shop', 'color' => 'dark', 'bg' => 'bg-dark'],
    'online_sale' => ['label' => 'Online Sale', 'icon' => 'fa-cloud-arrow-down', 'color' => 'primary', 'bg' => 'bg-primary'],
    'online_adjustment' => ['label' => 'Online Adjust', 'icon' => 'fa-cloud', 'color' => 'warning', 'bg' => 'bg-warning']
];
?>

<style>
    /* Mobile-first responsive styles */
    .movement-card {
        transition: all 0.2s ease;
        border-left: 4px solid transparent;
    }

    .movement-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .movement-card.type-stock_in {
        border-left-color: var(--bs-success);
    }

    .movement-card.type-stock_out {
        border-left-color: var(--bs-danger);
    }

    .movement-card.type-sales {
        border-left-color: var(--bs-primary);
    }

    .movement-card.type-adjustment {
        border-left-color: var(--bs-warning);
    }

    .movement-card.type-return {
        border-left-color: var(--bs-info);
    }

    .movement-card.type-allocation_to_online {
        border-left-color: var(--bs-info);
    }

    .movement-card.type-allocation_to_physical {
        border-left-color: var(--bs-dark);
    }

    .movement-card.type-online_sale {
        border-left-color: var(--bs-primary);
    }

    .movement-card.type-online_adjustment {
        border-left-color: var(--bs-warning);
    }

    .qty-badge {
        font-size: 1.1rem;
        min-width: 60px;
    }

    /* Desktop table view */
    @media (min-width: 992px) {
        .mobile-cards {
            display: none !important;
        }

        .desktop-table {
            display: block !important;
        }
    }

    /* Mobile card view */
    @media (max-width: 991.98px) {
        .mobile-cards {
            display: block !important;
        }

        .desktop-table {
            display: none !important;
        }

        .filter-row .col-md-2 {
            margin-bottom: 0.5rem;
        }
    }

    .stat-card {
        border-radius: 12px;
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: scale(1.02);
    }
</style>

<div class="container-fluid p-3 p-lg-4">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h4 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-arrow-right-arrow-left text-primary me-2"></i>Stock Movements
            </h4>
            <p class="text-muted mb-0 small">Track all inventory changes and transactions</p>
        </div>
        <div class="d-flex gap-2">
            <a href="restock.php" class="btn btn-success">
                <i class="fa-solid fa-plus me-1"></i><span class="d-none d-sm-inline">New Stock In</span>
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <?php
        $stat_items = [
            ['type' => 'stock_in', 'label' => 'Stock In', 'icon' => 'fa-arrow-down', 'color' => 'success'],
            ['type' => 'sales', 'label' => 'Sales', 'icon' => 'fa-shopping-cart', 'color' => 'primary'],
            ['type' => 'stock_out', 'label' => 'Stock Out', 'icon' => 'fa-arrow-up', 'color' => 'danger'],
            ['type' => 'adjustment', 'label' => 'Adjustments', 'icon' => 'fa-sliders', 'color' => 'warning'],
            ['type' => 'allocation_to_online', 'label' => 'Allocations', 'icon' => 'fa-right-left', 'color' => 'info']
        ];
        foreach ($stat_items as $stat):
            $count = 0;
            // Count from our results
            foreach ($movements as $m) {
                if ($m['type'] === $stat['type'])
                    $count++;
            }
            ?>
            <div class="col-6 col-lg-3">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-<?= $stat['color'] ?> bg-opacity-10 p-2 me-3">
                                <i class="fa-solid <?= $stat['icon'] ?> text-<?= $stat['color'] ?> fa-lg"></i>
                            </div>
                            <div>
                                <div class="h4 fw-bold mb-0 text-<?= $stat['color'] ?>"><?= $count ?></div>
                                <small class="text-muted"><?= $stat['label'] ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 filter-row align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">SEARCH</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-search text-muted"></i></span>
                        <input type="text" name="search" id="movements-search" class="form-control"
                            placeholder="Product, barcode, reference..." value="<?= htmlspecialchars($search) ?>"
                            autocomplete="off">
                        <span class="input-group-text d-none bg-white" id="movements-search-spinner">
                            <i class="fa-solid fa-spinner fa-spin text-primary"></i>
                        </span>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">TYPE</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($type_config as $key => $cfg): ?>
                            <option value="<?= $key ?>" <?= $type_filter === $key ? 'selected' : '' ?>>
                                <?= $cfg['label'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">FROM</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">TO</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                </div>
                <div class="col-6 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                    <?php if (!empty($search) || !empty($type_filter) || !empty($date_from) || !empty($date_to)): ?>
                        <a href="movements.php" class="btn btn-outline-secondary" title="Reset Filters">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                    <a href="../../api/inventory/export_movements.php?search=<?= urlencode($search) ?>&type=<?= urlencode($type_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"
                        class="btn btn-success" title="Export to CSV">
                        <i class="fa-solid fa-file-export"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">
                <i class="fa-solid fa-list text-primary me-2"></i>Movement History
            </h6>
            <span class="badge bg-secondary"><?= count($movements) ?> records</span>
        </div>

        <!-- Desktop Table View -->
        <div class="card-body p-0 desktop-table" style="display: none;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Date & Time</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th class="text-center">Qty Change</th>
                            <th class="text-center">Stock Level</th>
                            <th>Reference</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($movements) > 0): ?>
                            <?php foreach ($movements as $row):
                                $cfg = $type_config[$row['type']] ?? ['label' => $row['type'], 'icon' => 'fa-circle', 'color' => 'secondary', 'bg' => 'bg-secondary'];

                                // Determine sign and color based on actual quantity value
                                // Sales are always deductions but stored as positive numbers
                                $is_positive = $row['quantity'] >= 0 && $row['type'] !== 'sales';
                                $qty_sign = $is_positive ? '+' : '-';
                                $qty_color = $is_positive ? 'success' : 'danger';
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold small"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                                        <small class="text-muted"><?= date('h:i A', strtotime($row['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['product_name']) ?></div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($row['brand_name']) ?> |
                                            <?= htmlspecialchars($row['variation_name']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $cfg['bg'] ?> bg-opacity-10 text-<?= $cfg['color'] ?>">
                                            <i class="fa-solid <?= $cfg['icon'] ?> me-1"></i><?= $cfg['label'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $qty_color ?>-subtle text-<?= $qty_color ?> qty-badge">
                                            <?= $qty_sign ?>         <?= abs($row['quantity']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <small class="text-muted"><?= $row['previous_stock'] ?></small>
                                        <i class="fa-solid fa-arrow-right mx-1 text-muted"></i>
                                        <span class="fw-bold"><?= $row['new_stock'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($row['reference']): ?>
                                            <a href="reference.php?ref=<?= urlencode($row['reference']) ?>&from=movements"
                                                class="text-decoration-none fw-bold">
                                                <code class="small text-primary"><?= htmlspecialchars($row['reference']) ?></code>
                                            </a>
                                            <?php if ($row['attachment_count'] > 0): ?>
                                                <a href="javascript:void(0)"
                                                    onclick="viewAttachment('<?= $row['all_images_data'] ?>', '<?= htmlspecialchars($row['reference']) ?>')"
                                                    class="text-primary text-decoration-none small d-block mt-1">
                                                    <i class="fa-solid fa-paperclip me-1"></i>View Receipt
                                                    <?php if ($row['attachment_count'] > 1): ?>
                                                        <span class="badge bg-secondary rounded-pill small ms-1"
                                                            style="font-size: 0.7em;">+<?= $row['attachment_count'] - 1 ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-link p-0 text-decoration-none mt-1 d-block"
                                                    onclick="openRetroUpload(<?= $row['movement_id'] ?>, '<?= htmlspecialchars($row['reference']) ?>')">
                                                    <i class="fa-solid fa-cloud-arrow-up"></i> Add Photo
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <button class="btn btn-sm btn-link p-0 text-decoration-none ms-2"
                                                onclick="openRetroUpload(<?= $row['movement_id'] ?>, 'No Reference')">
                                                <i class="fa-solid fa-cloud-arrow-up"></i> Add Photo
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($row['remarks']): ?>
                                            <div class="small text-muted text-truncate mt-1" style="max-width: 150px;"
                                                title="<?= htmlspecialchars($row['remarks']) ?>">
                                                <?= htmlspecialchars($row['remarks']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small
                                            class="text-muted"><?= htmlspecialchars($row['created_by_name'] ?? 'System') ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fa-solid fa-inbox fa-3x text-muted opacity-25 mb-3"></i>
                                    <h6 class="text-muted">No movements found</h6>
                                    <p class="small text-muted mb-0">Try adjusting your filters</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile Card View -->
        <div class="card-body p-3 mobile-cards" style="display: none;">
            <?php if (count($movements) > 0): ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($movements as $row):
                        $cfg = $type_config[$row['type']] ?? ['label' => $row['type'], 'icon' => 'fa-circle', 'color' => 'secondary', 'bg' => 'bg-secondary'];

                        // Sales are always deductions but stored as positive numbers
                        $is_positive = $row['quantity'] >= 0 && $row['type'] !== 'sales';
                        $qty_sign = $is_positive ? '+' : '-';
                        $qty_color = $is_positive ? 'success' : 'danger';
                        ?>
                        <div class="card movement-card type-<?= $row['type'] ?> border-0 shadow-sm">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['product_name']) ?></div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($row['brand_name']) ?> |
                                            <?= htmlspecialchars($row['variation_name']) ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?= $qty_color ?>-subtle text-<?= $qty_color ?> qty-badge">
                                        <?= $qty_sign ?>         <?= abs($row['quantity']) ?>
                                    </span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge <?= $cfg['bg'] ?> bg-opacity-10 text-<?= $cfg['color'] ?> me-2">
                                            <i class="fa-solid <?= $cfg['icon'] ?> me-1"></i><?= $cfg['label'] ?>
                                        </span>
                                        <small class="text-muted">
                                            <?= $row['previous_stock'] ?> → <strong><?= $row['new_stock'] ?></strong>
                                        </small>
                                    </div>
                                    <small class="text-muted">
                                        <?= date('M d, h:i A', strtotime($row['created_at'])) ?>
                                    </small>
                                </div>

                                <?php if ($row['reference'] || $row['remarks'] || $row['attachment_count'] == 0): ?>

                                    <div class="mt-2 pt-2 border-top">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php if ($row['reference']): ?>
                                                    <a href="reference.php?ref=<?= urlencode($row['reference']) ?>&from=movements"
                                                        class="text-decoration-none fw-bold">
                                                        <code
                                                            class="small text-primary"><?= htmlspecialchars($row['reference']) ?></code>
                                                    </a>
                                                    <?php if ($row['attachment_count'] == 0): ?>
                                                        <button class="btn btn-sm btn-link p-0 text-decoration-none ms-2"
                                                            onclick="openRetroUpload(<?= $row['movement_id'] ?>, '<?= htmlspecialchars($row['reference']) ?>')">
                                                            <i class="fa-solid fa-cloud-arrow-up"></i> Add Photo
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-link p-0 text-decoration-none"
                                                        onclick="openRetroUpload(<?= $row['movement_id'] ?>, 'No Reference')">
                                                        <i class="fa-solid fa-cloud-arrow-up"></i> Add Photo
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($row['remarks']): ?>
                                                    <small
                                                        class="text-muted d-block mt-1"><?= htmlspecialchars($row['remarks']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($row['attachment_count'] > 0): ?>
                                                <a href="javascript:void(0)"
                                                    onclick="viewAttachment('<?= $row['all_images_data'] ?>', '<?= htmlspecialchars($row['reference']) ?>')"
                                                    class="text-primary text-decoration-none small d-block mt-1">
                                                    <i class="fa-solid fa-paperclip me-1"></i>View Receipt
                                                    <?php if ($row['attachment_count'] > 1): ?>
                                                        <span class="badge bg-secondary rounded-pill small ms-1"
                                                            style="font-size: 0.7em;">+<?= $row['attachment_count'] - 1 ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fa-solid fa-inbox fa-3x text-muted opacity-25 mb-3"></i>
                    <h6 class="text-muted">No movements found</h6>
                    <p class="small text-muted mb-0">Try adjusting your filters</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-footer bg-white py-3">
            <small class="text-muted">
                <i class="fa-solid fa-info-circle me-1"></i>
                Showing <?= count($movements) ?> most recent movements (max 500)
            </small>
        </div>
    </div>

</div>

<!-- Attachment Modal -->
<div class="modal fade" id="attachmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-paperclip me-2 text-primary"></i>Reference: <span
                        id="attachment-ref-title"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="attachmentCarousel" class="carousel slide" data-bs-ride="false">
                    <div class="carousel-inner" id="modal-carousel-inner">
                        <!-- Images will be injected here -->
                    </div>
                    <button class="carousel-control-prev d-none" type="button" data-bs-target="#attachmentCarousel"
                        data-bs-slide="prev" style="width: 10%;">
                        <span class="carousel-control-prev-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next d-none" type="button" data-bs-target="#attachmentCarousel"
                        data-bs-slide="next" style="width: 10%;">
                        <span class="carousel-control-next-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
                <div class="text-center p-2 bg-light border-top d-none" id="carousel-indicator-text">
                    <small class="text-muted">Image <span id="current-img-idx">1</span> of <span
                            id="total-img-count">1</span></small>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-between">
                <div>
                    <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-delete-attachment"
                            onclick="deleteCurrentAttachment()">
                            <i class="fa-solid fa-trash me-1"></i>Delete Photo
                        </button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <a id="attachment-download" href="" download class="btn btn-sm btn-outline-primary"
                        onclick="this.href=document.querySelector('#modal-carousel-inner .carousel-item.active img').src">
                        <i class="fa-solid fa-download me-1"></i>Download
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Retroactive Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-cloud-arrow-up me-2 text-primary"></i>Upload
                    Reference Photo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="uploadForm">
                <div class="modal-body">
                    <input type="hidden" id="upload-movement-id" name="movement_id">
                    <p class="small text-muted mb-3">Uploading photo for Reference: <strong id="upload-ref-label"
                            class="text-dark"></strong></p>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Images (Max 8) <span
                                class="text-danger">*</span></label>
                        <input type="file" name="reference_images[]" id="upload-file" class="form-control"
                            accept="image/*" multiple required>
                        <div class="form-text small">You can select up to 8 images at once.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-upload">
                        <i class="fa-solid fa-upload me-1"></i>Upload Photo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Show appropriate view based on screen size
        function updateView() {
            const isMobile = window.innerWidth < 992;
            document.querySelectorAll('.mobile-cards').forEach(el => {
                el.style.display = isMobile ? 'block' : 'none';
            });
            document.querySelectorAll('.desktop-table').forEach(el => {
                el.style.display = isMobile ? 'none' : 'block';
            });
        }

        updateView();
        window.addEventListener('resize', updateView);

        // Progressive search with debounce
        const searchInput = document.getElementById('movements-search');
        const spinner = document.getElementById('movements-search-spinner');
        let searchTimeout = null;

        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                spinner?.classList.remove('d-none');

                searchTimeout = setTimeout(() => {
                    // Submit the form for filtered results
                    searchInput.closest('form').submit();
                }, 500);
            });

            // Immediate search on Enter
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    searchInput.closest('form').submit();
                }
            });
        }

        // Handle Retroactive Upload Form
        document.getElementById('uploadForm')?.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('btn-upload');
            const fileInput = document.getElementById('upload-file');

            if (!fileInput.files.length) {
                if (typeof EllaToast !== 'undefined') EllaToast.warning('Please select an image first.');
                else alert('Please select an image first.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Uploading...';

            const formData = new FormData(this);

            try {
                const res = await fetch('../../api/inventory/upload_retroactive_attachment.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();

                if (data.success) {
                    if (typeof EllaToast !== 'undefined') EllaToast.success(data.message);
                    else alert(data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    if (typeof EllaToast !== 'undefined') EllaToast.error('Error: ' + data.error);
                    else alert('Error: ' + data.error);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-upload me-1"></i>Upload Photo';
                }
            } catch (err) {
                console.error(err);
                if (typeof EllaToast !== 'undefined') EllaToast.error('An error occurred during upload.');
                else alert('An error occurred during upload.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-upload me-1"></i>Upload Photo';
            }
        });
    });

    /**
     * View Attachment in Modal (Multiple Support)
     */
    function viewAttachment(imagesDataStr, ref) {
        const items = imagesDataStr.split(',');
        const container = document.getElementById('modal-carousel-inner');
        const prevBtn = document.querySelector('#attachmentCarousel .carousel-control-prev');
        const nextBtn = document.querySelector('#attachmentCarousel .carousel-control-next');
        const indicatorText = document.getElementById('carousel-indicator-text');

        container.innerHTML = '';
        document.getElementById('attachment-ref-title').textContent = ref;
        document.getElementById('total-img-count').textContent = items.length;
        document.getElementById('current-img-idx').textContent = '1';

        items.forEach((data, idx) => {
            const parts = data.split(':');
            const id = parts[0];
            const path = parts.slice(1).join(':'); // Handle if path has colons

            const item = document.createElement('div');
            item.className = `carousel-item ${idx === 0 ? 'active' : ''}`;
            item.innerHTML = `
                <div class="d-flex flex-column align-items-center justify-content-center p-4" style="min-height: 300px;">
                    <img src="<?= BASE_URL ?>${path}" class="img-fluid rounded shadow-sm" style="max-height: 70vh;" alt="Receipt">
                    <div class="mt-3" style="position: relative; z-index: 10;">
                        <a href="<?= BASE_URL ?>${path}" target="_blank" class="btn btn-sm btn-link text-decoration-none">
                            <i class="fa-solid fa-expand me-1"></i>View Full Size
                        </a>
                    </div>
                </div>
            `;
            // Store data for deletion
            item.dataset.id = id;
            item.dataset.path = path;
            container.appendChild(item);
        });

        if (items.length > 1) {
            prevBtn.classList.remove('d-none');
            nextBtn.classList.remove('d-none');
            indicatorText.classList.remove('d-none');
        } else {
            prevBtn.classList.add('d-none');
            nextBtn.classList.add('d-none');
            indicatorText.classList.add('d-none');
        }

        const modal = new bootstrap.Modal(document.getElementById('attachmentModal'));
        modal.show();

        // Handle index update
        const carouselEl = document.getElementById('attachmentCarousel');
        carouselEl.addEventListener('slide.bs.carousel', function (e) {
            document.getElementById('current-img-idx').textContent = e.to + 1;
        });
    }

    function deleteCurrentAttachment() {
        const activeItem = document.querySelector('#modal-carousel-inner .carousel-item.active');
        const id = activeItem ? activeItem.dataset.id : null;
        const path = activeItem ? activeItem.dataset.path : null;
        if (!path) return;

        if (!confirm('Are you sure you want to delete this specific photo? This action cannot be undone.')) return;

        const btn = document.getElementById('btn-delete-attachment');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Deleting...';
        }

        const formData = new FormData();
        if (id) formData.append('id', id);
        formData.append('image_path', path);

        fetch('../../api/inventory/delete_attachment.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (typeof EllaToast !== 'undefined') EllaToast.success(data.message);
                    else alert(data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    if (typeof EllaToast !== 'undefined') EllaToast.error('Error: ' + data.error);
                    else alert('Error: ' + data.error);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-trash me-1"></i>Delete Photo';
                    }
                }
            })
            .catch(err => {
                console.error(err);
                if (typeof EllaToast !== 'undefined') EllaToast.error('An error occurred during deletion.');
                else alert('An error occurred during deletion.');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-trash me-1"></i>Delete Photo';
                }
            });
    }

    function openRetroUpload(movementId, refLabel) {
        document.getElementById('upload-movement-id').value = movementId;
        document.getElementById('upload-ref-label').textContent = refLabel;
        document.getElementById('uploadForm').reset();
        const modal = new bootstrap.Modal(document.getElementById('uploadModal'));
        modal.show();
    }
</script>

<?php require_once '../../includes/footer.php'; ?>