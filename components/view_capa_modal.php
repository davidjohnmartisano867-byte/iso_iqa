<div class="modal fade" id="viewCapaModal" tabindex="-1" aria-labelledby="viewCapaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
            <div class="modal-header bg-primary px-4 py-3 text-white border-bottom-0" style="background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;">
                <h5 class="modal-title fw-bold"><i class="fas fa-tools me-2"></i>Corrective Action Plan (CAPA)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="mb-3">
                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-search text-primary me-1"></i>Root Cause Analysis</h6>
                    <div class="p-3 bg-white rounded-3 border shadow-sm" style="white-space: pre-wrap; font-size: 0.9rem;" id="view-capa-root-cause"></div>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-tools text-success me-1"></i>Corrective & Preventive Actions</h6>
                    <div class="p-3 bg-white rounded-3 border shadow-sm" style="white-space: pre-wrap; font-size: 0.9rem;" id="view-capa-content"></div>
                </div>
            </div>
            <div class="modal-footer border-top-0 bg-light px-4 py-3 d-flex justify-content-end gap-1">
                <button type="button" class="btn btn-success rounded-3 text-white d-none" id="view-capa-approve-btn" style="background: linear-gradient(135deg, #10b981, #059669) !important; border: none;">
                    <i class="fas fa-check-circle me-1"></i>Approve
                </button>
                <button type="button" class="btn btn-danger rounded-3 text-white d-none" id="view-capa-reject-btn" style="background: linear-gradient(135deg, #ef4444, #dc2626) !important; border: none;">
                    <i class="fas fa-times-circle me-1"></i>Reject
                </button>
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>
