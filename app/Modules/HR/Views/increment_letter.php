<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= esc($title) ?></title>
    <style>
        :root { --ink: #10243f; --muted: #6b7a90; --teal: #0d9488; --line: #d9dfe6; }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            color: var(--ink);
            margin: 0;
            padding: 32px;
            background: #eef2f5;
        }
        .sheet {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 48px 56px;
        }
        .toolbar {
            max-width: 800px;
            margin: 0 auto 14px;
            text-align: right;
        }
        .toolbar a, .toolbar button {
            display: inline-block;
            background: var(--teal);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 9px 20px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            margin-left: 8px;
        }
        .toolbar a.secondary { background: #fff; color: var(--teal); border: 1px solid var(--teal); }
        .letterhead {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--ink);
            padding-bottom: 16px;
            margin-bottom: 28px;
        }
        .letterhead h1 { font-size: 1.5rem; margin: 0 0 4px; color: var(--ink); }
        .letterhead .addr { font-size: .82rem; color: var(--muted); max-width: 380px; }
        .letterhead .doc-label { text-align: right; }
        .letterhead .doc-label .tag { font-size: 1.1rem; font-weight: 700; letter-spacing: 1px; color: var(--teal); }
        .letterhead .doc-label .num { font-size: .82rem; color: var(--muted); margin-top: 2px; }

        .letter-meta { font-size: .85rem; margin-bottom: 24px; }
        .letter-meta .date { margin-bottom: 14px; }
        .letter-body { font-size: .92rem; line-height: 1.7; }
        .letter-body p { margin: 0 0 14px; }
        .letter-body .subject { margin: 0 0 14px; }
        .letter-body h3 { font-size: .8rem; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); margin: 24px 0 8px; }

        .ctc-box { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; margin: 12px 0 20px; }
        .ctc-row { display: flex; justify-content: space-between; padding: 9px 16px; font-size: .85rem; border-bottom: 1px solid var(--line); }
        .ctc-row:last-child { border-bottom: none; font-weight: 700; background: #f6f8fa; }
        .ctc-row .k { color: var(--muted); }

        .signature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 40px; }
        .signature .line { border-top: 1px solid var(--ink); width: 220px; margin-bottom: 6px; padding-top: 34px; }
        .signature .role { font-size: .82rem; color: var(--muted); }
        .signature .blank { font-size: .85rem; margin-top: 10px; color: var(--muted); }
        .signature .blank span { display: inline-block; border-bottom: 1px solid var(--line); min-width: 140px; }

        .footer { border-top: 1px solid var(--line); margin-top: 36px; padding-top: 14px; text-align: center; font-size: .78rem; color: var(--muted); }

        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .sheet { padding: 0; max-width: none; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="<?= site_url('hr/employees/' . $letter['employee_profile_id'] . '/increment-letters/' . $letter['id'] . '/pdf') ?>" class="secondary">Download PDF</a>
    <button onclick="window.print()">Print</button>
</div>

<div class="sheet">
    <div class="letterhead">
        <div>
            <h1><?= esc($letter['company_name']) ?></h1>
            <?php if (! empty($letter['registered_address'])): ?>
                <div class="addr"><?= nl2br(esc($letter['registered_address'])) ?></div>
            <?php endif; ?>
            <?php if (! empty($letter['cin'])): ?>
                <div class="addr">CIN: <?= esc($letter['cin']) ?></div>
            <?php endif; ?>
        </div>
        <div class="doc-label">
            <div class="tag">LETTER OF INCREMENT</div>
            <div class="num">Ref #INC-<?= str_pad((string) $letter['id'], 4, '0', STR_PAD_LEFT) ?></div>
        </div>
    </div>

    <div class="letter-meta">
        <div class="date">Date: <?= esc(date('d/m/Y', strtotime($letter['created_at']))) ?></div>
        <div>To,</div>
        <div><?= esc($letter['user_name']) ?></div>
        <?php if (! empty($letter['employee_code'])): ?><div><?= esc($letter['employee_code']) ?></div><?php endif; ?>
        <?php if (! empty($letter['user_email'])): ?><div><?= esc($letter['user_email']) ?></div><?php endif; ?>
    </div>

    <div class="letter-body">
        <p class="subject"><strong>Subject: Revision in Compensation</strong></p>

        <p>Dear <?= esc($letter['user_name']) ?>,</p>

        <p>
            We are pleased to inform you that, in recognition of your contribution
            <?= $letter['reason'] ? 'and ' . esc(lcfirst($letter['reason'])) : '' ?>,
            your compensation has been revised with effect from <strong><?= esc(date('d M Y', strtotime($letter['effective_date']))) ?></strong>.
        </p>

        <h3>Compensation Revision</h3>
        <div class="ctc-box">
            <?php if ($letter['previous_ctc'] !== null): ?>
            <div class="ctc-row"><span class="k">Previous Annual CTC</span><span>&#8377; <?= number_format((float) $letter['previous_ctc'], 2) ?></span></div>
            <?php endif; ?>
            <div class="ctc-row"><span class="k">Revised Annual CTC</span><span>&#8377; <?= number_format((float) $letter['new_ctc'], 2) ?></span></div>
        </div>
        <p style="color:var(--muted);"><em>The detailed revised salary structure will be provided separately.</em></p>

        <p>All other terms and conditions of your employment remain unchanged.</p>

        <p>We appreciate your continued contribution and look forward to your ongoing success with us.</p>

        <p>Sincerely,</p>
    </div>

    <div class="signature-grid">
        <div class="signature">
            <div class="line"></div>
            <span class="role">Authorized Signatory</span>
            <div class="blank">Name: <span>&nbsp;</span></div>
            <div class="blank">Designation: <span>&nbsp;</span></div>
            For <?= esc($letter['company_name']) ?>
        </div>
        <div class="signature">
            <div class="line"></div>
            <span class="role">Employee Acknowledgement</span>
            <div style="margin-top:4px;">I acknowledge receipt of this letter.</div>
            <div class="blank">Employee Name: <span><?= esc($letter['user_name']) ?></span></div>
            <div class="blank">Signature: <span>&nbsp;</span></div>
            <div class="blank">Date: <span>&nbsp;</span></div>
        </div>
    </div>

    <div class="footer">
        <?= esc($letter['company_name']) ?>
    </div>
</div>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>

</body>
</html>
