<?php
$sess_success = $_SESSION['success_msg'] ?? '';
$sess_error   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
?>
<div class="toast-container position-fixed top-0 end-0 p-4" style="z-index: 9999;">
    <?php if ($sess_success): ?>
        <div class="toast show align-items-center text-white border-0 shadow-lg rounded-4 p-2" role="alert" aria-live="assertive" aria-atomic="true" style="background: linear-gradient(135deg, #10b981, #059669); min-width: 320px;">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="fas fa-check-circle fa-lg"></i>
                    <div>
                        <h6 class="fw-bold mb-0" style="font-size:0.875rem;">Success</h6>
                        <p class="mb-0 small" style="font-size:0.78rem; opacity:0.9;"><?= htmlspecialchars($sess_success) ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($sess_error): ?>
        <div class="toast show align-items-center text-white border-0 shadow-lg rounded-4 p-2" role="alert" aria-live="assertive" aria-atomic="true" style="background: linear-gradient(135deg, #ef4444, #dc2626); min-width: 320px;">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="fas fa-exclamation-circle fa-lg"></i>
                    <div>
                        <h6 class="fw-bold mb-0" style="font-size:0.875rem;">Error</h6>
                        <p class="mb-0 small" style="font-size:0.78rem; opacity:0.9;"><?= htmlspecialchars($sess_error) ?></p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toasts = document.querySelectorAll('.toast-container .toast');
        toasts.forEach(toast => {
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        });
    });

    window.showNotification = function(message, type = 'success', reasoning = '') {
        const container = document.querySelector('.toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'toast show align-items-center text-white border-0 shadow-lg rounded-4 p-2 mb-2';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.style.minWidth = '320px';
        toast.style.transition = 'all 0.5s ease';

        let icon = 'fa-check-circle';
        let bg = 'linear-gradient(135deg, #10b981, #059669)';
        let title = 'Success';

        if (type === 'danger' || type === 'error') {
            icon = 'fa-exclamation-circle';
            bg = 'linear-gradient(135deg, #ef4444, #dc2626)';
            title = 'Error';
        } else if (type === 'magic' || type === 'gemini') {
            icon = 'fa-magic';
            bg = 'linear-gradient(60deg, #4e88df 0%, #220474 100%)';
            title = '✨ AI Auto-Sorted';
        }

        toast.style.background = bg;

        let extraHTML = '';
        if (reasoning) {
            extraHTML = `<p class="mb-0 mt-1 small" style="font-size:0.72rem; opacity:0.85; font-style:italic;">AI reasoning: ${reasoning}</p>`;
        }

        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body d-flex align-items-start gap-2">
                    <i class="fas ${icon} fa-lg mt-1"></i>
                    <div>
                        <h6 class="fw-bold mb-0" style="font-size:0.875rem;">${title}</h6>
                        <p class="mb-0 small" style="font-size:0.78rem; opacity:0.95;">${message}</p>
                        ${extraHTML}
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 500);
        }, 3000);
    };
</script>
