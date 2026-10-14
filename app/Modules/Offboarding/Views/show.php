<?= $this->extend('layouts/main') ?>

<?php
$isRescinded = $record['status'] === 'rescinded';
$isEmployeeView = $myRole === 'employee';
$group       = \App\Modules\Offboarding\Models\OffboardingRecordModel::groupFor($record['status']);
$groupLabel  = \App\Modules\Offboarding\Models\OffboardingRecordModel::GROUP_LABELS[$group];
$groupColor  = [
    'pending'   => ['fg' => '#d97706', 'bg' => '#fef3e2'],
    'progress'  => ['fg' => '#2563eb', 'bg' => '#eaf1fe'],
    'completed' => ['fg' => '#16a34a', 'bg' => '#e8f8ee'],
    'rescinded' => ['fg' => '#64748b', 'bg' => '#eef1f4'],
][$group];

$today    = date('Y-m-d');
$allTasks = array_merge(...array_values($tasksByPhase ?: [[]]));
$total    = count($allTasks);
$done     = count(array_filter($allTasks, static fn (array $t) => $t['status'] === 'completed'));
$pct      = $total > 0 ? (int) round($done / $total * 100) : 0;

$isOverdue = static fn (array $t): bool => $t['due_date'] && $t['status'] !== 'completed' && $t['due_date'] < $today;

// Action Required = everything not completed/skipped, overdue first —
// the top one becomes the single "Current Action" card.
$pending  = array_values(array_filter($allTasks, static fn (array $t) => ! in_array($t['status'], ['completed', 'skipped'], true)));
$pending  = array_merge(
    array_values(array_filter($pending, $isOverdue)),
    array_values(array_filter($pending, static fn (array $t) => ! $isOverdue($t)))
);
$nextTask = $pending[0] ?? null;

$statusClass = static fn (string $s): string => $s === 'completed' ? 'bg-success' : ($s === 'in_progress' ? 'bg-warning text-dark' : ($s === 'skipped' ? 'bg-secondary' : 'bg-light text-dark border'));

// Progress stepper — the real workflow stages, in order. Completed =
// before the current stage; an Exit Completed record shows every stage done.
$stageIndex  = array_search($record['status'], $stages, true);
$exitDone    = $record['status'] === 'exit_completed';
$stagesDone  = $stageIndex === false ? 0 : ($exitDone ? count($stages) : $stageIndex);
$subtitle    = implode(' · ', array_filter([
    trim(($record['employee_code'] ?? '') . ''),
    $record['designation'] ?? '',
    $record['department_name'] ?? '',
]));

// Documents tab: the app has no fixed exit-document types, so uploaded
// HR documents are matched to the named slots by keyword in their
// type/title; anything left over lands under "Other".
$docSlots = ['settlement' => [], 'interview' => [], 'relieving' => [], 'exit' => [], 'experience' => [], 'other' => []];
foreach ($documents as $doc) {
    $hay = strtolower(($doc['document_type'] ?? '') . ' ' . ($doc['title'] ?? ''));
    $slot = match (true) {
        str_contains($hay, 'settlement') || str_contains($hay, 'f&f') || str_contains($hay, 'full and final') => 'settlement',
        str_contains($hay, 'interview')  => 'interview',
        str_contains($hay, 'relieving')  => 'relieving',
        str_contains($hay, 'experience') => 'experience',
        default                          => 'other',
    };
    $docSlots[$slot][] = $doc;
}
$uploadUrl = site_url('documents/create?company_id=' . $record['company_id'] . '&employee_user_id=' . $record['employee_user_id']);
$letterUrl = site_url('hr/offboarding/' . $record['id'] . '/relieving-letter');

