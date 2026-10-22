<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
        <div class="modal-content shadow-lg">
            <div class="modal-header px-4 py-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="../../assets/image/iso_badge.png" alt="ISO-QA AI" width="120" />
                    <div>
                        <div class="fw-bold text-white lh-1 mb-1" style="font-size: 25px; color:white">ISO-QA AI</div>
                        <div class="text-white-50 text-uppercase fw-semibold" style="font-size:0.75rem;letter-spacing:0.4px;line-height:1.4;">
                            Isang Sistemang Organisasyon<br />for Quality Assurance Using<br />Artificial Intelligence
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="modal-close-btn" style="margin-top: -100px; margin-right: -15px;"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <h4 class="text-center" style="color: #1a3a6e">Login to ISO-IQA AI System</h4>
                <h5 class="text-muted text-center mb-3" style="font-size: 17px;">Please enter your username and password to login</h5>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 rounded-3 mb-3" role="alert" id="modal-error-alert">
                        <i class="fas fa-exclamation-circle flex-shrink-0 text-danger"></i>
                        <small><?= htmlspecialchars($error_message) ?></small>
                    </div>
                <?php endif; ?>
                <form method="POST" action="" id="login-form" novalidate>
                    <div class="form-floating mb-3">
                        <input
                            type="text"
                            class="form-control bg-light <?= !empty($error_message) ? 'is-invalid' : '' ?>"
                            id="username"
                            name="username"
                            placeholder="Username"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            autocomplete="username"
                            required
                        />
                        <label for="username"><i class="fas fa-user me-1 text-secondary"></i>Username</label>
                    </div>
                    <div class="input-group mb-3">
                        <div class="form-floating flex-grow-1">
                            <input
                                type="password"
                                class="form-control bg-light border-end-0 <?= !empty($error_message) ? 'is-invalid' : '' ?>"
                                id="password"
                                name="password"
                                placeholder="Password"
                                autocomplete="current-password"
                                required
                            />
                            <label for="password"><i class="fas fa-lock me-1 text-secondary"></i>Password</label>
                        </div>
                        <button class="btn btn-light border border-start-0 text-secondary px-3"
                                type="button" id="toggle-password-btn" title="Show / Hide Password">
                            <i class="fas fa-eye" id="toggle-password-icon"></i>
                        </button>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-modal-login w-100 py-2 fw-semibold text-white rounded-3" id="login-submit-btn">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const openModal = <?= $open_modal ?>;
    if (openModal) {
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
    }

    const toggleBtn     = document.getElementById('toggle-password-btn');
    const passwordInput = document.getElementById('password');
    const toggleIcon    = document.getElementById('toggle-password-icon');
    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', () => {
            const show = passwordInput.type === 'password';
            passwordInput.type = show ? 'text' : 'password';
            toggleIcon.classList.toggle('fa-eye',       !show);
            toggleIcon.classList.toggle('fa-eye-slash',  show);
        });
    }

    const loginForm = document.getElementById('login-form');
    const submitBtn = document.getElementById('login-submit-btn');
    if (loginForm && submitBtn) {
        loginForm.addEventListener('submit', () => {
            const u = document.getElementById('username').value.trim();
            const p = document.getElementById('password').value;
            if (u && p) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Signing in...';
            }
        });
    }
</script>
