<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('expense_claim.create')): ?>
<a href="<?= site_url('expense-claims/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Claim</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$badge = ['submitted' => 'warning', 'approved' => 'info', 'rejected' => 'danger', 'reimbursed' => 'success', 'cancelled' => 'secondary'];
$me    = (int) session('userId');
?>
<form method="get" class="sy-card mb-3 filter-form">
    <div class="sy-card-body py-2 d-flex gap-2 flex-wrap align-items-end">
        <div>
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= (($filters['status'] ?? null) === $s) ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline-primary btn-sm">Filter</button>
        <a href="<?= site_url('expense-claims') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<div class="sy-card">
    <div class="sy-card-header"><strong>Expense Claims</strong> <span class="text-muted small"><?= count($claims) ?> records</span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($claims)): ?>
            <p class="text-muted mb-0 p-3">No expense claims yet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>Claim</th><th>Claimant</th><th>Category</th><th>Date</th><th class="text-end">Amount</th><th>Status</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($claims as $c): ?>
                <?php $own = (int) $c['user_id'] === $me; ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= esc($c['title']) ?></div>
                        <?php if ($c['decision_note']): ?><div class="text-muted small">Note: <?= esc($c['decision_note']) ?></div><?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= esc($c['claimant_name']) ?></td>
                    <td class="text-muted small"><?= esc($c['category']) ?></td>
                    <td class="text-muted small"><?= esc(erp_date($c['expense_date'])) ?></td>
                    <td class="text-end fw-semibold"><?= erp_number($c['amount'], 2) ?></td>
                    <td><span class="badge bg-<?= $badge[$c['status']] ?? 'secondary' ?>"><?= esc(ucfirst($c['status'])) ?></span></td>
                    <td class="text-end">
                        <?php if ($c['status'] === 'submitted' && $own): ?>
                        <form action="<?= site_url('expense-claims/' . $c['id'] . '/cancel') ?>" method="post" class="d-inline" onsubmit="return confirm('Cancel this claim?');">
                            <?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Cancel</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($c['status'] === 'submitted' && can('expense_claim.approve') && ! $own): ?>
                        <form action="<?= site_url('expense-claims/' . $c['id'] . '/approve') ?>" method="post" class="d-inline">
                            <?= csrf_field() ?><button class="btn btn-sm btn-outline-success">Approve</button>
                        </form>
                        <form action="<?= site_url('expense-claims/' . $c['id'] . '/reject') ?>" method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="text" name="note" class="form-control form-control-sm d-inline w-auto" placeholder="Reason (optional)" maxlength="255">
                            <button class="btn btn-sm btn-outline-danger">Reject</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($c['status'] === 'approved' && can('expense_claim.reimburse') && ! $own): ?>
                        <form action="<?= site_url('expense-claims/' . $c['id'] . '/reimburse') ?>" method="post" class="d-inline" onsubmit="return confirm('Record this reimbursement?');">
                            <?= csrf_field() ?>
                            <input type="text" name="payment_method" class="form-control form-control-sm d-inline w-auto" placeholder="Bank transfer, cash" maxlength="60">
                            <button class="btn btn-sm btn-primary">Reimburse</button>
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
