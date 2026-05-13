<?php
// views/system/roles.php - Role & Permissions Manager
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings')) {
    denyAccess("You do not have permission to manage roles.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

// Fetch dynamic roles from the database
$db = new Database();
$conn = $db->getConnection();
$rolesStmt = $conn->query("SELECT role_slug, role_name, is_system FROM roles ORDER BY role_name ASC");
$fetchedRoles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

$managedRoles = [];
$roleSystemFlags = [];
foreach ($fetchedRoles as $r) {
    $managedRoles[$r['role_slug']] = $r['role_name'];
    $roleSystemFlags[$r['role_slug']] = (bool) $r['is_system'];
}
?>

<style>
    .perm-card {
        border-radius: 12px;
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        margin-bottom: 20px;
    }

    .perm-header {
        background-color: var(--bg-surface);
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        border-radius: 12px 12px 0 0;
    }

    .table-permissions {
        margin-bottom: 0;
    }

    .table-permissions th {
        background-color: var(--bg-surface-hover);
        color: var(--text-secondary);
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--border-color);
    }

    .table-permissions td,
    .table-permissions th {
        padding: 12px 20px;
        vertical-align: middle;
    }

    .role-col {
        width: 120px;
        text-align: center;
    }

    .form-check-input {
        cursor: pointer;
        width: 1.2rem;
        height: 1.2rem;
    }
</style>

<div class="container-fluid p-3 p-lg-4">

    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h4 class="fw-bold mb-1">
                <i class="fa-solid fa-user-shield text-primary me-2"></i>Roles & Permissions
            </h4>
            <p class="text-muted mb-0 small">Control exactly what each user role is allowed to do in the system.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                <i class="fa-solid fa-plus me-1"></i> Add Custom Role
            </button>
            <button class="btn btn-outline-secondary" onclick="PermissionsApp.load()">
                <i class="fa-solid fa-rotate-right me-1"></i> Discard
            </button>
            <button class="btn btn-primary" onclick="PermissionsApp.save()" id="btn-save">
                <i class="fa-solid fa-floppy-disk me-1"></i> Save Perms
            </button>
        </div>
    </div>

    <!-- Alert / Info -->
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
        <i class="fa-solid fa-circle-info fs-4 me-3"></i>
        <div>
            <strong>Note:</strong> The <strong>Admin</strong> role inherently has full access to all system features and
            cannot be restricted here. Changes made below will take effect the next time users in those roles log in.
        </div>
    </div>

    <!-- Permissions Container -->
    <div id="permissions-container">
        <!-- Filled by JS -->
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="mt-2 text-muted">Loading permissions matrix...</div>
        </div>
    </div>

