<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('purchase_order.edit')): ?>
<a href="<?= site_url('vendors/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Vendor</a>
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
        <?php if (empty($vendors)): ?>
            <p class="text-muted small mb-0">No vendors recorded yet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Name</th><th>Company</th><th>Contact</th><th>Email</th><th>Phone</th><th>Status</th><?php if (can('purchase_order.edit')): ?><th class="text-end">Actions</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($vendors as $v): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($v['name']) ?></td>
                    <td class="text-muted small"><?= esc($v['company_name']) ?></td>
                    <td class="text-muted small"><?= esc($v['contact_person'] ?? '—') ?></td>
                    <td class="text-muted small"><?= esc($v['email'] ?? '—') ?></td>
                    <td class="text-muted small"><?= esc($v['phone'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= $v['status'] === 'active' ? 'success' : 'secondary' ?>"><?= esc(ucfirst($v['status'])) ?></span></td>
                    <?php if (can('purchase_order.edit')): ?>
                    <td class="text-end">
                        <a href="<?= site_url('vendors/' . $v['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                        <form action="<?= site_url('vendors/' . $v['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Remove this vendor?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
