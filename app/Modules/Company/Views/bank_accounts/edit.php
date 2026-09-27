<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('companies/' . $companyId) ?>" class="btn btn-light btn-sm">Back to company</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card" style="max-width: 720px;">
    <div class="card-body">
        <form action="<?= site_url('companies/' . $companyId . '/bank-accounts/' . $account['id']) ?>" method="post" enctype="multipart/form-data" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-md-6"><label class="form-label small">Bank name</label><input type="text" name="bank_name" class="form-control form-control-sm" value="<?= esc($account['bank_name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label small">Branch</label><input type="text" name="branch" class="form-control form-control-sm" value="<?= esc($account['branch'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label small">Account holder</label><input type="text" name="account_holder" class="form-control form-control-sm" value="<?= esc($account['account_holder']) ?>" required></div>
            <div class="col-md-6">
                <label class="form-label small">Account number</label>
                <input type="text" name="account_number" class="form-control form-control-sm" placeholder="•••• <?= esc($account['account_number_last4']) ?> (leave blank to keep)" maxlength="34" inputmode="numeric" pattern="\d+" title="Digits only">
            </div>
            <div class="col-md-6"><label class="form-label small">IFSC</label><input type="text" name="ifsc" class="form-control form-control-sm" value="<?= esc($account['ifsc'] ?? '') ?>" maxlength="11" style="text-transform:uppercase" pattern="[A-Za-z]{4}0[A-Za-z0-9]{6}" title="Enter a valid 11-character IFSC code, e.g. HDFC0001234"></div>
            <div class="col-md-6">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <?php foreach (['active', 'inactive', 'closed'] as $s): ?>
                        <option value="<?= $s ?>" <?= $account['status'] === $s ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12"><label class="form-label small">Authorized signatories</label><input type="text" name="authorized_signatories" class="form-control form-control-sm" value="<?= esc($account['authorized_signatories'] ?? '') ?>"></div>
            <div class="col-12">
                <label class="form-label small">Document<?= $account['document_path'] ? ' (replace)' : '' ?></label>
                <input type="file" name="document" class="form-control form-control-sm">
                <?php if ($account['document_path']): ?>
                    <div class="form-text">Current: <a href="<?= site_url('files/download?path=' . urlencode($account['document_path'])) ?>"><?= esc($account['document_name']) ?></a></div>
                <?php endif; ?>
            </div>
            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                <a href="<?= site_url('companies/' . $companyId) ?>" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </form>
        <p class="text-muted small mt-2 mb-0">The account number is encrypted before it's stored — only the last 4 digits are ever displayed.</p>
    </div>
</div>
<?= $this->endSection() ?>
