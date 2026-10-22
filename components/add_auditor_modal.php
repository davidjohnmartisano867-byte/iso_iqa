<div class="modal fade" id="addAuditorModal" tabindex="-1" aria-labelledby="addAuditorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header header-custom px-4 py-3">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-user-plus me-2"></i>Add Auditor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-danger d-none" id="add-auditor-error" role="alert"></div>
                <form id="add-auditor-form" novalidate>
                    <div class="mb-4">
                        <label for="auditor_name" class="form-label fw-semibold text-dark small"><i class="fas fa-user me-1 text-secondary"></i>Auditor Full Name</label>
                        <input type="text" class="form-control bg-light" id="auditor_name" name="auditor_name" placeholder="e.g. Dr. Helen Doe" required />
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-custom text-white rounded-3">
                            <i class="fas fa-plus me-2"></i>Add Auditor
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
