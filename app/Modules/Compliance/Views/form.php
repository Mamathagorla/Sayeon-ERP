<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form action="<?= $item ? site_url('compliance/' . $item['id']) : site_url('compliance') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($item['company_id'] ?? $defaultCompanyId ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Compliance Type</label>
                    <select name="compliance_type_id" class="form-select" required>
                        <option value="">Select type</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= (($item['compliance_type_id'] ?? null) == $t['id']) ? 'selected' : '' ?>><?= esc($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Title <span class="text-muted small">(optional label, e.g. "GST Filing - August 2026")</span></label>
                    <input type="text" name="title" class="form-control" value="<?= esc($item['title'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Regulator <span class="text-muted small">(optional, e.g. "GST Dept", "EU GDPR", "OSHA")</span></label>
                    <input type="text" name="regulator" class="form-control" value="<?= esc($item['regulator'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Period <span class="text-muted small">(optional, e.g. "2024-2025", "Q1 2026")</span></label>
                    <input type="text" name="period" class="form-control" value="<?= esc($item['period'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= esc($item['due_date'] ?? '') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Recurrence</label>
                    <select name="recurrence" class="form-select" required>
                        <?php foreach (['none', 'monthly', 'quarterly', 'half_yearly', 'annually'] as $r): ?>
                            <option value="<?= $r ?>" <?= (($item['recurrence'] ?? 'none') === $r) ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $r))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Reminder (days before due)</label>
                    <input type="number" name="reminder_days_before" class="form-control" min="0" value="<?= esc($item['reminder_days_before'] ?? 7) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Responsible Person</label>
                    <select name="responsible_user_id" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (($item['responsible_user_id'] ?? null) == $u['id']) ? 'selected' : '' ?>><?= esc($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"><?= esc($item['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $item ? 'Update' : 'Create' ?></button>
            <a href="<?= site_url('compliance') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
