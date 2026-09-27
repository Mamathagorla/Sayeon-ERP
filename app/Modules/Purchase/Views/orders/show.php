<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if ($canEdit && $order['approval_status'] === 'pending'): ?>
<a href="<?= site_url('purchase-orders/' . $order['id'] . '/edit') ?>" class="btn btn-light btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<?php endif; ?>
<a href="<?= site_url('purchase-orders') ?>" class="btn btn-light btn-sm">Back to list</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h5 class="mb-1"><?= esc($order['po_number']) ?></h5>
                <div class="text-muted small"><?= esc($order['vendor_name']) ?> &middot; <?= esc($order['company_name']) ?></div>
            </div>
            <span class="badge bg-<?= ['pending' => 'warning text-dark', 'approved' => 'success', 'rejected' => 'danger'][$order['approval_status']] ?> fs-6"><?= esc(ucfirst($order['approval_status'])) ?></span>
        </div>

        <hr>

        <div class="row small">
            <div class="col-md-3 mb-2"><span class="text-muted">Requestor</span><br><?= esc($order['requester_name'] ?? '—') ?></div>
            <div class="col-md-3 mb-2"><span class="text-muted">Order Date</span><br><?= esc(date('d/m/Y', strtotime($order['order_date']))) ?></div>
            <div class="col-md-3 mb-2"><span class="text-muted">Expected</span><br><?= $order['expected_date'] ? esc(date('d/m/Y', strtotime($order['expected_date']))) : '—' ?></div>
            <div class="col-md-3 mb-2"><span class="text-muted">Items</span><br><?= esc($order['items_count']) ?></div>
            <div class="col-md-3 mb-2"><span class="text-muted">Total Amount</span><br><?= number_format((float) $order['amount'], 2) ?></div>
            <?php if ($order['approval_status'] !== 'pending'): ?>
            <div class="col-md-3 mb-2"><span class="text-muted">Decided By</span><br><?= esc($order['approver_name'] ?? '—') ?></div>
            <div class="col-md-3 mb-2"><span class="text-muted">Decided On</span><br><?= $order['approved_at'] ? esc(date('d/m/Y, g:i A', strtotime($order['approved_at']))) : '—' ?></div>
            <?php endif; ?>
            <?php if ($order['notes']): ?>
            <div class="col-12 mb-2"><span class="text-muted">Notes</span><br><?= nl2br(esc($order['notes'])) ?></div>
            <?php endif; ?>
        </div>

        <hr>
        <div class="text-muted text-uppercase small fw-bold mb-2">Items</div>
        <?php if (empty($items)): ?>
            <p class="text-muted small mb-0">No line items recorded for this order.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Line Total</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= esc($item['item_name']) ?></td>
                    <td class="text-end"><?= esc((int) $item['quantity']) ?></td>
                    <td class="text-end"><?= number_format((float) $item['unit_price'], 2) ?></td>
                    <td class="text-end fw-semibold"><?= number_format((float) $item['line_total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end fw-semibold">Total</td>
                    <td class="text-end fw-semibold"><?= number_format((float) $order['amount'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
        </div>
        <?php endif; ?>

        <?php if ($order['approval_status'] === 'pending' && $canApprove): ?>
        <hr>
        <div class="d-flex gap-2">
            <form action="<?= site_url('purchase-orders/' . $order['id'] . '/approve') ?>" method="post" onsubmit="return confirm('Approve this purchase order?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Approve</button>
            </form>
            <form action="<?= site_url('purchase-orders/' . $order['id'] . '/reject') ?>" method="post" onsubmit="return confirm('Reject this purchase order?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-xmark me-1"></i>Reject</button>
            </form>
        </div>
        <?php elseif ($order['approval_status'] === 'pending' && ! $canApprove): ?>
        <hr>
        <p class="text-muted small mb-0"><i class="fas fa-hourglass-half me-1"></i>Awaiting approval<?= can('purchase_order.approve') ? ' — you requested this order, so you can\'t approve/reject it yourself.' : '.' ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if ($order['approval_status'] === 'approved'): ?>
<div class="card">
    <div class="card-header"><strong>Fulfillment &amp; Payment</strong></div>
    <div class="card-body">
        <div class="row small mb-3">
            <div class="col-md-6"><span class="text-muted">Fulfillment Status</span><br>
                <span class="badge bg-<?= ['ordered' => 'secondary', 'in_transit' => 'info text-dark', 'received' => 'success'][$order['fulfillment_status'] ?? 'ordered'] ?>"><?= esc(ucwords(str_replace('_', ' ', $order['fulfillment_status'] ?? 'ordered'))) ?></span>
            </div>
            <div class="col-md-6"><span class="text-muted">Payment Status</span><br>
                <span class="badge bg-<?= ['pending' => 'warning text-dark', 'partial' => 'info text-dark', 'paid' => 'success'][$order['payment_status']] ?>"><?= esc(ucfirst($order['payment_status'])) ?></span>
            </div>
        </div>
        <?php if ($canEdit): ?>
        <form action="<?= site_url('purchase-orders/' . $order['id'] . '/status') ?>" method="post" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label small mb-1">Fulfillment Status</label>
                <select name="fulfillment_status" class="form-select form-select-sm">
                    <?php foreach ($fulfillmentStatuses as $s): ?>
                        <option value="<?= $s ?>" <?= ($order['fulfillment_status'] ?? 'ordered') === $s ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Payment Status</label>
                <select name="payment_status" class="form-select form-select-sm">
                    <?php foreach ($paymentStatuses as $s): ?>
                        <option value="<?= $s ?>" <?= $order['payment_status'] === $s ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-sm btn-primary">Update</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>
