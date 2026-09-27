<?php
$isTermination = $record['exit_type'] === 'termination';
$lastDay       = $record['last_working_day'] ?: $record['exit_date'];
$tenure        = null;
if (! empty($record['date_of_joining']) && $lastDay) {
    $joined = new DateTime($record['date_of_joining']);
    $left   = new DateTime($lastDay);
    if ($left >= $joined) {
        $diff   = $joined->diff($left);
        $tenure = trim(($diff->y ? $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ' : '') . ($diff->m ? $diff->m . ' month' . ($diff->m > 1 ? 's' : '') : ''));
        if ($tenure === '') {
            $tenure = 'less than a month';
        }
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    table.head { width: 100%; border-collapse: collapse; border-bottom: 2px solid #10243f; padding-bottom: 10px; margin-bottom: 18px; }
    table.head td { vertical-align: top; padding-bottom: 10px; }
    .co-name { font-size: 15px; font-weight: bold; color: #10243f; }
    .co-addr { color: #666; font-size: 9px; margin-top: 2px; }
    .doc-tag { text-align: right; font-size: 12px; font-weight: bold; letter-spacing: .5px; color: #0d9488; }
    .doc-num { text-align: right; color: #666; font-size: 9px; margin-top: 2px; }
    .meta { font-size: 10px; margin-bottom: 16px; }
    .meta .date { margin-bottom: 10px; }
    p { font-size: 10.5px; line-height: 1.6; margin: 0 0 10px; }
    h3 { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #666; margin: 16px 0 6px; }
    table.details { width: 100%; border-collapse: collapse; border: 1px solid #d9dfe6; margin-bottom: 14px; }
    table.details td { padding: 6px 10px; font-size: 10px; border-bottom: 1px solid #d9dfe6; }
    table.details tr:last-child td { border-bottom: none; }
    table.details .k { color: #666; width: 45%; }
    table.sign { width: 100%; border-collapse: collapse; margin-top: 34px; }
    table.sign td { width: 50%; vertical-align: top; font-size: 10px; padding-right: 20px; }
    table.sign .line { border-top: 1px solid #10243f; width: 180px; padding-top: 30px; margin-bottom: 4px; }
    table.sign .role { color: #666; display: block; margin-bottom: 6px; }
    table.sign .blank { margin-top: 6px; color: #666; }
    table.sign .seal { border: 1px dashed #d9dfe6; color: #666; text-align: center; padding: 24px 0; }
    .footer { border-top: 1px solid #d9dfe6; margin-top: 24px; padding-top: 10px; text-align: center; font-size: 9px; color: #666; }
</style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width:60%">
                <div class="co-name"><?= esc($company['name'] ?? $record['company_name']) ?></div>
                <?php if (! empty($company['registered_address'])): ?><div class="co-addr"><?= esc($company['registered_address']) ?></div><?php endif; ?>
                <?php if (! empty($company['cin'])): ?><div class="co-addr">CIN: <?= esc($company['cin']) ?></div><?php endif; ?>
            </td>
            <td style="width:40%">
                <div class="doc-tag">RELIEVING LETTER</div>
                <div class="doc-num">Ref #OFB-<?= str_pad((string) $record['id'], 4, '0', STR_PAD_LEFT) ?></div>
            </td>
        </tr>
    </table>

    <div class="meta">
        <div class="date">Date: <?= esc(date('d/m/Y')) ?></div>
        <div>TO WHOM IT MAY CONCERN</div>
    </div>

    <p>
        This is to certify that Mr./Ms. <strong><?= esc($record['employee_name']) ?></strong>,
        Employee ID <?= esc($record['employee_code'] ?? '—') ?>,
        was employed with <strong><?= esc($company['name'] ?? $record['company_name']) ?></strong>
        as <strong><?= esc($record['designation'] ?? '—') ?></strong><?= ! empty($record['department_name']) ? ' in the ' . esc($record['department_name']) . ' department' : '' ?>.
    </p>

    <h3>Employment Details</h3>
    <table class="details">
        <tr><td class="k">Employee ID</td><td><?= esc($record['employee_code'] ?? '—') ?></td></tr>
        <tr><td class="k">Designation</td><td><?= esc($record['designation'] ?? '—') ?></td></tr>
        <?php if (! empty($record['department_name'])): ?><tr><td class="k">Department</td><td><?= esc($record['department_name']) ?></td></tr><?php endif; ?>
        <tr><td class="k">Date of Joining</td><td><?= ! empty($record['date_of_joining']) ? esc(date('d/m/Y', strtotime($record['date_of_joining']))) : '—' ?></td></tr>
        <tr><td class="k">Last Working Day</td><td><?= $lastDay ? esc(date('d/m/Y', strtotime($lastDay))) : '—' ?></td></tr>
        <?php if ($tenure): ?><tr><td class="k">Total Tenure</td><td><?= esc($tenure) ?></td></tr><?php endif; ?>
    </table>

    <p>
        The employee has been formally relieved from their services with the company effective from
        <strong><?= $lastDay ? esc(date('d/m/Y', strtotime($lastDay))) : '—' ?></strong>,
        after completion of the required clearance and handover formalities, following their
        <?= $isTermination ? 'separation from the company' : 'resignation' ?>.
    </p>
    <p>As of the date of this letter, there are no dues pending against the employee, and all company property issued to them has been returned in full.</p>

    <?php if (! $isTermination): ?>
        <p>We appreciate the contributions made by <?= esc($record['employee_name']) ?> during their tenure and wish them success in their future endeavors.</p>
    <?php else: ?>
        <p>This letter confirms the period of employment stated above and is issued for record purposes.</p>
    <?php endif; ?>

    <p>This letter is issued upon request for whatever purpose it may serve.</p>
    <p>Sincerely,</p>

    <table class="sign">
        <tr>
            <td>
                <div class="line"></div>
                <span class="role">Authorized Signatory, HR</span>
                <div class="blank">Name: ____________________</div>
                <div class="blank">Designation: ____________________</div>
                For <?= esc($company['name'] ?? $record['company_name']) ?>
            </td>
            <td>
                <div class="seal">Company Seal</div>
            </td>
        </tr>
    </table>

    <div class="footer"><?= esc($company['name'] ?? $record['company_name']) ?></div>
</body>
</html>
