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

// Documents tab: the app has no fixed exit-document types, so uploaded
// HR documents are matched to the four named slots by keyword in their
// type/title; anything left over lands under "Other".
$docSlots = ['settlement' => [], 'interview' => [], 'relieving' => [], 'experience' => [], 'other' => []];
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

$generated = [
    'relieving'  => ['Relieving Letter', 'Generated from the exit record'],
    'experience' => ['Experience Certificate', 'Included in the relieving letter document'],
];
$uploaded = [
    'settlement' => ['Final Settlement', 'Upload the F&F statement'],
    'interview'  => ['Exit Interview', 'Upload the interview record'],
];
?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/offboarding') ?>" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Offboarding</a>
<?php if ($isOwner): ?>
<a href="<?= site_url('hr/offboarding/' . $record['id'] . '/edit') ?>" class="btn btn-light btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<a href="<?= $letterUrl ?>" target="_blank" rel="noopener" class="btn btn-light btn-sm"><i class="fas fa-file-lines me-1"></i>View Relieving Letter</a>
<?php endif; ?>
<div class="dropdown d-inline-block">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">More</button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><button type="button" class="dropdown-item" data-pf-tab="checklist"><i class="fas fa-list-check me-2 text-muted"></i>Full Checklist</button></li>
        <li><button type="button" class="dropdown-item" data-pf-tab="employment"><i class="fas fa-briefcase me-2 text-muted"></i>Employment</button></li>
        <?php if (! $isEmployeeView): ?>
        <li><button type="button" class="dropdown-item" data-pf-tab="activity"><i class="fas fa-clock-rotate-left me-2 text-muted"></i>Activity</button></li>
        <?php endif; ?>
        <?php if ($isOwner): ?>
        <li><hr class="dropdown-divider"></li>
        <li>
            <form action="<?= site_url('hr/offboarding/' . $record['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Remove this exit record? This cannot be undone.');">
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
    .sy-off-facts { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
    .sy-off-facts .value { font-size: .9rem; font-weight: 700; color: var(--sy-ink); }

    .sy-off-phase + .sy-off-phase { margin-top: 22px; }
    .sy-off-phase-title { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--sy-ink); margin-bottom: 10px; padding-bottom: 6px; border-bottom: 2px solid var(--sy-border); }

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

    /* Full Checklist/Employment/Activity are reachable only via the
       More menu — same "hidden tab strip, JS-driven panes" pattern as
       the Employee Profile page, for a consistent app-wide convention. */
    .sy-off-tabs { display: none; }
</style>

<!-- Employee header -->
<div class="sy-card sy-pf-head mb-3" style="height:auto">
    <div class="sy-card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4><?= esc($record['employee_name']) ?></h4>
                <div class="sy-pf-meta">
                    <?php if (! empty($record['employee_code'])): ?><span><i class="far fa-id-badge"></i><?= esc($record['employee_code']) ?></span><?php endif; ?>
                    <span><?= esc($record['designation'] ?: 'No designation set') ?></span>
                    <?php if (! empty($record['department_name'])): ?><span><i class="fas fa-sitemap"></i><?= esc($record['department_name']) ?></span><?php endif; ?>
                </div>
            </div>
            <span class="badge fs-6" style="background:<?= $groupColor['bg'] ?>;color:<?= $groupColor['fg'] ?>"><?= esc($groupLabel) ?></span>
        </div>
    </div>
</div>

