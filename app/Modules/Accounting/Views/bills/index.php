<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('accounting/invoices') ?>" class="btn btn-outline-secondary btn-sm">Invoices</a>
<?php if (can('bill.create')): ?>
<a href="<?= site_url('accounting/bills/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Record Bill</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<form method="get" class="card mb-3 filter-form">
    <div class="card-body d-flex gap-2 flex-wrap align-items-end">
        <div style="min-width:200px">
            <label class="form-label small mb-1">Search</label>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search vendor or bill no…" value="<?= esc($filters['q'] ?? '') ?>">
        </div>
        <div>
            <label class="form-label small mb-1">Company</label>
            <select name="company_id" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (($filters['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width:160px">
            <label class="form-label small mb-1">Status</label>
            <?php $selectedBillStatuses = (array) ($filters['status'] ?? []); ?>
            <div class="dropdown sy-msel" data-placeholder="All">
                <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <span class="sy-msel-label">All</span>
                </button>
                <div class="dropdown-menu sy-msel-menu">
                    <?php foreach ($statuses as $s): ?>
                        <label class="sy-msel-item" data-label="<?= esc(ucwords(str_replace('_', ' ', $s))) ?>">
                            <input type="checkbox" class="sy-msel-opt" name="status[]" value="<?= $s ?>" <?= in_array($s, $selectedBillStatuses, true) ? 'checked' : '' ?>>
                            <?= esc(ucwords(str_replace('_', ' ', $s))) ?>
                        </label>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                </div>
            </div>
        </div>
        <div>
            <label class="form-label small mb-1">From</label>
            <input type="date" name="issue_from" class="form-control form-control-sm" value="<?= esc($filters['issue_from'] ?? '') ?>">
        </div>
        <div>
            <label class="form-label small mb-1">To</label>
            <input type="date" name="issue_to" class="form-control form-control-sm" value="<?= esc($filters['issue_to'] ?? '') ?>">
        </div>
        <a href="<?= site_url('accounting/bills') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <?php if (empty($bills)): ?>
            <p class="text-muted mb-0">No bills yet.</p>
        <?php else: ?>
        <table class="table table-sm table-striped">
            <thead><tr><th>Bill No.</th><th>Company</th><th>Vendor</th><th>Bill Date</th><th>Due Date</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($bills as $b): ?>
                <tr class="<?= $b['status'] === 'overdue' ? 'table-danger' : '' ?>">
                    <td><a href="<?= site_url('accounting/bills/' . $b['id']) ?>"><?= esc($b['bill_number']) ?></a></td>
                    <td><?= esc($b['company_name']) ?></td>
                    <td><?= esc($b['vendor_name']) ?></td>
                    <td><?= esc(date('d/m/Y', strtotime($b['issue_date']))) ?></td>
                    <td><?= $b['due_date'] ? esc(date('d/m/Y', strtotime($b['due_date']))) : '—' ?></td>
                    <td class="text-end"><?= number_format($b['amount'], 2) ?></td>
                    <td><span class="badge bg-<?= ['unpaid' => 'secondary', 'partially_paid' => 'warning', 'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'dark'][$b['status']] ?>"><?= esc(ucwords(str_replace('_', ' ', $b['status']))) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
