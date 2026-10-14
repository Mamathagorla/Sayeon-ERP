<?= $this->extend('layouts/main') ?>

<?php
$isWithdrawn  = in_array($record['status'], ['withdrawn', 'rejected'], true);

// Checklist completion — plain counts over the tasks already loaded, no
// new query. Final status is derived purely from the existing record
// status, not a new field.
$allTasks      = array_merge(...array_values($tasksByPhase ?: [[]]));
$totalTasks    = count($allTasks);
$doneTasks     = count(array_filter($allTasks, static fn (array $t) => $t['status'] === 'completed'));
$completionPct = $totalTasks > 0 ? (int) round($doneTasks / $totalTasks * 100) : 0;

// "Next Action" — the first task that isn't done/skipped yet, in the
// same phase order the checklist renders (Pre-Joining first). Also
// doubles as which task row in the flat checklist below gets the
// "current" (●) marker — everything before it is done, everything
// after is upcoming.
$nextTask = null;
foreach ($allTasks as $t) {
    if (! in_array($t['status'], ['completed', 'skipped'], true)) {
        $nextTask = $t;
        break;
    }
}
$workflowStages = ['documents_pending', 'bgv', 'hr_verification', 'ready_to_join', 'joining', 'joined', 'induction', 'probation'];
$workflowTask = in_array($record['status'], $workflowStages, true) ? $nextTask : null;
$workflowTaskCanAct = $workflowTask && ($isOwner || $myRole === $workflowTask['owner_role']);
$nextAction = match ($record['status']) {
    'candidate' => 'Select Candidate',
    'selected_for_onboarding', 'selected' => 'Send Onboarding Link',
    'link_sent' => 'Resend / View Link',
    'details_submitted' => 'Review Candidate',
    'hr_review' => 'Review Candidate',
    'approved' => 'Send offer letter',
    'offer_sent' => 'Await candidate response',
    'offer_accepted' => $record['joining_letter_sent_at'] ? 'Start joining checklist' : 'Send joining letter',
    'joining', 'joined', 'induction', 'probation', 'ready_to_join', 'documents_pending', 'bgv', 'hr_verification' => 'Complete Checklist',
    'confirmed' => 'View Employee',
    'rejected' => 'Recruitment closed',
    'withdrawn' => 'Recruitment withdrawn',
    default => $workflowTaskCanAct ? 'Complete: ' . $workflowTask['title'] : ($workflowTask ? 'Await ' . $ownerRoleLabels[$workflowTask['owner_role']] : ($nextStatus ? 'Advance to ' . ($stageLabels[$nextStatus] ?? 'next stage') : 'Workflow complete')),
};
$finalStatus = match (true) {
    $record['status'] === 'rejected'      => 'Rejected',
    $isWithdrawn                          => 'Withdrawn',
    $record['status'] === 'joined'        => 'Employee',
    $record['status'] === 'confirmed'     => 'Onboarded',
    $record['status'] === 'ready_to_join' => 'Ready to Join',
    default                                => 'In Progress',
};
$finalStatusColor = match ($finalStatus) {
    'Onboarded'     => ['fg' => '#16a34a', 'bg' => '#e8f8ee'],
    'Ready to Join' => ['fg' => '#2563eb', 'bg' => '#eaf1fe'],
    'Withdrawn', 'Rejected' => ['fg' => '#64748b', 'bg' => '#eef1f4'],
    default         => ['fg' => '#d97706', 'bg' => '#fef3e2'],
};

$offerStatusLabel = $record['offer_accepted_at'] ? 'Accepted' : ($record['offer_sent_at'] ? 'Sent' : 'Not sent');
$joiningLetterSent = $record['joining_letter_sent_at'] !== null;
// The joining letter follows the candidate's acceptance and can be (re)sent until they have confirmed.
$canSendJoining   = $isOwner && $record['offer_accepted_at'] !== null && ! in_array($record['status'], ['withdrawn', 'rejected', 'confirmed'], true);
$offerNeedsDetails = $record['offered_ctc'] === null || empty($record['joining_date']);
$isOverdue = static fn (array $t): bool => $t['due_date'] && $t['status'] !== 'completed' && $t['due_date'] < date('Y-m-d');

$progressSteps = \App\Modules\Onboarding\Models\OnboardingRecordModel::PROGRESS_STEPS;
$progressIndex = \App\Modules\Onboarding\Models\OnboardingRecordModel::progressIndexFor($record['status']);
$progressDoneCount = $progressIndex < 0 ? 0 : min($progressIndex, count($progressSteps));
$currentStageLabel = $stageLabels[$record['status']] ?? ucfirst(str_replace('_', ' ', $record['status']));
$statusBlurb = match (true) {
    $record['status'] === 'rejected' => 'This candidate was rejected. The recruitment process is closed.',
    $isWithdrawn => 'This candidate was withdrawn from the recruitment process.',
    $record['status'] === 'confirmed' => 'Onboarding is complete.',
    $progressIndex === 6 => $doneTasks . ' of ' . $totalTasks . ' checklist tasks completed.',
    default => 'Stage ' . ($progressIndex + 1) . ' of ' . count($progressSteps) . ' in the onboarding workflow.',
};
$offerAvailable = $record['offer_sent_at'] || $progressIndex >= 4;
$canViewLetters = $isOwner || $isSelf;
$inWorkflow = in_array($record['status'], $workflowStages, true) || $record['status'] === 'offer_accepted';
$subtitle = implode(' · ', array_filter([$record['company_name'] ?? '', $record['department_name'] ?? '', $record['designation'] ?? '']));
?>

