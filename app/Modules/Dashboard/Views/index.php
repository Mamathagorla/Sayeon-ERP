<?= $this->extend('layouts/main') ?>

<?php
/**
 * Tiny inline SVG sparkline from a genuine per-day count series (no
 * charting lib needed for a strip this small). Flat/short series still
 * render a sane line instead of dividing by zero.
 */
if (! function_exists('sy_sparkline')) {
    function sy_sparkline(array $series, string $color, int $w = 100, int $h = 30): string
    {
        $n = count($series);
        if ($n < 2) {
            return '';
        }

        $max   = max($series);
        $min   = min($series);
        $range = max($max - $min, 1);
        $stepX = $w / ($n - 1);
        $points = [];

        foreach (array_values($series) as $i => $v) {
            $x        = round($i * $stepX, 1);
            $y        = round($h - (($v - $min) / $range) * ($h - 6) - 3, 1);
            $points[] = "{$x},{$y}";
        }

        $polyline   = implode(' ', $points);
        $areaPoints = "0,{$h} {$polyline} {$w},{$h}";

        return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" height="' . $h . '" preserveAspectRatio="none">'
            . '<polyline points="' . $areaPoints . '" fill="' . $color . '" opacity="0.14" stroke="none"></polyline>'
            . '<polyline points="' . $polyline . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>'
            . '</svg>';
    }
}

$quickActions = [];
if (can('company.create')) {
    $quickActions[] = ['icon' => 'fa-building', 'label' => 'Add Company', 'href' => site_url('companies/create')];
}
if (can('task.create')) {
    $quickActions[] = ['icon' => 'fa-list-check', 'label' => 'Create Task', 'href' => site_url('tasks/create')];
}
if (can('employee.create')) {
    $quickActions[] = ['icon' => 'fa-user-plus', 'label' => 'Add Employee', 'href' => site_url('hr/employees/create')];
}
if (can('attendance.view')) {
    $quickActions[] = ['icon' => 'fa-clock', 'label' => 'Mark Attendance', 'href' => site_url('hr/attendance')];
}
if (can('task.view') || can('meeting.view') || can('compliance.view')) {
    $quickActions[] = ['icon' => 'fa-calendar-days', 'label' => 'View Calendar', 'href' => site_url('calendar')];
}

$rowAItems = [];
if (! empty($priorities)) {
    $rowAItems[] = 'priorities';
}
if ($canViewMeetings) {
    $rowAItems[] = 'meetings';
}
if (! empty($quickActions)) {
    $rowAItems[] = 'actions';
}

$rowBItems = [];
if ($canViewCompanyProgress) {
    $rowBItems[] = 'progress';
}
if ($canViewExpenses) {
    $rowBItems[] = 'expenses';
}
if ($canViewCompliance) {
    $rowBItems[] = 'compliance';
}

$priorityBadge = ['High' => 'danger', 'Medium' => 'warning', 'Low' => 'secondary'];
?>

<?= $this->section('content') ?>
<?php
    // The sy-stat-card, sy-card, sy-quick-btn, sy-priority-item,
    // sy-list-item and sy-week-chip component classes used below are
    // now defined globally in public/assets/css/sayeon-theme.css
    // (loaded by the layout) so other pages can reuse them too — this
    // page's own copy of that CSS was removed as a duplicate, not a
    // design change.
?>

<?php if ($noCompanyAssigned): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
    <i class="fas fa-triangle-exclamation"></i>
    <div>No company assigned — contact your administrator.</div>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <p class="text-muted mb-0">Welcome back, <strong><?= esc(session('userName')) ?></strong>! Here's what's happening today.</p>
    <span class="sy-week-chip"><i class="fas fa-calendar-days me-2" style="color:var(--sy-accent-ink)"></i><?= esc($weekRangeLabel) ?></span>
</div>

