<?php
$findings_dropdown = $findings_dropdown ?? $global_findings ?? $findings_list ?? [];
$show_ai_suggest = $show_ai_suggest ?? true;

$filtered_findings = [];
foreach ($findings_dropdown as $gf) {
    if (isset($gf['capas']) && is_array($gf['capas'])) {
        $has_submitted_capa = false;
        foreach ($gf['capas'] as $c) {
            if (!empty($c['id'])) {
                $has_submitted_capa = true;
                break;
            }
        }
        if ($has_submitted_capa) {
            continue;
        }
    }
    if (!empty($gf['has_capa']) || (!empty($gf['capas_count']) && $gf['capas_count'] > 0)) {
        continue;
    }
    $filtered_findings[] = $gf;
}
$findings_dropdown = $filtered_findings;
?>
<div class="modal fade" id="addCapaModal" tabindex="-1" aria-labelledby="addCapaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-primary px-4 py-3" style="background: linear-gradient(135deg, #2563eb, #1d4ed8) !important; border-bottom: none;">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-tools me-2"></i>Add Corrective Action (CAPA)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-danger d-none" id="add-capa-error" role="alert"></div>
                <form id="add-capa-form" novalidate>
                    <div class="mb-3">
                        <label for="capa_finding" class="form-label fw-semibold text-dark small"><i class="fas fa-exclamation-circle me-1 text-secondary"></i>Target Finding</label>
                        <select class="form-select bg-light" id="capa_finding" name="finding_id" required>
                            <option value="" selected disabled>-- Select Target Finding --</option>
                            <?php foreach ($findings_dropdown as $gf): ?>
                                <option value="<?= $gf['id'] ?>" class="finding-opt-<?= $gf['id'] ?>">
                                    <?= !empty($gf['abbreviation']) ? '[' . htmlspecialchars($gf['abbreviation']) . '] ' : '' ?><?= htmlspecialchars($gf['finding_type']) ?> – <?= htmlspecialchars(substr($gf['findings'], 0, 60)) ?>...
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="root_cause" class="form-label fw-semibold text-dark small mb-0"><i class="fas fa-search me-1 text-secondary"></i>Root Cause Analysis</label>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-link text-primary p-0 d-none text-decoration-none fw-semibold" id="btn-toggle-root-suggestions" style="font-size: 0.74rem;">
                                    <i class="fas fa-list-ul me-1"></i>Reopen suggestions
                                </button>
                                <?php if ($show_ai_suggest): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-2.5 py-1 fw-bold d-flex align-items-center gap-1.5" id="btn-ai-suggest-capa" style="font-size: 0.72rem; border: 1px solid #7c3aed; color: #7c3aed; background: transparent; transition: all 0.2s ease;">
                                    <i class="fas fa-wand-magic-sparkles"></i> AI Re-analyze
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="root_cause_suggestions_wrapper" class="mb-2 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label text-primary small fw-semibold mb-0" style="font-size: 0.78rem;">
                                    <i class="fas fa-magic me-1 text-warning"></i>Suggested Root Causes (Click an option to apply):
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-2" id="btn-close-root-suggestions" title="Hide suggestions" style="font-size: 0.7rem;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="list-group rounded-3 border bg-white shadow-sm" id="root_cause_suggestions_list" style="max-height: 200px; overflow-y: auto;">
                            </div>
                        </div>

                        <textarea class="form-control bg-light" id="root_cause" name="root_cause" rows="2" placeholder="Select a target finding above for AI suggestions, or type custom root cause..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="corrective_actions" class="form-label fw-semibold text-dark small mb-0"><i class="fas fa-tools me-1 text-secondary"></i>Corrective Actions (CAPAs)</label>
                            <button type="button" class="btn btn-sm btn-link text-primary p-0 d-none text-decoration-none fw-semibold" id="btn-toggle-capa-suggestions" style="font-size: 0.74rem;">
                                <i class="fas fa-list-ul me-1"></i>Reopen suggestions
                            </button>
                        </div>

                        <div id="capa_suggestions_wrapper" class="mb-2 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label text-primary small fw-semibold mb-0" style="font-size: 0.78rem;">
                                    <i class="fas fa-magic me-1 text-warning"></i>Suggested Corrective Actions (Click an option to apply):
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-2" id="btn-close-capa-suggestions" title="Hide suggestions" style="font-size: 0.7rem;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="list-group rounded-3 border bg-white shadow-sm" id="capa_suggestions_list" style="max-height: 200px; overflow-y: auto;">
                            </div>
                        </div>

                        <textarea class="form-control bg-light" id="corrective_actions" name="corrective_actions" rows="3" placeholder="Select a root cause above to generate tailored corrective action suggestions, or type custom steps..." required></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="submit" class="btn btn-primary text-white rounded-3" style="background: linear-gradient(135deg, #2563eb, #1d4ed8) !important; border: none;">
                            <i class="fas fa-plus me-2"></i>Save CAPA
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
