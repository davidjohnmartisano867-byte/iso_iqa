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
require_once __DIR__ . '/../../config/ai_service.php';

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'run_predictive_scan') {
        try {
            $stmt = $pdo->query("SELECT id, name, abbreviation FROM offices");
            $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $payload = [];

            foreach ($offices as $office) {
                $o_id = (int)$office['id'];

                $d_stmt = $pdo->prepare("SELECT filename, clause FROM documents WHERE office_id = ?");
                $d_stmt->execute([$o_id]);
                $docs_records = $d_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $documents = array_column($docs_records, 'filename');
                $doc_clauses = array_map(function($d) { return (int)($d['clause'] ?? 0); }, $docs_records);

                $f_stmt = $pdo->prepare("
                    SELECT f.id, f.findings, f.finding_type, f.created_at,
                           GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') as auditor_names
                    FROM findings f
                    LEFT JOIN office_assignments oa ON f.office_id = oa.office_id
                    LEFT JOIN auditors a ON oa.auditor_id = a.id
                    WHERE f.office_id = ?
                    GROUP BY f.id
                    ORDER BY f.created_at DESC
                ");
                $f_stmt->execute([$o_id]);
                $findings = $f_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $a_stmt = $pdo->prepare("
                    SELECT DISTINCT a.name
                    FROM office_assignments oa
                    JOIN auditors a ON oa.auditor_id = a.id
                    WHERE oa.office_id = ?
                ");
                $a_stmt->execute([$o_id]);
                $assigned_auditors = $a_stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                $c_stmt = $pdo->prepare("SELECT c.id, c.finding_id FROM capas c JOIN findings f ON c.finding_id = f.id WHERE f.office_id = ?");
                $c_stmt->execute([$o_id]);
                $capas = $c_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $capas_count = count($capas);

                $major = 0;
                $minor = 0;
                $conformity = 0;
                foreach ($findings as $f) {
                    if ($f['finding_type'] === 'Major Nonconformity') {
                        $major++;
                    } elseif ($f['finding_type'] === 'Minor Nonconformity') {
                        $minor++;
                    } elseif ($f['finding_type'] === 'Conformity') {
                        $conformity++;
                    }
                }

                $payload[] = [
                    'office_id' => $o_id,
                    'office_name' => $office['name'],
                    'office_abbr' => $office['abbreviation'],
                    'documents_count' => count($documents),
                    'findings_count' => count($findings),
                    'capas_count' => $capas_count,
                    'major_nc_count' => $major,
                    'minor_nc_count' => $minor,
                    'conformity_count' => $conformity,
                    'filenames' => $documents,
                    'doc_clauses' => $doc_clauses,
                    'historical_findings' => $findings,
                    'assigned_auditors' => $assigned_auditors,
                    'capas' => $capas
                ];
            }

            $predictions = ai_service_predict($payload);
            if (!is_array($predictions) || empty($predictions)) {
                throw new Exception("Invalid response from the forecasting engine.");
            }

            echo json_encode([
                'success' => true,
                'predictions' => $predictions
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
$active_page = 'predictive';
$base_url    = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>AI Predictive Analytics – ISO-QA AI System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" />
    <link rel="stylesheet" href="../../assets/css/style.css" />
        <link rel="icon" href="../../assets/image/iso_badge.png">
</head>
<body>

<svg style="display: none;">
    <defs>
        <linearGradient id="risk-grad-Low" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#10b981"></stop>
            <stop offset="100%" stop-color="#059669"></stop>
        </linearGradient>
        <linearGradient id="risk-grad-Medium" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#f59e0b"></stop>
            <stop offset="100%" stop-color="#d97706"></stop>
        </linearGradient>
        <linearGradient id="risk-grad-High" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#ef4444"></stop>
            <stop offset="100%" stop-color="#dc2626"></stop>
        </linearGradient>
    </defs>
</svg>

<?php require_once __DIR__ . '/../../components/sidebar.php'; ?>

<div class="main-content">
    <div class="p-4">
        <div class="chart-card mb-4 bg-white border shadow-sm p-4" style="border-radius: 16px;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1">AI Predictive Quality Analytics</h5>
                </div>
                <div>
                    <button class="btn btn-custom py-2.5 px-4 rounded-3 text-white fw-bold d-flex align-items-center gap-2" id="btn-run-scan" style="border: none; font-size: 0.9rem;">
                        <i class="fas fa-wand-magic-sparkles"></i> Run Predictive Scan
                    </button>
                </div>
            </div>
        </div>

        <div id="office-predictions-container"></div>
    </div>
</div>

<div class="modal fade" id="predictiveProgressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="predictiveProgressModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden; background: #ffffff;">
            <div class="modal-body p-5 text-center">
                <div class="mb-4 position-relative d-inline-block">
                    <div class="spinner-border text-primary" role="status" style="width: 5rem; height: 5rem; border-width: 0.35em;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="position-absolute top-50 start-50 translate-middle">
                        <i class="fas fa-brain text-primary fa-2x animate-pulse"></i>
                    </div>
                </div>
                <h4 class="fw-bold mb-2 text-dark" style="font-family: 'Outfit', sans-serif;">Predictive Quality Scan</h4>
                <p class="text-secondary small mb-4" id="predict-progress-status">Initializing QMS predictive models...</p>
                <div class="progress rounded-pill bg-light mb-0" style="height: 8px;">
                    <div id="predict-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%; transition: width 0.4s ease;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/notification.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const CACHE_KEY = 'iso_iqa_predictive_analysis_results';
        const runScanBtn = document.getElementById('btn-run-scan');
        const progressModal = new bootstrap.Modal(document.getElementById('predictiveProgressModal'));
        const progressBar = document.getElementById('predict-progress-bar');
        const progressStatus = document.getElementById('predict-progress-status');
        const container = document.getElementById('office-predictions-container');

        function triggerPredictiveScan() {
            progressBar.style.width = '0%';
            progressStatus.innerText = 'Initializing QMS predictive models...';
            progressModal.show();

            let progress = 0;
            const progressInterval = setInterval(() => {
                if (progress < 90) {
                    progress += Math.floor(Math.random() * 8) + 3;
                    if (progress > 90) progress = 90;
                    progressBar.style.width = progress + '%';
                    if (progress < 25) {
                        progressStatus.innerText = 'Scrutinizing document submission trails...';
                    } else if (progress < 50) {
                        progressStatus.innerText = 'Evaluating past CAPA closure speeds...';
                    } else if (progress < 75) {
                        progressStatus.innerText = 'Mapping process coverage ratios across clauses...';
                    } else {
                        progressStatus.innerText = 'Synthesizing compliance risk indices...';
                    }
                }
            }, 400);

            const formData = new FormData();
            formData.append('action', 'run_predictive_scan');

            fetch('predictive_analysis.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                clearInterval(progressInterval);
                progressBar.style.width = '100%';
                progressStatus.innerText = 'Scan completed!';
                setTimeout(() => { progressModal.hide(); }, 600);

                if (data.success && data.predictions) {
                    localStorage.setItem(CACHE_KEY, JSON.stringify(data.predictions));
                    renderPredictions(data.predictions);
                    window.showNotification('Predictive scan completed successfully.', 'success');
                } else {
                    window.showNotification(data.message || 'Scan failed.', 'danger');
                }
            })
            .catch(err => {
                clearInterval(progressInterval);
                progressModal.hide();
                window.showNotification('A network error occurred. Please try again.', 'danger');
            });
        }

        function showEmptyReadyState() {
            container.innerHTML = `
                <div class="chart-card p-5 text-center bg-white rounded-4 border shadow-sm my-2">
                    <div class="mb-3">
                        <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center" style="width: 70px; height: 70px; background: rgba(37,99,235,0.1) !important; color: #2563eb !important;">
                            <i class="fas fa-brain fa-2x"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Predictive Quality Analytics Ready</h5>
                    <p class="text-secondary small mx-auto mb-4" style="max-width: 480px;">
                        Click <strong>Run Predictive Scan</strong> to forecast audit nonconformities and map continuous improvement targets across ISO 9001:2015 Clauses.
                    </p>
                    <button class="btn btn-custom px-4 py-2.5 rounded-3 fw-bold shadow-sm" onclick="document.getElementById('btn-run-scan').click()">
                        <i class="fas fa-wand-magic-sparkles me-2"></i>Run Predictive Scan Now
                    </button>
                </div>
            `;
            document.getElementById('stat-scanned-count').innerText = '0';
            document.getElementById('stat-avg-risk').innerText = '0%';
            document.getElementById('stat-ci-target').innerText = 'Awaiting Scan...';
        }

        function loadInitialState() {
            const cached = localStorage.getItem(CACHE_KEY);
            if (cached) {
                try {
                    const predictions = JSON.parse(cached);
                    if (Array.isArray(predictions) && predictions.length > 0) {
                        renderPredictions(predictions);
                        return;
                    }
                } catch(e) {
                    console.error('Error parsing cached predictions', e);
                }
            }
            showEmptyReadyState();
        }

        function renderPredictions(predictions) {
            container.innerHTML = '';
            
            let totalRisk = 0;
            let scannedCount = predictions.length;
            let worstOffice = null;
            let highestRisk = -1;

            if (scannedCount === 0) {
                showEmptyReadyState();
                return;
            }

            predictions.forEach(p => {
                totalRisk += p.compliance_risk_score;
                if (p.compliance_risk_score > highestRisk) {
                    highestRisk = p.compliance_risk_score;
                    worstOffice = p;
                }

                let badgeColor = 'bg-success-subtle text-success';
                let strokeColor = 'url(#risk-grad-Low)';
                if (p.risk_category === 'Medium') {
                    badgeColor = 'bg-warning-subtle text-warning';
                    strokeColor = 'url(#risk-grad-Medium)';
                } else if (p.risk_category === 'High') {
                    badgeColor = 'bg-danger-subtle text-danger';
                    strokeColor = 'url(#risk-grad-High)';
                }

                const dashOffset = 314.16 - (314.16 * p.compliance_risk_score / 100);

                const forecastedClauses = (p.forecasted_clauses && Array.isArray(p.forecasted_clauses) && p.forecasted_clauses.length > 0)
                    ? p.forecasted_clauses
                    : [{
                        clause: (p.forecasted_nonconformity && p.forecasted_nonconformity.clause) ? p.forecasted_nonconformity.clause : 'Clause 7.5.3 - Control of documented information',
                        risk_level: p.risk_category || 'Medium',
                        description: (p.forecasted_nonconformity && p.forecasted_nonconformity.description) ? p.forecasted_nonconformity.description : 'Ensure documented procedures comply with ISO 9001:2015 requirements.',
                        area_for_improvement: p.area_for_improvement || 'Strengthen QMS review cycles and documentation control.',
                        proactive_preventive_actions: (p.proactive_preventive_actions && p.proactive_preventive_actions.length > 0) ? p.proactive_preventive_actions : ['Standardize internal audit checks', 'Maintain complete document metadata']
                    }];

                const clauseBadgesHtml = forecastedClauses.map(fc => {
                    let cBadgeBg = 'bg-danger-subtle text-danger border-danger-subtle';
                    let cIcon = 'fa-circle-exclamation';
                    if (fc.risk_level === 'Medium') {
                        cBadgeBg = 'bg-warning-subtle text-warning border-warning-subtle';
                        cIcon = 'fa-triangle-exclamation';
                    } else if (fc.risk_level === 'Low') {
                        cBadgeBg = 'bg-success-subtle text-success border-success-subtle';
                        cIcon = 'fa-circle-check';
                    }
                    return `<span class="badge ${cBadgeBg} border px-2.5 py-1.5 rounded-pill fw-semibold me-1 mb-1" style="font-size:0.75rem;"><i class="fas ${cIcon} me-1"></i>${fc.clause.split(' - ')[0]} (${fc.risk_level} Risk)</span>`;
                }).join('');

                const clausesHtml = forecastedClauses.map((fc, idx) => {
                    let cardBorder = fc.risk_level === 'High' ? 'border-danger-subtle' : (fc.risk_level === 'Medium' ? 'border-warning-subtle' : 'border-light-subtle');
                    let riskPill = fc.risk_level === 'High' 
                        ? '<span class="badge bg-danger text-white rounded-pill px-2.5 py-1" style="font-size:0.7rem;">High Risk</span>' 
                        : (fc.risk_level === 'Medium' 
                            ? '<span class="badge bg-warning text-dark rounded-pill px-2.5 py-1" style="font-size:0.7rem;">Medium Risk</span>' 
                            : '<span class="badge bg-success text-white rounded-pill px-2.5 py-1" style="font-size:0.7rem;">Low Risk</span>');

                    const mitigations = (fc.proactive_preventive_actions && fc.proactive_preventive_actions.length > 0)
                        ? fc.proactive_preventive_actions
                        : (p.proactive_preventive_actions || []);

                    return `
                        <div class="p-3 mb-2.5 rounded-3 border ${cardBorder} bg-light-subtle shadow-xs" style="background: #fafbfe;">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary text-white rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">Risk Area #${idx + 1}</span>
                                    <h6 class="fw-bold text-dark mb-0" style="font-size: 0.88rem;">${fc.clause}</h6>
                                </div>
                                <div>${riskPill}</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 border-end">
                                    <span class="text-danger fw-bold d-block mb-1" style="font-size: 0.74rem;"><i class="fas fa-exclamation-circle me-1"></i>Forecasted Audit Nonconformity</span>
                                    <p class="text-secondary mb-0 small lh-base" style="font-size:0.78rem;">${fc.description}</p>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-success fw-bold d-block mb-1" style="font-size: 0.74rem;"><i class="fas fa-arrow-trend-up me-1"></i>Continuous Improvement Target</span>
                                    <p class="text-secondary mb-0 small lh-base" style="font-size:0.78rem;">${fc.area_for_improvement || fc.area_of_improvement || 'Continuous QMS compliance verification'}</p>
                                </div>
                            </div>
                            ${mitigations.length > 0 ? `
                                <div class="mt-2.5 pt-2 border-top">
                                    <span class="text-primary fw-bold d-block mb-1.5" style="font-size:0.72rem;"><i class="fas fa-shield-alt me-1"></i>Proactive Preventive Mitigations</span>
                                    <ul class="list-unstyled mb-0 row g-2">
                                        ${mitigations.map(step => `
                                            <li class="col-md-4 text-secondary small d-flex align-items-start gap-1.5" style="font-size:0.74rem;">
                                                <i class="fas fa-check-circle text-primary mt-1 flex-shrink-0" style="font-size:0.68rem;"></i>
                                                <span>${step}</span>
                                            </li>
                                        `).join('')}
                                    </ul>
                                </div>
                            ` : ''}
                        </div>
                    `;
                }).join('');

                const cardHtml = `
                    <div class="chart-card p-4 mb-4 border rounded-4 bg-white shadow-sm hover-lift">
                        <div class="row g-4 align-items-start">
                            <div class="col-md-3 text-center border-end pt-2">
                                <div class="position-relative d-inline-block">
                                    <svg width="120" height="120" viewBox="0 0 120 120">
                                        <circle cx="60" cy="60" r="50" fill="transparent" stroke="#f1f5f9" stroke-width="8"></circle>
                                        <circle cx="60" cy="60" r="50" fill="transparent" stroke="${strokeColor}" stroke-width="8" stroke-dasharray="314.16" stroke-dashoffset="314.16" class="risk-circle-progress" style="transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dashoffset 1.5s cubic-bezier(0.4, 0, 0.2, 1);"></circle>
                                    </svg>
                                    <div class="position-absolute top-50 start-50 translate-middle text-center">
                                        <h3 class="fw-extrabold text-dark mb-0">${p.compliance_risk_score}%</h3>
                                        <span class="text-muted small fw-semibold" style="font-size:0.65rem;">RISK INDEX</span>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <span class="badge ${badgeColor} px-3 py-1.5 rounded-pill fw-bold" style="font-size:0.75rem;">${p.risk_category} Risk</span>
                                </div>
                            </div>
                            <div class="col-md-9 ps-md-4">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                    <div>
                                        <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-2 mb-1" style="font-size: 0.8rem; background: rgba(37,99,235,0.1) !important; color: #2563eb !important;">${p.office_abbr}</span>
                                        <h5 class="fw-bold text-dark mb-0 d-inline-block ms-2 align-middle">${p.office_name}</h5>
                                    </div>
                                    <div>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 rounded-pill fw-semibold" style="font-size: 0.72rem;">
                                            <i class="fas fa-microchip me-1"></i>${p.ml_model || 'Scikit-Learn ML Pipeline'}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="d-flex flex-wrap align-items-center gap-1 mb-3">
                                    <span class="text-secondary small fw-bold me-1" style="font-size: 0.73rem;"><i class="fas fa-layer-group me-1 text-primary"></i>Identified Clause Risks:</span>
                                    ${clauseBadgesHtml}
                                </div>

                                ${p.risk_drivers && p.risk_drivers.length ? `
                                    <div class="mb-3 p-2.5 rounded-3 bg-light border d-flex align-items-center flex-wrap gap-1.5">
                                        <span class="text-secondary small fw-bold me-1" style="font-size: 0.73rem;"><i class="fas fa-sliders-h me-1 text-primary"></i>ML Identified Risk Drivers:</span>
                                        ${p.risk_drivers.map(driver => `<span class="badge bg-white text-secondary border px-2 py-1 rounded-pill" style="font-size: 0.7rem;"><i class="fas fa-circle-exclamation me-1 text-warning"></i>${driver}</span>`).join('')}
                                    </div>
                                ` : ''}

                                ${(() => {
                                    const auditorFindingsList = p.auditor_findings || [];
                                    const assignedAuditorsList = p.assigned_auditors || [];
                                    const auditorNamesText = assignedAuditorsList.length > 0 ? assignedAuditorsList.join(', ') : 'Assigned Auditor Team';

                                    let auditorFindingsHtml = '';
                                    if (auditorFindingsList.length > 0) {
                                        auditorFindingsHtml = `
                                            <div class="d-flex flex-column gap-2 mt-2">
                                                ${auditorFindingsList.map(f => {
                                                    const fText = typeof f === 'object' ? (f.findings || '') : String(f);
                                                    const fType = typeof f === 'object' ? (f.finding_type || 'Finding') : 'Finding';
                                                    const fDate = (typeof f === 'object' && f.created_at) ? new Date(f.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '';
                                                    const fAuditor = (typeof f === 'object' && f.auditor_names) ? f.auditor_names : auditorNamesText;
                                                    
                                                    let badgeStyle = 'bg-secondary text-white';
                                                    if (fType === 'Major Nonconformity') badgeStyle = 'bg-danger text-white';
                                                    else if (fType === 'Minor Nonconformity') badgeStyle = 'bg-warning text-dark';
                                                    else if (fType === 'Conformity') badgeStyle = 'bg-success text-white';

                                                    return `
                                                        <div class="p-2.5 rounded-3 bg-white border border-light-subtle shadow-xs d-flex align-items-start gap-2.5">
                                                            <span class="badge ${badgeStyle} rounded-pill px-2.5 py-1.5 flex-shrink-0" style="font-size: 0.68rem; font-weight: 700;">${fType}</span>
                                                            <div class="small text-dark lh-sm flex-grow-1" style="font-size: 0.82rem;">
                                                                <span class="fw-semibold">"${escapeHtml(fText)}"</span>
                                                                <div class="text-muted mt-1" style="font-size: 0.72rem;">
                                                                    <i class="fas fa-user-pen me-1 text-primary"></i>Auditor: <strong>${escapeHtml(fAuditor)}</strong>
                                                                    ${fDate ? ` &bull; <i class="fas fa-calendar-alt me-1"></i>${fDate}` : ''}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    `;
                                                }).join('')}
                                            </div>
                                        `;
                                    } else {
                                        auditorFindingsHtml = `
                                            <div class="p-2.5 rounded-3 bg-white border border-light-subtle text-muted small fst-italic mt-2" style="font-size:0.78rem;">
                                                <i class="fas fa-info-circle me-1.5 text-primary"></i>No historical auditor nonconformities logged yet for this office. Prediction is based on initial document evidence completeness, clause mapping, and operational indicators.
                                            </div>
                                        `;
                                    }

                                    return `
                                        <div class="mb-3.5 p-3 rounded-3 border" style="background: #f8fafc;">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                <span class="text-dark small fw-bold" style="font-size: 0.8rem;">
                                                    <i class="fas fa-clipboard-list me-1.5 text-primary"></i>Auditor Findings (Basis for Prediction)
                                                </span>
                                                <span class="badge bg-white text-secondary border px-2.5 py-1 rounded-pill" style="font-size: 0.7rem;">
                                                    <i class="fas fa-user-check me-1 text-primary"></i>${escapeHtml(auditorNamesText)}
                                                </span>
                                            </div>
                                            ${auditorFindingsHtml}
                                        </div>
                                    `;
                                })()}
                                
                                <div class="mt-3">
                                    ${clausesHtml}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', cardHtml);
                
                setTimeout(() => {
                    const rowEl = container.lastElementChild;
                    const circle = rowEl.querySelector('.risk-circle-progress');
                    if (circle) {
                        circle.style.strokeDashoffset = dashOffset;
                    }
                }, 100);
            });

            document.getElementById('stat-scanned-count').innerText = scannedCount;
            document.getElementById('stat-avg-risk').innerText = Math.round(totalRisk / scannedCount) + '%';
            if (worstOffice) {
                const worstClauses = (worstOffice.forecasted_clauses && worstOffice.forecasted_clauses.length) 
                    ? worstOffice.forecasted_clauses.map(c => c.clause.split(' - ')[0]).join(', ')
                    : (worstOffice.forecasted_nonconformity ? worstOffice.forecasted_nonconformity.clause.split(' - ')[0] : 'Clause 7.5');
                document.getElementById('stat-ci-target').innerText = worstOffice.office_abbr + ': ' + worstClauses;
                document.getElementById('stat-ci-target').title = worstOffice.office_name + ' (' + worstClauses + ')';
            }
        }

        runScanBtn.addEventListener('click', function() {
            triggerPredictiveScan();
        });

        loadInitialState();
    });
</script>
</body>
</html>
