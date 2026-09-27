<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-md-6">
    <div class="card-body">
        <form action="<?= $department ? site_url('departments/' . $department['id']) : site_url('departments') ?>" method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="<?= esc($department['name'] ?? old('name')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= esc($department['description'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><?= $department ? 'Update' : 'Create' ?></button>
            <a href="<?= site_url('departments') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
