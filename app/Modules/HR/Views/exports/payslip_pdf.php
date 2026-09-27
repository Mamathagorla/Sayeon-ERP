<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    .co-name { text-align: center; font-size: 16px; font-weight: bold; }
    .co-meta { text-align: center; color: #666; font-size: 9px; margin-top: 2px; }
    .title { text-align: center; margin: 16px 0 2px; font-size: 13px; font-weight: bold; letter-spacing: 1px; }
    .period { text-align: center; color: #666; font-size: 10px; margin-bottom: 14px; }
    hr { border: none; border-top: 1px solid #ccc; margin: 12px 0; }
    table.facts { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.facts td { padding: 3px 4px; font-size: 10px; width: 50%; }
    table.facts .k { color: #666; }
    table.facts .v { font-weight: bold; }
    table.cols { width: 100%; border-collapse: collapse; }
    table.cols > tbody > tr > td { width: 50%; vertical-align: top; padding: 0 12px 0 0; }
    table.lines { width: 100%; border-collapse: collapse; }
    table.lines th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #666; border-bottom: 1px solid #999; padding-bottom: 4px; }
    table.lines th.amt, table.lines td.amt { text-align: right; }
    table.lines td { padding: 4px 0; font-size: 10px; }
    table.lines tr.total td { border-top: 1px solid #999; font-weight: bold; padding-top: 5px; }
    .net { background: #fdeced; border-radius: 4px; padding: 10px 14px; margin-top: 16px; }
    .net table { width: 100%; }
    .net .label { font-weight: bold; color: #b3261e; font-size: 11px; }
    .net .amount { font-weight: bold; color: #b3261e; font-size: 14px; text-align: right; }
    .words { font-size: 10px; margin-top: 10px; }
    .words .label { color: #666; display: block; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }
    .sign { margin-top: 40px; text-align: right; font-size: 10px; color: #666; }
    .sign strong { display: block; color: #222; margin-top: 26px; }
</style>
</head>
<body>
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

    <table class="facts">
        <tr>
            <td class="k">Employee Name:</td><td class="v"><?= esc($payslip['user_name']) ?></td>
            <?php if (! empty($payslip['employee_code'])): ?><td class="k">Employee ID:</td><td class="v"><?= esc($payslip['employee_code']) ?></td><?php else: ?><td></td><td></td><?php endif; ?>
        </tr>
        <tr>
            <?php if (! empty($payslip['designation'])): ?><td class="k">Designation:</td><td class="v"><?= esc($payslip['designation']) ?></td><?php else: ?><td></td><td></td><?php endif; ?>
            <?php if (! empty($payslip['department_name'])): ?><td class="k">Department:</td><td class="v"><?= esc($payslip['department_name']) ?></td><?php else: ?><td></td><td></td><?php endif; ?>
        </tr>
        <tr>
            <?php if (! empty($payslip['date_of_joining'])): ?><td class="k">Joining Date:</td><td class="v"><?= esc(date('d M Y', strtotime($payslip['date_of_joining']))) ?></td><?php else: ?><td></td><td></td><?php endif; ?>
            <td class="k">Pay Period:</td><td class="v"><?= esc($monthName) ?> <?= esc($payslip['year']) ?></td>
        </tr>
    </table>

    <hr>

    <table class="cols">
        <tr>
            <td>
                <table class="lines">
                    <tr><th>Earnings</th><th class="amt">Amount</th></tr>
                    <tr><td>Basic Salary</td><td class="amt"><?= number_format((float) $payslip['basic'], 2) ?></td></tr>
                    <tr><td>HRA</td><td class="amt"><?= number_format((float) $payslip['hra'], 2) ?></td></tr>
                    <tr><td>Allowances</td><td class="amt"><?= number_format((float) $payslip['allowances'], 2) ?></td></tr>
                    <tr class="total"><td>Gross Earnings</td><td class="amt"><?= number_format((float) $payslip['gross'], 2) ?></td></tr>
                </table>
            </td>
            <td>
                <table class="lines">
                    <tr><th>Deductions</th><th class="amt">Amount</th></tr>
                    <tr><td>Deductions</td><td class="amt"><?= number_format((float) $payslip['deductions'], 2) ?></td></tr>
                    <tr class="total"><td>Total Deductions</td><td class="amt"><?= number_format((float) $payslip['deductions'], 2) ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="net">
        <table>
            <tr><td class="label">Net Salary</td><td class="amount">Rs. <?= number_format((float) $payslip['net'], 2) ?></td></tr>
        </table>
    </div>

    <div class="words">
        <span class="label">Amount in Words</span>
        <?= esc(amount_in_words((float) $payslip['net'])) ?>
    </div>

    <div class="sign">
        <span>Authorized Signatory</span>
        <strong><?= esc($payslip['company_name'] ?? 'Company') ?></strong>
    </div>
</body>
</html>
