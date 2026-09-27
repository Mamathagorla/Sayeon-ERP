<?= $this->extend('layouts/main') ?>

<?php
$statusColor = ['active' => 'success', 'on_leave' => 'warning', 'resigned' => 'secondary', 'terminated' => 'danger'];
$label       = static fn (?string $v): string => ucwords(str_replace('_', ' ', (string) $v));
$date        = static fn (?string $d, string $f = 'd/m/Y'): string => $d ? date($f, strtotime($d)) : '—';
$clock       = static fn (?string $t): string => $t ? date('g:i A', strtotime($t)) : '—';
$money       = static fn ($n): string => number_format((float) $n, 2);
$val         = static fn ($v): string => ($v === null || $v === '') ? '—' : esc((string) $v);
$num         = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');

// "2 yrs 3 mos" since joining, or null when there's no joining date.
$tenure = null;
if (! empty($employee['date_of_joining'])) {
    $diff   = (new DateTime($employee['date_of_joining']))->diff(new DateTime('today'));
    $parts  = array_filter([$diff->y ? $diff->y . ($diff->y > 1 ? ' yrs' : ' yr') : '', $diff->m ? $diff->m . ($diff->m > 1 ? ' mos' : ' mo') : '']);
    $tenure = $parts ? implode(' ', $parts) : ($diff->invert ? 'Not started' : 'Less than a month');
}
$age = ! empty($employee['date_of_birth']) ? (new DateTime($employee['date_of_birth']))->diff(new DateTime('today'))->y : null;

$attColor   = ['present' => 'success', 'absent' => 'danger', 'half_day' => 'warning', 'on_leave' => 'info', 'holiday' => 'secondary', 'week_off' => 'secondary'];
$leaveColor = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'];
$revColor   = ['draft' => 'secondary', 'submitted' => 'info', 'acknowledged' => 'success'];
$taskColor  = ['pending' => 'warning', 'in_progress' => 'info', 'completed' => 'success', 'skipped' => 'secondary'];
$months     = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];

// What this viewer is allowed to see (controller only loads what they may).
$hasAtt   = isset($attendance);
$hasLeave = isset($leaveRequests);
$hasPay   = ! empty($showSalary);
$hasPerf  = isset($reviews);
$hasDocs  = isset($documents);
$hasOnb   = ! empty($showOnboarding) || ! empty($showOffboarding);

// The nav-tabs strip itself is hidden (see .sy-pf-tabs CSS) — these
// still need a data-bs-toggle="tab" button each so bootstrap's Tab JS
// can find and activate the pane when a KPI chip, a compact card's
// "View Details", or the header's "More" menu asks for it via
// data-pf-tab, even though nobody ever sees the strip itself.
$tabs = ['overview' => 'Overview', 'personal' => 'Personal Details', 'employment' => 'Employment'];
if ($hasAtt || $hasLeave) { $tabs['attendance'] = 'Attendance & Leave'; }
if ($hasPay) { $tabs['payroll'] = 'Payroll'; }
if ($hasPerf) { $tabs['performance'] = 'Performance'; }
if ($hasDocs) { $tabs['documents'] = 'Documents'; }
$tabs['activity'] = 'Activity';

$taskProgress = static function (array $tasks): array {
    $done  = count(array_filter($tasks, static fn (array $t) => in_array($t['status'], ['completed', 'skipped'], true)));
    $total = count($tasks);

    return [$done, $total, $total > 0 ? (int) round($done / $total * 100) : 0];
};

$gross = $net = null;
if (! empty($salary)) {
    $gross = (float) $salary['basic'] + (float) $salary['hra'] + (float) $salary['allowances'];
    $net   = $gross - (float) $salary['deductions'];
}

// ---- Reusable fragments (used by both the Overview cards and the tab panels) ----

$donut = static function (array $m) use ($num): void {
    $total = $m['onTime'] + $m['late'] + $m['absent'] + $m['onLeave'];
    $segs  = [['Present', $m['onTime'], '#d62431'], ['Late', $m['late'], '#f5a524'], ['Absent', $m['absent'], '#4b5260'], ['On Leave', $m['onLeave'], '#b8bec9']];
    $grad  = 'conic-gradient(var(--sy-border) 0 100%)';
    if ($total > 0) {
        $acc = 0.0; $stops = [];
        foreach ($segs as [$n, $c, $col]) {
            if ($c <= 0) { continue; }
            $from = $acc; $acc += $c / $total * 100;
            $stops[] = $col . ' ' . round($from, 2) . '% ' . round($acc, 2) . '%';
        }
        $grad = 'conic-gradient(' . implode(', ', $stops) . ')';
    }
    ?>
    <div class="d-flex align-items-center gap-4 flex-wrap">
        <div class="sy-pf-donut" style="background:<?= $grad ?>">
            <div class="mid"><div class="fs-4 fw-bold lh-1"><?= $m['pct'] !== null ? (int) $m['pct'] . '%' : '—' ?></div><div class="text-muted" style="font-size:.7rem">Present</div></div>
        </div>
        <div class="sy-pf-leg flex-grow-1">
            <?php foreach ($segs as [$n, $c, $col]): ?>
                <div><span class="dot" style="background:<?= $col ?>"></span><span class="flex-grow-1"><?= $n ?></span><strong><?= (int) $c ?></strong><span class="text-muted ms-2" style="width:38px;text-align:right"><?= $total > 0 ? round($c / $total * 100) . '%' : '—' ?></span></div>
            <?php endforeach; ?>
            <div class="text-muted mt-1" style="font-size:.68rem"><?= esc($m['label']) ?></div>
        </div>
    </div>
    <?php
};

