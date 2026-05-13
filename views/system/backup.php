<?php
// views/system/backup.php - Backup Control Panel
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Admin only
// Admin only
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings')) {
    header('Location: ' . BASE_URL . 'views/dashboard/index.php');
    exit;
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    .backup-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .backup-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
    }

    .status-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
    }

    .status-indicator.success {
        background: #22c55e;
        box-shadow: 0 0 8px rgba(34, 197, 94, 0.5);
    }

    .status-indicator.warning {
        background: #eab308;
        box-shadow: 0 0 8px rgba(234, 179, 8, 0.5);
    }

    .status-indicator.danger {
        background: #ef4444;
        box-shadow: 0 0 8px rgba(239, 68, 68, 0.5);
    }

    .action-btn {
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.2s;
    }

    .action-btn:hover {
        transform: scale(1.02);
    }

    .log-container {
        background: #1e293b;
        color: #e2e8f0;
        font-family: 'SF Mono', 'Fira Code', monospace;
        font-size: 0.85rem;
        border-radius: 8px;
        padding: 16px;
        max-height: 300px;
        overflow-y: auto;
    }

    .log-line {
        margin-bottom: 4px;
        line-height: 1.5;
    }

    .log-line.success {
        color: #4ade80;
    }

    .log-line.error {
        color: #f87171;
    }

    .log-line.warning {
        color: #fcd34d;
    }

    .backup-file-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: background 0.15s;
    }

    .backup-file-row:hover {
        background: rgba(59, 130, 246, 0.05);
    }

    .backup-file-row:last-child {
        border-bottom: none;
    }

    .file-size {
        font-size: 0.8rem;
        color: #64748b;
    }

    .config-badge {
        font-size: 0.75rem;
        padding: 4px 10px;
        border-radius: 20px;
    }

    .spinner-run {
        display: none;
    }

    .btn-running .spinner-run {
        display: inline-block;
    }

    .btn-running .btn-text {
        display: none;
    }
