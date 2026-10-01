<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('customer.create')): ?>
<a href="<?= site_url('customers/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Customer</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 filter-form">
            <div class="col-md-3">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search name, email, GSTIN…" value="<?= esc($filters['q'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <select name="company_id" class="form-select form-select-sm">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (($filters['company_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
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
        <?php if (empty($customers)): ?>
            <p class="text-muted small mb-0">No customers recorded yet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>GSTIN</th><th>Location</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <?php $location = implode(', ', array_filter([$c['city'] ?? null, $c['state_name'] ?? null, $c['country_name'] ?? null])); ?>
                <tr>
                    <td class="fw-semibold"><a href="<?= site_url('customers/' . $c['id']) ?>"><?= esc($c['name']) ?></a></td>
                    <td class="text-muted small"><?= esc($c['company_name']) ?></td>
                    <td class="text-muted small"><?= esc($c['email'] ?? '—') ?></td>
                    <td class="text-muted small"><?= esc($c['phone'] ?? '—') ?></td>
                    <td class="text-muted small"><?= esc($c['gstin'] ?? '—') ?></td>
                    <td class="text-muted small"><?= esc($location ?: '—') ?></td>
                    <td><span class="badge bg-<?= $c['status'] === 'active' ? 'success' : 'secondary' ?>"><?= esc(ucfirst($c['status'])) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= site_url('customers/' . $c['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
                        <?php if (can('customer.edit')): ?>
                        <a href="<?= site_url('customers/' . $c['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (can('customer.delete')): ?>
                        <form action="<?= site_url('customers/' . $c['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Remove this customer?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
