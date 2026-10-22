<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once __DIR__ . '/../../config/db.php';

$office_id = (int)($_GET['office_id'] ?? 0);
if ($office_id <= 0) {
    header("Location: ../view/offices.php");
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
        header("Location: ../view/offices.php");
        exit;
    }
} elseif ($role === 'auditee') {
    $stmt = $pdo->prepare("SELECT id FROM offices WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $auditee_office_id = $stmt->fetchColumn();
    if (!$auditee_office_id || $office_id !== (int)$auditee_office_id) {
        header("Location: ../view/dashboard.php");
        exit;
    }
}

$stmt = $pdo->prepare("SELECT o.abbreviation, o.name, o.officer_in_charge, o.created_at, u.username as account_user FROM offices o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$office_id]);
$office = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$office) {
    header("Location: ../view/offices.php");
    exit;
}

$total_docs = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE office_id = ?");
$total_docs->execute([$office_id]);
$doc_count = $total_docs->fetchColumn();

$findings_stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN finding_type = 'Major Nonconformity' THEN 1 ELSE 0 END), 0) AS majors,
        COALESCE(SUM(CASE WHEN finding_type = 'Minor Nonconformity' THEN 1 ELSE 0 END), 0) AS minors,
        COALESCE(SUM(CASE WHEN finding_type = 'Conformity' THEN 1 ELSE 0 END), 0) AS conformities
    FROM findings WHERE office_id = ?
");
$findings_stmt->execute([$office_id]);
$findings_data = $findings_stmt->fetch(PDO::FETCH_ASSOC);

$majors = (int)$findings_data['majors'];
$minors = (int)$findings_data['minors'];
$conformities = (int)$findings_data['conformities'];
$total_findings = $majors + $minors + $conformities;

if ($doc_count == 0) {
    $compliance_rate = 0;
} else {
    $compliance_rate = max(0, 100 - ($majors * 15 + $minors * 5));
}
$comp_color = '#10b981';
if ($compliance_rate < 75) {
    $comp_color = '#ef4444';
} elseif ($compliance_rate < 90) {
    $comp_color = '#f59e0b';
}

$capas_stmt = $pdo->prepare("SELECT COUNT(*) FROM capas WHERE finding_id IN (SELECT id FROM findings WHERE office_id = ?)");
$capas_stmt->execute([$office_id]);
$capa_count = $capas_stmt->fetchColumn();

$username = $_SESSION['username'] ?? 'User';
$active_page = 'offices';
$base_url    = '../view/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Office Details – ISO-QA AI System</title>
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
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-1">
                    <i class="fas fa-building text-primary me-2"></i>Office Details: <?= htmlspecialchars($office['abbreviation']) ?>
                </h4>
                <p class="text-muted mb-0 small"><?= htmlspecialchars($office['name']) ?></p>
            </div>
            <div>
                <?php if ($role !== 'auditee'): ?>
                <a href="../view/offices.php" class="btn btn-sm btn-secondary rounded-3 px-3 d-flex align-items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Back to Offices
                </a>
                <?php else: ?>
                <a href="../view/dashboard.php" class="btn btn-sm btn-secondary rounded-3 px-3 d-flex align-items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:rgba(16,185,129,0.1);">
                        <i class="fas fa-award" style="color:#10b981;font-size:1.4rem;"></i>
                    </div>
                    <div>
                        <div class="stat-label">Compliance Rate</div>
                        <div class="stat-value" style="color: <?= $comp_color ?>;"><?= $compliance_rate ?>%</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:rgba(37,99,235,0.1);">
                        <i class="fas fa-file-invoice text-primary"></i>
                    </div>
                    <div>
                        <div class="stat-label">Documents Scanned</div>
                        <div class="stat-value"><?= $doc_count ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:rgba(239,68,68,0.1);">
                        <i class="fas fa-clipboard-list text-danger" style="font-size:1.4rem;"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Findings / CAPAs</div>
                        <div class="stat-value"><?= $total_findings ?> / <?= $capa_count ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-6">
                <div class="chart-card h-100">
                    <h5 class="fw-bold text-dark border-bottom pb-3 mb-3"><i class="fas fa-info-circle text-primary me-2"></i>General Information</h5>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-secondary fw-semibold py-2.5 small" style="width: 35%;">Office Name:</td>
                                    <td class="text-dark fw-medium py-2.5 small"><?= htmlspecialchars($office['name']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-secondary fw-semibold py-2.5 small">Abbreviation:</td>
                                    <td class="text-dark fw-medium py-2.5 small"><span class="badge bg-primary-subtle text-primary fw-bold px-2.5 py-1.5 rounded-2" style="background: rgba(37,99,235,0.1) !important; color: #2563eb !important;"><?= htmlspecialchars($office['abbreviation']) ?></span></td>
                                </tr>
                                <tr>
                                    <td class="text-secondary fw-semibold py-2.5 small">Officer in Charge:</td>
                                    <td class="text-dark fw-medium py-2.5 small"><?= htmlspecialchars($office['officer_in_charge']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-secondary fw-semibold py-2.5 small">Registered Date:</td>
                                    <td class="text-dark fw-medium py-2.5 small"><?= date('F j, Y', strtotime($office['created_at'])) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="chart-card h-100">
                    <h5 class="fw-bold text-dark border-bottom pb-3 mb-3"><i class="fas fa-external-link-alt text-primary me-2"></i>QMS Quick Navigation</h5>
                    <p class="text-secondary small mb-4">Direct shortcuts to execute audits, manage compliance trails, and trace corrective action plans for this specific office.</p>
                    <div class="d-flex flex-column gap-3">
                        <a href="office_documents.php?office_id=<?= $office_id ?>" class="btn btn-outline-primary py-3 px-4 rounded-4 d-flex justify-content-between align-items-center border shadow-xs" style="text-align: left; transition: all 0.2s;">
                            <span class="d-flex align-items-center gap-3">
                                <span class="d-flex align-items-center justify-content-center bg-primary text-white rounded-3" style="width:40px;height:40px;"><i class="fas fa-file-invoice"></i></span>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">Office Documents</h6>
                                    <span class="text-secondary small">View uploaded compliance evidence files and categories</span>
                                </div>
                            </span>
                            <i class="fas fa-chevron-right text-muted"></i>
                        </a>
                        <a href="findings_capa.php?office_id=<?= $office_id ?>" class="btn btn-outline-primary py-3 px-4 rounded-4 d-flex justify-content-between align-items-center border shadow-xs" style="text-align: left; transition: all 0.2s;">
                            <span class="d-flex align-items-center gap-3">
                                <span class="d-flex align-items-center justify-content-center bg-danger text-white rounded-3" style="width:40px;height:40px;"><i class="fas fa-tools"></i></span>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">Findings & CAPAs</h6>
                                    <span class="text-secondary small">Execute compliance audits and apply action plans</span>
                                </div>
                            </span>
                            <i class="fas fa-chevron-right text-muted"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
