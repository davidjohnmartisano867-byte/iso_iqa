<div class="modal fade" id="editAuditorModal" tabindex="-1" aria-labelledby="editAuditorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header edit-header px-4 py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit me-2"></i>Edit Auditor Name</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-danger d-none" id="edit-auditor-error" role="alert"></div>
                <form id="edit-auditor-form" novalidate>
                    <input type="hidden" id="edit_auditor_id" name="auditor_id" />
                    <div class="mb-4">
                        <label for="edit_auditor_name" class="form-label fw-semibold text-dark small"><i class="fas fa-user me-1 text-secondary"></i>Auditor Full Name</label>
                        <input type="text" class="form-control bg-light" id="edit_auditor_name" name="auditor_name" required />
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-warning edit-save text-dark rounded-3">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                        <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
