<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/payroll') ?>" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Payroll</a>
<a href="<?= site_url('hr/payroll/structure/' . $employee['user_id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-sliders me-1"></i>View Salary Structure</a>
<a href="#payslipHistory" class="btn btn-outline-secondary btn-sm"><i class="fas fa-receipt me-1"></i>View Payslips</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="sy-pf-crumb"><a href="<?= site_url('hr/payroll') ?>"><i class="fas fa-arrow-left me-1"></i>Back to Payroll</a></div>

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
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Employee Payroll Details -->
<div class="sy-card mb-3" style="height:auto">
    <div class="sy-card-header"><strong>Employee Payroll Details</strong></div>
    <div class="sy-card-body">
        <?php if ($structure === null): ?>
            <p class="text-muted small mb-0">No salary structure has been set up for this employee yet. <a href="<?= site_url('hr/payroll/structure/' . $employee['user_id']) ?>">Set one up</a>.</p>
        <?php else: ?>
            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="sy-pf-row"><span class="k">Basic Salary</span><span class="v"><?= number_format((float) $structure['basic'], 2) ?></span></div></div>
                <div class="col-6 col-md-3"><div class="sy-pf-row"><span class="k">HRA</span><span class="v"><?= number_format((float) $structure['hra'], 2) ?></span></div></div>
                <div class="col-6 col-md-3"><div class="sy-pf-row"><span class="k">Allowances</span><span class="v"><?= number_format((float) $structure['allowances'], 2) ?></span></div></div>
                <div class="col-6 col-md-3"><div class="sy-pf-row"><span class="k">Gross Salary</span><span class="v"><?= number_format($gross, 2) ?></span></div></div>
                <div class="col-6 col-md-3"><div class="sy-pf-row"><span class="k">Deductions</span><span class="v"><?= number_format((float) $structure['deductions'], 2) ?></span></div></div>
                <div class="col-6 col-md-3"><div class="sy-pf-row"><span class="k">Net Salary</span><span class="v"><?= number_format($net, 2) ?></span></div></div>
                <div class="col-6 col-md-3"><div class="sy-pf-row"><span class="k">CTC (Annual)</span><span class="v"><?= number_format($ctc, 2) ?></span></div></div>
                <div class="col-6 col-md-3"><div class="sy-pf-row"><span class="k">Effective From</span><span class="v"><?= esc(date('d/m/Y', strtotime($structure['effective_from']))) ?></span></div></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Payslip History -->
<div class="sy-card" style="height:auto" id="payslipHistory">
    <div class="sy-card-header"><strong>Payslip History</strong> <span class="text-muted small"><?= count($payslips) ?> payslips</span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($payslips)): ?>
            <p class="text-muted small mb-0 p-3">No payslips generated yet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Period</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($payslips as $p): ?>
                <tr>
                    <td><?= esc($months[$p['month']] . ' ' . $p['year']) ?></td>
                    <td class="text-end"><?= number_format((float) $p['gross'], 2) ?></td>
                    <td class="text-end"><?= number_format((float) $p['deductions'], 2) ?></td>
                    <td class="text-end fw-semibold"><?= number_format((float) $p['net'], 2) ?></td>
                    <td><span class="badge bg-<?= $p['run_status'] === 'paid' ? 'success' : 'warning' ?>"><?= esc(ucfirst($p['run_status'])) ?></span></td>
                    <td><a href="<?= site_url('hr/payroll/payslip/' . $p['id']) ?>">Payslip</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
