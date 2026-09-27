<?= $this->extend('layouts/main') ?>

<?php
$fmt = static fn (string $d, string $f = 'd/m/Y'): string => date($f, strtotime($d));
$pct = static fn (int $done, int $total): int => $total > 0 ? (int) round($done / $total * 100) : 0;

// Action Required: each group = count, up to 4 shown, link to the page
// where HR actually acts on it.
$actions = [
    [
        'label' => 'Onboarding tasks', 'icon' => 'fa-user-plus', 'count' => count($pendingOnbTasks),
        'link' => site_url('hr/onboarding'), 'empty' => 'No pending onboarding tasks',
        'items' => array_map(static fn ($t) => ['title' => $t['title'], 'sub' => $t['candidate_name'], 'link' => site_url('hr/onboarding/' . $t['onboarding_record_id'])], array_slice($pendingOnbTasks, 0, 4)),
    ],
    [
        'label' => 'Leave approvals', 'icon' => 'fa-plane-departure', 'count' => count($pendingLeave),
        'link' => site_url('hr/leave?status=pending'), 'empty' => 'No pending leave requests',
        'items' => array_map(static fn ($l) => ['title' => $l['user_name'], 'sub' => $l['leave_type_name'] . ' · ' . $fmt($l['start_date']) . ($l['end_date'] !== $l['start_date'] ? '–' . $fmt($l['end_date']) : ''), 'link' => site_url('hr/leave?status=pending')], array_slice($pendingLeave, 0, 4)),
    ],
    [
        'label' => 'Attendance issues', 'icon' => 'fa-clock', 'count' => count($attendanceIssues),
        'link' => site_url('hr/attendance'), 'empty' => 'No attendance issues',
        'items' => array_map(static fn ($a) => ['title' => $a['name'], 'sub' => $a['detail'], 'link' => site_url('hr/attendance')], array_slice($attendanceIssues, 0, 4)),
    ],
    [
        'label' => 'Offboarding tasks', 'icon' => 'fa-person-walking-arrow-right', 'count' => count($pendingOffTasks),
        'link' => site_url('hr/offboarding'), 'empty' => 'No pending offboarding tasks',
        'items' => array_map(static fn ($t) => ['title' => $t['title'], 'sub' => $t['employee_name'], 'link' => site_url('hr/offboarding/' . $t['offboarding_record_id'])], array_slice($pendingOffTasks, 0, 4)),
    ],
];

$quick = array_filter([
    can('employee.create')   ? ['fa-user-plus',      'Add Employee',     site_url('hr/employees/create')] : null,
    can('attendance.create') ? ['fa-clock',          'Mark Attendance',  site_url('hr/attendance')] : null,
    can('leave.approve')     ? ['fa-plane-departure', 'Approve Leave',    site_url('hr/leave?status=pending')] : null,
    can('onboarding.create') ? ['fa-user-check',     'Start Onboarding', site_url('hr/onboarding/create')] : null,
    can('offboarding.create') ? ['fa-person-walking-arrow-right', 'Start Offboarding', site_url('hr/offboarding/create')] : null,
]);
?>

<?= $this->section('content') ?>

