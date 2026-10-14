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
                <div class="doc-tag">LETTER OF JOINING</div>
                <div class="doc-num">Ref #ONB-<?= str_pad((string) $record['id'], 4, '0', STR_PAD_LEFT) ?></div>
            </td>
        </tr>
    </table>

    <div class="meta">
        <div class="date">Date: <?= esc(erp_date($record['joined_at'])) ?></div>
        <div>To,</div>
        <div><?= esc($record['candidate_name']) ?></div>
        <?php if (! empty($record['candidate_email'])): ?><div><?= esc($record['candidate_email']) ?></div><?php endif; ?>
        <?php if (! empty($record['candidate_phone'])): ?><div><?= esc($record['candidate_phone']) ?></div><?php endif; ?>
    </div>

    <p><strong>Subject: Confirmation of Joining</strong></p>
    <p>Dear <?= esc($record['candidate_name']) ?>,</p>
    <p>
        We are pleased to confirm that you have joined <strong><?= esc($company['name'] ?? $record['company_name']) ?></strong>
        as <strong><?= esc($record['designation'] ?: '—') ?></strong>
        <?php if (! empty($record['department_name'])): ?>in the <strong><?= esc($record['department_name']) ?></strong> department<?php endif; ?>,
        in accordance with the terms of the offer letter (Ref #ONB-<?= str_pad((string) $record['id'], 4, '0', STR_PAD_LEFT) ?>) extended to and accepted by you.
    </p>

    <h3>Joining Details</h3>
    <table class="details">
        <tr><td class="k">Position</td><td><?= esc($record['designation'] ?: '—') ?></td></tr>
        <?php if (! empty($record['department_name'])): ?><tr><td class="k">Department</td><td><?= esc($record['department_name']) ?></td></tr><?php endif; ?>
        <?php if (! empty($record['employment_type']) && isset($empTypeLabels[$record['employment_type']])): ?><tr><td class="k">Employment Type</td><td><?= esc($empTypeLabels[$record['employment_type']]) ?></td></tr><?php endif; ?>
        <?php if (! empty($record['manager_name'])): ?><tr><td class="k">Reporting Manager</td><td><?= esc($record['manager_name']) ?></td></tr><?php endif; ?>
        <tr><td class="k">Date of Joining</td><td><?= esc(erp_date($record['joined_at'])) ?></td></tr>
        <?php if (! empty($record['probation_end_date'])): ?><tr><td class="k">Probation Ends</td><td><?= esc(erp_date($record['probation_end_date'])) ?></td></tr><?php endif; ?>
    </table>

    <h3>Terms &amp; Conditions</h3>
    <ol class="terms">
        <li>Your employment is subject to the successful completion of the probation period referred to above, during which either party may terminate this engagement as per company policy.</li>
        <li>You will be governed by the company's policies, code of conduct, and applicable laws, as may be amended from time to time.</li>
        <li>All terms of your original offer letter continue to apply except where expressly varied by this letter.</li>
    </ol>

    <p>We are glad to have you on board and look forward to a long and successful association.</p>
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
                <span class="role">Employee Acknowledgement</span>
                <div>I acknowledge receipt of this letter.</div>
                <div class="blank">Employee Name: <?= esc($record['candidate_name']) ?></div>
                <div class="blank">Signature: ____________________</div>
                <div class="blank">Date: ____________________</div>
            </td>
        </tr>
    </table>

    <div class="footer"><?= esc($company['name'] ?? $record['company_name']) ?></div>
</body>
</html>
