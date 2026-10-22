<div class="modal fade" id="credentialsModal" tabindex="-1" aria-labelledby="credentialsModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header header-custom px-4 py-3 bg-success" style="border-bottom: none;">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-key me-2"></i><span id="cred-modal-title">Officer Account Credentials</span></h5>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <div class="mb-3 text-success">
                    <i class="fas fa-check-circle fa-3x"></i>
                </div>
                <h5 class="fw-bold mb-3" style="color: #1a3a6e;" id="cred-modal-heading">Office & Account Created!</h5>
                <p class="text-muted small mb-4" id="cred-modal-desc">Please copy the temporary credentials generated for the Officer in Charge. They can use these to sign in to the system.</p>
                
                <div class="bg-light p-3 rounded-3 mb-4 border text-start">
                    <div class="mb-2">
                        <span class="text-muted small fw-semibold">Username:</span>
                        <div class="d-flex align-items-center justify-content-between bg-white px-3 py-2 rounded-2 border mt-1">
                            <span class="fw-bold text-dark fs-6" id="cred-username"></span>
                            <button type="button" class="btn btn-sm btn-link text-secondary p-0" title="Copy Username" onclick="copyCredentialText('cred-username', this)">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold">Password:</span>
                        <div class="d-flex align-items-center justify-content-between bg-white px-3 py-2 rounded-2 border mt-1">
                            <span class="fw-bold text-dark fs-6" id="cred-password"></span>
                            <button type="button" class="btn btn-sm btn-link text-secondary p-0" title="Copy Password" onclick="copyCredentialText('cred-password', this)">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-success text-white rounded-3" data-bs-dismiss="modal" style="border: none;">
                        <i class="fas fa-check me-2"></i>Got It, Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyCredentialText(elementId, btn) {
    const el = document.getElementById(elementId);
    const text = el ? el.innerText.trim() : '';
    if (!text) return;

    navigator.clipboard.writeText(text).then(() => {
        const icon = btn.querySelector('i');
        if (icon) {
            const originalClass = icon.className;
            icon.className = 'fas fa-check text-success';
            setTimeout(() => {
                icon.className = originalClass;
            }, 1500);
        }
    }).catch(() => {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            const icon = btn.querySelector('i');
            if (icon) {
                const originalClass = icon.className;
                icon.className = 'fas fa-check text-success';
                setTimeout(() => {
                    icon.className = originalClass;
                }, 1500);
            }
        } catch (err) {}
        document.body.removeChild(textArea);
    });
}
</script>
