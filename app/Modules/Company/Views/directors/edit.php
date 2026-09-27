<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('companies/' . $companyId) ?>" class="btn btn-light btn-sm">Back to company</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card" style="max-width: 720px;">
    <div class="card-body">
        <form action="<?= site_url('companies/' . $companyId . '/directors/' . $director['id']) ?>" method="post" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-md-6"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" value="<?= esc($director['name']) ?>" pattern="[A-Za-z\s.'\-]+" title="Letters, spaces, apostrophes, hyphens and periods only" required></div>
            <div class="col-md-6"><label class="form-label small">DIN</label><input type="text" name="din" class="form-control form-control-sm" value="<?= esc($director['din'] ?? '') ?>" maxlength="8" inputmode="numeric" pattern="\d{8}" title="Enter exactly 8 digits" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 8)"></div>
            <div class="col-md-6"><label class="form-label small">Designation</label><input type="text" name="designation" class="form-control form-control-sm" value="<?= esc($director['designation'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label small">Email</label><input type="email" name="email" class="form-control form-control-sm" value="<?= esc($director['email'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label small">Phone</label><input type="text" name="phone" class="form-control form-control-sm" value="<?= esc($director['phone'] ?? '') ?>" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" title="Enter exactly 10 digits" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)"></div>
            <div class="col-md-3"><label class="form-label small">Appointed</label><input type="date" name="appointed_date" class="form-control form-control-sm" value="<?= esc($director['appointed_date'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label small">Resigned</label><input type="date" name="resigned_date" class="form-control form-control-sm" value="<?= esc($director['resigned_date'] ?? '') ?>"></div>
            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                <a href="<?= site_url('companies/' . $companyId) ?>" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
