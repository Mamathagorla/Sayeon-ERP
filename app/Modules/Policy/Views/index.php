<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('policy.create')): ?>
<a href="<?= site_url('policies/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Policy/Manual</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 filter-form">
            <div class="col-md-3">
                <select name="company_id" class="form-select form-select-sm">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (($filters['company_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $t ?>" <?= (($filters['type'] ?? '') === $t) ? 'selected' : '' ?>><?= esc(ucfirst($t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= (($filters['status'] ?? '') === $s) ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($policies)): ?>
            <p class="text-muted small mb-0">No policies or manuals recorded yet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Title</th><th>Type</th><th>Company</th><th>Owner</th><th>Version</th><th>Last Review</th><th>Retention</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($policies as $p): ?>
                <tr>
                    <td><a href="<?= site_url('policies/' . $p['id']) ?>" class="fw-semibold text-decoration-none"><?= esc($p['title']) ?></a></td>
                    <td><?= esc(ucfirst($p['type'])) ?></td>
                    <td class="text-muted small"><?= esc($p['company_name']) ?></td>
                    <td class="text-muted small"><?= esc($p['owner_name'] ?? '—') ?></td>
                    <td><?= esc($p['version'] ?: '—') ?></td>
                    <td class="text-muted small"><?= $p['last_review_date'] ? esc(date('d/m/Y', strtotime($p['last_review_date']))) : '—' ?></td>
                    <td class="text-muted small"><?= $p['retention_years'] !== null ? esc($p['retention_years']) . ' Year' . ($p['retention_years'] == 1 ? '' : 's') : '—' ?></td>
                    <td><span class="badge bg-<?= ['draft' => 'info', 'published' => 'success', 'archived' => 'secondary'][$p['status']] ?>"><?= esc(ucfirst($p['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
