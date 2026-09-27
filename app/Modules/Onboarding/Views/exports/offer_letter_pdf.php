<?php
$empTypeLabels = ['full_time' => 'Full Time', 'part_time' => 'Part Time', 'contract' => 'Contract', 'intern' => 'Intern'];
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
    .ctc { border: 1px solid #d9dfe6; border-radius: 4px; padding: 8px 12px; margin-bottom: 10px; font-size: 10.5px; font-weight: bold; background: #f6f8fa; }
    ol.terms { font-size: 10px; margin: 6px 0 14px; padding-left: 16px; }
    ol.terms li { margin-bottom: 6px; }
    table.sign { width: 100%; border-collapse: collapse; margin-top: 34px; }
    table.sign td { width: 50%; vertical-align: top; font-size: 10px; padding-right: 20px; }
    table.sign .line { border-top: 1px solid #10243f; width: 180px; padding-top: 30px; margin-bottom: 4px; }
    table.sign .role { color: #666; display: block; margin-bottom: 6px; }
    table.sign .blank { margin-top: 6px; color: #666; }
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
                <div class="doc-tag">OFFER OF EMPLOYMENT</div>
                <div class="doc-num">Ref #ONB-<?= str_pad((string) $record['id'], 4, '0', STR_PAD_LEFT) ?></div>
            </td>
        </tr>
    </table>

    <div class="meta">
        <div class="date">Date: <?= esc(date('d/m/Y')) ?></div>
        <div>To,</div>
        <div><?= esc($record['candidate_name']) ?></div>
        <?php if (! empty($record['candidate_email'])): ?><div><?= esc($record['candidate_email']) ?></div><?php endif; ?>
        <?php if (! empty($record['candidate_phone'])): ?><div><?= esc($record['candidate_phone']) ?></div><?php endif; ?>
    </div>

    <p><strong>Subject: Offer of Employment</strong></p>
    <p>Dear <?= esc($record['candidate_name']) ?>,</p>
    <p>
        We are pleased to offer you the position of <strong><?= esc($record['designation'] ?: '—') ?></strong>
        <?php if (! empty($record['department_name'])): ?>in the <strong><?= esc($record['department_name']) ?></strong> department<?php endif; ?>
        at <strong><?= esc($company['name'] ?? $record['company_name']) ?></strong>.
    </p>

    <h3>Employment Details</h3>
    <table class="details">
        <tr><td class="k">Position</td><td><?= esc($record['designation'] ?: '—') ?></td></tr>
        <?php if (! empty($record['department_name'])): ?><tr><td class="k">Department</td><td><?= esc($record['department_name']) ?></td></tr><?php endif; ?>
        <?php if (! empty($record['employment_type']) && isset($empTypeLabels[$record['employment_type']])): ?><tr><td class="k">Employment Type</td><td><?= esc($empTypeLabels[$record['employment_type']]) ?></td></tr><?php endif; ?>
        <?php if (! empty($record['manager_name'])): ?><tr><td class="k">Reporting Manager</td><td><?= esc($record['manager_name']) ?></td></tr><?php endif; ?>
        <tr><td class="k">Joining Date</td><td><?= ! empty($record['joining_date']) ? esc(date('d/m/Y', strtotime($record['joining_date']))) : '—' ?></td></tr>
    </table>

    <h3>Compensation</h3>
    <?php if ($record['offered_ctc'] !== null): ?>
        <p>Your annual CTC will be Rs. <?= number_format((float) $record['offered_ctc'], 2) ?>.</p>
        <div class="ctc">Annual CTC: Rs. <?= number_format((float) $record['offered_ctc'], 2) ?></div>
        <p style="color:#666;"><em>The detailed salary structure will be provided separately.</em></p>
    <?php else: ?>
        <p style="color:#666;"><em>Compensation details to be confirmed separately.</em></p>
    <?php endif; ?>

    <h3>Terms &amp; Conditions</h3>
    <ol class="terms">
        <li>This offer is contingent on the successful completion of your background verification and submission of the required documents.</li>
        <li>You will be on probation from your date of joining, for a duration to be confirmed by HR, during which either party may terminate this engagement as per company policy.</li>
        <li>Your employment will be governed by the company's policies, code of conduct, and applicable laws, as may be amended from time to time.</li>
        <li>You are required to complete all onboarding formalities and provide the necessary documents on or before your date of joining.</li>
    </ol>

    <h3>Confidentiality</h3>
    <p>You agree to maintain the confidentiality of all proprietary and business information of the company, both during and after your employment, and to comply with all applicable company policies in this regard.</p>

    <p>Please sign and return a copy of this letter to indicate your acceptance of this offer.</p>
    <p>We look forward to welcoming you to <?= esc($company['name'] ?? $record['company_name']) ?>.</p>
    <p>Sincerely,</p>

    <table class="sign">
        <tr>
            <td>
                <div class="line"></div>
                <span class="role">Authorized Signatory</span>
                <div class="blank">Name: ____________________</div>
                <div class="blank">Designation: ____________________</div>
                For <?= esc($company['name'] ?? $record['company_name']) ?>
            </td>
            <td>
                <div class="line"></div>
                <span class="role">Employee Acceptance</span>
                <div>I accept the above offer and terms.</div>
                <div class="blank">Employee Name: <?= esc($record['candidate_name']) ?></div>
                <div class="blank">Signature: ____________________</div>
                <div class="blank">Date: ____________________</div>
            </td>
        </tr>
    </table>

    <div class="footer"><?= esc($company['name'] ?? $record['company_name']) ?></div>
</body>
</html>
