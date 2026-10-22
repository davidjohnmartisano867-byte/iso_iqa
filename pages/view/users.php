<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
if (in_array($_SESSION['role'] ?? '', ['auditor', 'auditee'])) {
    header("Location: dashboard.php");
    exit;
}
require_once __DIR__ . '/../../config/db.php';

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'edit_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $edit_name = trim($_POST['name'] ?? '');
        $edit_username = trim($_POST['username'] ?? '');
        $edit_password = $_POST['password'] ?? '';

        if ($user_id <= 0 || empty($edit_username)) {
            echo json_encode(['success' => false, 'message' => 'User ID and username are required.']);
            exit;
        }

        try {
            $check_admin = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $check_admin->execute([$user_id]);
            $current_role = $check_admin->fetchColumn();
            if ($current_role === 'admin') {
                echo json_encode(['success' => false, 'message' => 'Admin accounts cannot be modified here.']);
                exit;
            }

            $check_dup = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
            $check_dup->execute([$edit_username, $user_id]);
            if ($check_dup->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Username is already taken by another account.']);
                exit;
            }

            if (!empty($edit_password)) {
                $hashed = password_hash($edit_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, password = ? WHERE id = ?");
                $stmt->execute([$edit_name, $edit_username, $hashed, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ? WHERE id = ?");
                $stmt->execute([$edit_name, $edit_username, $user_id]);
            }

            if ($current_role === 'auditor' && !empty($edit_name)) {
                $stmt_aud = $pdo->prepare("UPDATE auditors SET name = ? WHERE user_id = ?");
                $stmt_aud->execute([$edit_name, $user_id]);
            }

            $fetch_stmt = $pdo->prepare("
                SELECT 
                    u.id, 
                    u.username, 
                    u.name AS user_name,
                    COALESCE(NULLIF(u.name, ''), a.name, o.officer_in_charge, o.name, u.username) AS display_name,
                    u.role
                FROM users u
                LEFT JOIN auditors a ON a.user_id = u.id
                LEFT JOIN offices o ON o.user_id = u.id
                WHERE u.id = ?
            ");
            $fetch_stmt->execute([$user_id]);
            $updated_user = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'user' => $updated_user]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    if ($_POST['action'] === 'delete_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
            exit;
        }

        try {
            $check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $check->execute([$user_id]);
            $user_role = $check->fetchColumn();

            if (!$user_role) {
                echo json_encode(['success' => false, 'message' => 'User not found.']);
                exit;
            }
            if ($user_role === 'admin' || $user_id === (int)$_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'Admin account cannot be deleted.']);
                exit;
            }

            $pdo->beginTransaction();

            $upd_office = $pdo->prepare("UPDATE offices SET user_id = NULL WHERE user_id = ?");
            $upd_office->execute([$user_id]);

            $del_aud = $pdo->prepare("DELETE FROM auditors WHERE user_id = ?");
            $del_aud->execute([$user_id]);

            $del_user = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $del_user->execute([$user_id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'user_id' => $user_id]);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

$username = $_SESSION['username'] ?? 'User';
$role     = $_SESSION['role'] ?? 'admin';
$active_page = 'users';
$base_url    = '';

$query = "
    SELECT 
        u.id, 
        u.username, 
        u.name AS user_name,
        COALESCE(NULLIF(u.name, ''), a.name, o.officer_in_charge, o.name, u.username) AS display_name,
        u.role, 
        u.created_at,
        a.id AS auditor_id,
        a.name AS auditor_name,
        o.id AS office_id,
        o.name AS office_name,
        o.officer_in_charge
    FROM users u
    LEFT JOIN auditors a ON a.user_id = u.id
    LEFT JOIN offices o ON o.user_id = u.id
    WHERE u.role != 'admin'
    ORDER BY display_name ASC
";
$users = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Users Management – ISO-QA AI System</title>
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
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1">Registered User Accounts</h5>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group" style="width: 350px;">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control bg-light border-start-0 ps-0" id="userSearchInput" placeholder="Search users..." />
                    </div>
                    <select class="form-select bg-light border" id="roleFilterSelect" style="font-size: 0.88rem; font-weight: 500; width: auto;">
                        <option value="all">All Roles</option>
                        <option value="auditor">Auditors</option>
                        <option value="auditee">Auditees</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-center">
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        <?php if (empty($users)): ?>
                            <tr id="no-users-row">
                                <td colspan="4" class="text-center text-muted py-4">No registered users found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr id="user-row-<?= $u['id'] ?>">
                                    <td class="fw-semibold user-name-cell"><?= htmlspecialchars($u['display_name']) ?></td>
                                    <td class="text-center">
                                        <span class="text-secondary fw-medium user-username-cell"><?= htmlspecialchars($u['username']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($u['role'] === 'auditor'): ?>
                                            <span class="badge bg-primary-subtle text-primary border-primary fw-semibold px-2 py-1 rounded-2" style="font-size: 0.78rem; background: rgba(37,99,235,0.1) !important; color: #2563eb !important; border: 1px solid rgba(37,99,235,0.2) !important;">Auditor</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border-success fw-semibold px-2 py-1 rounded-2" style="font-size: 0.78rem; background: rgba(16,185,129,0.1) !important; color: #10b981 !important; border: 1px solid rgba(16,185,129,0.2) !important;">Auditee</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-link text-warning p-0 me-3 btn-edit-user" data-id="<?= $u['id'] ?>" data-name="<?= htmlspecialchars($u['user_name'] ?? $u['display_name']) ?>" data-username="<?= htmlspecialchars($u['username']) ?>" title="Edit User">
                                            <i class="fas fa-edit fa-lg"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link text-danger p-0 btn-delete-user" data-id="<?= $u['id'] ?>" data-username="<?= htmlspecialchars($u['username']) ?>" title="Delete User">
                                            <i class="fas fa-trash-alt fa-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/edit_user_modal.php'; ?>

<?php require_once __DIR__ . '/../../components/delete_confirmation_modal.php'; ?>

<?php require_once __DIR__ . '/../../components/notification.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editUserModalEl = document.getElementById('editUserModal');
    const editUserModal = editUserModalEl ? new bootstrap.Modal(editUserModalEl) : null;

    const deleteConfirmationModalEl = document.getElementById('deleteConfirmationModal') || document.getElementById('deleteUserModal');
    const deleteUserModal = deleteConfirmationModalEl ? new bootstrap.Modal(deleteConfirmationModalEl) : null;

    const searchInput = document.getElementById('userSearchInput');
    const roleFilter = document.getElementById('roleFilterSelect');
    let activeDeleteUserId = 0;

    function applyUserFilters() {
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const selectedRole = roleFilter ? roleFilter.value : 'all';
        const rows = document.querySelectorAll('#users-table-body tr');

        rows.forEach(row => {
            if (row.id === 'no-users-row') return;
            const text = row.innerText.toLowerCase();
            const matchesQuery = !query || text.includes(query);
            let matchesRole = true;
            if (selectedRole === 'auditor') {
                matchesRole = text.includes('auditor');
            } else if (selectedRole === 'auditee') {
                matchesRole = text.includes('auditee');
            }

            if (matchesQuery && matchesRole) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', applyUserFilters);
    if (roleFilter) roleFilter.addEventListener('change', applyUserFilters);

    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.btn-edit-user');
        if (editBtn) {
            const id = editBtn.getAttribute('data-id');
            const name = editBtn.getAttribute('data-name');
            const username = editBtn.getAttribute('data-username');

            const idField = document.getElementById('edit_user_id');
            const nameField = document.getElementById('edit_name');
            const usernameField = document.getElementById('edit_username');
            const passwordField = document.getElementById('edit_password');
            const errDiv = document.getElementById('edit-user-error');

            if (idField) idField.value = id;
            if (nameField) nameField.value = name || '';
            if (usernameField) usernameField.value = username;
            if (passwordField) passwordField.value = '';
            if (errDiv) errDiv.classList.add('d-none');

            if (editUserModal) {
                editUserModal.show();
            }
            return;
        }

        const deleteBtn = e.target.closest('.btn-delete-user');
        if (deleteBtn) {
            activeDeleteUserId = deleteBtn.getAttribute('data-id');
            const username = deleteBtn.getAttribute('data-username');
            
            const titleText = document.getElementById('delete-modal-title-text');
            const heading = document.getElementById('delete-modal-heading');
            const message = document.getElementById('delete-modal-message');
            const itemName = document.getElementById('delete-item-name');
            const btnText = document.getElementById('delete-modal-btn-text');

            if (titleText) titleText.textContent = 'Delete User Account';
            if (heading) heading.textContent = 'Confirm Deletion';
            if (message) {
                message.innerHTML = `Are you sure you want to delete user account <strong id="delete-item-name" class="text-dark">${username}</strong>? This will remove their credentials and unbind linked profile records.`;
            } else if (itemName) {
                itemName.innerText = username;
            }
            if (btnText) btnText.textContent = 'Delete User';

            if (deleteUserModal) {
                deleteUserModal.show();
            }
            return;
        }
    });

    const editForm = document.getElementById('edit-user-form');
    const editErr = document.getElementById('edit-user-error');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (editErr) editErr.classList.add('d-none');

            const formData = new FormData(editForm);
            formData.append('action', 'edit_user');

            const submitBtn = editForm.querySelector('button[type="submit"]');
            const orig = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            }

            fetch('users.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = orig;
                }

                if (data.success && data.user) {
                    if (editUserModal) editUserModal.hide();
                    window.showNotification('User updated successfully!', 'success');

                    const row = document.getElementById('user-row-' + data.user.id);
                    if (row) {
                        const nameCell = row.querySelector('.user-name-cell');
                        const usernameCell = row.querySelector('.user-username-cell');
                        const editBtn = row.querySelector('.btn-edit-user');
                        const deleteBtn = row.querySelector('.btn-delete-user');

                        if (nameCell) nameCell.textContent = data.user.display_name;
                        if (usernameCell) usernameCell.textContent = data.user.username;
                        if (editBtn) {
                            editBtn.setAttribute('data-name', data.user.user_name || data.user.display_name);
                            editBtn.setAttribute('data-username', data.user.username);
                        }
                        if (deleteBtn) {
                            deleteBtn.setAttribute('data-username', data.user.username);
                        }

                        row.style.transition = 'background-color 0.4s ease';
                        row.classList.add('table-warning');
                        setTimeout(() => {
                            row.classList.remove('table-warning');
                        }, 1200);
                    }
                } else {
                    if (editErr) {
                        editErr.innerText = data.message || 'Failed to update user.';
                        editErr.classList.remove('d-none');
                    }
                }
            })
            .catch(err => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = orig;
                }
                if (editErr) {
                    editErr.innerText = 'Network error. Please try again.';
                    editErr.classList.remove('d-none');
                }
            });
        });
    }

    const confirmDelBtn = document.getElementById('confirm-delete-btn') || document.getElementById('confirm-delete-user-btn');
    if (confirmDelBtn) {
        confirmDelBtn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('user_id', activeDeleteUserId);

            const btn = this;
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';

            fetch('users.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = orig;

                if (data.success) {
                    if (deleteUserModal) deleteUserModal.hide();
                    window.showNotification('User deleted successfully.', 'success');
                    
                    const row = document.getElementById('user-row-' + activeDeleteUserId);
                    if (row) {
                        row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'scale(0.98)';
                        setTimeout(() => {
                            row.remove();
                            const tbody = document.getElementById('users-table-body');
                            if (tbody && tbody.querySelectorAll('tr').length === 0) {
                                tbody.innerHTML = '<tr id="no-users-row"><td colspan="4" class="text-center text-muted py-4">No registered users found.</td></tr>';
                            }
                        }, 300);
                    }
                } else {
                    alert(data.message || 'Failed to delete user.');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = orig;
                alert('Network error. Please try again.');
            });
        });
    }
});
</script>
</body>
</html>
