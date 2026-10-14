<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (in_array('company.create', session('permissions') ?? [], true) || session('roleSlug') === 'super_admin'): ?>
<a href="<?= site_url('companies/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Company</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <table class="table table-striped table-hover" id="companiesTable">
            <thead>
                <tr><th>Company ID</th><th>Company Code</th><th>Company Name</th><th>Country</th><th>State</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($companies as $c): ?>
                <tr>
                    <td><?= (int) $c['id'] ?></td>
                    <td><?php if (! empty($c['short_code'])): ?><span class="badge bg-light text-dark border"><?= esc($c['short_code']) ?></span><?php else: ?>—<?php endif; ?></td>
                    <td><a href="<?= site_url('companies/' . $c['id']) ?>"><?= esc($c['name']) ?></a></td>
                    <td><?= esc($c['country_name'] ?? $c['country'] ?: '—') ?></td>
                    <td><?= esc($c['state_name'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= $c['status'] === 'active' ? 'success' : 'secondary' ?>"><?= esc($c['status']) ?></span></td>
                    <td class="text-end">
                        <a href="<?= site_url('companies/' . $c['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                        <form action="<?= site_url('companies/' . $c['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Deactivate this company?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>$(function () { $('#companiesTable').DataTable({ order: [[0, 'asc']] }); });</script>
<?= $this->endSection() ?>
