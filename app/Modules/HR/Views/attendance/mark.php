<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/attendance') ?>" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Attendance</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card col-lg-6">
    <div class="card-body">
        <h5 class="mb-3">Mark Attendance</h5>
        <form action="<?= site_url('hr/attendance/mark') ?>" method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Employee</label>
                <select name="user_id" class="form-select" required>
                    <option value="">Select employee</option>
                    <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['user_id'] ?>"><?= esc($e['user_name']) ?> (<?= esc($e['employee_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= esc($today) ?>" max="<?= esc($today) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>"><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Check In <span class="text-muted small">(optional)</span></label>
                    <input type="time" name="check_in" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Check Out <span class="text-muted small">(optional)</span></label>
                    <input type="time" name="check_out" class="form-control">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Notes <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="notes" class="form-control" maxlength="255">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="<?= site_url('hr/attendance') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