$leaveSummary = static function (array $totals, array $balances) use ($num): void {
    ?>
    <div class="sy-pf-fig mb-3">
        <div><div class="k">Total Leave</div><div class="v"><?= esc($num($totals['quota'])) ?> <small>days</small></div></div>
        <div><div class="k">Used</div><div class="v"><?= esc($num($totals['used'])) ?> <small>days</small></div></div>
        <div><div class="k">Remaining</div><div class="v"><?= esc($num($totals['remaining'])) ?> <small>days</small></div></div>
    </div>
    <?php foreach ($balances as $b): ?>
        <?php $pct = $b['quota'] > 0 ? min(100, (int) round($b['used'] / $b['quota'] * 100)) : 0; ?>
        <div class="mb-2">
            <div class="d-flex justify-content-between small"><span class="fw-semibold"><?= esc($b['name']) ?></span><span class="text-muted"><?= esc($num($b['remaining'])) ?> left of <?= esc($num($b['quota'])) ?></span></div>
            <div class="sy-pf-bar"><span style="width:<?= $pct ?>%"></span></div>
        </div>
    <?php endforeach;
};

$salaryGrid = static function (array $salary, float $gross, float $net) use ($money): void {
    ?>
    <div class="sy-pf-fig sy-pf-fig-3">
        <div><div class="k">Annual Gross (CTC)</div><div class="v"><?= esc($money($gross * 12)) ?></div></div>
        <div><div class="k">Basic</div><div class="v"><?= esc($money($salary['basic'])) ?></div></div>
        <div><div class="k">HRA</div><div class="v"><?= esc($money($salary['hra'])) ?></div></div>
        <div><div class="k">Allowances</div><div class="v"><?= esc($money($salary['allowances'])) ?></div></div>
        <div><div class="k">Deductions</div><div class="v"><?= esc($money($salary['deductions'])) ?></div></div>
        <div class="net"><div class="k">Net Salary (Monthly)</div><div class="v"><?= esc($money($net)) ?></div></div>
    </div>
    <?php
};

$stars = static function (int $rating): void {
    echo '<span class="sy-pf-stars">';
    for ($i = 1; $i <= 5; $i++) {
        echo '<i class="fas fa-star' . ($i <= $rating ? ' on' : '') . '"></i>';
    }
    echo '</span>';
};

