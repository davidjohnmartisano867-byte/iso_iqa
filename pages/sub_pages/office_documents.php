<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/ai_service.php';

$office_id = (int)($_GET['office_id'] ?? 0);
if ($office_id <= 0) {
    header("Location: ../view/documents.php");
    exit;
}

$pdo = getDBConnection();
$role = $_SESSION['role'] ?? 'admin';

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
    if (!in_array($office_id, $assigned_office_ids)) {
        header("Location: ../view/documents.php");
        exit;
    }
}
if ($role === 'auditee') {
    $stmt = $pdo->prepare("SELECT id FROM offices WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $auditee_office_id = $stmt->fetchColumn();
    if (!$auditee_office_id || $office_id !== (int)$auditee_office_id) {
        if ($auditee_office_id) {
            header("Location: office_documents.php?office_id=" . $auditee_office_id);
        } else {
            header("Location: ../view/dashboard.php");
        }
        exit;
    }
}


$stmt = $pdo->prepare("SELECT abbreviation, name, officer_in_charge FROM offices WHERE id = ?");
$stmt->execute([$office_id]);
$office = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$office) {
    header("Location: ../view/documents.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_finding') {
        $finding_types = $_POST['finding_type'] ?? [];
        $descriptions = $_POST['findings'] ?? [];
        $deadlines = $_POST['deadline'] ?? [];

        if (empty($finding_types)) {
            echo json_encode(['success' => false, 'message' => 'At least one finding block is required.']);
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

    if ($_POST['action'] === 'fetch_office_documents') {
        $stmt = $pdo->prepare("SELECT d.id, d.filename, d.file_size, d.clause, d.ai_comment, d.created_at, c.title AS clause_title FROM documents d LEFT JOIN clauses c ON d.clause = c.clause_number WHERE d.office_id = ? ORDER BY d.created_at DESC");
        $stmt->execute([$office_id]);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $formatted_docs = [];
        foreach ($docs as $d) {
            $formatted_docs[] = [
                'id' => (int)$d['id'],
                'filename' => $d['filename'],
                'file_size' => (int)$d['file_size'],
                'clause' => (int)$d['clause'],
                'ai_comment' => $d['ai_comment'] ?: 'No comments recorded for this document.',
                'clause_title' => $d['clause_title'] ?: 'Operation',
                'created_at' => date('M j, Y', strtotime($d['created_at']))
            ];
        }
        echo json_encode([
            'success' => true,
            'documents' => $formatted_docs,
            'total_count' => count($formatted_docs)
        ]);
        exit;
    }

    if ($_POST['action'] === 'delete_document') {
        $document_id = (int)($_POST['document_id'] ?? 0);
        try {
            $del = $pdo->prepare("DELETE FROM documents WHERE id = ? AND office_id = ?");
            $del->execute([$document_id, $office_id]);

            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE office_id = ?");
            $count_stmt->execute([$office_id]);
            $total_count = (int)$count_stmt->fetchColumn();

            echo json_encode(['success' => true, 'total_count' => $total_count]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'cancel_upload') {
        $token = trim($_POST['upload_token'] ?? '');
        if (!empty($token)) {
            $stmt = $pdo->prepare("SELECT id, filename, file_path FROM documents WHERE office_id = ? AND upload_token = ?");
            $stmt->execute([$office_id, $token]);
            $to_delete = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($to_delete as $doc) {
                $path = __DIR__ . '/../../' . $doc['file_path'];
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
            $del = $pdo->prepare("DELETE FROM documents WHERE office_id = ? AND upload_token = ?");
            $del->execute([$office_id, $token]);

            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE office_id = ?");
            $count_stmt->execute([$office_id]);
            $total_count = (int)$count_stmt->fetchColumn();

            echo json_encode(['success' => true, 'total_count' => $total_count]);
            exit;
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'upload_document') {
        $clause = $_POST['clause'] ?? 'auto';
        $upload_token = trim($_POST['upload_token'] ?? '');
        
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
        $uploaded_docs = [];

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
                        $new_doc_id = (int)$pdo->lastInsertId();

                        $clause_title_stmt = $pdo->prepare("SELECT title FROM clauses WHERE clause_number = ?");
                        $clause_title_stmt->execute([$c_num]);
                        $c_title = $clause_title_stmt->fetchColumn() ?: 'Operation';

                        $reasonings[] = $filename . ": Clause " . $c_num . " (" . $c_title . ") - " . $reason;
                        $clause_num = $c_num;
                        $clause_title = $c_title;
                        $uploaded_count++;

                        $uploaded_docs[] = [
                            'id' => $new_doc_id,
                            'filename' => $filename,
                            'file_size' => (int)$file_size,
                            'clause' => (int)$c_num,
                            'ai_comment' => $ai_comment ?: 'No comments recorded for this document.',
                            'clause_title' => $c_title,
                            'created_at' => date('M j, Y')
                        ];
                    }
                }
            }

            if ($uploaded_count === 0) {
                echo json_encode(['success' => false, 'message' => 'No files were successfully uploaded.']);
                exit;
            }

            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE office_id = ?");
            $count_stmt->execute([$office_id]);
            $total_count = (int)$count_stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'is_auto' => $is_auto,
                'clause_number' => $clause_num,
                'clause_title' => $clause_title,
                'reasoning' => !empty($reasonings) ? implode("\n\n", $reasonings) : '',
                'documents' => $uploaded_docs,
                'total_count' => $total_count
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
        }
        exit;
    }
}

$username = $_SESSION['username'] ?? 'User';
$role     = $_SESSION['role'] ?? 'admin';
$active_page = 'documents';
$base_url    = '../view/';

$stmt = $pdo->prepare("SELECT d.id, d.filename, d.file_size, d.clause, d.ai_comment, d.created_at FROM documents d WHERE d.office_id = ? ORDER BY d.created_at DESC");
$stmt->execute([$office_id]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$clauses = $pdo->query("SELECT clause_number, title FROM clauses ORDER BY clause_number ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Office Documents – ISO-QA AI System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" />
    <link rel="stylesheet" href="../../assets/css/style.css" />
    <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/docx-preview@0.1.18/dist/docx-preview.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <link rel="icon" href="../../assets/image/iso_badge.png">
</head>
<body>

<?php require_once __DIR__ . '/../../components/sidebar.php'; ?>

<div class="main-content">
    <div class="p-4">

        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fas fa-folder-open text-primary me-2"></i>Documents of <?= htmlspecialchars($office['abbreviation']) ?>
                    </h5>
                    <p class="text-muted mb-0 small"><?= htmlspecialchars($office['name']) ?> &bull; OIC: <?= htmlspecialchars($office['officer_in_charge']) ?></p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary rounded-pill px-3 py-1" id="total-docs-badge"><?= count($documents) ?> Files</span>
                    <button class="btn btn-sm btn-custom rounded-3 px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                        <i class="fas fa-upload"></i> Upload Document
                    </button>
                    <?php if ($role !== 'auditee'): ?>
                    <a href="../view/documents.php" class="btn btn-sm btn-secondary rounded-3 px-3 d-flex align-items-center gap-2">
                        <i class="fas fa-arrow-left"></i> Back to Repository
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <ul class="nav nav-pills mb-4 border p-2 rounded-4 bg-light flex-nowrap overflow-x-auto gap-2" id="clauseTabs" role="tablist" style="scrollbar-width: none;">
                <?php foreach ($clauses as $c): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-3 px-3 py-2 fw-semibold <?= $c['clause_number'] === 1 ? 'active' : '' ?>" id="tab-clause-<?= $c['clause_number'] ?>" data-bs-toggle="pill" data-clause="<?= $c['clause_number'] ?>" type="button" role="tab" style="white-space: nowrap; font-size: 0.85rem;">
                            Clause <?= $c['clause_number'] ?>: <?= htmlspecialchars($c['title']) ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-center">
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Date Added</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="documents-table-body">
                        <tr id="no-docs-row" class="d-none">
                            <td colspan="4" class="text-center text-muted py-4">No documents uploaded for this clause yet.</td>
                        </tr>
                        <?php if (empty($documents)): ?>
                            <tr id="empty-repository-row">
                                <td colspan="4" class="text-center text-muted py-4">No documents uploaded for this office yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documents as $doc): 
                                $sizeKB = number_format($doc['file_size'] / 1024, 1) . ' KB';
                                $ext = strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION));
                                $iconClass = 'far fa-file-alt text-secondary';
                                if ($ext === 'pdf') {
                                    $iconClass = 'far fa-file-pdf text-danger';
                                } elseif (in_array($ext, ['doc', 'docx'])) {
                                    $iconClass = 'far fa-file-word text-primary';
                                } elseif (in_array($ext, ['xls', 'xlsx'])) {
                                    $iconClass = 'far fa-file-excel text-success';
                                }
                            ?>
                                <tr id="doc-row-<?= $doc['id'] ?>" class="document-row" data-clause="<?= $doc['clause'] ?>">
                                    <td class="fw-semibold text-dark">
                                        <div class="d-flex flex-column">
                                            <span class="d-inline-flex align-items-center mb-1">
                                                <i class="<?= $iconClass ?> me-2 fa-lg"></i><?= htmlspecialchars($doc['filename']) ?>
                                            </span>
                                            <span class="badge bg-light text-secondary align-self-start px-2 py-1 rounded" style="font-size: 0.72rem; font-weight: 500; width: fit-content; border: 1px solid rgba(108, 117, 125, 0.15) !important;">
                                                <i class="fas fa-folder me-1"></i>General Support
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-center text-secondary"><?= $sizeKB ?></td>
                                    <td class="text-center text-muted" style="font-size:0.85rem;"><?= date('M j, Y', strtotime($doc['created_at'])) ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-link text-primary p-0 me-3 btn-preview-file" data-id="<?= $doc['id'] ?>" data-filename="<?= htmlspecialchars($doc['filename']) ?>" data-path="uploads/<?= htmlspecialchars($doc['filename']) ?>" title="View File">
                                            <i class="fas fa-eye fa-lg"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link text-info p-0 me-3 btn-doc-comments" data-id="<?= $doc['id'] ?>" data-filename="<?= htmlspecialchars($doc['filename']) ?>" data-ai-comment="<?= htmlspecialchars($doc['ai_comment'] ?? 'No comments recorded for this document.') ?>" title="Comments">
                                            <i class="fas fa-comment fa-lg"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link text-warning p-0 me-3 btn-doc-add-finding" data-doc-id="<?= $doc['id'] ?>" data-office-id="<?= $office_id ?>" data-filename="<?= htmlspecialchars($doc['filename']) ?>" title="Add Finding for this Document">
                                            <i class="fas fa-search-plus fa-lg"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link text-danger p-0 btn-delete-doc" data-id="<?= $doc['id'] ?>" data-filename="<?= htmlspecialchars($doc['filename']) ?>" title="Delete Document">
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

<?php require_once __DIR__ . '/../../components/file_preview_modal.php'; ?>

<?php
$show_office_select = false;
$clause_list = $clauses;
require_once __DIR__ . '/../../components/upload_document_modal.php';
require_once __DIR__ . '/../../components/add_finding_modal.php';
require_once __DIR__ . '/../../components/delete_confirmation_modal.php';
require_once __DIR__ . '/../../components/doc_comments_modal.php';
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getDocIconClass(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        if (ext === 'pdf') {
            return 'far fa-file-pdf text-danger';
        } else if (['doc', 'docx'].includes(ext)) {
            return 'far fa-file-word text-primary';
        } else if (['xls', 'xlsx'].includes(ext)) {
            return 'far fa-file-excel text-success';
        }
        return 'far fa-file-alt text-secondary';
    }

    function formatFileSize(bytes) {
        if (!bytes || bytes <= 0) return '0.0 KB';
        return (bytes / 1024).toFixed(1) + ' KB';
    }

    const previewModal = new bootstrap.Modal(document.getElementById('filePreviewModal'));
    const previewContainer = document.getElementById('preview-container');
    const previewLoading = document.getElementById('preview-loading');
    const previewError = document.getElementById('preview-error');
    const previewErrorMsg = document.getElementById('preview-error-msg');
    const modalTitle = document.getElementById('preview-modal-title');

    const uploadDocModalObj = new bootstrap.Modal(document.getElementById('uploadDocModal'));
    const deleteConfirmationModal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    let activeDeleteDocId = null;

    const uploadForm = document.getElementById('upload-doc-form');
    const uploadError = document.getElementById('upload-doc-error');

    function filterDocuments(clauseId) {
        const allDocRows = document.querySelectorAll('.document-row');
        const emptyRepoRow = document.getElementById('empty-repository-row');
        const noDocsRow = document.getElementById('no-docs-row');

        if (allDocRows.length === 0) {
            if (emptyRepoRow) emptyRepoRow.classList.remove('d-none');
            if (noDocsRow) noDocsRow.classList.add('d-none');
            return;
        }

        if (emptyRepoRow) emptyRepoRow.classList.add('d-none');

        let count = 0;
        allDocRows.forEach(row => {
            if (row.getAttribute('data-clause') == clauseId) {
                row.classList.remove('d-none');
                count++;
            } else {
                row.classList.add('d-none');
            }
        });

        if (count === 0) {
            if (noDocsRow) noDocsRow.classList.remove('d-none');
        } else {
            if (noDocsRow) noDocsRow.classList.add('d-none');
        }
    }

    const clauseButtons = document.querySelectorAll('#clauseTabs button');
    clauseButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const clause = this.getAttribute('data-clause');
            filterDocuments(clause);
        });
    });

    filterDocuments(1);

    let currentUploadAbortController = null;
    let currentUploadToken = null;
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
            currentUploadToken = null;
            const cancelData = new FormData();
            cancelData.append('action', 'cancel_upload');
            cancelData.append('upload_token', tokenToCancel);

            fetch('office_documents.php?office_id=<?= $office_id ?>', {
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

            const submitBtn = uploadForm.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            const isAuto = (formData.get('clause') === 'auto');
            submitBtn.disabled = true;
            submitBtn.innerHTML = isAuto
                ? '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Analyzing & Uploading...'
                : '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Uploading File...';

            fetch('office_documents.php?office_id=<?= $office_id ?>', {
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

                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;

                if (data.success) {
                    uploadDocModalObj.hide();
                    uploadForm.reset();

                    const emptyRepoRow = document.getElementById('empty-repository-row');
                    if (emptyRepoRow) {
                        emptyRepoRow.remove();
                    }

                    const tbody = document.getElementById('documents-table-body');
                    if (tbody && data.documents && data.documents.length > 0) {
                        data.documents.forEach(doc => {
                            const tr = document.createElement('tr');
                            tr.id = `doc-row-${doc.id}`;
                            tr.className = 'document-row table-success';
                            tr.setAttribute('data-clause', doc.clause);

                            const iconClass = getDocIconClass(doc.filename);
                            const sizeKB = formatFileSize(doc.file_size);

                            tr.innerHTML = `
                                <td class="fw-semibold text-dark">
                                    <div class="d-flex flex-column">
                                        <span class="d-inline-flex align-items-center mb-1">
                                            <i class="${iconClass} me-2 fa-lg"></i>${escapeHtml(doc.filename)}
                                        </span>
                                        <span class="badge bg-light text-secondary align-self-start px-2 py-1 rounded" style="font-size: 0.72rem; font-weight: 500; width: fit-content; border: 1px solid rgba(108, 117, 125, 0.15) !important;">
                                            <i class="fas fa-folder me-1"></i>General Support
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center text-secondary">${sizeKB}</td>
                                <td class="text-center text-muted" style="font-size:0.85rem;">${escapeHtml(doc.created_at)}</td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-link text-primary p-0 me-3 btn-preview-file" data-id="${doc.id}" data-filename="${escapeHtml(doc.filename)}" data-path="uploads/${escapeHtml(doc.filename)}" title="View File">
                                        <i class="fas fa-eye fa-lg"></i>
                                    </button>
                                    <button class="btn btn-sm btn-link text-info p-0 me-3 btn-doc-comments" data-id="${doc.id}" data-filename="${escapeHtml(doc.filename)}" data-ai-comment="${escapeHtml(doc.ai_comment || 'No comments recorded for this document.')}" title="Comments">
                                        <i class="fas fa-comment fa-lg"></i>
                                    </button>
                                    <button class="btn btn-sm btn-link text-warning p-0 me-3 btn-doc-add-finding" data-doc-id="${doc.id}" data-office-id="<?= $office_id ?>" data-filename="${escapeHtml(doc.filename)}" title="Add Finding for this Document">
                                        <i class="fas fa-search-plus fa-lg"></i>
                                    </button>
                                    <button class="btn btn-sm btn-link text-danger p-0 btn-delete-doc" data-id="${doc.id}" title="Delete Document">
                                        <i class="fas fa-trash-alt fa-lg"></i>
                                    </button>
                                </td>
                            `;

                            const noRow = document.getElementById('no-docs-row');
                            if (noRow && noRow.nextSibling) {
                                tbody.insertBefore(tr, noRow.nextSibling);
                            } else {
                                tbody.appendChild(tr);
                            }

                            setTimeout(() => tr.classList.remove('table-success'), 2500);

                            document.querySelectorAll('.finding-document-select').forEach(select => {
                                const opt = document.createElement('option');
                                opt.value = doc.id;
                                opt.setAttribute('data-office', '<?= $office_id ?>');
                                opt.setAttribute('data-clause', `Clause ${doc.clause}`);
                                opt.setAttribute('data-clause-title', doc.clause_title || '');
                                opt.textContent = `${doc.filename} (Clause ${doc.clause}: ${doc.clause_title || 'General'})`;
                                select.appendChild(opt);
                            });
                        });
                    }

                    if (data.total_count !== undefined) {
                        const totalBadge = document.getElementById('total-docs-badge');
                        if (totalBadge) {
                            totalBadge.textContent = `${data.total_count} Files`;
                        }
                    }

                    const targetTabBtn = document.getElementById(`tab-clause-${data.clause_number}`);
                    if (targetTabBtn) {
                        const tabInstance = bootstrap.Tab.getOrCreateInstance(targetTabBtn);
                        tabInstance.show();
                    }
                    filterDocuments(data.clause_number);

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
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
                uploadError.innerText = 'Network error. Please try again.';
                uploadError.classList.remove('d-none');
            });
        });
    }

    document.addEventListener('click', function(e) {
        const commentBtn = e.target.closest('.btn-doc-comments');
        if (commentBtn) {
            const filename = commentBtn.getAttribute('data-filename');
            const commentText = commentBtn.getAttribute('data-ai-comment') || 'No comments recorded for this document.';
            
            const modalFilename = document.getElementById('doc-comments-modal-filename');
            const modalBody = document.getElementById('doc-comments-modal-body');
            const badge = document.getElementById('doc-comments-modal-badge');
            
            if (modalFilename) modalFilename.querySelector('span').textContent = filename;
            if (modalBody) modalBody.textContent = commentText;
            
            if (badge) {
                if (commentText.includes('⚠️') || commentText.includes('Warning') || commentText.includes('Missing') || commentText.includes('fake') || commentText.includes('Irrelevant')) {
                    badge.className = 'badge bg-warning-subtle text-warning rounded-pill px-3 py-2 fw-semibold';
                    badge.style.cssText = 'background: rgba(245,158,11,0.15) !important; color: #d97706 !important;';
                    badge.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Quality Notice';
                } else {
                    badge.className = 'badge bg-success-subtle text-success rounded-pill px-3 py-2 fw-semibold';
                    badge.style.cssText = 'background: rgba(16,185,129,0.15) !important; color: #059669 !important;';
                    badge.innerHTML = '<i class="fas fa-check-circle me-1"></i>AI Quality Verified';
                }
            }
            
            const commentsModalEl = document.getElementById('docCommentsModal');
            if (commentsModalEl) {
                const commentsModal = bootstrap.Modal.getOrCreateInstance(commentsModalEl);
                commentsModal.show();
            }
            return;
        }

        const findingBtn = e.target.closest('.btn-doc-add-finding');
        if (findingBtn) {
            const docId = findingBtn.getAttribute('data-doc-id');
            const offId = findingBtn.getAttribute('data-office-id');
            if (window.openAddFindingModalWithDoc) {
                window.openAddFindingModalWithDoc({ officeId: offId, docId: docId, lockDoc: true });
            }
            return;
        }

        const previewBtn = e.target.closest('.btn-preview-file');
        if (previewBtn) {
            const filename = previewBtn.getAttribute('data-filename');
            const path = previewBtn.getAttribute('data-path');
            const ext = filename.split('.').pop().toLowerCase();

            modalTitle.innerHTML = `<i class="fas fa-file-alt me-2"></i>Preview: ${escapeHtml(filename)}`;
            previewContainer.innerHTML = '';
            previewError.classList.add('d-none');
            previewLoading.classList.remove('d-none');
            previewModal.show();

            const relativePath = '../../' + path;

            fetch(relativePath)
            .then(res => {
                if (!res.ok) throw new Error('File not found');
                return res.blob();
            })
            .then(blob => {
                previewLoading.classList.add('d-none');
                renderFileBlob(blob, ext, filename, relativePath);
            })
            .catch(err => {
                previewLoading.classList.add('d-none');
                renderMockFile(ext, filename);
            });
            return;
        }

        const deleteBtn = e.target.closest('.btn-delete-doc');
        if (deleteBtn) {
            activeDeleteDocId = deleteBtn.getAttribute('data-id');
            const filename = deleteBtn.getAttribute('data-filename') || 'this document';

            const titleText = document.getElementById('delete-modal-title-text');
            const heading = document.getElementById('delete-modal-heading');
            const message = document.getElementById('delete-modal-message');
            const btnText = document.getElementById('delete-modal-btn-text');

            if (titleText) titleText.textContent = 'Confirm Deletion';
            if (heading) heading.textContent = 'Delete Document?';
            if (message) {
                message.innerHTML = `Are you sure you want to permanently delete <strong id="delete-item-name" class="text-dark">${escapeHtml(filename)}</strong>? This action cannot be undone.`;
            }
            if (btnText) btnText.textContent = 'Delete Document';

            deleteConfirmationModal.show();
            return;
        }
    });

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (!activeDeleteDocId) return;

            confirmDeleteBtn.disabled = true;
            const originalHTML = confirmDeleteBtn.innerHTML;
            confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Deleting...';

            const formData = new FormData();
            formData.append('action', 'delete_document');
            formData.append('document_id', activeDeleteDocId);

            const row = document.getElementById(`doc-row-${activeDeleteDocId}`);

            fetch('office_documents.php?office_id=<?= $office_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                deleteConfirmationModal.hide();
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = originalHTML;

                if (data.success) {
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            if (data.total_count !== undefined) {
                                const totalBadge = document.getElementById('total-docs-badge');
                                if (totalBadge) {
                                    totalBadge.textContent = `${data.total_count} Files`;
                                }
                            }
                            const activeTab = document.querySelector('#clauseTabs button.active');
                            const activeClause = activeTab ? activeTab.getAttribute('data-clause') : 1;
                            filterDocuments(activeClause);
                        }, 300);
                    }
                    if (window.showNotification) {
                        window.showNotification('Document deleted successfully.', 'success');
                    }
                } else {
                    if (window.showNotification) {
                        window.showNotification(data.message || 'An error occurred.', 'error');
                    } else {
                        alert(data.message || 'An error occurred.');
                    }
                }
            })
            .catch(err => {
                deleteConfirmationModal.hide();
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = originalHTML;
                if (window.showNotification) {
                    window.showNotification('Network error. Please try again.', 'error');
                } else {
                    alert('Network error. Please try again.');
                }
            });
        });
    }

    function renderFileBlob(blob, ext, filename, path) {
        if (ext === 'pdf') {
            const url = URL.createObjectURL(blob);
            previewContainer.innerHTML = `<iframe src="${url}" class="w-100 h-100 border-0 rounded-3"></iframe>`;
        } else if (ext === 'docx') {
            const reader = new FileReader();
            reader.onload = function(e) {
                docx.renderAsync(e.target.result, previewContainer)
                .catch(err => renderMockFile(ext, filename));
            };
            reader.readAsArrayBuffer(blob);
        } else if (ext === 'xlsx') {
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, {type: 'array'});
                    const firstSheetName = workbook.SheetNames[0];
                    const worksheet = workbook.Sheets[firstSheetName];
                    const html = XLSX.utils.sheet_to_html(worksheet);
                    previewContainer.innerHTML = `<div class="bg-white p-3 rounded-3 shadow-sm overflow-auto w-100 h-100 table-responsive">${html}</div>`;
                    
                    const table = previewContainer.querySelector('table');
                    if (table) table.className = 'table table-bordered table-striped table-hover small';
                } catch(err) {
                    renderMockFile(ext, filename);
                }
            };
            reader.readAsArrayBuffer(blob);
        } else if (['png', 'jpg', 'jpeg', 'webp', 'gif'].includes(ext)) {
            const url = URL.createObjectURL(blob);
            previewContainer.className = 'w-100 h-100 p-4 d-flex justify-content-center align-items-center';
            previewContainer.innerHTML = `<img src="${url}" class="img-fluid rounded-3 shadow" style="max-height: 100%; object-fit: contain;" />`;
        } else {
            renderMockFile(ext, filename);
        }
    }

    function renderMockFile(ext, filename) {
        previewContainer.className = 'w-100 h-100 p-4';
        if (ext === 'pdf') {
            previewContainer.innerHTML = `
                <div class="bg-white p-5 rounded-3 shadow border h-100 overflow-auto text-dark text-start" style="font-family: serif; max-width: 800px; margin: 0 auto;">
                    <div class="text-center border-bottom pb-4 mb-4">
                        <img src="../../assets/image/iso_badge.png" alt="Seal" width="80" class="mb-3" />
                        <h4 class="fw-bold text-uppercase">INTERNAL QUALITY AUDIT REPORT</h4>
                        <p class="text-muted small mb-0">ISO 9001:2015 Compliance Repository</p>
                    </div>
                    <div class="row mb-4">
                        <div class="col-6"><strong>Document:</strong> ${filename}</div>
                        <div class="col-6 text-end"><strong>Date Added:</strong> ${new Date().toLocaleDateString()}</div>
                    </div>
                    <h5 class="fw-bold mb-3 border-bottom pb-2">1. EXECUTIVE SUMMARY</h5>
                    <p class="mb-4">This document serves as the official compliance verification file for the Quality Assurance processes of the department. Preliminary audits show that internal procedures align successfully with the target standard ISO 9001:2015 clauses, showcasing mature management control frameworks and systematic corrective tracking models.</p>
                    <h5 class="fw-bold mb-3 border-bottom pb-2">2. COMPLIANCE ASSESSMENT</h5>
                    <table class="table table-bordered mb-4 small">
                        <thead>
                            <tr class="table-light">
                                <th>Clause Number</th>
                                <th>Clause Description</th>
                                <th>Compliance Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Clause 4</td><td>Context of the Organization</td><td class="text-success fw-bold">Fully Compliant</td></tr>
                            <tr><td>Clause 5</td><td>Leadership Alignment</td><td class="text-success fw-bold">Fully Compliant</td></tr>
                            <tr><td>Clause 8</td><td>Operations Execution</td><td class="text-warning fw-bold">Minor Gap Logged</td></tr>
                        </tbody>
                    </table>
                </div>
            `;
        } else if (ext === 'docx') {
            previewContainer.innerHTML = `
                <div class="bg-white p-5 rounded-3 shadow border h-100 overflow-auto text-dark text-start" style="max-width: 800px; margin: 0 auto;">
                    <h2 class="fw-bold mb-3">${filename.replace('_', ' ').replace('.docx', '').toUpperCase()}</h2>
                    <h4 class="text-secondary mb-4">Quality Assurance Operational Manual</h4>
                    <p class="lead">Systematic process mapping for the internal verification procedures and corrective documentation models.</p>
                    <hr />
                    <h5 class="fw-bold text-primary mt-4">1. Scope and Objective</h5>
                    <p>The core objective of this guidelines manual is to define the exact workflow required to compile and index document evidence files inside the centralized ISO database. All offices are required to upload files into their respective clause repositories.</p>
                    <h5 class="fw-bold text-primary mt-4">2. Core Responsibilities</h5>
                    <ul>
                        <li>Office Officer in Charge (OIC) oversees collection of audit logs.</li>
                        <li>Internal auditors execute clause verification assessments.</li>
                        <li>System administrators maintain database integrity and credentials security.</li>
                    </ul>
                </div>
            `;
        } else if (ext === 'xlsx') {
            previewContainer.innerHTML = `
                <div class="bg-white p-4 rounded-3 shadow border h-100 overflow-auto text-dark text-start">
                    <h5 class="fw-bold mb-3"><i class="fas fa-table text-success me-2"></i>Spreadsheet Preview: ${filename}</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover small">
                            <thead>
                                <tr class="table-success">
                                    <th>Audit ID</th>
                                    <th>Department/Office</th>
                                    <th>Clause Ref</th>
                                    <th>Auditor</th>
                                    <th>Findings Logged</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>AUD-2025-01</td><td>Academic Affairs</td><td>Clause 4.1</td><td>Dr. Helen Doe</td><td>None</td><td><span class="badge bg-success">Passed</span></td></tr>
                                <tr><td>AUD-2025-02</td><td>Finance and Admin</td><td>Clause 9.3</td><td>Prof. Alan Smith</td><td>Incomplete records</td><td><span class="badge bg-warning text-dark">Pending</span></td></tr>
                                <tr><td>AUD-2025-03</td><td>Human Resources</td><td>Clause 7.2</td><td>Dr. Helen Doe</td><td>Training log gap</td><td><span class="badge bg-danger">Failed</span></td></tr>
                                <tr><td>AUD-2025-04</td><td>Research and Dev</td><td>Clause 8.1</td><td>Engr. Carlos Diaz</td><td>None</td><td><span class="badge bg-success">Passed</span></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        } else {
            previewError.classList.remove('d-none');
            previewErrorMsg.innerText = `Preview unavailable for ${ext.toUpperCase()} file type.`;
        }
    }

    const slider = document.getElementById('clauseTabs');
    if (slider) {
        let isDown = false;
        let startX;
        let scrollLeft;

        slider.addEventListener('mousedown', (e) => {
            isDown = true;
            slider.style.cursor = 'grabbing';
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        });
        slider.addEventListener('mouseleave', () => {
            isDown = false;
            slider.style.cursor = 'grab';
        });
        slider.addEventListener('mouseup', () => {
            isDown = false;
            slider.style.cursor = 'grab';
        });
        slider.addEventListener('mousemove', (e) => {
            if(!isDown) return;
            e.preventDefault();
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 2;
            slider.scrollLeft = scrollLeft - walk;
        });
    }

    let isSyncing = false;
    function syncOfficeDocuments() {
        if (isSyncing || document.hidden) return;
        isSyncing = true;

        const formData = new FormData();
        formData.append('action', 'fetch_office_documents');

        fetch('office_documents.php?office_id=<?= $office_id ?>', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            isSyncing = false;
            if (!data.success || !Array.isArray(data.documents)) return;

            const tbody = document.getElementById('documents-table-body');
            if (!tbody) return;

            const existingRows = tbody.querySelectorAll('tr.document-row');
            const existingIdMap = new Map();
            existingRows.forEach(row => {
                const idAttr = row.getAttribute('id') || '';
                const match = idAttr.match(/^doc-row-(\d+)$/);
                if (match) {
                    existingIdMap.set(parseInt(match[1], 10), row);
                }
            });

            const newIdSet = new Set(data.documents.map(d => d.id));
            let hasChanges = false;

            existingIdMap.forEach((row, docId) => {
                if (!newIdSet.has(docId)) {
                    row.remove();
                    hasChanges = true;
                    document.querySelectorAll(`.finding-document-select option[value="${docId}"]`).forEach(opt => opt.remove());
                }
            });

            data.documents.forEach(doc => {
                if (!existingIdMap.has(doc.id)) {
                    hasChanges = true;
                    const emptyRepoRow = document.getElementById('empty-repository-row');
                    if (emptyRepoRow) emptyRepoRow.remove();

                    const tr = document.createElement('tr');
                    tr.id = `doc-row-${doc.id}`;
                    tr.className = 'document-row table-success';
                    tr.setAttribute('data-clause', doc.clause);

                    const iconClass = getDocIconClass(doc.filename);
                    const sizeKB = formatFileSize(doc.file_size);

                    tr.innerHTML = `
                        <td class="fw-semibold text-dark">
                            <div class="d-flex flex-column">
                                <span class="d-inline-flex align-items-center mb-1">
                                    <i class="${iconClass} me-2 fa-lg"></i>${escapeHtml(doc.filename)}
                                </span>
                                <span class="badge bg-light text-secondary align-self-start px-2 py-1 rounded" style="font-size: 0.72rem; font-weight: 500; width: fit-content; border: 1px solid rgba(108, 117, 125, 0.15) !important;">
                                    <i class="fas fa-folder me-1"></i>General Support
                                </span>
                            </div>
                        </td>
                        <td class="text-center text-secondary">${sizeKB}</td>
                        <td class="text-center text-muted" style="font-size:0.85rem;">${escapeHtml(doc.created_at)}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-link text-primary p-0 me-3 btn-preview-file" data-id="${doc.id}" data-filename="${escapeHtml(doc.filename)}" data-path="uploads/${escapeHtml(doc.filename)}" title="View File">
                                <i class="fas fa-eye fa-lg"></i>
                            </button>
                            <button class="btn btn-sm btn-link text-info p-0 me-3 btn-doc-comments" data-id="${doc.id}" data-filename="${escapeHtml(doc.filename)}" data-ai-comment="${escapeHtml(doc.ai_comment || 'No comments recorded for this document.')}" title="Comments">
                                <i class="fas fa-comment fa-lg"></i>
                            </button>
                            <button class="btn btn-sm btn-link text-warning p-0 me-3 btn-doc-add-finding" data-doc-id="${doc.id}" data-office-id="<?= $office_id ?>" data-filename="${escapeHtml(doc.filename)}" title="Add Finding for this Document">
                                <i class="fas fa-search-plus fa-lg"></i>
                            </button>
                            <button class="btn btn-sm btn-link text-danger p-0 btn-delete-doc" data-id="${doc.id}" data-filename="${escapeHtml(doc.filename)}" title="Delete Document">
                                <i class="fas fa-trash-alt fa-lg"></i>
                            </button>
                        </td>
                    `;

                    const noRow = document.getElementById('no-docs-row');
                    if (noRow && noRow.nextSibling) {
                        tbody.insertBefore(tr, noRow.nextSibling);
                    } else {
                        tbody.prepend(tr);
                    }

                    setTimeout(() => tr.classList.remove('table-success'), 2500);

                    document.querySelectorAll('.finding-document-select').forEach(select => {
                        if (!select.querySelector(`option[value="${doc.id}"]`)) {
                            const opt = document.createElement('option');
                            opt.value = doc.id;
                            opt.setAttribute('data-office', '<?= $office_id ?>');
                            opt.setAttribute('data-clause', `Clause ${doc.clause}`);
                            opt.setAttribute('data-clause-title', doc.clause_title || '');
                            opt.textContent = `${doc.filename} (Clause ${doc.clause}: ${doc.clause_title || 'General'})`;
                            select.appendChild(opt);
                        }
                    });
                }
            });

            const totalBadge = document.getElementById('total-docs-badge');
            if (totalBadge && data.total_count !== undefined) {
                totalBadge.textContent = `${data.total_count} Files`;
            }

            if (hasChanges) {
                const activeTab = document.querySelector('#clauseTabs button.active');
                const activeClause = activeTab ? activeTab.getAttribute('data-clause') : 1;
                filterDocuments(activeClause);
            }
        })
        .catch(() => {
            isSyncing = false;
        });
    }

    setInterval(syncOfficeDocuments, 3000);
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            syncOfficeDocuments();
        }
    });
</script>
<?php require_once __DIR__ . '/../../components/notification.php'; ?>
</body>
</html>