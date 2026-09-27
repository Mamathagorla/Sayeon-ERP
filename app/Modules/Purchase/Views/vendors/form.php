<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-7">
    <div class="card-body">
        <form action="<?= $vendor ? site_url('vendors/' . $vendor['id']) : site_url('vendors') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Vendor Name</label>
                    <input type="text" name="name" class="form-control" value="<?= esc($vendor['name'] ?? '') ?>" required>
                </div>
                <?php if (! $vendor): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <input type="text" class="form-control" value="<?= esc($vendor['company_name']) ?>" disabled>
                </div>
                <?php endif; ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Person <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="contact_person" class="form-control" value="<?= esc($vendor['contact_person'] ?? '') ?>" pattern="[\p{L}\s.'\-]+" title="Letters, spaces, apostrophes, hyphens and periods only" maxlength="150">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= (($vendor['status'] ?? 'active') === $s) ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email <span class="text-muted small">(optional)</span></label>
                    <input type="email" name="email" class="form-control" value="<?= esc($vendor['email'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="phone" class="form-control" value="<?= esc($vendor['phone'] ?? '') ?>" maxlength="20">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Address <span class="text-muted small">(optional)</span></label>
                    <textarea name="address" class="form-control" rows="2"><?= esc($vendor['address'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $vendor ? 'Save Changes' : 'Add Vendor' ?></button>
            <a href="<?= site_url('vendors') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
