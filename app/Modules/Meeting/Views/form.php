<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form data-erp-sections data-erp-first-section="Meeting Details" action="<?= $meeting ? site_url('meetings/' . $meeting['id']) : site_url('meetings') ?>" method="post">
            <?= csrf_field() ?>
            <?php /* One token per form load, re-sent on a retry after an error, so a timed-out Zoom create can be reconciled instead of repeated. */ ?>
            <input type="hidden" name="submission_token" value="<?= esc(old('submission_token') ?: bin2hex(random_bytes(8)), 'attr') ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Meeting Title</label>
                    <input type="text" name="title" class="form-control" value="<?= esc($meeting['title'] ?? old('title')) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" id="meetingCompanySelect" class="form-select" required>
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
                <div class="col-12 erp-section-marker" data-section-title="Schedule &amp; Location"></div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="meeting_date" class="form-control" value="<?= esc($meeting['meeting_date'] ?? '') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Start Time</label>
                    <input type="time" name="start_time" class="form-control" value="<?= esc(old('start_time', $meeting['start_time'] ?? '')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">End Time</label>
                    <input type="time" name="end_time" class="form-control" value="<?= esc(old('end_time', $meeting['end_time'] ?? '')) ?>">
                </div>
                <div class="col-12 mb-3">
                    <div class="form-text mt-0">Times are in <strong><?= esc($entryTimezone) ?></strong>.<?php if ($entryTimezone !== $serverTimezone): ?> Zoom meetings are scheduled in <?= esc($serverTimezone) ?>, so their times are converted automatically.<?php endif; ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Location <span class="text-muted small">(room, optional)</span></label>
                    <input type="text" name="location" class="form-control" value="<?= esc($meeting['location'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Calendar &amp; Online Meeting</label>
                    <select name="provider" id="meetingProviderSelect" class="form-select">
                        <option value="google_meet" <?= (($meeting['provider'] ?? 'google_meet') === 'google_meet') ? 'selected' : '' ?>>Google Calendar + Google Meet</option>
                        <option value="zoom" <?= (($meeting['provider'] ?? '') === 'zoom') ? 'selected' : '' ?>>Zoom</option>
                        <option value="none" <?= (($meeting['provider'] ?? '') === 'none') ? 'selected' : '' ?>>No online meeting</option>
                    </select>
                    <div class="form-text" id="providerHint">The selected calendar is the source of truth: the event is created there, with invitations sent to attendees and the video meeting attached to it.</div>
                </div>
                <div class="col-12 mb-3" id="googleConnectField" style="display:none">
                    <?php $googleUsable = \App\Modules\Meeting\Models\GoogleOAuthConnectionModel::isUsable($googleConnection); ?>
                    <?php if ($googleUsable): ?>
                    <div class="alert alert-success py-2 small d-flex justify-content-between align-items-center mb-0">
                        <span><i class="fab fa-google me-1"></i>Google account connected<?= $googleConnection['google_email'] ? ' (' . esc($googleConnection['google_email']) . ')' : '' ?> — real Google Meet links will be created automatically.</span>
                        <button type="submit" form="googleDisconnectForm" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Disconnect your Google account? Existing Google Meet meetings will keep working, but you won\'t be able to create new ones until you reconnect.');">Disconnect</button>
                    </div>
                    <?php elseif (! empty($googleConnection['google_email']) || ! empty($googleConnection['refresh_token'])): ?>
                    <div class="alert alert-warning py-2 small mb-0">
                        <i class="fab fa-google me-1"></i>Your Google connection needs to be renewed: calendar permission is missing or access was revoked. Click Reconnect and tick the calendar permission on Google's screen.
                        <a href="<?= site_url('meetings/google/connect') ?>" class="btn btn-sm btn-primary ms-2">Reconnect Google Account</a>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning py-2 small mb-0">
                        <i class="fab fa-google me-1"></i>Connect your Google account to create real Google Meet meetings — one-time setup.
                        <a href="<?= site_url('meetings/google/connect') ?>" class="btn btn-sm btn-primary ms-2">Connect Google Account</a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3" id="manualLinkField">
                    <label class="form-label">External Meeting Link <span class="text-muted small">(optional — e.g. an existing Zoom link)</span></label>
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
                <div class="col-12 erp-section-marker" data-section-title="Participants"></div>
                <div class="col-12 mb-3">
                    <label class="form-label">Participants <span class="text-muted small">(who is permitted to attend — they're notified with the meeting link)</span></label>
                    <div class="border rounded p-2" id="participantList" style="max-height:220px;overflow-y:auto">
                        <?php if (empty($users)): ?>
                            <div class="text-muted small">No users available to invite.</div>
                        <?php endif; ?>
                        <?php foreach ($users as $u): ?>
                        <?php /* data-self on the viewer's own row: store()/update() never drop the organizer
                                 for a company mismatch, so the company filter below must not either — otherwise
                                 an organizer with no employee_profiles row (e.g. Super Admin) could see their
                                 own name silently unchecked on page load and be removed as a participant on save. */ ?>
                        <div class="form-check" data-company-id="<?= esc($u['company_id'] ?? '', 'attr') ?>"<?= ((int) $u['id'] === (int) session('userId')) ? ' data-self="1"' : '' ?>>
                            <input class="form-check-input" type="checkbox" name="participant_user_ids[]" value="<?= $u['id'] ?>" id="mtUser<?= $u['id'] ?>" <?= in_array((int) $u['id'], $selectedParticipantIds, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="mtUser<?= $u['id'] ?>"><?= esc($u['name']) ?><?php if (! empty($u['email'])): ?> <span class="text-muted small"><?= esc($u['email']) ?></span><?php endif; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text" id="participantCompanyHint">External (non-employee) invitees can be added after scheduling, from the meeting's own page.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $meeting ? 'Update Meeting' : 'Schedule Meeting' ?></button>
            <a href="<?= site_url('meetings') ?>" class="btn btn-light">Cancel</a>
        </form>
        <?php if (! empty($googleConnection['refresh_token']) || ! empty($googleConnection['google_email'])): ?>
        <form id="googleDisconnectForm" action="<?= site_url('meetings/google/disconnect') ?>" method="post" class="d-none">
            <?= csrf_field() ?>
        </form>
        <?php endif; ?>
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

    // Participants narrow to the selected Company, the same pattern (and the
    // same getElementById-by-id fix) as the Task form's Project/Assigned To
    // filtering: the topbar's own "viewing company" switcher is ALSO a
    // <select name="company_id">, so a plain name= selector would silently
    // bind to that one instead of this form's field. A checkbox that becomes
    // out of scope is unchecked, not just hidden — the server also rejects a
    // cross-company participant (MeetingController::participantsMatchCompany()),
    // this just keeps the form from ever submitting one in the first place.
    const meetingCompanySelect = document.getElementById('meetingCompanySelect');
    const participantRows = Array.from(document.querySelectorAll('#participantList [data-company-id]'));
    const participantHint = document.getElementById('participantCompanyHint');
    const participantHintDefault = participantHint ? participantHint.textContent : '';

    function filterParticipants() {
        const companyId = meetingCompanySelect.value;

        participantRows.forEach(function (row) {
            if (row.dataset.self) return; // never auto-hide/uncheck the viewer's own row

            const matches = ! companyId || row.dataset.companyId === companyId;
            row.hidden = ! matches;
            if (! matches) {
                const box = row.querySelector('input[type="checkbox"]');
                if (box) box.checked = false;
            }
        });

        if (participantHint) {
            participantHint.textContent = companyId
                ? 'Showing people from the selected company only. ' + participantHintDefault
                : participantHintDefault;
        }
    }

    if (meetingCompanySelect && participantRows.length) {
        meetingCompanySelect.addEventListener('change', filterParticipants);
        filterParticipants();
    }
});
</script>
<?= $this->endSection() ?>