<div class="row row-cols-2 row-cols-lg-5 g-3 mb-4">
    <?php if ($canViewTasks): ?>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:#fdeced;color:#cc1f2c"><i class="fas fa-list-check"></i></div>
            <div class="sy-stat-label"><?= $isPersonalScope ? 'MY ACTIVE TASKS' : 'ACTIVE TASKS' ?></div>
            <div class="sy-stat-value"><?= $activeTasks ?></div>
            <?php if ($taskTrend): ?>
                <div class="sy-stat-trend">
                    <i class="fas fa-arrow-<?= $taskTrend['direction'] ?>"></i> <?= abs($taskTrend['percent']) ?>% from last week
                </div>
            <?php endif; ?>
            <div class="sy-stat-spark"><?= sy_sparkline($activeTasksSeries, '#d62431') ?></div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:#fdeced;color:#cc1f2c"><i class="fas fa-circle-check"></i></div>
            <div class="sy-stat-label"><?= $isPersonalScope ? 'MY COMPLETED TASKS' : 'COMPLETED TASKS' ?></div>
            <div class="sy-stat-value"><?= $completedTasks ?></div>
            <div class="sy-stat-trend">Last 7 days trend</div>
            <div class="sy-stat-spark"><?= sy_sparkline($completedTasksSeries, '#d62431') ?></div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-clock"></i></div>
            <div class="sy-stat-label"><?= $isPersonalScope ? 'MY OVERDUE TASKS' : 'OVERDUE TASKS' ?></div>
            <div class="sy-stat-value"><?= $overdueTasks ?></div>
            <div class="sy-stat-trend">As of today</div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($canViewMeetings): ?>
    <div class="col">
        <a href="<?= site_url('meetings?upcoming=1') ?>" class="text-decoration-none">
            <div class="sy-stat-card sy-hero">
                <div class="sy-stat-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-calendar-check"></i></div>
                <div class="sy-stat-label"><?= $isPersonalScope ? 'MY UPCOMING MEETINGS' : 'UPCOMING MEETINGS' ?></div>
                <div class="sy-stat-value"><?= $upcomingMeetings ?></div>
                <?php if ($meetingTrend): ?>
                    <div class="sy-stat-trend">
                        <i class="fas fa-arrow-<?= $meetingTrend['direction'] ?>"></i> <?= abs($meetingTrend['percent']) ?>% from last week
                    </div>
                <?php endif; ?>
                <div class="sy-stat-spark"><?= sy_sparkline($meetingsSeries, '#d62431') ?></div>
            </div>
        </a>
    </div>
    <?php endif; ?>
    <?php if ($canViewCompliance): ?>
    <div class="col">
        <a href="<?= site_url('compliance?status=overdue') ?>" class="text-decoration-none">
            <div class="sy-stat-card">
                <div class="sy-stat-icon" style="background:#ffedd5;color:#d97706"><i class="fas fa-shield-halved"></i></div>
                <div class="sy-stat-label">COMPLIANCE ALERTS</div>
                <div class="sy-stat-value"><?= $complianceAlertCount ?></div>
                <div class="sy-stat-trend text-warning">Due within 7 days</div>
                <div class="sy-stat-spark"><?= sy_sparkline($complianceSeries, '#d97706') ?></div>
            </div>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if (! empty($rowAItems)): ?>
