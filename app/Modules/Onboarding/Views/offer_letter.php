<?php
$empTypeLabels = ['full_time' => 'Full Time', 'part_time' => 'Part Time', 'contract' => 'Contract', 'intern' => 'Intern'];
?>
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

        .detail-box, .ctc-box {
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            margin: 12px 0 20px;
        }
        .detail-row, .ctc-row { display: flex; justify-content: space-between; padding: 9px 16px; font-size: .85rem; border-bottom: 1px solid var(--line); }
        .detail-row:last-child, .ctc-row:last-child { border-bottom: none; }
        .detail-row .k, .ctc-row .k { color: var(--muted); }
        .ctc-row:last-child { font-weight: 700; background: #f6f8fa; }

        .terms { font-size: .85rem; margin: 8px 0 20px; padding-left: 20px; }
        .terms li { margin-bottom: 8px; }

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
    <a href="<?= site_url('hr/onboarding/' . $record['id'] . '/offer-letter/pdf') ?>" class="secondary">Download PDF</a>
    <button onclick="window.print()">Print</button>
</div>

<div class="sheet">
    <div class="letterhead">
        <div>
            <h1><?= esc($company['name'] ?? $record['company_name']) ?></h1>
            <?php if (! empty($company['registered_address'])): ?>
                <div class="addr"><?= nl2br(esc($company['registered_address'])) ?></div>
            <?php endif; ?>
            <?php if (! empty($company['cin'])): ?>
                <div class="addr">CIN: <?= esc($company['cin']) ?></div>
            <?php endif; ?>
        </div>
        <div class="doc-label">
            <div class="tag">OFFER OF EMPLOYMENT</div>
            <div class="num">Ref #ONB-<?= str_pad((string) $record['id'], 4, '0', STR_PAD_LEFT) ?></div>
        </div>
    </div>

    <div class="letter-meta">
        <div class="date">Date: <?= esc(date('d/m/Y')) ?></div>
        <div>To,</div>
        <div><?= esc($record['candidate_name']) ?></div>
        <?php if (! empty($record['candidate_email'])): ?><div><?= esc($record['candidate_email']) ?></div><?php endif; ?>
        <?php if (! empty($record['candidate_phone'])): ?><div><?= esc($record['candidate_phone']) ?></div><?php endif; ?>
    </div>

    <div class="letter-body">
        <p class="subject"><strong>Subject: Offer of Employment</strong></p>

        <p>Dear <?= esc($record['candidate_name']) ?>,</p>

        <p>
            We are pleased to offer you the position of <strong><?= esc($record['designation'] ?: '—') ?></strong>
            <?php if (! empty($record['department_name'])): ?>in the <strong><?= esc($record['department_name']) ?></strong> department<?php endif; ?>
            at <strong><?= esc($company['name'] ?? $record['company_name']) ?></strong>.
        </p>

        <h3>Employment Details</h3>
        <div class="detail-box">
            <div class="detail-row"><span class="k">Position</span><span><?= esc($record['designation'] ?: '—') ?></span></div>
            <?php if (! empty($record['department_name'])): ?>
            <div class="detail-row"><span class="k">Department</span><span><?= esc($record['department_name']) ?></span></div>
            <?php endif; ?>
            <?php if (! empty($record['employment_type']) && isset($empTypeLabels[$record['employment_type']])): ?>
            <div class="detail-row"><span class="k">Employment Type</span><span><?= esc($empTypeLabels[$record['employment_type']]) ?></span></div>
            <?php endif; ?>
            <?php if (! empty($record['manager_name'])): ?>
            <div class="detail-row"><span class="k">Reporting Manager</span><span><?= esc($record['manager_name']) ?></span></div>
            <?php endif; ?>
            <div class="detail-row"><span class="k">Joining Date</span><span><?= ! empty($record['joining_date']) ? esc(date('d/m/Y', strtotime($record['joining_date']))) : '—' ?></span></div>
        </div>

        <h3>Compensation</h3>
        <?php if ($record['offered_ctc'] !== null): ?>
        <p>Your annual CTC will be &#8377; <?= number_format((float) $record['offered_ctc'], 2) ?>.</p>
        <div class="ctc-box">
            <div class="ctc-row"><span class="k">Annual CTC</span><span>&#8377; <?= number_format((float) $record['offered_ctc'], 2) ?></span></div>
        </div>
        <p style="color:var(--muted);"><em>The detailed salary structure will be provided separately.</em></p>
        <?php else: ?>
        <p style="color:var(--muted);"><em>Compensation details to be confirmed separately.</em></p>
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
    </div>

    <div class="signature-grid">
        <div class="signature">
            <div class="line"></div>
            <span class="role">Authorized Signatory</span>
            <div class="blank">Name: <span>&nbsp;</span></div>
            <div class="blank">Designation: <span>&nbsp;</span></div>
            For <?= esc($company['name'] ?? $record['company_name']) ?>
        </div>
        <div class="signature">
            <div class="line"></div>
            <span class="role">Employee Acceptance</span>
            <div style="margin-top:4px;">I accept the above offer and terms.</div>
            <div class="blank">Employee Name: <span><?= esc($record['candidate_name']) ?></span></div>
            <div class="blank">Signature: <span>&nbsp;</span></div>
            <div class="blank">Date: <span>&nbsp;</span></div>
        </div>
    </div>

    <div class="footer">
        <?= esc($company['name'] ?? $record['company_name']) ?>
    </div>
</div>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>

</body>
</html>
