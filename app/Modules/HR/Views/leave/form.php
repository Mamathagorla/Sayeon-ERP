<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <form action="<?= site_url('hr/leave') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Leave Type</label>
                        <select name="leave_type_id" class="form-select" required>
                            <option value="">Select type</option>
                            <?php foreach ($leaveTypes as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= esc($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                    <a href="<?= site_url('hr/leave') ?>" class="btn btn-light">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Your Leave Balance</h6>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Type</th><th>Quota</th><th>Used</th><th>Remaining</th></tr></thead>
                    <tbody>
                    <?php foreach ($balances as $b): ?>
                        <tr>
                            <td><?= esc($b['name']) ?></td>
                            <td><?= $b['quota'] ?></td>
                            <td><?= $b['used'] ?></td>
                            <td class="fw-semibold"><?= $b['remaining'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
