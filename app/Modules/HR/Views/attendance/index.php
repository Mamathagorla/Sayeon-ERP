<?= $this->extend('layouts/main') ?>

<?php
$label = static fn (string $v): string => ucwords(str_replace('_', ' ', $v));
$statusBadge = ['present' => 'success', 'half_day' => 'warning', 'absent' => 'danger', 'on_leave' => 'info', 'holiday' => 'secondary', 'week_off' => 'secondary'];

$tabLinks = [
    'attendance' => 'Attendance',
    'summary'    => 'Summary',
    'late'       => 'Late Arrivals',
    'early'      => 'Early Departures',
];

$qs = static function (array $overrides) use ($filters, $tab) {
    $params = array_filter(array_merge($filters, ['tab' => $tab], $overrides), static fn ($v) => $v !== '' && $v !== null);

    return site_url('hr/attendance') . (empty($params) ? '' : '?' . http_build_query($params));
};

// Mini month calendar for the sidebar — each day links back into this
// same page filtered to that single date (date_from = date_to = that
// day), reusing the existing date_from/date_to filter AttendanceModel::
// filtered() already supports rather than a separate AJAX/modal path.
$firstOfMonth = new DateTime(date('Y-m-01'));
$daysInMonth  = (int) $firstOfMonth->format('t');
$startWeekday = (int) $firstOfMonth->format('w');
$todayDay     = (int) date('j');
$selectedDate = ($filters['date_from'] ?? null) && ($filters['date_from'] === ($filters['date_to'] ?? null)) ? $filters['date_from'] : null;
?>

