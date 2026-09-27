<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <table class="table table-striped">
            <thead><tr><th>Role</th><th>Description</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($roles as $r): ?>
                <tr>
                    <td><?= esc($r['name']) ?></td>
                    <td class="text-muted small"><?= esc($r['description']) ?></td>
                    <td class="text-end">
                        <?php if ($r['slug'] !== 'super_admin'): ?>
                            <a href="<?= site_url('auth/roles/' . $r['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit Permissions</a>
                        <?php else: ?>
                            <span class="badge bg-dark">Full access — not editable</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