// Exit documents = whatever the CENTRAL type->workflow mapping offers the Offboarding workflow for this company's ACTIVE
// templates (relieving / exit letter, experience + internship completion certificate, ...). No list is kept here:
// upload a template under Document Templates and it appears, for this company only.
$docAvail    = \App\Modules\Document\Services\DocumentAvailability::shared();
$companyId   = (int) $record['company_id'];
$exitContext = ['offboarding_record' => (int) $record['id'], 'employee_profile' => (int) $record['employee_profile_id']];
$slotFor     = ['relieving_letter' => 'relieving', 'experience_certificate' => 'experience', 'exit_letter' => 'exit'];   // uploaded-file slots to list beside each
$generated    = [];
$generatedUrl = [];
foreach ($docAvail->items('offboarding', $companyId, $exitContext) as $it) {
    $kind = \App\Modules\Document\Services\DocumentTypeRegistry::TYPES[$it['type']]['record'] ?? '';
    $generated[$it['type']]    = [$it['label'], $kind === 'employee_profile' ? 'Generated from the employee profile' : 'Generated from the exit record'];
    $generatedUrl[$it['type']] = site_url('documents/render/' . $it['type'] . '/' . (int) $it['recordId'] . '?inline=1');
}
$uploaded = [
    'settlement' => ['Final Settlement Statement', 'Upload the F&F statement'],
    'interview'  => ['Exit Interview', 'Upload the interview record'],
];

$settlementStatus = $record['final_settlement_status'] ?? null;
$settlementTasks  = array_values(array_filter($allTasks, static fn (array $t) => ($t['task_type'] ?? '') === 'final_settlement'));
$showStageControls = $isOwner && ! $isRescinded && ! $exitDone;
?>

<?= $this->section('content') ?>

