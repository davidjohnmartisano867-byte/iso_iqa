<?php
$show_office_select = $show_office_select ?? false;
$office_list = $office_list ?? [];
$clause_list = $clause_list ?? [];
?>
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-labelledby="uploadDocModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header header-custom px-4 py-3">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-cloud-upload-alt me-2"></i>Upload Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-danger d-none" id="upload-doc-error" role="alert"></div>
                <form id="upload-doc-form" enctype="multipart/form-data" novalidate>
                    <?php if ($show_office_select): ?>
                        <div class="mb-3">
                            <label for="upload_office" class="form-label fw-semibold text-dark small"><i class="fas fa-building me-1 text-secondary"></i>Target Office</label>
                            <select class="form-select bg-light" id="upload_office" name="office_id" required>
                                <?php foreach ($office_list as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['abbreviation']) ?> – <?= htmlspecialchars($o['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="upload_clause" class="form-label fw-semibold text-dark small"><i class="fas fa-list me-1 text-secondary"></i>Target ISO Clause</label>
                        <select class="form-select bg-light" id="upload_clause" name="clause" required>
                            <option value="auto" selected>✨ Auto-Detect with Gemini AI</option>
                            <?php foreach ($clause_list as $c): ?>
                                <option value="<?= $c['clause_number'] ?>">Clause <?= $c['clause_number'] ?>: <?= htmlspecialchars($c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="document_file" class="form-label fw-semibold text-dark small"><i class="fas fa-file me-1 text-secondary"></i>Select Document Files</label>
                        <input type="file" class="form-control bg-light" id="document_file" name="document_file[]" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp" multiple required />
                        <div class="form-text small">Accepted formats: PDF, Word, Excel, Images (max 10MB). You can select multiple files!</div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" id="upload-doc-submit-btn" class="btn btn-custom text-white rounded-3">
                            <i class="fas fa-upload me-2"></i>Upload File
                        </button>
                        <button type="button" id="upload-doc-cancel-btn" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