<?= $this->section('pageActions') ?>
<?php if (session('roleSlug') !== 'super_admin'): ?>
<a href="<?= site_url('hr/attendance/mine') ?>" class="btn btn-primary btn-sm"><i class="fas fa-user-clock me-1"></i>My Attendance</a>
<?php endif; ?>
<button type="button" class="btn btn-outline-secondary btn-sm" id="attIndexExport"><i class="fas fa-download me-1"></i>Export Report</button>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
    .sy-att-tabs { border-bottom: 1px solid var(--sy-border); margin-bottom: 1rem; }
    .sy-att-tabs a { display: inline-block; padding: 8px 4px; margin-right: 22px; font-size: .88rem; font-weight: 600; color: var(--sy-muted); text-decoration: none; border-bottom: 2px solid transparent; }
    .sy-att-tabs a.active { color: var(--sy-accent-ink); border-bottom-color: var(--sy-accent-ink); }
    .sy-mini-cal { width: 100%; border-collapse: collapse; font-size: .74rem; text-align: center; }
    .sy-mini-cal th { color: var(--sy-muted); font-weight: 600; padding-bottom: 6px; }
    .sy-mini-cal td { padding: 3px 0; }
    .sy-mini-cal a, .sy-mini-cal span.sy-mini-cal-day { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; color: var(--sy-ink); text-decoration: none; }
    .sy-mini-cal a:hover { background: var(--sy-hover-bg); }
    .sy-mini-cal .today { background: var(--sy-accent-ink); color: #fff; }
    .sy-mini-cal .today:hover { background: var(--sy-accent-ink); color: #fff; }
    .sy-mini-cal .selected { background: var(--sy-accent-soft, #fde8e8); border: 1px solid var(--sy-accent-ink); }
</style>

<?php if ($isPersonalScope): ?>
<div class="alert alert-info py-2 small">Showing only your own attendance.</div>
<?php endif; ?>


<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <?php if (! $isPersonalScope): ?>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:#e0f2fe;color:#0891b2"><i class="fas fa-users"></i></div>
            <div class="sy-stat-label">TOTAL EMPLOYEES</div>
            <div class="sy-stat-value"><?= (int) $kpi['totalEmployees'] ?></div>
            <div class="sy-stat-trend">Active employees</div>
        </div>
    </div>
    <?php endif; ?>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:#dcfce7;color:#16a34a"><i class="fas fa-circle-check"></i></div>
            <div class="sy-stat-label">PRESENT TODAY</div>
            <div class="sy-stat-value"><?= $kpi['presentToday'] ?></div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:#ffedd5;color:#d97706"><i class="fas fa-user-xmark"></i></div>
            <div class="sy-stat-label">ABSENT TODAY</div>
            <div class="sy-stat-value"><?= $kpi['absentToday'] ?></div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-umbrella-beach"></i></div>
            <div class="sy-stat-label">ON LEAVE TODAY</div>
            <div class="sy-stat-value"><?= $kpi['onLeaveToday'] ?></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="sy-att-tabs">
            <?php foreach ($tabLinks as $key => $tLabel): ?>
                <a href="<?= $qs(['tab' => $key]) ?>" class="<?= $tab === $key ? 'active' : '' ?>"><?= esc($tLabel) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="sy-card mb-3" style="height:auto">
            <div class="sy-card-body">
                <form method="get" class="row g-2 align-items-end filter-form">
                    <input type="hidden" name="tab" value="<?= esc($tab) ?>">
                    <input type="hidden" name="date_from" value="<?= esc($filters['date_from'] ?? '') ?>">
                    <input type="hidden" name="date_to" value="<?= esc($filters['date_to'] ?? '') ?>">
                    <?php if (! $isPersonalScope): ?>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Department</label>
                        <select name="department_id" class="form-select form-select-sm">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= (($filters['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= $s ?>" <?= (($filters['status'] ?? '') === $s) ? 'selected' : '' ?>><?= esc($label($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (! $isPersonalScope): ?>
                    <div class="col-6 col-md-4">
                        <label class="form-label small mb-1">Search</label>
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search employees…" value="<?= esc($filters['q'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    <div class="col-6 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Go</button>
                        <a href="<?= site_url('hr/attendance') ?>" class="btn btn-light btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="sy-card" style="height:auto">
            <div class="sy-card-body p-0">

                <?php if ($tab === 'attendance'): ?>
                    <?php if (empty($records)): ?>
                        <p class="text-muted mb-0 p-3">No attendance records match these filters.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-hover mb-0" id="attIndexTable">
                        <thead><tr>
                            <?php if (! $isPersonalScope): ?><th>Employee</th><th>Department</th><?php endif; ?>
                            <th>Date</th><th>Check In</th><th>Check Out</th><th>Working Hours</th><th>Status</th>
                            <?php if ($canSeeLogins): ?><th>Last Login</th><?php endif; ?>
                            <?php if (! $isPersonalScope): ?><th class="text-end">Action</th><?php endif; ?>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($records as $r): ?>
                            <?php
                                $mins  = ($r['check_in'] && $r['check_out']) ? max(0, (int) round((strtotime($r['check_out']) - strtotime($r['check_in'])) / 60)) : null;
                                $hours = $mins !== null ? intdiv($mins, 60) . 'h ' . ($mins % 60) . 'm' : '—';
                            ?>
                            <tr>
                                <?php if (! $isPersonalScope): ?>
                                    <td><a href="<?= site_url('hr/attendance/employee/' . $r['user_id']) ?>" title="View full attendance history"><?= esc($r['user_name']) ?></a></td>
                                    <td class="text-muted small"><?= esc($r['department_name'] ?? '—') ?></td>
                                <?php endif; ?>
                                <td><?= esc(erp_date($r['date'])) ?></td>
                                <td><?= $r['check_in'] ? esc(erp_time($r['check_in'])) : '—' ?></td>
                                <td><?= $r['check_out'] ? esc(erp_time($r['check_out'])) : '—' ?></td>
                                <td><?= esc($hours) ?></td>
                                <td><span class="badge bg-<?= $statusBadge[$r['status']] ?? 'secondary' ?>"><?= esc($label($r['status'])) ?></span></td>
                                <?php if ($canSeeLogins): ?>
                                    <td class="text-muted small"><?= $r['last_login_at'] ? esc(erp_date($r['last_login_at']) . ", " . erp_time($r['last_login_at'])) : '—' ?></td>
                                <?php endif; ?>
                                <?php if (! $isPersonalScope): ?>
                                    <td class="text-end"><a href="<?= site_url('hr/attendance/employee/' . $r['user_id']) ?>" class="btn btn-sm btn-outline-secondary" title="View history"><i class="fas fa-eye"></i></a></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>

                <?php elseif ($tab === 'summary'): ?>
                    <?php if (empty($summaryByUser)): ?>
                        <p class="text-muted mb-0 p-3">No records to summarize for these filters.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Employee</th><th>Department</th><th>Present</th><th>Late</th><th>Absent</th><th>On Leave</th><th>Total Days</th></tr></thead>
                        <tbody>
                        <?php foreach ($summaryByUser as $userId => $s): ?>
                            <tr>
                                <td><a href="<?= site_url('hr/attendance/employee/' . $userId) ?>" title="View full attendance history"><?= esc($s['user_name']) ?></a></td>
                                <td class="text-muted small"><?= esc($s['department_name'] ?? '—') ?></td>
                                <td><?= $s['present'] ?></td>
                                <td><?= $s['late'] ?></td>
                                <td><?= $s['absent'] ?></td>
                                <td><?= $s['onLeave'] ?></td>
                                <td><?= $s['total'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>

                <?php elseif ($tab === 'late'): ?>
                    <?php if (empty($lateRecords)): ?>
                        <p class="text-muted mb-0 p-3">No late arrivals for these filters.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Employee</th><th>Department</th><th>Date</th><th>Check In</th></tr></thead>
                        <tbody>
                        <?php foreach ($lateRecords as $r): ?>
                            <tr>
                                <td><a href="<?= site_url('hr/attendance/employee/' . $r['user_id']) ?>" title="View full attendance history"><?= esc($r['user_name']) ?></a></td>
                                <td class="text-muted small"><?= esc($r['department_name'] ?? '—') ?></td>
                                <td><?= esc(erp_date($r['date'])) ?></td>
                                <td class="text-danger fw-semibold"><?= esc(erp_time($r['check_in'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <?php if (empty($earlyRecords)): ?>
                        <p class="text-muted mb-0 p-3">No early departures for these filters.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Employee</th><th>Department</th><th>Date</th><th>Check Out</th></tr></thead>
                        <tbody>
                        <?php foreach ($earlyRecords as $r): ?>
                            <tr>
                                <td><a href="<?= site_url('hr/attendance/employee/' . $r['user_id']) ?>" title="View full attendance history"><?= esc($r['user_name']) ?></a></td>
                                <td class="text-muted small"><?= esc($r['department_name'] ?? '—') ?></td>
                                <td><?= esc(erp_date($r['date'])) ?></td>
                                <td class="text-danger fw-semibold"><?= esc(erp_time($r['check_out'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="sy-card mb-3" style="height:auto">
            <div class="sy-card-header"><strong>Attendance Calendar</strong></div>
            <div class="sy-card-body">
                <div class="text-center fw-semibold small mb-2"><?= esc(date('F Y')) ?></div>
                <table class="sy-mini-cal">
                    <thead><tr><th>S</th><th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th></tr></thead>
                    <tbody>
                    <?php
                        $day = 1 - $startWeekday;
                        while ($day <= $daysInMonth):
                    ?>
                        <tr>
                        <?php for ($w = 0; $w < 7; $w++, $day++): ?>
                            <td>
                                <?php if ($day >= 1 && $day <= $daysInMonth):
                                    $cellDate = date('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                                    $classes  = trim(($day === $todayDay ? 'today ' : '') . ($cellDate === $selectedDate ? 'selected' : ''));
                                ?>
                                    <a href="<?= $qs(['date_from' => $cellDate, 'date_to' => $cellDate, 'tab' => 'attendance']) ?>" class="<?= $classes ?>" title="View attendance for <?= esc(erp_date($cellDate)) ?>"><?= $day ?></a>
                                <?php endif; ?>
                            </td>
                        <?php endfor; ?>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php if ($selectedDate): ?>
                <div class="text-center small mt-2">
                    Showing <strong><?= esc(erp_date($selectedDate)) ?></strong> only —
                    <a href="<?= $qs(['date_from' => null, 'date_to' => null]) ?>">show all</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canManage): ?>
        <div class="sy-card mb-3" style="height:auto">
            <div class="sy-card-header"><strong>Quick Actions</strong></div>
            <div class="sy-card-body d-flex flex-column gap-2">
                <a href="<?= site_url('hr/attendance/mark') ?>" class="btn btn-primary btn-sm text-start"><i class="fas fa-user-check me-2"></i>Mark Attendance</a>
                <a href="<?= site_url('hr/attendance/bulk-upload') ?>" class="btn btn-outline-secondary btn-sm text-start"><i class="fas fa-file-arrow-up me-2"></i>Bulk Upload</a>
                <button type="button" class="btn btn-outline-secondary btn-sm text-start" id="attIndexExport2"><i class="fas fa-file-lines me-2"></i>Generate Report</button>
            </div>
        </div>
        <?php endif; ?>

        <div class="sy-card" style="height:auto">
            <div class="sy-card-header"><strong>Attendance Rules</strong></div>
            <div class="sy-card-body small text-muted">
                <ul class="ps-3 mb-0">
                    <li class="mb-1">Late arrival after <?= esc(erp_time($lateAfter)) ?></li>
                    <li>Early departure before <?= esc(erp_time($earlyBefore)) ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var rows = <?= json_encode(array_map(static fn (array $r) => [
        $r['user_name'] ?? '', $r['department_name'] ?? '', erp_date($r['date']),
        $r['check_in'] ? erp_time($r['check_in']) : '', $r['check_out'] ? erp_time($r['check_out']) : '',
        ucwords(str_replace('_', ' ', $r['status'])),
    ], $records), JSON_UNESCAPED_UNICODE) ?>;
    var header = ['Employee', 'Department', 'Date', 'Check In', 'Check Out', 'Status'];

    function exportCsv() {
        var esc = function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; };
        var csv = [header].concat(rows).map(function (r) { return r.map(esc).join(','); }).join('\r\n');
        var a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
        a.download = 'attendance-report.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
    }

    var btn1 = document.getElementById('attIndexExport');
    var btn2 = document.getElementById('attIndexExport2');
    if (btn1) btn1.addEventListener('click', exportCsv);
    if (btn2) btn2.addEventListener('click', exportCsv);
});
</script>
<?= $this->endSection() ?>
