<?php
// views/inventory/unit_types.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    denyAccess("You do not have permission to access unit types.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// --- SEARCH LOGIC for Base Products ---
$search = trim($_GET['search'] ?? '');

$baseSql = "
    FROM product_variations v
    JOIN products p ON v.product_id = p.product_id
    WHERE v.status = 'active'
";

$params = [];

if (!empty($search)) {
    // Progressive matching
    $words = preg_split('/\s+/', $search);
    $validWords = [];
    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) >= 1) {
            $validWords[] = $word;
        }
    }

    $wordConditions = [];
    if (!empty($validWords)) {
        foreach ($validWords as $idx => $word) {
            $paramName = ":word{$idx}";
            $params[$paramName] = "%{$word}%";
            $wordConditions[] = "(
                p.product_name LIKE {$paramName}
                OR p.brand_name LIKE {$paramName}
                OR v.variation_name LIKE {$paramName}
                OR v.sku LIKE {$paramName}
                OR v.barcode LIKE {$paramName}
            )";
        }
    }

    // Exact barcode match prioritizing
    $params[':barcode'] = $search;
    $barcodeCondition = "v.barcode = :barcode OR ";

    $searchCondition = !empty($wordConditions) ? implode(' AND ', $wordConditions) : "1=1";

    $baseSql .= " AND ({$barcodeCondition}({$searchCondition}))";
}

$sqlProducts = "
    SELECT v.variation_id, v.variation_name, v.sku, v.unit_type as base_unit_type,
           v.price_capital, v.price_retail, v.price_wholesale, v.price_dealer,
           p.product_name, p.brand_name, p.image_path,
           (SELECT COUNT(*) FROM product_units pu WHERE pu.variation_id = v.variation_id) as custom_unit_count
    " . $baseSql . "
    ORDER BY p.product_name ASC
    LIMIT 50
";

$stmt = $conn->prepare($sqlProducts);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Highlight Helper function
function highlightQuery($text, $query) {
    if (empty($text)) return '';
    $textEsc = htmlspecialchars($text ?? '');
    $query = trim($query ?? '');
    if (empty($query)) return $textEsc;
    
    $words = preg_split('/\\s+/', $query);
    $words = array_filter($words, function($w) { return strlen($w) >= 1; });
    if (empty($words)) return $textEsc;
    
    foreach ($words as $w) {
        $pattern = '/(' . preg_quote($w, '/') . ')/i';
        $replacement = '<mark class="bg-warning bg-opacity-50 p-0 rounded text-dark">$1</mark>';
        $textEsc = preg_replace($pattern, $replacement, $textEsc);
    }
    return $textEsc;
}
?>

<style>
    /* Premium UI Additions */
    .table-hover-custom tbody tr {
        transition: background-color 0.2s ease, transform 0.1s ease;
    }

    .table-hover-custom tbody tr:hover {
        background-color: rgba(var(--bs-primary-rgb), 0.03);
        transform: translateY(-1px);
    }

    .search-input-group {
        border-radius: 50rem;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--bs-border-color);
        transition: box-shadow 0.3s ease;
    }

    .search-input-group:focus-within {
        box-shadow: 0 4px 12px rgba(var(--bs-primary-rgb), 0.15);
        border-color: var(--bs-primary);
    }

    .search-input-group input {
        border: none;
        box-shadow: none !important;
        padding-left: 1.25rem;
    }

    .search-input-group .btn {
        border-radius: 0 50rem 50rem 0;
        padding-left: 1.5rem;
        padding-right: 1.5rem;
    }

    .empty-state-icon {
        font-size: 3rem;
        color: var(--bs-gray-400);
        margin-bottom: 1rem;
    }

    /* Pricing Mode Toggle Styling */
    .pricing-toggle .btn {
        flex: 1;
        font-weight: 500;
        border-color: var(--bs-border-color);
        transition: all 0.2s ease;
    }

    .pricing-toggle .btn-check:checked+.btn {
        background-color: var(--bs-primary);
        color: white;
        border-color: var(--bs-primary);
        box-shadow: 0 2px 4px rgba(var(--bs-primary-rgb), 0.2);
    }

    .form-group-card {
        background: var(--bs-gray-100);
        border-radius: 0.5rem;
        padding: 1rem;
        border: 1px solid var(--bs-border-color);
    }

    [data-theme='dark'] .form-group-card {
        background: rgba(255, 255, 255, 0.03);
    }

    .readonly-price {
        background-color: var(--bs-gray-200) !important;
        opacity: 0.8;
        cursor: not-allowed;
    }

    [data-theme='dark'] .readonly-price {
        background-color: rgba(0, 0, 0, 0.2) !important;
        color: var(--bs-gray-500) !important;
    }

    /* Mobile Enhancements for Card Layout */
    @media (max-width: 767.98px) {
        .search-input-group {
            max-width: 100% !important;
        }

        form.d-flex {
            width: 100% !important;
            max-width: 100% !important;
            margin-top: 10px;
        }

        /* Force table to not be like tables anymore */
        .table-responsive.border-0 {
            background-color: transparent !important;
            padding: 0;
        }

        .table-hover-custom {
            display: block;
            background-color: transparent !important;
        }

        /* Hide table headers (but not display: none;, for accessibility) */
        .table-hover-custom thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }

        .table-hover-custom tbody {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 10px;
        }

        .table-hover-custom tbody tr {
            display: flex;
            flex-direction: column;
            background: #fff;
            border: 1px solid var(--bs-border-color);
            border-radius: 0.75rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            padding: 1rem;
            position: relative;
        }

        [data-theme='dark'] .table-hover-custom tbody tr {
            background: var(--bg-surface);
        }

        .table-hover-custom tbody td {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: none;
            padding: 0.5rem 0;
            text-align: right;
        }

        /* Product Detail Cell (Full Width at Top) */
        .table-hover-custom tbody td:first-child {
            display: block;
            text-align: left;
            margin-bottom: 0.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--bs-border-color);
        }

        .table-hover-custom tbody td:first-child .d-flex {
            align-items: flex-start !important;
        }

        /* Add Labels using pseudo-elements */
        .table-hover-custom tbody td:nth-child(2)::before {
            content: "Base Unit";
            font-weight: 600;
            color: var(--bs-secondary);
            font-size: 0.85rem;
        }

        .table-hover-custom tbody td:nth-child(3)::before {
            content: "Custom Units";
            font-weight: 600;
            color: var(--bs-secondary);
            font-size: 0.85rem;
        }

        .table-hover-custom tbody td:last-child {
            justify-content: center;
            margin-top: 0.5rem;
            padding-top: 1rem;
            padding-bottom: 0;
            border-top: 1px dashed var(--bs-border-color);
        }

        .table-hover-custom tbody td:last-child .btn {
            width: 100%;
        }

        /* Empty state override for mobile */
        .table-hover-custom tbody td[colspan="4"] {
            display: block;
            text-align: center;
            border: 1px dashed var(--bs-border-color);
            border-radius: 0.75rem;
            background: transparent;
        }

        .table-hover-custom tbody td[colspan="4"]::before {
            display: none;
        }
    }
