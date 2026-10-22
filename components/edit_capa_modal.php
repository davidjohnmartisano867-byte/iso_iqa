<div class="modal fade" id="editCapaModal" tabindex="-1" aria-labelledby="editCapaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg">
            <div class="modal-header edit-header px-4 py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit me-2"></i>Edit CAPA Details</h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-danger d-none" id="edit-capa-error" role="alert"></div>
                <form id="edit-capa-form" novalidate>
                    <input type="hidden" name="id" id="edit-capa-id" />
                    <input type="hidden" name="finding_id" id="edit-capa-finding-id" />

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="edit-root_cause" class="form-label fw-semibold text-dark small mb-0"><i class="fas fa-search me-1 text-secondary"></i>Root Cause Analysis</label>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-link text-primary p-0 d-none text-decoration-none fw-semibold" id="btn-toggle-edit-root-suggestions" style="font-size: 0.74rem;">
                                    <i class="fas fa-list-ul me-1"></i>Reopen suggestions
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-2.5 py-1 fw-bold d-flex align-items-center gap-1.5" id="btn-ai-suggest-edit-capa" style="font-size: 0.72rem; border: 1px solid #7c3aed; color: #7c3aed; background: transparent; transition: all 0.2s ease;">
                                    <i class="fas fa-wand-magic-sparkles"></i> AI Re-analyze
                                </button>
                            </div>
                        </div>

                        <div id="edit_root_cause_suggestions_wrapper" class="mb-2 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label text-primary small fw-semibold mb-0" style="font-size: 0.78rem;">
                                    <i class="fas fa-magic me-1 text-warning"></i>Suggested Root Causes (Click an option to apply):
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-2" id="btn-close-edit-root-suggestions" title="Hide suggestions" style="font-size: 0.7rem;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="list-group rounded-3 border bg-white shadow-sm" id="edit_root_cause_suggestions_list" style="max-height: 200px; overflow-y: auto;">
                            </div>
                        </div>

                        <textarea class="form-control bg-light" id="edit-root_cause" name="root_cause" rows="2" placeholder="Specify root cause..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="edit-corrective_actions" class="form-label fw-semibold text-dark small mb-0"><i class="fas fa-tools me-1 text-secondary"></i>Corrective Actions (CAPAs)</label>
                            <button type="button" class="btn btn-sm btn-link text-primary p-0 d-none text-decoration-none fw-semibold" id="btn-toggle-edit-capa-suggestions" style="font-size: 0.74rem;">
                                <i class="fas fa-list-ul me-1"></i>Reopen suggestions
                            </button>
                        </div>

                        <div id="edit_capa_suggestions_wrapper" class="mb-2 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label text-primary small fw-semibold mb-0" style="font-size: 0.78rem;">
                                    <i class="fas fa-magic me-1 text-warning"></i>Suggested Corrective Actions (Click an option to apply):
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-2" id="btn-close-edit-capa-suggestions" title="Hide suggestions" style="font-size: 0.7rem;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="list-group rounded-3 border bg-white shadow-sm" id="edit_capa_suggestions_list" style="max-height: 200px; overflow-y: auto;">
                            </div>
                        </div>

                        <textarea class="form-control bg-light" id="edit-corrective_actions" name="corrective_actions" rows="3" placeholder="Specify corrective and preventive steps..." required></textarea>
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
