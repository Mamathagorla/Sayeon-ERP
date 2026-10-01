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
    .wrap { max-width: 640px; margin: 0 auto; }
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

    <?php if (session()->getFlashdata('success')): ?>
        <div class="flash flash-success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="flash flash-error"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="hello">Hello, <?= esc($record['candidate_name']) ?></div>
        <div class="sub">Your onboarding at <?= esc($record['company_name']) ?></div>
        <div class="meta-row"><span class="k">Position</span><span><?= esc($record['designation'] ?: '—') ?></span></div>
        <?php if ($record['department_name']): ?>
        <div class="meta-row"><span class="k">Department</span><span><?= esc($record['department_name']) ?></span></div>
        <?php endif; ?>
        <?php if ($record['joining_date']): ?>
        <div class="meta-row"><span class="k">Joining Date</span><span><?= esc(date('d M Y', strtotime($record['joining_date']))) ?></span></div>
        <?php endif; ?>
    </div>

    <?php if ($record['status'] === 'withdrawn'): ?>
        <div class="card">
            <h2>This process has been withdrawn</h2>
            <p class="sub" style="margin-bottom:0">There is nothing further to action here. If you believe this is a mistake, please contact HR directly.</p>
        </div>
    <?php else: ?>

    <div class="card">
        <h2>Verify Your Information</h2>
        <p class="sub">Please confirm your details are correct before we proceed.</p>

        <?php if (in_array($record['verification_status'], [null, 'pending', 'correction_needed'], true)): ?>
            <?php if ($record['verification_status'] === 'correction_needed' && $record['verification_notes']): ?>
                <div class="note-box"><strong>HR requested a correction:</strong> <?= esc($record['verification_notes']) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= site_url('onboarding/portal/' . $record['id'] . '/' . $token . '/verify') ?>">
                <?= csrf_field() ?>
                <div class="meta-row"><span class="k">Name</span><span><?= esc($record['candidate_name']) ?></span></div>
                <div class="meta-row"><span class="k">Email</span><span><?= esc($record['candidate_email']) ?></span></div>
                <label for="candidate_phone">Phone Number</label>
                <input type="text" id="candidate_phone" name="candidate_phone" maxlength="10" pattern="[0-9]{10}" value="<?= esc($record['candidate_phone'] ?? '') ?>" placeholder="10-digit mobile number">
                <label for="notes">Anything HR should know? (optional)</label>
                <textarea id="notes" name="notes" maxlength="2000"></textarea>
                <div class="actions"><button type="submit" class="btn btn-primary">Confirm &amp; Submit</button></div>
            </form>
        <?php elseif ($record['verification_status'] === 'submitted'): ?>
            <span class="badge badge-warn">Submitted — awaiting HR review</span>
            <p class="sub" style="margin-top:10px">Submitted on <?= esc(date('d M Y, g:i A', strtotime($record['verification_submitted_at']))) ?>.</p>
        <?php elseif ($record['verification_status'] === 'verified'): ?>
            <span class="badge badge-success">Verified by HR</span>
        <?php endif; ?>
    </div>

    <?php if ($record['offer_sent_at'] !== null && $record['verification_status'] !== 'verified'): ?>
    <div class="card">
        <h2>Your Offer</h2>
        <span class="badge badge-warn">Pending HR review</span>
        <p class="sub" style="margin-top:10px">Your offer will appear here once HR has reviewed and verified your information above.</p>
    </div>
    <?php elseif ($record['offer_sent_at'] !== null): ?>
    <div class="card">
        <h2>Your Offer</h2>
        <p class="sub"><a href="<?= site_url('onboarding/portal/' . $record['id'] . '/' . $token . '/offer-letter') ?>">View full offer letter →</a></p>

        <?php if ($record['offer_response'] === null): ?>
            <div class="meta-row"><span class="k">Position</span><span><?= esc($record['designation'] ?: '—') ?></span></div>
            <?php if ($record['offered_ctc'] !== null): ?>
            <div class="meta-row"><span class="k">Annual CTC</span><span>&#8377; <?= number_format((float) $record['offered_ctc'], 2) ?></span></div>
            <?php endif; ?>
            <form method="post" action="<?= site_url('onboarding/portal/' . $record['id'] . '/' . $token . '/offer-response') ?>">
                <?= csrf_field() ?>
                <label for="note">Note (optional)</label>
                <textarea id="note" name="note" maxlength="2000"></textarea>
                <div class="actions">
                    <button type="submit" name="response" value="accepted" class="btn btn-accept">Accept Offer</button>
                    <button type="submit" name="response" value="rejected" class="btn btn-reject" onclick="return confirm('Are you sure you want to decline this offer?');">Decline Offer</button>
                </div>
            </form>
        <?php elseif ($record['offer_response'] === 'accepted'): ?>
            <span class="badge badge-success">You accepted this offer</span>
            <p class="sub" style="margin-top:10px">on <?= esc(date('d M Y', strtotime($record['offer_response_at']))) ?>. HR will be in touch about the next steps.</p>
        <?php else: ?>
            <span class="badge badge-muted">You declined this offer</span>
            <p class="sub" style="margin-top:10px">on <?= esc(date('d M Y', strtotime($record['offer_response_at']))) ?>.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
</body>
</html>
