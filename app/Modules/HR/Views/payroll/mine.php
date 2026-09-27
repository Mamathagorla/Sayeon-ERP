<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <?php if (empty($payslips)): ?>
            <p class="text-muted mb-0">No payslips yet.</p>
        <?php else: ?>
        <table class="table table-sm table-striped">
            <thead><tr><th>Period</th><th class="text-end">Gross</th><th class="text-end">Net</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($payslips as $p): ?>
                <tr>
                    <td><?= esc($months[$p['month']]) ?> <?= esc($p['year']) ?></td>
                    <td class="text-end"><?= number_format($p['gross'], 2) ?></td>
                    <td class="text-end fw-semibold"><?= number_format($p['net'], 2) ?></td>
                    <td><span class="badge bg-<?= $p['run_status'] === 'paid' ? 'success' : 'info' ?>"><?= esc(ucfirst($p['run_status'])) ?></span></td>
                    <td>
                        <a href="<?= site_url('hr/payroll/payslip/' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary">View</a>
                        <a href="<?= site_url('hr/payroll/payslip/' . $p['id'] . '/pdf') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-pdf"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
