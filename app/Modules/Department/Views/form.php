<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-md-6">
    <div class="card-body">
        <form action="<?= $department ? site_url('departments/' . $department['id']) : site_url('departments') ?>" method="post">
            <?= csrf_field() ?>
            <?= $this->include('App\Modules\Department\_fields', ['department' => $department]) ?>
            <button type="submit" class="btn btn-primary"><?= $department ? 'Update' : 'Create' ?></button>
            <a href="<?= site_url('departments') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
