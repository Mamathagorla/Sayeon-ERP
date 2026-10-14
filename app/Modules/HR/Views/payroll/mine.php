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
            <?php $payslipReady = \App\Modules\Document\Services\DocumentAvailability::shared()->has('payslip', \App\Modules\Document\Services\DocumentAvailability::shared()->companyOfUser((int) session('userId'))); ?>
            <?php if (! $payslipReady): ?>
                <div class="alert alert-light border small mb-2"><i class="far fa-circle-question me-1"></i>Payslip documents are not available for your company yet. Please contact HR.</div>
            <?php endif; ?>
            <?php foreach ($payslips as $p): ?>
                <tr>
                    <td><?= esc($months[$p['month']]) ?> <?= esc($p['year']) ?></td>
                    <td class="text-end"><?= erp_number($p['gross'], 2) ?></td>
                    <td class="text-end fw-semibold"><?= erp_number($p['net'], 2) ?></td>
                    <td><span class="badge bg-<?= $p['run_status'] === 'paid' ? 'success' : 'info' ?>"><?= esc(ucfirst($p['run_status'])) ?></span></td>
                    <td>
                        <?php if ($payslipReady): ?>
                        <a href="<?= site_url('hr/payroll/payslip/' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary" data-erp-form="off">View</a>
                        <a href="<?= site_url('hr/payroll/payslip/' . $p['id'] . '/pdf') ?>" class="btn btn-sm btn-outline-secondary" data-erp-form="off"><i class="fas fa-file-pdf"></i></a>
                        <?php else: ?><span class="text-muted small">Not available</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
