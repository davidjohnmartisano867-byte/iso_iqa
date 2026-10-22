<div class="modal fade" id="rejectConfirmationModal" tabindex="-1" aria-labelledby="rejectConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header px-4 py-3 text-white rounded-top-4" style="background: linear-gradient(135deg, #ef4444, #dc2626) !important; border-bottom: none;">
                <h5 class="modal-title fw-bold" id="rejectConfirmationModalLabel"><i class="fas fa-times-circle me-2"></i>Reject CAPA</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <div class="text-danger mb-3">
                    <i class="fas fa-times-circle fa-3x"></i>
                </div>
                <input type="hidden" id="reject_capa_id" />
                <h5 class="fw-bold mb-2 text-dark">Confirm Rejection</h5>
                <p class="text-muted text-md mb-4">Are you sure you want to mark this corrective and preventive action as ineffective?</p>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-danger text-white rounded-3 shadow-sm" id="confirm-reject-capa-btn" style="background: linear-gradient(135deg, #ef4444, #dc2626) !important; border: none;">
                        <i class="fas fa-times me-1"></i> Reject CAPA
                    </button>
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
