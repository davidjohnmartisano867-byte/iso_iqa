<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

    if ($_POST['action'] === 'get_filtered_report') {
        $office_id = (int)($_POST['office_id'] ?? 0);
        $month = (int)($_POST['month'] ?? 0);
        $year = (int)($_POST['year'] ?? 0);

        try {
            $office_cond = "";
            $office_params = [];
            if ($office_id > 0) {
                $office_cond = " AND id = ?";
                $office_params[] = $office_id;
            }

            $stmt = $pdo->prepare("SELECT id, name, abbreviation, officer_in_charge FROM offices WHERE 1=1" . $office_cond . " ORDER BY abbreviation ASC");
            $stmt->execute($office_params);
            $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $clause_titles = [
                1 => 'Scope',
                2 => 'Normative References',
                3 => 'Terms & Definitions',
                4 => 'Context of the Organization',
                5 => 'Leadership',
                6 => 'Planning',
                7 => 'Support',
                8 => 'Operation',
                9 => 'Performance Evaluation',
                10 => 'Improvement'
            ];

            $reports = [];
            $activities = [];
            $total_docs = 0;
            $total_findings = 0;
            $total_capas = 0;
            $total_nonconformity = 0;
            $internal_audit_count = 0;
            $external_audit_count = 0;

            foreach ($offices as $office) {
                $o_id = (int)$office['id'];

                $assign_stmt = $pdo->prepare("
                    SELECT oa.activity, a.name as auditor_name
                    FROM office_assignments oa 
                    JOIN auditors a ON oa.auditor_id = a.id 
                    WHERE oa.office_id = ?
                ");
                $assign_stmt->execute([$o_id]);
                $assignments = $assign_stmt->fetchAll(PDO::FETCH_ASSOC);

                $auditor_names = [];
                $office_activity_type = 'Internal Audit';
                foreach ($assignments as $as) {
                    if (!empty($as['auditor_name'])) {
                        $auditor_names[] = $as['auditor_name'];
                    }
                    if (!empty($as['activity'])) {
                        $office_activity_type = $as['activity'];
                    }
                }
                $assigned_auditors_display = !empty($auditor_names) 
                    ? implode(', ', array_unique($auditor_names)) 
                    : ($office_activity_type === 'External Audit' ? 'External ISO Registrar' : 'Internal Audit Team');

                $doc_query = "SELECT COUNT(*) FROM documents WHERE office_id = ?";
                $doc_params = [$o_id];
                if ($month > 0) {
                    $doc_query .= " AND MONTH(created_at) = ?";
                    $doc_params[] = $month;
                }
                if ($year > 0) {
                    $doc_query .= " AND YEAR(created_at) = ?";
                    $doc_params[] = $year;
                }
                $d_stmt = $pdo->prepare($doc_query);
                $d_stmt->execute($doc_params);
                $documents_count = (int)$d_stmt->fetchColumn();
                $total_docs += $documents_count;

                $find_query = "
                    SELECT f.id, f.office_id, f.document_id, f.finding_type, f.findings, f.deadline, f.created_at,
                           d.filename, d.clause as doc_clause, c.title as clause_title
                    FROM findings f
                    LEFT JOIN documents d ON f.document_id = d.id
                    LEFT JOIN clauses c ON d.clause = c.clause_number
                    WHERE f.office_id = ?
                ";
                $find_params = [$o_id];
                if ($month > 0) {
                    $find_query .= " AND MONTH(f.created_at) = ?";
                    $find_params[] = $month;
                }
                if ($year > 0) {
                    $find_query .= " AND YEAR(f.created_at) = ?";
                    $find_params[] = $year;
                }
                $find_query .= " ORDER BY f.created_at DESC";
                $f_stmt = $pdo->prepare($find_query);
                $f_stmt->execute($find_params);
                $findings_list = $f_stmt->fetchAll(PDO::FETCH_ASSOC);
                $total_findings += count($findings_list);

                $major = 0;
                $minor = 0;
                $conformity = 0;
                $office_findings_with_capas = [];

                foreach ($findings_list as $f) {
                    if ($f['finding_type'] === 'Major Nonconformity') {
                        $major++;
                        $total_nonconformity++;
                    } elseif ($f['finding_type'] === 'Minor Nonconformity') {
                        $minor++;
                        $total_nonconformity++;
                    } elseif ($f['finding_type'] === 'Conformity') {
                        $conformity++;
                    }

                    $capa_query = "SELECT id, root_cause, corrective_actions, status, deadline, created_at FROM capas WHERE finding_id = ?";
                    $capa_params = [$f['id']];
                    if ($month > 0) {
                        $capa_query .= " AND MONTH(created_at) = ?";
                        $capa_params[] = $month;
                    }
                    if ($year > 0) {
                        $capa_query .= " AND YEAR(created_at) = ?";
                        $capa_params[] = $year;
                    }
                    $c_stmt = $pdo->prepare($capa_query);
                    $c_stmt->execute($capa_params);
                    $capas = $c_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($capas) > 0) {
                        $total_capas += count($capas);
                    }

                    $office_findings_with_capas[] = [
                        'id' => $f['id'],
                        'finding_type' => $f['finding_type'],
                        'findings' => $f['findings'],
                        'deadline' => $f['deadline'] ? date('M j, Y', strtotime($f['deadline'])) : 'N/A',
                        'created_at' => date('M j, Y', strtotime($f['created_at'])),
                        'capas' => $capas
                    ];

                    if (!empty($f['doc_clause'])) {
                        $c_num = (int)$f['doc_clause'];
                        $c_title = $f['clause_title'] ?? ($clause_titles[$c_num] ?? 'General Requirement');
                        $clause_display = "Clause {$c_num} – {$c_title}";
                    } elseif (preg_match('/Clause\s*([0-9]+(?:\.[0-9]+)*)/i', $f['findings'], $matches)) {
                        $raw_clause = $matches[1];
                        $main_num = (int)explode('.', $raw_clause)[0];
                        $c_title = $clause_titles[$main_num] ?? 'Quality Requirements';
                        $clause_display = "Clause {$raw_clause} – {$c_title}";
                    } else {
                        $clause_display = "ISO 9001:2015 Requirements";
                    }

                    $act_type = $office_activity_type;
                    if ($act_type === 'External Audit') {
                        $external_audit_count++;
                    } else {
                        $internal_audit_count++;
                    }

                    $activities[] = [
                        'id' => $f['id'],
                        'activity_type' => $act_type,
                        'auditor' => $assigned_auditors_display,
                        'auditee_abbr' => $office['abbreviation'],
                        'auditee_name' => $office['name'],
                        'auditee_oic' => $office['officer_in_charge'],
                        'clause' => $clause_display,
                        'finding_type' => $f['finding_type'],
                        'findings' => $f['findings'],
                        'deadline' => $f['deadline'] ? date('M j, Y', strtotime($f['deadline'])) : 'N/A',
                        'created_at' => date('M j, Y', strtotime($f['created_at'])),
                        'capas' => $capas
                    ];
                }

                $reports[] = [
                    'office_id' => $o_id,
                    'office_name' => $office['name'],
                    'office_abbr' => $office['abbreviation'],
                    'officer_in_charge' => $office['officer_in_charge'],
                    'activity_type' => $office_activity_type,
                    'assigned_auditors' => $assigned_auditors_display,
                    'documents_count' => $documents_count,
                    'findings_count' => count($findings_list),
                    'major_nc_count' => $major,
                    'minor_nc_count' => $minor,
                    'conformity_count' => $conformity,
                    'findings' => $office_findings_with_capas
                ];
            }

            $overall_resolution_rate = 0;
            if ($total_nonconformity > 0) {
                $overall_resolution_rate = round(($total_capas / $total_nonconformity) * 100);
            } else if ($total_findings > 0) {
                $overall_resolution_rate = 100;
            }

            echo json_encode([
                'success' => true,
                'total_documents' => $total_docs,
                'total_findings' => $total_findings,
                'total_capas' => $total_capas,
                'overall_resolution_rate' => $overall_resolution_rate,
                'activities' => $activities,
                'internal_audit_count' => $internal_audit_count,
                'external_audit_count' => $external_audit_count,
                'reports' => $reports
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

$username = $_SESSION['username'] ?? 'User';
$role     = $_SESSION['role'] ?? 'admin';
$active_page = 'reports';
$base_url    = '';

$offices_list = $pdo->query("SELECT id, name, abbreviation FROM offices ORDER BY abbreviation ASC")->fetchAll(PDO::FETCH_ASSOC);
$current_year = (int)date('Y');
$years = range($current_year - 2, $current_year + 4);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ISO-QA AI Reports</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" />
    <link rel="stylesheet" href="../../assets/css/style.css" />
    <link rel="icon" href="../../assets/image/iso_badge.png">
    <style>
        @media print {
            @page {
                size: portrait;
                margin: 20mm 15mm 20mm 15mm;
            }
            body {
                background: #ffffff !important;
                color: #1e293b !important;
                font-family: 'Outfit', sans-serif !important;
                font-size: 10pt !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .sidebar, #filter-card, .sidebar-link, .sidebar-footer, .nav-section-label {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            .no-print {
                display: none !important;
            }
            .chart-card {
                border: 1px solid #e2e8f0 !important;
                box-shadow: none !important;
                margin-bottom: 25px !important;
                padding: 20px !important;
                border-radius: 12px !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                background-color: #ffffff !important;
            }
            .table-responsive {
                overflow: visible !important;
            }
            table {
                width: 100% !important;
                border-collapse: collapse !important;
                page-break-inside: auto !important;
            }
            tr {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }
            .badge {
                border: 1px solid rgba(0,0,0,0.1) !important;
            }
            .progress {
                background-color: #f1f5f9 !important;
                border: 1px solid #e2e8f0 !important;
            }
            .progress-bar {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../components/sidebar.php'; ?>

<div class="main-content">
    <div class="p-4">
        
        <div class="d-none d-print-block mb-4 border-bottom pb-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold text-dark mb-1">ISO 9001:2015 QUALITY REPORT</h2>
                    <p class="text-muted mb-0 small">Organization-wide Compliance & Quality Management System Executive Summary</p>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary text-white px-3 py-2 rounded-2 fw-bold" style="background-color: #2563eb !important; border: 1px solid rgba(0,0,0,0.1) !important;">CONFIDENTIAL</span>
                    <p class="text-muted small mb-0 mt-1">Generated: <?= date('M j, Y') ?></p>
                </div>
            </div>
        </div>

        <div class="chart-card mb-4 bg-white border shadow-sm p-4 no-print" style="border-radius: 16px;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">ISO-QA Reports</h5>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <form id="report-filter-form" class="d-flex align-items-center gap-2 mb-0 flex-nowrap">
                        <select class="form-select bg-light" id="filter_office" name="office_id" style="width: 180px;">
                            <option value="0">All Offices</option>
                            <?php foreach ($offices_list as $o): ?>
                                <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['abbreviation']) ?> – <?= htmlspecialchars($o['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-select bg-light" id="filter_month" name="month" style="width: 140px;">
                            <option value="0">All Months</option>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                        <select class="form-select bg-light" id="filter_year" name="year" style="width: 120px;">
                            <option value="0">All Years</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y ?>" <?= $y === $current_year ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <button class="btn btn-custom py-2 px-3 rounded-3 text-white fw-bold d-flex align-items-center gap-2 text-nowrap flex-shrink-0" id="btn-export-pdf" style="border: none; font-size: 0.9rem;">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>

            <div class="row g-4 mt-2 mb-4" id="reports-stats-overview">
                <div class="col-md-3">
                    <div class="chart-card bg-white border shadow-sm p-3 rounded-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-muted text-md fw-semibold"><i class="fas fa-file-alt me-1 text-primary"></i>Scanned Documents</span>
                            <h2 class="fw-bold text-dark mt-2 mb-0" id="stat-docs-count">0</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="chart-card bg-white border shadow-sm p-3 rounded-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-muted text-md fw-semibold"><i class="fas fa-exclamation-triangle me-1 text-danger"></i>Audit Findings</span>
                            <h2 class="fw-bold text-dark mt-2 mb-0" id="stat-findings-count">0</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="chart-card bg-white border shadow-sm p-3 rounded-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-muted text-md fw-semibold"><i class="fas fa-tools me-1 text-success"></i>Registered CAPAs</span>
                            <h2 class="fw-bold text-dark mt-2 mb-0" id="stat-capas-count">0</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="chart-card bg-white border shadow-sm p-3 rounded-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-muted text-md fw-semibold"><i class="fas fa-percent me-1 text-info"></i>Resolution Rate</span>
                            <h2 class="fw-bold text-dark mt-2 mb-0" id="stat-resolution-rate">0%</h2>
                        </div>
                    </div>
                </div>
            </div>

            <div id="reports-output-container">
                <div class="text-center py-5 text-muted">
                    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
                    <h6 class="fw-semibold">Compiling quality logs...</h6>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterForm = document.getElementById('report-filter-form');
        const container = document.getElementById('reports-output-container');
        const exportBtn = document.getElementById('btn-export-pdf');

        function fetchQMSReport() {
            container.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
                    <h6 class="fw-semibold">Compiling quality logs...</h6>
                </div>
            `;

            const formData = new FormData(filterForm);
            formData.append('action', 'get_filtered_report');

            fetch('reports.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderReportView(data);
                } else {
                    container.innerHTML = `
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>Failed to fetch reports: ${data.message}
                        </div>
                    `;
                }
            })
            .catch(err => {
                container.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>A network error occurred. Please try again.
                    </div>
                `;
            });
        }

        function renderReportView(data) {
            document.getElementById('stat-docs-count').innerText = data.total_documents;
            document.getElementById('stat-findings-count').innerText = data.total_findings;
            document.getElementById('stat-capas-count').innerText = data.total_capas;
            document.getElementById('stat-resolution-rate').innerText = data.overall_resolution_rate + '%';

            container.innerHTML = '';

            if (data.reports.length === 0) {
                container.innerHTML = `
                    <div class="text-center p-5 text-muted">
                        <i class="fas fa-folder-open fa-3x mb-3 text-secondary"></i>
                        <h5 class="fw-bold">No Records Found</h5>
                        <p class="small mb-0">No operational documents or findings match the selected search filters.</p>
                    </div>
                `;
                return;
            }

            function escapeHtml(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            let docSummaryRows = '';
            data.reports.forEach(r => {
                docSummaryRows += `
                    <tr style="font-size:0.85rem;">
                        <td class="text-center">
                            <span class="badge bg-primary-subtle text-primary fw-bold px-2.5 py-1.5 rounded-2" style="background: rgba(37,99,235,0.1) !important; color: #2563eb !important;">${r.office_abbr}</span>
                        </td>
                        <td class="fw-semibold text-dark">${r.office_name}</td>
                        <td class="text-secondary">${r.officer_in_charge}</td>
                        <td class="text-center fw-medium text-primary">${r.documents_count}</td>
                    </tr>
                `;
            });

            const docSummaryCard = `
                <div class="chart-card p-4 mb-4 border rounded-4 bg-white shadow-sm">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><i class="fas fa-folder-open text-primary me-2"></i>Office Document Coverage & Analytics</h5>
                            <p class="text-muted mb-0 small">Overview of scanned documents and evidence trails across audited departments</p>
                        </div>
                        <span class="badge bg-dark rounded-pill px-3 py-2 fw-semibold" style="font-size: 0.8rem;">${data.reports.length} Audited Offices</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="table-light text-center" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <th>Abbreviation</th>
                                    <th>Office Name</th>
                                    <th>Officer In Charge</th>
                                    <th class="text-center">Documents</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${docSummaryRows}
                                <tr class="table-light fw-bold" style="font-size:0.85rem; border-top: 2px solid #dee2e6;">
                                    <td colspan="3" class="text-end fw-bold">Grand Total:</td>
                                    <td class="text-center fw-bold text-primary">${data.total_documents}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

            function buildActivityTableRows(activitiesList, emptyMsg, isExternal) {
                if (!activitiesList || activitiesList.length === 0) {
                    return `
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="fas fa-info-circle me-1"></i>${emptyMsg}
                            </td>
                        </tr>
                    `;
                }

                return activitiesList.map(act => {
                    let fBadge = '';
                    if (act.finding_type === 'Major Nonconformity') {
                        fBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.7rem; background: rgba(239, 68, 68, 0.1) !important; color: #dc2626 !important;"><i class="fas fa-exclamation-triangle me-1"></i>Major NC</span>';
                    } else if (act.finding_type === 'Minor Nonconformity') {
                        fBadge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.7rem; background: rgba(245, 158, 11, 0.1) !important; color: #d97706 !important;"><i class="fas fa-exclamation-circle me-1"></i>Minor NC</span>';
                    } else {
                        fBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.7rem; background: rgba(16, 185, 129, 0.1) !important; color: #059669 !important;"><i class="fas fa-check-circle me-1"></i>Conformity</span>';
                    }

                    let capaHtml = '';
                    if (!act.capas || act.capas.length === 0) {
                        capaHtml = '<span class="badge bg-secondary-subtle text-secondary border rounded-pill px-2.5 py-1.5 fw-medium" style="font-size: 0.72rem;"><i class="fas fa-clock me-1"></i>No CAPA Submitted</span>';
                    } else {
                        capaHtml = act.capas.map(c => {
                            let statusBadge = '';
                            const st = (c.status || '').toLowerCase();
                            if (st === 'approved' || st === 'effective') {
                                statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 fw-bold" style="font-size:0.68rem; background: rgba(16, 185, 129, 0.1) !important; color: #059669 !important;"><i class="fas fa-check-circle me-1"></i>Approved</span>';
                            } else if (st === 'rejected' || st === 'ineffective') {
                                statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-0.5 fw-bold" style="font-size:0.68rem; background: rgba(239, 68, 68, 0.1) !important; color: #dc2626 !important;"><i class="fas fa-times-circle me-1"></i>Rejected</span>';
                            } else {
                                statusBadge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-0.5 fw-bold" style="font-size:0.68rem; background: rgba(245, 158, 11, 0.1) !important; color: #d97706 !important;"><i class="fas fa-hourglass-half me-1"></i>Pending</span>';
                            }

                            let rootCauseHtml = c.root_cause ? `<div class="text-muted small mt-1.5 pt-1 border-top" style="font-size:0.73rem;"><strong class="text-secondary"><i class="fas fa-search me-1 text-primary"></i>Root Cause:</strong> ${escapeHtml(c.root_cause)}</div>` : '';

                            return `
                                <div class="capa-item-box p-2 mb-1.5 rounded-2 bg-light border" style="font-size: 0.78rem;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-semibold text-dark"><i class="fas fa-tools text-primary me-1"></i>CAPA Plan</span>
                                        ${statusBadge}
                                    </div>
                                    <div class="text-secondary lh-sm" style="word-break: break-word;">${escapeHtml(c.corrective_actions)}</div>
                                    ${rootCauseHtml}
                                </div>
                            `;
                        }).join('');
                    }

                    return `
                        <tr style="font-size:0.83rem;">
                            <td style="vertical-align: top; width: 180px;">
                                <div class="fw-bold text-dark d-flex align-items-center gap-1.5">
                                    <span>${escapeHtml(act.auditor)}</span>
                                </div>
                                <div class="text-muted small mt-1" style="font-size:0.72rem;"><i class="far fa-calendar-alt me-1"></i>${act.created_at}</div>
                            </td>
                            <td style="vertical-align: top; width: 190px;">
                                <div class="fw-semibold text-dark ms-1" style="font-size:0.8rem;">${escapeHtml(act.auditee_name)}</div>
                                <div class="text-muted small mt-1" style="font-size: 0.72rem;">OIC: ${escapeHtml(act.auditee_oic)}</div>
                            </td>
                            <td style="vertical-align: top; width: 180px;">
                                <span class="badge bg-light text-dark border px-2.5 py-1.5 rounded-2 d-inline-flex align-items-center gap-1" style="font-size:0.74rem; white-space: normal; text-align: left; line-height: 1.35;">
                                    <i class="fas fa-bookmark ${isExternal ? 'text-purple' : 'text-primary'}" style="${isExternal ? 'color: #7e22ce !important;' : ''}"></i> ${escapeHtml(act.clause)}
                                </span>
                            </td>
                            <td style="vertical-align: top; min-width: 240px;">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    ${fBadge}
                                    <span class="text-muted small" style="font-size:0.7rem;"><i class="far fa-clock me-1"></i>Due: ${act.deadline}</span>
                                </div>
                                <div class="text-secondary small bg-light p-2 rounded-2 border lh-sm" style="font-size:0.78rem; word-break: break-word;">
                                    ${escapeHtml(act.findings)}
                                </div>
                            </td>
                            <td style="vertical-align: top; min-width: 250px;">
                                ${capaHtml}
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            const internalActivities = (data.activities || []).filter(act => act.activity_type === 'Internal Audit');
            const externalActivities = (data.activities || []).filter(act => act.activity_type === 'External Audit');

            const internalActivitiesCard = `
                <div class="chart-card p-4 mb-4 border rounded-4 bg-white shadow-sm" id="internal-audit-activities-section">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-bottom pb-3 mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><i class="fas fa-shield-alt text-primary me-2"></i>Internal Audit Activities</h5>
                            <p class="text-muted mb-0 small">Internal quality audit log detailing Auditor, Auditee, ISO Clause, Findings, and Corrective Actions (CAPA)</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="internal-activities-table">
                            <thead>
                                <tr class="text-center table-light" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <th style="width: 180px;">Auditor</th>
                                    <th style="width: 190px;">Auditee</th>
                                    <th style="width: 180px;">ISO Clause</th>
                                    <th>Findings</th>
                                    <th style="width: 250px;">Corrective Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${buildActivityTableRows(internalActivities, 'No internal audit activity records found matching the selected search filters.', false)}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

            const externalActivitiesCard = `
                <div class="chart-card p-4 mb-4 border rounded-4 bg-white shadow-sm" id="external-audit-activities-section">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-bottom pb-3 mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><i class="fas fa-globe me-2" style="color: #7e22ce !important;"></i>External Audit Activities</h5>
                            <p class="text-muted mb-0 small">Internal quality audit log detailing Auditor, Auditee, ISO Clause, Findings, and Corrective Actions (CAPA)</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="external-activities-table">
                            <thead>
                                <tr class="table-light" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <th style="width: 180px;">Auditor</th>
                                    <th style="width: 190px;">Auditee</th>
                                    <th style="width: 180px;">ISO Clause</th>
                                    <th>Findings / Nonconformity</th>
                                    <th style="width: 250px;">Corrective Actions (CAPA)</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${buildActivityTableRows(externalActivities, 'No external audit activity records found matching the selected search filters.', true)}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

            let qmsSummaryRows = '';
            let grandMajor = 0;
            let grandMinor = 0;
            let grandConformity = 0;
            let criticalOffices = [];

            data.reports.forEach(r => {
                grandMajor += r.major_nc_count;
                grandMinor += r.minor_nc_count;
                grandConformity += r.conformity_count;

                if (r.major_nc_count > 0) {
                    criticalOffices.push(r.office_abbr);
                }

                const totalNC = r.major_nc_count + r.minor_nc_count;
                let closurePercent = 0;
                if (totalNC > 0) {
                    let capaSum = 0;
                    r.findings.forEach(f => {
                        if (f.finding_type !== 'Conformity') {
                            capaSum += f.capas.length;
                        }
                    });
                    closurePercent = Math.min(100, Math.round((capaSum / totalNC) * 100));
                } else if (r.findings_count > 0) {
                    closurePercent = 100;
                }

                let riskBadge = '';
                if (r.major_nc_count > 0) {
                    riskBadge = '<span class="badge bg-danger rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.7rem;">High Risk</span>';
                } else if (r.minor_nc_count > 0) {
                    riskBadge = '<span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.7rem;">Medium Risk</span>';
                } else {
                    riskBadge = '<span class="badge bg-success rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.7rem;">Low Risk</span>';
                }

                qmsSummaryRows += `
                    <tr style="font-size:0.85rem;">
                        <td>
                            <span class="badge bg-primary-subtle text-primary fw-bold px-2.5 py-1.5 rounded-2" style="background: rgba(37,99,235,0.1) !important; color: #2563eb !important;">${r.office_abbr}</span>
                        </td>
                        <td class="fw-semibold text-dark">${r.office_name}</td>
                        <td class="text-center fw-bold text-danger">${r.major_nc_count}</td>
                        <td class="text-center fw-bold text-warning" style="color: #d97706 !important;">${r.minor_nc_count}</td>
                        <td class="text-center fw-bold text-success">${r.conformity_count}</td>
                        <td class="text-center fw-semibold text-secondary">${r.findings_count}</td>
                        <td class="text-center fw-bold text-dark">${closurePercent}%</td>
                        <td class="text-center">${riskBadge}</td>
                    </tr>
                `;
            });

            const qmsSummaryCard = `
                <div class="chart-card p-4 mb-4 border rounded-4 bg-white shadow-sm">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><i class="fas fa-chart-line text-primary me-2"></i>Performance Summary</h5>
                            <p class="text-muted mb-0 small">Audit findings distribution, resolution progress, and compliance risk indexing across offices</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="table-light" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <th style="width: 100px;">Code</th>
                                    <th>Office Name</th>
                                    <th class="text-center" style="width: 120px;">Major NCs</th>
                                    <th class="text-center" style="width: 120px;">Minor NCs</th>
                                    <th class="text-center" style="width: 120px;">Conformities</th>
                                    <th class="text-center" style="width: 120px;">Total Gaps</th>
                                    <th class="text-center" style="width: 150px;">Resolution Rate</th>
                                    <th class="text-center" style="width: 120px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${qmsSummaryRows}
                                <tr class="table-light fw-bold" style="font-size:0.85rem; border-top: 2px solid #dee2e6;">
                                    <td colspan="2" class="text-end fw-bold">Grand Total:</td>
                                    <td class="text-center fw-bold text-danger">${grandMajor}</td>
                                    <td class="text-center fw-bold text-warning" style="color: #d97706 !important;">${grandMinor}</td>
                                    <td class="text-center fw-bold text-success">${grandConformity}</td>
                                    <td class="text-center fw-bold text-secondary">${data.total_findings}</td>
                                    <td class="text-center fw-bold text-primary">${data.overall_resolution_rate}%</td>
                                    <td class="text-center">
                                        <span class="badge ${grandMajor > 0 ? 'bg-danger' : (grandMinor > 0 ? 'bg-warning text-dark' : 'bg-success')} rounded-pill px-3 py-1.5 fw-bold" style="font-size:0.75rem;">
                                            ${grandMajor > 0 ? 'High Risk' : (grandMinor > 0 ? 'Medium Risk' : 'Low Risk')}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', docSummaryCard);
            container.insertAdjacentHTML('beforeend', internalActivitiesCard);
            container.insertAdjacentHTML('beforeend', externalActivitiesCard);
            container.insertAdjacentHTML('beforeend', qmsSummaryCard);


        }

        filterForm.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', fetchQMSReport);
        });

        exportBtn.addEventListener('click', function() {
            window.print();
        });

        fetchQMSReport();
    });
</script>
</body>
</html>
