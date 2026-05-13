<?php
// views/payables/view.php
// Detailed view and management of a single supplier payment

require_once '../../includes/auth.php';
require_once '../../config/config.php';
require_once '../../config/database.php';

requirePermission('view_payables');

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$payment_id = intval($_GET['id']);
$db = new Database();
$conn = $db->getConnection();

// Fetch Payment Info
$stmt = $conn->prepare("
    SELECT sp.*, 
           po.po_ref, po.status as po_status, po.payment_status as po_payment_status,
           s.supplier_name, s.contact_person, s.phone as supplier_phone, s.email as supplier_email,
           (sp.amount - sp.paid_amount) as balance,
           u.full_name as created_by_name
    FROM supplier_payments sp
    JOIN purchase_orders po ON sp.po_id = po.po_id
    JOIN suppliers s ON sp.supplier_id = s.supplier_id
    JOIN users u ON sp.created_by = u.id
    WHERE sp.payment_id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    die("Payment record not found.");
}

$balance = floatval($payment['balance']);

// Fetch Payment History
$historyStmt = $conn->prepare("
    SELECT h.*, u.full_name as paid_by_name
    FROM supplier_payment_history h
    LEFT JOIN users u ON h.paid_by = u.id
    WHERE h.supplier_payment_id = ?
    ORDER BY h.paid_at DESC
");
$historyStmt->execute([$payment_id]);
$history = $historyStmt->fetchAll();

// Fetch All Attachments
$attachStmt = $conn->prepare("
    SELECT a.*, u.full_name as uploader_name
    FROM supplier_payment_attachments a
    LEFT JOIN users u ON a.uploaded_by = u.id
    WHERE a.supplier_payment_id = ?
    ORDER BY a.uploaded_at DESC
");
$attachStmt->execute([$payment_id]);
$attachments = $attachStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payment - Ella POS</title>
    <link href="<?php echo BASE_URL; ?>assets/css/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
    <style>
        .payment-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .payment-header h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .payment-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-box {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }

        .stat-box .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .stat-box .stat-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .stat-box.balance .stat-value {
            color: #fbbf24;
        }

        .info-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            height: 100%;
        }

        .info-card h5 {
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px dashed var(--border-color);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-secondary);
        }

        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
        }

        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 2px solid var(--card-bg);
        }

        .timeline-item.paid::before {
            background: #10b981;
        }

        .timeline-content {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
        }

        .timeline-amount {
            font-size: 1.25rem;
            font-weight: 700;
            color: #10b981;
        }

        .timeline-meta {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        .attachment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
        }

        .attachment-card {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .attachment-card:hover {
            border-color: var(--primary-color);
            transform: scale(1.02);
        }

        .attachment-card img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            cursor: pointer;
        }

        .attachment-info {
            padding: 0.5rem;
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-align: center;
            background: var(--bg-surface);
        }

        .upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .upload-zone:hover {
            border-color: var(--primary-color);
            background: rgba(139, 92, 246, 0.05);
        }

        .upload-zone i {
            font-size: 2.5rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(107, 114, 128, 0.15);
            color: #6b7280;
        }

        .status-partial {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
        }

        .status-paid {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
        }
    </style>
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Payables
                </a>
            </div>

            <!-- Payment Header -->
            <div class="payment-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2><i
                                class="fas fa-file-invoice-dollar me-2"></i><?= htmlspecialchars($payment['supplier_name']) ?>
                        </h2>
                        <p class="mb-0 opacity-75">
                            <span class="badge bg-light text-dark font-monospace me-2"><?= $payment['po_ref'] ?></span>
                            <span class="status-badge status-<?= $payment['payment_status'] ?>">
                                <?= strtoupper($payment['payment_status']) ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-4">
                        <div class="payment-stats">
                            <div class="stat-box">
                                <div class="stat-value">₱<?= number_format($payment['amount'], 2) ?></div>
                                <div class="stat-label">Total Due</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value text-success">₱<?= number_format($payment['paid_amount'], 2) ?>
                                </div>
                                <div class="stat-label">Paid</div>
                            </div>
                            <div class="stat-box balance">
                                <div class="stat-value">₱<?= number_format($balance, 2) ?></div>
                                <div class="stat-label">Balance</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-4 mb-4">
                    <!-- Supplier Info -->
                    <div class="info-card mb-4">
                        <h5><i class="fas fa-building me-2"></i>Supplier Information</h5>
                        <div class="info-row">
                            <span class="info-label">Company</span>
                            <span class="info-value"><?= htmlspecialchars($payment['supplier_name']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Contact</span>
                            <span class="info-value"><?= htmlspecialchars($payment['contact_person'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone</span>
                            <span class="info-value"><?= htmlspecialchars($payment['supplier_phone'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?= htmlspecialchars($payment['supplier_email'] ?? 'N/A') ?></span>
                        </div>
                    </div>

                    <!-- Payment Form -->
                    <?php if ($balance > 0.01): ?>
                        <div class="info-card">
                            <h5><i class="fas fa-plus-circle me-2"></i>Add Payment</h5>
                            <form id="paymentForm">
                                <input type="hidden" name="payment_id" value="<?= $payment_id ?>">

                                <div class="mb-3">
                                    <label class="form-label">Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" step="0.01" class="form-control" name="amount" id="amountInput"
                                            required max="<?= $balance ?>" value="<?= $balance ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <select class="form-select" name="payment_method">
                                        <option value="cash">Cash</option>
                                        <option value="gcash">GCash</option>
                                        <option value="bank">Bank Transfer</option>
                                        <option value="check">Check</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Reference No.</label>
                                    <input type="text" class="form-control" name="reference_no" placeholder="Optional">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="2"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Proof of Payment</label>
                                    <div class="upload-zone" onclick="document.getElementById('proofImages').click()">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p class="text-muted mb-0">Click to upload images</p>
                                        <input type="file" id="proofImages" class="d-none" multiple accept="image/*">
                                    </div>
                                    <div id="imagePreview" class="d-flex flex-wrap gap-2 mt-2"></div>
                                </div>

                                <button type="submit" class="btn btn-success w-100" id="submitBtn">
                                    <i class="fas fa-check-circle me-2"></i>Submit Payment
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="info-card text-center">
                            <div class="py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h5 class="text-success">Fully Paid</h5>
                                <p class="text-muted mb-0">This payment has been completed.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column -->
                <div class="col-lg-8">
                    <!-- Payment History -->
                    <div class="info-card mb-4">
                        <h5><i class="fas fa-history me-2"></i>Payment History</h5>
                        <?php if (count($history) > 0): ?>
                            <div class="timeline">
                                <?php foreach ($history as $h): ?>
                                    <div class="timeline-item paid">
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <div class="timeline-amount">₱<?= number_format($h['amount'], 2) ?></div>
                                                    <span
                                                        class="badge bg-secondary"><?= strtoupper($h['payment_method']) ?></span>
                                                    <?php if ($h['reference_no']): ?>
                                                        <span class="ms-2 text-muted">Ref:
                                                            <?= htmlspecialchars($h['reference_no']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="timeline-meta">
                                                <i
                                                    class="fas fa-user me-1"></i><?= htmlspecialchars($h['paid_by_name'] ?? 'Unknown') ?>
                                                <span class="ms-3"><i
                                                        class="fas fa-clock me-1"></i><?= date('M d, Y g:i A', strtotime($h['paid_at'])) ?></span>
                                            </div>
                                            <?php if ($h['notes']): ?>
                                                <div class="mt-2 small text-muted"><?= htmlspecialchars($h['notes']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p>No payments recorded yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Attachments Gallery -->
                    <div class="info-card">
                        <h5><i class="fas fa-images me-2"></i>Payment Proofs (<?= count($attachments) ?>)</h5>
                        <?php if (count($attachments) > 0): ?>
                            <div class="attachment-grid">
                                <?php foreach ($attachments as $a): ?>
                                    <div class="attachment-card">
                                        <img src="<?= BASE_URL . $a['image_path'] ?>"
                                            onclick="showLightbox('<?= BASE_URL . $a['image_path'] ?>')" alt="Proof">
                                        <div class="attachment-info">
                                            <?= date('M d, Y', strtotime($a['uploaded_at'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-image fa-2x mb-2"></i>
                                <p>No attachments uploaded.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lightbox Modal -->
    <div class="modal fade" id="lightboxModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark border-0">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img id="lightboxImage" src="" class="img-fluid" style="max-height: 80vh;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/css/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const BASE_URL = "<?php echo BASE_URL; ?>";
        const PAYMENT_ID = <?php echo $payment_id; ?>;
        const lightboxModal = new bootstrap.Modal(document.getElementById('lightboxModal'));

        // Show lightbox
        function showLightbox(imageSrc) {
            document.getElementById('lightboxImage').src = imageSrc;
            lightboxModal.show();
        }

        // Image preview
        document.getElementById('proofImages')?.addEventListener('change', function (e) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';

            Array.from(this.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.cssText = 'width: 60px; height: 60px; object-fit: cover; border-radius: 8px;';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            });
        });

        // Form submission
        document.getElementById('paymentForm')?.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            try {
                // 1. Submit payment
                const response = await fetch(`${BASE_URL}api/payables/payables.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.status === 'success') {
                    // 2. Upload images if any
                    const fileInput = document.getElementById('proofImages');
                    if (fileInput.files.length > 0) {
                        const uploadData = new FormData();
                        uploadData.append('supplier_payment_id', PAYMENT_ID);
                        uploadData.append('history_id', result.history_id);
                        for (let i = 0; i < fileInput.files.length; i++) {
                            uploadData.append('images[]', fileInput.files[i]);
                        }

                        await fetch(`${BASE_URL}api/payables/upload_payment_proof.php`, {
                            method: 'POST',
                            body: uploadData
                        });
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Payment Recorded',
                        text: 'The payment has been successfully recorded.',
                        confirmButtonColor: '#8b5cf6'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message
                    });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Submit Payment';
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'System Error',
                    text: 'Could not connect to the server.'
                });
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Submit Payment';
            }
        });
    </script>
</body>

</html>