<style>
    .sy-hr-att { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .sy-hr-att > div { border-radius: var(--sy-radius-sm); padding: 12px 14px; }
    .sy-hr-att .num { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
    .sy-hr-att .lbl { font-size: .74rem; font-weight: 600; opacity: .85; }
    .sy-hr-action { padding: 14px 16px; border: 1px solid var(--sy-border-soft); border-radius: var(--sy-radius-sm); height: 100%; }
    .sy-hr-action-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .sy-hr-count { min-width: 26px; height: 26px; padding: 0 8px; border-radius: 999px; font-size: .8rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; background: var(--sy-hover-bg); color: var(--sy-muted); }
    .sy-hr-count.hot { background: var(--sy-accent-ink); color: #fff; }
    .sy-hr-row { display: block; padding: 6px 0; border-top: 1px solid var(--sy-border-soft); text-decoration: none; }
    .sy-hr-row .t { font-size: .82rem; font-weight: 600; color: var(--sy-ink) !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sy-hr-row .s { font-size: .72rem; color: var(--sy-muted); }
    .sy-hr-prog { display: flex; align-items: center; gap: 8px; }
    .sy-hr-prog .progress { flex: 1; height: 6px; }
    .sy-hr-up h6 { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--sy-muted); margin-bottom: 8px; }
    .sy-hr-up .item { display: flex; justify-content: space-between; gap: 8px; padding: 6px 0; border-top: 1px solid var(--sy-border-soft); font-size: .82rem; }
    .sy-hr-up .item .d { color: var(--sy-muted); white-space: nowrap; font-size: .76rem; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <p class="text-muted mb-0">Welcome back, <strong><?= esc(session('userName')) ?></strong> — here's your workforce at a glance.</p>
    <span class="sy-week-chip"><i class="fas fa-calendar-days me-2" style="color:var(--sy-accent-ink)"></i><?= esc(date('D, d/m/Y')) ?></span>
</div>

<?php if ($noCompanyAssigned): ?>
<div class="alert alert-warning small"><i class="fas fa-triangle-exclamation me-1"></i>No company assigned — contact your administrator.</div>
<?php endif; ?>

<!-- 1. Summary -->
<div class="row row-cols-2 row-cols-lg-5 g-3 mb-3">
    <?php foreach ([
        ['fa-users',         'TOTAL EMPLOYEES', $totalEmployees, 'Active & on leave', site_url('hr/employees')],
        ['fa-user-check',    'PRESENT TODAY',   $presentToday,   'Checked in', site_url('hr/attendance')],
        ['fa-plane-departure', 'ON LEAVE',      $onLeaveToday,   'Approved, today', site_url('hr/leave')],
        ['fa-user-plus',     'NEW JOINERS',     $newJoiners,     'Last 30 days', site_url('hr/employees')],
        ['fa-person-walking-arrow-right', 'EXITS IN PROGRESS', $exitsInProgress, 'Open offboarding', site_url('hr/offboarding')],
    ] as [$icon, $label, $value, $sub, $href]): ?>
    <div class="col">
        <a href="<?= $href ?>" class="text-decoration-none">
            <div class="sy-stat-card sy-hero">
                <div class="sy-stat-icon"><i class="fas <?= $icon ?>"></i></div>
                <div class="sy-stat-label"><?= $label ?></div>
                <div class="sy-stat-value"><?= $value ?></div>
                <div class="sy-stat-trend"><?= $sub ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- 2 + 3. Action required / Today's attendance -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="sy-card h-100">
            <div class="sy-card-header"><strong><i class="fas fa-bolt me-2" style="color:var(--sy-accent-ink)"></i>Action Required</strong></div>
            <div class="sy-card-body">
                <div class="row g-3">
                    <?php foreach ($actions as $a): ?>
                    <div class="col-md-6">
                        <div class="sy-hr-action">
                            <div class="sy-hr-action-head">
                                <a href="<?= $a['link'] ?>" class="text-decoration-none fw-semibold small" style="color:var(--sy-ink) !important"><i class="fas <?= $a['icon'] ?> me-2 text-muted"></i><?= esc($a['label']) ?></a>
                                <span class="sy-hr-count <?= $a['count'] > 0 ? 'hot' : '' ?>"><?= $a['count'] ?></span>
                            </div>
                            <?php if (empty($a['items'])): ?>
                                <div class="small text-muted"><i class="fas fa-circle-check text-success me-1"></i><?= esc($a['empty']) ?></div>
                            <?php else: ?>
                                <?php foreach ($a['items'] as $i): ?>
                                <a href="<?= $i['link'] ?>" class="sy-hr-row">
                                    <div class="t"><?= esc($i['title']) ?></div>
                                    <div class="s"><?= esc($i['sub']) ?></div>
                                </a>
                                <?php endforeach; ?>
                                <?php if ($a['count'] > count($a['items'])): ?>
                                    <a href="<?= $a['link'] ?>" class="small d-block pt-2">+<?= $a['count'] - count($a['items']) ?> more</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="sy-card h-100">
            <div class="sy-card-header"><strong>Today's Attendance</strong> <a href="<?= site_url('hr/attendance') ?>">View</a></div>
            <div class="sy-card-body">
                <div class="sy-hr-att">
                    <div style="background:var(--sy-success-soft);color:var(--sy-success)"><div class="num"><?= $presentToday ?></div><div class="lbl">Present</div></div>
                    <div style="background:var(--sy-danger-soft);color:var(--sy-danger)"><div class="num"><?= $absentToday ?></div><div class="lbl">Absent</div></div>
                    <div style="background:var(--sy-warning-soft);color:var(--sy-warning)"><div class="num"><?= $lateToday ?></div><div class="lbl">Late (after <?= esc($lateAfter) ?>)</div></div>
                    <div style="background:var(--sy-info-soft);color:var(--sy-info)"><div class="num"><?= $onLeaveToday ?></div><div class="lbl">On Leave</div></div>
                </div>
                <div class="small text-muted mt-3"><?= $notMarked ?> employee(s) not marked yet today.</div>
            </div>
        </div>
    </div>
</div>

<!-- 4. Onboarding & Offboarding -->
<div class="row g-3 mb-3">
    <?php foreach ([
        ['Onboarding', $onbActive, 'candidate_name', 'hr/onboarding', 'Joining', 'joining_date', 'No candidates in onboarding.'],
        ['Offboarding', $offActive, 'employee_name', 'hr/offboarding', 'Last day', 'last_working_day', 'No exits in progress.'],
    ] as [$title, $rows, $nameKey, $base, $dateLabel, $dateKey, $emptyMsg]): ?>
    <div class="col-lg-6">
        <div class="sy-card h-100">
            <div class="sy-card-header"><strong><?= $title ?></strong> <a href="<?= site_url($base) ?>"><?= count($rows) ?> active · View all</a></div>
            <div class="sy-card-body">
                <?php if (empty($rows)): ?>
                    <p class="text-muted small mb-0"><?= $emptyMsg ?></p>
                <?php else: ?>
                    <?php foreach (array_slice($rows, 0, 5) as $r): ?>
                        <?php $p = $pct((int) $r['tasks_done'], (int) $r['tasks_total']); ?>
                        <a href="<?= site_url($base . '/' . $r['id']) ?>" class="sy-hr-row">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="t"><?= esc($r[$nameKey]) ?></span>
                                <span class="s text-nowrap"><?= $dateLabel ?>: <?= $r[$dateKey] ? $fmt($r[$dateKey]) : '—' ?></span>
                            </div>
                            <div class="sy-hr-prog">
                                <div class="progress"><div class="progress-bar" style="width:<?= $p ?>%;background:var(--sy-accent-ink)"></div></div>
                                <span class="s text-nowrap"><?= (int) $r['tasks_done'] ?>/<?= (int) $r['tasks_total'] ?> · <?= $p ?>%</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (count($rows) > 5): ?><a href="<?= site_url($base) ?>" class="small d-block pt-2">+<?= count($rows) - 5 ?> more</a><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- 5. Upcoming (next 30 days) -->
<div class="sy-card mb-3" style="height:auto">
    <div class="sy-card-header"><strong>Upcoming</strong> <span class="text-muted small">next 30 days</span></div>
    <div class="sy-card-body">
        <div class="row g-4 sy-hr-up">
            <div class="col-md-6 col-xl-3">
                <h6>Joining dates</h6>
                <?php foreach (array_slice($joinings, 0, 4) as $j): ?>
                    <div class="item"><span><?= esc($j['candidate_name']) ?></span><span class="d"><?= $fmt($j['joining_date']) ?></span></div>
                <?php endforeach; ?>
                <?php if (empty($joinings)): ?><div class="small text-muted">None scheduled</div><?php endif; ?>
            </div>
            <div class="col-md-6 col-xl-3">
                <h6>Last working days</h6>
                <?php foreach (array_slice($lastDays, 0, 4) as $l): ?>
                    <div class="item"><span><?= esc($l['employee_name']) ?></span><span class="d"><?= $fmt($l['last_working_day']) ?></span></div>
                <?php endforeach; ?>
                <?php if (empty($lastDays)): ?><div class="small text-muted">None scheduled</div><?php endif; ?>
            </div>
            <div class="col-md-6 col-xl-3">
                <h6>Birthdays</h6>
                <?php foreach (array_slice($birthdays, 0, 4) as $b): ?>
                    <div class="item"><span><?= esc($b['name']) ?></span><span class="d"><?= $fmt($b['date']) ?></span></div>
                <?php endforeach; ?>
                <?php if (empty($birthdays)): ?><div class="small text-muted">None (no dates of birth on file)</div><?php endif; ?>
            </div>
            <div class="col-md-6 col-xl-3">
                <h6>Upcoming leave</h6>
                <?php foreach (array_slice($upcomingLeave, 0, 4) as $l): ?>
                    <div class="item"><span><?= esc($l['user_name']) ?></span><span class="d"><?= $fmt($l['start_date']) ?><?= $l['end_date'] !== $l['start_date'] ? '–' . $fmt($l['end_date']) : '' ?></span></div>
                <?php endforeach; ?>
                <?php if (empty($upcomingLeave)): ?><div class="small text-muted">None scheduled</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 6. Quick actions -->
<?php if (! empty($quick)): ?>
<div class="sy-card" style="height:auto">
    <div class="sy-card-header"><strong>Quick Actions</strong></div>
    <div class="sy-card-body">
        <div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-2">
            <?php foreach ($quick as [$icon, $label, $href]): ?>
                <div class="col"><a href="<?= $href ?>" class="sy-quick-btn h-100"><i class="fas <?= $icon ?>"></i><?= esc($label) ?></a></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>
