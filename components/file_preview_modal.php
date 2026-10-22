<div class="modal fade" id="filePreviewModal" tabindex="-1" aria-labelledby="filePreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 90%; height: 90vh;">
        <div class="modal-content h-100 shadow-lg">
            <div class="modal-header header-custom px-4 py-3">
                <h5 class="modal-title fw-bold text-white" id="preview-modal-title"><i class="fas fa-file-alt me-2"></i>File Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 bg-light position-relative overflow-auto d-flex justify-content-center align-items-stretch" style="height: calc(100% - 56px);">
                <div id="preview-loading" class="position-absolute top-50 start-50 translate-middle text-center d-none">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="text-secondary small fw-medium">Loading preview...</div>
                </div>
                <div id="preview-error" class="position-absolute top-50 start-50 translate-middle text-center d-none">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-2"></i>
                    <div class="text-secondary small fw-medium" id="preview-error-msg">Preview unavailable.</div>
                </div>
                <div id="preview-container" class="w-100 h-100 p-4"></div>
            </div>
        </div>
    </div>
</div>
