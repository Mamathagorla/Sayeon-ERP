<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-7">
    <div class="card-body">
        <p class="text-muted small">Choose which compliance types apply to this company. Leave everything unchecked to keep showing every type (the default, unrestricted behaviour).</p>

        <?php if (count($companies) > 1): ?>
        <form method="get" action="<?= site_url('compliance/types/settings') ?>" class="mb-3">
            <label class="form-label small">Company</label>
            <select name="company_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedCompanyId == $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>

        <?php if ($selectedCompanyId === 0): ?>
            <p class="text-muted small mb-0">No company available to configure.</p>
        <?php else: ?>
        <form method="post" action="<?= site_url('compliance/types/settings') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">

            <?php if (empty($types)): ?>
                <p class="text-muted small">No compliance types exist yet.</p>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($types as $t): ?>
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="compliance_type_ids[]" value="<?= $t['id'] ?>" id="type<?= $t['id'] ?>" <?= in_array((int) $t['id'], $applicableIds, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="type<?= $t['id'] ?>"><?= esc($t['name']) ?></label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary mt-2">Save</button>
            <a href="<?= site_url('compliance') ?>" class="btn btn-light mt-2">Cancel</a>
        </form>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
