<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="alert alert-info">
    <i class="fas fa-flask me-1"></i> Dev-only tool — instantly switches your session to any active user without re-entering a password. Never available outside the development environment.
</div>

<div class="card">
    <div class="card-header"><strong>Switch Profile</strong></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr class="<?= (int) $u['id'] === (int) session('userId') ? 'table-active' : '' ?>">
                        <td><?= esc($u['name']) ?></td>
                        <td><?= esc($u['email']) ?></td>
                        <td><span class="badge bg-secondary"><?= esc($u['role_name']) ?></span></td>
                        <td class="text-end">
                            <?php if ((int) $u['id'] === (int) session('userId')): ?>
                                <span class="text-muted small">Current session</span>
                            <?php else: ?>
                                <form action="<?= site_url('auth/switch-profile/' . $u['id']) ?>" method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Switch to this user</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
