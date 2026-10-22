<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
if (($_SESSION['role'] ?? '') === 'auditor') {
    header("Location: dashboard.php");
    exit;
}
require_once __DIR__ . '/../../config/db.php';

$username = $_SESSION['username'] ?? 'User';
$role     = $_SESSION['role'] ?? 'admin';
$user_id  = $_SESSION['user_id'];
$active_page = 'settings';
$base_url    = '';

$success_message = '';
$error_message   = '';

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_username)) {
        $error_message = 'Username is required.';
    } elseif (empty($current_password)) {
        $error_message = 'Current password is required to verify changes.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_record = $stmt->fetch();

            if ($user_record) {
                if (!password_verify($current_password, $user_record['password'])) {
                    $error_message = 'Incorrect current password. Changes aborted.';
                } else {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                    $chk->execute([$new_username, $user_id]);
                    if ($chk->fetchColumn() > 0) {
                        $error_message = 'Username is already taken by another account.';
                    } else {
                        $pdo->beginTransaction();
                        $updated = false;

                        if (!empty($new_password)) {
                            if ($new_password !== $confirm_password) {
                                $error_message = 'New passwords do not match.';
                            } else {
                                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $upd = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                                $upd->execute([$new_username, $hashed_password, $user_id]);
                                $updated = true;
                            }
                        } else {
                            $upd = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                            $upd->execute([$new_username, $user_id]);
                            $updated = true;
                        }

                        if ($updated && empty($error_message)) {
                            $pdo->commit();
                            $_SESSION['username'] = $new_username;
                            $username = $new_username;
                            $success_message = 'Account settings updated successfully.';
                        } else {
                            $pdo->rollBack();
                        }
                    }
                }
            } else {
                $error_message = 'User record not found.';
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = 'An error occurred: ' . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare("SELECT username, role, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user_data = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Account Settings – ISO-QA AI System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" />
    <link rel="stylesheet" href="../../assets/css/style.css" />
    <link rel="icon" href="../../assets/image/iso_badge.png">
</head>
<body>

<?php require_once __DIR__ . '/../../components/sidebar.php'; ?>

<div class="main-content">


    <div class="p-4">
        <div class="row justify-content-center">
            <div class="col-xl-8 col-lg-10">
                <div class="chart-card">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div>
                            <h5 class="fw-bold text-dark mb-0">Account Settings</h5>
                        </div>
                    </div>

                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success border-0 rounded-3 d-flex align-items-center gap-2" role="alert" style="background:rgba(16,185,129,0.1);color:#10b981;">
                            <i class="fas fa-check-circle"></i>
                            <div><?= htmlspecialchars($success_message) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger border-0 rounded-3 d-flex align-items-center gap-2" role="alert" style="background:rgba(239,68,68,0.1);color:#ef4444;">
                            <i class="fas fa-exclamation-circle"></i>
                            <div><?= htmlspecialchars($error_message) ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="settings.php" method="POST" class="needs-validation" novalidate>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card p-3 border-0 rounded-3 bg-light" style="background: rgba(248,250,252,0.6) !important; border: 1px solid rgba(226,232,240,0.8) !important;">
                                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-user-circle me-2 text-primary"></i>Profile Details</h6>
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label text-secondary small fw-medium">Username</label>
                                        <input type="text" class="form-control rounded-3 border-slate-200" id="username" name="username" value="<?= htmlspecialchars($current_user_data['username']) ?>" required style="font-size:0.9rem;padding:0.6rem 0.8rem;" />
                                        <div class="invalid-feedback">Please enter a valid username.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label text-secondary small fw-medium d-block">Account Role</label>
                                        <input type="text" class="form-control rounded-3 border-slate-200 text-capitalize" value="<?= htmlspecialchars($current_user_data['role']) ?>" disabled readonly style="font-size:0.9rem;padding:0.6rem 0.8rem;background:#e2e8f0;color:#64748b;" />
                                    </div>

                                    <div class="mb-0">
                                        <label class="form-label text-secondary small fw-medium d-block">Member Since</label>
                                        <input type="text" class="form-control rounded-3 border-slate-200" value="<?= date('F j, Y', strtotime($current_user_data['created_at'])) ?>" disabled readonly style="font-size:0.9rem;padding:0.6rem 0.8rem;background:#e2e8f0;color:#64748b;" />
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card p-3 border-0 rounded-3 bg-light" style="background: rgba(248,250,252,0.6) !important; border: 1px solid rgba(226,232,240,0.8) !important;">
                                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-lock me-2 text-primary"></i>Security Password</h6>

                                    <div class="mb-3">
                                        <label for="new_password" class="form-label text-secondary small fw-medium">New Password</label>
                                        <input type="password" class="form-control rounded-3 border-slate-200" id="new_password" name="new_password" placeholder="Leave blank to keep current" style="font-size:0.9rem;padding:0.6rem 0.8rem;" />
                                    </div>

                                    <div class="mb-0">
                                        <label for="confirm_password" class="form-label text-secondary small fw-medium">Confirm New Password</label>
                                        <input type="password" class="form-control rounded-3 border-slate-200" id="confirm_password" name="confirm_password" placeholder="Leave blank to keep current" style="font-size:0.9rem;padding:0.6rem 0.8rem;" />
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mt-4 pt-2 border-top">
                                <div class="card p-3 border-0 rounded-3 mb-4" style="background: rgba(239,68,68,0.04) !important; border: 1px dashed rgba(239,68,68,0.2) !important;">
                                    <h6 class="fw-bold text-danger mb-2 small"><i class="fas fa-shield-alt me-2"></i>Security Verification Required</h6>
                                    <p class="text-muted small mb-3">You must enter your current account password to authorize these modifications.</p>
                                    
                                    <div class="col-md-6">
                                        <label for="current_password" class="form-label text-danger small fw-semibold">Current Account Password</label>
                                        <input type="password" class="form-control rounded-3 border-danger" id="current_password" name="current_password" required placeholder="Enter current password" style="font-size:0.9rem;padding:0.6rem 0.8rem;" />
                                        <div class="invalid-feedback">Please enter your current password to authorize changes.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-custom rounded-3 px-4 py-2" style="font-size:0.9rem; color:#fff;border:none;"><i class="fas fa-save me-1"></i>Save Changes</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (() => {
        'use strict';
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();
</script>
</body>
</html>
