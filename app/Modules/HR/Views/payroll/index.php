<?= $this->extend('layouts/main') ?>

<?php
// Indian-style Lakh formatting for the big KPI figures — matches the
// ₹ + "L" the rest of this app already uses for large amounts (see
// Onboarding's Offered CTC). Under ₹1L just shows the plain rupee
// amount instead of "0.42L", which reads oddly for small orgs/runs.
$lakh = static function (float $n): string {
    return $n >= 100000
        ? '₹' . number_format($n / 100000, 2) . 'L'
        : '₹' . number_format($n, 0);
};
$run = $summary['run'] ?? null;

// A payslip has no status of its own — every row in a run shares that
// run's status (see PayrollRunModel), so the table's Status column is
// literally the selected run's status relabeled to match how HR talks
// about it day to day.
$runStatusLabel = $run ? ($run['status'] === 'paid' ? 'Paid' : 'Pending') : '';
$runStatusBadge = $run && $run['status'] === 'paid' ? 'success' : 'warning';

$exportRows = array_map(static fn (array $s) => [
    $s['user_name'], $s['department_name'] ?? '', number_format((float) $s['gross'], 2),
    number_format((float) $s['deductions'], 2), number_format((float) $s['net'], 2), $runStatusLabel,
], $slips);
?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/payroll/my-payslips') ?>" class="btn btn-outline-secondary btn-sm">My Payslips</a>
<?php if (can('payroll.create')): ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#runPayrollModal"><i class="fas fa-play me-1"></i>Run Payroll</button>
<?php endif; ?>
<button type="button" class="btn btn-light btn-sm" id="payrollExport"><i class="fas fa-download me-1"></i>Export</button>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
    .sy-pr-filters .form-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--sy-muted); margin-bottom: 4px; }
</style>

<p class="text-muted mb-3">Manage salary processing and payroll records.</p>

<?php if (empty($summary)): ?>
    <div class="sy-card" style="height:auto"><div class="sy-card-body"><p class="text-muted mb-0">No payroll runs yet<?= can('payroll.create') ? ' — use "Run Payroll" to generate the first one.' : '.' ?></p></div></div>
<?php else: ?>

<!-- KPI cards -->
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">EMPLOYEES</div><div class="sy-stat-value"><?= $summary['employees'] ?></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">GROSS PAY</div><div class="sy-stat-value"><?= esc($lakh($summary['gross'])) ?></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">DEDUCTIONS</div><div class="sy-stat-value"><?= esc($lakh($summary['deductions'])) ?></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">NET PAY</div><div class="sy-stat-value"><?= esc($lakh($summary['net'])) ?></div></div></div>
</div>

<!-- Period + status -->
<div class="sy-card mb-3 sy-pr-filters" style="height:auto">
    <div class="sy-card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-6 col-md-4">
                <label class="form-label">Payroll Period</label>
                <select id="payrollRun" name="run" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($runs as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= (int) $r['id'] === (int) $run['id'] ? 'selected' : '' ?>><?= esc($months[$r['month']]) ?> <?= esc($r['year']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Status</label>
                <div><span class="badge bg-<?= $runStatusBadge ?> fs-6"><?= esc($runStatusLabel) ?></span></div>
            </div>
            <div class="col-auto ms-auto">
                <a href="<?= site_url('hr/payroll/' . $run['id']) ?>" class="small"><i class="fas fa-gear me-1"></i>Manage This Run</a>
            </div>
        </form>
    </div>
</div>

<!-- Employee payroll table -->
<div class="sy-card" style="height:auto">
    <div class="sy-card-header"><strong>Employee Payroll</strong> <span class="text-muted small"><?= count($slips) ?> employees</span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($slips)): ?>
            <p class="text-muted small mb-0 p-3">No payslips in this run.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Employee</th><th>Department</th><th>Gross</th><th>Deductions</th><th>Net Salary</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($slips as $s): ?>
                <tr>
                    <td><a href="<?= site_url('hr/payroll/employee/' . $s['user_id']) ?>" class="text-decoration-none fw-semibold text-dark"><?= esc($s['user_name']) ?></a></td>
                    <td class="text-muted small"><?= esc($s['department_name'] ?? '—') ?></td>
                    <td><?= number_format((float) $s['gross'], 2) ?></td>
                    <td><?= number_format((float) $s['deductions'], 2) ?></td>
                    <td class="fw-semibold"><?= number_format((float) $s['net'], 2) ?></td>
                    <td><span class="badge bg-<?= $runStatusBadge ?>"><?= esc($runStatusLabel) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Run Payroll modal -->
<?php if (can('payroll.create')): ?>
<div class="modal fade" id="runPayrollModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Run Payroll</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= site_url('hr/payroll/generate') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <p class="text-muted small">Snapshots every active employee's current salary structure into payslips for the selected month. Each employee needs a salary structure set up first — open their profile from the <a href="<?= site_url('hr/employees') ?>">Employees</a> list and use the "Salary Structure" link to set Basic/HRA/Allowances/Deductions.</p>
                    <div class="mb-2">
                        <label class="form-label small">Month</label>
                        <select name="month" class="form-select form-select-sm" required>
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?= $num ?>" <?= $num == date('n') ? 'selected' : '' ?>><?= esc($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Year</label>
                        <input type="number" name="year" class="form-control form-control-sm" value="<?= date('Y') ?>" min="2000" max="2100" step="1" title="Enter a valid 4-digit year between 2000 and 2100" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var rows = <?= json_encode($exportRows, JSON_UNESCAPED_UNICODE) ?>;
    var header = ['Employee', 'Department', 'Gross', 'Deductions', 'Net Salary', 'Status'];
    var exportBtn = document.getElementById('payrollExport');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            var esc = function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; };
            var csv = [header].concat(rows).map(function (r) { return r.map(esc).join(','); }).join('\r\n');
            var a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
            a.download = 'payroll.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
        });
    }
});
</script>
<?= $this->endSection() ?>
