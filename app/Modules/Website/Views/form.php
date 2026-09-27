<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-9">
    <div class="card-body">
        <form action="<?= $website ? site_url('websites/' . $website['id']) : site_url('websites') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Domain</label>
                    <input type="text" name="domain" class="form-control" value="<?= esc($website['domain'] ?? '') ?>" placeholder="example.com" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($website['company_id'] ?? $defaultCompanyId ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Registrar</label>
                    <input type="text" name="registrar" class="form-control" value="<?= esc($website['registrar'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Hosting Provider</label>
                    <input type="text" name="hosting_provider" class="form-control" value="<?= esc($website['hosting_provider'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Server IP</label>
                    <input type="text" name="server_ip" class="form-control" value="<?= esc($website['server_ip'] ?? '') ?>" pattern="^(\d{1,3}\.){3}\d{1,3}$" title="Enter a valid IPv4 address, e.g. 192.168.1.1">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">SSL Expiry</label>
                    <input type="date" name="ssl_expiry" class="form-control" value="<?= esc($website['ssl_expiry'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Renewal Date</label>
                    <input type="date" name="renewal_date" class="form-control" value="<?= esc($website['renewal_date'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= (($website['status'] ?? 'active') === $s) ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">DNS Details</label>
                    <input type="text" name="dns_details" class="form-control" value="<?= esc($website['dns_details'] ?? '') ?>" placeholder="Nameservers, key DNS records, …">
                </div>

                <div class="col-12"><hr><h6>Control Panel &amp; Repository</h6></div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Control Panel</label>
                    <input type="text" name="control_panel" class="form-control" value="<?= esc($website['control_panel'] ?? '') ?>" placeholder="cPanel, Plesk, …">
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">Control Panel URL</label>
                    <input type="url" name="control_panel_url" class="form-control" value="<?= esc($website['control_panel_url'] ?? '') ?>" placeholder="https://...">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Git Repository</label>
                    <input type="text" name="git_repository" class="form-control" value="<?= esc($website['git_repository'] ?? '') ?>">
                </div>

                <div class="col-12"><hr><h6>Credentials <span class="text-muted small fw-normal">(passwords are encrypted at rest)</span></h6></div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Admin Login Username</label>
                    <input type="text" name="admin_login_username" class="form-control" value="<?= esc($website['admin_login_username'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Admin Login Password <?= $website ? '<span class="text-muted small fw-normal">(leave blank to keep unchanged)</span>' : '' ?></label>
                    <input type="password" name="admin_login_password" class="form-control" autocomplete="new-password">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">FTP/SFTP Host</label>
                    <input type="text" name="ftp_host" class="form-control" value="<?= esc($website['ftp_host'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">FTP/SFTP Username</label>
                    <input type="text" name="ftp_username" class="form-control" value="<?= esc($website['ftp_username'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">FTP/SFTP Password <?= $website ? '<span class="text-muted small fw-normal">(leave blank to keep)</span>' : '' ?></label>
                    <input type="password" name="ftp_password" class="form-control" autocomplete="new-password">
                </div>

                <div class="col-12 mb-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc($website['notes'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $website ? 'Update Website' : 'Add Website' ?></button>
            <a href="<?= site_url('websites') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
