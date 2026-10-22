<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated', 'corrective_badge_count' => 0]);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
$pdo = getDBConnection();
$role = $_SESSION['role'] ?? 'admin';
$user_id = $_SESSION['user_id'];
$corrective_badge_count = 0;

if ($role === 'auditee') {
    $stmt = $pdo->prepare("SELECT id FROM offices WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $auditee_office_id = $stmt->fetchColumn();
    if ($auditee_office_id) {
        $unsubmitted_stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT f.id) 
            FROM findings f 
            WHERE f.office_id = ? 
              AND f.finding_type != 'Conformity'
              AND (
                f.id NOT IN (SELECT finding_id FROM capas)
                OR f.id IN (SELECT finding_id FROM capas WHERE status = 'Rejected' AND finding_id NOT IN (SELECT finding_id FROM capas WHERE status IN ('Pending', 'Approved')))
              )
        ");
        $unsubmitted_stmt->execute([$auditee_office_id]);
        $corrective_badge_count = (int)$unsubmitted_stmt->fetchColumn();
    }
} elseif ($role === 'auditor') {
    $aud_stmt = $pdo->prepare("SELECT id FROM auditors WHERE user_id = ?");
    $aud_stmt->execute([$user_id]);
    $auditor_id = $aud_stmt->fetchColumn();
    if ($auditor_id) {
        $assign_stmt = $pdo->prepare("SELECT office_id FROM office_assignments WHERE auditor_id = ?");
        $assign_stmt->execute([$auditor_id]);
        $assigned_office_ids = $assign_stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($assigned_office_ids)) {
            $placeholders = implode(',', array_fill(0, count($assigned_office_ids), '?'));
            $pending_stmt = $pdo->prepare("SELECT COUNT(*) FROM capas WHERE status = 'Pending' AND finding_id IN (SELECT id FROM findings WHERE office_id IN ($placeholders))");
            $pending_stmt->execute($assigned_office_ids);
            $corrective_badge_count = (int)$pending_stmt->fetchColumn();
        }
    }
} else {
    $unsub_admin = $pdo->query("SELECT COUNT(*) FROM findings WHERE finding_type != 'Conformity' AND (id NOT IN (SELECT finding_id FROM capas) OR id IN (SELECT finding_id FROM capas WHERE status = 'Rejected' AND finding_id NOT IN (SELECT finding_id FROM capas WHERE status IN ('Pending', 'Approved'))))");
    $pending_admin = $pdo->query("SELECT COUNT(*) FROM capas WHERE status = 'Pending'");
    $cnt_unsub = $unsub_admin ? (int)$unsub_admin->fetchColumn() : 0;
    $cnt_pending = $pending_admin ? (int)$pending_admin->fetchColumn() : 0;
    $corrective_badge_count = $cnt_unsub + $cnt_pending;
}

echo json_encode([
    'success' => true,
    'corrective_badge_count' => $corrective_badge_count
]);
exit;
