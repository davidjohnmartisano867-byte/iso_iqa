<div class="modal fade" id="editFindingModal" tabindex="-1" aria-labelledby="editFindingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header edit-header px-4 py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit me-2"></i>Edit Finding Details</h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-danger d-none" id="edit-finding-error" role="alert"></div>
                <form id="edit-finding-form" novalidate>
                    <input type="hidden" name="id" id="edit-finding-id" />
                    <div class="mb-3">
                        <label for="edit-finding-type" class="form-label fw-semibold text-dark small">Finding Type</label>
                        <select class="form-select bg-light" id="edit-finding-type" name="finding_type" required>
                            <option value="Major Nonconformity">Major Nonconformity</option>
                            <option value="Minor Nonconformity">Minor Nonconformity</option>
                            <option value="Conformity">Conformity</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-finding-desc" class="form-label fw-semibold text-dark small">Findings / Gaps Description</label>
                        <textarea class="form-control bg-light" id="edit-finding-desc" name="findings" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit-finding-deadline" class="form-label fw-semibold text-dark small">Deadline</label>
                        <input type="date" class="form-control bg-light" id="edit-finding-deadline" name="deadline" required />
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
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
