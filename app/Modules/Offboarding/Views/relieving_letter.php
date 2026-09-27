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
        .letter-body h3 { font-size: .8rem; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); margin: 24px 0 8px; }

        .detail-box {
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            margin: 12px 0 20px;
        }
        .detail-row { display: flex; justify-content: space-between; padding: 9px 16px; font-size: .85rem; border-bottom: 1px solid var(--line); }
        .detail-row:last-child { border-bottom: none; }
        .detail-row .k { color: var(--muted); }

        .signature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 48px; }
        .signature .line { border-top: 1px solid var(--ink); width: 220px; margin-bottom: 6px; padding-top: 34px; }
        .signature .role { font-size: .82rem; color: var(--muted); }
        .signature .blank { font-size: .85rem; margin-top: 10px; color: var(--muted); }
        .signature .blank span { display: inline-block; border-bottom: 1px solid var(--line); min-width: 140px; }
        .signature .seal { font-size: .8rem; margin-top: 28px; color: var(--muted); border: 1px dashed var(--line); border-radius: 50%; width: 90px; height: 90px; display: flex; align-items: center; justify-content: center; text-align: center; }

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
    <a href="<?= site_url('hr/offboarding/' . $record['id'] . '/relieving-letter/pdf') ?>" class="secondary">Download PDF</a>
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
            <div class="tag">RELIEVING LETTER</div>
            <div class="num">Ref #OFB-<?= str_pad((string) $record['id'], 4, '0', STR_PAD_LEFT) ?></div>
        </div>
    </div>

    <div class="letter-meta">
        <div class="date">Date: <?= esc(date('d/m/Y')) ?></div>
        <div>TO WHOM IT MAY CONCERN</div>
    </div>

    <div class="letter-body">
        <p>
            This is to certify that Mr./Ms. <strong><?= esc($record['employee_name']) ?></strong>,
            Employee ID <?= esc($record['employee_code'] ?? '—') ?>,
            was employed with <strong><?= esc($company['name'] ?? $record['company_name']) ?></strong>
            as <strong><?= esc($record['designation'] ?? '—') ?></strong><?= ! empty($record['department_name']) ? ' in the ' . esc($record['department_name']) . ' department' : '' ?>.
        </p>

        <h3>Employment Details</h3>
        <div class="detail-box">
            <div class="detail-row"><span class="k">Employee ID</span><span><?= esc($record['employee_code'] ?? '—') ?></span></div>
            <div class="detail-row"><span class="k">Designation</span><span><?= esc($record['designation'] ?? '—') ?></span></div>
            <?php if (! empty($record['department_name'])): ?>
            <div class="detail-row"><span class="k">Department</span><span><?= esc($record['department_name']) ?></span></div>
            <?php endif; ?>
            <div class="detail-row"><span class="k">Date of Joining</span><span><?= ! empty($record['date_of_joining']) ? esc(date('d/m/Y', strtotime($record['date_of_joining']))) : '—' ?></span></div>
            <div class="detail-row"><span class="k">Last Working Day</span><span><?= $lastDay ? esc(date('d/m/Y', strtotime($lastDay))) : '—' ?></span></div>
            <?php if ($tenure): ?>
            <div class="detail-row"><span class="k">Total Tenure</span><span><?= esc($tenure) ?></span></div>
            <?php endif; ?>
        </div>

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
    </div>

    <div class="signature-grid">
        <div class="signature">
            <div class="line"></div>
            <span class="role">Authorized Signatory, HR</span>
            <div class="blank">Name: <span>&nbsp;</span></div>
            <div class="blank">Designation: <span>&nbsp;</span></div>
            For <?= esc($company['name'] ?? $record['company_name']) ?>
        </div>
        <div class="signature">
            <div class="seal">Company Seal</div>
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
