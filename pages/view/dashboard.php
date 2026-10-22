<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once __DIR__ . '/../../config/db.php';

$username = $_SESSION['username'] ?? 'User';
$role     = $_SESSION['role'] ?? 'admin';

$pdo = getDBConnection();

$assigned_office_ids = [];
$auditee_office_abbr = '';

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
} elseif ($role === 'auditee') {
    $stmt = $pdo->prepare("SELECT id, abbreviation FROM offices WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $office_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($office_row) {
        $assigned_office_ids = [(int)$office_row['id']];
        $auditee_office_abbr = $office_row['abbreviation'];
    } else {
        $assigned_office_ids = [0];
    }
}

$total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$where_clause = "";
if ($role === 'auditor' || $role === 'auditee') {
    $where_clause = " WHERE office_id IN (" . implode(',', array_map('intval', $assigned_office_ids)) . ")";
}

$total_findings = (int)$pdo->query("SELECT COUNT(*) FROM findings" . $where_clause)->fetchColumn();

$today = date('Y-m-d');

$completed_q = "SELECT COUNT(DISTINCT f.id) FROM findings f WHERE (f.id IN (SELECT DISTINCT finding_id FROM capas) OR f.finding_type = 'Conformity')";
if ($role === 'auditor' || $role === 'auditee') {
    $completed_q .= " AND f.office_id IN (" . implode(',', array_map('intval', $assigned_office_ids)) . ")";
}
$completed_findings = (int)$pdo->query($completed_q)->fetchColumn();

$in_prog_q = "SELECT COUNT(DISTINCT f.id) FROM findings f WHERE f.id NOT IN (SELECT DISTINCT finding_id FROM capas) AND f.finding_type != 'Conformity' AND f.deadline >= ?";
if ($role === 'auditor' || $role === 'auditee') {
    $in_prog_q .= " AND f.office_id IN (" . implode(',', array_map('intval', $assigned_office_ids)) . ")";
}
$in_progress_stmt = $pdo->prepare($in_prog_q);
$in_progress_stmt->execute([$today]);
$in_progress_count = (int)$in_progress_stmt->fetchColumn();

$overdue_q = "SELECT COUNT(DISTINCT f.id) FROM findings f WHERE f.id NOT IN (SELECT DISTINCT finding_id FROM capas) AND f.finding_type != 'Conformity' AND f.deadline < ?";
if ($role === 'auditor' || $role === 'auditee') {
    $overdue_q .= " AND f.office_id IN (" . implode(',', array_map('intval', $assigned_office_ids)) . ")";
}
$overdue_stmt = $pdo->prepare($overdue_q);
$overdue_stmt->execute([$today]);
$overdue_count = (int)$overdue_stmt->fetchColumn();

$pending_actions = $in_progress_count + $overdue_count;

$offices_q = "
    SELECT 
        o.id, 
        o.name, 
        o.abbreviation,
        (SELECT COUNT(*) FROM documents WHERE office_id = o.id) AS doc_count,
        (SELECT COUNT(*) FROM findings WHERE office_id = o.id) AS total_findings,
        (SELECT COUNT(*) FROM findings WHERE office_id = o.id AND finding_type = 'Major Nonconformity' AND id NOT IN (SELECT DISTINCT finding_id FROM capas)) AS unresolved_majors,
        (SELECT COUNT(*) FROM findings WHERE office_id = o.id AND finding_type = 'Major Nonconformity' AND id IN (SELECT DISTINCT finding_id FROM capas)) AS resolved_majors,
        (SELECT COUNT(*) FROM findings WHERE office_id = o.id AND finding_type = 'Minor Nonconformity' AND id NOT IN (SELECT DISTINCT finding_id FROM capas)) AS unresolved_minors,
        (SELECT COUNT(*) FROM findings WHERE office_id = o.id AND finding_type = 'Minor Nonconformity' AND id IN (SELECT DISTINCT finding_id FROM capas)) AS resolved_minors,
        (SELECT COUNT(*) FROM findings WHERE office_id = o.id AND finding_type = 'Conformity') AS conformities,
        (SELECT COUNT(DISTINCT c.id) FROM capas c JOIN findings f ON c.finding_id = f.id WHERE f.office_id = o.id) AS total_capas
    FROM offices o
";
if ($role === 'auditor' || $role === 'auditee') {
    $offices_q .= " WHERE o.id IN (" . implode(',', array_map('intval', $assigned_office_ids)) . ")";
}
$offices_q .= " ORDER BY o.name ASC";
$offices_stmt = $pdo->query($offices_q);

