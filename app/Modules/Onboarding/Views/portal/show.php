<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title) ?></title>
<style>
    :root { --ink: #1f242c; --muted: #6b7a90; --accent: #d62431; --border: #e5e9ef; --success: #16a34a; --warn: #d97706; }
    * { box-sizing: border-box; }
    body { font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; color: var(--ink); background: #f4f5f7; margin: 0; padding: 24px 16px 60px; }
    .wrap { max-width: 860px; margin: 0 auto; }
    .brand { text-align: center; margin-bottom: 20px; font-weight: 700; font-size: 1.1rem; color: var(--accent); }
    .card { background: #fff; border-radius: 10px; border: 1px solid var(--border); padding: 24px; margin-bottom: 16px; }
    .card h2 { font-size: 1rem; margin: 0 0 4px; }
    .card .sub { color: var(--muted); font-size: .85rem; margin-bottom: 16px; }
    .hello { font-size: 1.25rem; font-weight: 700; margin-bottom: 4px; }
    .meta-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: .88rem; }
    .meta-row:last-child { border-bottom: none; }
    .meta-row .k { color: var(--muted); }
    label { display: block; font-size: .82rem; font-weight: 600; margin: 12px 0 4px; }
    input[type="text"], textarea { width: 100%; padding: 9px 11px; border: 1px solid var(--border); border-radius: 6px; font-size: .9rem; font-family: inherit; }
    textarea { resize: vertical; min-height: 70px; }
    .btn { display: inline-block; padding: 10px 20px; border-radius: 6px; border: none; font-weight: 600; font-size: .9rem; cursor: pointer; text-decoration: none; }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-secondary { background: #fff; color: var(--ink); border: 1px solid var(--border); }
    .btn-accept { background: var(--success); color: #fff; }
    .btn-reject { background: #fff; color: var(--accent); border: 1px solid var(--accent); }
    .actions { display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .78rem; font-weight: 600; }
    .badge-success { background: #dcfce7; color: var(--success); }
    .badge-warn { background: #fef3c7; color: var(--warn); }
    .badge-muted { background: #eef1f5; color: var(--muted); }
    .flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .88rem; }
    .flash-success { background: #dcfce7; color: #14532d; }
    .flash-error { background: #fee2e2; color: #7f1d1d; }
    .note-box { background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 14px; font-size: .85rem; margin-bottom: 12px; }
</style>
</head>
<body>
<div class="wrap">
    <div class="brand"><?= esc($record['company_name']) ?></div>
    <?php if (session()->getFlashdata('success')): ?><div class="flash flash-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?><div class="flash flash-error"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>

    <div class="card">
        <div class="hello">Hello, <?= esc($record['candidate_name']) ?></div>
        <div class="sub">Your candidate portal for <?= esc($record['company_name']) ?></div>
        <div class="meta-row"><span class="k">Reference</span><span><?= esc($reference) ?></span></div>
        <div class="meta-row"><span class="k">Applied role</span><span><?= esc($record['designation'] ?: '—') ?></span></div>
        <div class="meta-row"><span class="k">Email</span><span><?= esc($record['candidate_email']) ?></span></div>
        <?php if ($record['department_name']): ?><div class="meta-row"><span class="k">Department</span><span><?= esc($record['department_name']) ?></span></div><?php endif; ?>
        <?php if ($record['joining_date']): ?><div class="meta-row"><span class="k">Joining date</span><span><?= esc(erp_date($record['joining_date'])) ?></span></div><?php endif; ?>
    </div>

    <?php if (in_array($record['status'], ['withdrawn', 'rejected'], true)): ?>
        <div class="card"><h2><?= $record['status'] === 'rejected' ? 'Application update' : 'This process has been withdrawn' ?></h2><p class="sub" style="margin-bottom:0"><?= $record['status'] === 'rejected' ? 'The recruitment process has ended. Please contact HR if you have questions.' : 'There is nothing further to action here. Please contact HR if you believe this is a mistake.' ?></p></div>
    <?php else: ?>
        <?php if ($record['status'] === 'link_sent'): ?>
            <?= view('App\Modules\Onboarding\portal\_form', get_defined_vars()) ?>
        <?php elseif (in_array($record['status'], ['details_submitted', 'hr_review'], true)): ?>
        <div class="card">
            <h2>Candidate Details</h2>
            <span class="badge badge-warn">Submitted — with HR for review</span>
            <p class="sub" style="margin-top:10px">Thank you. Your application <strong><?= esc($reference) ?></strong> was submitted <?= $record['verification_submitted_at'] ? esc(erp_datetime($record['verification_submitted_at'])) : '' ?>. HR will contact you if anything else is needed. If HR asks for changes, use this same link to update your details and submit again.</p>
        </div>
        <?= view('App\Modules\Onboarding\partials\candidate_data', ['record' => $record, 'stored' => $stored, 'group' => $group, 'documents' => $documents, 'mode' => 'candidate', 'heading' => 'What you submitted']) ?>
        <?php elseif ($record['status'] === 'approved'): ?>
        <div class="card"><h2>Candidate Details</h2><span class="badge badge-success">Approved — offer preparation in progress</span></div>
        <?php endif; ?>

        <?php if ($record['offer_sent_at'] !== null && in_array($record['status'], ['offer_sent', 'offer_accepted'], true)): ?>
        <div class="card">
            <h2>Your Offer</h2>
            <?php if (\App\Modules\Document\Services\DocumentAvailability::shared()->has('offer_letter', (int) $record['company_id'])): ?><p class="sub"><a href="<?= site_url('onboarding/portal/' . $record['id'] . '/' . $token . '/offer-letter') ?>">View full offer letter</a></p><?php endif; ?>
            <div class="meta-row"><span class="k">Position</span><span><?= esc($record['designation'] ?: '—') ?></span></div>
            <?php if ($record['offered_ctc'] !== null): ?><div class="meta-row"><span class="k">Annual CTC</span><span><?= erp_money((float) $record['offered_ctc']) ?></span></div><?php endif; ?>
            <?php if ($record['offer_response'] === null): ?>
                <form method="post" action="<?= site_url('onboarding/portal/' . $record['id'] . '/' . $token . '/offer-response') ?>">
                    <?= csrf_field() ?><label for="offer_note">Note (optional)</label><textarea id="offer_note" name="note" maxlength="2000"></textarea>
                    <div class="actions"><button type="submit" name="response" value="accepted" class="btn btn-accept">Accept</button><button type="submit" name="response" value="rejected" class="btn btn-reject" onclick="return confirm('Reject this offer? This will withdraw your application.');">Reject</button></div>
                </form>
            <?php elseif ($record['offer_response'] === 'accepted'): ?>
                <span class="badge badge-success">Offer accepted</span>
                <p class="sub" style="margin-top:10px">HR will contact you about the next steps.</p>
            <?php endif; ?>
        </div>
        <?php elseif ($record['status'] === 'offer_accepted'): ?>
        <div class="card"><h2>Offer Accepted</h2><span class="badge badge-success">Thank you.</span><p class="sub" style="margin:10px 0 0"><?= $record['joining_letter_sent_at'] ? 'Your joining letter is below.' : 'HR will send your joining letter here shortly.' ?></p></div>
        <?php endif; ?>

        <?php
        $avail   = \App\Modules\Document\Services\DocumentAvailability::shared();
        $letters = [];
        if ($record['offer_sent_at'] !== null && $avail->has('offer_letter', (int) $record['company_id'])) {
            $letters[] = ['Offer Letter', $record['offer_sent_at'], 'offer-letter'];
        }
        if ($record['joining_letter_sent_at'] !== null && $avail->has('joining_letter', (int) $record['company_id'])) {
            $letters[] = ['Joining Letter', $record['joining_letter_sent_at'], 'joining-letter'];
        }
        ?>
        <?php if ($letters !== []): ?>
        <div class="card">
            <h2>Your Documents</h2>
            <p class="sub">Letters from <?= esc($record['company_name']) ?><?= ($record['joining_letter_sent_at'] !== null && $record['joining_date']) ? ' · Your joining date is ' . esc(erp_date($record['joining_date'])) : '' ?>.</p>
            <?php foreach ($letters as [$label, $sentAt, $path]): ?>
                <div class="meta-row" style="align-items:center;gap:10px;flex-wrap:wrap">
                    <span><strong><?= esc($label) ?></strong><br><span class="k">Sent <?= esc(erp_date($sentAt)) ?></span></span>
                    <span>
                        <a class="btn btn-secondary" style="padding:6px 12px" href="<?= site_url('onboarding/portal/' . $record['id'] . '/' . $token . '/' . $path) ?>" target="_blank" rel="noopener">View</a>
                        <a class="btn btn-primary" style="padding:6px 12px" href="<?= site_url('onboarding/portal/' . $record['id'] . '/' . $token . '/' . $path . '/pdf') ?>">Download</a>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
