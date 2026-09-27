<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('expense.create')): ?>
<a href="<?= site_url('expenses/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Expense</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row row-cols-1 row-cols-md-3 g-3 mb-3">
    <div class="col">
        <div class="sy-stat-card">
            <div class="sy-stat-icon" style="background:#dcfce7;color:#16a34a"><i class="fas fa-receipt"></i></div>
            <div class="sy-stat-label">MONTHLY-EQUIVALENT TOTAL</div>
            <div class="sy-stat-value"><?= number_format($monthlyTotal, 2) ?></div>
            <div class="sy-stat-trend text-muted">Active subscriptions, normalized</div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card">
            <div class="sy-stat-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-list"></i></div>
            <div class="sy-stat-label">TOTAL RECORDS</div>
            <div class="sy-stat-value"><?= count($expenses) ?></div>
            <div class="sy-stat-trend text-muted">Matching current filters</div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card">
            <div class="sy-stat-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="sy-stat-label">OVERDUE RENEWALS</div>
            <div class="sy-stat-value"><?= count(array_filter($expenses, static fn (array $e) => $e['renewal_date'] && $e['renewal_date'] < date('Y-m-d') && $e['status'] === 'active')) ?></div>
            <div class="sy-stat-trend text-muted">Active &amp; past due</div>
        </div>
    </div>
</div>

<form method="get" class="sy-card mb-3 filter-form">
    <div class="sy-card-body py-2 d-flex gap-2 flex-wrap align-items-end">
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
        <a href="<?= site_url('expenses') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<div class="sy-card">
    <div class="sy-card-header"><strong>Expenses</strong> <span class="text-muted small"><?= count($expenses) ?> records</span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($expenses)): ?>
            <p class="text-muted mb-0 p-3">No expenses recorded yet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Expense ID</th>
                    <th>Expense Name</th>
                    <th>Company</th>
                    <th>Category</th>
                    <th class="text-end">Amount</th>
                    <th>Payment Method</th>
                    <th>Renewal Date</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($expenses as $e): ?>
                <?php $overdue = $e['renewal_date'] && $e['renewal_date'] < date('Y-m-d') && $e['status'] === 'active'; ?>
                <tr class="<?= $overdue ? 'table-danger' : '' ?>">
                    <td class="text-muted small">#EXP<?= str_pad((string) $e['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <div class="fw-semibold">
                            <?= esc($e['vendor']) ?>
                            <?php if (! empty($e['recurring_expense_id'])): ?>
                            <a href="<?= site_url('recurring-expenses/' . $e['recurring_expense_id']) ?>" class="badge bg-primary-subtle text-primary text-decoration-none ms-1" title="Auto-generated — occurrence #<?= (int) $e['occurrence_number'] ?>"><i class="fas fa-rotate me-1"></i>Recurring #<?= (int) $e['occurrence_number'] ?></a>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted small"><?= esc(ucfirst($e['billing_cycle'])) ?></div>
                    </td>
                    <td class="text-muted small"><?= esc($e['company_name']) ?></td>
                    <td class="text-muted small"><?= esc($e['category']) ?></td>
                    <td class="text-end fw-semibold"><?= number_format($e['amount'], 2) ?></td>
                    <td class="text-muted small"><?= esc($e['payment_method'] ?: '—') ?></td>
                    <td class="text-muted small"><?= $e['renewal_date'] ? esc(date('d/m/Y', strtotime($e['renewal_date']))) : '—' ?></td>
                    <td><span class="badge bg-<?= $e['status'] === 'active' ? 'success' : 'secondary' ?>"><?= esc(ucfirst($e['status'])) ?></span></td>
                    <td class="text-end">
                        <?php if (can('expense.edit')): ?>
                        <a href="<?= site_url('expenses/' . $e['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (can('expense.delete')): ?>
                        <form action="<?= site_url('expenses/' . $e['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Delete this expense?');">
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
