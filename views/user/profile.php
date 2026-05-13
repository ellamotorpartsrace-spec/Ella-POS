<?php
// views/user/profile.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$page_title = "My Profile";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    /* Profile Page - Premium Makeover */
    .profile-page {
        animation: ellaFadeIn 0.5s ease-out;
        max-width: 1200px;
        margin: 0 auto;
        padding-bottom: 50px;
    }

    /* Hero Header */
    .profile-hero {
        background: linear-gradient(135deg, var(--primary-color) 0%, #8b5cf6 100%);
        border-radius: 30px;
        padding: 40px;
        margin-bottom: 30px;
        color: white;
        display: flex;
        align-items: center;
        gap: 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 15px 35px rgba(99, 102, 241, 0.25);
    }

    .profile-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -20%;
        width: 100%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
        transform: rotate(-15deg);
    }

    .hero-avatar {
        width: 120px;
        height: 120px;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        border: 4px solid rgba(255, 255, 255, 0.4);
        border-radius: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3.5rem;
        font-weight: 800;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        z-index: 1;
    }

    .hero-content {
        z-index: 1;
    }

    .hero-name {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 4px;
        letter-spacing: -0.02em;
    }

    .hero-role {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.15);
        padding: 6px 16px;
        border-radius: 100px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        backdrop-filter: blur(5px);
    }

    /* Cards */
    .profile-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        box-shadow: var(--card-shadow);
        height: 100%;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .profile-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
    }

    .card-header-premium {
        padding: 24px 28px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .card-header-premium i {
        width: 40px;
        height: 40px;
        background: var(--primary-light);
        color: var(--primary-color);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    .card-header-premium h5 {
        margin: 0;
        font-weight: 800;
        color: var(--text-primary);
        font-size: 1.1rem;
    }

    .card-body-premium {
        padding: 28px;
    }

    /* Form Elements */
    .form-group-modern {
        margin-bottom: 24px;
    }

    .label-modern {
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 10px;
        display: block;
    }

    .form-control-modern {
        background: var(--bg-surface);
        border: 2px solid var(--border-color);
        border-radius: 16px;
        padding: 12px 18px;
        color: var(--text-primary);
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .form-control-modern:focus {
        background: var(--card-bg);
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px var(--primary-light);
        outline: none;
    }

    .alert-modern {
        border-radius: 16px;
        border: none;
        padding: 16px 20px;
        font-size: 0.85rem;
        font-weight: 500;
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }

    .alert-modern.info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .alert-modern.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

    /* Printer Details Container */
    #pref_direct_settings {
        margin-top: 20px;
        padding: 20px;
        background: var(--bg-surface);
        border-radius: 20px;
        border: 2px dashed var(--border-color);
        transition: all 0.3s ease;
    }

    /* Save Button */
    .save-btn {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 14px 40px;
        border-radius: 18px;
        font-weight: 800;
        font-size: 1rem;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }

    .save-btn:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 12px 25px rgba(59, 130, 246, 0.4);
    }

    .save-btn:active {
        transform: translateY(-1px);
    }

    @media (max-width: 768px) {
        .profile-hero { padding: 30px; flex-direction: column; text-align: center; }
        .hero-avatar { width: 100px; height: 100px; font-size: 2.5rem; }
        .hero-name { font-size: 1.5rem; }
    }
</style>

<div class="profile-page">
    <!-- Profile Hero -->
    <div class="profile-hero">
        <div class="hero-avatar">
            <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="hero-content">
            <div class="hero-name"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></div>
            <div class="hero-role">
                <i class="fa-solid fa-crown"></i>
                <?= htmlspecialchars($_SESSION['role'] ?? 'User') ?>
            </div>
        </div>
    </div>

    <form id="profileForm" autocomplete="off">
        <div class="row g-4">
            <!-- Left: Security -->
            <div class="col-12 col-lg-6">
                <div class="profile-card">
                    <div class="card-header-premium">
                        <i class="fa-solid fa-shield-check"></i>
                        <h5>Security & Account</h5>
                    </div>
                    <div class="card-body-premium">
                        <div class="form-group-modern">
                            <label class="label-modern">Display Name</label>
                            <input type="text" class="form-control-modern w-100" name="username"
                                value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>" required>
                        </div>

                        <div class="alert-modern info mb-4">
                            <i class="fa-solid fa-circle-info mt-1"></i>
                            <div>Leave password fields blank if you do not want to change it.</div>
                        </div>

                        <div class="form-group-modern">
                            <label class="label-modern">New Password</label>
                            <input type="password" class="form-control-modern w-100" id="new_password" name="new_password"
                                placeholder="••••••••" autocomplete="new-password">
                        </div>
                        <div class="form-group-modern mb-0">
                            <label class="label-modern">Confirm Password</label>
                            <input type="password" class="form-control-modern w-100" id="confirm_password"
                                placeholder="••••••••" autocomplete="new-password">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Preferences -->
            <div class="col-12 col-lg-6">
                <div class="profile-card">
                    <div class="card-header-premium">
                        <i class="fa-solid fa-print"></i>
                        <h5>Device Preferences</h5>
                    </div>
                    <div class="card-body-premium">
                        <div class="alert-modern warning mb-4">
                            <i class="fa-solid fa-triangle-exclamation mt-1"></i>
                            <div>These settings only apply to THIS device when you are logged in.</div>
                        </div>

                        <div class="form-group-modern">
                            <label class="label-modern">Printer Mode</label>
                            <select class="form-control-modern w-100" id="pref_printer_mode"
                                name="preferences[printer_mode_override]">
                                <option value="">-- Follow Global Settings --</option>
                                <option value="browser">Browser Print Dialog</option>
                                <option value="direct">Direct to Printer (ESC/POS)</option>
                                <option value="rawbt">Android RawBT App</option>
                            </select>
                        </div>

                        <!-- Direct Settings -->
                        <div id="pref_direct_settings" style="display:none;">
                            <div class="form-group-modern">
                                <label class="label-modern">Connection Protocol</label>
                                <select class="form-control-modern w-100" id="pref_printer_connection"
                                    name="preferences[printer_connection_override]">
                                    <option value="">-- Follow Global --</option>
                                    <option value="network">Network (LAN/Wi-Fi)</option>
                                    <option value="usb_shared">USB (Shared)</option>
                                </select>
                            </div>
                            <div class="form-group-modern mb-0">
                                <label class="label-modern">Printer Address / IP</label>
                                <input type="text" class="form-control-modern w-100" id="pref_printer_address"
                                    name="preferences[printer_address_override]" placeholder="e.g. 192.168.1.100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="text-center mt-5">
            <button type="submit" class="save-btn">
                <i class="fa-solid fa-sparkles"></i>
                Save Profile Changes
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {

        // --- Initialize UI with current Prefs ---
        const prefs = window.USER_PREFERENCES || {};

        const modeSelect = document.getElementById('pref_printer_mode');
        const directSettings = document.getElementById('pref_direct_settings');
        const connSelect = document.getElementById('pref_printer_connection');
        const addrInput = document.getElementById('pref_printer_address');

        // Populate existing values
        if (prefs.printer_mode_override) modeSelect.value = prefs.printer_mode_override;
        if (prefs.printer_connection_override) connSelect.value = prefs.printer_connection_override;
        if (prefs.printer_address_override) addrInput.value = prefs.printer_address_override;

        // Toggle logic for direct mode wrapper
        const toggleDirectSettings = () => {
            directSettings.style.display = modeSelect.value === 'direct' ? 'block' : 'none';
        };

        modeSelect.addEventListener('change', toggleDirectSettings);
        toggleDirectSettings(); // run on load

        // --- Form Submission ---
        document.getElementById('profileForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const pwd1 = document.getElementById('new_password').value;
            const pwd2 = document.getElementById('confirm_password').value;

            if (pwd1 !== pwd2) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Passwords do not match.' });
                return;
            }

            const formData = new FormData(e.target);

            // Build nested JSON payload matching the PHP script expectation
            const payload = {
                username: formData.get('username'),
                new_password: pwd1,
                preferences: {
                    printer_mode_override: formData.get('preferences[printer_mode_override]'),
                    printer_connection_override: formData.get('preferences[printer_connection_override]'),
                    printer_address_override: formData.get('preferences[printer_address_override]')
                }
            };

            try {
                const res = await fetch('../../api/users/update_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    // Update global JS variable instantly without refresh
                    window.USER_PREFERENCES = data.session_preferences;

                    // Clear password fields for security
                    document.getElementById('new_password').value = '';
                    document.getElementById('confirm_password').value = '';

                    Swal.fire({
                        icon: 'success',
                        title: 'Settings Saved',
                        text: 'Your local device preferences have been updated!',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    throw new Error(data.error || 'Failed to update profile');
                }
            } catch (error) {
                console.error(error);
                Swal.fire({ icon: 'error', title: 'Error', html: String(error.message) });
            }
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>