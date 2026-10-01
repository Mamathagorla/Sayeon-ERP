<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/employees/' . $employee['id']) ?>" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Profile</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card col-lg-6">
    <div class="card-body">
        <h5 class="mb-3">Issue Increment Letter — <?= esc($employee['user_name']) ?></h5>

        <div class="mb-3">
            <div class="text-muted small">Current Annual CTC</div>
            <div class="fw-bold"><?= $currentCtc !== null ? '₹' . number_format($currentCtc, 2) : 'Not set up' ?></div>
        </div>

        <form action="<?= site_url('hr/employees/' . $employee['id'] . '/increment-letters') ?>" method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">New Annual CTC</label>
                <input type="number" step="0.01" min="0.01" name="new_ctc" class="form-control" value="<?= esc(old('new_ctc')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Effective Date</label>
                <input type="date" name="effective_date" class="form-control" value="<?= esc(old('effective_date') ?: date('Y-m-d')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Reason <span class="text-muted small">(optional)</span></label>
                <input type="text" name="reason" class="form-control" value="<?= esc(old('reason')) ?>" maxlength="255" placeholder="e.g. Annual performance increment">
            </div>
            <button type="submit" class="btn btn-primary">Issue Letter</button>
            <a href="<?= site_url('hr/employees/' . $employee['id']) ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
