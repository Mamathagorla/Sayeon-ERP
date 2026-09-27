<?= $this->extend('layouts/main') ?>

<?php
$isWithdrawn  = $record['status'] === 'withdrawn';
$currentGroup = \App\Modules\Onboarding\Models\OnboardingRecordModel::groupFor($record['status']);

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
$finalStatus = match (true) {
    $isWithdrawn                          => 'Withdrawn',
    $record['status'] === 'confirmed'     => 'Onboarded',
    $record['status'] === 'ready_to_join' => 'Ready to Join',
    default                                => 'In Progress',
};
$finalStatusColor = match ($finalStatus) {
    'Onboarded'     => ['fg' => '#16a34a', 'bg' => '#e8f8ee'],
    'Ready to Join' => ['fg' => '#2563eb', 'bg' => '#eaf1fe'],
    'Withdrawn'     => ['fg' => '#64748b', 'bg' => '#eef1f4'],
    default         => ['fg' => '#d97706', 'bg' => '#fef3e2'],
};

$offerStatusLabel = $record['offer_accepted_at'] ? 'Accepted' : ($record['offer_sent_at'] ? 'Sent' : 'Not sent');
$isOverdue = static fn (array $t): bool => $t['due_date'] && $t['status'] !== 'completed' && $t['due_date'] < date('Y-m-d');

// 3-step track: Hiring / Pre-Joining / Joining (the real STAGE_GROUPS,
// minus "Closed" which only ever holds 'withdrawn' and isn't part of
// the normal progression).
$trackGroups = array_slice(array_keys($stageGroups), 0, 3);
$trackState  = [];
$reached     = false;
foreach ($trackGroups as $g) {
    if ($g === $currentGroup) {
        $trackState[$g] = 'current';
        $reached = true;
    } else {
        $trackState[$g] = $reached ? 'upcoming' : 'done';
    }
}
?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/onboarding') ?>" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Onboarding</a>
<?php if ($isOwner): ?>
<a href="<?= site_url('hr/onboarding/' . $record['id'] . '/edit') ?>" class="btn btn-light btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<a href="<?= site_url('hr/onboarding/' . $record['id'] . '/offer-letter') ?>" target="_blank" rel="noopener" class="btn btn-light btn-sm"><i class="fas fa-file-lines me-1"></i>View Offer</a>
<a href="<?= site_url('hr/onboarding/' . $record['id'] . '/offer-letter?print=1') ?>" target="_blank" rel="noopener" class="btn btn-light btn-sm"><i class="fas fa-print me-1"></i>Print Offer</a>
<?php endif; ?>
<div class="dropdown d-inline-block">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">More</button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><button type="button" class="dropdown-item" data-pf-tab="checklist"><i class="fas fa-list-check me-2 text-muted"></i>Full Checklist</button></li>
        <li><button type="button" class="dropdown-item" data-pf-tab="documents"><i class="fas fa-folder-open me-2 text-muted"></i>Documents<?= empty($documents) ? '' : ' (' . count($documents) . ')' ?></button></li>
        <li><button type="button" class="dropdown-item" data-pf-tab="employment"><i class="fas fa-briefcase me-2 text-muted"></i>Employment</button></li>
        <li><button type="button" class="dropdown-item" data-pf-tab="activity"><i class="fas fa-clock-rotate-left me-2 text-muted"></i>Activity</button></li>
        <?php if ($isOwner): ?>
        <li><hr class="dropdown-divider"></li>
        <li>
            <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Remove this onboarding record? This cannot be undone.');">
                <?= csrf_field() ?>
                <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button>
            </form>
        </li>
        <?php endif; ?>
    </ul>
</div>
<?= $this->endSection() ?>

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

    /* Full Checklist/Documents/Employment/Activity are reachable only
       via the More menu — same hidden-tab-strip pattern used across
       Employee Profile / Attendance / Leave / Offboarding. */
    .sy-onb-tabs { display: none; }
</style>

