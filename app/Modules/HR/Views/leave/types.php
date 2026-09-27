<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-md-7">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Leave Types</h6>
                <table class="table table-sm table-striped">
                    <thead><tr><th>Name</th><th>Annual Quota</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($leaveTypes as $t): ?>
                        <tr>
                            <td><?= esc($t['name']) ?></td>
                            <td><?= esc($t['annual_quota']) ?> days</td>
                            <td class="text-end">
                                <form action="<?= site_url('hr/leave/types/' . $t['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Delete this leave type?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Add Leave Type</h6>
                <form action="<?= site_url('hr/leave/types') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small">Name</label>
                        <input type="text" name="name" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Annual Quota (days)</label>
                        <input type="number" step="0.5" min="0" name="annual_quota" class="form-control form-control-sm" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Add</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
