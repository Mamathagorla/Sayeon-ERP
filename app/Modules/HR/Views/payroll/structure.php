<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-md-6">
    <div class="card-body">
        <form action="<?= site_url('hr/payroll/structure/' . $userId) ?>" method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Basic</label>
                <input type="number" step="0.01" min="0" max="9999999999.99" name="basic" class="form-control" value="<?= esc($structure['basic'] ?? '0') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">HRA</label>
                <input type="number" step="0.01" min="0" max="9999999999.99" name="hra" class="form-control" value="<?= esc($structure['hra'] ?? '0') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Other Allowances</label>
                <input type="number" step="0.01" min="0" max="9999999999.99" name="allowances" class="form-control" value="<?= esc($structure['allowances'] ?? '0') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Deductions</label>
                <input type="number" step="0.01" min="0" max="9999999999.99" name="deductions" class="form-control" value="<?= esc($structure['deductions'] ?? '0') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Effective From</label>
                <input type="date" name="effective_from" class="form-control" value="<?= esc($structure['effective_from'] ?? date('Y-m-d')) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Save Structure</button>
            <a href="<?= site_url('hr/employees') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
