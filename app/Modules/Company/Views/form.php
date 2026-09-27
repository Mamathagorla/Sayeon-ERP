<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form action="<?= $company ? site_url('companies/' . $company['id']) : site_url('companies') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company Name</label>
                    <input type="text" name="name" class="form-control" value="<?= esc($company['name'] ?? old('name')) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="active" <?= (($company['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($company['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" class="form-control" value="<?= esc($company['country'] ?? old('country')) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Owner</label>
                    <select name="owner_id" class="form-select">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($owners as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= (($company['owner_id'] ?? null) == $o['id']) ? 'selected' : '' ?>><?= esc($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Incorporation Date</label>
                    <input type="date" name="incorporation_date" class="form-control" value="<?= esc($company['incorporation_date'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">CIN</label>
                    <input type="text" name="cin" class="form-control" value="<?= esc($company['cin'] ?? '') ?>" maxlength="21" style="text-transform:uppercase" pattern="[LUlu][0-9]{5}[A-Za-z]{2}[0-9]{4}[A-Za-z]{3}[0-9]{6}" title="Enter a valid 21-character CIN, e.g. U74999MH2019PTC321001">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">GST</label>
                    <input type="text" name="gst" class="form-control" value="<?= esc($company['gst'] ?? '') ?>" maxlength="15" style="text-transform:uppercase" pattern="[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}[1-9A-Za-z]{1}Z[0-9A-Za-z]{1}" title="Enter a valid 15-character GSTIN, e.g. 27ABCDE1234F1Z5">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">PAN</label>
                    <input type="text" name="pan" class="form-control" value="<?= esc($company['pan'] ?? '') ?>" maxlength="10" style="text-transform:uppercase" pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}" title="Enter a valid 10-character PAN, e.g. ABCDE1234F">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Registered Address</label>
                    <textarea name="registered_address" class="form-control" rows="3"><?= esc($company['registered_address'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $company ? 'Update Company' : 'Create Company' ?></button>
            <a href="<?= site_url('companies') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
