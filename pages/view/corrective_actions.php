<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once __DIR__ . '/../../config/db.php';

$pdo = getDBConnection();
$role = $_SESSION['role'] ?? 'admin';

if ($role === 'auditee') {
    $stmt = $pdo->prepare("SELECT id FROM offices WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $office_id = $stmt->fetchColumn();
    if ($office_id) {
        header("Location: ../sub_pages/findings_capa.php?office_id=" . $office_id);
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

    if ($_POST['action'] === 'fetch_overview_data') {
        $corrective_query = "
            SELECT 
                o.id, 
                o.abbreviation, 
                o.name, 
                o.officer_in_charge, 
                (SELECT COUNT(*) FROM findings WHERE office_id = o.id) as findings_count, 
                (SELECT COUNT(*) FROM capas WHERE finding_id IN (SELECT id FROM findings WHERE office_id = o.id)) as capas_count,
                (SELECT COUNT(*) FROM capas WHERE status = 'Pending' AND finding_id IN (SELECT id FROM findings WHERE office_id = o.id)) as pending_capas_count,
                (SELECT COUNT(*) FROM findings WHERE office_id = o.id AND finding_type != 'Conformity' AND (id NOT IN (SELECT finding_id FROM capas) OR id IN (SELECT finding_id FROM capas WHERE status = 'Rejected' AND finding_id NOT IN (SELECT finding_id FROM capas WHERE status IN ('Pending', 'Approved'))))) as in_progress_findings_count
            FROM offices o
        ";
        if ($role === 'auditor') {
            $corrective_query .= " WHERE o.id IN (" . implode(',', $assigned_office_ids) . ")";
        }
        $corrective_query .= " ORDER BY o.abbreviation ASC";
        $stmt = $pdo->query($corrective_query);
        $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $findings_q = "
            SELECT f.id, f.finding_type, f.findings, o.abbreviation 
            FROM findings f 
            JOIN offices o ON f.office_id = o.id 
            LEFT JOIN capas c ON f.id = c.finding_id 
            WHERE c.id IS NULL AND f.finding_type != 'Conformity'
        ";
        if ($role === 'auditor') {
            $findings_q .= " AND o.id IN (" . implode(',', $assigned_office_ids) . ")";
        }
        $findings_q .= " ORDER BY o.abbreviation ASC, f.created_at DESC";
        $findings_stmt = $pdo->query($findings_q);
        $global_findings = $findings_stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'counts' => $counts,
            'global_findings' => $global_findings
        ]);
        exit;
    }

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
            $office_stmt = $pdo->prepare("SELECT abbreviation FROM offices WHERE id = ?");
            $office_stmt->execute([$office_id]);
            $abbr = $office_stmt->fetchColumn() ?: '';
            echo json_encode([
                'success' => true,
                'inserted' => $inserted,
                'abbreviation' => $abbr
            ]);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    if ($_POST['action'] === 'add_capa') {
        $finding_id = (int)($_POST['finding_id'] ?? 0);
        $root_cause = trim($_POST['root_cause'] ?? '');
        $corrective_actions = trim($_POST['corrective_actions'] ?? '');

        if ($finding_id <= 0 || empty($corrective_actions)) {
            echo json_encode(['success' => false, 'message' => 'Corrective actions are required.']);
            exit;
        }

        $f_stmt = $pdo->prepare("SELECT office_id FROM findings WHERE id = ?");
        $f_stmt->execute([$finding_id]);
        $off_id = $f_stmt->fetchColumn() ?: 0;
        if ($role === 'auditor' && !in_array($off_id, $assigned_office_ids)) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized finding access.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO capas (finding_id, root_cause, corrective_actions, deadline) VALUES (?, ?, ?, NULL)");
            $stmt->execute([$finding_id, $root_cause, $corrective_actions]);
            echo json_encode([
                'success' => true,
                'office_id' => $off_id
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    if ($_POST['action'] === 'ai_suggest_capa') {
        $finding_id = (int)($_POST['finding_id'] ?? 0);
        if ($finding_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Valid finding is required.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("SELECT finding_type, findings FROM findings WHERE id = ?");
            $stmt->execute([$finding_id]);
            $finding = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$finding) {
                throw new Exception("Finding not found.");
            }
            $root_cause_input = trim($_POST['root_cause'] ?? '');
            if (!empty($root_cause_input)) {
                $finding['root_cause'] = $root_cause_input;
            }

            require_once __DIR__ . '/../../config/ai_capa_service.php';
            $suggested = generate_fast_ai_capa($finding);

            echo json_encode([
                'success' => true,
                'root_causes' => $suggested['root_causes'] ?? [],
                'root_cause' => $suggested['root_cause'] ?? '',
                'corrective_actions' => $suggested['corrective_actions'] ?? [],
                'corrective_action' => $suggested['corrective_action'] ?? ''
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    if ($_POST['action'] === 'delete_office_findings') {
        if ($role === 'auditor') {
            echo json_encode(['success' => false, 'message' => 'Auditors are not authorized to delete office findings/CAPAs.']);
            exit;
        }
        $office_id = (int)($_POST['office_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM findings WHERE office_id = ?");
            $stmt->execute([$office_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'ai_audit_all') {
        try {
            $offices_query = "SELECT id, name, abbreviation FROM offices";
            if ($role === 'auditor') {
                $offices_query .= " WHERE id IN (" . implode(',', $assigned_office_ids) . ")";
            }
            $stmt = $pdo->query($offices_query);
            $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $payload = [];
            foreach ($offices as $office) {
                $o_id = (int)$office['id'];
                $d_stmt = $pdo->prepare("SELECT filename FROM documents WHERE office_id = ?");
                $d_stmt->execute([$o_id]);
                $documents = $d_stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $f_stmt = $pdo->prepare("SELECT findings FROM findings WHERE office_id = ?");
                $f_stmt->execute([$o_id]);
                $existing = $f_stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $payload[] = [
                    'office_id' => $o_id,
                    'office_name' => $office['name'],
                    'office_abbr' => $office['abbreviation'],
                    'documents' => $documents,
                    'existing_findings' => $existing
                ];
            }
            $py_path = __DIR__ . '/../../generate_all_office_findings.py';
            $command = "python " . escapeshellarg($py_path);
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            $process = proc_open($command, $descriptors, $pipes);
            if (is_resource($process)) {
                fwrite($pipes[0], json_encode($payload));
                fclose($pipes[0]);
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                proc_close($process);
            } else {
                throw new Exception("Unable to run the Python compliance analyzer.");
            }
            $findings = json_decode($output, true);
            if (!is_array($findings)) {
                throw new Exception("Invalid response from the compliance analyzer.");
            }
            $deadline = date('Y-m-d', strtotime('+7 days'));
            $inserted = [];
            $pdo->beginTransaction();
            foreach ($findings as $f) {
                $o_id = (int)($f['office_id'] ?? 0);
                $type = trim($f['finding_type'] ?? '');
                $desc = trim($f['findings'] ?? '');
                if ($o_id > 0 && !empty($type) && !empty($desc)) {
                    $stmt = $pdo->prepare("INSERT INTO findings (office_id, finding_type, findings, deadline) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$o_id, $type, $desc, $deadline]);
                    $finding_id = $pdo->lastInsertId();
                    $inserted[] = [
                        'id' => $finding_id,
                        'office_id' => $o_id,
                        'finding_type' => $type,
                        'findings' => $desc
                    ];
                }
            }
            $pdo->commit();
            $update_stmt = $pdo->query("
                SELECT 
                    o.id, 
                    (SELECT COUNT(*) FROM findings WHERE office_id = o.id) as findings_count, 
                    (SELECT COUNT(*) FROM capas WHERE finding_id IN (SELECT id FROM findings WHERE office_id = o.id)) as capas_count,
                    (SELECT COUNT(*) FROM capas WHERE status = 'Pending' AND finding_id IN (SELECT id FROM findings WHERE office_id = o.id)) as pending_capas_count,
                    (SELECT COUNT(*) FROM findings WHERE office_id = o.id AND finding_type != 'Conformity' AND (id NOT IN (SELECT finding_id FROM capas) OR id IN (SELECT finding_id FROM capas WHERE status = 'Rejected' AND finding_id NOT IN (SELECT finding_id FROM capas WHERE status IN ('Pending', 'Approved'))))) as in_progress_findings_count
                FROM offices o
            ");
            $new_counts = $update_stmt->fetchAll(PDO::FETCH_ASSOC);
            $global_findings_stmt = $pdo->query("SELECT f.id, f.finding_type, f.findings, o.abbreviation FROM findings f JOIN offices o ON f.office_id = o.id ORDER BY o.abbreviation ASC, f.created_at DESC");
            $all_global_findings = $global_findings_stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'inserted' => $inserted,
                'counts' => $new_counts,
                'global_findings' => $all_global_findings
            ]);
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
$active_page = 'corrective';
$base_url    = '';

$corrective_query = "
    SELECT 
        o.id, 
        o.abbreviation, 
        o.name, 
        o.officer_in_charge, 
        (SELECT COUNT(*) FROM findings WHERE office_id = o.id) as findings_count, 
        (SELECT COUNT(*) FROM capas WHERE finding_id IN (SELECT id FROM findings WHERE office_id = o.id)) as capas_count,
        (SELECT COUNT(*) FROM capas WHERE status = 'Pending' AND finding_id IN (SELECT id FROM findings WHERE office_id = o.id)) as pending_capas_count,
        (SELECT COUNT(*) FROM findings WHERE office_id = o.id AND finding_type != 'Conformity' AND (id NOT IN (SELECT finding_id FROM capas) OR id IN (SELECT finding_id FROM capas WHERE status = 'Rejected' AND finding_id NOT IN (SELECT finding_id FROM capas WHERE status IN ('Pending', 'Approved'))))) as in_progress_findings_count
    FROM offices o
";
if ($role === 'auditor') {
    $corrective_query .= " WHERE o.id IN (" . implode(',', $assigned_office_ids) . ")";
}
$corrective_query .= " ORDER BY o.abbreviation ASC";
$stmt = $pdo->query($corrective_query);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$findings_q = "
    SELECT f.id, f.finding_type, f.findings, o.abbreviation 
    FROM findings f 
    JOIN offices o ON f.office_id = o.id 
    LEFT JOIN capas c ON f.id = c.finding_id 
    WHERE c.id IS NULL AND f.finding_type != 'Conformity'
";
if ($role === 'auditor') {
    $findings_q .= " AND o.id IN (" . implode(',', $assigned_office_ids) . ")";
}
$findings_q .= " ORDER BY o.abbreviation ASC, f.created_at DESC";
$findings_stmt = $pdo->query($findings_q);
$global_findings = $findings_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Findings and Corrective Actions - ISO-QA AI System</title>
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
                <div>
                    <h5 class="fw-bold text-dark mb-1">Findings & Corrective Actions (CAPAs)</h5>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="d-none">
                        <?php if ($role !== 'auditor'): ?>
                            <button class="btn btn-sm btn-custom rounded-3 px-3 d-flex align-items-center gap-2" id="btn-ai-audit-all" style="background: linear-gradient(135deg, #7c3aed, #2563eb) !important; border: none; color: #fff;">
                                <i class="fas fa-wand-magic-sparkles"></i> AI Compliance Audit
                            </button>
                        <?php endif; ?>
                    </div>    
                    <button class="btn btn-sm btn-custom rounded-3 px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addFindingModal">
                        <i class="fas fa-exclamation-triangle"></i> Add Findings
                    </button>
                    <?php if ($role !== 'auditor'): ?>
                    <button class="btn btn-sm btn-outline-primary rounded-3 px-3 d-flex align-items-center gap-2 fw-semibold" id="btn-global-add-capa" data-bs-toggle="modal" data-bs-target="#addCapaModal" <?= empty($global_findings) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                        <i class="fas fa-tools"></i> Add CAPA
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-center">
                            <th>Office Name</th>
                            <th>Officer in Charge</th>
                            <th>Findings Registered</th>
                            <th>CAPAs Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="offices-table-body">
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No registered offices found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <tr id="office-row-<?= $row['id'] ?>">
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($row['name']) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($row['officer_in_charge']) ?> </td>
                                    <td class="text-center">
                                        <div class="position-relative d-inline-block">
                                            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill fw-bold badge-findings-count" style="font-size:0.82rem;"><?= $row['findings_count'] ?> Findings</span>
                                            <?php if ($role === 'admin' && ($row['in_progress_findings_count'] ?? 0) > 0): ?>
                                                <span class="badge-findings-indicator position-absolute top-0 start-100 translate-middle bg-warning border border-2 border-white rounded-circle shadow-sm" style="width: 14px; height: 14px; margin-top: 3px; margin-left: -5px; background-color: #f59e0b !important;" title="<?= $row['in_progress_findings_count'] ?> finding(s) in progress"></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="position-relative d-inline-block">
                                            <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill fw-bold badge-capas-count" style="font-size:0.82rem;"><?= $row['capas_count'] ?> CAPAs</span>
                                            <?php if (in_array($role, ['admin', 'auditor']) && ($row['pending_capas_count'] ?? 0) > 0): ?>
                                                <span class="badge-capas-indicator position-absolute top-0 start-100 translate-middle bg-warning border border-2 border-white rounded-circle shadow-sm" style="width: 14px; height: 14px; margin-top: 3px; margin-left: -5px; background-color: #f59e0b !important;" title="<?= $row['pending_capas_count'] ?> CAPA(s) pending approval"></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <a href="../sub_pages/findings_capa.php?office_id=<?= $row['id'] ?>" class="btn btn-sm btn-link text-primary p-0 <?= $role !== 'auditor' ? 'me-3' : '' ?>" title="View Findings & CAPAs">
                                            <i class="fas fa-eye fa-lg"></i>
                                        </a>
                                        <?php if ($role !== 'auditor'): ?>
                                        <button class="btn btn-sm btn-link text-danger p-0 btn-delete-all-findings" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['name']) ?>" title="Delete All Findings" <?= $row['findings_count'] == 0 ? 'disabled style="opacity:0.3; cursor:not-allowed;"' : '' ?>>
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

<?php
$show_office_select = true;
$office_list = $rows;
require_once __DIR__ . '/../../components/add_finding_modal.php';
?>

<?php
$findings_dropdown = $global_findings;
$show_ai_suggest = true;
require_once __DIR__ . '/../../components/add_capa_modal.php';
require_once __DIR__ . '/../../components/approve_confirmation_modal.php';
require_once __DIR__ . '/../../components/reject_confirmation_modal.php';
require_once __DIR__ . '/../../components/delete_confirmation_modal.php';
?>

<div class="modal fade" id="aiAuditProgressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="aiAuditProgressModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden; background: #ffffff;">
            <div class="modal-body p-5 text-center">
                <div class="mb-4 position-relative d-inline-block">
                    <div class="spinner-border text-primary" role="status" style="width: 5rem; height: 5rem; border-width: 0.35em;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="position-absolute top-50 start-50 translate-middle">
                        <i class="fas fa-robot text-primary fa-2x animate-pulse"></i>
                    </div>
                </div>
                <h4 class="fw-bold mb-2 text-dark" style="font-family: 'Outfit', sans-serif;">ISO 9001 AI Auditor Active</h4>
                <p class="text-secondary small mb-4" id="ai-progress-status">Initializing QMS compliance scans...</p>
                <div class="progress rounded-pill bg-light mb-4" style="height: 8px;">
                    <div id="ai-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%; transition: width 0.4s ease;"></div>
                </div>
                <div class="border rounded-3 p-3 bg-light text-start small d-none" id="ai-results-recap" style="max-height: 180px; overflow-y: auto;">
                    <h6 class="fw-bold mb-2 text-dark"><i class="fas fa-check-circle text-success me-1"></i>Audit Completed Successfully</h6>
                    <ul class="list-unstyled mb-0 text-secondary" id="ai-recap-list"></ul>
                </div>
                <div class="d-none mt-4 justify-content-center" id="ai-modal-close-container">
                    <button type="button" class="btn btn-primary fw-semibold px-4 py-2 rounded-3 border-0" data-bs-dismiss="modal" style="background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;">Done</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/notification.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.CUSTOM_ADD_FINDING_HANDLER = true;
    let activeOfficeId = 0;

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    const deleteConfirmationModalEl = document.getElementById('deleteConfirmationModal');
    const deleteConfirmationModal = deleteConfirmationModalEl ? new bootstrap.Modal(deleteConfirmationModalEl) : null;
    const addFindingModal = new bootstrap.Modal(document.getElementById('addFindingModal'));
    const addCapaModal = new bootstrap.Modal(document.getElementById('addCapaModal'));

    document.querySelectorAll('.btn-delete-all-findings').forEach(btn => {
        btn.addEventListener('click', function() {
            activeOfficeId = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            const titleText = document.getElementById('delete-modal-title-text');
            const heading = document.getElementById('delete-modal-heading');
            const message = document.getElementById('delete-modal-message');
            const btnText = document.getElementById('delete-modal-btn-text');

            if (titleText) titleText.textContent = 'Confirm Deletion';
            if (heading) heading.textContent = 'Delete all findings?';
            if (message) {
                message.innerHTML = `Are you sure you want to permanently delete all findings and corrective actions for <strong id="delete-item-name" class="text-dark">${escapeHtml(name)}</strong>? This action cannot be undone.`;
            }
            if (btnText) btnText.textContent = 'Delete All';

            if (deleteConfirmationModal) {
                deleteConfirmationModal.show();
            }
        });
    });

    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (!activeOfficeId) return;
            const originalHTML = confirmDeleteBtn.innerHTML;
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Deleting...';

            const formData = new FormData();
            formData.append('action', 'delete_office_findings');
            formData.append('office_id', activeOfficeId);

            fetch('corrective_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = originalHTML;
                if (deleteConfirmationModal) {
                    deleteConfirmationModal.hide();
                }
                if (data.success) {
                    const row = document.getElementById('office-row-' + activeOfficeId);
                    if (row) {
                        row.querySelector('.badge-findings-count').innerText = '0 Findings';
                        row.querySelector('.badge-capas-count').innerText = '0 CAPAs';
                        
                        const delBtn = row.querySelector('.btn-delete-all-findings');
                        if (delBtn) {
                            delBtn.disabled = true;
                            delBtn.style.opacity = '0.3';
                            delBtn.style.cursor = 'not-allowed';
                        }
                    }

                    const capaSelect = document.getElementById('capa_finding');
                    if (capaSelect) {
                        Array.from(capaSelect.options).forEach(opt => {
                            if (opt.text.startsWith('[' + activeOfficeId + ']') || opt.className.includes('finding-opt-')) {
                                opt.remove();
                            }
                        });
                        
                        if (capaSelect.options.length <= 1) {
                            const addCapaBtn = document.getElementById('btn-global-add-capa');
                            if (addCapaBtn) {
                                addCapaBtn.disabled = true;
                                addCapaBtn.style.opacity = '0.5';
                                addCapaBtn.style.cursor = 'not-allowed';
                            }
                        }
                    }

                    if (window.refreshSidebarBadges) window.refreshSidebarBadges();
                    window.showNotification('All findings and corrective actions deleted successfully.', 'success');
                } else {
                    window.showNotification(data.message || 'An error occurred.', 'danger');
                }
            })
            .catch(err => {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = originalHTML;
                if (deleteConfirmationModal) {
                    deleteConfirmationModal.hide();
                }
                window.showNotification('Network error. Please try again.', 'danger');
            });
        });
    }

    const addFindingForm = document.getElementById('add-finding-form');
    const addFindingError = document.getElementById('add-finding-error');
    if (addFindingForm) {
        addFindingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            addFindingError.classList.add('d-none');

            const formData = new FormData(addFindingForm);
            formData.append('action', 'add_finding');

            const submitBtn = addFindingForm.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';

            fetch('corrective_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;

                if (data.success) {
                    addFindingModal.hide();
                    addFindingForm.reset();

                    const container = document.getElementById('findings-container');
                    if (container) {
                        const rows = container.querySelectorAll('.finding-row');
                        rows.forEach((r, idx) => {
                            if (idx > 0) r.remove();
                        });
                        const firstDocSelect = container.querySelector('.finding-document-select');
                        if (firstDocSelect) {
                            firstDocSelect.selectedIndex = 0;
                            const clauseText = container.querySelector('.clause-name-text');
                            if (clauseText) {
                                clauseText.innerText = 'None selected';
                                clauseText.className = 'fw-semibold text-muted clause-name-text';
                            }
                        }
                    }

                    const row = document.getElementById('office-row-' + formData.get('office_id'));
                    if (row) {
                        const badge = row.querySelector('.badge-findings-count');
                        const currentVal = parseInt(badge.innerText) || 0;
                        const addedCount = data.inserted ? data.inserted.length : 0;
                        badge.innerText = (currentVal + addedCount) + ' Findings';

                        const delBtn = row.querySelector('.btn-delete-all-findings');
                        if (delBtn) {
                            delBtn.disabled = false;
                            delBtn.style.opacity = '1';
                            delBtn.style.cursor = 'pointer';
                        }
                    }

                    const capaSelect = document.getElementById('capa_finding');
                    if (capaSelect && data.inserted) {
                        data.inserted.forEach(f => {
                            const opt = document.createElement('option');
                            opt.value = f.id;
                            opt.className = 'finding-opt-' + f.id;
                            opt.text = '[' + data.abbreviation + '] ' + f.finding_type + ' – ' + f.findings.substring(0, 60) + '...';
                            capaSelect.appendChild(opt);
                        });

                        const addCapaBtn = document.getElementById('btn-global-add-capa');
                        if (addCapaBtn) {
                            addCapaBtn.disabled = false;
                            addCapaBtn.style.opacity = '1';
                            addCapaBtn.style.cursor = 'pointer';
                        }
                    }

                    if (window.refreshSidebarBadges) window.refreshSidebarBadges();
                    window.showNotification('Findings registered successfully.', 'success');
                } else {
                    addFindingError.innerText = data.message || 'An error occurred.';
                    addFindingError.classList.remove('d-none');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
                addFindingError.innerText = 'Network error. Please try again.';
                addFindingError.classList.remove('d-none');
            });
        });
    }

    const addCapaForm = document.getElementById('add-capa-form');
    const addCapaError = document.getElementById('add-capa-error');
    if (addCapaForm) {
        addCapaForm.addEventListener('submit', function(e) {
            e.preventDefault();
            addCapaError.classList.add('d-none');

            const formData = new FormData(addCapaForm);
            formData.append('action', 'add_capa');

            const submitBtn = addCapaForm.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';

            fetch('corrective_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;

                if (data.success) {
                    addCapaModal.hide();
                    addCapaForm.reset();

                    const submittedFindingId = formData.get('finding_id');
                    const findingOpt = document.querySelector('#capa_finding .finding-opt-' + submittedFindingId);
                    if (findingOpt) {
                        findingOpt.remove();
                    }
                    const capaSelect = document.getElementById('capa_finding');
                    if (capaSelect && capaSelect.options.length <= 1) {
                        const addCapaBtn = document.getElementById('btn-global-add-capa');
                        if (addCapaBtn) {
                            addCapaBtn.disabled = true;
                            addCapaBtn.style.opacity = '0.5';
                            addCapaBtn.style.cursor = 'not-allowed';
                        }
                    }

                    const row = document.getElementById('office-row-' + data.office_id);
                    if (row) {
                        const badge = row.querySelector('.badge-capas-count');
                        const currentVal = parseInt(badge.innerText) || 0;
                        badge.innerText = (currentVal + 1) + ' CAPAs';
                    }

                    if (window.refreshSidebarBadges) window.refreshSidebarBadges();
                    window.showNotification('Corrective action (CAPA) saved successfully.', 'success');
                } else {
                    addCapaError.innerText = data.message || 'An error occurred.';
                    addCapaError.classList.remove('d-none');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
                addCapaError.innerText = 'Network error. Please try again.';
                addCapaError.classList.remove('d-none');
            });
        });
    }

    function runAiCapaSuggestion(fid, explicitRootCause) {
        if (!fid) return;

        const aiSuggestBtn = document.getElementById('btn-ai-suggest-capa');
        const originalHTML = aiSuggestBtn ? aiSuggestBtn.innerHTML : '';

        if (aiSuggestBtn) {
            aiSuggestBtn.disabled = true;
            aiSuggestBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Analyzing...';
        }

        const formData = new FormData();
        formData.append('action', 'ai_suggest_capa');
        formData.append('finding_id', fid);
        const rootVal = (explicitRootCause !== undefined && explicitRootCause !== null) ? explicitRootCause : (document.getElementById('root_cause')?.value.trim() || '');
        if (rootVal) {
            formData.append('root_cause', rootVal);
        }

        fetch('corrective_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (aiSuggestBtn) {
                aiSuggestBtn.disabled = false;
                aiSuggestBtn.innerHTML = originalHTML;
            }

            if (data.success) {
                const rootWrapper = document.getElementById('root_cause_suggestions_wrapper');
                const rootToggleBtn = document.getElementById('btn-toggle-root-suggestions');

                if (data.root_causes && data.root_causes.length > 0 && !explicitRootCause) {
                    const list = document.getElementById('root_cause_suggestions_list');
                    if (list) {
                        list.innerHTML = '';
                        data.root_causes.forEach((rc, idx) => {
                            const item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'list-group-item list-group-item-action text-start p-2 border-bottom d-flex align-items-start gap-2';
                            item.style.whiteSpace = 'normal';
                            item.style.wordBreak = 'break-word';
                            item.style.fontSize = '0.8rem';
                            item.innerHTML = `
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5 mt-0.5 fw-bold" style="font-size: 0.7rem; flex-shrink: 0;">${idx + 1}</span>
                                <span class="text-dark" style="line-height: 1.45; word-break: break-word;">${rc}</span>
                            `;
                            item.addEventListener('click', function() {
                                const rootTxt = document.getElementById('root_cause');
                                if (rootTxt) {
                                    rootTxt.value = rc;
                                }
                                if (rootWrapper) {
                                    rootWrapper.classList.add('d-none');
                                }
                                if (rootToggleBtn) {
                                    rootToggleBtn.classList.remove('d-none');
                                }
                                window.showNotification('Root cause applied. Tailoring corrective actions...', 'info');
                                runAiCapaSuggestion(fid, rc);
                            });
                            list.appendChild(item);
                        });
                    }
                    if (rootWrapper) {
                        rootWrapper.classList.remove('d-none');
                    }
                    if (rootToggleBtn) {
                        rootToggleBtn.classList.remove('d-none');
                    }
                }

                const capaWrapper = document.getElementById('capa_suggestions_wrapper');
                const capaToggleBtn = document.getElementById('btn-toggle-capa-suggestions');
                const capaList = document.getElementById('capa_suggestions_list');

                if (rootVal && data.corrective_actions && data.corrective_actions.length > 0) {
                    if (capaList) {
                        capaList.innerHTML = '';
                        data.corrective_actions.forEach((ca, idx) => {
                            const item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'list-group-item list-group-item-action text-start p-2 border-bottom d-flex align-items-start gap-2';
                            item.style.whiteSpace = 'normal';
                            item.style.wordBreak = 'break-word';
                            item.style.fontSize = '0.8rem';
                            item.innerHTML = `
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 mt-0.5 fw-bold" style="font-size: 0.7rem; flex-shrink: 0;">${idx + 1}</span>
                                <span class="text-dark" style="line-height: 1.45; word-break: break-word;">${ca}</span>
                            `;
                            item.addEventListener('click', function() {
                                const capaTxt = document.getElementById('corrective_actions');
                                if (capaTxt) {
                                    capaTxt.value = ca;
                                }
                                if (capaWrapper) {
                                    capaWrapper.classList.add('d-none');
                                }
                                if (capaToggleBtn) {
                                    capaToggleBtn.classList.remove('d-none');
                                }
                                window.showNotification('Corrective action applied.', 'success');
                            });
                            capaList.appendChild(item);
                        });
                    }
                    if (capaWrapper) {
                        capaWrapper.classList.remove('d-none');
                    }
                    if (capaToggleBtn) {
                        capaToggleBtn.classList.add('d-none');
                    }
                } else if (!rootVal) {
                    if (capaWrapper) {
                        capaWrapper.classList.add('d-none');
                    }
                    if (capaToggleBtn) {
                        capaToggleBtn.classList.add('d-none');
                    }
                    if (capaList) {
                        capaList.innerHTML = '';
                    }
                }

                if (rootVal) {
                    window.showNotification('Tailored corrective action suggestions generated for root cause.', 'success');
                } else {
                    window.showNotification('AI root causes suggested. Select an option to generate tailored corrective actions.', 'info');
                }
            } else {
                window.showNotification(data.message || 'Failed to generate suggestion.', 'danger');
            }
        })
        .catch(err => {
            if (aiSuggestBtn) {
                aiSuggestBtn.disabled = false;
                aiSuggestBtn.innerHTML = originalHTML;
            }
            window.showNotification('Network error. Please try again.', 'danger');
        });
    }

    const capaFindingSelect = document.getElementById('capa_finding');
    if (capaFindingSelect) {
        capaFindingSelect.addEventListener('change', function() {
            const rootTxt = document.getElementById('root_cause');
            const capaTxt = document.getElementById('corrective_actions');
            if (rootTxt) rootTxt.value = '';
            if (capaTxt) capaTxt.value = '';
            const rootWrapper = document.getElementById('root_cause_suggestions_wrapper');
            const rootToggleBtn = document.getElementById('btn-toggle-root-suggestions');
            const capaWrapper = document.getElementById('capa_suggestions_wrapper');
            const capaToggleBtn = document.getElementById('btn-toggle-capa-suggestions');
            if (rootWrapper) rootWrapper.classList.add('d-none');
            if (rootToggleBtn) rootToggleBtn.classList.add('d-none');
            if (capaWrapper) capaWrapper.classList.add('d-none');
            if (capaToggleBtn) capaToggleBtn.classList.add('d-none');

            if (this.value) {
                runAiCapaSuggestion(this.value);
            }
        });
    }

    const aiSuggestBtn = document.getElementById('btn-ai-suggest-capa');
    if (aiSuggestBtn) {
        aiSuggestBtn.addEventListener('click', function() {
            const fid = document.getElementById('capa_finding')?.value;
            if (!fid) {
                window.showNotification('Please select a target finding first.', 'danger');
                return;
            }
            runAiCapaSuggestion(fid);
        });
    }

    const toggleRootSuggestionsBtn = document.getElementById('btn-toggle-root-suggestions');
    if (toggleRootSuggestionsBtn) {
        toggleRootSuggestionsBtn.addEventListener('click', function() {
            const wrapper = document.getElementById('root_cause_suggestions_wrapper');
            if (wrapper) {
                wrapper.classList.toggle('d-none');
            }
        });
    }

    const closeRootSuggestionsBtn = document.getElementById('btn-close-root-suggestions');
    if (closeRootSuggestionsBtn) {
        closeRootSuggestionsBtn.addEventListener('click', function() {
            const wrapper = document.getElementById('root_cause_suggestions_wrapper');
            const toggleBtn = document.getElementById('btn-toggle-root-suggestions');
            if (wrapper) {
                wrapper.classList.add('d-none');
            }
            if (toggleBtn) {
                toggleBtn.classList.remove('d-none');
            }
        });
    }

    const toggleCapaSuggestionsBtn = document.getElementById('btn-toggle-capa-suggestions');
    if (toggleCapaSuggestionsBtn) {
        toggleCapaSuggestionsBtn.addEventListener('click', function() {
            const wrapper = document.getElementById('capa_suggestions_wrapper');
            if (wrapper) {
                wrapper.classList.toggle('d-none');
            }
        });
    }

    const closeCapaSuggestionsBtn = document.getElementById('btn-close-capa-suggestions');
    if (closeCapaSuggestionsBtn) {
        closeCapaSuggestionsBtn.addEventListener('click', function() {
            const wrapper = document.getElementById('capa_suggestions_wrapper');
            const toggleBtn = document.getElementById('btn-toggle-capa-suggestions');
            if (wrapper) {
                wrapper.classList.add('d-none');
            }
            if (toggleBtn) {
                toggleBtn.classList.remove('d-none');
            }
        });
    }

    const aiAuditBtn = document.getElementById('btn-ai-audit-all');
    if (aiAuditBtn) {
        aiAuditBtn.addEventListener('click', function() {
            const progressModal = new bootstrap.Modal(document.getElementById('aiAuditProgressModal'));
            const pBar = document.getElementById('ai-progress-bar');
            const pStatus = document.getElementById('ai-progress-status');
            const pRecap = document.getElementById('ai-results-recap');
            const pRecapList = document.getElementById('ai-recap-list');
            const pCloseContainer = document.getElementById('ai-modal-close-container');

            pBar.style.width = '0%';
            pBar.ariaValueNow = 0;
            pStatus.innerText = 'Initializing internal QMS audit protocols...';
            pRecap.classList.add('d-none');
            pRecapList.innerHTML = '';
            pCloseContainer.classList.add('d-none');
            pCloseContainer.classList.remove('d-flex');

            progressModal.show();

            let progress = 0;
            const progressInterval = setInterval(() => {
                if (progress < 90) {
                    progress += Math.floor(Math.random() * 8) + 3;
                    if (progress > 90) progress = 90;
                    pBar.style.width = progress + '%';
                    
                    if (progress < 25) {
                        pStatus.innerText = 'Analyzing office organizational contexts...';
                    } else if (progress < 50) {
                        pStatus.innerText = 'Verifying uploaded document trails and evidence packages...';
                    } else if (progress < 75) {
                        pStatus.innerText = 'Performing signature and approval checks on uploaded documents...';
                    } else {
                        pStatus.innerText = 'Consolidating ISO 9001:2015 auditor findings...';
                    }
                }
            }, 600);

            const formData = new FormData();
            formData.append('action', 'ai_audit_all');

            fetch('corrective_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                clearInterval(progressInterval);
                if (data.success) {
                    pBar.style.width = '100%';
                    pStatus.innerText = 'Audit completed! Gaps and findings registered.';

                    if (data.inserted && data.inserted.length > 0) {
                        data.inserted.forEach(item => {
                            const li = document.createElement('li');
                            li.className = 'mb-1 text-dark border-bottom pb-1';
                            li.innerHTML = `<span class="badge bg-danger-subtle text-danger fw-bold me-1">${item.finding_type}</span> <strong class="text-secondary">${item.findings}</strong>`;
                            pRecapList.appendChild(li);
                        });
                        pRecap.classList.remove('d-none');
                    } else {
                        const li = document.createElement('li');
                        li.className = 'mb-1 text-success';
                        li.innerText = 'No new findings detected. All offices conform fully with QMS procedures.';
                        pRecapList.appendChild(li);
                        pRecap.classList.remove('d-none');
                    }

                    if (data.counts) {
                        data.counts.forEach(c => {
                            const row = document.getElementById('office-row-' + c.id);
                            if (row) {
                                row.querySelector('.badge-findings-count').innerText = c.findings_count + ' Findings';
                                row.querySelector('.badge-capas-count').innerText = c.capas_count + ' CAPAs';

                                const delBtn = row.querySelector('.btn-delete-all-findings');
                                if (delBtn) {
                                    if (c.findings_count > 0) {
                                        delBtn.disabled = false;
                                        delBtn.style.opacity = '1';
                                        delBtn.style.cursor = 'pointer';
                                    } else {
                                        delBtn.disabled = true;
                                        delBtn.style.opacity = '0.3';
                                        delBtn.style.cursor = 'not-allowed';
                                    }
                                }
                            }
                        });
                    }

                    const capaSelect = document.getElementById('capa_finding');
                    if (capaSelect && data.global_findings) {
                        capaSelect.innerHTML = '';
                        data.global_findings.forEach(gf => {
                            const opt = document.createElement('option');
                            opt.value = gf.id;
                            opt.className = 'finding-opt-' + gf.id;
                            opt.text = '[' + gf.abbreviation + '] ' + gf.finding_type + ' – ' + gf.findings.substring(0, 60) + '...';
                            capaSelect.appendChild(opt);
                        });
                    }

                    const globalCapaBtn = document.getElementById('btn-global-add-capa');
                    if (globalCapaBtn) {
                        if (data.global_findings && data.global_findings.length > 0) {
                            globalCapaBtn.disabled = false;
                            globalCapaBtn.style.opacity = '1';
                            globalCapaBtn.style.cursor = 'pointer';
                        } else {
                            globalCapaBtn.disabled = true;
                            globalCapaBtn.style.opacity = '0.5';
                            globalCapaBtn.style.cursor = 'not-allowed';
                        }
                    }

                    pCloseContainer.classList.remove('d-none');
                    pCloseContainer.classList.add('d-flex');
                    window.showNotification('AI compliance audit completed successfully.', 'success');
                } else {
                    pBar.classList.remove('bg-primary');
                    pBar.classList.add('bg-danger');
                    pStatus.innerText = 'Audit failed: ' + (data.message || 'Unknown error.');
                    pCloseContainer.classList.remove('d-none');
                    pCloseContainer.classList.add('d-flex');
                    window.showNotification(data.message || 'An error occurred during AI audit.', 'danger');
                }
            })
            .catch(err => {
                clearInterval(progressInterval);
                pBar.classList.remove('bg-primary');
                pBar.classList.add('bg-danger');
                pStatus.innerText = 'Network error during compliance audit.';
                pCloseContainer.classList.remove('d-none');
                pCloseContainer.classList.add('d-flex');
                window.showNotification('A network error occurred. Please try again.', 'danger');
            });
        });
    }

    let isSyncingOverview = false;
    function syncCorrectiveActionsOverview() {
        if (isSyncingOverview) return;
        isSyncingOverview = true;

        const formData = new FormData();
        formData.append('action', 'fetch_overview_data');

        fetch('corrective_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            isSyncingOverview = false;
            if (!data || !data.success) return;

            const currentRole = <?= json_encode($role) ?>;
            if (Array.isArray(data.counts)) {
                data.counts.forEach(c => {
                    const row = document.getElementById('office-row-' + c.id);
                    if (row) {
                        const fBadge = row.querySelector('.badge-findings-count');
                        if (fBadge) {
                            const newFText = c.findings_count + ' Findings';
                            if (fBadge.innerText !== newFText) {
                                fBadge.innerText = newFText;
                            }
                            const fContainer = fBadge.parentElement;
                            if (fContainer) {
                                let fIndicator = fContainer.querySelector('.badge-findings-indicator');
                                const inProgressCount = parseInt(c.in_progress_findings_count) || 0;
                                if (currentRole === 'admin' && inProgressCount > 0) {
                                    if (!fIndicator) {
                                        fIndicator = document.createElement('span');
                                        fIndicator.className = 'badge-findings-indicator position-absolute top-0 start-100 translate-middle bg-warning border border-2 border-white rounded-circle shadow-sm';
                                        fIndicator.style.cssText = 'width: 14px; height: 14px; margin-top: 3px; margin-left: -5px; background-color: #f59e0b !important;';
                                        fContainer.appendChild(fIndicator);
                                    }
                                    fIndicator.setAttribute('title', inProgressCount + ' finding(s) in progress');
                                } else if (fIndicator) {
                                    fIndicator.remove();
                                }
                            }
                        }

                        const cBadge = row.querySelector('.badge-capas-count');
                        if (cBadge) {
                            const newCText = c.capas_count + ' CAPAs';
                            if (cBadge.innerText !== newCText) {
                                cBadge.innerText = newCText;
                            }
                            const cContainer = cBadge.parentElement;
                            if (cContainer) {
                                let cIndicator = cContainer.querySelector('.badge-capas-indicator');
                                const pendingCount = parseInt(c.pending_capas_count) || 0;
                                if ((currentRole === 'admin' || currentRole === 'auditor') && pendingCount > 0) {
                                    if (!cIndicator) {
                                        cIndicator = document.createElement('span');
                                        cIndicator.className = 'badge-capas-indicator position-absolute top-0 start-100 translate-middle bg-warning border border-2 border-white rounded-circle shadow-sm';
                                        cIndicator.style.cssText = 'width: 14px; height: 14px; margin-top: 3px; margin-left: -5px; background-color: #f59e0b !important;';
                                        cContainer.appendChild(cIndicator);
                                    }
                                    cIndicator.setAttribute('title', pendingCount + ' CAPA(s) pending approval');
                                } else if (cIndicator) {
                                    cIndicator.remove();
                                }
                            }
                        }

                        const delBtn = row.querySelector('.btn-delete-all-findings');
                        if (delBtn) {
                            if (parseInt(c.findings_count) > 0) {
                                delBtn.disabled = false;
                                delBtn.style.opacity = '1';
                                delBtn.style.cursor = 'pointer';
                            } else {
                                delBtn.disabled = true;
                                delBtn.style.opacity = '0.3';
                                delBtn.style.cursor = 'not-allowed';
                            }
                        }
                    }
                });
            }

            const capaSelect = document.getElementById('capa_finding');
            if (capaSelect && Array.isArray(data.global_findings)) {
                if (document.activeElement !== capaSelect) {
                    const currentVal = capaSelect.value;
                    const defaultOpt = capaSelect.querySelector('option[value=""]');
                    capaSelect.innerHTML = '';
                    if (defaultOpt) {
                        capaSelect.appendChild(defaultOpt);
                    } else {
                        const def = document.createElement('option');
                        def.value = '';
                        def.text = '-- Select a Finding to Address --';
                        capaSelect.appendChild(def);
                    }
                    data.global_findings.forEach(gf => {
                        const opt = document.createElement('option');
                        opt.value = gf.id;
                        opt.className = 'finding-opt-' + gf.id;
                        opt.text = '[' + gf.abbreviation + '] ' + gf.finding_type + ' – ' + gf.findings.substring(0, 60) + '...';
                        capaSelect.appendChild(opt);
                    });
                    if (currentVal && capaSelect.querySelector('option[value="' + currentVal + '"]')) {
                        capaSelect.value = currentVal;
                    }
                }

                const globalCapaBtn = document.getElementById('btn-global-add-capa');
                if (globalCapaBtn) {
                    if (data.global_findings.length > 0) {
                        globalCapaBtn.disabled = false;
                        globalCapaBtn.style.opacity = '1';
                        globalCapaBtn.style.cursor = 'pointer';
                    } else {
                        globalCapaBtn.disabled = true;
                        globalCapaBtn.style.opacity = '0.5';
                        globalCapaBtn.style.cursor = 'not-allowed';
                    }
                }
            }
        })
        .catch(() => {
            isSyncingOverview = false;
        });
    }

    setInterval(syncCorrectiveActionsOverview, 3000);
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            syncCorrectiveActionsOverview();
        }
    });
</script>
</body>
</html>
