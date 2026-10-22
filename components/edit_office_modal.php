<div class="modal fade" id="editOfficeModal" tabindex="-1" aria-labelledby="editOfficeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header edit-header px-4 py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit me-2"></i>Edit Office</h5>
                <button type="button" class="btn-close btn-close-black" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-danger d-none" id="edit-office-error" role="alert"></div>
                <form id="edit-office-form" novalidate>
                    <input type="hidden" id="edit_office_id" name="office_id" />
                    <div class="mb-3">
                        <label for="edit_office_abbreviation" class="form-label fw-semibold text-dark small"><i class="fas fa-heading me-1 text-secondary"></i>Office Abbreviation</label>
                        <input type="text" class="form-control bg-light" id="edit_office_abbreviation" name="abbreviation" required />
                    </div>
                    <div class="mb-3">
                        <label for="edit_office_name" class="form-label fw-semibold text-dark small"><i class="fas fa-font me-1 text-secondary"></i>Office Name</label>
                        <input type="text" class="form-control bg-light" id="edit_office_name" name="name" required />
                    </div>
                    <div class="mb-3">
                        <label for="edit_officer_in_charge" class="form-label fw-semibold text-dark small"><i class="fas fa-user-tie me-1 text-secondary"></i>Officer in Charge</label>
                        <input type="text" class="form-control bg-light" id="edit_officer_in_charge" name="officer_in_charge" required />
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-warning edit-save rounded-3">
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
