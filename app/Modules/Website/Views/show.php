<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('website.edit')): ?>
<a href="<?= site_url('websites/' . $website['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<?php endif; ?>
<?php if (can('website.delete')): ?>
<form action="<?= site_url('websites/' . $website['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Delete this website record?');">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Delete</button>
</form>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><?= esc($website['domain']) ?> <span class="badge bg-<?= $website['status'] === 'active' ? 'success' : ($website['status'] === 'expired' ? 'danger' : 'secondary') ?>"><?= esc(ucfirst($website['status'])) ?></span></h5>
                <p class="text-muted small"><?= esc($website['company_name']) ?></p>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Registrar</dt><dd class="col-sm-8"><?= esc($website['registrar'] ?? '—') ?></dd>
                    <dt class="col-sm-4">Hosting Provider</dt><dd class="col-sm-8"><?= esc($website['hosting_provider'] ?? '—') ?></dd>
                    <dt class="col-sm-4">Server IP</dt><dd class="col-sm-8"><?= esc($website['server_ip'] ?? '—') ?></dd>
                    <dt class="col-sm-4">DNS Details</dt><dd class="col-sm-8"><?= esc($website['dns_details'] ?? '—') ?></dd>
                    <dt class="col-sm-4">SSL Expiry</dt><dd class="col-sm-8"><?= $website['ssl_expiry'] ? esc(date('d/m/Y', strtotime($website['ssl_expiry']))) : '—' ?></dd>
                    <dt class="col-sm-4">Renewal Date</dt><dd class="col-sm-8"><?= $website['renewal_date'] ? esc(date('d/m/Y', strtotime($website['renewal_date']))) : '—' ?></dd>
                    <dt class="col-sm-4">Control Panel</dt><dd class="col-sm-8"><?= esc($website['control_panel'] ?? '—') ?> <?= $website['control_panel_url'] ? '(<a href="' . esc($website['control_panel_url']) . '" target="_blank" rel="noopener">open</a>)' : '' ?></dd>
                    <dt class="col-sm-4">Git Repository</dt><dd class="col-sm-8"><?= esc($website['git_repository'] ?? '—') ?></dd>
                    <dt class="col-sm-4">Notes</dt><dd class="col-sm-8"><?= esc($website['notes'] ?? '—') ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Credentials</h6>
                <dl class="row mb-2 small">
                    <dt class="col-sm-5">Admin Login</dt><dd class="col-sm-7"><?= esc($website['admin_login_username'] ?? '—') ?></dd>
                    <dt class="col-sm-5">FTP/SFTP Host</dt><dd class="col-sm-7"><?= esc($website['ftp_host'] ?? '—') ?></dd>
                    <dt class="col-sm-5">FTP/SFTP Username</dt><dd class="col-sm-7"><?= esc($website['ftp_username'] ?? '—') ?></dd>
                </dl>
                <?php if (can('website.edit')): ?>
                <button type="button" id="revealBtn" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye me-1"></i>Reveal Passwords</button>
                <div id="revealResult" class="mt-2 small mono d-none">
                    <div>Admin password: <strong id="revealAdmin"></strong></div>
                    <div>FTP password: <strong id="revealFtp"></strong></div>
                </div>
                <?php else: ?>
                <p class="text-muted small mb-0">Passwords are encrypted and not shown without edit permission.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?php if (can('website.edit')): ?>
<?= $this->section('scripts') ?>
<script>
document.getElementById('revealBtn').addEventListener('click', function () {
    fetch('<?= site_url('websites/' . $website['id'] . '/reveal') ?>', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '<?= csrf_hash() ?>',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: '<?= csrf_token() ?>=<?= csrf_hash() ?>',
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            document.getElementById('revealAdmin').textContent = data.admin_password || '(not set)';
            document.getElementById('revealFtp').textContent = data.ftp_password || '(not set)';
            document.getElementById('revealResult').classList.remove('d-none');
        });
});
</script>
<?= $this->endSection() ?>
<?php endif; ?>