$departments = [];
$total_compliance = 0;
$office_count = 0;

while ($row = $offices_stmt->fetch(PDO::FETCH_ASSOC)) {
    $unresolved_majors = (int)$row['unresolved_majors'];
    $resolved_majors   = (int)$row['resolved_majors'];
    $unresolved_minors = (int)$row['unresolved_minors'];
    $resolved_minors   = (int)$row['resolved_minors'];
    $conformities      = (int)$row['conformities'];
    $doc_count         = (int)$row['doc_count'];
    $total_f           = (int)$row['total_findings'];
    $total_c           = (int)$row['total_capas'];

    $deductions = ($unresolved_majors * 20) + ($resolved_majors * 5) + ($unresolved_minors * 10) + ($resolved_minors * 2);
    $bonuses = ($conformities * 5);
    $pct = max(0, min(100, 100 - $deductions + $bonuses));

    $color = '#10b981';
    if ($pct < 75) {
        $color = '#ef4444';
    } elseif ($pct < 90) {
        $color = '#f59e0b';
    }

    $departments[] = [
        'name' => $row['name'] . ' (' . $row['abbreviation'] . ')',
        'pct' => $pct,
        'color' => $color,
        'doc_count' => $doc_count,
        'majors' => $unresolved_majors + $resolved_majors,
        'minors' => $unresolved_minors + $resolved_minors,
        'capas' => $total_c
    ];
    $total_compliance += $pct;
    $office_count++;
}

$avg_compliance_rate = $office_count > 0 ? round($total_compliance / $office_count) : 100;

function getMonthlyActivity($pdo, $months_back, $assigned_office_ids, $role) {
    $data = [];
    for ($i = $months_back - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i months"));
        $month_name = date('M', strtotime($date));
        $m_num = (int)date('m', strtotime($date));
        $y_num = (int)date('Y', strtotime($date));

        $completed_q = "
            SELECT COUNT(DISTINCT f.id) 
            FROM findings f 
            WHERE MONTH(f.created_at) = ? AND YEAR(f.created_at) = ?
              AND (f.id IN (SELECT DISTINCT finding_id FROM capas) OR f.finding_type = 'Conformity')
        ";
        if ($role === 'auditor' || $role === 'auditee') {
            $completed_q .= " AND f.office_id IN (" . implode(',', array_map('intval', $assigned_office_ids)) . ")";
        }
        $c_stmt = $pdo->prepare($completed_q);
        $c_stmt->execute([$m_num, $y_num]);
        $completed = (int)$c_stmt->fetchColumn();

        $pending_q = "
            SELECT COUNT(DISTINCT f.id) 
            FROM findings f 
            WHERE MONTH(f.created_at) = ? AND YEAR(f.created_at) = ?
              AND f.finding_type != 'Conformity' 
              AND f.id NOT IN (SELECT DISTINCT finding_id FROM capas)
        ";
        if ($role === 'auditor' || $role === 'auditee') {
            $pending_q .= " AND f.office_id IN (" . implode(',', array_map('intval', $assigned_office_ids)) . ")";
        }
        $p_stmt = $pdo->prepare($pending_q);
        $p_stmt->execute([$m_num, $y_num]);
        $pending = (int)$p_stmt->fetchColumn();

        $data[] = [
            'label' => $month_name,
            'completed' => $completed,
            'pending' => $pending
        ];
    }
    return $data;
}

