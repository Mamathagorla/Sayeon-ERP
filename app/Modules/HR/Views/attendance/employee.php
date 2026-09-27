<?= $this->extend('layouts/main') ?>

<?php
$statusBadge = ['present' => 'success', 'half_day' => 'warning', 'absent' => 'danger', 'on_leave' => 'info', 'holiday' => 'secondary', 'week_off' => 'secondary'];
$label       = static fn (string $v): string => ucwords(str_replace('_', ' ', $v));

// Same export-CSV pattern as the Employees list page.
$exportRows = array_map(static fn (array $r) => [
    date('d/m/Y', strtotime($r['date'])), $r['check_in'] ? date('g:i A', strtotime($r['check_in'])) : '',
    $r['check_out'] ? date('g:i A', strtotime($r['check_out'])) : '', ucwords(str_replace('_', ' ', $r['status'])), $r['notes'] ?? '',
], $records);
?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/attendance') ?>" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Attendance</a>
<button type="button" class="btn btn-outline-secondary btn-sm" id="attExport"><i class="fas fa-download me-1"></i>Export Report</button>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
    .sy-att-row { cursor: pointer; }
    .sy-att-filters .form-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--sy-muted); margin-bottom: 4px; }
    .sy-att-summary { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
    @media (min-width: 640px) { .sy-att-summary { grid-template-columns: repeat(5, 1fr); } }
    .sy-att-summary > div { text-align: center; padding: 10px; border-radius: var(--sy-radius-sm); background: var(--sy-subtle-bg); }
    .sy-att-summary .v { font-size: 1.15rem; font-weight: 700; color: var(--sy-ink); }
    .sy-att-summary .k { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; color: var(--sy-muted); margin-top: 2px; }
</style>

<div class="sy-pf-crumb"><a href="<?= site_url('hr/attendance') ?>"><i class="fas fa-arrow-left me-1"></i>Back to Attendance</a></div>

<!-- Employee header -->
<div class="sy-card sy-pf-head mb-3" style="height:auto">
    <div class="sy-card-body">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <?php if (! empty($employee['user_avatar'])): ?>
                <img class="sy-pf-photo" src="<?= base_url($employee['user_avatar']) ?>" alt="">
            <?php else: ?>
                <span class="sy-avatar sy-pf-photo initial"><?= esc(mb_strtoupper(mb_substr($employee['user_name'], 0, 1))) ?></span>
            <?php endif; ?>
            <div class="flex-grow-1" style="min-width:0">
                <h4><?= esc($employee['user_name']) ?></h4>
                <div class="sy-pf-meta">
                    <span><i class="far fa-id-badge"></i><?= esc($employee['employee_code']) ?></span>
                    <span><?= esc($employee['designation'] ?: 'No designation set') ?></span>
                    <?php if (! empty($employee['department_name'])): ?><span><i class="fas fa-sitemap"></i><?= esc($employee['department_name']) ?></span><?php endif; ?>
                    <span><span class="badge bg-<?= $employee['status'] === 'active' ? 'success' : 'secondary' ?>"><?= esc(ucfirst($employee['status'])) ?></span></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- KPI cards -->
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">ATTENDANCE</div><div class="sy-stat-value"><?= $summary['pct'] !== null ? $summary['pct'] . '%' : '—' ?></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">PRESENT</div><div class="sy-stat-value"><?= $summary['onTime'] ?> <small class="fs-6 text-muted">days</small></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">LATE</div><div class="sy-stat-value"><?= $summary['late'] ?> <small class="fs-6 text-muted">days</small></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">ABSENT</div><div class="sy-stat-value"><?= $summary['absent'] ?> <small class="fs-6 text-muted">days</small></div></div></div>
</div>

<!-- Filters -->
<div class="sy-card mb-3 sy-att-filters" style="height:auto">
    <div class="sy-card-body">
        <form method="get" class="row g-3 align-items-end filter-form">
            <div class="col-6 col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= esc($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= esc($filters['date_to'] ?? '') ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Status</label>
                <?php $selectedEmpAttStatuses = (array) ($filters['status'] ?? []); ?>
                <div class="dropdown sy-msel" data-placeholder="All">
                    <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span class="sy-msel-label">All</span>
                    </button>
                    <div class="dropdown-menu sy-msel-menu">
                        <?php foreach ($statuses as $s): ?>
                            <label class="sy-msel-item" data-label="<?= esc($label($s)) ?>">
                                <input type="checkbox" class="sy-msel-opt" name="status[]" value="<?= $s ?>" <?= in_array($s, $selectedEmpAttStatuses, true) ? 'checked' : '' ?>>
                                <?= esc($label($s)) ?>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= site_url('hr/attendance/employee/' . $employee['user_id']) ?>" class="btn btn-light btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Attendance History -->
<div class="sy-card mb-3" style="height:auto">
    <div class="sy-card-header"><strong>Attendance History</strong> <span class="text-muted small"><?= count($records) ?> records</span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($records)): ?>
            <p class="text-muted small mb-0 p-3">No attendance records for this period.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0" id="attTable">
            <thead><tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Working Hours</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <?php
                    $mins = ($r['check_in'] && $r['check_out']) ? max(0, (int) round((strtotime($r['check_out']) - strtotime($r['check_in'])) / 60)) : null;
                    $hours = $mins !== null ? intdiv($mins, 60) . 'h ' . ($mins % 60) . 'm' : '—';
                ?>
                <tr class="sy-att-row"
                    data-date="<?= esc(date('D, d M Y', strtotime($r['date']))) ?>"
                    data-checkin="<?= $r['check_in'] ? esc(date('g:i A', strtotime($r['check_in']))) : '—' ?>"
                    data-checkout="<?= $r['check_out'] ? esc(date('g:i A', strtotime($r['check_out']))) : '—' ?>"
                    data-hours="<?= esc($hours) ?>"
                    data-status="<?= esc($label($r['status'])) ?>"
                    data-remarks="<?= esc($r['notes'] ?: 'No remarks.') ?>">
                    <td><?= esc(date('d M Y', strtotime($r['date']))) ?></td>
                    <td><?= $r['check_in'] ? esc(date('g:i A', strtotime($r['check_in']))) : '—' ?></td>
                    <td><?= $r['check_out'] ? esc(date('g:i A', strtotime($r['check_out']))) : '—' ?></td>
                    <td><?= esc($hours) ?></td>
                    <td><span class="badge bg-<?= $statusBadge[$r['status']] ?? 'secondary' ?>"><?= esc($label($r['status'])) ?></span></td>
                    <td class="text-muted small"><?= esc(mb_strimwidth($r['notes'] ?? '—', 0, 40, '…')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Monthly Summary -->
<div class="sy-card" style="height:auto">
    <div class="sy-card-header"><strong>Summary</strong></div>
    <div class="sy-card-body">
        <div class="sy-att-summary">
            <div><div class="v"><?= $summary['workingDays'] ?></div><div class="k">Working Days</div></div>
            <div><div class="v"><?= $summary['onTime'] ?></div><div class="k">Present</div></div>
            <div><div class="v"><?= $summary['late'] ?></div><div class="k">Late</div></div>
            <div><div class="v"><?= $summary['absent'] ?></div><div class="k">Absent</div></div>
            <div><div class="v"><?= $summary['onLeave'] ?></div><div class="k">Leave</div></div>
        </div>
    </div>
</div>

<!-- Day detail modal -->
<div class="modal fade" id="attDayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold" id="attModalDate"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="sy-pf-row"><span class="k">Check In</span><span class="v" id="attModalIn"></span></div>
                <div class="sy-pf-row"><span class="k">Check Out</span><span class="v" id="attModalOut"></span></div>
                <div class="sy-pf-row"><span class="k">Working Hours</span><span class="v" id="attModalHours"></span></div>
                <div class="sy-pf-row"><span class="k">Status</span><span class="v" id="attModalStatus"></span></div>
                <div class="sy-pf-row"><span class="k">Remarks</span><span class="v" id="attModalRemarks"></span></div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('attDayModal');
    var modal   = modalEl ? new bootstrap.Modal(modalEl) : null;

    document.querySelectorAll('.sy-att-row').forEach(function (row) {
        row.addEventListener('click', function () {
            if (! modal) return;
            document.getElementById('attModalDate').textContent    = row.dataset.date;
            document.getElementById('attModalIn').textContent      = row.dataset.checkin;
            document.getElementById('attModalOut').textContent     = row.dataset.checkout;
            document.getElementById('attModalHours').textContent   = row.dataset.hours;
            document.getElementById('attModalStatus').textContent  = row.dataset.status;
            document.getElementById('attModalRemarks').textContent = row.dataset.remarks;
            modal.show();
        });
    });

    var rows = <?= json_encode($exportRows, JSON_UNESCAPED_UNICODE) ?>;
    var header = ['Date', 'Check In', 'Check Out', 'Status', 'Remarks'];
    var exportBtn = document.getElementById('attExport');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            var esc = function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; };
            var csv = [header].concat(rows).map(function (r) { return r.map(esc).join(','); }).join('\r\n');
            var a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
            a.download = 'attendance-<?= esc($employee['employee_code']) ?>.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
        });
    }
});
</script>
<?= $this->endSection() ?>
