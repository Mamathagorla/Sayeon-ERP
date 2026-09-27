<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<button type="button" class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
<a href="<?= site_url('hr/payroll/payslip/' . $payslip['id'] . '/pdf') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-pdf me-1"></i>Download PDF</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
    .sy-payslip { max-width: 720px; margin: 0 auto; background: var(--sy-surface); border: 1px solid var(--sy-border); border-radius: var(--sy-radius); padding: 36px 40px; }
    .sy-payslip .co-name { font-size: 1.3rem; font-weight: 700; color: var(--sy-ink); text-align: center; }
    .sy-payslip .co-meta { text-align: center; color: var(--sy-muted); font-size: .8rem; margin-top: 2px; }
    .sy-payslip .title { text-align: center; margin: 22px 0 2px; font-size: 1.05rem; font-weight: 700; letter-spacing: .5px; color: var(--sy-ink); }
    .sy-payslip .period { text-align: center; color: var(--sy-muted); font-size: .88rem; margin-bottom: 22px; }
    .sy-payslip hr { border-top: 1px solid var(--sy-border); margin: 18px 0; }
    .sy-payslip-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; font-size: .86rem; margin-bottom: 6px; }
    .sy-payslip-facts .k { color: var(--sy-muted); }
    .sy-payslip-facts .v { font-weight: 600; color: var(--sy-ink); }
    .sy-payslip-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 0 28px; }
    .sy-payslip-cols h6 { font-size: .74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--sy-muted); border-bottom: 1px solid var(--sy-border); padding-bottom: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; }
    .sy-payslip-line { display: flex; justify-content: space-between; padding: 5px 0; font-size: .88rem; }
    .sy-payslip-total { display: flex; justify-content: space-between; padding-top: 8px; margin-top: 4px; border-top: 1px solid var(--sy-border); font-weight: 700; font-size: .9rem; }
    .sy-payslip-net { display: flex; justify-content: space-between; align-items: center; background: var(--sy-accent-soft); border-radius: var(--sy-radius-sm); padding: 14px 18px; margin-top: 22px; }
    .sy-payslip-net .label { font-weight: 700; color: var(--sy-accent-ink); }
    .sy-payslip-net .amount { font-weight: 700; font-size: 1.2rem; color: var(--sy-accent-ink); }
    .sy-payslip-words { font-size: .84rem; color: var(--sy-ink); margin-top: 10px; }
    .sy-payslip-words .label { color: var(--sy-muted); display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px; }
    .sy-payslip-sign { margin-top: 44px; text-align: right; font-size: .84rem; color: var(--sy-muted); }
    .sy-payslip-sign strong { display: block; color: var(--sy-ink); margin-top: 30px; }
    @media print {
        .app-sidebar, .app-header, .app-content-header, .app-footer { display: none !important; }
        .app-main, .app-content { overflow: visible !important; height: auto !important; }
        .sy-payslip { border: none; max-width: none; padding: 0; }
    }
</style>

<div class="sy-payslip">
    <div class="co-name"><?= esc($payslip['company_name'] ?? 'Company') ?></div>
    <?php if (! empty($payslip['company_address']) || ! empty($payslip['company_gst'])): ?>
        <div class="co-meta">
            <?= esc($payslip['company_address'] ?? '') ?>
            <?php if (! empty($payslip['company_gst'])): ?><?= ! empty($payslip['company_address']) ? ' &middot; ' : '' ?>GST: <?= esc($payslip['company_gst']) ?><?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="title">PAYSLIP</div>
    <div class="period"><?= esc($monthName) ?> <?= esc($payslip['year']) ?></div>

    <hr>

    <div class="sy-payslip-facts">
        <div><span class="k">Employee Name:</span> <span class="v"><?= esc($payslip['user_name']) ?></span></div>
        <?php if (! empty($payslip['employee_code'])): ?><div><span class="k">Employee ID:</span> <span class="v"><?= esc($payslip['employee_code']) ?></span></div><?php endif; ?>
        <?php if (! empty($payslip['designation'])): ?><div><span class="k">Designation:</span> <span class="v"><?= esc($payslip['designation']) ?></span></div><?php endif; ?>
        <?php if (! empty($payslip['department_name'])): ?><div><span class="k">Department:</span> <span class="v"><?= esc($payslip['department_name']) ?></span></div><?php endif; ?>
        <?php if (! empty($payslip['date_of_joining'])): ?><div><span class="k">Joining Date:</span> <span class="v"><?= esc(date('d M Y', strtotime($payslip['date_of_joining']))) ?></span></div><?php endif; ?>
        <div><span class="k">Pay Period:</span> <span class="v"><?= esc($monthName) ?> <?= esc($payslip['year']) ?></span></div>
    </div>

    <hr>

    <div class="sy-payslip-cols">
        <div>
            <h6><span>Earnings</span><span>Amount</span></h6>
            <div class="sy-payslip-line"><span>Basic Salary</span><span><?= number_format((float) $payslip['basic'], 2) ?></span></div>
            <div class="sy-payslip-line"><span>HRA</span><span><?= number_format((float) $payslip['hra'], 2) ?></span></div>
            <div class="sy-payslip-line"><span>Allowances</span><span><?= number_format((float) $payslip['allowances'], 2) ?></span></div>
            <div class="sy-payslip-total"><span>Gross Earnings</span><span><?= number_format((float) $payslip['gross'], 2) ?></span></div>
        </div>
        <div>
            <h6><span>Deductions</span><span>Amount</span></h6>
            <div class="sy-payslip-line"><span>Deductions</span><span><?= number_format((float) $payslip['deductions'], 2) ?></span></div>
            <div class="sy-payslip-total"><span>Total Deductions</span><span><?= number_format((float) $payslip['deductions'], 2) ?></span></div>
        </div>
    </div>

    <div class="sy-payslip-net">
        <span class="label">Net Salary</span>
        <span class="amount">₹<?= number_format((float) $payslip['net'], 2) ?></span>
    </div>

    <div class="sy-payslip-words">
        <span class="label">Amount in Words</span>
        <?= esc(amount_in_words((float) $payslip['net'])) ?>
    </div>

    <div class="sy-payslip-sign">
        <span>Authorized Signatory</span>
        <strong><?= esc($payslip['company_name'] ?? 'Company') ?></strong>
    </div>
</div>
<?= $this->endSection() ?>
