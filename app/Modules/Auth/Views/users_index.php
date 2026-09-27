<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (in_array('user.create', session('permissions') ?? [], true) || session('roleSlug') === 'super_admin'): ?>
<a href="<?= site_url('auth/users/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add User</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <table class="table table-striped table-hover" id="usersTable">
            <thead>
                <tr>
                    <th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= esc($u['name']) ?></td>
                    <td><?= esc($u['email']) ?></td>
                    <td><span class="badge bg-info"><?= esc($u['role_name']) ?></span></td>
                    <td><span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>"><?= esc($u['status']) ?></span></td>
                    <td><?= $u['last_login_at'] ? esc(date('d/m/Y, g:i A', strtotime($u['last_login_at']))) : '—' ?></td>
                    <td class="text-end">
                        <a href="<?= site_url('auth/users/' . $u['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                        <form action="<?= site_url('auth/users/' . $u['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Deactivate this user?');">
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

<?= $this->section('scripts') ?>
<script>$(function () { $('#usersTable').DataTable({ order: [[0, 'asc']] }); });</script>
<?= $this->endSection() ?>