</style>

<div class="container-fluid p-3 p-md-4">
    <!-- Header -->
    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-0 mb-md-4 gap-2 gap-md-3">
        <div>
            <h4 class="mb-1 fw-bold" style="color: var(--text-primary);">
                <i class="fa-solid fa-boxes-packing text-primary me-2"></i>Unit Types
            </h4>
            <p class="text-muted small mb-0">Manage packaging and custom multipliers for your inventory items.</p>
        </div>

        <form method="GET" action="unit_types.php" class="d-flex" style="width: 100%; max-width: 450px;">
            <div class="input-group search-input-group w-100 bg-white shadow-sm">
                <input type="text" name="search" class="form-control border-0"
                    placeholder="Search product name, brand, or barcode..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-primary border-0" type="submit"><i class="fa-solid fa-search"></i></button>
            </div>
        </form>
    </div>

    <?php if (empty($search)): ?>
        <div class="alert alert-light border shadow-sm small my-3 my-md-4 py-2">
            <i class="fa-solid fa-circle-info text-primary me-2"></i> Showing the first 50 active products. Use the search
            bar to find specific items quickly.
        </div>
    <?php else: ?>
        <div class="my-3 my-md-4"></div>
    <?php endif; ?>

    <!-- Main Table / Mobile Cards layout -->
    <div class="card shadow-sm border-0 mb-4 bg-transparent bg-md-surface"
        style="border-radius: 0.75rem; box-shadow: none !important;">
        <div class="card-body p-0">
            <div class="table-responsive border-0 overflow-visible overflow-md-auto">
                <table class="table table-hover-custom align-middle mb-0">
                    <thead style="background: var(--bg-surface);" class="d-none d-md-table-header-group">
                        <tr>
                            <th class="ps-4 py-3 text-secondary fw-semibold border-bottom-0">Product Detail</th>
                            <th class="py-3 text-secondary fw-semibold border-bottom-0">Base Unit</th>
                            <th class="py-3 text-secondary fw-semibold border-bottom-0">Custom Units</th>
                            <th class="text-end pe-4 py-3 text-secondary fw-semibold border-bottom-0">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $row): ?>
                                <tr>
                                    <td class="ps-md-4 py-3 border-0 border-md-bottom">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded d-flex align-items-center justify-content-center me-3 border shrink-0"
                                                style="width: 56px; height: 56px; background: var(--bs-gray-100); flex-shrink: 0;">
                                                <?php if (!empty($row['image_path'])): ?>
                                                    <img src="<?= BASE_URL . $row['image_path'] ?>"
                                                        style="width:100%; height:100%; object-fit:cover; border-radius: var(--bs-border-radius);">
                                                <?php else: ?>
                                                    <i class="fa-solid fa-box text-secondary fs-4"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold fs-6 lh-sm mb-1" style="color: var(--text-primary);">
                                                    <?= highlightQuery($row['product_name'] ?? '', $search) ?>
                                                </div>
                                                <div class="small text-muted lh-sm mb-1">
                                                    <?= highlightQuery($row['brand_name'] ?? '', $search) ?>
                                                    <span class="mx-1">&bull;</span>
                                                    <span
                                                        class="text-primary fw-medium"><?= highlightQuery($row['variation_name'] ?? '', $search) ?></span>
                                                </div>
                                                <div class="small lh-sm" style="font-size: 0.75rem; color: var(--bs-gray-500);">
                                                    <i
                                                        class="fa-solid fa-barcode me-1"></i><?= highlightQuery($row['barcode'] ?? $row['sku'] ?? 'N/A', $search) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="border-0 border-md-bottom">
                                        <span
                                            class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary fw-medium px-2 py-1 shadow-sm">
                                            <?= htmlspecialchars($row['base_unit_type'] ?? 'pc') ?>
                                        </span>
                                    </td>
                                    <td class="border-0 border-md-bottom">
                                        <?php if ($row['custom_unit_count'] > 0): ?>
                                            <span
                                                class="badge bg-info bg-opacity-10 text-info border border-info fw-medium px-2 py-1 shadow-sm">
                                                <i class="fa-solid fa-layer-group me-1"></i><?= $row['custom_unit_count'] ?>
                                                Unit<?= $row['custom_unit_count'] > 1 ? 's' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small italic"><i class="fa-solid fa-minus me-1"></i>None
                                                setup</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-md-4 border-0 border-md-bottom">
                                        <?php
                                        $pricesDataPayload = [
                                            'retail' => (float) ($row['price_retail'] ?? 0),
                                            'wholesale' => (float) ($row['price_wholesale'] ?? 0),
                                            'dealer' => (float) ($row['price_dealer'] ?? 0)
                                        ];
                                        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                                            $pricesDataPayload['capital'] = (float) ($row['price_capital'] ?? 0);
                                        }
                                        $pricesData = htmlspecialchars(json_encode($pricesDataPayload), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <button
                                            class="btn btn-primary d-md-none btn-sm btn-manage-units px-3 fw-medium rounded-pill w-100"
                                            data-variation-id="<?= $row['variation_id'] ?>"
                                            data-title="<?= htmlspecialchars(($row['product_name'] ?? '') . ' - ' . ($row['variation_name'] ?? '')) ?>"
                                            data-prices="<?= $pricesData ?>" data-bs-toggle="tooltip"
                                            title="View, Add, or Edit Units">
                                            <i class="fa-solid fa-list-check me-1"></i> Manage Custom Units
                                        </button>
                                        <button
                                            class="btn btn-outline-primary d-none d-md-inline-block btn-sm btn-manage-units px-3 fw-medium rounded-pill"
                                            data-variation-id="<?= $row['variation_id'] ?>"
                                            data-title="<?= htmlspecialchars(($row['product_name'] ?? '') . ' - ' . ($row['variation_name'] ?? '')) ?>"
                                            data-prices="<?= $pricesData ?>" data-bs-toggle="tooltip"
                                            title="View, Add, or Edit Units">
                                            <i class="fa-solid fa-list-check me-1"></i> Manage
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 bg-light rounded shadow-sm border">
                                    <div class="empty-state-icon"><i class="fa-solid fa-box-open"></i></div>
                                    <h5 class="fw-bold text-muted">No products found</h5>
                                    <p class="text-muted small">Try adjusting your search term or barcode.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ======================================= -->
<!-- MODALS & TOAST COMPONENTS               -->
<!-- ======================================= -->

<!-- 1. Manage Units Modal -->
<div class="modal fade" id="unitListModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header border-bottom bg-light bg-opacity-50 align-items-center">
                <div>
                    <h5 class="modal-title fw-bold mb-0 text-dark" id="unitListTitle">Manage Units</h5>
                    <small class="text-muted d-block mt-1" id="unitListSubtitle"></small>
                </div>
                <!-- Persistent Add Button -->
                <button class="btn btn-primary btn-sm ms-auto me-3 px-3 rounded-pill fw-medium shadow-sm"
                    id="btnHeaderAddUnit">
                    <i class="fa-solid fa-plus me-1"></i> Add Unit
                </button>
                <button type="button" class="btn-close m-0" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 text-secondary small text-uppercase border-bottom-0 py-3">Unit Package
                                </th>
                                <th class="text-secondary small text-uppercase border-bottom-0 py-3">Multiplier</th>
                                <th class="text-secondary small text-uppercase border-bottom-0 py-3">Barcode</th>
                                <th class="text-secondary small text-uppercase border-bottom-0 py-3">Description</th>
                                <th class="text-secondary small text-uppercase border-bottom-0 py-3">Retail</th>
                                <th class="text-secondary small text-uppercase border-bottom-0 py-3">Wholesale</th>
                                <th class="text-secondary small text-uppercase border-bottom-0 py-3">Dealer</th>
                                <th class="text-end pe-3 text-secondary small text-uppercase border-bottom-0 py-3">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody id="unitListBody">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light bg-opacity-50 border-top-0 py-2">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3 shadow-sm"
                    data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- 2. Add/Edit Unit Form Modal -->
<div class="modal fade" id="unitFormModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header border-bottom-0 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="unitFormTitle"><i
                        class="fa-solid fa-box me-2 text-primary"></i>Add Custom Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                <form id="unitForm" onsubmit="saveUnit(event)">
                    <input type="hidden" id="uf_id">
                    <input type="hidden" id="uf_variation_id">
                    <input type="hidden" id="uf_base_capital">
                    <input type="hidden" id="uf_base_retail">
                    <input type="hidden" id="uf_base_wholesale">
                    <input type="hidden" id="uf_base_dealer">

                    <!-- Basic Setup Card -->
                    <div class="form-group-card mb-3">
                        <h6 class="fw-bold text-secondary mb-3 small text-uppercase"><i
                                class="fa-solid fa-cube me-2"></i>Package Details</h6>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label text-secondary small fw-bold mb-1">Unit Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm border-0" id="uf_name"
                                    placeholder="e.g., Box, Case" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-secondary small fw-bold mb-1">Multiplier (pcs) <span
                                        class="text-danger">*</span></label>
                                <input type="number" class="form-control form-control-sm border-0" id="uf_multiplier"
                                     min="1" placeholder="e.g., 1" required>
                            </div>
                            <div class="col-12 mt-2">
                                <label class="form-label text-secondary small fw-bold mb-1">Unit Barcode <span
                                        class="text-muted fw-normal">(Optional)</span></label>
                                <div class="input-group input-group-sm rounded shadow-sm">
                                    <span class="input-group-text bg-white border-0"><i
                                            class="fa-solid fa-barcode text-muted"></i></span>
                                    <input type="text" class="form-control border-0" id="uf_barcode"
                                        placeholder="Scan unit barcode">
                                </div>
                            </div>
                            <div class="col-12 mt-2">
                                <label class="form-label text-secondary small fw-bold mb-1">Description <span
                                        class="text-muted fw-normal">(Optional — shown in POS cart)</span></label>
                                <textarea class="form-control form-control-sm border-0" id="uf_description" rows="2"
                                    placeholder="e.g., Box of 12 pieces, sealed with tape"
                                    style="resize:vertical;"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing Section -->
                    <div class="form-group-card mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-secondary mb-0 small text-uppercase"><i
                                    class="fa-solid fa-tags me-2"></i>Pricing Configuration</h6>
                            <span class="badge bg-light text-secondary border small fw-normal"><i
                                    class="fa-solid fa-lock me-1"></i>Lock = Follow Original &times; Multiplier</span>
                        </div>

                        <div class="row g-2">
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <!-- Capital: always auto-calculated, no lock needed -->
                                <div class="col-12">
                                    <label class="form-label text-secondary small mb-1">Capital Cost <span
                                            class="text-muted fw-normal">(auto &times; multiplier)</span></label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light border-0 text-muted">
                                            <i class="fa-solid fa-lock text-muted"></i>
                                        </span>
                                        <input type="text" class="form-control border-0 fw-medium text-muted bg-light"
                                            id="preview_capital_display" value="₱0.00" readonly
                                            style="cursor:not-allowed; font-variant-numeric: tabular-nums;">
                                        <span class="input-group-text bg-light border-0 text-muted small">Auto</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Retail -->
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label text-secondary small mb-0 fw-bold" id="label_retail">Retail
                                        Price (₱)</label>
                                    <button type="button"
                                        class="btn btn-sm px-2 py-0 rounded-pill border price-lock-btn" id="lock_retail"
                                        data-tier="retail" data-locked="1" style="font-size:0.72rem;">
                                        <i class="fa-solid fa-lock me-1"></i>Following Original
                                    </button>
                                </div>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0"
                                        class="form-control border-0 fw-medium text-success" id="price_retail"
                                        placeholder="Price per piece" readonly style="background:var(--bs-gray-100);">
                                    <span class="input-group-text bg-light border-0 text-success small fw-bold"
                                        id="auto_badge_retail" style="min-width:80px;">
                                        <i class="fa-solid fa-calculator me-1"></i>Auto
                                    </span>
                                </div>
                            </div>

                            <!-- Wholesale -->
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label text-secondary small mb-0 fw-bold"
                                        id="label_wholesale">Wholesale (₱)</label>
                                    <button type="button"
                                        class="btn btn-sm px-2 py-0 rounded-pill border price-lock-btn"
                                        id="lock_wholesale" data-tier="wholesale" data-locked="1"
                                        style="font-size:0.72rem;">
                                        <i class="fa-solid fa-lock me-1"></i>Following Original
                                    </button>
                                </div>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0"
                                        class="form-control border-0 fw-medium text-primary" id="price_wholesale"
                                        placeholder="Price per piece" readonly style="background:var(--bs-gray-100);">
                                    <span class="input-group-text bg-light border-0 text-primary small fw-bold"
                                        id="auto_badge_wholesale" style="min-width:80px;">
                                        <i class="fa-solid fa-calculator me-1"></i>Auto
                                    </span>
                                </div>
                            </div>

                            <!-- Dealer -->
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label text-secondary small mb-0 fw-bold" id="label_dealer">Dealer
                                        (₱)</label>
                                    <button type="button"
                                        class="btn btn-sm px-2 py-0 rounded-pill border price-lock-btn" id="lock_dealer"
                                        data-tier="dealer" data-locked="1" style="font-size:0.72rem;">
                                        <i class="fa-solid fa-lock me-1"></i>Following Original
                                    </button>
                                </div>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0"
                                        class="form-control border-0 fw-medium text-warning" id="price_dealer"
                                        placeholder="Price per piece" readonly style="background:var(--bs-gray-100);">
                                    <span class="input-group-text bg-light border-0 text-warning small fw-bold"
                                        id="auto_badge_dealer" style="min-width:80px;">
                                        <i class="fa-solid fa-calculator me-1"></i>Auto
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden submission fields -->
                        <input type="hidden" id="uf_capital" name="price_capital" value="0.00">
                        <input type="hidden" id="uf_retail" name="price_retail" value="0.00">
                        <input type="hidden" id="uf_wholesale" name="price_wholesale" value="0.00">
                        <input type="hidden" id="uf_dealer" name="price_dealer" value="0.00">
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light border rounded-pill px-4 shadow-sm"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm" id="btnSaveUnit">
                            <i class="fa-solid fa-check me-1"></i> Save Unit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 3. Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
            <div class="modal-body text-center p-4">
                <div class="text-danger mb-3" style="font-size: 3rem;">
                    <i class="fa-solid fa-circle-exclamation shadow-sm rounded-circle"></i>
                </div>
                <h5 class="fw-bold mb-2 text-dark">Are you sure?</h5>
                <p class="text-muted small mb-4" id="confirmMessage">This action cannot be undone.</p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light border px-4 rounded-pill shadow-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger px-4 rounded-pill shadow-sm"
                        id="btnConfirmAction">Delete</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 4. Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1060;">
    <div id="actionToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert"
        aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-medium" id="toastMessage">
                Action successful.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
    let listModal, formModal, confirmModalObj;
    let currentVariationPrices = null;
    let currentVariationId = null;
    let headerAddBtn;
    let actionCallback = null;

    document.addEventListener('DOMContentLoaded', () => {
        // Initialize Modals
        listModal = new bootstrap.Modal(document.getElementById('unitListModal'));
        formModal = new bootstrap.Modal(document.getElementById('unitFormModal'));
        confirmModalObj = new bootstrap.Modal(document.getElementById('confirmModal'));
        headerAddBtn = document.getElementById('btnHeaderAddUnit');

        // Initialize Tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        // Event Delegation for Table Buttons
        document.addEventListener('click', function (e) {
            const manageBtn = e.target.closest('.btn-manage-units');
            if (manageBtn) {
                const vid = manageBtn.dataset.variationId;
                const title = manageBtn.dataset.title;
                let prices = {};
                try {
                    let rawPrices = manageBtn.dataset.prices || '{}';
                    prices = JSON.parse(rawPrices);
                } catch (e) {
                    console.error("Failed to parse prices", e);
                }
                currentVariationPrices = prices;
                currentVariationId = vid;
                viewUnits(vid, title);
            }
        });

        // Header Add Button Handler
        headerAddBtn.addEventListener('click', () => {
            openAddUnitModal(currentVariationId, currentVariationPrices);
        });

        // Multiplier input triggers recalculation for ALL tiers (locked & unlocked)
        document.getElementById('uf_multiplier').addEventListener('input', function () {
            recalcAllPrices();
        });

        // Per-tier lock buttons
        document.querySelectorAll('.price-lock-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                togglePriceLock(this.dataset.tier);
            });
        });

        // Price inputs (per-piece): recalc final price on input
        ['retail', 'wholesale', 'dealer'].forEach(tier => {
            const el = document.getElementById('price_' + tier);
            if (el) el.addEventListener('input', function () {
                recalcTier(tier); // recompute final = this value * multiplier
            });
        });

        // Confirmation Action bind
        document.getElementById('btnConfirmAction').addEventListener('click', () => {
            if (typeof actionCallback === 'function') {
                actionCallback();
            }
            confirmModalObj.hide();
        });
    });

    // Utility to show toasts
    function showToast(message, type = 'success') {
        const toastEl = document.getElementById('actionToast');
        const toastMessage = document.getElementById('toastMessage');

        // Reset classes
        toastEl.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-info');

        if (type === 'success') {
            toastEl.classList.add('bg-success');
            toastMessage.innerHTML = `<i class="fa-solid fa-circle-check me-2"></i> ${message}`;
        } else if (type === 'error') {
            toastEl.classList.add('bg-danger');
            toastMessage.innerHTML = `<i class="fa-solid fa-circle-xmark me-2"></i> ${message}`;
        } else {
            toastEl.classList.add('bg-primary');
            toastMessage.innerHTML = `<i class="fa-solid fa-circle-info me-2"></i> ${message}`;
        }

        const bsToast = new bootstrap.Toast(toastEl, { delay: 3000 });
        bsToast.show();
    }

    function showConfirm(message, callback) {
        document.getElementById('confirmMessage').textContent = message;
        actionCallback = callback;
        confirmModalObj.show();
    }

    // Toggle the lock state for a price tier (retail / wholesale / dealer)
    function togglePriceLock(tier) {
        const btn = document.getElementById('lock_' + tier);
        const input = document.getElementById('price_' + tier);
        const badge = document.getElementById('auto_badge_' + tier);
        const label = document.getElementById('label_' + tier);
        if (!btn || !input) return;

        const tierLabels = { retail: 'Retail Price', wholesale: 'Wholesale', dealer: 'Dealer' };
        const isLocked = btn.dataset.locked === '1';

        if (isLocked) {
            // Unlock: user types their own per-piece price (still multiplied)
            btn.dataset.locked = '0';
            btn.innerHTML = '<i class="fa-solid fa-lock-open me-1"></i>Custom Price';
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-outline-warning');
            input.readOnly = false;
            input.style.background = '';
            input.style.cursor = '';
            input.value = ''; // clear so user types fresh
            input.placeholder = 'Price per piece';
            if (label) label.textContent = tierLabels[tier] + ' /pc (₱)';
            if (badge) badge.textContent = '= ₱0.00';
            input.focus();
        } else {
            // Lock: revert to auto base × multiplier
            btn.dataset.locked = '1';
            btn.innerHTML = '<i class="fa-solid fa-lock me-1"></i>Following Original';
            btn.classList.remove('btn-outline-warning');
            btn.classList.add('btn-outline-secondary');
            input.readOnly = true;
            input.style.background = 'var(--bs-gray-100)';
            input.style.cursor = 'not-allowed';
            input.placeholder = 'Price per piece';
            if (label) label.textContent = tierLabels[tier] + ' (₱)';
            if (badge) badge.innerHTML = '<i class="fa-solid fa-calculator me-1"></i>Auto';
            recalcTier(tier); // restore locked auto value
        }
    }

    // Recalculate a single tier: final = (per_pc_input OR base_price) * multiplier
    function recalcTier(tier) {
        const multiplier = parseFloat(document.getElementById('uf_multiplier').value) || 0;
        const lockBtn = document.getElementById('lock_' + tier);
        const input = document.getElementById('price_' + tier);
        const badge = document.getElementById('auto_badge_' + tier);
        const isLocked = !lockBtn || lockBtn.dataset.locked === '1';

        let perPc;
        if (isLocked) {
            // Use product base price
            const baseMap = { retail: 'uf_base_retail', wholesale: 'uf_base_wholesale', dealer: 'uf_base_dealer' };
            perPc = parseFloat(document.getElementById(baseMap[tier])?.value) || 0;
        } else {
            // Use whatever the user typed in the input as their per-piece price
            perPc = parseFloat(input?.value) || 0;
        }

        const final = perPc * multiplier;
        document.getElementById('uf_' + tier).value = final.toFixed(2);
        // Show the computed final price in the input when locked (read-only display)
        if (isLocked && input) input.value = final.toFixed(2);

        // Update badge
        if (badge) {
            if (isLocked) {
                badge.innerHTML = '<i class="fa-solid fa-calculator me-1"></i>Auto';
            } else {
                badge.textContent = '= ₱' + final.toFixed(2);
            }
        }
    }

    // Recalculate ALL tiers + capital
    function recalcAllPrices() {
        const multiplier = parseFloat(document.getElementById('uf_multiplier').value) || 0;

        // Capital: always auto
        const baseCap = parseFloat(document.getElementById('uf_base_capital').value) || 0;
        const finalCap = baseCap * multiplier;
        document.getElementById('uf_capital').value = finalCap.toFixed(2);
        const capDisplay = document.getElementById('preview_capital_display');
        if (capDisplay) capDisplay.value = '₱' + finalCap.toFixed(2);

        // All 3 price tiers
        ['retail', 'wholesale', 'dealer'].forEach(tier => recalcTier(tier));
    }

    // Reset all price lock buttons to locked state
    function resetPriceLocks() {
        const tierLabels = { retail: 'Retail Price', wholesale: 'Wholesale', dealer: 'Dealer' };
        ['retail', 'wholesale', 'dealer'].forEach(tier => {
            const btn = document.getElementById('lock_' + tier);
            const input = document.getElementById('price_' + tier);
            const badge = document.getElementById('auto_badge_' + tier);
            const label = document.getElementById('label_' + tier);
            if (!btn) return;
            btn.dataset.locked = '1';
            btn.innerHTML = '<i class="fa-solid fa-lock me-1"></i>Following Original';
            btn.classList.remove('btn-outline-warning');
            btn.classList.add('btn-outline-secondary');
            if (input) {
                input.readOnly = true;
                input.style.background = 'var(--bs-gray-100)';
                input.style.cursor = 'not-allowed';
                input.value = ''; // Clear input value when resetting to locked
            }
            if (badge) badge.innerHTML = '<i class="fa-solid fa-calculator me-1"></i>Auto';
            if (label) label.textContent = tierLabels[tier] + ' (₱)';
        });
    }

    function viewUnits(variation_id, titleText) {
        document.getElementById('unitListSubtitle').textContent = titleText;
        document.getElementById('unitListBody').innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-5">
                <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                <span class="ms-2 text-muted fw-medium small">Loading units...</span>
            </td>
        </tr>`;
        listModal.show();

        fetch(`../../api/units/list.php?variation_id=${variation_id}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    renderUnitsTable(res.data, variation_id);
                } else {
                    document.getElementById('unitListBody').innerHTML = `<tr><td colspan="7" class="text-danger text-center"><i class="fa-solid fa-triangle-exclamation me-1"></i> ${res.message}</td></tr>`;
                }
            }).catch(() => {
                document.getElementById('unitListBody').innerHTML = `<tr><td colspan="7" class="text-danger text-center"><i class="fa-solid fa-triangle-exclamation me-1"></i> Error loading data</td></tr>`;
            });
    }

    function renderUnitsTable(units, variation_id) {
        const tbody = document.getElementById('unitListBody');
        if (units.length === 0) {
            tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5 bg-light">
                    <div class="empty-state-icon" style="font-size: 2.5rem;"><i class="fa-solid fa-box-open"></i></div>
                    <h6 class="fw-bold text-muted mb-1">No custom units yet</h6>
                    <p class="text-muted small mb-3">Create specific packaging like Boxes, Cases, or Sacks.</p>
                    <button class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm" onclick="openAddUnitModal(${variation_id}, currentVariationPrices); listModal.hide();">
                        <i class="fa-solid fa-plus me-1"></i> Create First Unit
                    </button>
                </td>
            </tr>`;
            return;
        }

        let html = '';
        units.forEach(u => {
            let jsonStr = encodeURIComponent(JSON.stringify(u));
            const descSnippet = u.description
                ? `<span class="text-muted" style="font-size:0.8rem;" title="${u.description.replace(/"/g, '&quot;')}">${u.description.length > 40 ? u.description.substring(0, 40) + '…' : u.description}</span>`
                : `<span class="text-muted fst-italic" style="font-size:0.8rem;">—</span>`;
            html += `<tr class="bg-white">
            <td class="ps-3 fw-bold text-dark py-3">${u.unit_name}</td>
            <td class="py-3"><span class="badge bg-primary bg-opacity-10 text-primary border border-primary fw-bold px-2 py-1 shadow-sm"><i class="fa-solid fa-xmark small me-1"></i>${u.multiplier}</span></td>
            <td class="small text-muted py-3">${u.barcode ? '<i class="fa-solid fa-barcode me-1"></i>' + u.barcode : '<i>No Barcode</i>'}</td>
            <td class="py-3">${descSnippet}</td>
            <td class="fw-medium text-success py-3">₱${parseFloat(u.price_retail).toFixed(2)}</td>
            <td class="fw-medium text-primary py-3">₱${parseFloat(u.price_wholesale).toFixed(2)}</td>
            <td class="fw-medium text-warning py-3">₱${parseFloat(u.price_dealer).toFixed(2)}</td>
            <td class="text-end pe-3 py-3">
                <button class="btn btn-sm btn-light border text-primary me-1 rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;" onclick="editUnit('${jsonStr}')" title="Edit Unit"><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-sm btn-light border text-danger rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;" onclick="deleteUnit(${u.id}, ${u.variation_id})" title="Delete Unit"><i class="fa-solid fa-trash"></i></button>
            </td>
        </tr>`;
        });
        tbody.innerHTML = html;
    }

    function openAddUnitModal(variation_id, prices = null) {
        document.getElementById('unitForm').reset();
        document.getElementById('uf_id').value = '';
        document.getElementById('uf_variation_id').value = variation_id;
        document.getElementById('uf_description').value = '';

        const basePrices = prices && typeof prices === 'object' ? prices : { capital: 0, retail: 0, wholesale: 0, dealer: 0 };
        document.getElementById('uf_base_capital').value = basePrices.capital || 0;
        document.getElementById('uf_base_retail').value = basePrices.retail || 0;
        document.getElementById('uf_base_wholesale').value = basePrices.wholesale || 0;
        document.getElementById('uf_base_dealer').value = basePrices.dealer || 0;

        // Reset all locks to locked (follow original)
        resetPriceLocks();

        // Calculate initial auto values (multiplier = 0 at start, shows 0.00)
        recalcAllPrices();

        document.getElementById('unitFormTitle').innerHTML = '<i class="fa-solid fa-box me-2 text-primary"></i>Create New Unit';
        document.getElementById('btnSaveUnit').innerHTML = '<i class="fa-solid fa-check me-1"></i> Save Unit';

        listModal.hide();
        setTimeout(() => { formModal.show(); }, 300);
    }

    function editUnit(encodedJson) {
        const u = JSON.parse(decodeURIComponent(encodedJson));
        document.getElementById('uf_id').value = u.id;
        document.getElementById('uf_variation_id').value = u.variation_id;
        document.getElementById('uf_name').value = u.unit_name;
        document.getElementById('uf_multiplier').value = u.multiplier;
        document.getElementById('uf_barcode').value = u.barcode || '';
        document.getElementById('uf_description').value = u.description || '';

        // Store base prices from the product variation
        document.getElementById('uf_base_capital').value = currentVariationPrices?.capital || 0;
        document.getElementById('uf_base_retail').value = currentVariationPrices?.retail || 0;
        document.getElementById('uf_base_wholesale').value = currentVariationPrices?.wholesale || 0;
        document.getElementById('uf_base_dealer').value = currentVariationPrices?.dealer || 0;

        // Pre-populate the hidden submission fields with existing saved prices
        document.getElementById('uf_capital').value = parseFloat(u.price_capital).toFixed(2);
        document.getElementById('uf_retail').value = parseFloat(u.price_retail).toFixed(2);
        document.getElementById('uf_wholesale').value = parseFloat(u.price_wholesale).toFixed(2);
        document.getElementById('uf_dealer').value = parseFloat(u.price_dealer).toFixed(2);

        // Reset all locks to locked (following original)
        resetPriceLocks();

        const mult = parseFloat(u.multiplier) || 1;
        [['retail', 'retail'], ['wholesale', 'wholesale'], ['dealer', 'dealer']].forEach(([tier, baseKey]) => {
            const basePrice = parseFloat(currentVariationPrices?.[baseKey] || 0);
            const autoPrice = parseFloat((basePrice * mult).toFixed(2));
            const savedPrice = parseFloat(parseFloat(u['price_' + tier]).toFixed(2));
            const input = document.getElementById('price_' + tier);

            if (Math.abs(savedPrice - autoPrice) > 0.001) {
                // Price was customized — show as unlocked
                togglePriceLock(tier); // will switch to unlocked
                if (input) input.value = (savedPrice / mult).toFixed(2); // Set the per-piece value
            } else {
                // Price is following original, display base price in input
                if (input) input.value = basePrice.toFixed(2);
            }
        });

        // Recalc all after setting locks and input values
        recalcAllPrices();

        document.getElementById('unitFormTitle').innerHTML = '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Custom Unit';
        document.getElementById('btnSaveUnit').innerHTML = '<i class="fa-solid fa-check me-1"></i> Save Changes';

        listModal.hide();
        setTimeout(() => { formModal.show(); }, 300);
    }

    function saveUnit(e) {
        e.preventDefault();
        const id = document.getElementById('uf_id').value;
        const variation_id = document.getElementById('uf_variation_id').value;

        // Final recalc for all tiers before submitting (handles both locked & unlocked)
        recalcAllPrices();

        // Validate: retail must be > 0
        const retVal = parseFloat(document.getElementById('uf_retail').value) || 0;
        if (retVal <= 0) {
            showToast('Please set a Retail price greater than 0.', 'error');
            return;
        }

        const data = new URLSearchParams();
        if (id) data.append('id', id);
        data.append('variation_id', variation_id);
        data.append('unit_name', document.getElementById('uf_name').value);
        data.append('multiplier', document.getElementById('uf_multiplier').value);
        data.append('barcode', document.getElementById('uf_barcode').value);
        data.append('description', document.getElementById('uf_description').value);
        data.append('price_capital', document.getElementById('uf_capital').value);
        data.append('price_retail', document.getElementById('uf_retail').value);
        data.append('price_wholesale', document.getElementById('uf_wholesale').value);
        data.append('price_dealer', document.getElementById('uf_dealer').value);

        const endpoint = id ? '../../api/units/update.php' : '../../api/units/create.php';
        const btn = document.getElementById('btnSaveUnit');
        const originalBtnHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Saving...';

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: data.toString()
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    formModal.hide();
                    showToast(id ? 'Unit updated successfully!' : 'New unit created successfully!');
                    // Reload Main Page to reflect updated Custom Unit Count
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(res.message || 'Error saving unit', 'error');
                }
            }).catch(() => {
                showToast('Server request failed. Please check connection.', 'error');
            }).finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            });
    }

    function deleteUnit(id, variation_id) {
        showConfirm("Are you sure you want to delete this packaging unit?", () => {
            const data = new URLSearchParams();
            data.append('id', id);

            fetch('../../api/units/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data.toString()
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast('Unit deleted successfully!');
                        // Wait briefly so toast is seen, then reload page to update the main table badge
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast(res.message || 'Error deleting unit', 'error');
                    }
                }).catch(() => showToast('Request failed. Please try again.', 'error'));
        });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>