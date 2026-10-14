<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if ($run['status'] === 'processed' && can('payroll.edit')): ?>
<form action="<?= site_url('hr/payroll/' . $run['id'] . '/mark-paid') ?>" method="post" onsubmit="return confirm('Mark this payroll run as paid?');">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>Mark as Paid</button>
</form>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <?php if (empty($payslips)): ?>
            <p class="text-muted mb-0">No payslips in this run.</p>
        <?php else: ?>
        <table class="table table-sm table-striped">
            <thead><tr><th>Employee</th><th class="text-end">Basic</th><th class="text-end">HRA</th><th class="text-end">Allowances</th><th class="text-end">Deductions</th><th class="text-end">LOP</th><th class="text-end">Gross</th><th class="text-end">Net</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($payslips as $p): ?>
                <tr>
                    <td><?= esc($p['user_name']) ?></td>
                    <td class="text-end"><?= erp_number($p['basic'], 2) ?></td>
                    <td class="text-end"><?= erp_number($p['hra'], 2) ?></td>
                    <td class="text-end"><?= erp_number($p['allowances'], 2) ?></td>
                    <td class="text-end"><?= erp_number($p['deductions'], 2) ?></td>
                    <td class="text-end"><?= (float) ($p['lop_days'] ?? 0) > 0 ? erp_number($p['lop_amount'], 2) . ' (' . rtrim(rtrim(erp_number($p['lop_days'], 1), '0'), '.') . 'd)' : '—' ?></td>
                    <td class="text-end"><?= erp_number($p['gross'], 2) ?></td>
                    <td class="text-end fw-semibold"><?= erp_number($p['net'], 2) ?></td>
                    <td><?php if (\App\Modules\Document\Services\DocumentAvailability::shared()->has('payslip', (int) ($p['company_id'] ?? 0))): ?><a href="<?= site_url('hr/payroll/payslip/' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary" data-erp-form="off">View</a><?php else: ?><span class="text-muted small" title="No payslip template is active for this employee's company">No template</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
