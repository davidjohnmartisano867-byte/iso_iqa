<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once __DIR__ . '/../../config/db.php';

$office_id = (int)($_GET['office_id'] ?? 0);
if ($office_id <= 0) {
    header("Location: ../view/corrective_actions.php");
    exit;
}

$pdo = getDBConnection();
$role = $_SESSION['role'] ?? 'admin';

if ($role === 'auditor') {
    $aud_stmt = $pdo->prepare("SELECT id FROM auditors WHERE user_id = ?");
    $aud_stmt->execute([$_SESSION['user_id']]);
    $auditor_id = $aud_stmt->fetchColumn();
    $assigned_office_ids = [];
    if ($auditor_id) {
        $assign_stmt = $pdo->prepare("SELECT office_id FROM office_assignments WHERE auditor_id = ?");
        $assign_stmt->execute([$auditor_id]);
        $assigned_office_ids = $assign_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    if (!in_array($office_id, $assigned_office_ids)) {
        header("Location: ../view/corrective_actions.php");
        exit;
    }
} elseif ($role === 'auditee') {
    $stmt = $pdo->prepare("SELECT id FROM offices WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $auditee_office_id = $stmt->fetchColumn();
    if (!$auditee_office_id || $office_id !== (int)$auditee_office_id) {
        if ($auditee_office_id) {
            header("Location: findings_capa.php?office_id=" . $auditee_office_id);
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
    header("Location: ../view/corrective_actions.php");
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
                    
                    $deadline_time = strtotime($dl);
                    $today_time = strtotime(date('Y-m-d'));
                    if ($deadline_time < $today_time) {
                        $status_html = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; border-color: rgba(220, 53, 69, 0.2) !important;"><i class="fas fa-calendar-times me-1"></i>Overdue</span>';
                    } else {
                        $status_html = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(255, 193, 7, 0.1) !important; color: #ffc107 !important; border-color: rgba(255, 193, 7, 0.2) !important;"><i class="fas fa-calendar-alt me-1"></i>In Progress</span>';
                    }

                    $inserted[] = [
                        'id' => $finding_id,
                        'finding_type' => $type,
                        'findings' => $desc,
                        'deadline' => $dl,
                        'formatted_deadline' => date('M j, Y', $deadline_time),
                        'status_html' => $status_html,
                        'created_at' => date('M j, Y')
                    ];
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'inserted' => $inserted]);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    if ($_POST['action'] === 'edit_finding') {
        $finding_id = (int)($_POST['id'] ?? 0);
        $finding_type = trim($_POST['finding_type'] ?? '');
        $findings = trim($_POST['findings'] ?? '');
        $deadline = trim($_POST['deadline'] ?? '');

        if ($finding_id <= 0 || empty($finding_type) || empty($findings) || empty($deadline)) {
            echo json_encode(['success' => false, 'message' => 'All fields including deadline are required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE findings SET finding_type = ?, findings = ?, deadline = ? WHERE id = ? AND office_id = ?");
            $stmt->execute([$finding_type, $findings, $deadline, $finding_id, $office_id]);
            
            $deadline_time = strtotime($deadline);
            $today_time = strtotime(date('Y-m-d'));
            if ($deadline_time < $today_time) {
                $status_html = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; border-color: rgba(220, 53, 69, 0.2) !important;"><i class="fas fa-calendar-times me-1"></i>Overdue</span>';
            } else {
                $status_html = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(255, 193, 7, 0.1) !important; color: #ffc107 !important; border-color: rgba(255, 193, 7, 0.2) !important;"><i class="fas fa-calendar-alt me-1"></i>In Progress</span>';
            }

            echo json_encode([
                'success' => true,
                'deadline' => date('M j, Y', $deadline_time),
                'raw_deadline' => $deadline,
                'status_html' => $status_html
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'fetch_office_capas_data') {
        $stmt = $pdo->prepare("SELECT id, finding_type, findings, deadline, created_at FROM findings WHERE office_id = ? ORDER BY created_at DESC");
        $stmt->execute([$office_id]);
        $findings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $f_ids = array_column($findings_data, 'id');
        $all_capas = [];
        if (!empty($f_ids)) {
            $placeholders = implode(',', array_fill(0, count($f_ids), '?'));
            $capa_stmt = $pdo->prepare("SELECT id, finding_id, root_cause, corrective_actions, status, created_at FROM capas WHERE finding_id IN ($placeholders) ORDER BY created_at DESC");
            $capa_stmt->execute($f_ids);
            $capa_rows = $capa_stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($capa_rows as $c) {
                $all_capas[$c['finding_id']][] = [
                    'id' => (int)$c['id'],
                    'finding_id' => (int)$c['finding_id'],
                    'root_cause' => $c['root_cause'] ?? '',
                    'corrective_actions' => $c['corrective_actions'] ?? '',
                    'status' => $c['status'] ?? 'Pending',
                    'created_at' => $c['created_at']
                ];
            }
        }

        $formatted_findings = [];
        $total_capas = 0;
        foreach ($findings_data as $f) {
            $fid = (int)$f['id'];
            $capas_for_f = $all_capas[$fid] ?? [];
            $total_capas += count($capas_for_f);
            $formatted_findings[] = [
                'id' => $fid,
                'finding_type' => $f['finding_type'],
                'findings' => $f['findings'],
                'deadline' => $f['deadline'],
                'formatted_deadline' => date('M j, Y', strtotime($f['deadline'])),
                'created_at' => date('M j, Y', strtotime($f['created_at'])),
                'capas' => $capas_for_f
            ];
        }

        echo json_encode([
            'success' => true,
            'total_capas' => $total_capas,
            'findings' => $formatted_findings
        ]);
        exit;
    }

    if ($_POST['action'] === 'delete_finding') {
        $finding_id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM findings WHERE id = ? AND office_id = ?");
            $stmt->execute([$finding_id, $office_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'add_capa') {
        $finding_id = (int)($_POST['finding_id'] ?? 0);
        $root_cause = trim($_POST['root_cause'] ?? '');
        $corrective_actions = trim($_POST['corrective_actions'] ?? '');

        if ($finding_id <= 0 || empty($corrective_actions)) {
            echo json_encode(['success' => false, 'message' => 'Corrective actions are required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO capas (finding_id, root_cause, corrective_actions, deadline) VALUES (?, ?, ?, NULL)");
            $stmt->execute([$finding_id, $root_cause, $corrective_actions]);
            $capa_id = $pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'id' => $capa_id,
                'root_cause' => $root_cause,
                'corrective_actions' => $corrective_actions
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
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

    if ($_POST['action'] === 'edit_capa') {
        if ($role === 'auditor') {
            echo json_encode(['success' => false, 'message' => 'Auditors are not authorized to edit CAPAs.']);
            exit;
        }
        $capa_id = (int)($_POST['id'] ?? 0);
        $root_cause = trim($_POST['root_cause'] ?? '');
        $corrective_actions = trim($_POST['corrective_actions'] ?? '');

        if ($capa_id <= 0 || empty($corrective_actions)) {
            echo json_encode(['success' => false, 'message' => 'Corrective actions are required.']);
            exit;
        }

        try {
            $check_stmt = $pdo->prepare("SELECT status FROM capas WHERE id = ?");
            $check_stmt->execute([$capa_id]);
            $current_status = $check_stmt->fetchColumn() ?: 'Pending';

            $new_status = $current_status;
            if ($current_status === 'Rejected') {
                $new_status = 'Pending';
                $stmt = $pdo->prepare("UPDATE capas SET root_cause = ?, corrective_actions = ?, status = 'Pending' WHERE id = ?");
                $stmt->execute([$root_cause, $corrective_actions, $capa_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE capas SET root_cause = ?, corrective_actions = ? WHERE id = ?");
                $stmt->execute([$root_cause, $corrective_actions, $capa_id]);
            }

            echo json_encode([
                'success' => true,
                'root_cause' => $root_cause,
                'corrective_actions' => $corrective_actions,
                'status' => $new_status,
                'was_rejected' => ($current_status === 'Rejected')
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'delete_capa') {
        if ($role === 'auditor') {
            echo json_encode(['success' => false, 'message' => 'Auditors are not authorized to delete CAPAs.']);
            exit;
        }
        $capa_id = (int)($_POST['id'] ?? 0);
        try {
            $f_stmt = $pdo->prepare("SELECT finding_id FROM capas WHERE id = ?");
            $f_stmt->execute([$capa_id]);
            $finding_id = (int)$f_stmt->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM capas WHERE id = ?");
            $stmt->execute([$capa_id]);
            echo json_encode(['success' => true, 'finding_id' => $finding_id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'approve_capa') {
        if (!in_array($role, ['admin', 'auditor'])) {
            echo json_encode(['success' => false, 'message' => 'Auditors and Admins only are authorized to approve CAPAs.']);
            exit;
        }
        $capa_id = (int)($_POST['id'] ?? 0);
        if ($capa_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid CAPA ID.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE capas SET status = 'Approved' WHERE id = ?");
            $stmt->execute([$capa_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'reject_capa') {
        if (!in_array($role, ['admin', 'auditor'])) {
            echo json_encode(['success' => false, 'message' => 'Auditors and Admins only are authorized to reject CAPAs.']);
            exit;
        }
        $capa_id = (int)($_POST['id'] ?? 0);
        if ($capa_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid CAPA ID.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE capas SET status = 'Rejected' WHERE id = ?");
            $stmt->execute([$capa_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

$username = $_SESSION['username'] ?? 'User';
$role     = $_SESSION['role'] ?? 'admin';
$active_page = 'corrective';
$base_url    = '../view/';

$stmt = $pdo->prepare("
    SELECT 
        f.id, 
        f.finding_type, 
        f.findings, 
        f.deadline, 
        f.document_id,
        f.created_at,
        d.filename AS doc_filename,
        d.clause AS doc_clause,
        c.title AS clause_title
    FROM findings f
    LEFT JOIN documents d ON f.document_id = d.id
    LEFT JOIN clauses c ON d.clause = c.clause_number
    WHERE f.office_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$office_id]);
$findings_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_capas = 0;
foreach ($findings_list as &$f) {
    $capa_stmt = $pdo->prepare("SELECT id, root_cause, corrective_actions, deadline, status, created_at FROM capas WHERE finding_id = ? ORDER BY created_at DESC");
    $capa_stmt->execute([$f['id']]);
    $f['capas'] = $capa_stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_capas += count($f['capas']);
}
unset($f);

$unassigned_findings = array_values(array_filter($findings_list, function($item) {
    return empty($item['capas']);
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Office CAPAs – ISO-QA AI System</title>
    <link class="main-css" rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
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
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-1">
                    <i class="fas fa-clipboard-list text-primary me-2"></i>Findings & CAPAs of <?= htmlspecialchars($office['abbreviation']) ?>
                </h4>
                <p class="text-muted mb-0 small"><?= htmlspecialchars($office['name']) ?> &bull; OIC: <?= htmlspecialchars($office['officer_in_charge']) ?></p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if ($role !== 'auditee'): ?>
                <button class="btn btn-sm btn-custom rounded-3 px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addFindingModal">
                    <i class="fas fa-plus"></i> Add Findings
                </button>
                <?php endif; ?>
                <?php if ($role !== 'auditor'): ?>
                <button class="btn btn-sm btn-primary rounded-3 px-3 d-flex align-items-center gap-2 fw-semibold" id="btn-sub-add-capa" data-bs-toggle="modal" data-bs-target="#addCapaModal" <?= empty($unassigned_findings) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                    <i class="fas fa-tools"></i> Add CAPA
                </button>
                <?php endif; ?>
                <?php if ($role !== 'auditee'): ?>
                <a href="../view/corrective_actions.php" class="btn btn-sm btn-secondary rounded-3 px-3 d-flex align-items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div id="findings-list-wrapper">
            <?php if (empty($findings_list)): ?>
                <div class="card p-5 border shadow-sm text-center" id="empty-findings-placeholder">
                    <div class="text-muted mb-3"><i class="fas fa-clipboard-list fa-3x"></i></div>
                    <h5 class="fw-bold text-dark mb-2">No Registered Findings Yet</h5>
                    <p class="text-secondary small mb-4">You must first register the office audit findings and nonconformities before applying corrective actions (CAPAs).</p>
                    <?php if ($role !== 'auditee'): ?>
                    <div>
                        <button class="btn btn-custom py-2 px-4 rounded-3 text-white fw-semibold" data-bs-toggle="modal" data-bs-target="#addFindingModal">
                            <i class="fas fa-plus me-1"></i> Add First Finding
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($findings_list as $f): 
                    $badge_class = 'bg-success';
                    if ($f['finding_type'] === 'Major Nonconformity') {
                        $badge_class = 'bg-danger';
                    } elseif ($f['finding_type'] === 'Minor Nonconformity') {
                        $badge_class = 'bg-warning text-dark';
                    }
                ?>
                    <div class="card p-4 border shadow-sm mb-4 bg-white rounded-4 position-relative finding-card-item" id="finding-card-<?= $f['id'] ?>" data-finding-type="<?= htmlspecialchars($f['finding_type']) ?>" data-deadline="<?= htmlspecialchars($f['deadline']) ?>">
                        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge <?= $badge_class ?> rounded-pill px-3 py-1.5 fw-bold finding-badge-type" style="font-size:0.8rem;"><?= htmlspecialchars($f['finding_type']) ?></span>
                                <span class="text-muted small" style="font-size: 0.8rem;"><i class="far fa-clock me-1"></i>Logged: <?= date('M j, Y', strtotime($f['created_at'])) ?></span>
                                <span class="text-muted small ms-2" style="font-size: 0.8rem;" id="finding-deadline-container-<?= $f['id'] ?>"><i class="far fa-calendar-alt me-1"></i>Deadline: <span class="fw-semibold text-dark finding-deadline-val"><?= date('M j, Y', strtotime($f['deadline'])) ?></span></span>
                                <?php
                                    if ($f['finding_type'] === 'Conformity') {
                                        $f_status_badge = '<span class="badge bg-success-subtle text-success border border-success-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(25, 135, 84, 0.1) !important; color: #198754 !important; border-color: rgba(25, 135, 84, 0.2) !important;"><i class="fas fa-check-circle me-1"></i>Conformed</span>';
                                    } elseif (!empty($f['capas'])) {
                                        $has_approved = false;
                                        $has_pending = false;
                                        $has_rejected = false;
                                        foreach ($f['capas'] as $cp) {
                                            if ($cp['status'] === 'Approved') $has_approved = true;
                                            elseif ($cp['status'] === 'Pending') $has_pending = true;
                                            elseif ($cp['status'] === 'Rejected') $has_rejected = true;
                                        }

                                        if ($has_approved && !$has_pending && !$has_rejected) {
                                            $f_status_badge = '<span class="badge bg-success-subtle text-success border border-success-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(25, 135, 84, 0.1) !important; color: #198754 !important; border-color: rgba(25, 135, 84, 0.2) !important;"><i class="fas fa-check-circle me-1"></i>Approved</span>';
                                        } elseif ($has_rejected && !$has_pending && !$has_approved) {
                                            $f_status_badge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; border-color: rgba(220, 53, 69, 0.2) !important;"><i class="fas fa-times-circle me-1"></i>CAPA Rejected</span>';
                                        } else {
                                            $f_status_badge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(37, 99, 235, 0.1) !important; color: #2563eb !important; border-color: rgba(37, 99, 235, 0.2) !important;"><i class="fas fa-check-double me-1"></i>CAPA Submitted</span>';
                                        }
                                    } else {
                                        $f_deadline_time = strtotime($f['deadline']);
                                        $today_time = strtotime(date('Y-m-d'));
                                        if ($f_deadline_time < $today_time) {
                                            $f_status_badge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; border-color: rgba(220, 53, 69, 0.2) !important;"><i class="fas fa-calendar-times me-1"></i>Overdue</span>';
                                        } else {
                                            $f_status_badge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(255, 193, 7, 0.1) !important; color: #ffc107 !important; border-color: rgba(255, 193, 7, 0.2) !important;"><i class="fas fa-calendar-alt me-1"></i>In Progress</span>';
                                        }
                                    }
                                ?>
                                <span class="finding-status-container-<?= $f['id'] ?>"><?= $f_status_badge ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($role !== 'auditee'): ?>
                                <button class="btn btn-sm btn-link text-warning p-0 mx-2 btn-edit-finding" data-id="<?= $f['id'] ?>" data-type="<?= htmlspecialchars($f['finding_type']) ?>" data-findings="<?= htmlspecialchars($f['findings']) ?>" data-deadline="<?= $f['deadline'] ?>" title="Edit Finding">
                                    <i class="fas fa-edit fa-lg"></i>
                                </button>
                                <button class="btn btn-sm btn-link text-danger p-0 btn-delete-finding" data-id="<?= $f['id'] ?>" title="Delete Finding">
                                    <i class="fas fa-trash-alt fa-lg"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                            <?php if (!empty($f['doc_filename'])): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 rounded-2 fw-semibold d-inline-flex align-items-center" style="font-size: .8rem; background: rgba(37,99,235,0.08) !important; color: #1d4ed8 !important; border: 1px solid rgba(37,99,235,0.25) !important;">
                                    <i class="fas fa-file-alt me-2 text-primary"></i>
                                    <span class="fw-normal text-muted me-1">File:</span>
                                    <span class="text-truncate fw-bold" style="max-width: 320px;" title="<?= htmlspecialchars($f['doc_filename']) ?>"><?= htmlspecialchars($f['doc_filename']) ?></span>
                                </span>
                                <?php if (!empty($f['doc_clause'])): ?>
                                <span class="badge bg-secondary-subtle text-dark border px-2.5 py-1.5 rounded-2 fw-semibold d-inline-flex align-items-center" style="font-size: 0.8rem; background: #f8fafc !important; border: 1px solid #cbd5e1 !important;">
                                    <i class="fas fa-tag me-2 text-primary"></i>
                                    <span class="fw-normal text-muted me-1">ISO Clause:</span>
                                    <span class="fw-bold text-dark">Clause <?= htmlspecialchars($f['doc_clause']) ?><?= !empty($f['clause_title']) ? ' – ' . htmlspecialchars($f['clause_title']) : '' ?></span>
                                </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-light text-secondary border px-2.5 py-1.5 rounded-2 fw-medium d-inline-flex align-items-center" style="font-size: 0.8rem;">
                                    <i class="fas fa-file-circle-question me-1.5 text-muted"></i>
                                    <span>No specific file linked (General Finding)</span>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-exclamation-circle text-danger me-1"></i>Finding / Gap Description</h6>
                            <p class="text-secondary small bg-light p-3 rounded-3 border-start border-4 border-danger lh-base finding-description-text mb-0"><?= nl2br(htmlspecialchars($f['findings'])) ?></p>
                        </div>

                        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-tools text-success me-1"></i>Corrective & Preventive Actions (CAPAs)</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 border rounded-3 bg-light overflow-hidden">
                                <thead>
                                    <tr class="table-secondary text-center" style="font-size: 0.85rem;">
                                        <th class="ps-3 py-2" style="width: 30%;">Root Cause</th>
                                        <th class="py-2" style="width: 35%;">Corrective Actions</th>
                                        <th class="py-2" style="width: 15%;">Status</th>
                                        <th class="pe-3 py-2" style="width: 20%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="capas-table-body-<?= $f['id'] ?>">
                                    <?php if (empty($f['capas'])): ?>
                                        <tr class="empty-capas-row-placeholder">
                                            <td colspan="4" class="text-center text-muted py-3 small"><i class="fas fa-info-circle me-1"></i>No corrective actions registered for this finding yet. Click "Add CAPA" to record one.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($f['capas'] as $c): 
                                            $capa_status = $c['status'] ?? 'Pending';
                                            $is_approved = ($capa_status === 'Approved');
                                            $is_rejected = ($capa_status === 'Rejected');
                                            $is_pending  = ($capa_status === 'Pending');
                                        ?>
                                            <tr style="font-size: 0.85rem;" id="capa-row-<?= $c['id'] ?>">
                                                <td class="ps-3 py-2.5 fw-medium text-secondary capa-root-text" style="max-width: 250px; white-space: normal;">
                                                    <?= !empty($c['root_cause']) ? nl2br(htmlspecialchars(mb_strlen($c['root_cause']) > 60 ? mb_substr($c['root_cause'], 0, 60) . '...' : $c['root_cause'])) : '<span class="text-muted fst-italic">Not specified</span>' ?>
                                                </td>
                                                <td class="py-2.5 fw-medium text-dark capa-desc-text" style="max-width: 350px; white-space: normal;">
                                                    <?php
                                                        $raw_capa = $c['corrective_actions'];
                                                        $truncated_capa = mb_strlen($raw_capa) > 80 ? mb_substr($raw_capa, 0, 80) . '...' : $raw_capa;
                                                        echo nl2br(htmlspecialchars($truncated_capa));
                                                    ?>
                                                </td>
                                                <td class="py-2.5 text-center capa-status-cell" data-status="<?= htmlspecialchars($capa_status) ?>">
                                                    <?php if ($is_approved): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem; background-color: rgba(25, 135, 84, 0.1) !important; color: #198754 !important; border-color: rgba(25, 135, 84, 0.2) !important;"><i class="fas fa-check-circle me-1"></i>Approved</span>
                                                    <?php elseif ($is_rejected): ?>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem; background-color: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; border-color: rgba(220, 53, 69, 0.2) !important;"><i class="fas fa-times-circle me-1"></i>Rejected</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem; background-color: rgba(255, 193, 7, 0.1) !important; color: #856404 !important; border-color: rgba(255, 193, 7, 0.2) !important;"><i class="fas fa-clock me-1"></i>Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="pe-3 py-2.5 text-center capa-actions-cell">
                                                    <?php if (in_array($role, ['admin', 'auditor']) && $is_pending): ?>
                                                    <button class="btn btn-sm btn-link text-success p-0 me-2 btn-approve-capa" data-id="<?= $c['id'] ?>" title="Approve CAPA">
                                                        <i class="fas fa-check-circle fa-lg"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-link text-danger p-0 me-2 btn-reject-capa" data-id="<?= $c['id'] ?>" title="Reject CAPA">
                                                        <i class="fas fa-times-circle fa-lg"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-link text-primary p-0 <?= $role !== 'auditor' ? 'me-2' : '' ?> btn-view-capa" data-id="<?= $c['id'] ?>" data-root-cause="<?= htmlspecialchars($c['root_cause'] ?? '') ?>" data-actions="<?= htmlspecialchars($c['corrective_actions']) ?>" title="View Full CAPA">
                                                        <i class="fas fa-eye fa-lg" ></i>
                                                    </button>
                                                    <?php if ($role !== 'auditor'): ?>
                                                    <button class="btn btn-sm btn-link text-warning p-0 me-2 btn-edit-capa" data-id="<?= $c['id'] ?>" data-finding-id="<?= $f['id'] ?>" data-root-cause="<?= htmlspecialchars($c['root_cause'] ?? '') ?>" data-actions="<?= htmlspecialchars($c['corrective_actions']) ?>" title="Edit CAPA">
                                                        <i class="fas fa-edit fa-lg"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-link text-danger p-0 btn-delete-capa" data-id="<?= $c['id'] ?>" title="Delete CAPA">
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
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../components/add_finding_modal.php'; ?>
<?php require_once __DIR__ . '/../../components/edit_finding_modal.php'; ?>

<?php
$findings_dropdown = $unassigned_findings;
$show_ai_suggest = true;
require_once __DIR__ . '/../../components/add_capa_modal.php';
require_once __DIR__ . '/../../components/edit_capa_modal.php';
require_once __DIR__ . '/../../components/view_capa_modal.php';
require_once __DIR__ . '/../../components/delete_confirmation_modal.php';
?>

<?php if (in_array($role, ['admin', 'auditor'])): ?>
<?php require_once __DIR__ . '/../../components/approve_confirmation_modal.php'; ?>
<?php require_once __DIR__ . '/../../components/reject_confirmation_modal.php'; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/../../components/notification.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let activeFindingId = 0;
    let activeCapaId = 0;
    let activeDeleteType = null;
    let activeApproveCapaId = 0;
    let activeApproveRow = null;
    let activeRejectCapaId = 0;
    let activeRejectRow = null;
    const deleteConfirmationModalEl = document.getElementById('deleteConfirmationModal');
    const deleteConfirmationModal = deleteConfirmationModalEl ? new bootstrap.Modal(deleteConfirmationModalEl) : null;
    const approveModalEl = document.getElementById('approveConfirmationModal');
    const approveConfirmationModal = approveModalEl ? new bootstrap.Modal(approveModalEl) : null;
    const rejectModalEl = document.getElementById('rejectConfirmationModal');
    const rejectConfirmationModal = rejectModalEl ? new bootstrap.Modal(rejectModalEl) : null;
    const addFindingModal = new bootstrap.Modal(document.getElementById('addFindingModal'));
    const editFindingModal = new bootstrap.Modal(document.getElementById('editFindingModal'));
    const addCapaModal = new bootstrap.Modal(document.getElementById('addCapaModal'));
    const editCapaModal = new bootstrap.Modal(document.getElementById('editCapaModal'));

    function bindFindingEvents(card) {
        const triggerBtn = card.querySelector('.btn-trigger-add-capa');
        if (triggerBtn) {
            triggerBtn.addEventListener('click', function() {
                const fid = this.getAttribute('data-finding-id');
                const select = document.getElementById('capa_finding');
                if (select) {
                    select.value = fid;
                }
                document.getElementById('add-capa-error').classList.add('d-none');
                addCapaModal.show();
                if (typeof runAiCapaSuggestion === 'function' && fid) {
                    runAiCapaSuggestion(fid);
                }
            });
        }

        const editBtn = card.querySelector('.btn-edit-finding');
        if (editBtn) {
            editBtn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const type = this.getAttribute('data-type');
                const findings = this.getAttribute('data-findings');
                const deadline = this.getAttribute('data-deadline');

                document.getElementById('edit-finding-id').value = id;
                document.getElementById('edit-finding-type').value = type;
                document.getElementById('edit-finding-desc').value = findings;
                document.getElementById('edit-finding-deadline').value = deadline;
                document.getElementById('edit-finding-error').classList.add('d-none');
                
                editFindingModal.show();
            });
        }

        const deleteBtn = card.querySelector('.btn-delete-finding');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                activeDeleteType = 'finding';
                activeFindingId = this.getAttribute('data-id');
                const titleText = document.getElementById('delete-modal-title-text');
                const heading = document.getElementById('delete-modal-heading');
                const message = document.getElementById('delete-modal-message');
                const btnText = document.getElementById('delete-modal-btn-text');

                if (titleText) titleText.textContent = 'Confirm Deletion';
                if (heading) heading.textContent = 'Delete Finding?';
                if (message) message.innerHTML = 'Are you sure you want to permanently delete this audit finding and all of its attached corrective actions (CAPAs)? This action cannot be undone.';
                if (btnText) btnText.textContent = 'Delete';

                if (deleteConfirmationModal) deleteConfirmationModal.show();
            });
        }

        const aiBtn = card.querySelector('.btn-finding-ai-capa');
        if (aiBtn) {
            aiBtn.addEventListener('click', function() {
                const fid = this.getAttribute('data-id');
                const select = document.getElementById('capa_finding');
                if (select) {
                    select.value = fid;
                }
                const modalEl = document.getElementById('addCapaModal');
                const modalInst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modalInst.show();
                const trigger = document.getElementById('btn-ai-suggest-capa');
                if (trigger) {
                    trigger.click();
                }
            });
        }
    }

    function bindCapaEvents(row) {
    }

    document.addEventListener('click', function(e) {
        const approveBtn = e.target.closest('.btn-approve-capa');
        if (approveBtn) {
            activeApproveCapaId = approveBtn.getAttribute('data-id');
            activeApproveRow = approveBtn.closest('tr');
            if (approveConfirmationModal) {
                approveConfirmationModal.show();
            }
            return;
        }

        const rejectBtn = e.target.closest('.btn-reject-capa');
        if (rejectBtn) {
            activeRejectCapaId = rejectBtn.getAttribute('data-id');
            activeRejectRow = rejectBtn.closest('tr');
            if (rejectConfirmationModal) {
                rejectConfirmationModal.show();
            }
            return;
        }

        const viewBtn = e.target.closest('.btn-view-capa');
        if (viewBtn) {
            const row = viewBtn.closest('tr');
            const capaId = viewBtn.getAttribute('data-id');
            const actions = viewBtn.getAttribute('data-actions') || '';
            const rootCause = viewBtn.getAttribute('data-root-cause') || 'Not specified';
            document.getElementById('view-capa-root-cause').innerText = rootCause;
            document.getElementById('view-capa-content').innerText = actions;

            const viewCapaModalEl = document.getElementById('viewCapaModal');
            const viewCapaModalInstance = bootstrap.Modal.getInstance(viewCapaModalEl) || new bootstrap.Modal(viewCapaModalEl);

            const hasApproveBtn = row ? !!row.querySelector('.btn-approve-capa') : false;
            const modalApproveBtn = document.getElementById('view-capa-approve-btn');
            const modalRejectBtn = document.getElementById('view-capa-reject-btn');

            if (modalApproveBtn && modalRejectBtn) {
                if (hasApproveBtn) {
                    modalApproveBtn.classList.remove('d-none');
                    modalRejectBtn.classList.remove('d-none');
                    modalApproveBtn.onclick = function() {
                        viewCapaModalInstance.hide();
                        activeApproveCapaId = capaId;
                        activeApproveRow = row;
                        if (approveConfirmationModal) approveConfirmationModal.show();
                    };
                    modalRejectBtn.onclick = function() {
                        viewCapaModalInstance.hide();
                        activeRejectCapaId = capaId;
                        activeRejectRow = row;
                        if (rejectConfirmationModal) rejectConfirmationModal.show();
                    };
                } else {
                    modalApproveBtn.classList.add('d-none');
                    modalRejectBtn.classList.add('d-none');
                }
            }

            viewCapaModalInstance.show();
            return;
        }

        const editBtn = e.target.closest('.btn-edit-capa');
        if (editBtn) {
            const row = editBtn.closest('tr');
            const id = editBtn.getAttribute('data-id');
            const actions = editBtn.getAttribute('data-actions') || '';
            const rootCause = editBtn.getAttribute('data-root-cause') || '';
            const findingId = editBtn.getAttribute('data-finding-id') || row?.closest('.finding-card-item')?.id?.replace('finding-card-', '') || '';

            document.getElementById('edit-capa-id').value = id;
            const findingInput = document.getElementById('edit-capa-finding-id');
            if (findingInput) {
                findingInput.value = findingId;
            }
            document.getElementById('edit-root_cause').value = rootCause;
            document.getElementById('edit-corrective_actions').value = actions;
            document.getElementById('edit-capa-error').classList.add('d-none');

            document.getElementById('edit_root_cause_suggestions_wrapper')?.classList.add('d-none');
            document.getElementById('btn-toggle-edit-root-suggestions')?.classList.add('d-none');
            document.getElementById('edit_capa_suggestions_wrapper')?.classList.add('d-none');
            document.getElementById('btn-toggle-edit-capa-suggestions')?.classList.add('d-none');
            
            editCapaModal.show();
            return;
        }

        const deleteBtn = e.target.closest('.btn-delete-capa');
        if (deleteBtn) {
            activeDeleteType = 'capa';
            activeCapaId = deleteBtn.getAttribute('data-id');
            const titleText = document.getElementById('delete-modal-title-text');
            const heading = document.getElementById('delete-modal-heading');
            const message = document.getElementById('delete-modal-message');
            const btnText = document.getElementById('delete-modal-btn-text');

            if (titleText) titleText.textContent = 'Confirm Deletion';
            if (heading) heading.textContent = 'Delete CAPA?';
            if (message) message.innerHTML = 'Are you sure you want to permanently delete this corrective action? This action cannot be undone.';
            if (btnText) btnText.textContent = 'Delete';

            if (deleteConfirmationModal) deleteConfirmationModal.show();
            return;
        }
    });

    function updateFindingStatusBadge(findingId) {
        if (!findingId) return;
        const findingCard = document.getElementById('finding-card-' + findingId);
        if (!findingCard) return;

        const statusContainer = findingCard.querySelector('.finding-status-container-' + findingId) || document.querySelector('.finding-status-container-' + findingId);
        if (!statusContainer) return;

        const tbody = document.getElementById('capas-table-body-' + findingId);
        const rows = tbody ? Array.from(tbody.querySelectorAll('tr:not(.empty-capas-row-placeholder)')) : [];
        const hasCapas = rows.length > 0;

        const fType = findingCard.getAttribute('data-finding-type') || findingCard.querySelector('.finding-badge-type')?.innerText.trim() || '';
        const deadlineVal = findingCard.getAttribute('data-deadline') || findingCard.querySelector('.finding-deadline-val')?.innerText.trim() || '';

        if (fType === 'Conformity') {
            statusContainer.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(25, 135, 84, 0.1) !important; color: #198754 !important; border-color: rgba(25, 135, 84, 0.2) !important;"><i class="fas fa-check-circle me-1"></i>Conformed</span>';
        } else if (hasCapas) {
            let allRejected = true;
            rows.forEach(r => {
                const cell = r.querySelector('.capa-status-cell');
                const st = (cell?.getAttribute('data-status') || cell?.innerText.trim() || '').toLowerCase();
                if (!st.includes('rejected')) {
                    allRejected = false;
                }
            });

            if (allRejected) {
                statusContainer.innerHTML = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; border-color: rgba(220, 53, 69, 0.2) !important;"><i class="fas fa-times-circle me-1"></i>CAPA Rejected</span>';
            } else {
                statusContainer.innerHTML = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(37, 99, 235, 0.1) !important; color: #2563eb !important; border-color: rgba(37, 99, 235, 0.2) !important;"><i class="fas fa-check-double me-1"></i>CAPA Submitted</span>';
            }
        } else {
            const deadlineDate = new Date(deadlineVal);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (!isNaN(deadlineDate.getTime()) && deadlineDate < today) {
                statusContainer.innerHTML = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; border-color: rgba(220, 53, 69, 0.2) !important;"><i class="fas fa-calendar-times me-1"></i>Overdue</span>';
            } else {
                statusContainer.innerHTML = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-bold rounded-pill px-2.5 py-1 finding-status-badge" style="font-size:0.75rem; background-color: rgba(255, 193, 7, 0.1) !important; color: #ffc107 !important; border-color: rgba(255, 193, 7, 0.2) !important;"><i class="fas fa-calendar-alt me-1"></i>In Progress</span>';
            }
        }
    }

    document.querySelectorAll('.finding-card-item').forEach(card => bindFindingEvents(card));
    document.querySelectorAll('.finding-card-item table tbody tr').forEach(row => {
        if (!row.classList.contains('empty-capas-row-placeholder')) {
            bindCapaEvents(row);
        }
    });

    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (activeDeleteType === 'finding' && activeFindingId) {
                const originalHtml = confirmDeleteBtn.innerHTML;
                confirmDeleteBtn.disabled = true;
                confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Deleting...';

                const formData = new FormData();
                formData.append('action', 'delete_finding');
                formData.append('id', activeFindingId);

                fetch('findings_capa.php?office_id=<?= $office_id ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.innerHTML = originalHtml;
                    if (deleteConfirmationModal) deleteConfirmationModal.hide();
                    if (data.success) {
                        const card = document.getElementById('finding-card-' + activeFindingId);
                        if (card) {
                            const rowCount = card.querySelectorAll('table tbody tr:not(.empty-capas-row-placeholder)').length;
                            const headerBadge = document.querySelector('.badge.bg-secondary');
                            if (headerBadge) {
                                const currentCapaVal = parseInt(headerBadge.innerText) || 0;
                                headerBadge.innerText = Math.max(0, currentCapaVal - rowCount) + ' CAPAs';
                            }

                            card.remove();
                        }

                        const capaSelect = document.getElementById('capa_finding');
                        if (capaSelect) {
                            Array.from(capaSelect.options).forEach(opt => {
                                if (opt.value == activeFindingId) opt.remove();
                            });
                        }

                        const remainingCards = document.querySelectorAll('.finding-card-item');
                        if (remainingCards.length === 0) {
                            document.getElementById('btn-sub-add-capa').disabled = true;
                            document.getElementById('btn-sub-add-capa').style.opacity = '0.5';
                            document.getElementById('btn-sub-add-capa').style.cursor = 'not-allowed';

                            const wrapper = document.getElementById('findings-list-wrapper');
                            wrapper.innerHTML = `
                                <div class="card p-5 border shadow-sm text-center" id="empty-findings-placeholder">
                                    <div class="text-muted mb-3"><i class="fas fa-clipboard-list fa-3x"></i></div>
                                    <h5 class="fw-bold text-dark mb-2">No Registered Findings Yet</h5>
                                    <p class="text-secondary small mb-4">You must first register the office audit findings and nonconformities before applying corrective actions (CAPAs).</p>
                                    <div>
                                        <button class="btn btn-custom py-2 px-4 rounded-3 text-white fw-semibold" data-bs-toggle="modal" data-bs-target="#addFindingModal">
                                            <i class="fas fa-plus me-1"></i> Add First Finding
                                        </button>
                                    </div>
                                </div>
                            `;
                        }

                        window.showNotification('Finding deleted successfully.', 'success');
                    } else {
                        window.showNotification(data.message || 'Error deleting finding.', 'danger');
                    }
                })
                .catch(err => {
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.innerHTML = originalHtml;
                    if (deleteConfirmationModal) deleteConfirmationModal.hide();
                    window.showNotification('Network error. Please try again.', 'danger');
                });
            } else if (activeDeleteType === 'capa' && activeCapaId) {
                const originalHtml = confirmDeleteBtn.innerHTML;
                confirmDeleteBtn.disabled = true;
                confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Deleting...';

                const formData = new FormData();
                formData.append('action', 'delete_capa');
                formData.append('id', activeCapaId);

                fetch('findings_capa.php?office_id=<?= $office_id ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.innerHTML = originalHtml;
                    if (deleteConfirmationModal) deleteConfirmationModal.hide();
                    if (data.success) {
                        const row = document.getElementById('capa-row-' + activeCapaId);
                        const findingCard = row ? row.closest('.finding-card-item') : null;
                        const fId = (findingCard ? findingCard.id.replace('finding-card-', '') : null) || data.finding_id;

                        if (row) {
                            const tbody = row.closest('tbody');
                            row.remove();

                            if (tbody && tbody.querySelectorAll('tr:not(.empty-capas-row-placeholder)').length === 0) {
                                tbody.innerHTML = `
                                    <tr class="empty-capas-row-placeholder">
                                        <td colspan="4" class="text-center text-muted py-3 small"><i class="fas fa-info-circle me-1"></i>No corrective actions registered for this finding yet. Click "Add CAPA" to record one.</td>
                                    </tr>
                                `;

                                const capaSelect = document.getElementById('capa_finding');
                                if (capaSelect && findingCard) {
                                    const fType = findingCard.querySelector('.finding-badge-type')?.innerText || 'Finding';
                                    const fDesc = findingCard.querySelector('.finding-description-text')?.innerText || '';
                                    const exists = Array.from(capaSelect.options).some(opt => opt.value == fId);
                                    if (!exists) {
                                        const opt = document.createElement('option');
                                        opt.value = fId;
                                        opt.className = 'finding-opt-' + fId;
                                        opt.text = fType + ' – ' + fDesc.substring(0, 60) + '...';
                                        capaSelect.appendChild(opt);
                                    }
                                    const addCapaBtn = document.getElementById('btn-sub-add-capa');
                                    if (addCapaBtn) {
                                        addCapaBtn.disabled = false;
                                        addCapaBtn.style.opacity = '1';
                                        addCapaBtn.style.cursor = 'pointer';
                                    }
                                }
                            }
                        }

                        if (fId) {
                            updateFindingStatusBadge(fId);
                        }
                        if (window.refreshSidebarBadges) {
                            window.refreshSidebarBadges();
                        }

                        const headerBadge = document.querySelector('.badge.bg-secondary');
                        if (headerBadge) {
                            const currentVal = parseInt(headerBadge.innerText) || 0;
                            headerBadge.innerText = Math.max(0, currentVal - 1) + ' CAPAs';
                        }

                        window.showNotification('Corrective action (CAPA) deleted successfully.', 'success');
                    } else {
                        window.showNotification(data.message || 'Error deleting CAPA.', 'danger');
                    }
                })
                .catch(err => {
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.innerHTML = originalHtml;
                    if (deleteConfirmationModal) deleteConfirmationModal.hide();
                    window.showNotification('Network error. Please try again.', 'danger');
                });
            }
        });
    }

    const confirmApproveCapaBtn = document.getElementById('confirm-approve-capa-btn');
    if (confirmApproveCapaBtn) {
        confirmApproveCapaBtn.addEventListener('click', function() {
            if (!activeApproveCapaId) return;

            const originalHtml = confirmApproveCapaBtn.innerHTML;
            confirmApproveCapaBtn.disabled = true;
            confirmApproveCapaBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Approving...';

            const formData = new FormData();
            formData.append('action', 'approve_capa');
            formData.append('id', activeApproveCapaId);

            fetch('findings_capa.php?office_id=<?= $office_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                confirmApproveCapaBtn.disabled = false;
                confirmApproveCapaBtn.innerHTML = originalHtml;
                approveConfirmationModal.hide();                if (data.success) {
                    if (activeApproveRow) {
                        const statusCell = activeApproveRow.querySelector('.capa-status-cell');
                        if (statusCell) {
                            statusCell.setAttribute('data-status', 'Approved');
                            statusCell.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem; background-color: rgba(25, 135, 84, 0.1) !important; color: #198754 !important; border-color: rgba(25, 135, 84, 0.2) !important;"><i class="fas fa-check-circle me-1"></i>Approved</span>';
                        }
                        const appBtn = activeApproveRow.querySelector('.btn-approve-capa');
                        if (appBtn) appBtn.remove();
                        const rejBtn = activeApproveRow.querySelector('.btn-reject-capa');
                        if (rejBtn) rejBtn.remove();

                        const findingCard = activeApproveRow.closest('.finding-card-item');
                        const fId = findingCard ? findingCard.id.replace('finding-card-', '') : null;
                        if (fId) updateFindingStatusBadge(fId);
                    }
                    if (window.refreshSidebarBadges) window.refreshSidebarBadges();
                    window.showNotification('CAPA approved successfully.', 'success');
                } else {
                    window.showNotification(data.message || 'Error approving CAPA.', 'danger');
                }
            })
            .catch(err => {
                confirmApproveCapaBtn.disabled = false;
                confirmApproveCapaBtn.innerHTML = originalHtml;
                approveConfirmationModal.hide();
                window.showNotification('Network error. Please try again.', 'danger');
            });
        });
    }

    const confirmRejectCapaBtn = document.getElementById('confirm-reject-capa-btn');
    if (confirmRejectCapaBtn) {
        confirmRejectCapaBtn.addEventListener('click', function() {
            if (!activeRejectCapaId) return;

            const originalHtml = confirmRejectCapaBtn.innerHTML;
            confirmRejectCapaBtn.disabled = true;
            confirmRejectCapaBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Rejecting...';

            const formData = new FormData();
            formData.append('action', 'reject_capa');
            formData.append('id', activeRejectCapaId);

            fetch('findings_capa.php?office_id=<?= $office_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                confirmRejectCapaBtn.disabled = false;
                confirmRejectCapaBtn.innerHTML = originalHtml;
                rejectConfirmationModal.hide();

                if (data.success) {
                    if (activeRejectRow) {
                        const statusCell = activeRejectRow.querySelector('.capa-status-cell');
                        if (statusCell) {
                            statusCell.setAttribute('data-status', 'Rejected');
                            statusCell.innerHTML = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem; background-color: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; border-color: rgba(220, 53, 69, 0.2) !important;"><i class="fas fa-times-circle me-1"></i>Rejected</span>';
                        }
                        const appBtn = activeRejectRow.querySelector('.btn-approve-capa');
                        if (appBtn) appBtn.remove();
                        const rejBtn = activeRejectRow.querySelector('.btn-reject-capa');
                        if (rejBtn) rejBtn.remove();

                        const findingCard = activeRejectRow.closest('.finding-card-item');
                        const fId = findingCard ? findingCard.id.replace('finding-card-', '') : null;
                        if (fId) updateFindingStatusBadge(fId);
                    }
                    if (window.refreshSidebarBadges) window.refreshSidebarBadges();
                    window.showNotification('CAPA marked as ineffective (rejected).', 'success');
                } else {
                    window.showNotification(data.message || 'Error rejecting CAPA.', 'danger');
                }
            })
            .catch(err => {
                confirmRejectCapaBtn.disabled = false;
                confirmRejectCapaBtn.innerHTML = originalHtml;
                rejectConfirmationModal.hide();
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

            fetch('findings_capa.php?office_id=<?= $office_id ?>', {
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

                    const wrapper = document.getElementById('findings-list-wrapper');
                    const emptyPlaceholder = document.getElementById('empty-findings-placeholder');
                    if (emptyPlaceholder) emptyPlaceholder.remove();

                    const capaSelect = document.getElementById('capa_finding');

                    data.inserted.forEach(f => {
                        const card = document.createElement('div');
                        card.className = 'card p-4 border shadow-sm mb-4 bg-white rounded-4 position-relative finding-card-item';
                        card.id = 'finding-card-' + f.id;

                        let badgeClass = 'bg-success';
                        if (f.finding_type === 'Major Nonconformity') {
                            badgeClass = 'bg-danger';
                        } else if (f.finding_type === 'Minor Nonconformity') {
                            badgeClass = 'bg-warning text-dark';
                        }

                        card.innerHTML = `
                            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge ${badgeClass} rounded-pill px-3 py-1.5 fw-bold finding-badge-type" style="font-size:0.8rem;">${f.finding_type}</span>
                                    <span class="text-muted small" style="font-size: 0.8rem;"><i class="far fa-clock me-1"></i>Logged: ${f.created_at}</span>
                                    <span class="text-muted small ms-2" style="font-size: 0.8rem;" id="finding-deadline-container-${f.id}"><i class="far fa-calendar-alt me-1"></i>Deadline: <span class="fw-semibold text-dark finding-deadline-val">${f.formatted_deadline}</span></span>
                                    <span class="finding-status-container-${f.id}">${f.status_html}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button class="btn btn-sm btn-outline-primary py-1 px-2.5 rounded-pill fw-semibold text-xs btn-trigger-add-capa" data-finding-id="${f.id}" data-finding-desc="${f.findings.substring(0, 50)}...">
                                        <i class="fas fa-plus me-1"></i> Add CAPA
                                    </button>
                                    <button class="btn btn-sm btn-link text-warning p-0 mx-2 btn-edit-finding" data-id="${f.id}" data-type="${f.finding_type}" data-findings="${f.findings}" data-deadline="${f.deadline}" title="Edit Finding">
                                        <i class="fas fa-edit fa-lg"></i>
                                    </button>
                                    <button class="btn btn-sm btn-link text-danger p-0 btn-delete-finding" data-id="${f.id}" title="Delete Finding">
                                        <i class="fas fa-trash-alt fa-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="fw-bold text-dark mb-2"><i class="fas fa-exclamation-circle text-danger me-1"></i>Finding / Gap Description</h6>
                                <p class="text-secondary small bg-light p-3 rounded-3 border-start border-4 border-danger lh-base finding-description-text mb-0">${f.findings}</p>
                            </div>

                            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-tools text-success me-1"></i>Corrective & Preventive Actions (CAPAs)</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0 border rounded-3 bg-light overflow-hidden">
                                    <thead>
                                        <tr class="table-secondary" style="font-size: 0.85rem;">
                                            <th class="ps-3 py-2" style="width: 30%;">Root Cause</th>
                                            <th class="py-2" style="width: 40%;">Corrective Actions</th>
                                            <th class="py-2 text-center" style="width: 15%;">Status</th>
                                            <th class="pe-3 py-2 text-center" style="width: 15%;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="capas-table-body-${f.id}">
                                        <tr class="empty-capas-row-placeholder">
                                            <td colspan="4" class="text-center text-muted py-3 small"><i class="fas fa-info-circle me-1"></i>No corrective actions registered for this finding yet. Click "Add CAPA" to record one.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        `;
                        wrapper.insertBefore(card, wrapper.firstChild);
                        bindFindingEvents(card);

                        if (capaSelect) {
                            const opt = document.createElement('option');
                            opt.value = f.id;
                            opt.text = f.finding_type + ' – ' + f.findings.substring(0, 60) + '...';
                            capaSelect.appendChild(opt);
                        }
                    });

                    document.getElementById('btn-sub-add-capa').disabled = false;
                    document.getElementById('btn-sub-add-capa').style.opacity = '1';
                    document.getElementById('btn-sub-add-capa').style.cursor = 'pointer';

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

    const editFindingForm = document.getElementById('edit-finding-form');
    const editFindingError = document.getElementById('edit-finding-error');
    if (editFindingForm) {
        editFindingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            editFindingError.classList.add('d-none');

            const formData = new FormData(editFindingForm);
            formData.append('action', 'edit_finding');

            const submitBtn = editFindingForm.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';

            fetch('findings_capa.php?office_id=<?= $office_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;

                if (data.success) {
                    editFindingModal.hide();
                    
                    const fid = formData.get('id');
                    const card = document.getElementById('finding-card-' + fid);
                    if (card) {
                        card.querySelector('.finding-description-text').innerHTML = formData.get('findings').replace(/\n/g, '<br>');
                        
                        const badge = card.querySelector('.finding-badge-type');
                        badge.innerText = formData.get('finding_type');
                        
                        badge.className = 'badge rounded-pill px-3 py-1.5 fw-bold finding-badge-type';
                        if (formData.get('finding_type') === 'Major Nonconformity') {
                            badge.classList.add('bg-danger');
                        } else if (formData.get('finding_type') === 'Minor Nonconformity') {
                            badge.classList.add('bg-warning', 'text-dark');
                        } else {
                            badge.classList.add('bg-success');
                        }

                        const dlVal = card.querySelector('.finding-deadline-val');
                        if (dlVal) {
                            dlVal.innerText = data.deadline;
                        }

                        const statusContainer = card.querySelector('.finding-status-container-' + fid);
                        if (statusContainer) {
                            statusContainer.innerHTML = data.status_html;
                        }

                        const editBtn = card.querySelector('.btn-edit-finding');
                        editBtn.setAttribute('data-type', formData.get('finding_type'));
                        editBtn.setAttribute('data-findings', formData.get('findings'));
                        editBtn.setAttribute('data-deadline', data.raw_deadline);
                    }

                    window.showNotification('Finding details updated successfully.', 'success');
                } else {
                    editFindingError.innerText = data.message || 'An error occurred.';
                    editFindingError.classList.remove('d-none');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
                editFindingError.innerText = 'Network error. Please try again.';
                editFindingError.classList.remove('d-none');
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

            fetch('findings_capa.php?office_id=<?= $office_id ?>', {
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

                    const fid = formData.get('finding_id');
                    const tbody = document.getElementById('capas-table-body-' + fid);
                    if (tbody) {
                        const placeholder = tbody.querySelector('.empty-capas-row-placeholder');
                        if (placeholder) placeholder.remove();

                        const truncated = data.corrective_actions.length > 80 ? data.corrective_actions.substring(0, 80) + '...' : data.corrective_actions;
                        const rootTruncated = data.root_cause && data.root_cause.length > 60 ? data.root_cause.substring(0, 60) + '...' : (data.root_cause || '<span class="text-muted fst-italic">Not specified</span>');
                        const tr = document.createElement('tr');
                        tr.style.fontSize = '0.85rem';
                        tr.id = 'capa-row-' + data.id;
                        tr.style.textAlign = 'center';
                        const canApproveCapa = <?= json_encode(in_array($role, ['admin', 'auditor'])) ?>;
                        const canModifyCapa = <?= json_encode($role !== 'auditor') ?>;
                        let actionButtons = '';
                        if (canApproveCapa) {
                            actionButtons += `
                                <button class="btn btn-sm btn-link text-success p-0 me-2 btn-approve-capa" data-id="${data.id}" title="Approve CAPA">
                                    <i class="fas fa-check-circle fa-lg"></i>
                                </button>
                                <button class="btn btn-sm btn-link text-danger p-0 me-2 btn-reject-capa" data-id="${data.id}" title="Reject CAPA">
                                    <i class="fas fa-times-circle fa-lg"></i>
                                </button>
                            `;
                        }
                        actionButtons += `
                            <button class="btn btn-sm btn-link text-primary p-0 ${canModifyCapa ? 'me-2' : ''} btn-view-capa" data-id="${data.id}" data-root-cause="${(data.root_cause || '').replace(/"/g, '&quot;')}" data-actions="${data.corrective_actions.replace(/"/g, '&quot;')}" title="View Full CAPA">
                                <i class="fas fa-eye fa-lg"></i>
                            </button>
                        `;
                        if (canModifyCapa) {
                            actionButtons += `
                                <button class="btn btn-sm btn-link text-warning p-0 me-2 btn-edit-capa" data-id="${data.id}" data-finding-id="${fid}" data-root-cause="${(data.root_cause || '').replace(/"/g, '&quot;')}" data-actions="${data.corrective_actions.replace(/"/g, '&quot;')}" title="Edit CAPA">
                                    <i class="fas fa-edit fa-lg"></i>
                                </button>
                                <button class="btn btn-sm btn-link text-danger p-0 btn-delete-capa" data-id="${data.id}" title="Delete CAPA">
                                    <i class="fas fa-trash-alt fa-lg"></i>
                                </button>
                            `;
                        }
                        tr.innerHTML = `
                            <td class="ps-3 py-2.5 fw-medium text-secondary capa-root-text" style="max-width: 250px; white-space: normal;">${rootTruncated.replace(/\n/g, '<br>')}</td>
                            <td class="py-2.5 fw-medium text-dark capa-desc-text" style="max-width: 350px; white-space: normal;">${truncated.replace(/\n/g, '<br>')}</td>
                            <td class="py-2.5 text-center capa-status-cell" data-status="Pending">
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem; background-color: rgba(255, 193, 7, 0.1) !important; color: #856404 !important; border-color: rgba(255, 193, 7, 0.2) !important;"><i class="fas fa-clock me-1"></i>Pending</span>
                            </td>
                            <td class="pe-3 py-2.5 text-center capa-actions-cell">
                                ${actionButtons}
                            </td>
                        `;
                        tbody.insertBefore(tr, tbody.firstChild);
                        bindCapaEvents(tr);
                    }

                    const headerBadge = document.querySelector('.badge.bg-secondary');
                    if (headerBadge) {
                        const currentVal = parseInt(headerBadge.innerText) || 0;
                        headerBadge.innerText = (currentVal + 1) + ' CAPAs';
                    }

                    const capaSelect = document.getElementById('capa_finding');
                    if (capaSelect) {
                        Array.from(capaSelect.options).forEach(opt => {
                            if (opt.value == fid) opt.remove();
                        });
                        if (capaSelect.options.length <= 1) {
                            const addCapaBtn = document.getElementById('btn-sub-add-capa');
                            if (addCapaBtn) {
                                addCapaBtn.disabled = true;
                                addCapaBtn.style.opacity = '0.5';
                                addCapaBtn.style.cursor = 'not-allowed';
                            }
                        }
                    }

                    if (fid) {
                        updateFindingStatusBadge(fid);
                    }
                    if (window.refreshSidebarBadges) {
                        window.refreshSidebarBadges();
                    }

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

    const editCapaForm = document.getElementById('edit-capa-form');
    const editCapaError = document.getElementById('edit-capa-error');
    if (editCapaForm) {
        editCapaForm.addEventListener('submit', function(e) {
            e.preventDefault();
            editCapaError.classList.add('d-none');

            const formData = new FormData(editCapaForm);
            formData.append('action', 'edit_capa');

            const submitBtn = editCapaForm.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';

            fetch('findings_capa.php?office_id=<?= $office_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;

                if (data.success) {
                    editCapaModal.hide();
                    
                    const cid = formData.get('id');
                    const row = document.getElementById('capa-row-' + cid);
                    if (row) {
                        const truncated = data.corrective_actions.length > 80 ? data.corrective_actions.substring(0, 80) + '...' : data.corrective_actions;
                        const rootTruncated = data.root_cause && data.root_cause.length > 60 ? data.root_cause.substring(0, 60) + '...' : (data.root_cause || '<span class="text-muted fst-italic">Not specified</span>');
                        const rootEl = row.querySelector('.capa-root-text');
                        if (rootEl) {
                            rootEl.innerHTML = rootTruncated.replace(/\n/g, '<br>');
                        }
                        row.querySelector('.capa-desc-text').innerHTML = truncated.replace(/\n/g, '<br>');

                        const viewBtn = row.querySelector('.btn-view-capa');
                        if (viewBtn) {
                            viewBtn.setAttribute('data-root-cause', data.root_cause || '');
                            viewBtn.setAttribute('data-actions', data.corrective_actions);
                        }
                        const editBtn = row.querySelector('.btn-edit-capa');
                        if (editBtn) {
                            editBtn.setAttribute('data-root-cause', data.root_cause || '');
                            editBtn.setAttribute('data-actions', data.corrective_actions);
                        }

                        if (data.status === 'Pending' || data.was_rejected) {
                            const statusCell = row.querySelector('.capa-status-cell');
                            if (statusCell) {
                                statusCell.setAttribute('data-status', 'Pending');
                                statusCell.innerHTML = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem; background-color: rgba(255, 193, 7, 0.1) !important; color: #856404 !important; border-color: rgba(255, 193, 7, 0.2) !important;"><i class="fas fa-clock me-1"></i>Pending</span>';
                            }
                            const actionsCell = row.querySelector('.capa-actions-cell');
                            const canApproveCapa = <?= json_encode(in_array($role, ['admin', 'auditor'])) ?>;
                            if (actionsCell && canApproveCapa && !actionsCell.querySelector('.btn-approve-capa')) {
                                const approveBtn = document.createElement('button');
                                approveBtn.className = 'btn btn-sm btn-link text-success p-0 me-2 btn-approve-capa';
                                approveBtn.setAttribute('data-id', cid);
                                approveBtn.setAttribute('title', 'Approve CAPA');
                                approveBtn.innerHTML = '<i class="fas fa-check-circle fa-lg"></i>';
                                
                                const rejectBtn = document.createElement('button');
                                rejectBtn.className = 'btn btn-sm btn-link text-danger p-0 me-2 btn-reject-capa';
                                rejectBtn.setAttribute('data-id', cid);
                                rejectBtn.setAttribute('title', 'Reject CAPA');
                                rejectBtn.innerHTML = '<i class="fas fa-times-circle fa-lg"></i>';

                                actionsCell.insertBefore(rejectBtn, actionsCell.firstChild);
                                actionsCell.insertBefore(approveBtn, actionsCell.firstChild);
                                bindCapaEvents(row);
                            }
                        }

                        const findingCard = row.closest('.finding-card-item');
                        const fId = findingCard ? findingCard.id.replace('finding-card-', '') : null;
                        if (fId) updateFindingStatusBadge(fId);
                        if (window.refreshSidebarBadges) window.refreshSidebarBadges();
                    }

                    const notificationMsg = data.was_rejected 
                        ? 'CAPA updated and status reset to Pending.' 
                        : 'Corrective action details updated successfully.';
                    window.showNotification(notificationMsg, 'success');
                } else {
                    editCapaError.innerText = data.message || 'An error occurred.';
                    editCapaError.classList.remove('d-none');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
                editCapaError.innerText = 'Network error. Please try again.';
                editCapaError.classList.remove('d-none');
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

        fetch('findings_capa.php?office_id=<?= $office_id ?>', {
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

    function runAiEditCapaSuggestion(fid) {
        if (!fid) return;

        const aiSuggestBtn = document.getElementById('btn-ai-suggest-edit-capa');
        const originalHTML = aiSuggestBtn ? aiSuggestBtn.innerHTML : '';

        if (aiSuggestBtn) {
            aiSuggestBtn.disabled = true;
            aiSuggestBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Analyzing...';
        }

        const formData = new FormData();
        formData.append('action', 'ai_suggest_capa');
        formData.append('finding_id', fid);
        const currentRootTxt = document.getElementById('edit-root_cause');
        if (currentRootTxt && currentRootTxt.value.trim()) {
            formData.append('root_cause', currentRootTxt.value.trim());
        }

        fetch('findings_capa.php?office_id=<?= $office_id ?>', {
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
                const rootWrapper = document.getElementById('edit_root_cause_suggestions_wrapper');
                const rootToggleBtn = document.getElementById('btn-toggle-edit-root-suggestions');

                if (data.root_causes && data.root_causes.length > 0) {
                    const list = document.getElementById('edit_root_cause_suggestions_list');
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
                                const rootTxt = document.getElementById('edit-root_cause');
                                if (rootTxt) {
                                    rootTxt.value = rc;
                                }
                                if (rootWrapper) {
                                    rootWrapper.classList.add('d-none');
                                }
                                if (rootToggleBtn) {
                                    rootToggleBtn.classList.remove('d-none');
                                }
                                window.showNotification('Root cause applied.', 'success');
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

                const capaWrapper = document.getElementById('edit_capa_suggestions_wrapper');
                const capaToggleBtn = document.getElementById('btn-toggle-edit-capa-suggestions');
                const capaList = document.getElementById('edit_capa_suggestions_list');

                if (data.corrective_actions && data.corrective_actions.length > 0) {
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
                                const capaTxt = document.getElementById('edit-corrective_actions');
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
                        capaToggleBtn.classList.remove('d-none');
                    }
                }

                window.showNotification('AI suggestions generated. Click any suggested root cause or corrective action to apply.', 'info');
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

    const editAiSuggestBtn = document.getElementById('btn-ai-suggest-edit-capa');
    if (editAiSuggestBtn) {
        editAiSuggestBtn.addEventListener('click', function() {
            const fid = document.getElementById('edit-capa-finding-id')?.value;
            if (!fid) {
                window.showNotification('Target finding not identified.', 'danger');
                return;
            }
            runAiEditCapaSuggestion(fid);
        });
    }

    const toggleEditRootSuggestionsBtn = document.getElementById('btn-toggle-edit-root-suggestions');
    if (toggleEditRootSuggestionsBtn) {
        toggleEditRootSuggestionsBtn.addEventListener('click', function() {
            const wrapper = document.getElementById('edit_root_cause_suggestions_wrapper');
            if (wrapper) {
                wrapper.classList.toggle('d-none');
            }
        });
    }

    const closeEditRootSuggestionsBtn = document.getElementById('btn-close-edit-root-suggestions');
    if (closeEditRootSuggestionsBtn) {
        closeEditRootSuggestionsBtn.addEventListener('click', function() {
            const wrapper = document.getElementById('edit_root_cause_suggestions_wrapper');
            const toggleBtn = document.getElementById('btn-toggle-edit-root-suggestions');
            if (wrapper) {
                wrapper.classList.add('d-none');
            }
            if (toggleBtn) {
                toggleBtn.classList.remove('d-none');
            }
        });
    }

    const toggleEditCapaSuggestionsBtn = document.getElementById('btn-toggle-edit-capa-suggestions');
    if (toggleEditCapaSuggestionsBtn) {
        toggleEditCapaSuggestionsBtn.addEventListener('click', function() {
            const wrapper = document.getElementById('edit_capa_suggestions_wrapper');
            if (wrapper) {
                wrapper.classList.toggle('d-none');
            }
        });
    }

    const closeEditCapaSuggestionsBtn = document.getElementById('btn-close-edit-capa-suggestions');
    if (closeEditCapaSuggestionsBtn) {
        closeEditCapaSuggestionsBtn.addEventListener('click', function() {
            const wrapper = document.getElementById('edit_capa_suggestions_wrapper');
            const toggleBtn = document.getElementById('btn-toggle-edit-capa-suggestions');
            if (wrapper) {
                wrapper.classList.add('d-none');
            }
            if (toggleBtn) {
                toggleBtn.classList.remove('d-none');
            }
        });
    }

    let isSyncingCapas = false;
    function syncOfficeCapas() {
        if (isSyncingCapas || document.hidden) return;
        isSyncingCapas = true;

        const formData = new FormData();
        formData.append('action', 'fetch_office_capas_data');

        fetch('findings_capa.php?office_id=<?= $office_id ?>', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            isSyncingCapas = false;
            if (!data || !data.success || !Array.isArray(data.findings)) return;

            const canApproveCapa = <?= json_encode(in_array($role, ['admin', 'auditor'])) ?>;
            const canModifyCapa = <?= json_encode($role !== 'auditor') ?>;

            let totalCapasCount = 0;

            data.findings.forEach(f => {
                const fid = f.id;
                const tbody = document.getElementById('capas-table-body-' + fid);
                if (!tbody) return;

                const capas = f.capas || [];
                totalCapasCount += capas.length;

                if (capas.length === 0) {
                    if (!tbody.querySelector('.empty-capas-row-placeholder')) {
                        tbody.innerHTML = `
                            <tr class="empty-capas-row-placeholder">
                                <td colspan="4" class="text-center text-muted py-3 small"><i class="fas fa-info-circle me-1"></i>No corrective actions registered for this finding yet. Click "Add CAPA" to record one.</td>
                            </tr>
                        `;
                    }
                } else {
                    const placeholder = tbody.querySelector('.empty-capas-row-placeholder');
                    if (placeholder) placeholder.remove();

                    const currentRows = tbody.querySelectorAll('tr[id^="capa-row-"]');
                    const currentIdMap = new Map();
                    currentRows.forEach(r => {
                        const cid = parseInt(r.id.replace('capa-row-', ''), 10);
                        if (cid) currentIdMap.set(cid, r);
                    });

                    const incomingIds = new Set(capas.map(c => c.id));

                    currentIdMap.forEach((row, cid) => {
                        if (!incomingIds.has(cid)) {
                            row.remove();
                        }
                    });

                    capas.forEach(capa => {
                        const cid = capa.id;
                        const truncated = capa.corrective_actions.length > 80 ? capa.corrective_actions.substring(0, 80) + '...' : capa.corrective_actions;
                        const rootTruncated = capa.root_cause && capa.root_cause.length > 60 ? capa.root_cause.substring(0, 60) + '...' : (capa.root_cause || '<span class="text-muted fst-italic">Not specified</span>');

                        let statusBadgeHtml = '';
                        if (capa.status === 'Approved') {
                            statusBadgeHtml = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem; background-color: rgba(25, 135, 84, 0.1) !important; color: #198754 !important; border-color: rgba(255, 193, 7, 0) !important; color: #198754 !important; border-color: rgba(25, 135, 84, 0.2) !important;"><i class="fas fa-check-circle me-1"></i>Approved</span>';
                        } else if (capa.status === 'Rejected') {
                            statusBadgeHtml = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem; background-color: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; border-color: rgba(220, 53, 69, 0.2) !important;"><i class="fas fa-times-circle me-1"></i>Rejected</span>';
                        } else {
                            statusBadgeHtml = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem; background-color: rgba(255, 193, 7, 0.1) !important; color: #856404 !important; border-color: rgba(255, 193, 7, 0.2) !important;"><i class="fas fa-clock me-1"></i>Pending</span>';
                        }

                        let actionButtons = '';
                        if (canApproveCapa && capa.status === 'Pending') {
                            actionButtons += `
                                <button class="btn btn-sm btn-link text-success p-0 me-2 btn-approve-capa" data-id="${cid}" title="Approve CAPA">
                                    <i class="fas fa-check-circle fa-lg"></i>
                                </button>
                                <button class="btn btn-sm btn-link text-danger p-0 me-2 btn-reject-capa" data-id="${cid}" title="Reject CAPA">
                                    <i class="fas fa-times-circle fa-lg"></i>
                                </button>
                            `;
                        }
                        actionButtons += `
                            <button class="btn btn-sm btn-link text-primary p-0 ${canModifyCapa ? 'me-2' : ''} btn-view-capa" data-id="${cid}" data-root-cause="${(capa.root_cause || '').replace(/"/g, '&quot;')}" data-actions="${capa.corrective_actions.replace(/"/g, '&quot;')}" title="View Full CAPA">
                                <i class="fas fa-eye fa-lg"></i>
                            </button>
                        `;
                        if (canModifyCapa) {
                            actionButtons += `
                                <button class="btn btn-sm btn-link text-warning p-0 me-2 btn-edit-capa" data-id="${cid}" data-finding-id="${fid}" data-root-cause="${(capa.root_cause || '').replace(/"/g, '&quot;')}" data-actions="${capa.corrective_actions.replace(/"/g, '&quot;')}" title="Edit CAPA">
                                    <i class="fas fa-edit fa-lg"></i>
                                </button>
                                <button class="btn btn-sm btn-link text-danger p-0 btn-delete-capa" data-id="${cid}" title="Delete CAPA">
                                    <i class="fas fa-trash-alt fa-lg"></i>
                                </button>
                            `;
                        }

                        if (currentIdMap.has(cid)) {
                            const row = currentIdMap.get(cid);
                            const statusCell = row.querySelector('.capa-status-cell');
                            if (statusCell && statusCell.getAttribute('data-status') !== capa.status) {
                                statusCell.setAttribute('data-status', capa.status);
                                statusCell.innerHTML = statusBadgeHtml;
                            }
                            const actionsCell = row.querySelector('.capa-actions-cell');
                            if (actionsCell) {
                                actionsCell.innerHTML = actionButtons;
                            }
                            const rootEl = row.querySelector('.capa-root-text');
                            if (rootEl) {
                                rootEl.innerHTML = rootTruncated.replace(/\n/g, '<br>');
                            }
                            const descEl = row.querySelector('.capa-desc-text');
                            if (descEl) {
                                descEl.innerHTML = truncated.replace(/\n/g, '<br>');
                            }
                        } else {
                            const tr = document.createElement('tr');
                            tr.style.fontSize = '0.85rem';
                            tr.style.textAlign = 'center';
                            tr.id = 'capa-row-' + cid;
                            tr.className = 'table-success';
                            tr.innerHTML = `
                                <td class="ps-3 py-2.5 fw-medium text-secondary capa-root-text" style="max-width: 250px; white-space: normal;">${rootTruncated.replace(/\n/g, '<br>')}</td>
                                <td class="py-2.5 fw-medium text-dark capa-desc-text" style="max-width: 350px; white-space: normal;">${truncated.replace(/\n/g, '<br>')}</td>
                                <td class="py-2.5 text-center capa-status-cell" data-status="${capa.status}">
                                    ${statusBadgeHtml}
                                </td>
                                <td class="pe-3 py-2.5 text-center capa-actions-cell">
                                    ${actionButtons}
                                </td>
                            `;
                            tbody.prepend(tr);
                            setTimeout(() => tr.classList.remove('table-success'), 2500);
                        }
                    });
                }

                updateFindingStatusBadge(fid);
            });

            const headerBadge = document.querySelector('.badge.bg-secondary');
            if (headerBadge) {
                headerBadge.innerText = totalCapasCount + ' CAPAs';
            }

            const capaSelect = document.getElementById('capa_finding');
            if (capaSelect) {
                data.findings.forEach(f => {
                    const fid = f.id;
                    const capas = f.capas || [];
                    const exists = Array.from(capaSelect.options).some(opt => opt.value == fid);
                    if (capas.length === 0 && !exists) {
                        const opt = document.createElement('option');
                        opt.value = fid;
                        opt.className = 'finding-opt-' + fid;
                        opt.text = f.finding_type + ' – ' + f.findings.substring(0, 60) + '...';
                        capaSelect.appendChild(opt);
                    } else if (capas.length > 0 && exists) {
                        Array.from(capaSelect.options).forEach(opt => {
                            if (opt.value == fid) opt.remove();
                        });
                    }
                });
                const addCapaBtn = document.getElementById('btn-sub-add-capa');
                if (addCapaBtn) {
                    if (capaSelect.options.length <= 1) {
                        addCapaBtn.disabled = true;
                        addCapaBtn.style.opacity = '0.5';
                        addCapaBtn.style.cursor = 'not-allowed';
                    } else {
                        addCapaBtn.disabled = false;
                        addCapaBtn.style.opacity = '1';
                        addCapaBtn.style.cursor = 'pointer';
                    }
                }
            }
        })
        .catch(() => {
            isSyncingCapas = false;
        });
    }

    setInterval(syncOfficeCapas, 3000);
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            syncOfficeCapas();
        }
    });
</script>
</body>
</html>
