<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form action="<?= site_url('auth/roles/' . $role['id']) ?>" method="post">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-body">
            <?php foreach ($groupedPermissions as $module => $permissions): ?>
                <h6 class="text-uppercase text-muted mt-3"><?= esc($module) ?></h6>
                <div class="row">
                    <?php foreach ($permissions as $p): ?>
                        <div class="col-md-3 form-check ms-3">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= $p['id'] ?>"
                                id="perm<?= $p['id'] ?>" <?= in_array($p['id'], $assignedIds, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="perm<?= $p['id'] ?>"><?= esc($p['slug']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save Permissions</button>
            <a href="<?= site_url('auth/roles') ?>" class="btn btn-light">Cancel</a>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
