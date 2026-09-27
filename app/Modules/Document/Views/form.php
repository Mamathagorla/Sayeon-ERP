<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-7">
    <div class="card-body">
        <form action="<?= site_url('documents') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <?php if (! empty($onboardingRecordId)): ?>
                <input type="hidden" name="onboarding_record_id" value="<?= (int) $onboardingRecordId ?>">
            <?php endif; ?>
            <?php if (! empty($policyId)): ?>
                <input type="hidden" name="policy_id" value="<?= (int) $policyId ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($defaultCompanyId == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= esc(ucfirst($cat)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. GST Registration Certificate">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Document Type <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="document_type" class="form-control" list="docTypes" placeholder="GST Certificate, PAN, Agreement, …">
                    <datalist id="docTypes">
                        <option value="GST Certificate"><option value="PAN"><option value="Incorporation Certificate">
                        <option value="Agreement"><option value="Contract"><option value="Property Document">
                        <option value="Bank Document"><option value="License"><option value="Employee Document">
                    </datalist>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Linked Employee <span class="text-muted small">(optional)</span></label>
                    <select name="employee_user_id" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($defaultEmployeeUserId ?? null) == $u['id'] ? 'selected' : '' ?>><?= esc($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Expiry Date <span class="text-muted small">(optional)</span></label>
                    <input type="date" name="expiry_date" class="form-control">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">File</label>
                    <input type="file" name="file" class="form-control" required>
                    <div class="form-text">Maximum 20MB.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Upload</button>
            <?php
                $cancelHref = ! empty($onboardingRecordId) ? site_url('hr/onboarding/' . $onboardingRecordId)
                    : (! empty($policyId) ? site_url('policies/' . $policyId) : site_url('documents'));
            ?>
            <a href="<?= $cancelHref ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
