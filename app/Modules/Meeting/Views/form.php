<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form action="<?= $meeting ? site_url('meetings/' . $meeting['id']) : site_url('meetings') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Meeting Title</label>
                    <input type="text" name="title" class="form-control" value="<?= esc($meeting['title'] ?? old('title')) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($meeting['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Agenda</label>
                    <textarea name="agenda" class="form-control" rows="3"><?= esc($meeting['agenda'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="meeting_date" class="form-control" value="<?= esc($meeting['meeting_date'] ?? '') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Start Time</label>
                    <input type="time" name="start_time" class="form-control" value="<?= esc($meeting['start_time'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">End Time</label>
                    <input type="time" name="end_time" class="form-control" value="<?= esc($meeting['end_time'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Location <span class="text-muted small">(room, optional)</span></label>
                    <input type="text" name="location" class="form-control" value="<?= esc($meeting['location'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Meeting Provider</label>
                    <select name="provider" id="meetingProviderSelect" class="form-select">
                        <option value="none" <?= (($meeting['provider'] ?? 'none') === 'none') ? 'selected' : '' ?>>None (manual link below)</option>
                        <option value="google_meet" <?= (($meeting['provider'] ?? '') === 'google_meet') ? 'selected' : '' ?>>Google Meet</option>
                        <option value="zoom" <?= (($meeting['provider'] ?? '') === 'zoom') ? 'selected' : '' ?>>Zoom</option>
                    </select>
                    <div class="form-text" id="providerHint">A real meeting is created automatically through the selected provider's API and its join link sent to every participant.</div>
                </div>
                <div class="col-12 mb-3" id="googleConnectField" style="display:none">
                    <?php if (! empty($googleConnection['refresh_token'])): ?>
                    <div class="alert alert-success py-2 small d-flex justify-content-between align-items-center mb-0">
                        <span><i class="fab fa-google me-1"></i>Google account connected<?= $googleConnection['google_email'] ? ' (' . esc($googleConnection['google_email']) . ')' : '' ?> — real Google Meet links will be created automatically.</span>
                        <form action="<?= site_url('meetings/google/disconnect') ?>" method="post" class="d-inline" onsubmit="return confirm('Disconnect your Google account? Existing Google Meet meetings will keep working, but you won\'t be able to create new ones until you reconnect.');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Disconnect</button>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning py-2 small mb-0">
                        <i class="fab fa-google me-1"></i>Connect your Google account to create real Google Meet meetings — one-time setup.
                        <a href="<?= site_url('meetings/google/connect') ?>" class="btn btn-sm btn-primary ms-2">Connect Google Account</a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3" id="manualLinkField">
                    <label class="form-label">External Meeting Link <span class="text-muted small">(optional — e.g. an existing Zoom/Teams link)</span></label>
                    <input type="url" name="meeting_link" class="form-control" placeholder="https://…" value="<?= esc($meeting['meeting_link'] ?? '') ?>">
                    <div class="form-text">Every meeting also gets its own auto-generated internal join link, sent to participants automatically — this is only needed if you're hosting the actual call elsewhere.</div>
                </div>
                <?php if (($meeting['provider'] ?? 'none') !== 'none'): ?>
                <div class="col-12 mb-3">
                    <div class="alert alert-info py-2 small mb-0">
                        <?= esc(ucwords(str_replace('_', ' ', $meeting['provider']))) ?> meeting —
                        <?php if ($meeting['meeting_link']): ?>
                            join link: <a href="<?= esc($meeting['meeting_link']) ?>" target="_blank" rel="noopener"><?= esc($meeting['meeting_link']) ?></a>.
                        <?php else: ?>
                            no join link on file.
                        <?php endif; ?>
                        Changing the date/time here will update this same external meeting automatically — it will not create a duplicate.
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-12 mb-3">
                    <label class="form-label">Participants <span class="text-muted small">(who is permitted to attend — they're notified with the meeting link)</span></label>
                    <div class="border rounded p-2" style="max-height:220px;overflow-y:auto">
                        <?php if (empty($users)): ?>
                            <div class="text-muted small">No users available to invite.</div>
                        <?php endif; ?>
                        <?php foreach ($users as $u): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="participant_user_ids[]" value="<?= $u['id'] ?>" id="mtUser<?= $u['id'] ?>" <?= in_array((int) $u['id'], $selectedParticipantIds, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="mtUser<?= $u['id'] ?>"><?= esc($u['name']) ?><?php if (! empty($u['email'])): ?> <span class="text-muted small"><?= esc($u['email']) ?></span><?php endif; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">External (non-employee) invitees can be added after scheduling, from the meeting's own page.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $meeting ? 'Update Meeting' : 'Schedule Meeting' ?></button>
            <a href="<?= site_url('meetings') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var providerSelect = document.getElementById('meetingProviderSelect');
    var manualLinkField = document.getElementById('manualLinkField');
    var googleConnectField = document.getElementById('googleConnectField');

    function syncManualLinkVisibility() {
        manualLinkField.style.display = providerSelect.value === 'none' ? '' : 'none';
        googleConnectField.style.display = providerSelect.value === 'google_meet' ? '' : 'none';
    }

    providerSelect.addEventListener('change', syncManualLinkVisibility);
    syncManualLinkVisibility();
});
</script>
<?= $this->endSection() ?>
