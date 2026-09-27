<?= $this->extend('layouts/main') ?>

<?php $qs = http_build_query($filters); ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('reports/compliance/export/pdf?' . $qs) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-pdf me-1"></i>PDF</a>
<a href="<?= site_url('reports/compliance/export/csv?' . $qs) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-csv me-1"></i>CSV</a>
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
                    <option value="<?= $s ?>" <?= (($filters['status'] ?? null) === $s) ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <a href="<?= site_url('reports/compliance') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold"><?= $total ?></div><div class="text-muted small">Total Items</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold text-danger"><?= $byStatus['overdue'] ?></div><div class="text-muted small">Overdue</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold text-info"><?= $byStatus['pending'] + $byStatus['in_progress'] ?></div><div class="text-muted small">Pending / In Progress</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold text-success"><?= $byStatus['filed'] ?></div><div class="text-muted small">Filed</div></div></div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($items)): ?>
            <p class="text-muted mb-0">No compliance items match these filters.</p>
        <?php else: ?>
        <table class="table table-striped table-sm">
            <thead><tr><th>Title</th><th>Company</th><th>Type</th><th>Responsible</th><th>Status</th><th>Due Date</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i): ?>
                <tr class="<?= $i['status'] === 'overdue' ? 'table-danger' : '' ?>">
                    <td><a href="<?= site_url('compliance/' . $i['id']) ?>"><?= esc($i['title']) ?></a></td>
                    <td><?= esc($i['company_name']) ?></td>
                    <td><?= esc($i['type_name']) ?></td>
                    <td><?= esc($i['responsible_name'] ?? 'Unassigned') ?></td>
                    <td><?= esc(ucwords(str_replace('_', ' ', $i['status']))) ?></td>
                    <td><?= esc(date('d/m/Y', strtotime($i['due_date']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