<?= $this->section('content') ?>

<style>
    .sy-onb-quick { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
    .sy-onb-quick .label { font-size: .72rem; color: var(--sy-muted); margin-bottom: 2px; }
    .sy-onb-quick .value { font-size: .92rem; font-weight: 700; color: var(--sy-ink); }

    .sy-onb-check-row { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--sy-border-soft); font-size: .86rem; }
    .sy-onb-check-row:last-child { border-bottom: none; }
    .sy-onb-check-row .ico { flex: none; width: 18px; text-align: center; font-size: .8rem; }
    .sy-onb-check-row .ico.done { color: var(--sy-success); }
    .sy-onb-check-row .ico.current { color: var(--sy-accent-ink); }
    .sy-onb-check-row .ico.upcoming { color: var(--sy-muted-soft); }
    .sy-onb-check-row .title.done { color: var(--sy-muted); text-decoration: line-through; }

    .sy-onb-phase-block + .sy-onb-phase-block { margin-top: 22px; }
    .sy-onb-phase-title { display: flex; justify-content: space-between; align-items: center; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--sy-ink); margin-bottom: 10px; padding-bottom: 6px; border-bottom: 2px solid var(--sy-border); }
    .sy-onb-phase-title .count { font-weight: 600; color: var(--sy-muted); text-transform: none; letter-spacing: normal; }
    .sy-onb-task-row { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--sy-border-soft); }
    .sy-onb-task-row:last-child { border-bottom: none; }
    .sy-onb-task-check { flex-shrink: 0; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .65rem; margin-top: 2px; }
    .sy-onb-task-check.done { background: var(--sy-success); color: #fff; }
    .sy-onb-task-check.pending { border: 2px solid var(--sy-border); }
    .sy-onb-task-meta { font-size: .74rem; color: var(--sy-muted); }
    .sy-onb-task-meta .due.overdue { color: var(--sy-danger); font-weight: 600; }

    .sy-onb-activity-row { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--sy-border-soft); }
    .sy-onb-activity-row:last-child { border-bottom: none; }
    .sy-onb-activity-row .when { font-size: .72rem; color: var(--sy-muted); white-space: nowrap; }

     .sy-onb-tabs { flex-wrap: nowrap; overflow-x: auto; }
    .sy-onb-head-top { font-size: .8rem; margin-bottom: 8px; }
    .sy-onb-head h4 { margin: 0; font-size: 1.25rem; font-weight: 700; }
    .sy-onb-head .sub { color: var(--sy-muted); font-size: .86rem; margin-top: 2px; }
    .sy-onb-stepper { display: flex; align-items: flex-start; overflow-x: auto; padding-bottom: 2px; }
    .sy-onb-stepper .st { flex: 1 0 74px; text-align: center; position: relative; font-size: .72rem; color: var(--sy-muted); }
    .sy-onb-stepper .st::before { content: ''; position: absolute; top: 11px; left: -50%; width: 100%; height: 2px; background: var(--sy-border); z-index: 0; }
    .sy-onb-stepper .st:first-child::before { display: none; }
    .sy-onb-stepper .st.done::before, .sy-onb-stepper .st.current::before { background: var(--sy-success); }
    .sy-onb-stepper .dot { position: relative; z-index: 1; width: 24px; height: 24px; border-radius: 50%; margin: 0 auto 4px; display: flex; align-items: center; justify-content: center; font-size: .66rem; font-weight: 700; background: var(--sy-surface, #fff); border: 2px solid var(--sy-border); }
    .sy-onb-stepper .st.done .dot { background: var(--sy-success); border-color: var(--sy-success); color: #fff; }
    .sy-onb-stepper .st.current .dot { border-color: var(--sy-accent-ink); color: var(--sy-accent-ink); box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
    .sy-onb-stepper .st.current, .sy-onb-stepper .st.done { color: var(--sy-ink); }
    .sy-onb-stepper .st.current .lbl { font-weight: 700; }
    .sy-onb-summary { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 16px; align-items: start; }
    @media (max-width: 991.98px) { .sy-onb-summary { grid-template-columns: 1fr; } }
    .sy-onb-next .stage { font-size: 1.05rem; font-weight: 700; color: var(--sy-ink); }
    .sy-onb-next .blurb { font-size: .84rem; color: var(--sy-muted); margin-top: 2px; }
    .sy-onb-next .next { margin-top: 12px; padding: 10px 12px; border-radius: 8px; background: rgba(37,99,235,.08); font-size: .88rem; font-weight: 600; }
    .sy-onb-card-lift { box-shadow: 0 1px 2px rgba(16,24,40,.05); }
</style>

<!-- Header -->
<div class="sy-card sy-onb-card-lift sy-onb-head mb-3" id="candidate-overview" style="height:auto;overflow:visible">
    <div class="sy-card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div>
                    <h4><?= esc($record['candidate_name']) ?></h4>
                    <div class="sub"><?= $subtitle !== '' ? esc($subtitle) : 'No target role set' ?></div>
                </div>
                <span class="badge" style="background:<?= $finalStatusColor['bg'] ?>;color:<?= $finalStatusColor['fg'] ?>"><?= esc($finalStatus) ?></span>
            </div>
            <div class="d-flex gap-2">
                <?php if ($isOwner && ! empty($record['verification_submitted_at'])): ?>
                <a href="<?= site_url('hr/onboarding/' . $record['id'] . '/candidate-details') ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-id-card me-1"></i>View Candidate Details</a>
                <?php endif; ?>
                <?php if ($isOwner): ?>
                <a href="<?= site_url('hr/onboarding/' . $record['id'] . '/edit') ?>" class="btn btn-light btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
                <?php endif; ?>
                <?php
                    // A letter action is offered only when the candidate's company has an active template for it.
                    $docAvail        = \App\Modules\Document\Services\DocumentAvailability::shared();
                    $offerTpl        = $docAvail->has('offer_letter', (int) $record['company_id']);
                    $joiningTpl      = $docAvail->has('joining_letter', (int) $record['company_id']);
                    $showOfferItems  = $canViewLetters && $offerAvailable && $offerTpl;
                    $showLetterItems = $canViewLetters && ($record['joined_at'] || $joiningLetterSent) && $joiningTpl;
                    $missingLetters  = array_filter([
                        ($canViewLetters && $offerAvailable && ! $offerTpl) ? 'offer letter' : null,
                        ($canViewLetters && ($record['joined_at'] || $joiningLetterSent) && ! $joiningTpl) ? 'joining letter' : null,
                    ]);
                    $showWithdraw    = $isOwner && ! $isWithdrawn && $record['status'] !== 'confirmed';
                ?>
                <?php if ($showOfferItems || $showLetterItems || $isOwner || $missingLetters): ?>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($showOfferItems): ?>
                        <li><a class="dropdown-item" href="<?= site_url('hr/onboarding/' . $record['id'] . '/offer-letter') ?>" target="_blank" rel="noopener"><i class="fas fa-file-lines me-2 text-muted"></i>View Offer</a></li>
                        <li><a class="dropdown-item" href="<?= site_url('hr/onboarding/' . $record['id'] . '/offer-letter?print=1') ?>" target="_blank" rel="noopener"><i class="fas fa-print me-2 text-muted"></i>Download / Print Offer</a></li>
                        <?php endif; ?>
                        <?php if ($missingLetters): ?>
                        <li><span class="dropdown-item-text text-muted small"><i class="far fa-circle-question me-1"></i>No <?= esc(implode(' / ', $missingLetters)) ?> template is configured for this company.</span></li>
                        <?php if (can('document.edit')): ?><li><a class="dropdown-item small" data-erp-form="off" href="<?= site_url('documents/templates') ?>"><i class="fas fa-file-code me-2 text-muted"></i>Manage document templates</a></li><?php endif; ?>
                        <?php endif; ?>
                        <?= view('App\\Modules\\Document\\partials/doc_workflow_menu', ['workflow' => 'onboarding', 'companyId' => (int) $record['company_id'], 'context' => ['onboarding_record' => (int) $record['id']], 'except' => ['offer_letter', 'joining_letter'], 'showEmpty' => false]) ?>
                        <?php if ($showLetterItems): ?>
                        <li><a class="dropdown-item" href="<?= site_url('hr/onboarding/' . $record['id'] . '/joining-letter') ?>" target="_blank" rel="noopener"><i class="fas fa-file-signature me-2 text-muted"></i>View / Download / Print Joining Letter</a></li>
                        <?php endif; ?>
                        <?php if ($isOwner): ?>
                        <?php if ($showOfferItems || $showLetterItems): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                        <?php if ($showWithdraw): ?>
                        <li>
                            <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/withdraw') ?>" method="post" onsubmit="return confirm('Withdraw this candidate from the recruitment process?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-item text-warning"><i class="fas fa-ban me-2"></i>Withdraw Candidate</button>
                            </form>
                        </li>
                        <?php endif; ?>
                        <li>
                            <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Remove this onboarding record? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button>
                            </form>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Progress -->
<div class="sy-card sy-onb-card-lift mb-3" style="height:auto">
    <div class="sy-card-body">
        <div class="d-flex justify-content-between align-items-center small mb-2">
            <strong>Onboarding Progress</strong>
            <span class="text-muted"><?= $progressDoneCount ?>/<?= count($progressSteps) ?> stages completed<?= $totalTasks > 0 ? ' · ' . $completionPct . '% checklist' : '' ?></span>
        </div>
        <div class="sy-onb-stepper">
            <?php foreach ($progressSteps as $i => $step): ?>
                <?php $stepDone = $progressIndex >= 0 && $i < $progressIndex; $stepCurrent = $progressIndex === $i; ?>
                <div class="st <?= $stepDone ? 'done' : '' ?> <?= $stepCurrent ? 'current' : '' ?>">
                    <span class="dot"><?php if ($stepDone): ?><i class="fas fa-check"></i><?php else: ?><?= $i + 1 ?><?php endif; ?></span>
                    <span class="lbl"><?= esc($step) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<ul class="nav nav-tabs sy-onb-tabs" id="onbTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button">Overview</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-candidate-details" type="button">Candidate</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-documents" type="button">Documents<?= ($docCount = count($documents) + (int) ($record['offer_sent_at'] !== null) + (int) ($record['joining_letter_sent_at'] !== null)) > 0 ? ' (' . $docCount . ')' : '' ?></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-offer" type="button">Offer</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-checklist" type="button">Joining &amp; Checklist</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activity" type="button">Activity</button></li>
</ul>
<div class="tab-content pt-3">

    <!-- ============ OVERVIEW ============ -->
    <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
        <div class="sy-onb-summary">
            <div class="sy-card sy-onb-card-lift sy-onb-next" style="height:auto">
                <div class="sy-card-header"><strong>Current Status</strong></div>
                <div class="sy-card-body">
                    <div class="stage"><?= esc($currentStageLabel) ?></div>
                    <div class="blurb"><?= esc($statusBlurb) ?></div>
                    <div class="next"><i class="fas fa-arrow-right me-2 text-warning"></i>Next: <?= esc($nextAction) ?>
                        <?php if ($workflowTask): ?><div class="small fw-normal text-muted mt-1">Owner: <?= esc($ownerRoleLabels[$workflowTask['owner_role']]) ?><?= $workflowTask['due_date'] ? ' · Due ' . esc(erp_date($workflowTask['due_date'])) : '' ?></div><?php endif; ?>
                    </div>
                    <div class="d-flex gap-2 flex-wrap align-items-center mt-3">
                <?php if ($isOwner && $record['status'] === 'candidate'): ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/select') ?>" method="post"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-primary">Select Candidate</button></form>
                <?php elseif ($isOwner && in_array($record['status'], ['selected_for_onboarding', 'selected'], true)): ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/send-portal-link') ?>" method="post"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-primary">Send Link</button></form>
                <?php elseif ($isOwner && $record['status'] === 'link_sent'): ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/send-portal-link') ?>" method="post"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-primary">Resend Link</button></form>
                    <?php if ($portalUrl): ?><a class="btn btn-sm btn-outline-secondary" href="<?= esc($portalUrl) ?>" target="_blank" rel="noopener">View Link</a><?php endif; ?>
                <?php elseif ($isOwner && $record['status'] === 'details_submitted'): ?>
                    <a href="<?= site_url('hr/onboarding/' . $record['id'] . '/candidate-details') ?>" class="btn btn-sm btn-outline-primary">View Candidate Details</a>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/start-review') ?>" method="post"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-primary">Start HR Review</button></form>
                <?php elseif ($isOwner && $record['status'] === 'hr_review'): ?>
                    <a href="<?= site_url('hr/onboarding/' . $record['id'] . '/candidate-details') ?>" class="btn btn-sm btn-primary"><i class="fas fa-id-card me-1"></i>View Candidate Details</a>
                    <span class="small text-muted align-self-center">Review the submission and record your evaluation, then approve, request changes or reject.</span>
                <?php elseif ($isOwner && $record['status'] === 'approved'): ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/send-offer') ?>" method="post" onsubmit="return confirm('Send the offer letter to <?= esc($record['candidate_email'], 'js') ?>?');"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-paper-plane me-1"></i>Send Offer Letter</button></form>
                    <?php if ($offerNeedsDetails): ?><span class="small text-warning align-self-center"><i class="fas fa-circle-info me-1"></i>Set the offered CTC and joining date first (Edit) - the letter needs them.</span><?php endif; ?>
                <?php elseif ($isOwner && $record['status'] === 'offer_sent' && $record['offer_response'] === null): ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/send-offer') ?>" method="post"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-rotate me-1"></i>Resend Offer Link</button></form>
                    <span class="small text-muted align-self-center">Offer letter sent <?= esc(erp_date($record['offer_sent_at'])) ?> - waiting for the candidate to accept or decline.</span>
                <?php elseif ($isOwner && $record['status'] === 'offer_accepted'): ?>
                    <?php if ($joiningLetterSent): ?>
                        <span class="badge bg-success align-self-center"><i class="fas fa-check me-1"></i>Joining letter sent <?= esc(erp_date($record['joining_letter_sent_at'])) ?></span>
                        <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/send-joining-letter') ?>" method="post"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-rotate me-1"></i>Resend</button></form>
                    <?php else: ?>
                        <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/send-joining-letter') ?>" method="post" onsubmit="return confirm('Send the joining letter to <?= esc($record['candidate_email'], 'js') ?>?');"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-paper-plane me-1"></i>Send Joining Letter</button></form>
                        <?php if (empty($record['joining_date'])): ?><span class="small text-warning align-self-center"><i class="fas fa-circle-info me-1"></i>Set the joining date first (Edit).</span><?php endif; ?>
                    <?php endif; ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/status') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="status" value="documents_pending"><button type="submit" class="btn btn-sm btn-outline-primary">Start Checklist</button></form>
                <?php elseif ($record['status'] === 'confirmed' && $record['employee_profile_id']): ?>
                    <a href="<?= site_url('hr/employees/' . $record['employee_profile_id']) ?>" class="btn btn-sm btn-primary">View Employee</a>
                <?php elseif ($workflowTaskCanAct): ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/tasks/' . $workflowTask['id']) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="status" value="completed"><button type="submit" class="btn btn-sm btn-primary">Complete</button></form>
                <?php elseif ($isOwner && in_array($record['status'], $workflowStages, true) && ! $nextTask && $nextStatus): ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/status') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="status" value="<?= esc($nextStatus) ?>"><button type="submit" class="btn btn-sm btn-primary">Advance to <?= esc($stageLabels[$nextStatus]) ?></button></form>
                <?php endif; ?>
                <?php if ($inWorkflow): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-pf-tab="checklist"><i class="fas fa-list-check me-1"></i>Go to Joining &amp; Checklist</button>
                <?php endif; ?>
                    </div>
                    <?php if ($totalTasks > 0 && $inWorkflow): ?>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between small text-muted mb-1"><span>Checklist</span><span><?= $doneTasks ?>/<?= $totalTasks ?> · <?= $completionPct ?>%</span></div>
                        <div class="progress" style="height:6px;"><div class="progress-bar" role="progressbar" style="width:<?= $completionPct ?>%;background:var(--sy-accent-ink)" aria-valuenow="<?= $completionPct ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sy-card sy-onb-card-lift" style="height:auto">
                <div class="sy-card-header"><strong>Quick Info</strong></div>
                <div class="sy-card-body">
                    <div class="sy-pf-row"><span class="k">Company</span><span class="v"><?= esc($record['company_name']) ?></span></div>
                    <div class="sy-pf-row"><span class="k">Department</span><span class="v"><?= esc($record['department_name'] ?? '—') ?></span></div>
                    <div class="sy-pf-row"><span class="k">Manager</span><span class="v"><?= esc($record['manager_name'] ?? '—') ?></span></div>
                    <div class="sy-pf-row"><span class="k">Candidate Email</span><span class="v"><?= esc($record['candidate_email']) ?></span></div>
                    <div class="sy-pf-row"><span class="k">Joining Date</span><span class="v"><?= $record['joining_date'] ? esc(erp_date($record['joining_date'])) : '—' ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-candidate-details" role="tabpanel">
        <div class="sy-card" style="height:auto">
            <div class="sy-card-header"><strong>Candidate</strong><?php if ($isOwner && ! empty($record['verification_submitted_at'])): ?> <a href="<?= site_url('hr/onboarding/' . $record['id'] . '/candidate-details') ?>" class="small">View full submission &amp; evaluation</a><?php endif; ?></div>
            <div class="sy-card-body">
                <div class="row small">
                    <div class="col-md-4 mb-3"><span class="text-muted">Name</span><br><?= esc($record['candidate_name']) ?></div>
                    <div class="col-md-4 mb-3"><span class="text-muted">Email</span><br><?= esc($record['candidate_email']) ?></div>
                    <div class="col-md-4 mb-3"><span class="text-muted">Phone</span><br><?= esc($record['candidate_phone'] ?: '—') ?></div>
                    <div class="col-md-4 mb-3"><span class="text-muted">Date of Birth</span><br><?= $record['date_of_birth'] ? esc(erp_date($record['date_of_birth'])) : '—' ?></div>
                    <div class="col-md-4 mb-3"><span class="text-muted">Gender</span><br><?= esc($record['gender'] ?: '—') ?></div>
                    <div class="col-md-4 mb-3"><span class="text-muted">Highest Qualification</span><br><?= esc($record['highest_qualification'] ?: '—') ?></div>
                    <div class="col-md-4 mb-3"><span class="text-muted">Institution</span><br><?= esc($record['institution'] ?: '—') ?></div>
                    <div class="col-md-4 mb-3"><span class="text-muted">Graduation Year</span><br><?= esc($record['graduation_year'] ?: '—') ?></div>
                    <div class="col-md-4 mb-3"><span class="text-muted">Candidate Source</span><br><?= esc(ucwords(str_replace('_', ' ', $record['candidate_source'] ?? 'direct_hire'))) ?></div>
                    <?php if (! empty($record['source_detail'])): ?><div class="col-md-4 mb-3"><span class="text-muted">Source Details</span><br><?= esc($record['source_detail']) ?></div><?php endif; ?>
                    <div class="col-12 mb-2"><span class="text-muted">Address</span><br><?= esc(implode(', ', array_filter([$record['address_line1'], $record['address_line2'], $record['city'], $record['state'], $record['postal_code'], $record['country']])) ?: '—') ?></div>
                    <?php if ($record['verification_notes']): ?><div class="col-12"><span class="text-muted">Candidate / HR Note</span><br><?= esc($record['verification_notes']) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ FULL CHECKLIST (by phase, with actions) ============ -->
    <div class="tab-pane fade" id="tab-checklist" role="tabpanel">
        <div class="sy-card">
            <?php if ($isOwner): ?>
            <div class="sy-card-header d-flex justify-content-end">
                <a href="<?= site_url('hr/onboarding/phases') ?>" class="small"><i class="fas fa-diagram-project me-1"></i>Manage Phases</a>
            </div>
            <?php endif; ?>
            <div class="sy-card-body">
                <?php if ($totalTasks === 0): ?>
                    <p class="text-muted small mb-0">No checklist items.</p>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center small mb-1">
                        <span class="fw-semibold">Overall Progress: <?= $doneTasks ?>/<?= $totalTasks ?></span>
                        <span class="text-muted"><?= $completionPct ?>%</span>
                    </div>
                    <div class="progress mb-3" style="height:8px;">
                        <div class="progress-bar" role="progressbar" style="width:<?= $completionPct ?>%;background:var(--sy-accent-ink)" aria-valuenow="<?= $completionPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                    <?php foreach ($tasksByPhase as $phase => $tasks): ?>
                        <?php if (empty($tasks)) continue; ?>
                        <?php $phaseDone = count(array_filter($tasks, static fn (array $t) => $t['status'] === 'completed')); ?>
                        <div class="sy-onb-phase-block">
                            <div class="sy-onb-phase-title">
                                <span><?= esc(strtoupper($phase)) ?></span>
                                <span class="count"><?= $phaseDone ?>/<?= count($tasks) ?></span>
                            </div>
                            <?php foreach ($tasks as $task): ?>
                                <?php
                                    $canActTask = $isOwner || $myRole === $task['owner_role'];
                                    $overdueTask = $task['due_date'] && $task['status'] !== 'completed' && $task['due_date'] < date('Y-m-d');
                                ?>
                                <div class="sy-onb-task-row">
                                    <span class="sy-onb-task-check <?= $task['status'] === 'completed' ? 'done' : 'pending' ?>"><?= $task['status'] === 'completed' ? '<i class="fas fa-check"></i>' : '' ?></span>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div class="fw-semibold small"><?= esc($task['title']) ?></div>
                                            <span class="badge <?= $task['status'] === 'completed' ? 'bg-success' : ($task['status'] === 'in_progress' ? 'bg-warning text-dark' : ($task['status'] === 'skipped' ? 'bg-secondary' : 'bg-light text-dark border')) ?> text-nowrap">
                                                <?= esc(ucfirst(str_replace('_', ' ', $task['status']))) ?>
                                            </span>
                                        </div>
                                        <div class="sy-onb-task-meta">
                                            <?= esc($ownerRoleLabels[$task['owner_role']]) ?>
                                            <?php if ($task['status'] === 'completed' && $task['completed_at']): ?>
                                                &middot; Completed <?= esc(erp_date($task['completed_at'])) ?>
                                            <?php elseif ($task['due_date']): ?>
                                                &middot; <span class="due <?= $overdueTask ? 'overdue' : '' ?>">Due <?= esc(erp_date($task['due_date'])) ?><?= $overdueTask ? ' — overdue' : '' ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($task['notes']): ?><div class="sy-onb-task-meta"><?= esc($task['notes']) ?></div><?php endif; ?>
                                        <?php if ($canActTask && $task['status'] !== 'completed'): ?>
                                        <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/tasks/' . $task['id']) ?>" method="post" class="d-flex gap-1 mt-2">
                                            <?= csrf_field() ?>
                                            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Note (optional)">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-sm btn-outline-success text-nowrap">Mark Done</button>
                                        </form>
                                        <?php elseif ($canActTask && $task['status'] === 'completed'): ?>
                                        <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/tasks/' . $task['id']) ?>" method="post" class="mt-1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="status" value="pending">
                                            <button type="submit" class="btn btn-sm btn-link p-0">Reopen</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="sy-card mt-3" style="height:auto">
            <div class="sy-card-header"><strong>Joining Details</strong></div>
            <div class="sy-card-body">
                <div class="row small">
                    <div class="col-md-4 mb-2"><span class="text-muted">Department</span><br><?= esc($record['department_name'] ?? '—') ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Designation</span><br><?= esc($record['designation'] ?? '—') ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Reporting Manager</span><br><?= esc($record['manager_name'] ?? '—') ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Joining Date</span><br><?= $record['joining_date'] ? esc(erp_date($record['joining_date'])) : '—' ?></div>
                    <?php if ($record['probation_end_date']): ?><div class="col-md-4 mb-2"><span class="text-muted">Probation Ends</span><br><?= esc(erp_date($record['probation_end_date'])) ?></div><?php endif; ?>
                    <?php if ($record['employee_profile_id']): ?><div class="col-md-4 mb-2"><span class="text-muted">Employee Record</span><br><span class="badge bg-success me-1">Employee Created / Linked</span><a href="<?= site_url('hr/employees/' . $record['employee_profile_id']) ?>"><?= esc($record['employee_code']) ?></a></div><?php endif; ?>
                </div>
                <?php if ($isOwner && $record['employee_profile_id']): ?>
                <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/send-link') ?>" method="post" class="mt-2">
                    <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-paper-plane me-1"></i>Send Onboarding Link</button>
                    <span class="text-muted small ms-1">Delivered in-app (no email/SMTP configured on this instance).</span>
                </form>
                <?php endif; ?>
                <?php if ($isOwner && ! $record['employee_profile_id']): ?>
                <div class="mt-2"><div class="small text-muted mb-2">Create the employee record from this onboarding (reuses an existing login for <?= esc($record['candidate_email']) ?> or creates one), or link an existing employee:</div><div class="d-flex flex-wrap gap-2">
                    <?php if (in_array($record['status'], \App\Modules\Onboarding\Controllers\OnboardingController::EMPLOYEE_ELIGIBLE_STATUSES, true)): ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/create-employee') ?>" method="post" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-primary">Create Employee Record</button></form>
                    <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" disabled title="Available once the candidate has joined">Create Employee Record</button>
                    <?php endif; ?>
                    <?php if (! empty($employeeOptions)): ?><form action="<?= site_url('hr/onboarding/' . $record['id'] . '/link-employee') ?>" method="post" class="d-inline-flex gap-1"><?= csrf_field() ?><select name="employee_profile_id" class="form-select form-select-sm" required><option value="">Link existing employee…</option><?php foreach ($employeeOptions as $employee): ?><option value="<?= $employee['id'] ?>"><?= esc($employee['user_name']) ?> (<?= esc($employee['employee_code']) ?>)</option><?php endforeach; ?></select><button type="submit" class="btn btn-sm btn-outline-secondary">Link</button></form><?php endif; ?>
                </div></div>
                <?php endif; ?>
                <?php if ($isOwner && $record['status'] === 'offer_accepted'): ?><form action="<?= site_url('hr/onboarding/' . $record['id'] . '/status') ?>" method="post" class="mt-3"><?= csrf_field() ?><input type="hidden" name="status" value="documents_pending"><button type="submit" class="btn btn-sm btn-primary">Start Joining Checklist</button></form><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============ DOCUMENTS ============ -->
    <div class="tab-pane fade" id="tab-documents" role="tabpanel">
        <?php
        // Letters HR has sent to the candidate (the issued copies are archived by the document pipeline).
        $sentLetters = [];
        if ($record['offer_sent_at'] !== null) {
            $sentLetters[] = [
                'label' => 'Offer Letter', 'icon' => 'fa-file-lines', 'sent' => $record['offer_sent_at'],
                'status' => match (true) {
                    $record['offer_response'] === 'accepted' => ['Accepted by candidate' . ($record['offer_response_at'] ? ' ' . erp_date($record['offer_response_at']) : ''), 'success'],
                    $record['offer_response'] === 'rejected' => ['Declined by candidate', 'secondary'],
                    default                                  => ['Awaiting candidate response', 'warning text-dark'],
                },
                'view' => site_url('hr/onboarding/' . $record['id'] . '/offer-letter'), 'pdf' => site_url('hr/onboarding/' . $record['id'] . '/offer-letter/pdf'),
            ];
        }
        if ($joiningLetterSent) {
            $sentLetters[] = [
                'label' => 'Joining Letter', 'icon' => 'fa-file-signature', 'sent' => $record['joining_letter_sent_at'],
                'status' => ['Sent to candidate', 'success'],
                'view' => site_url('hr/onboarding/' . $record['id'] . '/joining-letter'), 'pdf' => site_url('hr/onboarding/' . $record['id'] . '/joining-letter/pdf'),
            ];
        }
        ?>
        <?php if ($sentLetters !== []): ?>
        <div class="sy-card mb-3">
            <div class="sy-card-header"><strong>Letters sent to the candidate</strong></div>
            <div class="sy-card-body p-0">
                <?php foreach ($sentLetters as $letter): ?>
                    <div class="sy-list-item px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="fw-semibold"><i class="fas <?= $letter['icon'] ?> me-1 text-muted"></i><?= esc($record['candidate_name']) ?> - <?= esc($letter['label']) ?></span>
                            <div class="muted">Sent <?= esc(erp_date($letter['sent'])) ?> · <span class="badge bg-<?= $letter['status'][1] ?>"><?= esc($letter['status'][0]) ?></span></div>
                        </div>
                        <?php if ($canViewLetters): ?>
                        <div class="d-flex gap-1">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= esc($letter['view']) ?>" target="_blank" rel="noopener" data-erp-form="off"><i class="fas fa-eye me-1"></i>View</a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= esc($letter['pdf']) ?>" data-erp-form="off"><i class="fas fa-download me-1"></i>Download</a>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="sy-card">
            <div class="sy-card-header"><strong>Documents</strong>
                <a href="<?= site_url('documents/create?onboarding_record_id=' . $record['id'] . '&company_id=' . $record['company_id']) ?>">Upload</a>
            </div>
            <div class="sy-card-body p-0">
                <?php if (empty($documents)): ?>
                    <p class="text-muted small mb-0 p-3">No documents uploaded yet.</p>
                <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                        <div class="sy-list-item px-3">
                            <a href="<?= site_url('files/download?path=' . urlencode($doc['file_path'])) ?>" class="text-decoration-none fw-semibold"><i class="fas fa-file-arrow-down me-1"></i><?= esc($doc['title']) ?></a>
                            <div class="muted"><?= esc($doc['document_type'] ?: ucfirst($doc['category'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============ OFFER ============ -->
    <div class="tab-pane fade" id="tab-offer" role="tabpanel">
        <?php if ($isOwner): ?>
        <div class="sy-card mb-3">
            <div class="sy-card-header"><strong>Candidate Portal</strong></div>
            <div class="sy-card-body">
                <div class="small mb-2">Stage: <strong><?= esc($stageLabels[$record['status']] ?? ucfirst(str_replace('_', ' ', $record['status']))) ?></strong></div>
                <?php if ($portalUrl): ?>
                <div class="input-group input-group-sm mb-2" style="max-width:520px">
                    <input type="text" class="form-control" value="<?= esc($portalUrl) ?>" id="portalUrlInput" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('portalUrlInput').value)">Copy</button>
                </div>
                <?php else: ?>
                <p class="small text-muted mb-2">No active link yet.</p>
                <?php endif; ?>
                <?php if ($record['offer_response']): ?>
                <hr>
                <div class="small fw-semibold mb-1">Offer Response</div>
                <span class="badge bg-<?= $record['offer_response'] === 'accepted' ? 'success' : 'secondary' ?>"><?= $record['offer_response'] === 'accepted' ? 'Accepted' : 'Rejected' ?></span>
                <span class="text-muted small ms-1">on <?= esc(erp_date($record['offer_response_at'])) ?></span>
                <?php if ($record['offer_response_note']): ?>
                <div class="small text-muted mt-1">"<?= esc($record['offer_response_note']) ?>"</div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="sy-card mb-3">
            <div class="sy-card-header"><strong>Offer Details</strong></div>
            <div class="sy-card-body">
                <div class="row small">
                    <div class="col-md-4 mb-2"><span class="text-muted">Offer Status</span><br><?= esc($offerStatusLabel) ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Offered CTC (Annual)</span><br><?= $record['offered_ctc'] !== null ? erp_money((float) $record['offered_ctc']) : '—' ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Offer Sent</span><br><?= $record['offer_sent_at'] ? esc(erp_date($record['offer_sent_at'])) : '—' ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Offer Accepted</span><br><?= $record['offer_accepted_at'] ? esc(erp_date($record['offer_accepted_at'])) : '—' ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Joining Letter</span><br><?= $joiningLetterSent ? 'Sent ' . esc(erp_date($record['joining_letter_sent_at'])) : ($record['offer_accepted_at'] ? 'Not sent yet' : 'After the offer is accepted') ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Joining Date</span><br><?= $record['joining_date'] ? esc(erp_date($record['joining_date'])) : '—' ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">BGV Status</span><br><?= esc(ucfirst(str_replace('_', ' ', $record['bgv_status']))) ?><?= $record['bgv_notes'] ? ' — ' . esc($record['bgv_notes']) : '' ?></div>
                </div>
                <?php if ($isOwner && $record['status'] === 'approved'): ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/send-offer') ?>" method="post" class="mt-2"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-paper-plane me-1"></i>Send Offer Letter</button></form>
                <?php elseif ($isOwner && $record['status'] === 'offer_sent' && $record['offer_response'] === null): ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/send-offer') ?>" method="post" class="mt-2"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fas fa-rotate me-1"></i>Resend Offer Link</button></form>
                <?php endif; ?>
                <?php if ($canSendJoining): ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/send-joining-letter') ?>" method="post" class="mt-2"><?= csrf_field() ?><button class="btn btn-sm <?= $joiningLetterSent ? 'btn-outline-secondary' : 'btn-primary' ?>" type="submit"><i class="fas fa-paper-plane me-1"></i><?= $joiningLetterSent ? 'Resend Joining Letter' : 'Send Joining Letter' ?></button></form>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ============ ACTIVITY ============ -->
    <div class="tab-pane fade" id="tab-activity" role="tabpanel">
        <div class="sy-card">
            <div class="sy-card-header"><strong>Activity</strong></div>
            <div class="sy-card-body">
                <?php if (empty($activity)): ?>
                    <p class="text-muted small mb-0">No activity recorded yet.</p>
                <?php else: ?>
                    <?php foreach ($activity as $a): ?>
                        <div class="sy-onb-activity-row">
                            <div class="flex-grow-1">
                                <div class="small"><span class="fw-semibold"><?= esc($a['user_name'] ?? 'System') ?></span> &mdash; <?= esc($a['description']) ?></div>
                            </div>
                            <div class="when"><?= esc(erp_date($a['created_at']) . ", " . erp_time($a['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    function showTab(key) {
        var btn = document.querySelector('[data-bs-target="#tab-' + key + '"]');
        if (btn) { bootstrap.Tab.getOrCreateInstance(btn).show(); }
    }
    document.querySelectorAll('[data-pf-tab]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            showTab(el.getAttribute('data-pf-tab'));
            var tabs = document.getElementById('onbTabs');
            if (tabs) { tabs.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        });
    });
})();
</script>
<?= $this->endSection() ?>