<div class="row row-cols-1 row-cols-lg-<?= count($rowAItems) ?> g-3 mb-3">
    <?php if (! empty($priorities)): ?>
    <div class="col">
        <div class="sy-card h-100">
            <div class="sy-card-header"><strong>Today's Priorities</strong></div>
            <div class="sy-card-body sy-priorities-body">
                <?php $dotColor = ['red' => '#dc2626', 'orange' => '#d97706', 'blue' => '#2563eb', 'purple' => '#5b6472']; ?>
                <?php foreach ($priorities as $p): ?>
                    <div class="sy-priority-item">
                        <span class="sy-priority-dot" style="background: <?= $dotColor[$p['color']] ?? '#8592a3' ?>"></span>
                        <div class="flex-grow-1">
                            <div class="title"><a href="<?= $p['link'] ?>" class="text-decoration-none text-dark"><?= esc($p['title']) ?></a></div>
                            <div class="subtitle"><?= esc($p['subtitle']) ?></div>
                        </div>
                        <span class="badge bg-<?= $priorityBadge[$p['priority']] ?? 'secondary' ?> align-self-start"><?= esc($p['priority']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($canViewMeetings): ?>
    <div class="col">
        <div class="sy-card">
            <div class="sy-card-header"><strong><?= $isPersonalScope ? 'My Next Meetings' : 'Next Meetings' ?></strong> <a href="<?= site_url('meetings?upcoming=1') ?>">View Calendar</a></div>
            <div class="sy-card-body">
                <?php foreach ($nextMeetings as $m): ?>
                    <div class="sy-list-item">
                        <div class="d-flex justify-content-between">
                            <a href="<?= site_url('meetings/' . $m['id']) ?>" class="text-decoration-none fw-semibold"><?= esc($m['title']) ?></a>
                            <span class="badge bg-light text-dark border"><?= esc($m['relative']) ?></span>
                        </div>
                        <div class="muted"><?= esc($m['company_name']) ?> &middot; <?= date('d/m/Y', strtotime($m['meeting_date'])) ?><?= $m['start_time'] ? ', ' . date('g:i A', strtotime($m['start_time'])) : '' ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($nextMeetings)): ?><p class="text-muted small mb-0">No upcoming meetings scheduled.</p><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (! empty($quickActions)): ?>
    <div class="col">
        <div class="sy-card">
            <div class="sy-card-header"><strong>Quick Actions</strong></div>
            <div class="sy-card-body">
                <div class="sy-quick-grid">
                    <?php foreach ($quickActions as $qa): ?>
                        <a href="<?= $qa['href'] ?>" class="sy-quick-btn"><i class="fas <?= $qa['icon'] ?>"></i><?= esc($qa['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (! empty($rowBItems)): ?>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-<?= count($rowBItems) ?> g-3">
    <?php if ($canViewCompanyProgress): ?>
    <div class="col">
        <div class="sy-card">
            <div class="sy-card-header"><strong>Company-wise Progress</strong> <a href="<?= site_url('companies') ?>">View All</a></div>
            <div class="sy-card-body">
                <?php $palette = ['#d62431', '#5b6472', '#f28b90', '#aab1bd', '#d97706', '#8a929e']; ?>
                <?php foreach ($companyProgress as $i => $cp): ?>
                    <div class="sy-progress-row">
                        <div class="d-flex justify-content-between small">
                            <span><?= esc($cp['name']) ?></span>
                            <span class="fw-semibold"><?= $cp['percent'] ?>%</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="width: <?= $cp['percent'] ?>%; background: <?= $palette[$i % count($palette)] ?>;"></div></div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($companyProgress)): ?><p class="text-muted small mb-0">No companies yet.</p><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($canViewExpenses): ?>
    <div class="col">
        <div class="sy-card">
            <div class="sy-card-header"><strong>Monthly Expenses</strong> <a href="<?= site_url('expenses') ?>">View Report</a></div>
            <div class="sy-card-body">
                <div class="fs-4 fw-bold text-body"><?= number_format($monthlyExpenseTotal, 2) ?></div>
                <div class="text-muted small mb-2">Active subscriptions, normalized to a monthly figure</div>
                <?php foreach ($expenseByCategory as $ec): ?>
                    <div class="d-flex justify-content-between small py-1">
                        <span class="text-muted"><?= esc($ec['category']) ?></span>
                        <span class="fw-semibold"><?= number_format((float) $ec['total'], 0) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($expenseByCategory)): ?><p class="text-muted small mb-0 mt-2">No active expenses yet.</p><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($canViewCompliance): ?>
    <div class="col">
        <div class="sy-card">
            <div class="sy-card-header"><strong>Compliance Alerts</strong> <a href="<?= site_url('compliance?status=overdue') ?>">View All</a></div>
            <div class="sy-card-body">
                <?php foreach ($complianceAlerts as $ca): ?>
                    <div class="sy-list-item">
                        <div class="d-flex justify-content-between">
                            <a href="<?= site_url('compliance/' . $ca['id']) ?>" class="text-decoration-none fw-semibold"><?= esc($ca['title'] ?: $ca['type_name']) ?></a>
                            <span class="badge bg-<?= $priorityBadge[$ca['priority']] ?? 'secondary' ?>"><?= esc($ca['priority']) ?></span>
                        </div>
                        <div class="muted"><?= esc($ca['company_name']) ?> &middot; due <?= date('d/m/Y', strtotime($ca['due_date'])) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($complianceAlerts)): ?><p class="text-muted small mb-0">Nothing due soon.</p><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?= $this->endSection() ?>
