<?php
$show_office_select = $show_office_select ?? false;
$office_list = $office_list ?? [];
$office_id = $office_id ?? null;

if (!isset($documents_list)) {
    require_once __DIR__ . '/../config/db.php';
    $db = getDBConnection();
    if ($show_office_select) {
        $doc_stmt = $db->query("SELECT d.id, d.office_id, d.filename, d.clause, c.title AS clause_title FROM documents d LEFT JOIN clauses c ON d.clause = c.clause_number ORDER BY d.created_at DESC");
        $documents_list = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($office_id) {
        $doc_stmt = $db->prepare("SELECT d.id, d.office_id, d.filename, d.clause, c.title AS clause_title FROM documents d LEFT JOIN clauses c ON d.clause = c.clause_number WHERE d.office_id = ? ORDER BY d.created_at DESC");
        $doc_stmt->execute([$office_id]);
        $documents_list = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $documents_list = [];
    }
}
?>
<div class="modal fade" id="addFindingModal" tabindex="-1" aria-labelledby="addFindingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg">
            <div class="modal-header header-custom px-4 py-3">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-exclamation-triangle me-2"></i>Add Office Findings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-danger d-none" id="add-finding-error" role="alert"></div>
                <form id="add-finding-form" novalidate>
                    <?php if ($show_office_select): ?>
                        <div class="mb-4">
                            <label for="finding_office" class="form-label fw-semibold text-dark small"><i class="fas fa-building me-1 text-secondary"></i>Target Office</label>
                            <select class="form-select bg-light" id="finding_office" name="office_id" required>
                                <?php foreach ($office_list as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['abbreviation']) ?> – <?= htmlspecialchars($o['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php elseif ($office_id): ?>
                        <input type="hidden" name="office_id" value="<?= $office_id ?>" id="finding_office_hidden" />
                    <?php endif; ?>
                    <div id="findings-container">
                        <div class="finding-row border p-3 rounded-3 mb-3 bg-white shadow-sm position-relative">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark small">
                                        <i class="fas fa-file-alt me-1 text-secondary"></i>Associated File
                                        <span class="badge bg-secondary-subtle text-secondary border ms-1 py-0.5 px-2 doc-locked-badge d-none" style="font-size:0.68rem;">
                                            <i class="fas fa-lock me-1 text-primary"></i>Locked File
                                        </span>
                                    </label>
                                    <select class="form-select bg-light finding-document-select" name="document_id[]">
                                        <option value="" data-clause="" data-clause-title="">-- Select Uploaded File (Optional) --</option>
                                        <?php foreach ($documents_list as $doc): ?>
                                            <option value="<?= $doc['id'] ?>" 
                                                    data-office="<?= $doc['office_id'] ?>" 
                                                    data-clause="Clause <?= $doc['clause'] ?>" 
                                                    data-clause-title="<?= htmlspecialchars($doc['clause_title'] ?? '') ?>">
                                                <?= htmlspecialchars($doc['filename']) ?> (Clause <?= $doc['clause'] ?>: <?= htmlspecialchars($doc['clause_title'] ?? 'General') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text small selected-clause-label text-muted mt-1" style="font-size: 0.75rem;">
                                        <i class="fas fa-tag me-1 text-primary"></i>ISO Clause: <span class="fw-semibold clause-name-text text-dark">None selected</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark small">Finding Type</label>
                                    <select class="form-select bg-light" name="finding_type[]" required>
                                        <option value="Major Nonconformity">Major Nonconformity</option>
                                        <option value="Minor Nonconformity">Minor Nonconformity</option>
                                        <option value="Conformity">Conformity</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark small">Deadline</label>
                                    <input type="date" class="form-control bg-light" name="deadline[]" required />
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold text-dark small">Findings / Gaps Description</label>
                                    <textarea class="form-control bg-light" name="findings[]" rows="2" placeholder="Describe the identified finding or gap details" required></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 py-2 rounded-3 mt-1 mb-3" id="btn-add-finding-row">
                        <i class="fas fa-plus me-1"></i> Add Another Finding Block
                    </button>

                    <div class="d-flex justify-content-end gap-2 mt-2">
                        <button type="submit" class="btn btn-custom text-white rounded-3">
                            <i class="fas fa-save me-2"></i>Save Findings
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

<script>
(function() {
    function updateClauseLabel(selectEl) {
        const row = selectEl.closest('.finding-row');
        if (!row) return;
        const clauseTextEl = row.querySelector('.clause-name-text');
        if (!clauseTextEl) return;
        const opt = selectEl.selectedOptions[0];
        if (opt && opt.value) {
            const clause = opt.getAttribute('data-clause') || '';
            const title = opt.getAttribute('data-clause-title') || '';
            clauseTextEl.innerText = clause + (title ? ' – ' + title : '');
            clauseTextEl.className = 'fw-semibold text-primary clause-name-text';
        } else {
            clauseTextEl.innerText = 'None selected';
            clauseTextEl.className = 'fw-semibold text-muted clause-name-text';
        }
    }

    function filterDocumentsByOffice(officeId) {
        document.querySelectorAll('.finding-document-select').forEach(select => {
            const currentVal = select.value;
            let currentValid = false;
            Array.from(select.options).forEach((opt, idx) => {
                if (idx === 0) {
                    opt.hidden = false;
                    return;
                }
                const docOffice = opt.getAttribute('data-office');
                if (!officeId || docOffice === String(officeId)) {
                    opt.hidden = false;
                    if (opt.value === currentVal) currentValid = true;
                } else {
                    opt.hidden = true;
                }
            });
            if (!currentValid && currentVal !== '') {
                select.value = '';
                updateClauseLabel(select);
            }
        });
    }

    let globalLockedDocId = null;

    window.openAddFindingModalWithDoc = function(opts) {
        const modalEl = document.getElementById('addFindingModal');
        if (!modalEl) return;
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        
        const err = document.getElementById('add-finding-error');
        if (err) err.classList.add('d-none');

        const isLocked = !!(opts && (opts.lockDoc || opts.docId));
        globalLockedDocId = isLocked && opts.docId ? String(opts.docId) : null;

        const officeSelect = document.getElementById('finding_office');
        if (officeSelect && opts && opts.officeId) {
            officeSelect.value = String(opts.officeId);
            filterDocumentsByOffice(opts.officeId);
        }

        const docSelects = document.querySelectorAll('.finding-document-select');
        const lockedBadges = document.querySelectorAll('.doc-locked-badge');

        docSelects.forEach(select => {
            if (opts && opts.docId) {
                select.value = String(opts.docId);
                updateClauseLabel(select);
            }

            if (isLocked) {
                select.style.pointerEvents = 'none';
                select.style.backgroundColor = '#e9ecef';
                select.style.color = '#1e293b';
                select.style.cursor = 'not-allowed';
                select.setAttribute('tabindex', '-1');
                select.dataset.locked = 'true';
            } else {
                select.style.pointerEvents = 'auto';
                select.style.backgroundColor = '';
                select.style.color = '';
                select.style.cursor = '';
                select.removeAttribute('tabindex');
                select.dataset.locked = 'false';
            }
        });

        lockedBadges.forEach(badge => {
            if (isLocked) {
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        });

        modal.show();
    };

    const modalElement = document.getElementById('addFindingModal');
    if (modalElement) {
        modalElement.addEventListener('hidden.bs.modal', function() {
            globalLockedDocId = null;
            document.querySelectorAll('.finding-document-select').forEach(select => {
                select.style.pointerEvents = 'auto';
                select.style.backgroundColor = '';
                select.style.color = '';
                select.style.cursor = '';
                select.removeAttribute('tabindex');
                select.dataset.locked = 'false';
            });
            document.querySelectorAll('.doc-locked-badge').forEach(badge => {
                badge.classList.add('d-none');
            });
        });
    }

    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('finding-document-select')) {
            updateClauseLabel(e.target);
        }
        if (e.target && e.target.id === 'finding_office') {
            filterDocumentsByOffice(e.target.value);
        }
    });

    const officeSelect = document.getElementById('finding_office');
    if (officeSelect) {
        filterDocumentsByOffice(officeSelect.value);
    }

    const addRowBtn = document.getElementById('btn-add-finding-row');
    if (addRowBtn) {
        addRowBtn.addEventListener('click', function() {
            const container = document.getElementById('findings-container');
            const firstRow = container.querySelector('.finding-row');
            if (!firstRow) return;
            const newRow = firstRow.cloneNode(true);
            
            newRow.querySelectorAll('input').forEach(input => input.value = '');
            newRow.querySelectorAll('textarea').forEach(ta => ta.value = '');
            
            const newDocSelect = newRow.querySelector('.finding-document-select');
            const newLockedBadge = newRow.querySelector('.doc-locked-badge');

            if (globalLockedDocId && newDocSelect) {
                newDocSelect.value = globalLockedDocId;
                updateClauseLabel(newDocSelect);
                newDocSelect.style.pointerEvents = 'none';
                newDocSelect.style.backgroundColor = '#e9ecef';
                newDocSelect.style.color = '#1e293b';
                newDocSelect.style.cursor = 'not-allowed';
                newDocSelect.setAttribute('tabindex', '-1');
                newDocSelect.dataset.locked = 'true';
                if (newLockedBadge) newLockedBadge.classList.remove('d-none');
            } else if (newDocSelect) {
                newDocSelect.selectedIndex = 0;
                newDocSelect.style.pointerEvents = 'auto';
                newDocSelect.style.backgroundColor = '';
                newDocSelect.style.color = '';
                newDocSelect.style.cursor = '';
                newDocSelect.removeAttribute('tabindex');
                newDocSelect.dataset.locked = 'false';
                if (newLockedBadge) newLockedBadge.classList.add('d-none');

                const clauseText = newRow.querySelector('.clause-name-text');
                if (clauseText) {
                    clauseText.innerText = 'None selected';
                    clauseText.className = 'fw-semibold text-muted clause-name-text';
                }
            }
            
            let closeBtn = newRow.querySelector('.btn-remove-finding-row');
            if (!closeBtn) {
                closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'btn-close position-absolute top-0 end-0 m-2 btn-remove-finding-row';
                closeBtn.style.fontSize = '0.75rem';
                newRow.appendChild(closeBtn);
            }
            
            closeBtn.addEventListener('click', function() {
                newRow.remove();
            });
            
            container.appendChild(newRow);
            
            const officeSelect = document.getElementById('finding_office');
            if (officeSelect) {
                filterDocumentsByOffice(officeSelect.value);
            }
        });
    }

    const form = document.getElementById('add-finding-form');
    if (form && !form.dataset.submitAttached) {
        form.dataset.submitAttached = "true";
        form.addEventListener('submit', function(e) {
            if (window.CUSTOM_ADD_FINDING_HANDLER) return;

            e.preventDefault();
            const errEl = document.getElementById('add-finding-error');
            if (errEl) errEl.classList.add('d-none');

            const formData = new FormData(form);
            formData.append('action', 'add_finding');

            const submitBtn = form.querySelector('button[type="submit"]');
            const origText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

            const postUrl = window.ADD_FINDING_POST_URL || window.location.href;

            fetch(postUrl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origText;

                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addFindingModal'));
                    if (modal) modal.hide();
                    form.reset();
                    if (window.showNotification) {
                        window.showNotification('Finding recorded successfully!', 'success');
                    }
                    setTimeout(() => {
                        location.reload();
                    }, 1200);
                } else {
                    if (errEl) {
                        errEl.innerText = data.message || 'Failed to record finding.';
                        errEl.classList.remove('d-none');
                    }
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origText;
                if (errEl) {
                    errEl.innerText = 'Network error. Please try again.';
                    errEl.classList.remove('d-none');
                }
            });
        });
    }
})();
</script>