<style>
    .sy-off-card-lift { box-shadow: 0 1px 2px rgba(16,24,40,.05); }
    .sy-off-tabs { flex-wrap: nowrap; overflow-x: auto; }
    .sy-off-tabs .nav-link { white-space: nowrap; }
    .sy-off-head-top { font-size: .8rem; margin-bottom: 8px; }
    .sy-off-head h4 { margin: 0; font-size: 1.25rem; font-weight: 700; }
    .sy-off-head .sub { color: var(--sy-muted); font-size: .86rem; margin-top: 2px; }

    .sy-off-stepper { display: flex; align-items: flex-start; overflow-x: auto; padding-bottom: 2px; }
    .sy-off-stepper .st { flex: 1 0 84px; text-align: center; position: relative; font-size: .7rem; color: var(--sy-muted); padding: 0 2px; }
    .sy-off-stepper .st::before { content: ''; position: absolute; top: 11px; left: -50%; width: 100%; height: 2px; background: var(--sy-border); z-index: 0; }
    .sy-off-stepper .st:first-child::before { display: none; }
    .sy-off-stepper .st.done::before, .sy-off-stepper .st.current::before { background: var(--sy-success); }
    .sy-off-stepper .dot { position: relative; z-index: 1; width: 24px; height: 24px; border-radius: 50%; margin: 0 auto 4px; display: flex; align-items: center; justify-content: center; font-size: .66rem; font-weight: 700; background: var(--sy-surface, #fff); border: 2px solid var(--sy-border); }
    .sy-off-stepper .st.done .dot { background: var(--sy-success); border-color: var(--sy-success); color: #fff; }
    .sy-off-stepper .st.current .dot { border-color: var(--sy-accent-ink); color: var(--sy-accent-ink); box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
    .sy-off-stepper .st.current, .sy-off-stepper .st.done { color: var(--sy-ink); }
    .sy-off-stepper .st.current .lbl { font-weight: 700; }

    .sy-off-summary { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 16px; align-items: start; }
    @media (max-width: 991.98px) { .sy-off-summary { grid-template-columns: 1fr; } }

    .sy-off-phase + .sy-off-phase { margin-top: 22px; }
    .sy-off-phase-title { display: flex; justify-content: space-between; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--sy-ink); margin-bottom: 10px; padding-bottom: 6px; border-bottom: 2px solid var(--sy-border); }
    .sy-off-phase-title .count { font-weight: 600; color: var(--sy-muted); text-transform: none; letter-spacing: normal; }

    .sy-off-task { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--sy-border-soft); }
    .sy-off-task:last-child { border-bottom: none; }
    .sy-off-state { flex-shrink: 0; width: 20px; text-align: center; margin-top: 1px; font-size: .85rem; }
    .sy-off-state.done { color: var(--sy-success); }
    .sy-off-state.pending { color: var(--sy-muted); }
    .sy-off-state.overdue { color: var(--sy-danger); }
    .sy-off-meta { font-size: .74rem; color: var(--sy-muted); }
    .sy-off-meta .overdue { color: var(--sy-danger); font-weight: 600; }

    .sy-off-doc { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 0; border-bottom: 1px solid var(--sy-border-soft); }
    .sy-off-doc:last-child { border-bottom: none; }
    .sy-off-activity-row { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--sy-border-soft); }
    .sy-off-activity-row:last-child { border-bottom: none; }
    .sy-off-activity-row .when { font-size: .72rem; color: var(--sy-muted); white-space: nowrap; }
</style>

<!-- Header -->
<div class="sy-card sy-off-card-lift sy-off-head mb-3" style="height:auto;overflow:visible">
    <div class="sy-card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div>
                    <h4><?= esc($record['employee_name']) ?></h4>
                    <div class="sub"><?= $subtitle !== '' ? esc($subtitle) : 'No designation set' ?></div>
                </div>
                <span class="badge" style="background:<?= $groupColor['bg'] ?>;color:<?= $groupColor['fg'] ?>"><?= esc($groupLabel) ?></span>
            </div>
            <div class="d-flex gap-2">
                <?php if ($isOwner): ?>
                <a href="<?= site_url('hr/offboarding/' . $record['id'] . '/edit') ?>" class="btn btn-light btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($generated as $slotKey => [$docName, $docHint]): ?>
                        <li><a class="dropdown-item" href="<?= $generatedUrl[$slotKey] ?>" target="_blank" rel="noopener" data-erp-form="off"><i class="fas fa-file-pdf me-2 text-muted"></i>View <?= esc($docName) ?></a></li>
                        <?php endforeach; ?>
                        <?php if (! $generated): ?>
                        <li><span class="dropdown-item-text text-muted small"><i class="far fa-circle-question me-1"></i>No exit document templates are active for this company. An administrator needs to activate one under Document Templates (e.g. Relieving Letter, Experience or Internship Completion Certificate).</span></li>
                        <?php if (can('document.edit')): ?><li><a class="dropdown-item small" data-erp-form="off" href="<?= site_url('documents/templates') ?>"><i class="fas fa-file-code me-2 text-muted"></i>Manage document templates</a></li><?php endif; ?>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (! $isRescinded && ! $exitDone): ?>
                        <li>
                            <form action="<?= site_url('hr/offboarding/' . $record['id'] . '/status') ?>" method="post" onsubmit="return confirm('Rescind this exit? The employee will remain active.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="rescinded">
                                <button type="submit" class="dropdown-item text-warning"><i class="fas fa-ban me-2"></i>Rescind Offboarding</button>
                            </form>
                        </li>
                        <?php endif; ?>
                        <li>
                            <form action="<?= site_url('hr/offboarding/' . $record['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Remove this exit record? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button>
                            </form>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Progress -->
<div class="sy-card sy-off-card-lift mb-3" style="height:auto">
    <div class="sy-card-body">
        <div class="d-flex justify-content-between align-items-center small mb-2 gap-2 flex-wrap">
            <strong>Offboarding Progress</strong>
            <span class="text-muted">
                <?php if ($isRescinded): ?>Rescinded
                <?php else: ?><?= $stagesDone ?>/<?= count($stages) ?> stages completed<?= $total > 0 ? ' · ' . $pct . '% checklist (' . $done . ' of ' . $total . ')' : '' ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="sy-off-stepper">
            <?php foreach ($stages as $i => $step): ?>
                <?php $stepDone = ! $isRescinded && $i < $stagesDone; $stepCurrent = ! $isRescinded && ! $exitDone && $stageIndex === $i; ?>
                <div class="st <?= $stepDone ? 'done' : '' ?> <?= $stepCurrent ? 'current' : '' ?>">
                    <span class="dot"><?php if ($stepDone): ?><i class="fas fa-check"></i><?php else: ?><?= $i + 1 ?><?php endif; ?></span>
                    <span class="lbl"><?= esc($stageLabels[$step]) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<ul class="nav nav-tabs sy-off-tabs" id="offTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button">Overview</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-exit" type="button">Exit Details</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-documents" type="button">Documents</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-checklist" type="button">Clearance<?= $total > 0 ? ' (' . $done . '/' . $total . ')' : '' ?></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-settlement" type="button">Final Settlement</button></li>
    <?php if (! $isEmployeeView): ?><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activity" type="button">Activity</button></li><?php endif; ?>
</ul>
<div class="tab-content pt-3">

    <!-- ============ OVERVIEW ============ -->
    <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
        <div class="sy-off-summary">
            <div>
                <div class="sy-card sy-off-card-lift" style="height:auto">
                    <div class="sy-card-header"><strong>Current Action</strong></div>
                    <div class="sy-card-body">
                        <?php if ($isRescinded): ?>
                            <p class="text-muted small mb-0"><i class="fas fa-ban me-1"></i>This exit was rescinded. The employee remains active.</p>
                        <?php elseif ($nextTask): ?>
                            <?php $overdue = $isOverdue($nextTask); $canAct = $isOwner || $myRole === $nextTask['owner_role']; ?>
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div>
                                    <div class="fw-bold"><i class="fas fa-triangle-exclamation me-2 <?= $overdue ? 'text-danger' : 'text-warning' ?>"></i><?= esc($nextTask['title']) ?></div>
                                    <div class="sy-off-meta mt-1">
                                        Owner: <?= esc($ownerRoleLabels[$nextTask['owner_role']]) ?>
                                        <?php if ($overdue): ?> &middot; <span class="overdue">Overdue since <?= esc(erp_date($nextTask['due_date'])) ?></span>
                                        <?php elseif ($nextTask['due_date']): ?> &middot; Due <?= esc(erp_date($nextTask['due_date'])) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($canAct): ?>
                                <form action="<?= site_url('hr/offboarding/' . $record['id'] . '/tasks/' . $nextTask['id']) ?>" method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-sm btn-primary text-nowrap">Complete</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($total > 0): ?>
                            <p class="text-success small fw-semibold mb-0"><i class="fas fa-circle-check me-1"></i>No pending actions — every checklist item is done.</p>
                        <?php else: ?>
                            <p class="text-muted small mb-0">No checklist items for this exit yet.</p>
                        <?php endif; ?>

                        <?php if ($showStageControls): ?>
                        <div class="d-flex flex-wrap gap-2 align-items-center mt-3 pt-3 border-top">
                            <?php if ($nextStatus): ?>
                            <form action="<?= site_url('hr/offboarding/' . $record['id'] . '/status') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="<?= $nextStatus ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Advance to "<?= esc($stageLabels[$nextStatus]) ?>"</button>
                            </form>
                            <?php endif; ?>
                            <form action="<?= site_url('hr/offboarding/' . $record['id'] . '/status') ?>" method="post" class="d-inline-flex gap-1">
                                <?= csrf_field() ?>
                                <select name="status" class="form-select form-select-sm" aria-label="Set stage">
                                    <?php foreach ($stages as $s): ?>
                                        <option value="<?= $s ?>" <?= $record['status'] === $s ? 'selected' : '' ?>><?= esc($stageLabels[$s]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap">Set Stage</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="sy-card sy-off-card-lift" style="height:auto">
                <div class="sy-card-header"><strong>Quick Info</strong></div>
                <div class="sy-card-body">
                    <div class="sy-pf-row"><span class="k">Company</span><span class="v"><?= esc($record['company_name']) ?></span></div>
                    <div class="sy-pf-row"><span class="k">Department</span><span class="v"><?= esc($record['department_name'] ?? '—') ?></span></div>
                    <div class="sy-pf-row"><span class="k">Manager</span><span class="v"><?= esc($record['manager_name'] ?? '—') ?></span></div>
                    <div class="sy-pf-row"><span class="k">Joining Date</span><span class="v"><?= $record['date_of_joining'] ? esc(erp_date($record['date_of_joining'])) : '—' ?></span></div>
                    <div class="sy-pf-row"><span class="k">Last Working Day</span><span class="v"><?= $record['last_working_day'] ? esc(erp_date($record['last_working_day'])) : '—' ?></span></div>
                    <div class="sy-pf-row"><span class="k">Exit Type</span><span class="v"><?= esc(ucfirst($record['exit_type'])) ?></span></div>
                    <div class="sy-pf-row"><span class="k">Notice Period</span><span class="v"><?= $record['notice_period_days'] !== null ? esc($record['notice_period_days']) . ' day(s)' : '—' ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ EXIT DETAILS ============ -->
    <div class="tab-pane fade" id="tab-exit" role="tabpanel">
        <div class="sy-card sy-off-card-lift mb-3" style="height:auto">
            <div class="sy-card-header"><strong>Exit Details</strong></div>
            <div class="sy-card-body">
                <div class="row small">
                    <div class="col-md-4 mb-2"><span class="text-muted">Exit Type</span><br><?= esc(ucfirst($record['exit_type'])) ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted"><?= $record['exit_type'] === 'resignation' ? 'Resignation Date' : 'Exit Date' ?></span><br><?= ! empty($record['exit_date']) ? esc(erp_date($record['exit_date'])) : '—' ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Notice Period</span><br><?= $record['notice_period_days'] !== null ? esc($record['notice_period_days']) . ' day(s)' : '—' ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Last Working Day</span><br><?= $record['last_working_day'] ? esc(erp_date($record['last_working_day'])) : '—' ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Current Stage</span><br><?= esc($stageLabels[$record['status']]) ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Completed On</span><br><?= $record['completed_at'] ? esc(erp_date($record['completed_at'])) : '—' ?></div>
                    <div class="col-12 mb-2"><span class="text-muted">Reason</span><br><?= $record['reason'] ? nl2br(esc($record['reason'])) : '—' ?></div>
                </div>
            </div>
        </div>
        <div class="sy-card sy-off-card-lift" style="height:auto">
            <div class="sy-card-header"><strong>Employment</strong></div>
            <div class="sy-card-body">
                <div class="row small">
                    <div class="col-md-4 mb-2"><span class="text-muted">Employee Record</span><br><a href="<?= site_url('hr/employees/' . $record['employee_profile_id']) ?>"><?= esc($record['employee_code']) ?></a></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Designation</span><br><?= esc($record['designation'] ?? '—') ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Reporting Manager</span><br><?= esc($record['manager_name'] ?? '—') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ DOCUMENTS ============ -->
    <div class="tab-pane fade" id="tab-documents" role="tabpanel">
        <div class="sy-card sy-off-card-lift" style="height:auto">
            <div class="sy-card-header"><strong>Exit Documents</strong>
                <?php if ($isOwner): ?><a href="<?= $uploadUrl ?>">Upload</a><?php endif; ?>
            </div>
            <div class="sy-card-body">
                <?php if (! $generated): ?>
                    <div class="small text-muted mb-2"><i class="far fa-circle-question me-1"></i>No generated exit documents are configured for this company. Uploaded documents are listed below.</div>
                <?php endif; ?>
                <?php foreach ($generated as $key => [$name, $hint]): ?>
                    <div class="sy-off-doc">
                        <div>
                            <div class="fw-semibold small"><?= esc($name) ?></div>
                            <div class="sy-off-meta"><?= esc($hint) ?></div>
                            <?php foreach ($docSlots[$slotFor[$key] ?? $key] ?? [] as $doc): ?>
                                <div class="small"><a href="<?= site_url('files/download?path=' . urlencode($doc['file_path'])) ?>" class="text-decoration-none"><i class="fas fa-file-arrow-down me-1"></i><?= esc($doc['title']) ?></a></div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($isOwner): ?>
                        <a href="<?= $generatedUrl[$key] ?>" target="_blank" rel="noopener" data-erp-form="off" class="btn btn-sm btn-outline-primary">View</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($uploaded as $key => [$name, $hint]): ?>
                    <div class="sy-off-doc">
                        <div>
                            <div class="fw-semibold small"><?= esc($name) ?></div>
                            <?php if (empty($docSlots[$key])): ?>
                                <div class="sy-off-meta">Not uploaded &middot; <?= esc($hint) ?></div>
                            <?php else: ?>
                                <?php foreach ($docSlots[$key] as $doc): ?>
                                    <div class="small"><a href="<?= site_url('files/download?path=' . urlencode($doc['file_path'])) ?>" class="text-decoration-none"><i class="fas fa-file-arrow-down me-1"></i><?= esc($doc['title']) ?></a></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($isOwner && empty($docSlots[$key])): ?>
                            <a href="<?= $uploadUrl ?>" class="btn btn-sm btn-outline-secondary">Upload</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="sy-off-doc">
                    <div>
                        <div class="fw-semibold small">Other Exit Documents</div>
                        <?php if (empty($docSlots['other'])): ?>
                            <div class="sy-off-meta">None uploaded</div>
                        <?php else: ?>
                            <?php foreach ($docSlots['other'] as $doc): ?>
                                <div class="small"><a href="<?= site_url('files/download?path=' . urlencode($doc['file_path'])) ?>" class="text-decoration-none"><i class="fas fa-file-arrow-down me-1"></i><?= esc($doc['title']) ?></a> <span class="text-muted">&middot; <?= esc($doc['document_type'] ?: 'HR Document') ?></span></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ CLEARANCE (full checklist, grouped by phase) ============ -->
    <div class="tab-pane fade" id="tab-checklist" role="tabpanel">
        <div class="sy-card sy-off-card-lift" style="height:auto">
            <div class="sy-card-header d-flex justify-content-between align-items-center">
                <strong>Clearance Checklist</strong>
                <?php if ($isOwner): ?><a href="<?= site_url('hr/offboarding/phases') ?>" class="small"><i class="fas fa-diagram-project me-1"></i>Manage Phases</a><?php endif; ?>
            </div>
            <div class="sy-card-body">
                <?php foreach ($tasksByPhase as $phase => $tasks): ?>
                    <?php $phaseDone = count(array_filter($tasks, static fn (array $t) => $t['status'] === 'completed')); ?>
                    <div class="sy-off-phase">
                        <div class="sy-off-phase-title">
                            <span><?= esc(strtoupper($phase)) ?></span>
                            <span class="count"><?= $phaseDone ?>/<?= count($tasks) ?></span>
                        </div>

                        <?php if ($exitCompletedPhaseName !== null && $phase === $exitCompletedPhaseName): ?>
                            <div class="sy-off-task">
                                <span class="sy-off-state <?= $exitDone ? 'done' : 'pending' ?>"><i class="fas <?= $exitDone ? 'fa-circle-check' : 'fa-circle' ?>"></i></span>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small">Exit completed</div>
                                    <div class="sy-off-meta">HR<?= $exitDone && $record['completed_at'] ? ' &middot; Completed ' . esc(erp_date($record['completed_at'])) : ($isRescinded ? ' &middot; Rescinded' : ' &middot; Set when the stage is advanced to Exit Completed') ?></div>
                                </div>
                                <span class="badge <?= $exitDone ? 'bg-success' : 'bg-light text-dark border' ?> text-nowrap"><?= $exitDone ? 'Completed' : 'Pending' ?></span>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($tasks as $task): ?>
                            <?php
                                $canActTask = $isOwner || $myRole === $task['owner_role'];
                                $overdueTask = $isOverdue($task);
                                $state   = $task['status'] === 'completed' ? 'done' : ($overdueTask ? 'overdue' : 'pending');
                                $icon    = $state === 'done' ? 'fa-circle-check' : ($state === 'overdue' ? 'fa-triangle-exclamation' : 'fa-circle');
                            ?>
                            <div class="sy-off-task">
                                <span class="sy-off-state <?= $state ?>"><i class="fas <?= $icon ?>"></i></span>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div class="fw-semibold small"><?= esc($task['title']) ?></div>
                                        <span class="badge <?= $overdueTask ? 'bg-danger' : $statusClass($task['status']) ?> text-nowrap"><?= $overdueTask ? 'Overdue' : esc(ucfirst(str_replace('_', ' ', $task['status']))) ?></span>
                                    </div>
                                    <div class="sy-off-meta">
                                        <?= esc($ownerRoleLabels[$task['owner_role']]) ?>
                                        <?php if ($task['status'] === 'completed' && $task['completed_at']): ?>
                                            &middot; Completed <?= esc(erp_date($task['completed_at'])) ?>
                                        <?php elseif ($task['due_date']): ?>
                                            &middot; <span class="<?= $overdueTask ? 'overdue' : '' ?>">Due <?= esc(erp_date($task['due_date'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($task['notes']): ?><div class="sy-off-meta"><?= esc($task['notes']) ?></div><?php endif; ?>
                                    <?php if ($canActTask && $task['status'] !== 'completed'): ?>
                                    <form action="<?= site_url('hr/offboarding/' . $record['id'] . '/tasks/' . $task['id']) ?>" method="post" class="d-flex gap-1 mt-2">
                                        <?= csrf_field() ?>
                                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Note (optional)">
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="btn btn-sm btn-outline-success text-nowrap">Mark Done</button>
                                    </form>
                                    <?php elseif ($canActTask && $task['status'] === 'completed'): ?>
                                    <form action="<?= site_url('hr/offboarding/' . $record['id'] . '/tasks/' . $task['id']) ?>" method="post" class="mt-1">
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
                <?php if (empty($tasksByPhase)): ?><p class="text-muted small mb-0">No checklist items yet.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============ FINAL SETTLEMENT ============ -->
    <div class="tab-pane fade" id="tab-settlement" role="tabpanel">
        <div class="sy-card sy-off-card-lift" style="height:auto">
            <div class="sy-card-header"><strong>Final Settlement</strong></div>
            <div class="sy-card-body">
                <div class="row small">
                    <div class="col-md-4 mb-2"><span class="text-muted">Settlement Status</span><br>
                        <?php if ($settlementStatus !== null): ?>
                            <span class="badge <?= $settlementStatus === 'completed' ? 'bg-success' : ($settlementStatus === 'in_progress' ? 'bg-warning text-dark' : 'bg-light text-dark border') ?>"><?= esc(ucfirst(str_replace('_', ' ', $settlementStatus))) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Last Working Day</span><br><?= $record['last_working_day'] ? esc(erp_date($record['last_working_day'])) : '—' ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Settlement Statement</span><br>
                        <?php if (empty($docSlots['settlement'])): ?>Not uploaded
                        <?php else: foreach ($docSlots['settlement'] as $doc): ?>
                            <a href="<?= site_url('files/download?path=' . urlencode($doc['file_path'])) ?>" class="text-decoration-none"><i class="fas fa-file-arrow-down me-1"></i><?= esc($doc['title']) ?></a><br>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <?php if ($settlementTasks): ?>
                <div class="sy-off-phase-title mt-3"><span>Settlement tasks</span></div>
                <?php foreach ($settlementTasks as $task): ?>
                    <?php $canActTask = $isOwner || $myRole === $task['owner_role']; $isDone = $task['status'] === 'completed'; ?>
                    <div class="sy-off-task">
                        <span class="sy-off-state <?= $isDone ? 'done' : 'pending' ?>"><i class="fas <?= $isDone ? 'fa-circle-check' : 'fa-circle' ?>"></i></span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small"><?= esc($task['title']) ?></div>
                            <div class="sy-off-meta"><?= esc($ownerRoleLabels[$task['owner_role']]) ?><?= $isDone && $task['completed_at'] ? ' &middot; Completed ' . esc(erp_date($task['completed_at'])) : ($task['due_date'] ? ' &middot; Due ' . esc(erp_date($task['due_date'])) : '') ?></div>
                        </div>
                        <?php if ($canActTask && ! $isDone): ?>
                        <form action="<?= site_url('hr/offboarding/' . $record['id'] . '/tasks/' . $task['id']) ?>" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="completed">
                            <button type="submit" class="btn btn-sm btn-outline-success text-nowrap">Mark Done</button>
                        </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============ ACTIVITY ============ -->
    <?php if (! $isEmployeeView): ?>
    <div class="tab-pane fade" id="tab-activity" role="tabpanel">
        <div class="sy-card sy-off-card-lift" style="height:auto">
            <div class="sy-card-header"><strong>Activity</strong></div>
            <div class="sy-card-body">
                <?php if (empty($activity)): ?>
                    <p class="text-muted small mb-0">No activity recorded yet.</p>
                <?php else: ?>
                    <?php foreach ($activity as $a): ?>
                        <div class="sy-off-activity-row">
                            <div class="flex-grow-1 small"><span class="fw-semibold"><?= esc($a['user_name'] ?? 'System') ?></span> &mdash; <?= esc($a['description']) ?></div>
                            <div class="when"><?= esc(erp_date($a['created_at']) . ", " . erp_time($a['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?= $this->endSection() ?>
