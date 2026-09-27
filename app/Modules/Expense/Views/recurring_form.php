<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form action="<?= $template ? site_url('recurring-expenses/' . $template['id']) : site_url('recurring-expenses') ?>" method="post">
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
                    <label class="form-label">Expense Title</label>
                    <input type="text" name="title" class="form-control" value="<?= esc(old('title', $template['title'] ?? '')) ?>" required placeholder="e.g. Office Rent">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= esc($cat['name']) ?>" <?= (old('category', $template['category'] ?? '') === $cat['name']) ? 'selected' : '' ?>><?= esc($cat['name']) ?><?= $cat['status'] === 'inactive' ? ' (inactive)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" min="0.01" max="9999999999.99" name="amount" class="form-control" value="<?= esc(old('amount', $template['amount'] ?? '')) ?>" required>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Description <span class="text-muted small">(optional)</span></label>
                    <textarea name="description" class="form-control" rows="2"><?= esc(old('description', $template['description'] ?? '')) ?></textarea>
                </div>

                <?php if ($template): ?>
                <div class="col-12 mb-3">
                    <div class="alert alert-light border small mb-0">
                        <strong>Schedule</strong> (fixed after creation) —
                        <?= esc(ucfirst($template['frequency'])) ?>, starting <?= esc(date('d/m/Y', strtotime($template['start_date']))) ?>,
                        <?= $template['end_type'] === 'occurrences'
                            ? 'for ' . (int) $template['occurrences_total'] . ' occurrences'
                            : 'until ' . esc(date('d/m/Y', strtotime($template['end_date']))) ?>.
                        <?= (int) $template['occurrences_generated'] ?> generated so far.
                    </div>
                </div>
                <?php else: ?>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Frequency</label>
                    <select name="frequency" class="form-select" required>
                        <?php foreach ($frequencies as $f): ?>
                            <option value="<?= $f ?>" <?= (old('frequency', 'monthly') === $f) ? 'selected' : '' ?>><?= esc(ucfirst($f)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= esc(old('start_date')) ?>" required>
                    <div class="form-text">The first expense generates on this date.</div>
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
                    <input type="number" min="1" max="240" name="occurrences_total" id="occurrencesInput" class="form-control" value="<?= esc(old('occurrences_total', 3)) ?>">
                    <div class="btn-group btn-group-sm mt-2" role="group">
                        <?php foreach ([2, 3, 6, 12] as $quick): ?>
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
            <button type="submit" class="btn btn-primary"><?= $template ? 'Save Changes' : 'Create Recurring Expense' ?></button>
            <a href="<?= site_url('recurring-expenses') ?>" class="btn btn-light">Cancel</a>
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
