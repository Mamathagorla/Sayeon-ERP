<?= $this->extend('layouts/main') ?>

<?php
$statusBadge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'];
$num         = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
$currentYear = (int) date('Y');

$exportRows = array_map(static fn (array $r) => [
    $r['leave_type_name'], date('d/m/Y', strtotime($r['start_date'])), date('d/m/Y', strtotime($r['end_date'])),
    $r['days'], $r['reason'] ?? '', ucfirst($r['status']),
], $records);
?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/leave') ?>" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Leave</a>
<button type="button" class="btn btn-outline-secondary btn-sm" id="leaveExport"><i class="fas fa-download me-1"></i>Export Report</button>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
    .sy-lv-row { cursor: pointer; }
    .sy-lv-filters .form-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--sy-muted); margin-bottom: 4px; }
</style>

<div class="sy-pf-crumb"><a href="<?= site_url('hr/leave') ?>"><i class="fas fa-arrow-left me-1"></i>Back to Leave</a></div>

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
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">LEAVE BALANCE</div><div class="sy-stat-value"><?= esc($num($totals['quota'])) ?> <small class="fs-6 text-muted">days</small></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">USED</div><div class="sy-stat-value"><?= esc($num($totals['used'])) ?> <small class="fs-6 text-muted">days</small></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">REMAINING</div><div class="sy-stat-value"><?= esc($num($totals['remaining'])) ?> <small class="fs-6 text-muted">days</small></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">PENDING</div><div class="sy-stat-value"><?= $totals['pending'] ?> <small class="fs-6 text-muted"><?= $totals['pending'] === 1 ? 'request' : 'requests' ?></small></div></div></div>
</div>

<!-- Filters -->
<div class="sy-card mb-3 sy-lv-filters" style="height:auto">
    <div class="sy-card-body">
        <form method="get" class="row g-3 align-items-end filter-form">
            <div class="col-6 col-md-3">
                <label class="form-label">Year</label>
                <select name="year" class="form-select form-select-sm">
                    <?php for ($y = $currentYear; $y >= $currentYear - 3; $y--): ?>
                        <option value="<?= $y ?>" <?= (int) $filters['year'] === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Leave Type</label>
                <?php $selectedLeaveTypes = array_map('strval', (array) ($filters['leave_type_id'] ?? [])); ?>
                <div class="dropdown sy-msel" data-placeholder="All">
                    <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span class="sy-msel-label">All</span>
                    </button>
                    <div class="dropdown-menu sy-msel-menu">
                        <?php foreach ($leaveTypes as $t): ?>
                            <label class="sy-msel-item" data-label="<?= esc($t['name']) ?>">
                                <input type="checkbox" class="sy-msel-opt" name="leave_type_id[]" value="<?= $t['id'] ?>" <?= in_array((string) $t['id'], $selectedLeaveTypes, true) ? 'checked' : '' ?>>
                                <?= esc($t['name']) ?>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Status</label>
                <?php $selectedLeaveStatuses = (array) ($filters['status'] ?? []); ?>
                <div class="dropdown sy-msel" data-placeholder="All">
                    <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span class="sy-msel-label">All</span>
                    </button>
                    <div class="dropdown-menu sy-msel-menu">
                        <?php foreach (['pending', 'approved', 'rejected', 'cancelled'] as $s): ?>
                            <label class="sy-msel-item" data-label="<?= esc(ucfirst($s)) ?>">
                                <input type="checkbox" class="sy-msel-opt" name="status[]" value="<?= $s ?>" <?= in_array($s, $selectedLeaveStatuses, true) ? 'checked' : '' ?>>
                                <?= esc(ucfirst($s)) ?>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= site_url('hr/leave/employee/' . $employee['user_id']) ?>" class="btn btn-light btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Leave History -->