<!-- Candidate header -->
<div class="sy-card sy-pf-head mb-3" style="height:auto">
    <div class="sy-card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4><?= esc($record['candidate_name']) ?></h4>
                <div class="sy-pf-meta">
                    <span><?= esc($record['designation'] ?: 'No target role set') ?></span>
                    <?php if (! empty($record['department_name'])): ?><span><i class="fas fa-sitemap"></i><?= esc($record['department_name']) ?></span><?php endif; ?>
                    <span><i class="far fa-calendar"></i>Joining: <?= $record['joining_date'] ? esc(date('d M Y', strtotime($record['joining_date']))) : '—' ?></span>
                </div>
            </div>
            <div class="text-end">
                <span class="badge fs-6" style="background:<?= $finalStatusColor['bg'] ?>;color:<?= $finalStatusColor['fg'] ?>"><?= esc($finalStatus) ?></span>
                <div class="text-muted small mt-1"><?= $completionPct ?>% Complete</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <!-- Progress -->
        <?php if (! $isWithdrawn): ?>
        <div class="sy-card mb-3" style="height:auto">
            <div class="sy-card-header"><strong>Progress</strong></div>
            <div class="sy-card-body">
                <div class="sy-track">
                    <?php foreach ($trackGroups as $i => $g): ?>
                        <div class="step <?= $trackState[$g] ?>">
                            <span class="dot"><?php if ($trackState[$g] === 'done'): ?><i class="fas fa-check"></i><?php endif; ?></span>
                            <span class="label"><?= esc($g) ?></span>
                        </div>
                        <?php if ($i < count($trackGroups) - 1): ?><div class="line <?= $trackState[$g] === 'done' ? 'done' : '' ?>"></div><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Current Action -->
        <?php if ($nextTask && ! $isWithdrawn): ?>
        <?php $overdue = $isOverdue($nextTask); $canAct = $isOwner || $myRole === $nextTask['owner_role']; ?>
        <div class="mb-3">
            <div class="sy-current-action">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <div class="title"><i class="fas fa-triangle-exclamation me-2 <?= $overdue ? 'text-danger' : 'text-warning' ?>"></i><?= esc($nextTask['title']) ?></div>
                        <div class="meta">
                            Owner: <?= esc($ownerRoleLabels[$nextTask['owner_role']]) ?>
                            <?php if ($overdue): ?> &middot; <span class="overdue">Overdue since <?= esc(date('d M Y', strtotime($nextTask['due_date']))) ?></span>
                            <?php elseif ($nextTask['due_date']): ?> &middot; Due <?= esc(date('d M Y', strtotime($nextTask['due_date']))) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($canAct): ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/tasks/' . $nextTask['id']) ?>" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="btn btn-sm btn-primary text-nowrap">Complete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php elseif (! $isWithdrawn && $totalTasks > 0): ?>
        <div class="mb-3"><p class="text-success small fw-semibold mb-0"><i class="fas fa-circle-check me-1"></i>Nothing pending — every checklist item is done.</p></div>
        <?php endif; ?>

        <!-- Onboarding Checklist (flat, read-only — full task
             management with notes/reopen lives under More → Full
             Checklist) -->
        <div class="sy-card" style="height:auto">
            <div class="sy-card-header"><strong>Onboarding Checklist</strong></div>
            <div class="sy-card-body p-0">
                <?php if ($totalTasks === 0): ?>
                    <p class="text-muted small mb-0 p-3">No checklist items.</p>
                <?php else: ?>
                    <?php $seenCurrent = false; ?>
                    <?php foreach ($allTasks as $t): ?>
                        <?php
                            $done = in_array($t['status'], ['completed', 'skipped'], true);
                            if ($done) {
                                $state = 'done'; $icon = 'fa-circle-check';
                            } elseif (! $seenCurrent) {
                                $state = 'current'; $icon = 'fa-circle-dot'; $seenCurrent = true;
                            } else {
                                $state = 'upcoming'; $icon = 'fa-circle';
                            }
                        ?>
                        <div class="sy-onb-check-row px-3">
                            <span class="ico <?= $state ?>"><i class="fas <?= $icon ?>"></i></span>
                            <span class="title <?= $done ? 'done' : '' ?>"><?= esc($t['title']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isOwner && ! $isWithdrawn): ?>
        <div class="sy-card mt-3" style="height:auto">
            <div class="sy-card-header"><strong>Stage Controls</strong></div>
            <div class="sy-card-body d-flex flex-wrap gap-2 align-items-center">
                <?php if ($nextStatus): ?>
                <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/status') ?>" method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="<?= $nextStatus ?>">
                    <button type="submit" class="btn btn-sm btn-primary">Advance to "<?= esc($stageLabels[$nextStatus]) ?>"</button>
                </form>
                <?php endif; ?>
                <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/status') ?>" method="post" class="d-inline-flex gap-1">
                    <?= csrf_field() ?>
                    <select name="status" class="form-select form-select-sm">
                        <?php foreach ($stages as $s): ?>
                            <option value="<?= $s ?>" <?= $record['status'] === $s ? 'selected' : '' ?>><?= esc($stageLabels[$s]) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Set Stage</button>
                </form>
                <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/status') ?>" method="post" class="d-inline" onsubmit="return confirm('Mark this candidate as withdrawn?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="withdrawn">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Withdraw</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <!-- Quick Info -->
        <div class="sy-card" style="height:auto">
            <div class="sy-card-header"><strong>Quick Info</strong></div>
            <div class="sy-card-body">
                <div class="sy-pf-row"><span class="k">Manager</span><span class="v"><?= esc($record['manager_name'] ?? '—') ?></span></div>
                <div class="sy-pf-row"><span class="k">Department</span><span class="v"><?= esc($record['department_name'] ?? '—') ?></span></div>
                <div class="sy-pf-row"><span class="k">Company</span><span class="v"><?= esc($record['company_name']) ?></span></div>
                <div class="sy-pf-row"><span class="k">Offer Status</span><span class="v"><?= esc($offerStatusLabel) ?></span></div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden nav-tabs strip — exists only so bootstrap's Tab JS has a
     trigger to activate; the More menu drives it via data-pf-tab. -->
