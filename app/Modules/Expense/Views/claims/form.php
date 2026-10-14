<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-7">
    <div class="card-body">
        <form action="<?= site_url('expense-claims') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">What was it for?</label>
                    <input type="text" name="title" class="form-control" value="<?= esc(old('title')) ?>" maxlength="150" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Expense date</label>
                    <input type="date" name="expense_date" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= esc(old('expense_date', date('Y-m-d'))) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= esc($cat['name']) ?>" <?= old('category') === $cat['name'] ? 'selected' : '' ?>><?= esc($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" min="0.01" max="9999999999.99" name="amount" class="form-control" value="<?= esc(old('amount')) ?>" required>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Details</label>
                    <textarea name="description" class="form-control" rows="3" maxlength="2000"><?= esc(old('description')) ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Submit for approval</button>
            <a href="<?= site_url('expense-claims') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
