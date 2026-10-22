<?php
$office_list = $office_list ?? [];
?>
<div class="modal fade" id="addProcessModal" tabindex="-1" aria-labelledby="addProcessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header header-custom px-4 py-3">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-project-diagram me-2"></i>Add Office Process</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-danger d-none" id="add-process-error" role="alert"></div>
                <form id="add-process-form" enctype="multipart/form-data" novalidate>
                    <div class="mb-3">
                        <label for="process_office" class="form-label fw-semibold text-dark small"><i class="fas fa-building me-1 text-secondary"></i>Target Office</label>
                        <select class="form-select bg-light" id="process_office" name="office_id" required>
                            <?php foreach ($office_list as $o): ?>
                                <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['abbreviation']) ?> – <?= htmlspecialchars($o['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="process_name" class="form-label fw-semibold text-dark small"><i class="fas fa-cog me-1 text-secondary"></i>Process Name</label>
                        <input type="text" class="form-control bg-light" id="process_name" name="process_name" placeholder="e.g. Student Registration System" required />
                    </div>
                    <div class="mb-4">
                        <label for="document_file" class="form-label fw-semibold text-dark small"><i class="fas fa-file-upload me-1 text-secondary"></i>Upload Supporting Documents (Optional)</label>
                        <input type="file" class="form-control bg-light" id="document_file" name="document_file[]" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp" multiple />
                        <div class="form-text small">Accepted formats: PDF, Word, Excel, Images (max 10MB). You can select multiple files! Uploaded files will be **automatically sorted by Gemini AI**!</div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-custom py-2 fw-semibold text-white rounded-3">
                            <i class="fas fa-plus me-2"></i>Add Process
                        </button>
                        <button type="button" class="btn btn-secondary py-2 fw-semibold rounded-3" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
