<?php
$office_list = $office_list ?? [];
?>
<div class="modal fade" id="assignOfficesModal" tabindex="-1" aria-labelledby="assignOfficesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header header-custom px-4 py-3">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-tasks me-2"></i>Assign Offices</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-danger d-none" id="assign-offices-error" role="alert"></div>
                <form id="assign-offices-form" novalidate>
                    <input type="hidden" id="assign_auditor_id" name="auditor_id" />
                    <div class="mb-3 text-start">
                        <label for="assign_auditor_activity" class="form-label fw-semibold text-dark small"><i class="fas fa-tasks me-1 text-secondary"></i>Activities</label>
                        <select class="form-select bg-light" id="assign_auditor_activity" name="activity" required>
                            <option value="Internal Audit" selected>Internal Audit</option>
                            <option value="External Audit">External Audit</option>
                            <option value="Follow-up Audit">Follow-up Audit</option>
                        </select>
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label fw-semibold text-dark small"><i class="fas fa-building me-1 text-secondary"></i>Assigned Offices</label>
                        <p class="text-muted small mb-2">Select the offices this auditor is assigned to audit. You can select multiple offices.</p>
                        <div class="border rounded-3 p-3 bg-light overflow-auto" style="max-height: 240px;" id="offices-checkbox-list">
                            <?php foreach ($office_list as $o): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input office-checkbox" type="checkbox" name="office_ids[]" value="<?= $o['id'] ?>" id="assign-off-<?= $o['id'] ?>">
                                    <label class="form-check-label fw-medium text-dark small" for="assign-off-<?= $o['id'] ?>">
                                        <span class="badge bg-primary-subtle text-primary me-2" style="background: rgba(37,99,235,0.1) !important; color: #2563eb !important;"><?= htmlspecialchars($o['abbreviation']) ?></span>
                                        <?= htmlspecialchars($o['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-custom text-white rounded-3">
                            <i class="fas fa-save me-2"></i>Save Assignments
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
