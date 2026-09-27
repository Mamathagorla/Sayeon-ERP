<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('departments/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Department</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <table class="table table-striped">
            <thead><tr><th>Name</th><th>Description</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($departments as $d): ?>
                <tr>
                    <td><?= esc($d['name']) ?></td>
                    <td class="text-muted small"><?= esc($d['description'] ?? '—') ?></td>
                    <td class="text-end">
                        <a href="<?= site_url('departments/' . $d['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                        <form action="<?= site_url('departments/' . $d['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Delete this department?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
