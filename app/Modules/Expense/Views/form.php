<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-7">
    <div class="card-body">
        <form action="<?= $expense ? site_url('expenses/' . $expense['id']) : site_url('expenses') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($expense['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Vendor</label>
                    <input type="text" name="vendor" class="form-control" value="<?= esc($expense['vendor'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= esc($cat['name']) ?>" <?= (($expense['category'] ?? '') === $cat['name']) ? 'selected' : '' ?>><?= esc($cat['name']) ?><?= $cat['status'] === 'inactive' ? ' (inactive)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Billing Cycle</label>
                    <select name="billing_cycle" class="form-select" required>
                        <?php foreach ($billingCycles as $c): ?>
                            <option value="<?= $c ?>" <?= (($expense['billing_cycle'] ?? 'monthly') === $c) ? 'selected' : '' ?>><?= esc(ucfirst(str_replace('_', ' ', $c))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" min="0" max="9999999999.99" name="amount" class="form-control" value="<?= esc($expense['amount'] ?? '') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Renewal Date</label>
                    <input type="date" name="renewal_date" class="form-control" value="<?= esc($expense['renewal_date'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Payment Method</label>
                    <input type="text" name="payment_method" class="form-control" value="<?= esc($expense['payment_method'] ?? '') ?>" placeholder="Card, Bank Transfer, …">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= (($expense['status'] ?? 'active') === $s) ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc($expense['notes'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $expense ? 'Update Expense' : 'Add Expense' ?></button>
            <a href="<?= site_url('expenses') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
