<div class="modal fade" id="docCommentsModal" tabindex="-1" aria-labelledby="docCommentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header header-custom px-4 py-3 rounded-top-4">
                <h5 class="modal-title fw-bold text-white mb-0" id="docCommentsModalLabel">
                    <i class="fas fa-comment-dots me-2"></i>Document Comments & AI Verification
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="card border-0 shadow-sm rounded-3 mb-3">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block">Target Document</span>
                            <h6 class="fw-bold text-dark mb-0 d-flex align-items-center" id="doc-comments-modal-filename">
                                <i class="far fa-file-alt text-primary me-2"></i><span>Document</span>
                            </h6>
                        </div>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-semibold" id="doc-comments-modal-badge" style="background: rgba(37,99,235,0.1) !important; color: #2563eb !important;">
                            <i class="fas fa-robot me-1"></i>AI Quality Verified
                        </span>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-bottom-0 pt-3 px-3 pb-0">
                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center">
                            <i class="fas fa-shield-alt text-primary me-2"></i>AI Verification Notes & Comments
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="p-3 rounded-3 border bg-white text-dark small leading-relaxed" id="doc-comments-modal-body" style="white-space: pre-wrap; min-height: 120px; font-size: 0.92rem;">
                            No comments recorded for this document yet.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0 px-4 pb-3">
                <button type="button" class="btn btn-secondary rounded-3 px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>
