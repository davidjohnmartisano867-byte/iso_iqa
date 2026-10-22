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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $pdo = getDBConnection();

    if ($_POST['action'] === 'add_auditor') {
        $auditor_name = trim($_POST['auditor_name'] ?? '');

        if (empty($auditor_name)) {
            echo json_encode(['success' => false, 'message' => 'Auditor name is required.']);
            exit;
        }

        try {
            $words = preg_split('/\s+/', $auditor_name);
            $first_word = preg_replace('/[^a-zA-Z0-9]/', '', trim($words[0]));
            $last_word = preg_replace('/[^a-zA-Z0-9]/', '', trim(end($words)));
            
            $base_username = strtolower($first_word);
            $username = $base_username . rand(100, 999);
            $plain_password = $last_word . rand(100, 999);
            $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

            $check_user = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check_user->execute([$username]);
            while ($check_user->fetchColumn() > 0) {
                $username = $base_username . rand(100, 999);
                $check_user->execute([$username]);
            }

            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'auditor')");
            $stmt->execute([$username, $hashed_password]);
            $user_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO auditors (name, user_id) VALUES (?, ?)");
            $stmt->execute([$auditor_name, $user_id]);
            $auditor_id = (int)$pdo->lastInsertId();

            $count_stmt = $pdo->query("SELECT COUNT(*) FROM auditors");
            $total_count = (int)$count_stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'username' => $username,
                'password' => $plain_password,
                'auditor' => [
                    'id' => $auditor_id,
                    'name' => $auditor_name,
                    'username' => $username,
                    'created_at' => date('M j, Y'),
                    'assigned_offices' => ''
                ],
                'total_count' => $total_count
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($_POST['action'] === 'delete_auditor') {
        $auditor_id = (int)($_POST['auditor_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE u FROM users u JOIN auditors a ON a.user_id = u.id WHERE a.id = ?");
            $stmt->execute([$auditor_id]);

            $count_stmt = $pdo->query("SELECT COUNT(*) FROM auditors");
            $total_count = (int)$count_stmt->fetchColumn();

            echo json_encode(['success' => true, 'total_count' => $total_count]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'edit_auditor') {
        $auditor_id = (int)($_POST['auditor_id'] ?? 0);
        $auditor_name = trim($_POST['auditor_name'] ?? '');

        if (empty($auditor_name)) {
            echo json_encode(['success' => false, 'message' => 'Auditor name is required.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE auditors SET name = ? WHERE id = ?");
            $stmt->execute([$auditor_name, $auditor_id]);
            echo json_encode(['success' => true, 'auditor_id' => $auditor_id, 'name' => $auditor_name]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'get_auditor_assignments') {
        $auditor_id = (int)($_POST['auditor_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("SELECT office_id FROM office_assignments WHERE auditor_id = ?");
            $stmt->execute([$auditor_id]);
            $assigned = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $stmt_act = $pdo->prepare("SELECT activity FROM office_assignments WHERE auditor_id = ? LIMIT 1");
            $stmt_act->execute([$auditor_id]);
            $activity = $stmt_act->fetchColumn() ?: 'Internal Audit';

            echo json_encode(['success' => true, 'assigned' => $assigned, 'activity' => $activity]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'save_auditor_assignments') {
        $auditor_id = (int)($_POST['auditor_id'] ?? 0);
        $office_ids = $_POST['office_ids'] ?? [];
        $activity = trim($_POST['activity'] ?? 'Internal Audit');
        if (!in_array($activity, ['Internal Audit', 'External Audit', 'Follow-up Audit', 'Follow up Audit', 'Follow up audit'])) {
            $activity = 'Internal Audit';
        }
        try {
            $pdo->beginTransaction();

            $del = $pdo->prepare("DELETE FROM office_assignments WHERE auditor_id = ?");
            $del->execute([$auditor_id]);
            
            $assigned_abbrs = [];
            if (!empty($office_ids)) {
                $ins = $pdo->prepare("INSERT INTO office_assignments (auditor_id, office_id, activity) VALUES (?, ?, ?)");
                $abbr_stmt = $pdo->prepare("SELECT abbreviation FROM offices WHERE id = ?");
                foreach ($office_ids as $off_id) {
                    $ins->execute([$auditor_id, (int)$off_id, $activity]);
                    $abbr_stmt->execute([(int)$off_id]);
                    $ab = $abbr_stmt->fetchColumn();
                    if ($ab) $assigned_abbrs[] = $ab;
                }
            }
            sort($assigned_abbrs);
            
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'auditor_id' => $auditor_id,
                'assigned_offices' => $assigned_abbrs,
                'activity' => $activity
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

$username = $_SESSION['username'] ?? 'User';
$role     = $_SESSION['role'] ?? 'admin';
$active_page = 'auditors';
$base_url    = '';

$pdo = getDBConnection();
$stmt = $pdo->query("SELECT a.id, a.name, u.username, a.created_at, GROUP_CONCAT(o.abbreviation ORDER BY o.abbreviation ASC SEPARATOR ', ') as assigned_offices FROM auditors a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN office_assignments oa ON a.id = oa.auditor_id LEFT JOIN offices o ON oa.office_id = o.id GROUP BY a.id ORDER BY a.created_at DESC");
$auditors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_offices = $pdo->query("SELECT id, abbreviation, name FROM offices ORDER BY abbreviation ASC");
$offices_list = $stmt_offices->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Auditors – ISO-QA AI System</title>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold text-dark mb-0">Registered Quality Auditors</h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary rounded-pill px-3 py-1" id="auditors-total-badge"><?= count($auditors) ?> Total</span>
                    <button class="btn btn-sm btn-custom rounded-3 px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addAuditorModal">
                        <i class="fas fa-plus"></i> Add Auditor
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-center">
                            <th>Auditor Full Name</th>
                            <th>Offices Assigned</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="auditors-table-body">
                        <?php if (empty($auditors)): ?>
                            <tr id="no-auditors-row">
                                <td colspan="4" class="text-center text-muted py-4">No registered auditors found. Click "Add Auditor" to register one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($auditors as $auditor): ?>
                                <tr id="auditor-row-<?= $auditor['id'] ?>">
                                    <td class="fw-semibold"><?= htmlspecialchars($auditor['name']) ?></td>
                                    <td class="text-center auditor-offices-cell">
                                        <?php if (empty($auditor['assigned_offices'])): ?>
                                            <span class="badge bg-light text-muted border px-2 py-1 rounded-2" style="font-size:0.78rem;">None Assigned</span>
                                        <?php else: ?>
                                            <?php 
                                             $offs = explode(', ', $auditor['assigned_offices']);
                                             foreach ($offs as $of): 
                                            ?>
                                                <span class="badge bg-primary-subtle text-primary border-primary fw-semibold px-2 py-1 rounded-2 me-1 mb-1" style="font-size: 0.78rem; background: rgba(37,99,235,0.1) !important; color: #2563eb !important; border: 1px solid rgba(37,99,235,0.2) !important;"><?= htmlspecialchars($of) ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-muted auditor-date-cell" style="font-size:0.82rem;"><?= date('M j, Y', strtotime($auditor['created_at'])) ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-link text-primary p-0 me-3 btn-assign-offices" data-id="<?= $auditor['id'] ?>" data-name="<?= htmlspecialchars($auditor['name']) ?>" title="Assign Offices">
                                            <i class="fas fa-tasks fa-lg"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link text-warning p-0 me-3 btn-edit-auditor" data-id="<?= $auditor['id'] ?>" data-name="<?= htmlspecialchars($auditor['name']) ?>" title="Edit Auditor">
                                            <i class="fas fa-edit fa-lg"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link text-danger p-0 btn-delete-auditor" data-id="<?= $auditor['id'] ?>" data-name="<?= htmlspecialchars($auditor['name']) ?>" title="Delete Auditor">
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

<?php require_once __DIR__ . '/../../components/add_auditor_modal.php'; ?>
<?php
$office_list = $offices_list;
require_once __DIR__ . '/../../components/edit_auditor_modal.php';
require_once __DIR__ . '/../../components/assign_offices_modal.php';
?>
<?php require_once __DIR__ . '/../../components/delete_confirmation_modal.php'; ?>
<?php require_once __DIR__ . '/../../components/credentials_modal.php'; ?>
<?php require_once __DIR__ . '/../../components/notification.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    const addAuditorForm = document.getElementById('add-auditor-form');
    const errorDiv = document.getElementById('add-auditor-error');

    if (addAuditorForm) {
        addAuditorForm.addEventListener('submit', function(e) {
            e.preventDefault();
            errorDiv.classList.add('d-none');
            
            const formData = new FormData(addAuditorForm);
            formData.append('action', 'add_auditor');

            const submitBtn = addAuditorForm.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Adding...';

            fetch('auditors.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;

                if (data.success) {
                    const addModal = bootstrap.Modal.getInstance(document.getElementById('addAuditorModal')) || new bootstrap.Modal(document.getElementById('addAuditorModal'));
                    addModal.hide();
                    addAuditorForm.reset();

                    const noRow = document.getElementById('no-auditors-row');
                    if (noRow) noRow.remove();

                    const tbody = document.getElementById('auditors-table-body');
                    if (tbody && data.auditor) {
                        const tr = document.createElement('tr');
                        tr.id = `auditor-row-${data.auditor.id}`;
                        tr.className = 'table-success';
                        tr.innerHTML = `
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-sm auditor-avatar" style="width:30px;height:30px;font-size:0.75rem;">${escapeHtml(data.auditor.name.charAt(0).toUpperCase())}</div>
                                    <div>
                                        <span class="fw-semibold text-dark d-block auditor-name-cell">${escapeHtml(data.auditor.name)}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center auditor-offices-cell">
                                <span class="badge bg-light text-muted border px-2 py-1 rounded-2" style="font-size:0.78rem;">None Assigned</span>
                            </td>
                            <td class="text-center text-muted auditor-date-cell" style="font-size:0.82rem;">${escapeHtml(data.auditor.created_at)}</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-link text-primary p-0 me-3 btn-assign-offices" data-id="${data.auditor.id}" data-name="${escapeHtml(data.auditor.name)}" title="Assign Offices">
                                    <i class="fas fa-tasks fa-lg"></i>
                                </button>
                                <button class="btn btn-sm btn-link text-warning p-0 me-3 btn-edit-auditor" data-id="${data.auditor.id}" data-name="${escapeHtml(data.auditor.name)}" title="Edit Auditor">
                                    <i class="fas fa-edit fa-lg"></i>
                                </button>
                                <button class="btn btn-sm btn-link text-danger p-0 btn-delete-auditor" data-id="${data.auditor.id}" data-name="${escapeHtml(data.auditor.name)}" title="Delete Auditor">
                                    <i class="fas fa-trash-alt fa-lg"></i>
                                </button>
                            </td>
                        `;
                        tbody.insertBefore(tr, tbody.firstChild);
                        setTimeout(() => tr.classList.remove('table-success'), 2500);
                    }

                    if (data.total_count !== undefined) {
                        const totalBadge = document.getElementById('auditors-total-badge');
                        if (totalBadge) {
                            totalBadge.textContent = `${data.total_count} Total`;
                        }
                    }

                    document.getElementById('cred-modal-title').innerText = 'Auditor Account Credentials';
                    document.getElementById('cred-modal-heading').innerText = 'Auditor Account Created!';
                    document.getElementById('cred-modal-desc').innerText = 'Please copy the temporary credentials generated for the new Auditor. They can use these to sign in to the system.';
                    
                    document.getElementById('cred-username').innerText = data.username;
                    document.getElementById('cred-password').innerText = data.password;

                    const credModal = new bootstrap.Modal(document.getElementById('credentialsModal'));
                    credModal.show();

                    if (window.showNotification) {
                        window.showNotification('Auditor registered successfully.', 'success');
                    }
                } else {
                    errorDiv.innerText = data.message || 'An error occurred.';
                    errorDiv.classList.remove('d-none');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
                errorDiv.innerText = 'Network error. Please try again.';
                errorDiv.classList.remove('d-none');
            });
        });
    }

    const deleteConfirmationModal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    let activeDeleteAuditorId = 0;
    let activeDeleteAuditorName = '';

    const editAuditorModal = new bootstrap.Modal(document.getElementById('editAuditorModal'));
    const editForm = document.getElementById('edit-auditor-form');
    const editError = document.getElementById('edit-auditor-error');

    const assignOfficesModal = new bootstrap.Modal(document.getElementById('assignOfficesModal'));
    const assignSubmitForm = document.getElementById('assign-offices-form');

    document.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.btn-delete-auditor');
        if (deleteBtn) {
            activeDeleteAuditorId = deleteBtn.getAttribute('data-id');
            activeDeleteAuditorName = deleteBtn.getAttribute('data-name');
            
            const titleText = document.getElementById('delete-modal-title-text');
            const heading = document.getElementById('delete-modal-heading');
            const message = document.getElementById('delete-modal-message');
            const btnText = document.getElementById('delete-modal-btn-text');

            if (titleText) titleText.textContent = 'Confirm Deletion';
            if (heading) heading.textContent = 'Delete auditor account?';
            if (message) {
                message.innerHTML = `Are you sure you want to permanently delete the auditor account for <strong id="delete-item-name" class="text-dark">${escapeHtml(activeDeleteAuditorName)}</strong>? This action cannot be undone.`;
            }
            if (btnText) btnText.textContent = 'Delete Account';

            deleteConfirmationModal.show();
            return;
        }

        const editBtn = e.target.closest('.btn-edit-auditor');
        if (editBtn) {
            const auditorId = editBtn.getAttribute('data-id');
            const name = editBtn.getAttribute('data-name');
            editError.classList.add('d-none');
            
            document.getElementById('edit_auditor_id').value = auditorId;
            document.getElementById('edit_auditor_name').value = name;
            
            editAuditorModal.show();
            return;
        }

        const assignBtn = e.target.closest('.btn-assign-offices');
        if (assignBtn) {
            const auditorId = assignBtn.getAttribute('data-id');
            const fallbackActivity = assignBtn.getAttribute('data-activity') || 'Internal Audit';
            document.getElementById('assign_auditor_id').value = auditorId;
            document.getElementById('assign_auditor_activity').value = fallbackActivity;
            
            document.querySelectorAll('#offices-checkbox-list .office-checkbox').forEach(cb => {
                cb.checked = false;
            });

            const formData = new FormData();
            formData.append('action', 'get_auditor_assignments');
            formData.append('auditor_id', auditorId);

            fetch('auditors.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.activity) {
                        document.getElementById('assign_auditor_activity').value = data.activity;
                    }
                    const assigned = data.assigned || [];
                    assigned.forEach(officeId => {
                        const cb = document.getElementById('assign-off-' + officeId);
                        if (cb) cb.checked = true;
                    });
                    assignOfficesModal.show();
                } else {
                    if (window.showNotification) {
                        window.showNotification('Could not load current office assignments.', 'error');
                    }
                }
            })
            .catch(err => {
                if (window.showNotification) {
                    window.showNotification('Network error loading assignments.', 'error');
                }
            });
            return;
        }
    });

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (!activeDeleteAuditorId) return;

            const formData = new FormData();
            formData.append('action', 'delete_auditor');
            formData.append('auditor_id', activeDeleteAuditorId);

            confirmDeleteBtn.disabled = true;
            const origHTML = confirmDeleteBtn.innerHTML;
            confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Deleting...';

            fetch('auditors.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                deleteConfirmationModal.hide();
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = origHTML;

                if (data.success) {
                    const row = document.getElementById(`auditor-row-${activeDeleteAuditorId}`);
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            if (data.total_count !== undefined) {
                                const totalBadge = document.getElementById('auditors-total-badge');
                                if (totalBadge) totalBadge.textContent = `${data.total_count} Total`;
                            }
                            const tbody = document.getElementById('auditors-table-body');
                            if (tbody && tbody.querySelectorAll('tr').length === 0) {
                                tbody.innerHTML = `
                                    <tr id="no-auditors-row">
                                        <td colspan="4" class="text-center text-muted py-4">No registered auditors found. Click "Add Auditor" to register one.</td>
                                    </tr>
                                `;
                            }
                        }, 300);
                    }
                    if (window.showNotification) {
                        window.showNotification('Auditor ' + activeDeleteAuditorName + ' deleted successfully.', 'success');
                    }
                } else {
                    if (window.showNotification) {
                        window.showNotification(data.message || 'An error occurred.', 'error');
                    }
                }
            })
            .catch(err => {
                deleteConfirmationModal.hide();
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = origHTML;
                if (window.showNotification) {
                    window.showNotification('Network error. Please try again.', 'error');
                }
            });
        });
    }

    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            editError.classList.add('d-none');

            const formData = new FormData(editForm);
            formData.append('action', 'edit_auditor');

            const submitBtn = editForm.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';

            fetch('auditors.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;

                if (data.success) {
                    editAuditorModal.hide();
                    const row = document.getElementById(`auditor-row-${data.auditor_id}`);
                    if (row) {
                        const nameCell = row.querySelector('.auditor-name-cell');
                        const avatar = row.querySelector('.auditor-avatar');
                        const editBtn = row.querySelector('.btn-edit-auditor');
                        const deleteBtn = row.querySelector('.btn-delete-auditor');
                        const assignBtn = row.querySelector('.btn-assign-offices');

                        if (nameCell) nameCell.textContent = data.name;
                        if (avatar && data.name) avatar.textContent = data.name.charAt(0).toUpperCase();
                        if (editBtn) editBtn.setAttribute('data-name', data.name);
                        if (deleteBtn) deleteBtn.setAttribute('data-name', data.name);
                        if (assignBtn) assignBtn.setAttribute('data-name', data.name);

                        row.classList.add('table-info');
                        setTimeout(() => row.classList.remove('table-info'), 2000);
                    }
                    if (window.showNotification) {
                        window.showNotification('Auditor name updated successfully.', 'success');
                    }
                } else {
                    editError.innerText = data.message || 'An error occurred.';
                    editError.classList.remove('d-none');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
                editError.innerText = 'Network error. Please try again.';
                editError.classList.remove('d-none');
            });
        });
    }

    if (assignSubmitForm) {
        assignSubmitForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(assignSubmitForm);
            formData.append('action', 'save_auditor_assignments');

            const submitBtn = assignSubmitForm.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';

            fetch('auditors.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;

                if (data.success) {
                    assignOfficesModal.hide();
                    const row = document.getElementById(`auditor-row-${data.auditor_id}`);
                    if (row) {
                        const officesCell = row.querySelector('.auditor-offices-cell');
                        const assignBtn = row.querySelector('.btn-assign-offices');
                        if (assignBtn) assignBtn.setAttribute('data-activity', data.activity);

                        if (officesCell) {
                            if (!data.assigned_offices || data.assigned_offices.length === 0) {
                                officesCell.innerHTML = '<span class="badge bg-light text-muted border px-2 py-1 rounded-2" style="font-size:0.78rem;">None Assigned</span>';
                            } else {
                                officesCell.innerHTML = data.assigned_offices.map(of => `
                                    <span class="badge bg-primary-subtle text-primary border-primary fw-semibold px-2 py-1 rounded-2 me-1 mb-1" style="font-size: 0.78rem; background: rgba(37,99,235,0.1) !important; color: #2563eb !important; border: 1px solid rgba(37,99,235,0.2) !important;">${escapeHtml(of)}</span>
                                `).join('');
                            }
                        }

                        row.classList.add('table-info');
                        setTimeout(() => row.classList.remove('table-info'), 2000);
                    }
                    if (window.showNotification) {
                        window.showNotification('Office assignments updated successfully.', 'success');
                    }
                } else {
                    if (window.showNotification) {
                        window.showNotification(data.message || 'An error occurred.', 'error');
                    }
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
                if (window.showNotification) {
                    window.showNotification('Network error saving assignments.', 'error');
                }
            });
        });
    }
</script>
</body>
</html>