<ul class="nav nav-tabs sy-onb-tabs" id="onbTabs" role="tablist">
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-checklist" type="button">Full Checklist</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-documents" type="button">Documents</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-employment" type="button">Employment</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activity" type="button">Activity</button></li>
</ul>
<div class="tab-content">

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
                                                &middot; Completed <?= esc(date('d/m/Y', strtotime($task['completed_at']))) ?>
                                            <?php elseif ($task['due_date']): ?>
                                                &middot; <span class="due <?= $overdueTask ? 'overdue' : '' ?>">Due <?= esc(date('d/m/Y', strtotime($task['due_date']))) ?><?= $overdueTask ? ' — overdue' : '' ?></span>
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
    </div>

    <!-- ============ DOCUMENTS ============ -->
    <div class="tab-pane fade" id="tab-documents" role="tabpanel">
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

    <!-- ============ EMPLOYMENT ============ -->
    <div class="tab-pane fade" id="tab-employment" role="tabpanel">
        <div class="sy-card mb-3">
            <div class="sy-card-header"><strong>Employment Details</strong></div>
            <div class="sy-card-body">
                <div class="row small">
                    <div class="col-md-4 mb-2"><span class="text-muted">Department</span><br><?= esc($record['department_name'] ?? '—') ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Designation</span><br><?= esc($record['designation'] ?? '—') ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Reporting / Onboarding Manager</span><br><?= esc($record['manager_name'] ?? '—') ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Joining Date</span><br><?= $record['joining_date'] ? esc(date('d/m/Y', strtotime($record['joining_date']))) : '—' ?></div>
                    <?php if ($record['probation_end_date']): ?>
                    <div class="col-md-4 mb-2"><span class="text-muted">Probation Ends</span><br><?= esc(date('d/m/Y', strtotime($record['probation_end_date']))) ?></div>
                    <?php endif; ?>
                    <?php if ($record['employee_profile_id']): ?>
                    <div class="col-md-4 mb-2"><span class="text-muted">Employee Record</span><br><a href="<?= site_url('hr/employees/' . $record['employee_profile_id']) ?>"><?= esc($record['employee_code']) ?></a></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="sy-card mb-3">
            <div class="sy-card-header"><strong>Offer Details</strong></div>
            <div class="sy-card-body">
                <div class="row small">
                    <div class="col-md-4 mb-2"><span class="text-muted">Offered CTC (Annual)</span><br><?= $record['offered_ctc'] !== null ? '₹' . number_format((float) $record['offered_ctc'], 2) : '—' ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Offer Sent</span><br><?= $record['offer_sent_at'] ? esc(date('d/m/Y', strtotime($record['offer_sent_at']))) : '—' ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Offer Accepted</span><br><?= $record['offer_accepted_at'] ? esc(date('d/m/Y', strtotime($record['offer_accepted_at']))) : '—' ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">BGV Status</span><br><?= esc(ucfirst(str_replace('_', ' ', $record['bgv_status']))) ?><?= $record['bgv_notes'] ? ' — ' . esc($record['bgv_notes']) : '' ?></div>
                </div>
            </div>
        </div>

        <?php if ($isOwner && $record['status'] !== 'selected' && ! $record['employee_profile_id']): ?>
        <div class="sy-card">
            <div class="sy-card-header"><strong>Link Employee Record</strong></div>
            <div class="sy-card-body">
                <div class="small text-muted mb-2">Once the real Employee record exists, link it here to close the loop:</div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= site_url('hr/employees/create') ?>" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">Create Employee Record</a>
                    <?php if (! empty($employeeOptions)): ?>
                    <form action="<?= site_url('hr/onboarding/' . $record['id'] . '/link-employee') ?>" method="post" class="d-inline-flex gap-1">
                        <?= csrf_field() ?>
                        <select name="employee_profile_id" class="form-select form-select-sm" required>
                            <option value="">Link existing employee…</option>
                            <?php foreach ($employeeOptions as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= esc($e['user_name']) ?> (<?= esc($e['employee_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Link</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
                            <div class="when"><?= esc(date('d/m/Y, g:i A', strtotime($a['created_at']))) ?></div>
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
            var content = document.querySelector('.tab-content');
            if (content) { content.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        });
    });
})();
</script>
<?= $this->endSection() ?>
