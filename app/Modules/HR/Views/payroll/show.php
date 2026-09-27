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
            <thead><tr><th>Employee</th><th class="text-end">Basic</th><th class="text-end">HRA</th><th class="text-end">Allowances</th><th class="text-end">Deductions</th><th class="text-end">Gross</th><th class="text-end">Net</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($payslips as $p): ?>
                <tr>
                    <td><?= esc($p['user_name']) ?></td>
                    <td class="text-end"><?= number_format($p['basic'], 2) ?></td>
                    <td class="text-end"><?= number_format($p['hra'], 2) ?></td>
                    <td class="text-end"><?= number_format($p['allowances'], 2) ?></td>
                    <td class="text-end"><?= number_format($p['deductions'], 2) ?></td>
                    <td class="text-end"><?= number_format($p['gross'], 2) ?></td>
                    <td class="text-end fw-semibold"><?= number_format($p['net'], 2) ?></td>
                    <td><a href="<?= site_url('hr/payroll/payslip/' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