<div class="sy-card mb-3" style="height:auto">
    <div class="sy-card-header"><strong>Leave History</strong> <span class="text-muted small"><?= count($records) ?> requests</span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($records)): ?>
            <p class="text-muted small mb-0 p-3">No leave requests for this period.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Leave Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <tr class="sy-lv-row"
                    data-type="<?= esc($r['leave_type_name']) ?>"
                    data-from="<?= esc(date('d M Y', strtotime($r['start_date']))) ?>"
                    data-to="<?= esc(date('d M Y', strtotime($r['end_date']))) ?>"
                    data-days="<?= esc($num($r['days'])) ?>"
                    data-reason="<?= esc($r['reason'] ?: 'No reason given.') ?>"
                    data-status="<?= esc(ucfirst($r['status'])) ?>"
                    data-approver="<?= esc($r['approver_name'] ? ($r['status'] === 'rejected' ? 'Rejected by ' : 'Approved by ') . $r['approver_name'] : 'Awaiting decision') ?>"
                    data-applied="<?= esc(date('d M Y', strtotime($r['created_at']))) ?>">
                    <td><?= esc($r['leave_type_name']) ?></td>
                    <td><?= esc(date('d M Y', strtotime($r['start_date']))) ?></td>
                    <td><?= esc(date('d M Y', strtotime($r['end_date']))) ?></td>
                    <td><?= esc($num($r['days'])) ?></td>
                    <td class="text-muted small"><?= esc(mb_strimwidth($r['reason'] ?? '—', 0, 40, '…')) ?></td>
                    <td><span class="badge bg-<?= $statusBadge[$r['status']] ?? 'secondary' ?>"><?= esc(ucfirst($r['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Leave Balance -->
<div class="sy-card" style="height:auto">
    <div class="sy-card-header"><strong>Leave Balance</strong></div>
    <div class="sy-card-body">
        <?php foreach ($balances as $b): ?>
            <?php $pct = $b['quota'] > 0 ? min(100, (int) round($b['used'] / $b['quota'] * 100)) : 0; ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between small"><span class="fw-semibold"><?= esc($b['name']) ?></span><span class="text-muted"><?= esc($num($b['remaining'])) ?> / <?= esc($num($b['quota'])) ?> remaining</span></div>
                <div class="progress" style="height:6px;"><div class="progress-bar" role="progressbar" style="width:<?= $pct ?>%;background:var(--sy-accent-ink)"></div></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Leave detail modal -->
<div class="modal fade" id="leaveDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold" id="lvModalType"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="sy-pf-row"><span class="k">From</span><span class="v" id="lvModalFrom"></span></div>
                <div class="sy-pf-row"><span class="k">To</span><span class="v" id="lvModalTo"></span></div>
                <div class="sy-pf-row"><span class="k">Days</span><span class="v" id="lvModalDays"></span></div>
                <div class="sy-pf-row"><span class="k">Reason</span><span class="v" id="lvModalReason"></span></div>
                <div class="sy-pf-row"><span class="k">Status</span><span class="v" id="lvModalStatus"></span></div>
                <div class="sy-pf-row"><span class="k">Decision</span><span class="v" id="lvModalApprover"></span></div>
                <div class="sy-pf-row"><span class="k">Applied On</span><span class="v" id="lvModalApplied"></span></div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('leaveDetailModal');
    var modal   = modalEl ? new bootstrap.Modal(modalEl) : null;

    document.querySelectorAll('.sy-lv-row').forEach(function (row) {
        row.addEventListener('click', function () {
            if (! modal) return;
            document.getElementById('lvModalType').textContent     = row.dataset.type;
            document.getElementById('lvModalFrom').textContent     = row.dataset.from;
            document.getElementById('lvModalTo').textContent       = row.dataset.to;
            document.getElementById('lvModalDays').textContent     = row.dataset.days;
            document.getElementById('lvModalReason').textContent   = row.dataset.reason;
            document.getElementById('lvModalStatus').textContent   = row.dataset.status;
            document.getElementById('lvModalApprover').textContent = row.dataset.approver;
            document.getElementById('lvModalApplied').textContent  = row.dataset.applied;
            modal.show();
        });
    });

    var rows = <?= json_encode($exportRows, JSON_UNESCAPED_UNICODE) ?>;
    var header = ['Leave Type', 'From', 'To', 'Days', 'Reason', 'Status'];
    var exportBtn = document.getElementById('leaveExport');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            var esc = function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; };
            var csv = [header].concat(rows).map(function (r) { return r.map(esc).join(','); }).join('\r\n');
            var a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
            a.download = 'leave-<?= esc($employee['employee_code']) ?>.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
        });
    }
});
</script>
<?= $this->endSection() ?>