<!-- KPI cards -->
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">PROGRESS</div><div class="sy-stat-value"><?= $pct ?>%</div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">EXIT TYPE</div><div class="sy-stat-value fs-5"><?= esc(ucfirst($record['exit_type'])) ?></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">LAST DAY</div><div class="sy-stat-value fs-5"><?= $record['last_working_day'] ? esc(date('d M Y', strtotime($record['last_working_day']))) : '—' ?></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">CURRENT STAGE</div><div class="sy-stat-value fs-5"><?= esc($stageLabels[$record['status']]) ?></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <!-- Current Action -->
        <?php if ($nextTask && ! $isRescinded): ?>
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
                    <form action="<?= site_url('hr/offboarding/' . $record['id'] . '/tasks/' . $nextTask['id']) ?>" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="btn btn-sm btn-primary text-nowrap">Complete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (count($pending) > 1): ?>
            <div class="small text-muted mt-2">+<?= count($pending) - 1 ?> more pending — see the full checklist under More.</div>
            <?php endif; ?>
        </div>
        <?php elseif (! $isRescinded && $total > 0): ?>
        <div class="mb-3"><p class="text-success small fw-semibold mb-0"><i class="fas fa-circle-check me-1"></i>No pending actions — every checklist item is done.</p></div>
        <?php endif; ?>

        <!-- Exit Documents — kept on the main page (not behind More)
             since final settlement/handover paperwork is core to what
             this page needs to answer, unlike the generic task
             checklist (see More → Full Checklist). -->
        <div class="sy-card" style="height:auto">
            <div class="sy-card-header"><strong>Exit Documents</strong>
                <?php if ($isOwner): ?><a href="<?= $uploadUrl ?>">Upload</a><?php endif; ?>
            </div>
            <div class="sy-card-body">
                <?php foreach ($generated as $key => [$name, $hint]): ?>
                    <div class="sy-off-doc">
                        <div>
                            <div class="fw-semibold small"><?= esc($name) ?></div>
                            <div class="sy-off-meta"><?= esc($hint) ?></div>
                            <?php foreach ($docSlots[$key] as $doc): ?>
                                <div class="small"><a href="<?= site_url('files/download?path=' . urlencode($doc['file_path'])) ?>" class="text-decoration-none"><i class="fas fa-file-arrow-down me-1"></i><?= esc($doc['title']) ?></a></div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($isOwner): ?>
                        <a href="<?= $letterUrl ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">View</a>
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

        <?php if ($isOwner && ! $isRescinded && $record['status'] !== 'exit_completed'): ?>
        <div class="sy-card mt-3" style="height:auto">
            <div class="sy-card-header"><strong>Stage Controls</strong></div>
            <div class="sy-card-body d-flex flex-wrap gap-2 align-items-center">
                <?php if ($nextStatus): ?>
                <form action="<?= site_url('hr/offboarding/' . $record['id'] . '/status') ?>" method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="<?= $nextStatus ?>">
                    <button type="submit" class="btn btn-sm btn-primary">Advance to "<?= esc($stageLabels[$nextStatus]) ?>"</button>
                </form>
                <?php endif; ?>
                <form action="<?= site_url('hr/offboarding/' . $record['id'] . '/status') ?>" method="post" class="d-inline-flex gap-1">
                    <?= csrf_field() ?>
                    <select name="status" class="form-select form-select-sm">
                        <?php foreach ($stages as $s): ?>
                            <option value="<?= $s ?>" <?= $record['status'] === $s ? 'selected' : '' ?>><?= esc($stageLabels[$s]) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Set Stage</button>
                </form>
                <form action="<?= site_url('hr/offboarding/' . $record['id'] . '/status') ?>" method="post" class="d-inline" onsubmit="return confirm('Rescind this exit? The employee will remain active.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="rescinded">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Rescind</button>
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
                <div class="sy-pf-row"><span class="k">Joining Date</span><span class="v"><?= $record['date_of_joining'] ? esc(date('d/m/Y', strtotime($record['date_of_joining']))) : '—' ?></span></div>
                <div class="sy-pf-row"><span class="k">Last Working Day</span><span class="v"><?= $record['last_working_day'] ? esc(date('d/m/Y', strtotime($record['last_working_day']))) : '—' ?></span></div>
                <div class="sy-pf-row"><span class="k">Exit Type</span><span class="v"><?= esc(ucfirst($record['exit_type'])) ?></span></div>
                <div class="sy-pf-row"><span class="k">Notice Period</span><span class="v"><?= $record['notice_period_days'] !== null ? esc($record['notice_period_days']) . ' day(s)' : '—' ?></span></div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden nav-tabs strip — exists only so bootstrap's Tab JS has a
     trigger to activate; the More menu drives it via data-pf-tab. -->
<ul class="nav nav-tabs sy-off-tabs" id="offTabs" role="tablist">
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-checklist" type="button">Full Checklist</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-employment" type="button">Employment</button></li>
    <?php if (! $isEmployeeView): ?><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activity" type="button">Activity</button></li><?php endif; ?>
