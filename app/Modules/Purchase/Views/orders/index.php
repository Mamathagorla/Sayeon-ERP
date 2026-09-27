<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('purchase_order.create')): ?>
<a href="<?= site_url('purchase-orders/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Purchase Order</a>
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
                <?php $selectedApprovalStatuses = (array) ($filters['approval_status'] ?? []); ?>
                <div class="dropdown sy-msel" data-placeholder="All Statuses">
                    <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span class="sy-msel-label">All Statuses</span>
                    </button>
                    <div class="dropdown-menu sy-msel-menu">
                        <?php foreach ($statuses as $s): ?>
                            <label class="sy-msel-item" data-label="<?= esc(ucfirst($s)) ?>">
                                <input type="checkbox" class="sy-msel-opt" name="approval_status[]" value="<?= $s ?>" <?= in_array($s, $selectedApprovalStatuses, true) ? 'checked' : '' ?>>
                                <?= esc(ucfirst($s)) ?>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($orders)): ?>
            <p class="text-muted small mb-0">No purchase orders yet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>PO ID</th><th>Supplier</th><th>Items</th><th>Requestor</th><th>Order Date</th><th>Expected</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><a href="<?= site_url('purchase-orders/' . $o['id']) ?>" class="fw-semibold text-decoration-none"><?= esc($o['po_number']) ?></a></td>
                    <td><?= esc($o['vendor_name']) ?></td>
                    <td class="text-muted small"><?= esc(mb_strimwidth((string) ($o['items_preview'] ?? ''), 0, 40, '…')) ?: '—' ?></td>
                    <td class="text-muted small"><?= esc($o['requester_name'] ?? '—') ?></td>
                    <td class="text-muted small"><?= esc(date('d/m/Y', strtotime($o['order_date']))) ?></td>
                    <td class="text-muted small"><?= $o['expected_date'] ? esc(date('d/m/Y', strtotime($o['expected_date']))) : '—' ?></td>
                    <td class="text-end"><?= number_format((float) $o['amount'], 2) ?></td>
                    <td><span class="badge bg-<?= ['pending' => 'warning text-dark', 'approved' => 'success', 'rejected' => 'danger'][$o['approval_status']] ?>"><?= esc(ucfirst($o['approval_status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
