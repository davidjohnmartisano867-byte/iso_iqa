<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header edit-header px-4 py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-user-pen me-2"></i>Edit User Account</h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-danger d-none" id="edit-user-error" role="alert"></div>
                <form id="edit-user-form" novalidate>
                    <input type="hidden" id="edit_user_id" name="user_id" />
                    <div class="mb-3">
                        <label for="edit_name" class="form-label fw-semibold text-dark small">Full Name</label>
                        <input type="text" class="form-control bg-light" id="edit_name" name="name" placeholder="Enter full name" />
                    </div>
                    <div class="mb-3">
                        <label for="edit_username" class="form-label fw-semibold text-dark small">Username</label>
                        <input type="text" class="form-control bg-light" id="edit_username" name="username" required />
                    </div>
                    <div class="mb-4">
                        <label for="edit_password" class="form-label fw-semibold text-dark small">New Password <span class="text-muted fw-normal">(Leave blank to keep current)</span></label>
                        <input type="password" class="form-control bg-light" id="edit_password" name="password" placeholder="Enter new password to change" />
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-warning edit-save text-dark rounded-3">
                            <i class="fas fa-save me-2"></i>Update User
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
