<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form action="<?= $policy ? site_url('policies/' . $policy['id']) : site_url('policies') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" value="<?= esc($policy['title'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" required>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t ?>" <?= (($policy['type'] ?? 'policy') === $t) ? 'selected' : '' ?>><?= esc(ucfirst($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (! $policy): ?>
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
                    <input type="text" class="form-control" value="<?= esc($policy['company_name']) ?>" disabled>
                </div>
                <?php endif; ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Owner <span class="text-muted small">(optional)</span></label>
                    <select name="owner_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($owners as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= (($policy['owner_id'] ?? null) == $o['id']) ? 'selected' : '' ?>><?= esc($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Version <span class="text-muted small">(optional, e.g. "v1.0")</span></label>
                    <input type="text" name="version" class="form-control" value="<?= esc($policy['version'] ?? '') ?>" placeholder="v1.0">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Last Review Date <span class="text-muted small">(optional)</span></label>
                    <input type="date" name="last_review_date" class="form-control" value="<?= esc($policy['last_review_date'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Retention (years) <span class="text-muted small">(optional)</span></label>
                    <input type="number" name="retention_years" class="form-control" min="0" max="100" value="<?= esc($policy['retention_years'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= (($policy['status'] ?? 'draft') === $s) ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $policy ? 'Save Changes' : 'Add Policy/Manual' ?></button>
            <a href="<?= $policy ? site_url('policies/' . $policy['id']) : site_url('policies') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
