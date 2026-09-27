<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="sy-card col-lg-7" style="height:auto">
    <div class="sy-card-body">
        <form action="<?= $phase ? site_url('hr/offboarding/phases/' . $phase['id']) : site_url('hr/offboarding/phases') ?>" method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Phase Name</label>
                <input type="text" name="name" class="form-control" value="<?= esc(old('name', $phase['name'] ?? '')) ?>" required maxlength="100">
            </div>
            <div class="mb-3">
                <label class="form-label">Description <span class="text-muted small">(optional)</span></label>
                <textarea name="description" class="form-control" rows="3" maxlength="500"><?= esc(old('description', $phase['description'] ?? '')) ?></textarea>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Order</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= esc(old('sort_order', $nextOrder)) ?>" min="1" required>
                    <div class="form-text">Where this phase sits among the others — 1 is first. Other phases shift automatically.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label d-block">Status</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="isActive" <?= old('is_active', $phase['is_active'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                    <div class="form-text">Inactive phases are hidden from the checklist display; any existing tasks under them are kept and shown under "Other".</div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary"><?= $phase ? 'Save Changes' : 'Add Phase' ?></button>
                <a href="<?= site_url('hr/offboarding/phases') ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
