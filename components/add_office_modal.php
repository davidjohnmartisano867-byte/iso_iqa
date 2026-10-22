<div class="modal fade" id="addOfficeModal" tabindex="-1" aria-labelledby="addOfficeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header header-custom px-4 py-3">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-building me-2"></i>Add New Office</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-danger d-none" id="add-office-error" role="alert"></div>
                <form id="add-office-form" novalidate>
                    <div class="mb-3">
                        <label for="office_abbreviation" class="form-label fw-semibold text-dark small"><i class="fas fa-heading me-1 text-secondary"></i>Office Abbreviation</label>
                        <input type="text" class="form-control bg-light" id="office_abbreviation" name="abbreviation" placeholder="e.g. OIC-IQA" required />
                    </div>
                    <div class="mb-3">
                        <label for="office_name" class="form-label fw-semibold text-dark small"><i class="fas fa-font me-1 text-secondary"></i>Office Name</label>
                        <input type="text" class="form-control bg-light" id="office_name" name="name" placeholder="e.g. Office of the Internal Quality Assurance" required />
                    </div>
                    <div class="mb-3">
                        <label for="officer_in_charge" class="form-label fw-semibold text-dark small"><i class="fas fa-user-tie me-1 text-secondary"></i>Officer in Charge</label>
                        <input type="text" class="form-control bg-light" id="officer_in_charge" name="officer_in_charge" placeholder="Enter officer in charge name..." required />
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-custom text-white rounded-3">
                            <i class="fas fa-plus me-2"></i>Add Office
                        </button>
                        <button type="button" class="btn btn-secondary text-white rounded-3" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
