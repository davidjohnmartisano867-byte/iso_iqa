<?php
$active_page = $active_page ?? 'dashboard';
$base_url    = $base_url ?? '';

$role = $role ?? ($_SESSION['role'] ?? 'admin');
$username = $username ?? ($_SESSION['username'] ?? 'User');

$auditee_office_id = null;
$corrective_badge_count = 0;

if (!isset($pdo)) {
    require_once __DIR__ . '/../config/db.php';
    $pdo = getDBConnection();
}

if ($role === 'auditee') {
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM offices WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $auditee_office_id = $stmt->fetchColumn();
    }
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
    if (isset($_SESSION['user_id'])) {
        $aud_stmt = $pdo->prepare("SELECT id FROM auditors WHERE user_id = ?");
        $aud_stmt->execute([$_SESSION['user_id']]);
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
    }
} else {
    $unsub_admin = $pdo->query("SELECT COUNT(*) FROM findings WHERE finding_type != 'Conformity' AND (id NOT IN (SELECT finding_id FROM capas) OR id IN (SELECT finding_id FROM capas WHERE status = 'Rejected' AND finding_id NOT IN (SELECT finding_id FROM capas WHERE status IN ('Pending', 'Approved'))))");
    $pending_admin = $pdo->query("SELECT COUNT(*) FROM capas WHERE status = 'Pending'");
    $cnt_unsub = $unsub_admin ? (int)$unsub_admin->fetchColumn() : 0;
    $cnt_pending = $pending_admin ? (int)$pending_admin->fetchColumn() : 0;
    $corrective_badge_count = $cnt_unsub + $cnt_pending;
}
?>
<div class="sidebar">
    <div class="sidebar-brand d-flex align-items-center">
        <img src="<?= $base_url ?>../../assets/image/iso_badge.png" alt="ISO-QA AI" width="80" />
        <div class="">
            <div class="text-dark fw-bold lh-1" style="font-size:0.95rem;">ISO-QA AI</div>
            <div class="text-muted" style="font-size:0.6rem; text-align: justify;">Isang Sistemang Organisasyon<br />for Quality Assurance Using<br />Artificial Intelligence</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= $base_url ?>dashboard.php" class="sidebar-link <?= $active_page === 'dashboard' ? 'active' : '' ?>" id="nav-dashboard">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <?php if ($role !== 'auditee'): ?>
        <a href="<?= $base_url ?>offices.php" class="sidebar-link <?= $active_page === 'offices' ? 'active' : '' ?>" id="nav-offices">
            <i class="fas fa-building"></i> Offices
        </a>
        <?php endif; ?>

        <?php if ($role === 'auditee'): ?>
            <?php if ($auditee_office_id): ?>
            <a href="<?= $base_url ?>../sub_pages/office_documents.php?office_id=<?= $auditee_office_id ?>" class="sidebar-link <?= $active_page === 'documents' ? 'active' : '' ?>" id="nav-documents">
                <i class="fas fa-file-invoice"></i> Documents
            </a>
            <?php endif; ?>
        <?php else: ?>
        <a href="<?= $base_url ?>documents.php" class="sidebar-link <?= $active_page === 'documents' ? 'active' : '' ?>" id="nav-documents">
            <i class="fas fa-file-invoice"></i> Documents
        </a>
        <?php endif; ?>

        <?php if (!in_array($role, ['auditor', 'auditee'])): ?>
        <a href="<?= $base_url ?>auditors.php" class="sidebar-link <?= $active_page === 'auditors' ? 'active' : '' ?>" id="nav-auditors">
            <i class="fas fa-user-shield"></i> Auditors
        </a>
        <a href="<?= $base_url ?>users.php" class="sidebar-link <?= $active_page === 'users' ? 'active' : '' ?>" id="nav-users">
            <i class="fas fa-users-cog"></i> Users
        </a>
        <?php endif; ?>

        <?php if ($role === 'auditee'): ?>
            <?php if ($auditee_office_id): ?>
            <a href="<?= $base_url ?>../sub_pages/findings_capa.php?office_id=<?= $auditee_office_id ?>" class="sidebar-link <?= $active_page === 'corrective' ? 'active' : '' ?>" id="nav-corrective">
                <i class="fas fa-clipboard-list flex-shrink-0"></i>
                <span class="flex-grow-1">Findings & Corrective Actions</span>
                <span class="badge rounded-pill px-2 py-0.5 fw-bold ms-auto bg-primary text-white flex-shrink-0 sidebar-corrective-badge" style="font-size: 0.72rem; letter-spacing: 0.2px; <?= $corrective_badge_count > 0 ? '' : 'display: none !important;' ?>">
                    <?= $corrective_badge_count ?>
                </span>
            </a>
            <?php endif; ?>
        <?php else: ?>
        <a href="<?= $base_url ?>corrective_actions.php" class="sidebar-link <?= $active_page === 'corrective' ? 'active' : '' ?>" id="nav-corrective">
            <i class="fas fa-clipboard-list flex-shrink-0"></i>
            <span class="flex-grow-1">Findings & Corrective Actions</span>
            <span class="badge rounded-pill px-2 py-0.5 fw-bold ms-auto bg-primary text-white flex-shrink-0 sidebar-corrective-badge" style="font-size: 0.72rem; letter-spacing: 0.2px; <?= $corrective_badge_count > 0 ? '' : 'display: none !important;' ?>">
                <?= $corrective_badge_count ?>
            </span>
        </a>
        <?php endif; ?>

        <?php if (!in_array($role, ['auditor', 'auditee'])): ?>
        <a href="<?= $base_url ?>predictive_analysis.php" class="sidebar-link <?= $active_page === 'predictive' ? 'active' : '' ?>" id="nav-predictive">
            <i class="fas fa-brain"></i> Predictive Analysis
        </a>
        <a href="<?= $base_url ?>reports.php" class="sidebar-link <?= $active_page === 'reports' ? 'active' : '' ?>" id="nav-reports">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
        <?php endif; ?>

        <?php if ($role !== 'auditor'): ?>
        <a href="<?= $base_url ?>settings.php" class="sidebar-link <?= $active_page === 'settings' ? 'active' : '' ?>" id="nav-settings">
            <i class="fas fa-cog"></i> Settings
        </a>
        <?php endif; ?>

        <a href="<?= $base_url ?>../auth/logout.php" class="sidebar-link text-danger" id="nav-logout">
            <i class="fas fa-sign-out-alt text-danger"></i> Logout
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="d-flex align-items-center gap-2">
            <div class="avatar-sm flex-shrink-0"><?= strtoupper(substr($username, 0, 1)) ?></div>
            <div class="flex-grow-1 overflow-hidden">
                <div class="text-dark fw-semibold text-truncate" style="font-size:0.85rem;"><?= htmlspecialchars($username) ?></div>
                <div class="text-muted text-capitalize" style="font-size:0.7rem;"><?= htmlspecialchars($role) ?></div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const badgeEndpoint = <?= json_encode($base_url . 'get_badge_counts.php') ?>;
    let isFetchingBadges = false;

    window.refreshSidebarBadges = function() {
        if (isFetchingBadges || document.hidden) return;
        isFetchingBadges = true;

        fetch(badgeEndpoint)
            .then(res => res.json())
            .then(data => {
                isFetchingBadges = false;
                if (!data || !data.success || data.corrective_badge_count === undefined) return;

                const count = parseInt(data.corrective_badge_count, 10) || 0;
                const badges = document.querySelectorAll('.sidebar-corrective-badge');

                badges.forEach(badge => {
                    const currentCount = parseInt(badge.textContent, 10) || 0;
                    const isVisible = (badge.style.display !== 'none');

                    if (count > 0) {
                        badge.textContent = count;
                        badge.style.setProperty('display', 'inline-block', 'important');
                        if (currentCount !== count || !isVisible) {
                            badge.style.transition = 'transform 0.25s ease, background-color 0.25s ease';
                            badge.style.transform = 'scale(1.25)';
                            setTimeout(() => {
                                badge.style.transform = 'scale(1)';
                            }, 300);
                        }
                    } else {
                        badge.style.setProperty('display', 'none', 'important');
                    }
                });
            })
            .catch(() => {
                isFetchingBadges = false;
            });
    };

    setInterval(window.refreshSidebarBadges, 3000);
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            window.refreshSidebarBadges();
        }
    });
})();
</script>
