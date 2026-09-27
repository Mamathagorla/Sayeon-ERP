<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (in_array('compliance.create', session('permissions') ?? [], true) || session('roleSlug') === 'super_admin'): ?>
<a href="<?= site_url('compliance/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Compliance Item</a>
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
                <?php $selectedComplianceTypes = array_map('strval', (array) ($filters['compliance_type_id'] ?? [])); ?>
                <div class="dropdown sy-msel" data-placeholder="All Types">
                    <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span class="sy-msel-label">All Types</span>
                    </button>
                    <div class="dropdown-menu sy-msel-menu">
                        <?php foreach ($types as $t): ?>
                            <label class="sy-msel-item" data-label="<?= esc($t['name']) ?>">
                                <input type="checkbox" class="sy-msel-opt" name="compliance_type_id[]" value="<?= $t['id'] ?>" <?= in_array((string) $t['id'], $selectedComplianceTypes, true) ? 'checked' : '' ?>>
                                <?= esc($t['name']) ?>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php $selectedComplianceStatuses = (array) ($filters['status'] ?? []); ?>
                <div class="dropdown sy-msel" data-placeholder="All Statuses">
                    <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span class="sy-msel-label">All Statuses</span>
                    </button>
                    <div class="dropdown-menu sy-msel-menu">
                        <?php foreach ($statuses as $s): ?>
                            <label class="sy-msel-item" data-label="<?= esc(ucwords(str_replace('_', ' ', $s))) ?>">
                                <input type="checkbox" class="sy-msel-opt" name="status[]" value="<?= $s ?>" <?= in_array($s, $selectedComplianceStatuses, true) ? 'checked' : '' ?>>
                                <?= esc(ucwords(str_replace('_', ' ', $s))) ?>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-striped table-hover" id="complianceTable">
            <thead><tr><th>Type</th><th>Regulator</th><th>Company</th><th>Period</th><th>Due Date</th><th>Recurrence</th><th>Responsible</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i): ?>
                <tr class="<?= $i['status'] === 'overdue' ? 'table-danger' : '' ?>">
                    <td><a href="<?= site_url('compliance/' . $i['id']) ?>"><?= esc($i['title'] ?: $i['type_name']) ?></a></td>
                    <td><?= esc($i['regulator'] ?? '—') ?></td>
                    <td><?= esc($i['company_name']) ?></td>
                    <td><?= esc($i['period'] ?? '—') ?></td>
                    <td><?= esc(date('d/m/Y', strtotime($i['due_date']))) ?></td>
                    <td><?= esc(ucwords(str_replace('_', ' ', $i['recurrence']))) ?></td>
                    <td><?= esc($i['responsible_name'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= ['pending' => 'secondary', 'in_progress' => 'info', 'filed' => 'success', 'overdue' => 'danger'][$i['status']] ?>"><?= esc(ucwords(str_replace('_', ' ', $i['status']))) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>$(function () { $('#complianceTable').DataTable({ order: [[4, 'asc']] }); });</script>
<?= $this->endSection() ?>