$taskTable = static function (array $tasks) use ($label, $date, $taskColor): void {
    if ($tasks === []) { return; }
    ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Task</th><th>Owner</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($tasks as $t): ?>
                <tr>
                    <td><?= esc($t['title']) ?></td>
                    <td><?= esc($label($t['owner_role'])) ?></td>
                    <td><?= esc($date($t['due_date'])) ?></td>
                    <td><span class="badge bg-<?= $taskColor[$t['status']] ?? 'secondary' ?>"><?= esc($label($t['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};

$processBlock = static function (string $title, string $badgeColor, array $rec, array $tasks, array $stages, string $when, bool $withTasks) use ($label, $taskProgress, $taskTable): void {
    [$done, $total, $pct] = $taskProgress($tasks);
    ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
        <div><span class="fw-semibold"><?= esc($title) ?></span> <span class="badge bg-<?= $badgeColor ?> ms-1"><?= esc($stages[$rec['status']] ?? $label($rec['status'])) ?></span></div>
        <span class="text-muted small"><?= esc($when) ?></span>
    </div>
    <div class="d-flex align-items-center gap-2 mb-2">
        <div class="sy-pf-bar flex-grow-1"><span style="width:<?= $pct ?>%"></span></div>
        <span class="small text-muted text-nowrap"><?= $done ?>/<?= $total ?> tasks · <?= $pct ?>%</span>
    </div>
    <?php
    if ($withTasks) { $taskTable($tasks); }
};

$docTable = static function (array $docs, ?int $limit = null) use ($date, $label, $val): void {
    $docs = $limit ? array_slice($docs, 0, $limit) : $docs;
    ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Document</th><th>Type</th><th>Uploaded</th><th>Expires</th></tr></thead>
            <tbody>
            <?php foreach ($docs as $d): ?>
                <?php
                $expired  = ! empty($d['expiry_date']) && $d['expiry_date'] < date('Y-m-d');
                $expiring = ! empty($d['expiry_date']) && ! $expired && $d['expiry_date'] <= date('Y-m-d', strtotime('+30 days'));
                ?>
                <tr>
                    <td><a href="<?= site_url('files/download?path=' . urlencode($d['file_path'])) ?>"><i class="fas fa-file me-1"></i><?= esc($d['title']) ?></a></td>
                    <td><?= esc($d['document_type'] ?: $label($d['category'])) ?></td>
                    <td><?= esc($date($d['created_at'])) ?></td>
                    <td>
                        <?php if (empty($d['expiry_date'])): ?>—
                        <?php else: ?><span class="<?= $expired ? 'text-danger fw-semibold' : ($expiring ? 'text-warning fw-semibold' : '') ?>"><?= esc($date($d['expiry_date'])) ?></span><?= $expired ? ' <span class="badge bg-danger">Expired</span>' : '' ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};

$reviewBlock = static function (array $r, bool $full) use ($label, $date, $revColor, $stars): void {
    ?>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <div class="fw-semibold"><?= esc($r['cycle_name']) ?></div>
            <div class="text-muted small">Reviewed by <?= esc($r['reviewer_name']) ?><?= $r['submitted_at'] ? ' · ' . esc($date($r['submitted_at'])) : '' ?></div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (! empty($r['rating'])): ?><?php $stars((int) $r['rating']); ?> <span class="fw-bold"><?= (int) $r['rating'] ?> / 5</span><?php endif; ?>
            <span class="badge bg-<?= $revColor[$r['status']] ?? 'secondary' ?>"><?= esc($label($r['status'])) ?></span>
        </div>
    </div>
    <div class="row g-3 mt-0 small">
        <?php foreach (['strengths' => 'Strengths', 'improvements' => 'Areas to Improve', 'goals_next' => 'Goals Next Cycle'] as $key => $title): ?>
            <?php if (! empty($r[$key])): ?>
                <div class="<?= $full ? 'col-md-4' : 'col-12' ?>"><div class="sy-pf-sub"><?= $title ?></div><div class="<?= $full ? '' : 'sy-pf-clamp' ?>"><?= nl2br(esc($r[$key])) ?></div></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php
};

$activityList = static function (array $items) use ($label): void {
    if ($items === []) { echo '<div class="text-muted small">No recent activity recorded for this employee.</div>'; return; }
    foreach ($items as $log): ?>
        <div class="sy-pf-tl">
            <div class="when"><?= esc(date('d/m/Y', strtotime($log['created_at']))) ?><br><span><?= esc(date('g:i A', strtotime($log['created_at']))) ?></span></div>
            <div class="what"><div class="t"><?= esc($log['description']) ?></div><div class="m"><?= esc($label($log['module'])) ?><?= ! empty($log['actor_name']) ? ' · ' . esc($log['actor_name']) : '' ?></div></div>
        </div>
    <?php endforeach;
};

$stageLabels = \App\Modules\Onboarding\Models\OnboardingRecordModel::STAGE_LABELS;
$exitLabels  = \App\Modules\Offboarding\Models\OffboardingRecordModel::STAGE_LABELS;
?>

<?= $this->section('content') ?>

<style>
    .sy-pf-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin: 14px 0; }
    .sy-pf-kpi { display: flex; align-items: center; gap: 12px; width: 100%; padding: 14px; border: 1px solid var(--sy-border); border-radius: 14px; background: var(--sy-surface); text-align: left; color: var(--sy-ink); }
    .sy-pf-kpi:hover { border-color: var(--sy-accent); }
    .sy-pf-kpi .ico { flex: none; width: 42px; height: 42px; border-radius: 12px; background: var(--sy-accent-soft); color: var(--sy-accent-ink); display: flex; align-items: center; justify-content: center; }
    .sy-pf-kpi .l { font-size: .72rem; color: var(--sy-muted); font-weight: 600; }
    .sy-pf-kpi .v { font-size: 1.25rem; font-weight: 700; line-height: 1.15; }
    .sy-pf-kpi .s { font-size: .68rem; color: var(--sy-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sy-pf-kpi .go { margin-left: auto; color: var(--sy-muted-soft); font-size: .8rem; }
    .sy-pf-card { height: 100%; }
    .sy-pf-card .sy-card-header strong { font-size: .88rem; }
    .sy-pf-card .sy-card-header .act { font-size: .76rem; font-weight: 600; color: var(--sy-accent-ink); text-decoration: none; cursor: pointer; background: none; border: 0; padding: 0; }
    .sy-pf-donut { position: relative; width: 150px; height: 150px; border-radius: 50%; flex: none; }
    .sy-pf-donut::after { content: ''; position: absolute; inset: 20px; border-radius: 50%; background: var(--sy-surface); }
    .sy-pf-donut .mid { position: absolute; inset: 0; z-index: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .sy-pf-leg > div { display: flex; align-items: center; gap: 8px; padding: 3px 0; font-size: .8rem; }
    .sy-pf-leg .dot { width: 9px; height: 9px; border-radius: 50%; flex: none; }
    .sy-pf-fig { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .sy-pf-fig > div { border-radius: 10px; padding: 8px 10px; background: var(--sy-hover-bg); min-width: 0; }
    .sy-pf-fig .k { font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; color: var(--sy-muted); }
    .sy-pf-fig .v { font-size: 1.05rem; font-weight: 700; word-break: break-word; }
    .sy-pf-fig .v small { font-size: .68rem; font-weight: 600; color: var(--sy-muted); }
    .sy-pf-fig .net { background: var(--sy-accent-soft); }
    .sy-pf-fig .net .k, .sy-pf-fig .net .v { color: var(--sy-accent-ink); }
    .sy-pf-bar { height: 5px; border-radius: 5px; background: var(--sy-border-soft); overflow: hidden; margin-top: 4px; }
    .sy-pf-bar > span { display: block; height: 100%; background: var(--sy-accent); border-radius: 5px; }
    .sy-pf-stars i { font-size: .8rem; color: var(--sy-border); margin-right: 1px; }
    .sy-pf-stars i.on { color: var(--sy-accent); }
    .sy-pf-sub { font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--sy-muted); margin-bottom: 2px; }
    .sy-pf-clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .sy-pf-scroll { max-height: 340px; overflow-y: auto; }
    .sy-pf-link { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-top: 1px solid var(--sy-border-soft); font-size: .84rem; color: var(--sy-ink) !important; text-decoration: none; background: none; border-left: 0; border-right: 0; border-bottom: 0; width: 100%; text-align: left; cursor: pointer; }
    .sy-pf-link:first-child { border-top: 0; padding-top: 0; }
    .sy-pf-link .ico { flex: none; width: 22px; text-align: center; color: var(--sy-muted); }
    .sy-pf-link .go { margin-left: auto; color: var(--sy-muted-soft); font-size: .7rem; }
    .sy-pf-pend { background: var(--sy-accent-soft); border-color: transparent; }
    .sy-pf-pend .item { display: flex; gap: 10px; align-items: flex-start; padding: 7px 0; font-size: .82rem; }
    .sy-pf-pend .num { flex: none; width: 22px; height: 22px; border-radius: 50%; background: var(--sy-surface); color: var(--sy-accent-ink); font-size: .68rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
    .sy-pf-tl { display: flex; gap: 12px; padding: 0 0 12px 0; font-size: .78rem; position: relative; }
    .sy-pf-tl:last-child { padding-bottom: 0; }
    .sy-pf-tl .when { flex: none; width: 74px; color: var(--sy-muted); font-size: .7rem; line-height: 1.3; }
    .sy-pf-tl .when span { color: var(--sy-muted-soft); }
    .sy-pf-tl .what { flex: 1 1 auto; min-width: 0; padding-left: 14px; border-left: 2px solid var(--sy-border-soft); position: relative; }
    .sy-pf-tl .what::before { content: ''; position: absolute; left: -6px; top: 3px; width: 10px; height: 10px; border-radius: 50%; background: var(--sy-accent); border: 2px solid var(--sy-surface); }
    .sy-pf-tl .t { font-weight: 600; line-height: 1.3; }
    .sy-pf-tl .m { color: var(--sy-muted); font-size: .7rem; }
    .sy-pf-va { font-size: .76rem; font-weight: 600; color: var(--sy-accent-ink); text-decoration: none; background: none; border: 0; padding: 0; cursor: pointer; }
    .sy-pf-side .sy-card { height: auto; margin-bottom: 12px; }
    .sy-pf-side .sy-card-header strong { font-size: .86rem; }
    /* The tab strip only exists so bootstrap's Tab JS has something to
       drive — navigation happens via the KPI row, each compact card's
       "View Details", and the header's More menu instead. */
    .sy-pf-tabs { display: none; }
    @media print {
        .app-sidebar, .app-header, .app-content-header, .sy-pf-side, .sy-pf-tabs, .sy-pf-kpi .go, .sy-pf-noprint, .btn, .dropdown { display: none !important; }
        .tab-content > .tab-pane { display: block !important; opacity: 1 !important; margin-bottom: 16px; }
        .app-main, .app-content { overflow: visible !important; height: auto !important; }
    }
</style>

<div class="sy-pf-crumb"><a href="<?= site_url('hr/employees') ?>"><i class="fas fa-arrow-left me-1"></i>Back to Employees</a></div>

<div class="row g-3">
<!-- ================= MAIN COLUMN ================= -->
<div class="col-xl-9">

    <!-- Header card -->
    <div class="sy-card sy-pf-head" style="height:auto">
        <div class="sy-card-body">
            <div class="d-flex flex-wrap gap-3 align-items-start">
                <?php if (! empty($employee['user_avatar'])): ?>
                    <img class="sy-pf-photo" src="<?= base_url($employee['user_avatar']) ?>" alt="">
                <?php else: ?>
                    <span class="sy-avatar sy-pf-photo initial"><?= esc(mb_strtoupper(mb_substr($employee['user_name'], 0, 1))) ?></span>
                <?php endif; ?>
                <div class="flex-grow-1" style="min-width:0">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <h4><?= esc($employee['user_name']) ?></h4>
                        <span class="badge bg-<?= $statusColor[$employee['status']] ?? 'secondary' ?>"><?= esc($label($employee['status'])) ?></span>
                    </div>
                    <div class="text-muted"><?= esc($employee['designation'] ?: 'No designation set') ?></div>
                    <div class="sy-pf-meta">
                        <span><i class="far fa-id-badge"></i><?= esc($employee['employee_code']) ?></span>
                        <?php if (! empty($employee['department_name'])): ?><span><i class="fas fa-sitemap"></i><?= esc($employee['department_name']) ?></span><?php endif; ?>
                        <span><i class="fas fa-building"></i><?= esc($employee['company_name']) ?></span>
                    </div>
                    <div class="sy-pf-meta">
                        <span><i class="far fa-calendar"></i>Joined on <?= esc($date($employee['date_of_joining'])) ?><?= $tenure ? ' · ' . esc($tenure) : '' ?></span>
                        <span><i class="fas fa-user-tie"></i>Reporting Manager: <strong style="color:var(--sy-ink)"><?= esc($employee['manager_name'] ?: '—') ?></strong></span>
                        <span><i class="fas fa-user-clock"></i><?= esc($label($employee['employment_type'])) ?></span>
                    </div>
                </div>
                <div class="d-flex gap-2 sy-pf-noprint">
                    <?php if (can('employee.edit')): ?>
                        <a href="<?= site_url('hr/employees/' . $employee['id'] . '/edit') ?>" class="btn btn-primary btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
                    <?php endif; ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">More</button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><button type="button" class="dropdown-item" data-pf-tab="personal"><i class="fas fa-id-card me-2 text-muted"></i>Personal Details</button></li>
                            <?php if ($hasDocs): ?>
                                <li><button type="button" class="dropdown-item" data-pf-tab="documents"><i class="fas fa-folder-open me-2 text-muted"></i>Documents</button></li>
                            <?php endif; ?>
                            <?php if ($hasPerf): ?>
                                <li><button type="button" class="dropdown-item" data-pf-tab="performance"><i class="fas fa-chart-line me-2 text-muted"></i>Performance</button></li>
                            <?php endif; ?>
                            <li><button type="button" class="dropdown-item" data-pf-tab="activity"><i class="fas fa-clock-rotate-left me-2 text-muted"></i>Activity</button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button type="button" class="dropdown-item" onclick="window.print()"><i class="fas fa-print me-2 text-muted"></i>Print profile</button></li>
                            <?php if (! empty($employee['user_email'])): ?>
                                <li><a class="dropdown-item" href="mailto:<?= esc($employee['user_email']) ?>"><i class="far fa-envelope me-2 text-muted"></i>Email employee</a></li>
                            <?php endif; ?>
                            <?php if (can('employee.delete')): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="<?= site_url('hr/employees/' . $employee['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Delete this employee profile?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete employee</button>
                                    </form>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI cards -->
    <?php if (! empty($metrics)): ?>
    <div class="sy-pf-kpis">
        <?php foreach ($metrics as [$icon, $mLabel, $mValue, $mSub, $mTab]): ?>
        <button type="button" class="sy-pf-kpi" data-pf-tab="<?= esc($mTab) ?>">
            <span class="ico"><i class="fas <?= $icon ?>"></i></span>
            <span style="min-width:0">
                <span class="l d-block"><?= esc($mLabel) ?></span>
                <span class="v d-block"><?= esc($mValue) ?></span>
                <span class="s d-block"><?= esc($mSub) ?></span>
            </span>
            <i class="fas fa-chevron-right go"></i>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs sy-pf-tabs" id="pfTabs" role="tablist">
        <?php foreach ($tabs as $key => $tabLabel): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $key === 'overview' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#pf-<?= $key ?>" type="button" role="tab"><?= esc($tabLabel) ?></button>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content pt-3">

        <!-- ================= OVERVIEW (compact 2x2) ================= -->
        <div class="tab-pane fade show active" id="pf-overview" role="tabpanel">

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="sy-card sy-pf-card">
                        <div class="sy-card-header"><strong><i class="fas fa-briefcase me-2" style="color:var(--sy-accent-ink)"></i>Employment</strong>
                            <button type="button" class="act" data-pf-tab="employment">View Details</button></div>
                        <div class="sy-card-body">
                            <div class="sy-pf-row"><span class="k">Designation</span><span class="v"><?= $val($employee['designation']) ?></span></div>
                            <div class="sy-pf-row"><span class="k">Department</span><span class="v"><?= $val($employee['department_name']) ?></span></div>
                            <div class="sy-pf-row"><span class="k">Employment Type</span><span class="v"><?= esc($label($employee['employment_type'])) ?></span></div>
                            <div class="sy-pf-row"><span class="k">Joining Date</span><span class="v"><?= esc($date($employee['date_of_joining'])) ?></span></div>
                            <div class="sy-pf-row"><span class="k">Reporting Manager</span><span class="v"><?= $val($employee['manager_name']) ?></span></div>
                        </div>
                    </div>
                </div>

                <?php if ($hasAtt || $hasLeave): ?>
                <div class="col-md-6">
                    <div class="sy-card sy-pf-card">
                        <div class="sy-card-header"><strong><i class="fas fa-calendar-check me-2" style="color:var(--sy-accent-ink)"></i>Attendance &amp; Leave</strong>
                            <button type="button" class="act" data-pf-tab="attendance">View Details</button></div>
                        <div class="sy-card-body">
                            <div class="sy-pf-fig" style="grid-template-columns:repeat(<?= ($hasAtt && $hasLeave) ? 2 : 1 ?>, 1fr)">
                                <?php if ($hasAtt): ?>
                                    <div><div class="k">Attendance</div><div class="v"><?= $attendanceMonth['pct'] !== null ? $attendanceMonth['pct'] . '%' : '—' ?></div><div class="text-muted" style="font-size:.68rem">This month</div></div>
                                <?php endif; ?>
                                <?php if ($hasLeave): ?>
                                    <div><div class="k">Leave Remaining</div><div class="v"><?= esc($num($leaveTotals['remaining'])) ?> <small>days</small></div><div class="text-muted" style="font-size:.68rem">This year</div></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($hasPay || ! empty($actions)): ?>
            <div class="row g-3 mb-3">
                <?php if ($hasPay): ?>
                <div class="<?= empty($actions) ? 'col-12' : 'col-md-6' ?>">
                    <div class="sy-card sy-pf-card">
                        <div class="sy-card-header"><strong><i class="fas fa-money-check-dollar me-2" style="color:var(--sy-accent-ink)"></i>Payroll</strong>
                            <button type="button" class="act" data-pf-tab="payroll">View Details</button></div>
                        <div class="sy-card-body">
                            <?php if ($salary === null): ?>
                                <div class="text-muted small">No salary structure has been set up for this employee yet.</div>
                            <?php else: ?>
                                <?php $salaryGrid($salary, $gross, $net); ?>
                                <?php if (! empty($payslips)): ?>
                                    <div class="d-flex justify-content-between align-items-center mt-3 small">
                                        <span class="text-muted">Last Payroll: <strong style="color:var(--sy-ink)"><?= esc($months[$payslips[0]['month']] . ' ' . $payslips[0]['year']) ?></strong></span>
                                        <span class="badge bg-<?= $payslips[0]['run_status'] === 'paid' ? 'success' : 'info' ?>"><?= esc($label($payslips[0]['run_status'])) ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (! empty($actions)): ?>
                <div class="<?= $hasPay ? 'col-md-6' : 'col-12' ?>">
                    <div class="sy-card sy-pf-card sy-pf-pend">
                        <div class="sy-card-header" style="border-bottom-color:transparent">
                            <strong><i class="fas fa-bell me-2" style="color:var(--sy-accent-ink)"></i>Action Required</strong>
                            <span class="badge bg-danger"><?= count($actions) ?></span>
                        </div>
                        <div class="sy-card-body" style="padding-top:0">
                            <?php foreach ($actions as $i => [$level, $icon, $text]): ?>
                                <div class="item"><span class="num"><?= $i + 1 ?></span><span><?= esc($text) ?></span></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>

        <!-- ================= PERSONAL DETAILS (More menu only) ================= -->
        <div class="tab-pane fade" id="pf-personal" role="tabpanel">
            <div class="sy-card" style="height:auto">
                <div class="sy-card-header"><strong><i class="fas fa-id-card me-2" style="color:var(--sy-accent-ink)"></i>Personal Information</strong>
                    <?php if (can('employee.edit')): ?><a class="act" href="<?= site_url('hr/employees/' . $employee['id'] . '/edit') ?>">Edit</a><?php endif; ?></div>
                <div class="sy-card-body">
                    <div class="sy-pf-row"><span class="k">Full Name</span><span class="v"><?= $val($employee['user_name']) ?></span></div>
                    <div class="sy-pf-row"><span class="k">Date of Birth</span><span class="v"><?= esc($date($employee['date_of_birth'])) ?><?= $age !== null ? ' (' . $age . ' yrs)' : '' ?></span></div>
                    <div class="sy-pf-row"><span class="k">Email</span><span class="v"><?= $val($employee['user_email']) ?></span></div>
                    <div class="sy-pf-row"><span class="k">Phone</span><span class="v"><?= $val($employee['user_phone']) ?></span></div>
                    <div class="sy-pf-row"><span class="k">Address</span><span class="v"><?= $val($employee['address']) ?></span></div>
                    <div class="sy-pf-row"><span class="k">Emergency Contact</span><span class="v">
                        <?php if (! empty($employee['emergency_contact_name']) || ! empty($employee['emergency_contact_phone'])): ?>
                            <?= esc($employee['emergency_contact_name'] ?? '') ?><?= ! empty($employee['emergency_contact_phone']) ? ' (' . esc($employee['emergency_contact_phone']) . ')' : '' ?>
                        <?php else: ?>—<?php endif; ?></span></div>
                </div>
            </div>
        </div>

        <!-- ================= EMPLOYMENT ================= -->
        <div class="tab-pane fade" id="pf-employment" role="tabpanel">
            <div class="sy-card mb-3" style="height:auto">
                <div class="sy-card-header"><strong><i class="fas fa-briefcase me-2" style="color:var(--sy-accent-ink)"></i>Employment Details</strong></div>
                <div class="sy-card-body">
                    <div class="row g-0 column-gap-4">
                        <div class="col-md">
                            <div class="sy-pf-row"><span class="k">Employee Code</span><span class="v"><?= $val($employee['employee_code']) ?></span></div>
                            <div class="sy-pf-row"><span class="k">Company</span><span class="v"><?= $val($employee['company_name']) ?></span></div>
                            <div class="sy-pf-row"><span class="k">Department</span><span class="v"><?= $val($employee['department_name']) ?></span></div>
                            <div class="sy-pf-row"><span class="k">Designation</span><span class="v"><?= $val($employee['designation']) ?></span></div>
                        </div>
                        <div class="col-md">
                            <div class="sy-pf-row"><span class="k">Employment Type</span><span class="v"><?= esc($label($employee['employment_type'])) ?></span></div>
                            <div class="sy-pf-row"><span class="k">Joining Date</span><span class="v"><?= esc($date($employee['date_of_joining'])) ?></span></div>
                            <div class="sy-pf-row"><span class="k">Tenure</span><span class="v"><?= $val($tenure) ?></span></div>
                            <div class="sy-pf-row"><span class="k">Reporting Manager</span><span class="v"><?= $val($employee['manager_name']) ?></span></div>
                            <div class="sy-pf-row"><span class="k">Status</span><span class="v"><span class="badge bg-<?= $statusColor[$employee['status']] ?? 'secondary' ?>"><?= esc($label($employee['status'])) ?></span></span></div>
                            <?php if (! empty($onboarding['probation_end_date'])): ?><div class="sy-pf-row"><span class="k">Probation Ends</span><span class="v"><?= esc($date($onboarding['probation_end_date'])) ?></span></div><?php endif; ?>
                            <?php if (! empty($onboarding['confirmed_at'])): ?><div class="sy-pf-row"><span class="k">Confirmed On</span><span class="v"><?= esc($date($onboarding['confirmed_at'])) ?></span></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($hasOnb): ?>
            <div class="sy-card" style="height:auto">
                <div class="sy-card-header"><strong><i class="fas fa-user-plus me-2" style="color:var(--sy-accent-ink)"></i>Onboarding &amp; Training</strong></div>
                <div class="sy-card-body">
                    <?php $shown = false; ?>
                    <?php if (! empty($onboarding)): $shown = true; ?>
                        <?php $processBlock('Onboarding', 'info', $onboarding, $onboardingTasks, $stageLabels, 'Joining ' . $date($onboarding['joining_date']), true); ?>
                    <?php endif; ?>
                    <?php if (! empty($offboarding)): $shown = true; ?>
                        <div class="<?= ! empty($onboarding) ? 'mt-4 pt-3 border-top' : '' ?>">
                            <?php $processBlock('Exit Process', 'danger', $offboarding, $offboardingTasks, $exitLabels, $label($offboarding['exit_type']) . ' · Last day ' . $date($offboarding['last_working_day']), true); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (! $shown): ?><div class="text-muted small">No onboarding or exit record is linked to this employee.</div><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ================= ATTENDANCE & LEAVE ================= -->
        <?php if (isset($tabs['attendance'])): ?>
        <div class="tab-pane fade" id="pf-attendance" role="tabpanel">
            <div class="row g-3 mb-3">
                <?php if ($hasAtt): ?>
                <div class="<?= $hasLeave ? 'col-md-6' : 'col-12' ?>">
                    <div class="sy-card sy-pf-card"><div class="sy-card-header"><strong>Attendance Summary</strong></div><div class="sy-card-body"><?php $donut($attendanceMonth); ?></div></div>
                </div>
                <?php endif; ?>
                <?php if ($hasLeave): ?>
                <div class="<?= $hasAtt ? 'col-md-6' : 'col-12' ?>">
                    <div class="sy-card sy-pf-card"><div class="sy-card-header"><strong>Leave Summary</strong> <span class="text-muted small"><?= esc(date('Y')) ?></span></div><div class="sy-card-body"><?php $leaveSummary($leaveTotals, $leaveBalances); ?></div></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($hasAtt): ?>
            <div class="sy-card mb-3" style="height:auto">
                <div class="sy-card-header"><strong>Attendance Records</strong> <span class="text-muted small">latest <?= count($attendance) ?></span></div>
                <div class="sy-card-body">
                    <?php if (empty($attendance)): ?>
                        <div class="text-muted small">No attendance recorded yet.</div>
                    <?php else: ?>
                    <div class="table-responsive sy-pf-scroll">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Date</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Notes</th></tr></thead>
                            <tbody>
                            <?php foreach ($attendance as $a): ?>
                                <?php $mins = ($a['check_in'] && $a['check_out']) ? max(0, (int) round((strtotime($a['check_out']) - strtotime($a['check_in'])) / 60)) : null; ?>
                                <tr>
                                    <td><?= esc($date($a['date'])) ?></td>
                                    <td><span class="badge bg-<?= $attColor[$a['status']] ?? 'secondary' ?>"><?= esc($label($a['status'])) ?></span></td>
                                    <td><?= esc($clock($a['check_in'])) ?></td>
                                    <td><?= esc($clock($a['check_out'])) ?></td>
                                    <td><?= $mins !== null ? intdiv($mins, 60) . 'h ' . ($mins % 60) . 'm' : '—' ?></td>
                                    <td class="text-muted small"><?= $val($a['notes']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($hasLeave): ?>
            <div class="sy-card" style="height:auto">
                <div class="sy-card-header"><strong>Leave Requests</strong> <span class="text-muted small">latest <?= count($leaveRequests) ?></span></div>
                <div class="sy-card-body">
                    <?php if (empty($leaveRequests)): ?>
                        <div class="text-muted small">No leave requests yet.</div>
                    <?php else: ?>
                    <div class="table-responsive sy-pf-scroll">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Reason</th><th>Decided By</th></tr></thead>
                            <tbody>
                            <?php foreach ($leaveRequests as $l): ?>
                                <tr>
                                    <td><?= esc($l['leave_type_name']) ?></td>
                                    <td><?= esc($date($l['start_date'])) ?></td>
                                    <td><?= esc($date($l['end_date'])) ?></td>
                                    <td><?= esc($num($l['days'])) ?></td>
                                    <td><span class="badge bg-<?= $leaveColor[$l['status']] ?? 'secondary' ?>"><?= esc($label($l['status'])) ?></span></td>
                                    <td class="text-muted small"><?= $val($l['reason']) ?></td>
                                    <td class="text-muted small"><?= $val($l['approver_name'] ?? null) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ================= PAYROLL ================= -->
        <?php if ($hasPay): ?>
        <div class="tab-pane fade" id="pf-payroll" role="tabpanel">
            <div class="sy-card mb-3" style="height:auto">
                <div class="sy-card-header"><strong>Salary Structure</strong>
                    <a href="<?= site_url('hr/payroll/structure/' . $employee['user_id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i><?= $salary ? 'Edit Salary Structure' : 'Set Up Salary Structure' ?></a></div>
                <div class="sy-card-body">
                    <?php if ($salary === null): ?>
                        <div class="text-muted small">No salary structure has been set up for this employee yet.</div>
                    <?php else: ?>
                        <?php $salaryGrid($salary, $gross, $net); ?>
                        <div class="text-muted small mt-2">Effective from <?= esc($date($salary['effective_from'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sy-card" style="height:auto">
                <div class="sy-card-header"><strong>Payslips</strong> <span class="text-muted small">latest <?= count($payslips) ?></span></div>
                <div class="sy-card-body">
                    <?php if (empty($payslips)): ?>
                        <div class="text-muted small">No payslips generated yet.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Period</th><th class="text-end">Basic</th><th class="text-end">HRA</th><th class="text-end">Allowances</th><th class="text-end">Deductions</th><th class="text-end">Gross</th><th class="text-end">Net</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($payslips as $p): ?>
                                <tr>
                                    <td><?= esc($months[$p['month']] . ' ' . $p['year']) ?></td>
                                    <td class="text-end"><?= esc($money($p['basic'])) ?></td>
                                    <td class="text-end"><?= esc($money($p['hra'])) ?></td>
                                    <td class="text-end"><?= esc($money($p['allowances'])) ?></td>
                                    <td class="text-end"><?= esc($money($p['deductions'])) ?></td>
                                    <td class="text-end"><?= esc($money($p['gross'])) ?></td>
                                    <td class="text-end fw-semibold"><?= esc($money($p['net'])) ?></td>
                                    <td><span class="badge bg-<?= $p['run_status'] === 'paid' ? 'success' : 'info' ?>"><?= esc($label($p['run_status'])) ?></span></td>
                                    <td><a href="<?= site_url('hr/payroll/payslip/' . $p['id']) ?>">Payslip</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ================= PERFORMANCE ================= -->
        <?php if ($hasPerf): ?>
        <div class="tab-pane fade" id="pf-performance" role="tabpanel">
            <div class="sy-card" style="height:auto">
                <div class="sy-card-header"><strong>Performance Reviews</strong> <span class="text-muted small">latest <?= count($reviews) ?></span></div>
                <div class="sy-card-body">
                    <?php if (empty($reviews)): ?>
                        <div class="text-muted small">No performance reviews yet.</div>
                    <?php endif; ?>
                    <?php foreach ($reviews as $i => $r): ?>
                        <div class="<?= $i > 0 ? 'border-top pt-3 mt-3' : '' ?>"><?php $reviewBlock($r, true); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ================= DOCUMENTS ================= -->
        <?php if ($hasDocs): ?>
        <div class="tab-pane fade" id="pf-documents" role="tabpanel">
            <div class="sy-card" style="height:auto">
                <div class="sy-card-header"><strong>Documents</strong> <span class="text-muted small"><?= count($documents) ?> on file</span></div>
                <div class="sy-card-body">
                    <?php if (empty($documents)): ?>
                        <div class="text-muted small">No documents on file for this employee.</div>
                    <?php else: ?>
                        <?php $docTable($documents); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ================= ACTIVITY ================= -->
        <div class="tab-pane fade" id="pf-activity" role="tabpanel">
            <div class="sy-card" style="height:auto">
                <div class="sy-card-header"><strong>Activity</strong> <span class="text-muted small">latest <?= count($activity) ?></span></div>
                <div class="sy-card-body"><?php $activityList($activity); ?></div>
            </div>
        </div>

    </div>
</div>

<!-- ================= SIDEBAR ================= -->
<div class="col-xl-3 sy-pf-side">
    <?php if ($hasLeave): ?>
    <div class="sy-card">
        <div class="sy-card-header"><strong>Upcoming Leave</strong> <button type="button" class="sy-pf-va" data-pf-tab="attendance">View All</button></div>
        <div class="sy-card-body">
            <?php if (empty($upcomingLeave)): ?>
                <div class="small text-muted">No upcoming leave.</div>
            <?php endif; ?>
            <?php foreach ($upcomingLeave as $l): ?>
                <div class="d-flex justify-content-between align-items-center gap-2 py-2 border-top small" style="border-color:var(--sy-border-soft)!important">
                    <div><div class="fw-semibold"><?= esc($date($l['start_date'])) ?><?= $l['end_date'] !== $l['start_date'] ? ' – ' . esc($date($l['end_date'])) : '' ?></div><div class="text-muted" style="font-size:.7rem"><?= esc($l['leave_type_name']) ?></div></div>
                    <span class="badge bg-<?= $leaveColor[$l['status']] ?? 'secondary' ?>"><?= esc($label($l['status'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="sy-card">
        <div class="sy-card-header"><strong>Quick Links</strong></div>
        <div class="sy-card-body">
            <button type="button" class="sy-pf-link" onclick="window.print()"><span class="ico"><i class="fas fa-print"></i></span>Print Employee Report<i class="fas fa-chevron-right go"></i></button>
            <?php if ($hasPay && ! empty($payslips)): ?>
                <a class="sy-pf-link" href="<?= site_url('hr/payroll/payslip/' . $payslips[0]['id']) ?>"><span class="ico"><i class="fas fa-file-invoice"></i></span>View Latest Payslip<i class="fas fa-chevron-right go"></i></a>
            <?php endif; ?>
            <a class="sy-pf-link" href="<?= site_url('hr/employees') ?>"><span class="ico"><i class="fas fa-users"></i></span>All Employees<i class="fas fa-chevron-right go"></i></a>
        </div>
    </div>
</div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    function showTab(key) {
        var btn = document.querySelector('[data-bs-target="#pf-' + key + '"]');
        if (btn) { bootstrap.Tab.getOrCreateInstance(btn).show(); }
    }
    document.querySelectorAll('[data-pf-tab]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            showTab(el.getAttribute('data-pf-tab'));
            // The tab strip itself is hidden (see .sy-pf-tabs), so scroll
            // to the pane content instead of the old nav-tabs bar.
            var content = document.querySelector('.tab-content');
            if (content) { content.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        });
    });
    document.querySelectorAll('#pfTabs [data-bs-toggle="tab"]').forEach(function (b) {
        b.addEventListener('shown.bs.tab', function () {
            try { history.replaceState(null, '', b.getAttribute('data-bs-target')); } catch (e) { }
        });
    });
    var hash = (location.hash || '').replace('#pf-', '');
    if (hash) { showTab(hash); }
})();
</script>
<?= $this->endSection() ?>
