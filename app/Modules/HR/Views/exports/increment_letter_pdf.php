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
    .ctc { border: 1px solid #d9dfe6; border-radius: 4px; padding: 8px 12px; margin-bottom: 4px; font-size: 10.5px; }
    .ctc.new { font-weight: bold; background: #f6f8fa; }
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
                <div class="co-name"><?= esc($letter['company_name']) ?></div>
                <?php if (! empty($letter['registered_address'])): ?><div class="co-addr"><?= esc($letter['registered_address']) ?></div><?php endif; ?>
                <?php if (! empty($letter['cin'])): ?><div class="co-addr">CIN: <?= esc($letter['cin']) ?></div><?php endif; ?>
            </td>
            <td style="width:40%">
                <div class="doc-tag">LETTER OF INCREMENT</div>
                <div class="doc-num">Ref #INC-<?= str_pad((string) $letter['id'], 4, '0', STR_PAD_LEFT) ?></div>
            </td>
        </tr>
    </table>

    <div class="meta">
        <div class="date">Date: <?= esc(erp_date($letter['created_at'])) ?></div>
        <div>To,</div>
        <div><?= esc($letter['user_name']) ?></div>
        <?php if (! empty($letter['employee_code'])): ?><div><?= esc($letter['employee_code']) ?></div><?php endif; ?>
        <?php if (! empty($letter['user_email'])): ?><div><?= esc($letter['user_email']) ?></div><?php endif; ?>
    </div>

    <p><strong>Subject: Revision in Compensation</strong></p>
    <p>Dear <?= esc($letter['user_name']) ?>,</p>
    <p>
        We are pleased to inform you that, in recognition of your contribution
        <?= $letter['reason'] ? 'and ' . esc(lcfirst($letter['reason'])) : '' ?>,
        your compensation has been revised with effect from <strong><?= esc(erp_date($letter['effective_date'])) ?></strong>.
    </p>

    <h3>Compensation Revision</h3>
    <?php if ($letter['previous_ctc'] !== null): ?>
    <div class="ctc">Previous Annual CTC: Rs. <?= erp_number((float) $letter['previous_ctc'], 2) ?></div>
    <?php endif; ?>
    <div class="ctc new">Revised Annual CTC: Rs. <?= erp_number((float) $letter['new_ctc'], 2) ?></div>
    <p style="color:#666;margin-top:10px;"><em>The detailed revised salary structure will be provided separately.</em></p>

    <p>All other terms and conditions of your employment remain unchanged.</p>
    <p>We appreciate your continued contribution and look forward to your ongoing success with us.</p>
    <p>Sincerely,</p>

    <table class="sign">
        <tr>
            <td>
                <div class="line"></div>
                <span class="role">Authorized Signatory</span>
                <div class="blank">Name: ____________________</div>
                <div class="blank">Designation: ____________________</div>
                For <?= esc($letter['company_name']) ?>
            </td>
            <td>
                <div class="line"></div>
                <span class="role">Employee Acknowledgement</span>
                <div>I acknowledge receipt of this letter.</div>
                <div class="blank">Employee Name: <?= esc($letter['user_name']) ?></div>
                <div class="blank">Signature: ____________________</div>
                <div class="blank">Date: ____________________</div>
            </td>
        </tr>
    </table>

    <div class="footer"><?= esc($letter['company_name']) ?></div>
</body>
</html>
