<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-6">
        <div class="card card-primary card-outline">
            <div class="card-header"><h5 class="card-title mb-0">Profile</h5></div>
            <div class="card-body">
                <form action="<?= site_url('auth/profile') ?>" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-3 d-flex align-items-center gap-3">
                        <?php if (! empty($user['avatar_path'])): ?>
                            <img src="<?= base_url($user['avatar_path']) ?>" alt="" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">
                        <?php else: ?>
                            <span class="sy-avatar" style="width:64px;height:64px;font-size:1.4rem;"><?= esc(mb_strtoupper(mb_substr((string) $user['name'], 0, 1))) ?></span>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" name="avatar" class="form-control" accept="image/png,image/jpeg,image/webp">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="<?= esc($user['name']) ?>" pattern="[A-Za-z\s.'\-]+" title="Letters, spaces, apostrophes, hyphens and periods only" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= esc($user['email']) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= esc($user['phone']) ?>" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" title="Enter exactly 10 digits" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)">
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-secondary card-outline">
            <div class="card-header"><h5 class="card-title mb-0">Change Password</h5></div>
            <div class="card-body">
                <form action="<?= site_url('auth/change-password') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-secondary">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
