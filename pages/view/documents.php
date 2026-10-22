<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/ai_service.php';

$pdo = getDBConnection();
$role = $_SESSION['role'] ?? 'admin';

if ($role === 'auditee') {
    $stmt = $pdo->prepare("SELECT id FROM offices WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $office_id = $stmt->fetchColumn();
    if ($office_id) {
        header("Location: ../sub_pages/office_documents.php?office_id=" . $office_id);
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $pdo = getDBConnection();
    
    if ($_POST['action'] === 'add_finding') {
        $office_id = (int)($_POST['office_id'] ?? 0);
        $finding_types = $_POST['finding_type'] ?? [];
        $descriptions = $_POST['findings'] ?? [];
        $deadlines = $_POST['deadline'] ?? [];

        if ($role === 'auditor' && !in_array($office_id, $assigned_office_ids)) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized office access.']);
            exit;
        }
        if ($office_id <= 0 || empty($finding_types)) {
            echo json_encode(['success' => false, 'message' => 'Office and at least one finding block are required.']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $inserted = [];
            for ($i = 0; $i < count($finding_types); $i++) {
                $type = trim($finding_types[$i] ?? '');
                $desc = trim($descriptions[$i] ?? '');
                $dl = trim($deadlines[$i] ?? '');
                $doc_id = !empty($_POST['document_id'][$i]) ? (int)$_POST['document_id'][$i] : null;
                if (!empty($type) && !empty($desc) && !empty($dl)) {
                    $stmt = $pdo->prepare("INSERT INTO findings (office_id, finding_type, findings, deadline, document_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$office_id, $type, $desc, $dl, $doc_id]);
                    $finding_id = $pdo->lastInsertId();
                    $inserted[] = [
                        'id' => $finding_id,
                        'finding_type' => $type,
                        'findings' => $desc
                    ];
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'inserted' => $inserted]);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    if ($_POST['action'] === 'fetch_documents_summary') {
        $offices_query = "SELECT o.id, COUNT(d.id) as doc_count FROM offices o LEFT JOIN documents d ON o.id = d.office_id";
        if ($role === 'auditor') {
            $offices_query .= " WHERE o.id IN (" . implode(',', $assigned_office_ids) . ")";
        }
        $offices_query .= " GROUP BY o.id ORDER BY o.abbreviation ASC";
        $stmt = $pdo->query($offices_query);
        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $counts[(int)$r['id']] = (int)$r['doc_count'];
        }
        echo json_encode(['success' => true, 'counts' => $counts]);
        exit;
    }

    if ($_POST['action'] === 'delete_all_office_documents') {
        $office_id = (int)($_POST['office_id'] ?? 0);
        if ($role === 'auditor' && !in_array($office_id, $assigned_office_ids)) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized office access.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM documents WHERE office_id = ?");
            $stmt->execute([$office_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'cancel_upload') {
        $token = trim($_POST['upload_token'] ?? '');
        $office_id = (int)($_POST['office_id'] ?? 0);
        if (!empty($token)) {
            $stmt = $pdo->prepare("SELECT id, filename, file_path FROM documents WHERE upload_token = ?");
            $stmt->execute([$token]);
            $to_delete = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($to_delete as $doc) {
                $path = __DIR__ . '/../../' . $doc['file_path'];
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
            $del = $pdo->prepare("DELETE FROM documents WHERE upload_token = ?");
            $del->execute([$token]);

            $new_count = 0;
            if ($office_id > 0) {
                $c_stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE office_id = ?");
                $c_stmt->execute([$office_id]);
                $new_count = (int)$c_stmt->fetchColumn();
            }

            echo json_encode(['success' => true, 'office_id' => $office_id, 'new_doc_count' => $new_count]);
            exit;
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'upload_document') {
        $office_id = (int)($_POST['office_id'] ?? 0);
        if ($role === 'auditor' && !in_array($office_id, $assigned_office_ids)) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized office access.']);
            exit;
        }
        $clause = $_POST['clause'] ?? 'auto';
        $upload_token = trim($_POST['upload_token'] ?? '');
        
        if ($office_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid target office.']);
            exit;
        }

        if (!isset($_FILES['document_file'])) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one file to upload.']);
            exit;
        }

        $files = $_FILES['document_file'];
        $file_count = is_array($files['name']) ? count($files['name']) : 0;
        if ($file_count === 0) {
            echo json_encode(['success' => false, 'message' => 'No files selected.']);
            exit;
        }

        $reasonings = [];
        $is_auto = ($clause === 'auto');
        $clause_num = 8;
        $clause_title = 'Operation';
        $uploaded_count = 0;

        try {
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $filename = basename($files['name'][$i]);
                    $file_size = $files['size'][$i];
                    
                    $target_dir = __DIR__ . '/../../uploads/';
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }

                    $target_path = $target_dir . $filename;
                    $db_path = 'uploads/' . $filename;

                    if (move_uploaded_file($files['tmp_name'][$i], $target_path)) {
                        $ai_data = ai_service_classify_document($target_path, $is_auto, $clause);
                        $c_num = (int)($ai_data['clause_number'] ?? 8);
                        $reason = $ai_data['reasoning'] ?? '';
                        $ai_comment = $ai_data['ai_comment'] ?? '';

                        $stmt = $pdo->prepare("INSERT INTO documents (office_id, filename, file_path, file_size, clause, upload_token, ai_comment) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$office_id, $filename, $db_path, $file_size, $c_num, $upload_token ?: null, $ai_comment ?: null]);

                        $clause_title_stmt = $pdo->prepare("SELECT title FROM clauses WHERE clause_number = ?");
                        $clause_title_stmt->execute([$c_num]);
                        $c_title = $clause_title_stmt->fetchColumn() ?: 'Operation';

                        $reasonings[] = $filename . ": Clause " . $c_num . " (" . $c_title . ") - " . $reason;
                        $clause_num = $c_num;
                        $clause_title = $c_title;
                        $uploaded_count++;
                    }
                }
            }

            if ($uploaded_count === 0) {
                echo json_encode(['success' => false, 'message' => 'No files were successfully uploaded.']);
                exit;
            }

            $new_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE office_id = ?");
            $new_count_stmt->execute([$office_id]);
            $new_doc_count = (int)$new_count_stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'is_auto' => $is_auto,
                'clause_number' => $clause_num,
                'clause_title' => $clause_title,
                'reasoning' => !empty($reasonings) ? implode("\n\n", $reasonings) : '',
                'office_id' => $office_id,
                'uploaded_count' => $uploaded_count,
                'new_doc_count' => $new_doc_count
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
        }
        exit;
    }
}

$username = $_SESSION['username'] ?? 'User';
$active_page = 'documents';
$base_url    = '';

$offices_query = "SELECT o.id, o.abbreviation, o.name, o.officer_in_charge, COUNT(d.id) as doc_count FROM offices o LEFT JOIN documents d ON o.id = d.office_id";
if ($role === 'auditor') {
    $offices_query .= " WHERE o.id IN (" . implode(',', $assigned_office_ids) . ")";
}
$offices_query .= " GROUP BY o.id ORDER BY o.abbreviation ASC";
$stmt = $pdo->query($offices_query);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$clauses = $pdo->query("SELECT clause_number, title FROM clauses ORDER BY clause_number ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Documents – ISO-QA AI System</title>
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
                <h5 class="fw-bold text-dark mb-0">Documents Repository</h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary rounded-pill px-3 py-1"><?= count($rows) ?> Offices</span>
                    <?php if ($role !== 'auditor'): ?>
                    <button class="btn btn-sm btn-custom rounded-3 px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                        <i class="fas fa-upload"></i> Upload Document
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
                            <th>Documents Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="documents-table-body">
                        <?php if (empty($rows)): ?>
                            <tr id="no-documents-row">
                                <td colspan="5" class="text-center text-muted py-4">No offices or documents found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <tr id="office-row-<?= $row['id'] ?>">
                                    <td class="text-center"><span class="badge text-center bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-2" style="font-size: 0.85rem; background: rgba(37,99,235,0.1) !important; color: #2563eb !important;"><?= htmlspecialchars($row['abbreviation']) ?></span></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($row['name']) ?></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($row['officer_in_charge']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill fw-bold doc-count-badge" id="doc-count-<?= $row['id'] ?>" style="font-size:0.82rem;"><?= $row['doc_count'] ?> Files</span>
                                    </td>
                                    <td>
                                        <a href="../sub_pages/office_documents.php?office_id=<?= $row['id'] ?>" class="btn btn-sm btn-link text-primary p-0 me-3" title="View Documents">
                                            <i class="fas fa-eye fa-lg"></i>
                                        </a>
                                        <button class="btn btn-sm btn-link text-danger p-0 btn-delete-all-docs" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['name']) ?>" title="Delete All Documents" <?= $row['doc_count'] == 0 ? 'disabled style="opacity:0.3; cursor:not-allowed;"' : '' ?>>
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

<?php
$show_office_select = true;
$office_list = $rows;
$clause_list = $clauses;
require_once __DIR__ . '/../../components/upload_document_modal.php';
require_once __DIR__ . '/../../components/delete_confirmation_modal.php';
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let activeOfficeId = 0;
    const deleteConfirmationModal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
    const uploadModal = new bootstrap.Modal(document.getElementById('uploadDocModal'));

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-delete-all-docs');
        if (btn) {
            activeOfficeId = btn.getAttribute('data-id');
            const name = btn.getAttribute('data-name');

            const titleText = document.getElementById('delete-modal-title-text');
            const heading = document.getElementById('delete-modal-heading');
            const message = document.getElementById('delete-modal-message');
            const btnText = document.getElementById('delete-modal-btn-text');

            if (titleText) titleText.textContent = 'Confirm Deletion';
            if (heading) heading.textContent = 'Delete all documents?';
            if (message) {
                message.innerHTML = `Are you sure you want to permanently delete all documents for <strong id="delete-item-name" class="text-dark">${escapeHtml(name)}</strong>? This action cannot be undone.`;
            }
            if (btnText) btnText.textContent = 'Delete All';

            deleteConfirmationModal.show();
        }
    });

    document.getElementById('confirm-delete-btn').addEventListener('click', function() {
        const formData = new FormData();
        formData.append('action', 'delete_all_office_documents');
        formData.append('office_id', activeOfficeId);

        const btn = this;
        const origHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';

        fetch('documents.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = origHTML;
            deleteConfirmationModal.hide();

            if (data.success) {
                const docBadge = document.getElementById(`doc-count-${activeOfficeId}`);
                if (docBadge) {
                    docBadge.textContent = '0 Files';
                }
                const row = document.getElementById(`office-row-${activeOfficeId}`);
                if (row) {
                    const delBtn = row.querySelector('.btn-delete-all-docs');
                    if (delBtn) {
                        delBtn.setAttribute('disabled', 'disabled');
                        delBtn.style.opacity = '0.3';
                        delBtn.style.cursor = 'not-allowed';
                    }
                }
                if (window.showNotification) {
                    window.showNotification('All documents deleted for the selected office.', 'success');
                }
            } else {
                if (window.showNotification) {
                    window.showNotification(data.message || 'Failed to delete documents.', 'error');
                }
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = origHTML;
            deleteConfirmationModal.hide();
            if (window.showNotification) {
                window.showNotification('Network error. Please try again.', 'error');
            }
        });
    });

    let currentUploadAbortController = null;
    let currentUploadToken = null;
    let currentUploadOfficeId = null;
    let isUploadingDocument = false;

    function cancelActiveUpload() {
        if (!isUploadingDocument) return;
        isUploadingDocument = false;

        if (currentUploadAbortController) {
            currentUploadAbortController.abort();
            currentUploadAbortController = null;
        }

        if (currentUploadToken) {
            const tokenToCancel = currentUploadToken;
            const offIdToCancel = currentUploadOfficeId;
            currentUploadToken = null;
            currentUploadOfficeId = null;

            const cancelData = new FormData();
            cancelData.append('action', 'cancel_upload');
            cancelData.append('upload_token', tokenToCancel);
            if (offIdToCancel) cancelData.append('office_id', offIdToCancel);

            fetch('documents.php', {
                method: 'POST',
                body: cancelData
            }).catch(() => {});
        }

        const submitBtn = uploadForm ? uploadForm.querySelector('button[type="submit"]') : null;
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-upload me-2"></i>Upload File';
        }
        if (uploadError) uploadError.classList.add('d-none');
        if (uploadForm) uploadForm.reset();

        if (window.showNotification) {
            window.showNotification('Upload cancelled.', 'info');
        }
    }

    const uploadModalEl = document.getElementById('uploadDocModal');
    if (uploadModalEl) {
        uploadModalEl.addEventListener('hide.bs.modal', function() {
            if (isUploadingDocument) {
                cancelActiveUpload();
            }
        });
    }

    const uploadCancelBtn = document.getElementById('upload-doc-cancel-btn');
    if (uploadCancelBtn) {
        uploadCancelBtn.addEventListener('click', function() {
            if (isUploadingDocument) {
                cancelActiveUpload();
            }
        });
    }

    const uploadForm = document.getElementById('upload-doc-form');
    const uploadError = document.getElementById('upload-doc-error');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            uploadError.classList.add('d-none');

            currentUploadToken = 'up_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            currentUploadAbortController = new AbortController();
            isUploadingDocument = true;

            const formData = new FormData(uploadForm);
            formData.append('action', 'upload_document');
            formData.append('upload_token', currentUploadToken);
            currentUploadOfficeId = formData.get('office_id');

            const submitBtn = uploadForm.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            const isAuto = (formData.get('clause') === 'auto');
            submitBtn.disabled = true;
            submitBtn.innerHTML = isAuto
                ? '<span class="spinner-border spinner-border-sm me-2" role="status"></span>AI Analyzing & Uploading...'
                : '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Uploading File...';

            fetch('documents.php', {
                method: 'POST',
                body: formData,
                signal: currentUploadAbortController.signal
            })
            .then(res => res.json())
            .then(data => {
                if (!isUploadingDocument) {
                    return;
                }
                isUploadingDocument = false;
                currentUploadAbortController = null;
                currentUploadToken = null;
                currentUploadOfficeId = null;

                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;

                if (data.success) {
                    uploadModal.hide();
                    uploadForm.reset();

                    const docBadge = document.getElementById(`doc-count-${data.office_id}`);
                    if (docBadge) {
                        docBadge.textContent = `${data.new_doc_count} Files`;
                        docBadge.classList.add('bg-success-subtle', 'text-success');
                        setTimeout(() => {
                            docBadge.classList.remove('bg-success-subtle', 'text-success');
                        }, 2500);
                    }

                    const row = document.getElementById(`office-row-${data.office_id}`);
                    if (row) {
                        const delBtn = row.querySelector('.btn-delete-all-docs');
                        if (delBtn && data.new_doc_count > 0) {
                            delBtn.removeAttribute('disabled');
                            delBtn.style.opacity = '1';
                            delBtn.style.cursor = 'pointer';
                        }
                    }

                    if (data.is_auto) {
                        window.showNotification('Successfully sorted into Clause ' + data.clause_number + ': ' + data.clause_title, 'gemini', data.reasoning);
                    } else {
                        window.showNotification('Document uploaded successfully!', 'success');
                    }
                } else {
                    uploadError.innerText = data.message || 'An error occurred during upload.';
                    uploadError.classList.remove('d-none');
                }
            })
            .catch(err => {
                if (err.name === 'AbortError' || !isUploadingDocument) {
                    return;
                }
                isUploadingDocument = false;
                currentUploadAbortController = null;
                currentUploadToken = null;
                currentUploadOfficeId = null;
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
                uploadError.innerText = 'Network error. Please try again.';
                uploadError.classList.remove('d-none');
            });
        });
    }

    function syncDocumentsSummary() {
        const formData = new FormData();
        formData.append('action', 'fetch_documents_summary');

        fetch('documents.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.counts) {
                Object.keys(data.counts).forEach(officeId => {
                    const newCount = data.counts[officeId];
                    const docBadge = document.getElementById(`doc-count-${officeId}`);
                    if (docBadge) {
                        const currentCount = parseInt(docBadge.textContent) || 0;
                        if (currentCount !== newCount) {
                            docBadge.textContent = `${newCount} Files`;
                            docBadge.classList.add('bg-success-subtle', 'text-success');
                            setTimeout(() => {
                                docBadge.classList.remove('bg-success-subtle', 'text-success');
                            }, 2500);

                            const row = document.getElementById(`office-row-${officeId}`);
                            if (row) {
                                const delBtn = row.querySelector('.btn-delete-all-docs');
                                if (delBtn) {
                                    if (newCount > 0) {
                                        delBtn.removeAttribute('disabled');
                                        delBtn.style.opacity = '1';
                                        delBtn.style.cursor = 'pointer';
                                    } else {
                                        delBtn.setAttribute('disabled', 'disabled');
                                        delBtn.style.opacity = '0.3';
                                        delBtn.style.cursor = 'not-allowed';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        })
        .catch(() => {});
    }

    setInterval(syncDocumentsSummary, 3000);
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            syncDocumentsSummary();
        }
    });
</script>
<?php require_once __DIR__ . '/../../components/notification.php'; ?>
</body>
</html>
