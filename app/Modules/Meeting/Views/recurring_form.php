<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form data-erp-sections data-erp-first-section="Meeting Details" action="<?= $template ? site_url('recurring-meetings/' . $template['id']) : site_url('recurring-meetings') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <?php if ($template): ?>
                        <input type="text" class="form-control" value="<?= esc($template['company_name'] ?? '') ?>" disabled>
                        <div class="form-text">Company can't change after creation.</div>
                    <?php else: ?>
                        <select name="company_id" class="form-select" required>
                            <option value="">Select company</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (old('company_id') == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Meeting Title</label>
                    <input type="text" name="title" class="form-control" value="<?= esc(old('title', $template['title'] ?? '')) ?>" required placeholder="e.g. Weekly Standup">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Location <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="location" class="form-control" value="<?= esc(old('location', $template['location'] ?? '')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Meeting Link <span class="text-muted small">(optional)</span></label>
                    <input type="url" name="meeting_link" class="form-control" placeholder="https://…" value="<?= esc(old('meeting_link', $template['meeting_link'] ?? '')) ?>">
                </div>
                <div class="col-12 erp-section-marker" data-section-title="Schedule"></div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Start Time <span class="text-muted small">(optional)</span></label>
                    <input type="time" name="start_time" class="form-control" value="<?= esc(old('start_time', $template['start_time'] ?? '')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">End Time <span class="text-muted small">(optional)</span></label>
                    <input type="time" name="end_time" class="form-control" value="<?= esc(old('end_time', $template['end_time'] ?? '')) ?>">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="ends_next_day" value="1" id="endsNextDay" <?= (old('ends_next_day', $template['ends_next_day'] ?? 0) == 1) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="endsNextDay">Ends the next day <span class="text-muted">(overnight — e.g. 11:30 PM to 12:30 AM)</span></label>
                    </div>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Agenda <span class="text-muted small">(optional)</span></label>
                    <textarea name="agenda" class="form-control" rows="2"><?= esc(old('agenda', $template['agenda'] ?? '')) ?></textarea>
                </div>
                <div class="col-12 erp-section-marker" data-section-title="Participants"></div>
                <div class="col-12 mb-3">
                    <label class="form-label">Standing Attendees <span class="text-muted small">(every generated meeting gets this same list)</span></label>
                    <div class="border rounded p-2" style="max-height:180px;overflow-y:auto">
                        <?php foreach ($users as $u): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="participant_user_ids[]" value="<?= $u['id'] ?>" id="pmUser<?= $u['id'] ?>" <?= in_array((int) $u['id'], $selectedParticipantIds, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="pmUser<?= $u['id'] ?>"><?= esc($u['name']) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($template): ?>
                <div class="col-12 mb-3">
                    <div class="alert alert-light border small mb-0">
                        <strong>Schedule</strong> (fixed after creation) —
                        <?= esc(ucfirst($template['frequency'])) ?>, starting <?= esc(erp_date($template['start_date'])) ?>,
                        <?= $template['end_type'] === 'occurrences'
                            ? 'for ' . (int) $template['occurrences_total'] . ' occurrences'
                            : 'until ' . esc(erp_date($template['end_date'])) ?>.
                        <?= (int) $template['occurrences_generated'] ?> generated so far.
                    </div>
                </div>
                <?php else: ?>
                <div class="col-12 erp-section-marker" data-section-title="Recurrence"></div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Frequency</label>
                    <select name="frequency" class="form-select" required>
                        <?php foreach ($frequencies as $f): ?>
                            <option value="<?= $f ?>" <?= (old('frequency', 'weekly') === $f) ? 'selected' : '' ?>><?= esc(ucfirst($f)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= esc(old('start_date')) ?>" required>
                    <div class="form-text">The first meeting is created on this date.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label d-block">Ends</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="end_type" id="endTypeOccurrences" value="occurrences" <?= old('end_type', 'occurrences') === 'occurrences' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary btn-sm" for="endTypeOccurrences">Occurrences</label>
                        <input type="radio" class="btn-check" name="end_type" id="endTypeDate" value="end_date" <?= old('end_type') === 'end_date' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary btn-sm" for="endTypeDate">End Date</label>
                    </div>
                </div>

                <div class="col-md-6 mb-3" id="occurrencesField">
                    <label class="form-label">Number of Occurrences</label>
                    <input type="number" min="1" max="365" name="occurrences_total" id="occurrencesInput" class="form-control" value="<?= esc(old('occurrences_total', 4)) ?>">
                    <div class="btn-group btn-group-sm mt-2" role="group">
                        <?php foreach ([4, 8, 12, 26] as $quick): ?>
                        <button type="button" class="btn btn-outline-secondary quick-occurrences" data-value="<?= $quick ?>"><?= $quick ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-6 mb-3 d-none" id="endDateField">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= esc(old('end_date')) ?>">
                </div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary"><?= $template ? 'Save Changes' : 'Create Recurring Meeting' ?></button>
            <a href="<?= site_url('recurring-meetings') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?php if (! $template): ?>
<?= $this->section('scripts') ?>
<script>
(function () {
    var occField = document.getElementById('occurrencesField');
    var dateField = document.getElementById('endDateField');
    var occRadio = document.getElementById('endTypeOccurrences');
    var dateRadio = document.getElementById('endTypeDate');
    var occInput = document.getElementById('occurrencesInput');

    function sync() {
        var byDate = dateRadio.checked;
        occField.classList.toggle('d-none', byDate);
        dateField.classList.toggle('d-none', ! byDate);
    }
    occRadio.addEventListener('change', sync);
    dateRadio.addEventListener('change', sync);
    sync();

    document.querySelectorAll('.quick-occurrences').forEach(function (btn) {
        btn.addEventListener('click', function () {
            occInput.value = this.dataset.value;
        });
    });
})();
</script>
<?= $this->endSection() ?>
<?php endif; ?>