</div>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus text-success me-2"></i>Create Custom
                    Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">Role Name</label>
                    <input type="text" id="newRoleName" class="form-control form-control-lg"
                        placeholder="e.g. Supervisor">
                    <div class="form-text">Role names must be unique.</div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success fw-bold px-4" onclick="PermissionsApp.createRole()"
                    id="btn-create-role">
                    Create Role
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const PermissionsApp = {
        roles: <?= json_encode($managedRoles) ?>,
        systemFlags: <?= json_encode($roleSystemFlags) ?>,

        init() {
            this.load();
        },

        async load() {
            const container = document.getElementById('permissions-container');
            try {
                const res = await fetch('../../api/system/get_role_permissions.php');
                const data = await res.json();

                if (data.success) {
                    this.render(data.grouped_permissions, data.role_permissions);
                } else {
                    container.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                }
            } catch (e) {
                container.innerHTML = `<div class="alert alert-danger">Network error loading permissions.</div>`;
            }
        },

        render(groupedPerms, currentMappings) {
            const container = document.getElementById('permissions-container');
            let html = '';

            for (const [moduleName, perms] of Object.entries(groupedPerms)) {
                // Card for each Module
                html += `
            <div class="card perm-card">
                <div class="perm-header">
                    <h5 class="mb-0 fw-bold text-primary">
                        <i class="fa-solid fa-cube me-2"></i>${moduleName}
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-permissions table-hover">
                        <thead>
                            <tr>
                                <th>Permission</th>
                                ${Object.keys(this.roles).map(roleSlug => {
                    const roleName = this.roles[roleSlug];
                    const isSystem = this.systemFlags[roleSlug];
                    const deleteBtn = !isSystem ? `
                                        <button class="btn btn-sm btn-outline-danger py-0 px-2" title="Delete custom role" onclick="PermissionsApp.deleteRole('${roleSlug}')">
                                            <i class="fa-solid fa-trash-can" style="font-size: 0.85rem;"></i>
                                        </button>
                                    ` : '';
                    return `
                                    <th class="role-col">
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <span>${this.escapeHtml(roleName)}</span>
                                            ${deleteBtn}
                                        </div>
                                    </th>`;
                }).join('')}
                            </tr>
                        </thead>
                        <tbody>
            `;

                perms.forEach(perm => {
                    html += `
                    <tr>
                        <td>
                            <div class="fw-bold">${this.escapeHtml(perm.name)}</div>
                            <div class="small text-muted">${this.escapeHtml(perm.description || '')}</div>
                        </td>
                `;

                    // Columns for each role
                    for (const roleKey of Object.keys(this.roles)) {
                        // Check if this role currently has this permission
                        const hasPerm = currentMappings[roleKey] && currentMappings[roleKey].includes(perm.slug);
                        const checked = hasPerm ? 'checked' : '';

                        html += `
                        <td class="role-col">
                            <input class="form-check-input perm-checkbox" type="checkbox" 
                                   data-role="${roleKey}" value="${perm.slug}" ${checked}>
                        </td>
                    `;
                    }

                    html += `</tr>`;
                });

                html += `
                        </tbody>
                    </table>
                </div>
            </div>
            `;
            }

            container.innerHTML = html;
        },

        async save() {
            const btn = document.getElementById('btn-save');
            const originalHtml = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

            // Gather all checked boxes
            const mappings = {};
            Object.keys(this.roles).forEach(r => mappings[r] = []);

            document.querySelectorAll('.perm-checkbox:checked').forEach(cb => {
                const role = cb.getAttribute('data-role');
                const slug = cb.value;
                if (mappings[role]) mappings[role].push(slug);
            });

            try {
                const res = await fetch('../../api/system/save_role_permissions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ role_permissions: mappings })
                });
                const data = await res.json();

                if (data.success) {
                    this.showToast('Permissions saved successfully!', 'success');
                } else {
                    this.showToast('Error: ' + data.error, 'danger');
                }
            } catch (e) {
                this.showToast('Network error while saving', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        },

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showToast(message, type = 'info') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'position-fixed bottom-0 end-0 p-3';
                container.style.zIndex = '1100';
                document.body.appendChild(container);
            }
            const toastEl = document.createElement('div');
            toastEl.className = `toast align-items-center text-bg-${type} border-0 shadow-lg show`;
            toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
            container.appendChild(toastEl);
            setTimeout(() => toastEl.remove(), 3000);
        },

        async createRole() {
            const nameInput = document.getElementById('newRoleName');
            const roleName = nameInput.value.trim();
            const btn = document.getElementById('btn-create-role');

            if (!roleName) return this.showToast('Please enter a role name', 'warning');

            btn.disabled = true;
            btn.innerHTML = 'Creating...';

            try {
                const res = await fetch('../../api/system/create_role.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ role_name: roleName })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    this.showToast(data.error || 'Failed to create role', 'danger');
                    btn.disabled = false;
                    btn.innerHTML = 'Create Role';
                }
            } catch (e) {
                this.showToast('Network error', 'danger');
                btn.disabled = false;
                btn.innerHTML = 'Create Role';
            }
        },

        async deleteRole(slug) {
            if (!confirm(`Are you sure you want to permanently delete the custom role "${this.roles[slug]}"? Any users with this role will lose their custom access immediately until reassigned.`)) return;

            try {
                const res = await fetch('../../api/system/delete_role.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ role_slug: slug })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    this.showToast(data.error || 'Failed to delete role', 'danger');
                }
            } catch (e) {
                this.showToast('Network error', 'danger');
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => PermissionsApp.init());
</script>

<?php require_once '../../includes/footer.php'; ?>