</ul>
<div class="tab-content">

    <!-- ============ FULL CHECKLIST (every task, by phase — reachable
         via More, not shown on the page by default) ============ -->
    <div class="tab-pane fade" id="tab-checklist" role="tabpanel">
        <div class="sy-card" style="height:auto">
            <div class="sy-card-header d-flex justify-content-between align-items-center">
                <strong>Full Checklist</strong>
                <?php if ($isOwner): ?><a href="<?= site_url('hr/offboarding/phases') ?>" class="small"><i class="fas fa-diagram-project me-1"></i>Manage Phases</a><?php endif; ?>
            </div>
            <div class="sy-card-body">
                <?php foreach ($tasksByPhase as $phase => $tasks): ?>
                    <div class="sy-off-phase">
                        <div class="sy-off-phase-title">
                            <span><?= esc(strtoupper($phase)) ?></span>
                        </div>

                        <?php if ($exitCompletedPhaseName !== null && $phase === $exitCompletedPhaseName): ?>
                            <?php $exitDone = $record['status'] === 'exit_completed'; ?>
                            <div class="sy-off-task">
                                <span class="sy-off-state <?= $exitDone ? 'done' : 'pending' ?>"><i class="fas <?= $exitDone ? 'fa-circle-check' : 'fa-circle' ?>"></i></span>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small">Exit completed</div>
                                    <div class="sy-off-meta">HR<?= $exitDone && $record['completed_at'] ? ' &middot; Completed ' . esc(date('d/m/Y', strtotime($record['completed_at']))) : ($isRescinded ? ' &middot; Rescinded' : ' &middot; Set when the stage is advanced to Exit Completed') ?></div>
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
                                            &middot; Completed <?= esc(date('d/m/Y', strtotime($task['completed_at']))) ?>
                                        <?php elseif ($task['due_date']): ?>
                                            &middot; <span class="<?= $overdueTask ? 'overdue' : '' ?>">Due <?= esc(date('d/m/Y', strtotime($task['due_date']))) ?></span>
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
            </div>
        </div>
    </div>

    <!-- ============ EMPLOYMENT ============ -->
    <div class="tab-pane fade" id="tab-employment" role="tabpanel">
        <div class="sy-card mb-3" style="height:auto">
            <div class="sy-card-header"><strong>Employment Details</strong></div>
            <div class="sy-card-body">
                <div class="row small">
                    <div class="col-md-4 mb-2"><span class="text-muted">Employee Record</span><br><a href="<?= site_url('hr/employees/' . $record['employee_profile_id']) ?>"><?= esc($record['employee_code']) ?></a></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Designation</span><br><?= esc($record['designation'] ?? '—') ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Department</span><br><?= esc($record['department_name'] ?? '—') ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Company</span><br><?= esc($record['company_name']) ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Reporting Manager</span><br><?= esc($record['manager_name'] ?? '—') ?></div>
                    <div class="col-md-4 mb-2"><span class="text-muted">Date of Joining</span><br><?= $record['date_of_joining'] ? esc(date('d/m/Y', strtotime($record['date_of_joining']))) : '—' ?></div>
                </div>
            </div>
        </div>
        <div class="sy-card" style="height:auto">
            <div class="sy-card-header"><strong>Exit Reason &amp; Completion</strong></div>
            <div class="sy-card-body">
                <div class="row small">
                    <div class="col-md-4 mb-2"><span class="text-muted">Completed On</span><br><?= $record['completed_at'] ? esc(date('d/m/Y', strtotime($record['completed_at']))) : '—' ?></div>
                    <div class="col-12 mb-2"><span class="text-muted">Reason</span><br><?= $record['reason'] ? nl2br(esc($record['reason'])) : '—' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ ACTIVITY ============ -->
    <?php if (! $isEmployeeView): ?>
    <div class="tab-pane fade" id="tab-activity" role="tabpanel">
        <div class="sy-card" style="height:auto">
            <div class="sy-card-header"><strong>Activity</strong></div>
            <div class="sy-card-body">
                <?php if (empty($activity)): ?>
                    <p class="text-muted small mb-0">No activity recorded yet.</p>
                <?php else: ?>
                    <?php foreach ($activity as $a): ?>
                        <div class="sy-off-activity-row">
                            <div class="flex-grow-1 small"><span class="fw-semibold"><?= esc($a['user_name'] ?? 'System') ?></span> &mdash; <?= esc($a['description']) ?></div>
                            <div class="when"><?= esc(date('d/m/Y, g:i A', strtotime($a['created_at']))) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
