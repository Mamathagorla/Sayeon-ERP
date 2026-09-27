<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-7">
    <div class="card-body">
        <form action="<?= $record ? site_url('hr/offboarding/' . $record['id']) : site_url('hr/offboarding') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <?php if ($isEmployee): ?>
                    <div class="col-12 mb-3">
                        <div class="alert alert-info small mb-0">Submitting this will notify your reporting manager and HR to begin the exit process.</div>
                    </div>
                <?php elseif (! $record): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Employee</label>
                        <select name="employee_profile_id" class="form-select" required>
                            <option value="">Select employee</option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= esc($e['user_name']) ?> (<?= esc($e['employee_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Employee</label>
                        <input type="text" class="form-control" value="<?= esc($record['employee_name']) ?>" disabled>
                    </div>
                <?php endif; ?>

                <?php if (! $isEmployee): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Exit Type</label>
                    <select name="exit_type" class="form-select" required>
                        <option value="resignation" <?= (($record['exit_type'] ?? '') === 'resignation') ? 'selected' : '' ?>>Resignation</option>
                        <option value="termination" <?= (($record['exit_type'] ?? '') === 'termination') ? 'selected' : '' ?>>Termination</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= $isEmployee ? 'Resignation Date' : 'Exit Date' ?></label>
                    <input type="date" name="exit_date" class="form-control" value="<?= esc($record['exit_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Last Working Day <span class="text-muted small">(optional)</span></label>
                    <input type="date" name="last_working_day" class="form-control" value="<?= esc($record['last_working_day'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Notice Period (days) <span class="text-muted small">(optional)</span></label>
                    <input type="number" name="notice_period_days" class="form-control" min="0" value="<?= esc($record['notice_period_days'] ?? '') ?>">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Reason <span class="text-muted small">(optional)</span></label>
                    <textarea name="reason" class="form-control" rows="3"><?= esc($record['reason'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $record ? 'Save Changes' : ($isEmployee ? 'Submit Resignation' : 'Initiate Exit') ?></button>
            <a href="<?= $record ? site_url('hr/offboarding/' . $record['id']) : site_url('hr/offboarding') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