</style>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-dark mb-0">
            <i class="fa-solid fa-database text-primary me-2"></i>Backup & Recovery
        </h4>
        <span class="badge bg-danger">Admin Only</span>
    </div>

    <!-- System Status Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card backup-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <span class="status-indicator" id="binlogStatus"></span>
                        <h6 class="mb-0">Binary Logging</h6>
                    </div>
                    <p class="text-muted small mb-2" id="binlogText">Checking...</p>
                    <span class="config-badge bg-light text-secondary" id="binlogConfig"></span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card backup-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <span class="status-indicator" id="lastBackupStatus"></span>
                        <h6 class="mb-0">Last Backup</h6>
                    </div>
                    <p class="text-muted small mb-2" id="lastBackupText">Checking...</p>
                    <span class="config-badge bg-light text-secondary" id="lastBackupSize"></span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card backup-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <span class="status-indicator" id="dbSizeStatus"></span>
                        <h6 class="mb-0">Database Size</h6>
                    </div>
                    <p class="text-muted small mb-2" id="dbSizeText">Checking...</p>
                    <span class="config-badge bg-light text-secondary" id="dbTableCount"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card backup-card h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h5 class="fw-bold">
                        <i class="fa-solid fa-download text-primary me-2"></i>Create Backup
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Create an immediate full backup of the database. The backup will be compressed and saved to the
                        backups folder.
                    </p>
                    <button class="btn btn-primary action-btn w-100" id="btnRunBackup">
                        <span class="spinner-border spinner-border-sm spinner-run me-2"></span>
                        <span class="btn-text"><i class="fa-solid fa-play me-2"></i>Run Backup Now</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card backup-card h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h5 class="fw-bold">
                        <i class="fa-solid fa-vial text-success me-2"></i>Validate Backups
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Run a restore test to verify that the latest backup can be successfully restored. This does not
                        affect live data.
                    </p>
                    <button class="btn btn-success action-btn w-100" id="btnRunTest">
                        <span class="spinner-border spinner-border-sm spinner-run me-2"></span>
                        <span class="btn-text"><i class="fa-solid fa-check-double me-2"></i>Run Restore Test</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Backup Files & Logs Row -->
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card backup-card h-100">
                <div
                    class="card-header bg-transparent border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-folder-open text-warning me-2"></i>Available Backups
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary" id="btnRefreshFiles">
                        <i class="fa-solid fa-sync"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div id="backupFilesList" style="max-height: 350px; overflow-y: auto;">
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-spinner fa-spin"></i> Loading...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card backup-card h-100">
                <div
                    class="card-header bg-transparent border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-terminal text-info me-2"></i>Operation Log
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary" id="btnClearLog">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="log-container" id="logContainer">
                        <div class="log-line">Ready. Waiting for commands...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recovery Info Section -->
    <div class="row g-3 mt-2">
        <div class="col-12">
            <div class="card backup-card border-warning">
                <div class="card-header bg-warning bg-opacity-10 border-0 pt-4 pb-0">
                    <h5 class="fw-bold text-warning">
                        <i class="fa-solid fa-circle-info me-2"></i>Manual Recovery Guide
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="fa-solid fa-database me-2"></i>
                        <strong>How backups work:</strong> Backups are generated as compressed <code>.zip</code> files
                        containing a full SQL dump of your database. Download a backup file and import it via
                        <strong>phpMyAdmin</strong> or any MySQL client to restore your data.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="fw-semibold mb-2"><i class="fa-solid fa-list-ol me-2 text-primary"></i>Steps to Restore</h6>
                            <ol class="text-muted small mb-0">
                                <li class="mb-1">Click <strong>Run Backup Now</strong> to create a fresh backup.</li>
                                <li class="mb-1">Download the latest <code>.zip</code> file from <strong>Available Backups</strong>.</li>
                                <li class="mb-1">Extract the <code>.sql</code> file from the zip.</li>
                                <li class="mb-1">Open <strong>phpMyAdmin</strong> on your hosting control panel.</li>
                                <li class="mb-1">Select the database and click <strong>Import</strong>.</li>
                                <li>Upload the <code>.sql</code> file and click <strong>Go</strong>.</li>
                            </ol>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-semibold mb-2"><i class="fa-solid fa-shield-halved me-2 text-success"></i>Best Practices</h6>
                            <ul class="text-muted small mb-0">
                                <li class="mb-1">Run a backup <strong>before any major update</strong>.</li>
                                <li class="mb-1">Download and store backups <strong>locally or on cloud storage</strong>.</li>
                                <li class="mb-1">Use <strong>Validate Backups</strong> to confirm integrity.</li>
                                <li>Backup files are automatically kept to <strong>the latest 10</strong> to save disk space.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const API_BASE = '<?= BASE_URL ?>api/system/backup.php';

        // Load initial status
        loadStatus();
        loadBackupFiles();

        // Event Listeners
        document.getElementById('btnRunBackup').addEventListener('click', runBackup);
        document.getElementById('btnRunTest').addEventListener('click', runRestoreTest);
        document.getElementById('btnRefreshFiles').addEventListener('click', loadBackupFiles);
        document.getElementById('btnClearLog').addEventListener('click', clearLog);

        async function loadStatus() {
            try {
                const response = await fetch(`${API_BASE}?action=status`);
                const data = await response.json();

                if (data.success) {
                    // Binary Logging Status
                    const binlogEl = document.getElementById('binlogStatus');
                    const binlogText = document.getElementById('binlogText');
                    const binlogConfig = document.getElementById('binlogConfig');

                    if (data.binlog_enabled) {
                        binlogEl.className = 'status-indicator success';
                        binlogText.textContent = 'Binary logging is enabled for PITR';
                        binlogConfig.textContent = 'PITR Ready';
                        binlogConfig.className = 'config-badge bg-success-subtle text-success';
                    } else {
                        binlogEl.className = 'status-indicator warning';
                        binlogText.textContent = 'Binary logging is disabled';
                        binlogConfig.textContent = 'Backups Only';
                        binlogConfig.className = 'config-badge bg-warning-subtle text-warning';
                    }

                    // Last Backup Status
                    const lastBackupEl = document.getElementById('lastBackupStatus');
                    const lastBackupText = document.getElementById('lastBackupText');
                    const lastBackupSize = document.getElementById('lastBackupSize');

                    if (data.last_backup) {
                        const hoursSince = data.hours_since_backup;
                        if (hoursSince < 24) {
                            lastBackupEl.className = 'status-indicator success';
                        } else if (hoursSince < 48) {
                            lastBackupEl.className = 'status-indicator warning';
                        } else {
                            lastBackupEl.className = 'status-indicator danger';
                        }
                        lastBackupText.textContent = data.last_backup.name;
                        lastBackupSize.textContent = formatBytes(data.last_backup.size);
                    } else {
                        lastBackupEl.className = 'status-indicator danger';
                        lastBackupText.textContent = 'No backups found!';
                        lastBackupSize.textContent = 'Run backup now';
                        lastBackupSize.className = 'config-badge bg-danger-subtle text-danger';
                    }

                    // Database Size
                    const dbSizeEl = document.getElementById('dbSizeStatus');
                    const dbSizeText = document.getElementById('dbSizeText');
                    const dbTableCount = document.getElementById('dbTableCount');

                    dbSizeEl.className = 'status-indicator success';
                    dbSizeText.textContent = formatBytes(data.db_size);
                    dbTableCount.textContent = `${data.table_count} tables`;
                }
            } catch (error) {
                console.error('Error loading status:', error);
                addLog('Error loading status: ' + error.message, 'error');
            }
        }

        async function loadBackupFiles() {
            try {
                const response = await fetch(`${API_BASE}?action=list_files`);
                const data = await response.json();

                const container = document.getElementById('backupFilesList');

                if (data.success && data.files.length > 0) {
                    container.innerHTML = data.files.map(file => `
                    <div class="backup-file-row">
                        <div>
                            <i class="fa-solid fa-file-zipper text-warning me-2"></i>
                            <span class="fw-medium">${escapeHtml(file.name)}</span>
                            <br>
                            <small class="text-muted">${file.date}</small>
                        </div>
                        <div class="text-end">
                            <span class="file-size">${formatBytes(file.size)}</span>
                            <a href="${API_BASE}?action=download&file=${encodeURIComponent(file.name)}" 
                               class="btn btn-sm btn-outline-primary ms-2" title="Download">
                                <i class="fa-solid fa-download"></i>
                            </a>
                        </div>
                    </div>
                `).join('');
                } else {
                    container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-folder-open fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">No backup files found</p>
                    </div>
                `;
                }
            } catch (error) {
                console.error('Error loading files:', error);
            }
        }

        async function runBackup() {
            const btn = document.getElementById('btnRunBackup');
            btn.classList.add('btn-running');
            btn.disabled = true;

            addLog('Starting backup...', 'info');

            try {
                const response = await fetch(`${API_BASE}?action=run_backup`);
                const data = await response.json();

                if (data.success) {
                    addLog('✓ Backup completed: ' + data.filename, 'success');
                    addLog('  Size: ' + formatBytes(data.size), 'success');
                    loadStatus();
                    loadBackupFiles();
                } else {
                    addLog('✗ Backup failed: ' + data.error, 'error');
                }
            } catch (error) {
                addLog('✗ Error: ' + error.message, 'error');
            } finally {
                btn.classList.remove('btn-running');
                btn.disabled = false;
            }
        }

        async function runRestoreTest() {
            const btn = document.getElementById('btnRunTest');
            btn.classList.add('btn-running');
            btn.disabled = true;

            addLog('Starting restore test...', 'info');
            addLog('This may take a few minutes...', 'warning');

            try {
                const response = await fetch(`${API_BASE}?action=run_test`);
                const data = await response.json();

                if (data.success) {
                    addLog('✓ Restore test completed', 'success');
                    if (data.results) {
                        data.results.forEach(line => {
                            addLog('  ' + line, line.includes('PASS') ? 'success' :
                                line.includes('FAIL') ? 'error' : 'info');
                        });
                    }
                } else {
                    addLog('✗ Restore test failed: ' + data.error, 'error');
                }
            } catch (error) {
                addLog('✗ Error: ' + error.message, 'error');
            } finally {
                btn.classList.remove('btn-running');
                btn.disabled = false;
            }
        }

        function addLog(message, type = 'info') {
            const container = document.getElementById('logContainer');
            const timestamp = new Date().toLocaleTimeString();
            const line = document.createElement('div');
            line.className = `log-line ${type}`;
            line.textContent = `[${timestamp}] ${message}`;
            container.appendChild(line);
            container.scrollTop = container.scrollHeight;
        }

        function clearLog() {
            document.getElementById('logContainer').innerHTML =
                '<div class="log-line">Log cleared. Ready for new commands...</div>';
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>