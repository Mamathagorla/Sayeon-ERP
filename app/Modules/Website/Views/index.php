<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('website.create')): ?>
<a href="<?= site_url('websites/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Website</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<form method="get" class="card mb-3 filter-form">
    <div class="card-body d-flex gap-2 flex-wrap align-items-end">
        <div>
            <label class="form-label small mb-1">Company</label>
            <select name="company_id" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (($filters['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= (($filters['status'] ?? null) === $s) ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <a href="<?= site_url('websites') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <?php if (empty($websites)): ?>
            <p class="text-muted mb-0">No websites on record yet.</p>
        <?php else: ?>
        <table class="table table-sm table-striped">
            <thead><tr><th>Domain</th><th>Company</th><th>Hosting Provider</th><th>SSL Expiry</th><th>Renewal Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php
                $soon = date('Y-m-d', strtotime('+30 days'));
            ?>
            <?php foreach ($websites as $w): ?>
                <?php $isSoon = ($w['ssl_expiry'] && $w['ssl_expiry'] <= $soon) || ($w['renewal_date'] && $w['renewal_date'] <= $soon); ?>
                <tr class="<?= $isSoon ? 'table-warning' : '' ?>">
                    <td><a href="<?= site_url('websites/' . $w['id']) ?>"><?= esc($w['domain']) ?></a></td>
                    <td><?= esc($w['company_name']) ?></td>
                    <td><?= esc($w['hosting_provider'] ?? '—') ?></td>
                    <td><?= esc($w['ssl_expiry'] ?? '—') ?></td>
                    <td><?= $w['renewal_date'] ? esc(date('d/m/Y', strtotime($w['renewal_date']))) : '—' ?></td>
                    <td><span class="badge bg-<?= $w['status'] === 'active' ? 'success' : ($w['status'] === 'expired' ? 'danger' : 'secondary') ?>"><?= esc(ucfirst($w['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-muted small mb-0">Rows highlighted in yellow have SSL or renewal due within 30 days.</p>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
