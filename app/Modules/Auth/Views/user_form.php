<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-md-6">
    <div class="card-body">
        <form action="<?= $user ? site_url('auth/users/' . $user['id']) : site_url('auth/users') ?>" method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="<?= esc($user['name'] ?? old('name')) ?>" pattern="[A-Za-z\s.'\-]+" title="Letters, spaces, apostrophes, hyphens and periods only" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= esc($user['email'] ?? old('email')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= esc($user['phone'] ?? old('phone')) ?>" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" title="Enter exactly 10 digits" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)">
            </div>
            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role_id" class="form-select" required>
                    <option value="">Select role</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= (($user['role_id'] ?? null) == $r['id']) ? 'selected' : '' ?>><?= esc($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($user): ?>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label"><?= $user ? 'New Password (leave blank to keep current)' : 'Password' ?></label>
                <input type="password" name="password" class="form-control" minlength="8" <?= $user ? '' : 'required' ?>>
            </div>
            <button type="submit" class="btn btn-primary"><?= $user ? 'Update User' : 'Create User' ?></button>
            <a href="<?= site_url('auth/users') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
