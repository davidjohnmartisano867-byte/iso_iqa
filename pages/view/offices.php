<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
if (($_SESSION['role'] ?? '') === 'auditee') {
    header("Location: dashboard.php");
    exit;
}
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_office') {
    header('Content-Type: application/json');
    $abbreviation = trim($_POST['abbreviation'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $officer_in_charge = trim($_POST['officer_in_charge'] ?? '');

    if (empty($abbreviation) || empty($name) || empty($officer_in_charge)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    try {
        $pdo = getDBConnection();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM offices WHERE LOWER(TRIM(abbreviation)) = LOWER(?)");
        $stmt->execute([$abbreviation]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Office abbreviation already exists.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM offices WHERE LOWER(TRIM(name)) = LOWER(?)");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Office name already exists.']);
            exit;
        }

        $words = preg_split('/\s+/', $officer_in_charge);
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

        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'auditee')");
        $stmt->execute([$username, $hashed_password]);
        $user_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO offices (abbreviation, name, officer_in_charge, user_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$abbreviation, $name, $officer_in_charge, $user_id]);
        $office_id = (int)$pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'username' => $username,
            'password' => $plain_password,
            'office' => [
                'id' => $office_id,
                'abbreviation' => $abbreviation,
                'name' => $name,
                'officer_in_charge' => $officer_in_charge,
                'created_at' => date('M j, Y')
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_office') {
    header('Content-Type: application/json');
    $office_id = (int)($_POST['office_id'] ?? 0);
    $abbreviation = trim($_POST['abbreviation'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $officer_in_charge = trim($_POST['officer_in_charge'] ?? '');

    if (empty($abbreviation) || empty($name) || empty($officer_in_charge)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM offices WHERE LOWER(TRIM(abbreviation)) = LOWER(?) AND id != ?");
        $stmt->execute([$abbreviation, $office_id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Office abbreviation already exists.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM offices WHERE LOWER(TRIM(name)) = LOWER(?) AND id != ?");
        $stmt->execute([$name, $office_id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Office name already exists.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE offices SET abbreviation = ?, name = ?, officer_in_charge = ? WHERE id = ?");
        $stmt->execute([$abbreviation, $name, $officer_in_charge, $office_id]);

        echo json_encode([
            'success' => true,
            'office' => [
                'id' => $office_id,
                'abbreviation' => $abbreviation,
                'name' => $name,
                'officer_in_charge' => $officer_in_charge
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_office') {
    header('Content-Type: application/json');
    $office_id = (int)($_POST['office_id'] ?? 0);
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT user_id FROM offices WHERE id = ?");
        $stmt->execute([$office_id]);
        $user_id = $stmt->fetchColumn();

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM offices WHERE id = ?");
        $stmt->execute([$office_id]);

        if ($user_id) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$username = $_SESSION['username'] ?? 'User';
$role     = $_SESSION['role'] ?? 'admin';
$active_page = 'offices';

$pdo = getDBConnection();

$assigned_office_ids = [];
if ($role === 'auditor') {
    $aud_stmt = $pdo->prepare("SELECT id FROM auditors WHERE user_id = ?");
    $aud_stmt->execute([$_SESSION['user_id']]);
    $auditor_id = $aud_stmt->fetchColumn();
    if ($auditor_id) {
        $assign_stmt = $pdo->prepare("SELECT office_id FROM office_assignments WHERE auditor_id = ?");
        $assign_stmt->execute([$auditor_id]);
        $assigned_office_ids = $assign_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    if (empty($assigned_office_ids)) {
        $assigned_office_ids = [0];
    }
}

$offices_query = "SELECT o.id, o.abbreviation, o.name, o.officer_in_charge, u.username as account_user, o.created_at FROM offices o LEFT JOIN users u ON o.user_id = u.id";
if ($role === 'auditor') {
    $offices_query .= " WHERE o.id IN (" . implode(',', $assigned_office_ids) . ")";
}
$offices_query .= " ORDER BY o.name ASC";
$offices = $pdo->query($offices_query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Offices – ISO IQA System v2</title>
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
                <h5 class="fw-bold text-dark mb-0">Registered Offices</h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary rounded-pill px-3 py-1" id="offices-total-badge"><?= count($offices) ?> Total</span>
                    <?php if ($role !== 'auditor'): ?>
                    <button class="btn btn-sm btn-custom rounded-3 px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addOfficeModal">
                        <i class="fas fa-plus"></i> Add Office
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-center">
                            <th>Abbreviation</th>
                            <th>Office Name</th>
                            <th>Officer in Charge</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="offices-table-body">
                        <?php if (empty($offices)): ?>
                            <tr id="no-offices-row">
                                <td colspan="5" class="text-center text-muted py-4">No registered offices found. Click "Add Office" to create one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($offices as $office): ?>
                                <tr id="office-row-<?= $office['id'] ?>">
                                    <td class="text-center"><span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-2 office-abbr-badge" style="font-size: 0.85rem; background: rgba(37,99,235,0.1) !important; color: #2563eb !important;"><?= htmlspecialchars($office['abbreviation']) ?></span></td>
                                    <td class="fw-semibold text-dark office-name-cell"><?= htmlspecialchars($office['name']) ?></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($office['officer_in_charge']) ?></td>
                                    <td class="text-center text-muted office-date-cell" style="font-size:0.82rem;"><?= date('M j, Y', strtotime($office['created_at'])) ?></td>
                                    <td class="text-center">
                                        <a href="../sub_pages/office_details.php?office_id=<?= $office['id'] ?>" class="btn btn-sm btn-link text-primary p-0 me-3" title="View Details">
                                            <i class="fas fa-eye fa-lg"></i>
                                        </a>
                                        <?php if ($role !== 'auditor'): ?>
                                        <button class="btn btn-sm btn-link text-warning p-0 me-3 btn-edit-office" data-id="<?= $office['id'] ?>" data-abbr="<?= htmlspecialchars($office['abbreviation']) ?>" data-name="<?= htmlspecialchars($office['name']) ?>" data-oic="<?= htmlspecialchars($office['officer_in_charge']) ?>" title="Edit Office">
                                            <i class="fas fa-edit fa-lg"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link text-danger p-0 btn-delete-office" data-id="<?= $office['id'] ?>" data-name="<?= htmlspecialchars($office['name']) ?>" title="Delete Office">
                                            <i class="fas fa-trash-alt fa-lg"></i>
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
    </div>
</div>

<?php require_once __DIR__ . '/../../components/add_office_modal.php'; ?>
<?php require_once __DIR__ . '/../../components/edit_office_modal.php'; ?>
<?php require_once __DIR__ . '/../../components/delete_confirmation_modal.php'; ?>
<?php require_once __DIR__ . '/../../components/credentials_modal.php'; ?>
<?php require_once __DIR__ . '/../../components/notification.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const addOfficeForm = document.getElementById('add-office-form');
    const errorDiv = document.getElementById('add-office-error');

    function updateOfficesCount(delta) {
        const badge = document.getElementById('offices-total-badge');
        if (!badge) return;
        const currentCount = parseInt(badge.textContent) || 0;
        const newCount = Math.max(0, currentCount + delta);
        badge.textContent = `${newCount} Total`;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function sortTableAlphabetically() {
        const tbody = document.getElementById('offices-table-body');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr:not(#no-offices-row)'));
        rows.sort((a, b) => {
            const nameA = (a.querySelector('.office-name-cell')?.textContent || '').trim().toLowerCase();
            const nameB = (b.querySelector('.office-name-cell')?.textContent || '').trim().toLowerCase();
            return nameA.localeCompare(nameB);
        });
        rows.forEach(r => tbody.appendChild(r));
    }

    if (addOfficeForm) {
        addOfficeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            errorDiv.classList.add('d-none');
            
            const formData = new FormData(addOfficeForm);
            formData.append('action', 'add_office');

            const submitBtn = addOfficeForm.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Adding...';

            fetch('offices.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;

                if (data.success) {
                    const addOfficeModalEl = document.getElementById('addOfficeModal');
                    const addOfficeModal = bootstrap.Modal.getInstance(addOfficeModalEl) || new bootstrap.Modal(addOfficeModalEl);
                    addOfficeModal.hide();
                    addOfficeForm.reset();

                    const noRow = document.getElementById('no-offices-row');
                    if (noRow) noRow.remove();

                    const tbody = document.getElementById('offices-table-body');
                    if (tbody && data.office) {
                        const tr = document.createElement('tr');
                        tr.id = `office-row-${data.office.id}`;
                        tr.className = 'table-success';
                        tr.innerHTML = `
                            <td><span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-2 office-abbr-badge" style="font-size: 0.85rem; background: rgba(37,99,235,0.1) !important; color: #2563eb !important;">${escapeHtml(data.office.abbreviation)}</span></td>
                            <td class="fw-semibold text-dark office-name-cell">${escapeHtml(data.office.name)}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-sm office-avatar" style="width:30px;height:30px;font-size:0.75rem;">${escapeHtml(data.office.officer_in_charge.charAt(0).toUpperCase())}</div>
                                    <div>
                                        <span class="fw-semibold text-dark d-block office-oic-cell">${escapeHtml(data.office.officer_in_charge)}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-muted office-date-cell" style="font-size:0.82rem;">${escapeHtml(data.office.created_at)}</td>
                            <td>
                                <a href="../sub_pages/office_details.php?office_id=${data.office.id}" class="btn btn-sm btn-link text-primary p-0 me-3" title="View Details">
                                    <i class="fas fa-eye fa-lg"></i>
                                </a>
                                <?php if ($role !== 'auditor'): ?>
                                <button class="btn btn-sm btn-link text-warning p-0 me-3 btn-edit-office" data-id="${data.office.id}" data-abbr="${escapeHtml(data.office.abbreviation)}" data-name="${escapeHtml(data.office.name)}" data-oic="${escapeHtml(data.office.officer_in_charge)}" title="Edit Office">
                                    <i class="fas fa-edit fa-lg"></i>
                                </button>
                                <button class="btn btn-sm btn-link text-danger p-0 btn-delete-office" data-id="${data.office.id}" data-name="${escapeHtml(data.office.name)}" title="Delete Office">
                                    <i class="fas fa-trash-alt fa-lg"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        `;
                        tbody.appendChild(tr);
                        sortTableAlphabetically();
                        setTimeout(() => tr.classList.remove('table-success'), 2000);
                        updateOfficesCount(1);
                    }

                    document.getElementById('cred-username').innerText = data.username;
                    document.getElementById('cred-password').innerText = data.password;

                    const credModal = new bootstrap.Modal(document.getElementById('credentialsModal'));
                    credModal.show();

                    if (window.showNotification) {
                        window.showNotification('Office added successfully.', 'success');
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

    const editOfficeModalEl = document.getElementById('editOfficeModal');
    const editOfficeModal = new bootstrap.Modal(editOfficeModalEl);
    const editForm = document.getElementById('edit-office-form');
    const editError = document.getElementById('edit-office-error');

    const deleteConfirmationModalEl = document.getElementById('deleteConfirmationModal');
    const deleteConfirmationModal = new bootstrap.Modal(deleteConfirmationModalEl);
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    let activeDeleteOfficeId = null;

    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.btn-edit-office');
        if (editBtn) {
            const officeId = editBtn.getAttribute('data-id');
            const abbr = editBtn.getAttribute('data-abbr');
            const name = editBtn.getAttribute('data-name');
            const oic = editBtn.getAttribute('data-oic');

            editError.classList.add('d-none');

            document.getElementById('edit_office_id').value = officeId;
            document.getElementById('edit_office_abbreviation').value = abbr;
            document.getElementById('edit_office_name').value = name;
            document.getElementById('edit_officer_in_charge').value = oic;

            editOfficeModal.show();
            return;
        }

        const deleteBtn = e.target.closest('.btn-delete-office');
        if (deleteBtn) {
            activeDeleteOfficeId = deleteBtn.getAttribute('data-id');
            const name = deleteBtn.getAttribute('data-name');

            const titleText = document.getElementById('delete-modal-title-text');
            const heading = document.getElementById('delete-modal-heading');
            const message = document.getElementById('delete-modal-message');
            const btnText = document.getElementById('delete-modal-btn-text');

            if (titleText) titleText.textContent = 'Confirm Deletion';
            if (heading) heading.textContent = 'Delete Office?';
            if (message) {
                message.innerHTML = `Are you sure you want to permanently delete the office <strong id="delete-item-name" class="text-dark">${escapeHtml(name)}</strong>? This will also delete the associated auditee account and cannot be undone.`;
            }
            if (btnText) btnText.textContent = 'Delete Office';

            deleteConfirmationModal.show();
            return;
        }
    });

    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            editError.classList.add('d-none');

            const formData = new FormData(editForm);
            formData.append('action', 'edit_office');

            const submitBtn = editForm.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';

            fetch('offices.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;

                if (data.success) {
                    editOfficeModal.hide();
                    const officeId = document.getElementById('edit_office_id').value;
                    const row = document.getElementById(`office-row-${officeId}`);
                    if (row && data.office) {
                        const abbrBadge = row.querySelector('.office-abbr-badge');
                        const nameCell = row.querySelector('.office-name-cell');
                        const oicCell = row.querySelector('.office-oic-cell');
                        const editBtn = row.querySelector('.btn-edit-office');
                        const deleteBtn = row.querySelector('.btn-delete-office');

                        const avatarEl = row.querySelector('.office-avatar');

                        if (abbrBadge) abbrBadge.textContent = data.office.abbreviation;
                        if (nameCell) nameCell.textContent = data.office.name;
                        if (oicCell) oicCell.textContent = data.office.officer_in_charge;
                        if (avatarEl && data.office.officer_in_charge) {
                            avatarEl.textContent = data.office.officer_in_charge.charAt(0).toUpperCase();
                        }
                        if (editBtn) {
                            editBtn.setAttribute('data-abbr', data.office.abbreviation);
                            editBtn.setAttribute('data-name', data.office.name);
                            editBtn.setAttribute('data-oic', data.office.officer_in_charge);
                        }
                        if (deleteBtn) {
                            deleteBtn.setAttribute('data-name', data.office.name);
                        }
                        sortTableAlphabetically();
                        row.classList.add('table-info');
                        setTimeout(() => row.classList.remove('table-info'), 2000);
                    }
                    if (window.showNotification) {
                        window.showNotification('Office details updated successfully.', 'success');
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

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            confirmDeleteBtn.disabled = true;
            const originalHTML = confirmDeleteBtn.innerHTML;
            confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Deleting...';

            const formData = new FormData();
            formData.append('action', 'delete_office');
            formData.append('office_id', activeDeleteOfficeId);

            fetch('offices.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                deleteConfirmationModal.hide();
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = originalHTML;

                if (data.success) {
                    const row = document.getElementById(`office-row-${activeDeleteOfficeId}`);
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            updateOfficesCount(-1);
                            const tbody = document.getElementById('offices-table-body');
                            if (tbody && tbody.querySelectorAll('tr').length === 0) {
                                tbody.innerHTML = `
                                    <tr id="no-offices-row">
                                        <td colspan="5" class="text-center text-muted py-4">No registered offices found. Click "Add Office" to create one.</td>
                                    </tr>
                                `;
                            }
                        }, 300);
                    }
                    if (window.showNotification) {
                        window.showNotification('Office deleted successfully.', 'success');
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
                confirmDeleteBtn.innerHTML = originalHTML;
                if (window.showNotification) {
                    window.showNotification('Network error. Please try again.', 'error');
                }
            });
        });
    }
</script>
</body>
</html>