$months_data_6  = getMonthlyActivity($pdo, 6, $assigned_office_ids, $role);
$months_data_12 = getMonthlyActivity($pdo, 12, $assigned_office_ids, $role);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard – ISO-QA AI System</title>
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
    <div class="topbar d-flex align-items-center justify-content-between">
        <div>
            <h6 class="mb-0 fw-bold text-dark">Dashboard</h6>
            <p class="mb-0 text-muted" style="font-size:0.75rem;">Welcome back, <?= htmlspecialchars($username) ?>! Here is your real-time QMS audit overview.</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-light border rounded-3 px-3 shadow-xs" id="topbar-refresh-btn">
                <i class="fas fa-sync-alt me-1 text-primary"></i> Refresh
            </button>
            <span class="text-muted small fw-medium"><?= date('F j, Y') ?></span>
            <div class="avatar-sm"><?= strtoupper(substr($username, 0, 1)) ?></div>
        </div>
    </div>

    <div class="p-4">
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:rgba(37,99,235,0.1);">
                        <i class="fas fa-exclamation-triangle text-primary"></i>
                    </div>
                    <div>
                        <div class="stat-label">Logged Findings</div>
                        <div class="stat-value"><?= $total_findings ?></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:rgba(16,185,129,0.1);">
                        <i class="fas fa-award" style="color:#10b981;font-size:1.4rem;"></i>
                    </div>
                    <div>
                        <div class="stat-label">Compliance Rate</div>
                        <div class="stat-value"><?= $avg_compliance_rate ?>%</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:rgba(245,158,11,0.1);">
                        <i class="fas fa-tasks" style="color:#f59e0b;font-size:1.4rem;"></i>
                    </div>
                    <div>
                        <div class="stat-label">Pending Actions</div>
                        <div class="stat-value"><?= $pending_actions ?></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card d-flex align-items-center gap-3">
                    <?php if ($role === 'auditor'): ?>
                        <div class="stat-icon" style="background:rgba(139,92,246,0.1);">
                            <i class="fas fa-building" style="color:#8b5cf6;font-size:1.4rem;"></i>
                        </div>
                        <div>
                            <div class="stat-label">Assigned Offices</div>
                            <div class="stat-value"><?= (count($assigned_office_ids) == 1 && $assigned_office_ids[0] == 0) ? 0 : count($assigned_office_ids) ?></div>
                        </div>
                    <?php elseif ($role === 'auditee'): ?>
                        <div class="stat-icon" style="background:rgba(139,92,246,0.1);">
                            <i class="fas fa-building" style="color:#8b5cf6;font-size:1.4rem;"></i>
                        </div>
                        <div>
                            <div class="stat-label">My Office</div>
                            <div class="stat-value" style="font-size: 1.4rem; font-weight: 700;"><?= htmlspecialchars($auditee_office_abbr ?: 'None') ?></div>
                        </div>
                    <?php else: ?>
                        <div class="stat-icon" style="background:rgba(139,92,246,0.1);">
                            <i class="fas fa-users" style="color:#8b5cf6;font-size:1.4rem;"></i>
                        </div>
                        <div>
                            <div class="stat-label">Registered Users</div>
                            <div class="stat-value"><?= $total_users ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-7">
                <div class="chart-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="card-title mb-0" id="activity-chart-title">Audit Activity – Last 6 Months</h6>
                            <span class="text-muted small" style="font-size: 0.72rem;">Completed vs Pending finding resolutions</span>
                        </div>
                        <select class="form-select form-select-sm w-auto border rounded-2 text-dark fw-semibold" id="chart-period-select" style="font-size: 0.8rem;">
                            <option value="6">Last 6 Months</option>
                            <option value="12">Last Year</option>
                        </select>
                    </div>
                    <div style="position: relative; min-height: 220px;">
                        <canvas id="auditChart" height="110"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="chart-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title mb-0">Audit Status Breakdown</h6>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1" style="font-size:0.72rem;">
                            Total: <?= $total_findings ?>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 50%; position: relative;">
                            <canvas id="statusChart" height="180"></canvas>
                        </div>
                        <div class="d-flex flex-column gap-2.5 flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-light border">
                                <span class="d-flex align-items-center gap-2 small fw-semibold text-dark">
                                    <span style="width:10px;height:10px;border-radius:50%;background:#2563eb;display:inline-block;"></span> Completed / Conformed
                                </span>
                                <strong class="text-primary"><?= $completed_findings ?></strong>
                            </div>
                            <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-light border">
                                <span class="d-flex align-items-center gap-2 small fw-semibold text-dark">
                                    <span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;display:inline-block;"></span> In Progress (Open)
                                </span>
                                <strong class="text-warning"><?= $in_progress_count ?></strong>
                            </div>
                            <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-light border">
                                <span class="d-flex align-items-center gap-2 small fw-semibold text-dark">
                                    <span style="width:10px;height:10px;border-radius:50%;background:#ef4444;display:inline-block;"></span> Overdue Actions
                                </span>
                                <strong class="text-danger"><?= $overdue_count ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-12">
                <div class="chart-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="card-title mb-0">Department Compliance Overview</h6>
                            <span class="text-muted small" style="font-size: 0.75rem;">Calculated based on ISO 9001:2015 audit findings, CAPA status, and document evidence</span>
                        </div>
                        <span class="badge bg-light text-secondary border px-3 py-1.5 rounded-pill fw-semibold" style="font-size:0.75rem;">
                            <?= count($departments) ?> Office<?= count($departments) === 1 ? '' : 's' ?> Evaluated
                        </span>
                    </div>
                    <div class="d-flex flex-column gap-3">
                        <?php if (empty($departments)): ?>
                            <div class="text-center py-4 text-muted small">
                                <i class="fas fa-building fa-2x mb-2 text-secondary"></i>
                                <p class="mb-0">No assigned offices found for this account.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($departments as $dept): ?>
                            <div class="p-3 rounded-3 bg-light border">
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span style="font-size:0.88rem;font-weight:600;" class="text-dark"><?= htmlspecialchars($dept['name']) ?></span>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="badge bg-white text-secondary border px-2 py-0.5 rounded-pill" style="font-size:0.68rem;" title="Uploaded Documents"><i class="fas fa-file-alt me-1 text-primary"></i><?= $dept['doc_count'] ?> Docs</span>
                                            <?php if ($dept['majors'] > 0): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 rounded-pill" style="font-size:0.68rem;"><i class="fas fa-exclamation-circle me-1"></i><?= $dept['majors'] ?> Major</span>
                                            <?php endif; ?>
                                            <?php if ($dept['minors'] > 0): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0.5 rounded-pill" style="font-size:0.68rem;"><i class="fas fa-exclamation-triangle me-1"></i><?= $dept['minors'] ?> Minor</span>
                                            <?php endif; ?>
                                            <?php if ($dept['capas'] > 0): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded-pill" style="font-size:0.68rem;"><i class="fas fa-check-circle me-1"></i><?= $dept['capas'] ?> CAPA</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span style="font-size:0.88rem;font-weight:800;color:<?= $dept['color'] ?>;"><?= $dept['pct'] ?>%</span>
                                </div>
                                <div class="compliance-bar" style="height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden;">
                                    <div class="compliance-fill" style="width:<?= $dept['pct'] ?>%;background:<?= $dept['color'] ?>;height:100%;border-radius:999px;transition:width 1s ease;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    const data6Months = <?= json_encode($months_data_6) ?>;
    const data12Months = <?= json_encode($months_data_12) ?>;

    const auditCtx = document.getElementById('auditChart').getContext('2d');
    const auditChart = new Chart(auditCtx, {
        type: 'bar',
        data: {
            labels: data6Months.map(d => d.label),
            datasets: [
                {
                    label: 'Completed / Resolved',
                    data: data6Months.map(d => d.completed),
                    backgroundColor: 'rgba(37,99,235,0.85)',
                    borderRadius: 6,
                },
                {
                    label: 'Pending Actions',
                    data: data6Months.map(d => d.pending),
                    backgroundColor: 'rgba(245,158,11,0.75)',
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { 
                    position: 'top', 
                    labels: { font: { family: 'Outfit', size: 12 }, boxWidth: 12 } 
                } 
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { family: 'Outfit' } } },
                y: { grid: { color: '#f0f4f8' }, ticks: { font: { family: 'Outfit' }, stepSize: 1 }, beginAtZero: true }
            }
        }
    });

    const periodSelect = document.getElementById('chart-period-select');
    if (periodSelect) {
        periodSelect.addEventListener('change', function() {
            const val = this.value;
            const targetData = val === '12' ? data12Months : data6Months;
            document.getElementById('activity-chart-title').innerText = val === '12' ? 'Audit Activity – Last Year' : 'Audit Activity – Last 6 Months';
            
            auditChart.data.labels = targetData.map(d => d.label);
            auditChart.data.datasets[0].data = targetData.map(d => d.completed);
            auditChart.data.datasets[1].data = targetData.map(d => d.pending);
            auditChart.update();
        });
    }

    const totalStatusFindings = <?= $completed_findings + $in_progress_count + $overdue_count ?>;
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    
    const chartData = totalStatusFindings === 0 
        ? [0, 0, 0, 1] 
        : [<?= $completed_findings ?>, <?= $in_progress_count ?>, <?= $overdue_count ?>];
    const chartColors = totalStatusFindings === 0 
        ? ['#2563eb', '#f59e0b', '#ef4444', '#e2e8f0'] 
        : ['#2563eb', '#f59e0b', '#ef4444'];
    const chartLabels = totalStatusFindings === 0 
        ? ['Completed', 'In Progress', 'Overdue', 'No Findings'] 
        : ['Completed', 'In Progress', 'Overdue'];

    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: chartLabels,
            datasets: [{
                data: chartData,
                backgroundColor: chartColors,
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: { legend: { display: false } }
        }
    });

    document.getElementById('topbar-refresh-btn').addEventListener('click', () => location.reload());
</script>
</body>
</html>
