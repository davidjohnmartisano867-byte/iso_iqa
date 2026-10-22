<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: pages/view/dashboard.php");
    exit;
}

require_once __DIR__ . '/../../config/db.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both your username and password.';
    } else {
        try {
            $pdo  = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                header("Location: ../view/dashboard.php");
                exit;
            } else {
                $error_message = 'Invalid username or password. Please try again.';
            }
        } catch (PDOException $e) {
            $error_message = 'A database error occurred. Please try again later.';
        }
    }
}

$open_modal = !empty($error_message) ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="ISO IQA System v2 – Smarter Quality Assurance. Stronger Organizations." />
    <title>ISO IQA System v2 – Internal Quality Audit</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" />
    <link rel="stylesheet" href="../../assets/css/style.css" />
</head>
<body class="bg-white">

<nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm py-2 px-3 px-lg-5" id="main-navbar">
    <div class="container-fluid px-0">
        <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="#">
            <div>
                <img src="../../assets/image/iso_badge.png" alt="" width="80px">
            </div>
            <div>
                <div class="fw-black fs-5 lh-1 text-dark" style="font-weight:800;letter-spacing:-0.5px;">ISO-QA AI</div>
                <div class="navbar-brand-subtitle text-muted text-uppercase fw-semibold">
                    Isang Sistemang Organisasyon<br />for Quality Assurance Using<br />Artificial Intelligence
                </div>
            </div>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navMenu">
            <button
                class="btn btn-primary px-4 py-2 fw-semibold rounded-2 d-flex align-items-center gap-2"
                data-bs-toggle="modal"
                data-bs-target="#loginModal"
                id="nav-login-btn"
                style="background:#1a3a6e;border-color:#1a3a6e;white-space:nowrap;">
                <i class="fas fa-sign-in-alt"></i> LOGIN
            </button>
        </div>
    </div>
</nav>

<section class="hero-section" id="hero-section">
    <img
        src="../../assets/image/admin_building.png"
        alt="Administration Building"
        class="hero-building-img"
    />
    <div class="hero-overlay"></div>
    <div class="iso-badge-circle d-none d-md-flex">
        <img src="../../assets/image/iso_badge.png" alt="ISO-QA AI Badge" style="width:100%;height:100%;object-fit:contain;" />
    </div>
    <div class="container-fluid px-4 px-lg-5 h-100 position-relative" style="z-index:4;">
        <div class="row align-items-center" style="min-height:480px;">
            <div class="col-md-6 col-lg-6" style="margin-top: -90px">
                <h1 class="fw-black lh-sm mb-3" style="font-size:clamp(1.8rem,3.5vw,2.6rem);color:#1a3a6e;font-weight:900;">
                    Smarter Quality Assurance.<br />Stronger Organizations.
                </h1>
                <p class="text-secondary mb-4" style="font-size:0.92rem;max-width:400px;line-height:1.7;" id="about-section">
                    ISO-QA AI is an AI-assisted system that automates ISO-based quality assurance
                    processes, helps detect compliance gaps, recommends corrective actions, and
                    predicts potential audit issues to support data-driven decision-making.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <button
                        class="btn-hero-primary d-flex align-items-center gap-2"
                        data-bs-toggle="modal"
                        data-bs-target="#loginModal"
                        id="hero-login-btn">
                        <i class="fas fa-sign-in-alt"></i> LOGIN TO SYSTEM
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="features-bar" id="features-section" style="margin-top: -75px; z-index: 20; position: relative;">
    <div class="container-fluid px-0">
        <div class="row g-0">
            <div class="col feature-col text-center d-flex justify-content-start align-items-center">
                <div class="feature-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="text-start ms-3">
                    <div class="feature-title">Document Management</div>
                    <div class="feature-desc">Upload, store, and organize ISO-related documents securely.</div>
                </div>
            </div>
            <div class="col feature-col text-center d-flex justify-content-center align-items-center">
                <div class="feature-icon">
                    <i class="fas fa-microchip"></i>
                </div>
                <div class="text-start ms-3">
                    <div class="feature-title">AI-Assisted Analysis</div>
                    <div class="feature-desc">Classify documents, identify compliance gaps, and generate audit findings.</div>
                </div>
            </div>
            <div class="col feature-col text-center d-flex justify-content-center align-items-center">
                <div class="feature-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="text-start ms-3">
                    <div class="feature-title">Corrective Action Management</div>
                    <div class="feature-desc">Recommend actions, assign responsibilities, set deadlines, and track compliance.</div>
                </div>
            </div>
            <div class="col feature-col text-center d-flex justify-content-center align-items-center">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="text-start ms-3">
                    <div class="feature-title">Predictive Analytics</div>
                    <div class="feature-desc">Predict potential audit risks and identify areas for continuous improvement.</div>
                </div>
            </div>
            <div class="col feature-col text-center d-flex justify-content-center align-items-center">
                <div class="feature-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="text-start ms-3">
                    <div class="feature-title">Analytical Dashboard</div>
                    <div class="feature-desc">View real-time compliance status, progress, and insights in interactive dashboards.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="mission-section py-4" id="mission-section">
    <div class="container-fluid px-4 px-lg-5">
        <div class="d-flex flex-column flex-md-row align-items-center gap-4 gap-md-0">
            <div class="d-flex align-items-start gap-3 flex-fill pe-md-4" style="width: 500px;">
                <div class="iso-globe-badge flex-shrink-0">
                    <img src="../../assets/image/shield.png" alt="" style="width:100%;height:100%;object-fit:contain;" />
                </div>
                <div>
                    <div class="fw-bold text-dark mb-1" style="font-size:0.95rem;">Our Mission</div>
                    <p class="text-secondary mb-0" style="font-size:0.82rem;line-height:1.65;">
                        To empower organizations with intelligent tools for achieving ISO compliance,
                        improving quality, and fostering a culture of continuous improvement.
                    </p>
                </div>
            </div>
            <div class="mission-divider d-none d-md-block"></div>
            <div class="flex-fill text-center px-md-4">
                <blockquote class="iso-quote mb-0">
                    <span class="quote-mark">&ldquo;</span>
                    Quality is not an act, it is a habit.
                    <span class="quote-mark">&rdquo;</span>
                    <footer class="mt-1 fw-semibold" style="font-size:0.8rem;font-style:normal;color:#2563eb;">
                        – Aristotle
                    </footer>
                </blockquote>
            </div>
            <div class="mission-divider d-none d-md-block"></div>
            <div class="flex-fill text-center ps-md-4">
                <div class="d-flex flex-column align-items-center gap-1">
                    <div class="iso-globe-badge" style="width:60px;height:60px;font-size:1.5rem;">
                        <img src="../../assets/image/iso_logo.png" alt="" style="width:100%;height:100%;object-fit:contain;" />
                    </div>
                    <div class="fw-semibold text-primary" style="font-size:0.78rem;letter-spacing:0.5px;">9001:2015</div>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="bg-dark py-3 text-center" style="background-color:#1a3a6e !important;">
    <p class="text-white-50 mb-0" style="font-size:0.8rem;">
        &copy; <?= date('Y') ?> ISO-QA AI System. All rights reserved.
    </p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once __DIR__ . '/../../components/login_modal.php'; ?>
</body>
</html>
