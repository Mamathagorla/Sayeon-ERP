<?= $this->extend('layouts/main') ?>

<?php
$status       = $record['status'];
$isClosed     = in_array($status, ['rejected', 'withdrawn'], true);
$profile      = $stored['profile'];
$submittedAt  = $profile['submitted_at'] ?? $record['verification_submitted_at'] ?? null;
$subtitle     = implode(' · ', array_filter([$record['company_name'] ?? '', $record['department_name'] ?? '', $record['designation'] ?? '']));
$statusLabel  = $stageLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
// A candidate who used the detailed form must be assessed before approval; the short legacy submissions are exempt.
$evaluationRequired = ! empty($profile['submitted_at']);
$reviewer = null;
if (! empty($record['verification_reviewed_by'])) {
    $reviewer = db_connect()->table('users')->select('name')->where('id', (int) $record['verification_reviewed_by'])->get()->getRow('name');
}
?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/onboarding/' . $record['id']) ?>" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to onboarding record</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    .sy-cd-stepper { display: flex; align-items: flex-start; overflow-x: auto; padding-bottom: 2px; }
    .sy-cd-stepper .st { flex: 1 0 74px; text-align: center; position: relative; font-size: .72rem; color: var(--sy-muted); }
    .sy-cd-stepper .st::before { content: ''; position: absolute; top: 11px; left: -50%; width: 100%; height: 2px; background: var(--sy-border); z-index: 0; }
    .sy-cd-stepper .st:first-child::before { display: none; }
    .sy-cd-stepper .st.done::before, .sy-cd-stepper .st.current::before { background: var(--sy-success); }
    .sy-cd-stepper .dot { position: relative; z-index: 1; width: 24px; height: 24px; border-radius: 50%; margin: 0 auto 4px; display: flex; align-items: center; justify-content: center; font-size: .66rem; font-weight: 700; background: var(--sy-surface, #fff); border: 2px solid var(--sy-border); }
    .sy-cd-stepper .st.done .dot { background: var(--sy-success); border-color: var(--sy-success); color: #fff; }
    .sy-cd-stepper .st.current .dot { border-color: var(--sy-accent-ink); color: var(--sy-accent-ink); box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
    .sy-cd-stepper .st.current, .sy-cd-stepper .st.done { color: var(--sy-ink); }
    .sy-cd-stepper .st.current .lbl { font-weight: 700; }
    .sy-cd-side { position: sticky; top: 76px; max-height: calc(100vh - 92px); overflow-y: auto; padding-right: 2px; }
    @media (max-width: 991.98px) { .sy-cd-side { position: static; max-height: none; overflow: visible; } }
    .sy-cd-criterion { display: grid; grid-template-columns: 1fr 86px; gap: 6px 8px; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--sy-border-soft); font-size: .84rem; }
    .sy-cd-criterion:last-child { border-bottom: 0; }
    .sy-cd-criterion .note { grid-column: 1 / -1; }
</style>

<!-- Header -->
<div class="sy-card mb-3" style="height:auto;overflow:visible">
    <div class="sy-card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4 class="mb-0" style="font-size:1.25rem;font-weight:700"><?= esc($record['candidate_name']) ?></h4>
                <div class="text-muted small"><?= esc($subtitle !== '' ? $subtitle : 'No target role set') ?> · <?= esc($reference) ?></div>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="badge bg-<?= $isClosed ? 'secondary' : ($status === 'approved' ? 'success' : 'warning text-dark') ?>"><?= esc($statusLabel) ?></span>
                <?php if ($profile && $profile['candidate_type']): ?><span class="badge bg-light text-dark border"><?= esc(\App\Modules\Onboarding\Services\CandidateFormService::TYPES[$profile['candidate_type']] ?? $profile['candidate_type']) ?></span><?php endif; ?>
                <span class="small text-muted"><?= $submittedAt ? 'Submitted ' . esc(erp_datetime($submittedAt)) : 'Not submitted yet' ?></span>
            </div>
        </div>
        <?php if (! $isClosed): ?>
        <div class="sy-cd-stepper mt-3">
            <?php foreach ($progressSteps as $i => $step): ?>
                <?php $done = $progressIndex >= 0 && $i < $progressIndex; $cur = $progressIndex === $i; ?>
                <div class="st <?= $done ? 'done' : '' ?> <?= $cur ? 'current' : '' ?>">
                    <span class="dot"><?php if ($done): ?><i class="fas fa-check"></i><?php else: ?><?= $i + 1 ?><?php endif; ?></span>
                    <span class="lbl"><?= esc($step) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($missing !== [] && $hasSubmission): ?>
<div class="alert alert-warning small">
    <strong><i class="fas fa-triangle-exclamation me-1"></i>Missing or incomplete information</strong>
    <ul class="mb-0 mt-1"><?php foreach ($missing as $item): ?><li><?= esc($item) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if (! $hasSubmission): ?>
<div class="alert alert-info small">The candidate has not submitted the form yet<?= $status === 'link_sent' ? ' (it is still with them)' : '' ?>. What they have saved so far is not shown until they submit.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <?php if ($hasSubmission): ?>
            <?= view('App\Modules\Onboarding\partials\candidate_data', ['record' => $record, 'stored' => $stored, 'group' => $group, 'documents' => $documents, 'mode' => 'hr']) ?>
        <?php else: ?>
            <div class="sy-card" style="height:auto"><div class="sy-card-body text-muted small">Nothing to review yet.</div></div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="sy-cd-side">
            <!-- Decision -->
            <div class="sy-card mb-3" style="height:auto">
                <div class="sy-card-header"><strong>HR decision</strong></div>
                <div class="sy-card-body">
                    <?php if ($status === 'details_submitted'): ?>
                        <p class="small text-muted">Review the submission, then start HR review to record a decision.</p>
                        <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/start-review') ?>" method="post"><?= csrf_field() ?><button class="btn btn-primary btn-sm w-100">Start HR Review</button></form>
                    <?php elseif ($canDecide): ?>
                        <form id="decisionForm" action="<?= site_url('hr/onboarding/' . $record['id'] . '/verification/review') ?>" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="decision" id="decisionInput" value="">
                            <label for="decisionNotes" class="form-label small mb-1">Note</label>
                            <textarea id="decisionNotes" name="notes" class="form-control form-control-sm" rows="4" maxlength="2000" placeholder="Required for Request Changes and Reject. For changes, say exactly what the candidate should fix — it is shown to them on their link."></textarea>
                            <?php if ($evaluationRequired && ! $hasAssessment): ?><div class="form-text text-warning"><i class="fas fa-circle-info me-1"></i>Approving needs a saved evaluation. Click Approve to jump to it.</div><?php endif; ?>
                            <div class="d-grid gap-2 mt-2">
                                <button type="button" class="btn btn-success btn-sm" data-decision="approved" data-confirm="Approve this candidate and move to the offer stage?" <?= $evaluationRequired && ! $hasAssessment ? 'data-needs-evaluation="1"' : '' ?>><i class="fas fa-check me-1"></i>Approve</button>
                                <button type="button" class="btn btn-outline-warning btn-sm" data-decision="correction_needed" data-need-note="1" data-confirm="Send this back to the candidate to correct and resubmit?"><i class="fas fa-rotate-left me-1"></i>Request Changes</button>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-decision="rejected" data-need-note="1" data-confirm="Reject this candidate? The recruitment process will be closed."><i class="fas fa-xmark me-1"></i>Reject</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="small">Current stage: <strong><?= esc($statusLabel) ?></strong></div>
                        <?php if (! empty($record['verification_reviewed_at'])): ?>
                            <div class="small text-muted mt-1">Last review: <?= esc(erp_datetime($record['verification_reviewed_at'])) ?><?= $reviewer ? ' by ' . esc($reviewer) : '' ?></div>
                        <?php endif; ?>
                        <?php if ($status === 'link_sent' && $record['verification_status'] === 'correction_needed' && $record['verification_notes']): ?>
                            <div class="small mt-2"><span class="text-muted">Changes requested:</span><br><?= nl2br(esc($record['verification_notes'])) ?></div>
                            <div class="small text-muted mt-1">Waiting for the candidate to update and resubmit through their link.</div>
                        <?php elseif ($status === 'rejected' && $record['verification_notes']): ?>
                            <div class="small mt-2"><span class="text-muted">Reason:</span><br><?= nl2br(esc($record['verification_notes'])) ?></div>
                        <?php elseif ($status === 'link_sent'): ?>
                            <div class="small text-muted mt-1">The candidate is still completing the form.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Evaluation -->
            <?php if ($hasSubmission): ?>
            <div class="sy-card" style="height:auto">
                <div class="sy-card-header"><strong>Evaluation</strong> <span class="text-muted small"><?= esc($group['name']) ?></span></div>
                <div class="sy-card-body">
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/evaluation') ?>" method="post">
                        <?= csrf_field() ?>
                        <?php if ($criteria !== []): ?>
                            <div class="small fw-semibold mb-1">Criteria <span class="text-muted fw-normal">(1 = weak, 5 = strong · optional · guidance only, not a pass mark)</span></div>
                            <?php
                            $savedByLabel = [];
                            foreach ($evaluation['criteria'] ?? [] as $c) {
                                $savedByLabel[$c['label']] = $c;
                            }
                            ?>
                            <?php foreach ($criteria as $c): ?>
                                <?php $saved = $savedByLabel[$c['label']] ?? ['score' => null, 'note' => '']; $sv = (string) old('score.' . $c['id'], $saved['score'] ?? '', false); ?>
                                <div class="sy-cd-criterion">
                                    <div><?= esc($c['label']) ?><?php if ($c['description']): ?><div class="text-muted" style="font-size:.74rem"><?= esc($c['description']) ?></div><?php endif; ?></div>
                                    <select name="score[<?= (int) $c['id'] ?>]" class="form-select form-select-sm" aria-label="Score for <?= esc($c['label'], 'attr') ?>">
                                        <option value="">—</option>
                                        <?php for ($n = 1; $n <= 5; $n++): ?><option value="<?= $n ?>" <?= $sv === (string) $n ? 'selected' : '' ?>><?= $n ?></option><?php endfor; ?>
                                    </select>
                                    <input type="text" name="score_note[<?= (int) $c['id'] ?>]" class="form-control form-control-sm note" maxlength="300" placeholder="Note (optional)" value="<?= esc((string) old('score_note.' . $c['id'], $saved['note'] ?? '', false)) ?>">
                                </div>
                            <?php endforeach; ?>
                            <hr>
                        <?php endif; ?>

                        <?php
                        $fields = [
                            'technical_assessment'  => ['Technical / role-specific assessment', 'How well do the skills, projects and answers match what this role needs?'],
                            'experience_assessment' => ['Relevant experience & qualifications', 'Education, internships or employment relevant to the position.'],
                            'strengths'             => ['Strengths', null],
                            'concerns'              => ['Concerns or missing information', 'Gaps, inconsistencies or things to clarify with the candidate.'],
                            'hr_comments'           => ['HR comments', null],
                            'decision_notes'        => ['Decision notes', 'Why you are leaning towards approving, requesting changes or rejecting.'],
                        ];
                        foreach ($fields as $name => [$label, $help]): ?>
                            <div class="mb-2">
                                <label class="form-label small mb-1" for="ev_<?= $name ?>"><?= esc($label) ?></label>
                                <textarea id="ev_<?= $name ?>" name="<?= $name ?>" class="form-control form-control-sm" rows="3" maxlength="4000"><?= esc((string) old($name, $evaluation[$name] ?? '', false)) ?></textarea>
                                <?php if ($help): ?><div class="form-text"><?= esc($help) ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-floppy-disk me-1"></i>Save evaluation</button>
                        <?php if ($evaluation): ?><div class="form-text mt-1">Last saved <?= esc(erp_datetime($evaluation['updated_at'] ?? $evaluation['created_at'])) ?><?= ! empty($evaluation['evaluator_name']) ? ' by ' . esc($evaluation['evaluator_name']) : '' ?>.</div><?php endif; ?>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var form = document.getElementById('decisionForm');
    if (!form) { return; }
    var input = document.getElementById('decisionInput'), notes = document.getElementById('decisionNotes');
    var busy = false;
    [].forEach.call(form.querySelectorAll('[data-decision]'), function (btn) {
        btn.addEventListener('click', function () {
            if (busy) { return; }
            if (btn.getAttribute('data-needs-evaluation')) {
                var target = document.getElementById('ev_technical_assessment');
                alert('Before approving, record your evaluation of this candidate (at least the technical / role-specific assessment) and click "Save evaluation". Then press Approve again.');
                if (target) { target.scrollIntoView({ behavior: 'smooth', block: 'center' }); target.focus(); target.classList.add('is-invalid'); }
                return;
            }
            if (btn.getAttribute('data-need-note') && notes.value.trim().length < 5) {
                notes.classList.add('is-invalid'); notes.focus();
                alert('Please add a note (at least a few words) explaining this decision.');
                return;
            }
            notes.classList.remove('is-invalid');
            if (!confirm(btn.getAttribute('data-confirm'))) { return; }
            busy = true; input.value = btn.getAttribute('data-decision');
            [].forEach.call(form.querySelectorAll('button'), function (b) { b.disabled = true; });
            form.submit();
        });
    });
})();
</script>
<?= $this->endSection() ?>
