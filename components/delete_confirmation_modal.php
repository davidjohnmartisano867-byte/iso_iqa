<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header px-4 py-3 bg-danger text-white rounded-top-4" style="background: linear-gradient(135deg, #ef4444, #dc2626) !important;">
                <h5 class="modal-title fw-bold" id="delete-modal-title"><i class="fas fa-exclamation-triangle me-2"></i><span id="delete-modal-title-text">Confirm Deletion</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <div class="text-danger mb-3" id="delete-modal-icon-wrap">
                    <i class="fas fa-trash-alt fa-3x" id="delete-modal-icon"></i>
                </div>
                <h5 class="fw-bold mb-2 text-dark" id="delete-modal-heading">Confirm Deletion</h5>
                <p class="text-muted small mb-4" id="delete-modal-message">Are you sure you want to permanently delete <strong id="delete-item-name" class="text-dark"></strong>? This action cannot be undone.</p>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-danger text-white rounded-3 shadow-sm" id="confirm-delete-btn" style="background: linear-gradient(135deg, #ef4444, #dc2626) !important; border:none;">
                        <i class="fas fa-trash-alt me-2"></i><span id="delete-modal-btn-text">Delete</span>
                    </button>
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
