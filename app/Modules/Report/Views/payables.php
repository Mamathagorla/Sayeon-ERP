<?= $this->extend('layouts/main') ?>

<?php $qs = http_build_query($filters); ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('reports/payables/export/pdf?' . $qs) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-pdf me-1"></i>PDF</a>
<a href="<?= site_url('reports/payables/export/csv?' . $qs) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-csv me-1"></i>CSV</a>
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
        <a href="<?= site_url('reports/payables') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold"><?= number_format($total, 2) ?></div><div class="text-muted small">Total Outstanding</div></div></div>
    </div>
    <div class="col-md-6">
        <div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold text-danger"><?= $overdue ?></div><div class="text-muted small">Overdue Bills</div></div></div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($bills)): ?>
            <p class="text-muted mb-0">Nothing outstanding — all bills are paid or cancelled.</p>
        <?php else: ?>
        <table class="table table-sm table-striped">
            <thead><tr><th>Bill</th><th>Company</th><th>Vendor</th><th class="text-end">Amount</th><th>Due Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($bills as $b): ?>
                <tr class="<?= $b['status'] === 'overdue' ? 'table-danger' : '' ?>">
                    <td><a href="<?= site_url('accounting/bills/' . $b['id']) ?>"><?= esc($b['bill_number']) ?></a></td>
                    <td><?= esc($b['company_name']) ?></td>
                    <td><?= esc($b['vendor_name']) ?></td>
                    <td class="text-end"><?= number_format($b['amount'], 2) ?></td>
                    <td><?= $b['due_date'] ? esc(date('d/m/Y', strtotime($b['due_date']))) : '—' ?></td>
                    <td><?= esc(ucwords(str_replace('_', ' ', $b['status']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
