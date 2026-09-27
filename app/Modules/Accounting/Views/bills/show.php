<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('accounting/bills/' . $bill['id'] . '/print') ?>" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i>Print</a>
<a href="<?= site_url('accounting/bills/' . $bill['id'] . '/pdf') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-pdf me-1"></i>Download PDF</a>
<?php if (can('bill.edit')): ?>
<a href="<?= site_url('accounting/bills/' . $bill['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<?php endif; ?>
<?php if (can('bill.delete')): ?>
<form action="<?= site_url('accounting/bills/' . $bill['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Delete this bill?');">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Delete</button>
</form>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    .bill-meta-grid { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #edf1f4; border-radius: 10px; overflow: hidden; }
    .bill-meta-col { border-right: 1px solid #edf1f4; }
    .bill-meta-col:last-child { border-right: none; }
    .bill-meta-row { display: flex; justify-content: space-between; gap: 12px; padding: 11px 16px; border-bottom: 1px solid #f0f3f6; font-size: .86rem; }
    .bill-meta-row:last-child { border-bottom: none; }
    .bill-meta-row .k { font-weight: 600; color: #10243f; }
    .bill-meta-row .v { color: #10243f; text-align: right; }
    .bill-meta-row .v.muted { color: #8592a3; }
    .bill-meta-row.balance .v { font-weight: 700; color: #b91c1c; }

    .bill-doc-table { width: 100%; border-collapse: collapse; font-size: .86rem; }
    .bill-doc-table th { background: #f6f8fa; text-align: left; padding: 9px 12px; border: 1px solid #edf1f4; font-size: .72rem; text-transform: uppercase; color: #8592a3; font-weight: 700; }
    .bill-doc-table td { padding: 9px 12px; border: 1px solid #edf1f4; vertical-align: middle; }

    .bill-totals { width: 100%; max-width: 360px; margin-left: auto; border: 1px solid #edf1f4; border-radius: 10px; overflow: hidden; }
    .bill-totals .bill-meta-row.grand { background: #f6f8fa; font-weight: 700; }
</style>

<?php $showPaymentPanel = can('bill.edit') && $balance > 0; ?>
<div class="row g-3">
    <div class="<?= $showPaymentPanel ? 'col-lg-7' : 'col-12' ?>">
        <div class="card mb-3">
            <div class="card-header">
                <strong class="fs-5"><?= esc($bill['bill_number']) ?></strong>
                <span class="badge bg-<?= ['unpaid' => 'secondary', 'partially_paid' => 'warning', 'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'dark'][$bill['status']] ?>"><?= esc(ucwords(str_replace('_', ' ', $bill['status']))) ?></span>
            </div>
            <div class="card-body">
                <div class="bill-meta-grid">
                    <div class="bill-meta-col">
                        <div class="bill-meta-row"><span class="k">Company</span><span class="v"><?= esc($bill['company_name']) ?></span></div>
                        <?php if (! empty($company['gst'])): ?>
                        <div class="bill-meta-row"><span class="k">Company GSTIN</span><span class="v"><?= esc($company['gst']) ?></span></div>
                        <?php endif; ?>
                        <div class="bill-meta-row"><span class="k">Bill Date</span><span class="v"><?= esc(date('d/m/Y', strtotime($bill['issue_date']))) ?></span></div>
                        <div class="bill-meta-row"><span class="k">Due Date</span><span class="v <?= $bill['due_date'] ? '' : 'muted' ?>"><?= $bill['due_date'] ? esc(date('d/m/Y', strtotime($bill['due_date']))) : '—' ?></span></div>
                        <div class="bill-meta-row"><span class="k">Notes</span><span class="v <?= $bill['notes'] ? '' : 'muted' ?>"><?= esc($bill['notes'] ?? '—') ?></span></div>
                    </div>
                    <div class="bill-meta-col">
                        <div class="bill-meta-row"><span class="k">Vendor</span><span class="v"><?= esc($bill['vendor_name']) ?></span></div>
                        <div class="bill-meta-row"><span class="k">Vendor GSTIN</span><span class="v <?= $bill['vendor_gstin'] ? '' : 'muted' ?>"><?= esc($bill['vendor_gstin'] ?? '—') ?></span></div>
                        <div class="bill-meta-row"><span class="k">Vendor Address</span><span class="v <?= $bill['vendor_address'] ? '' : 'muted' ?>"><?= $bill['vendor_address'] ? nl2br(esc($bill['vendor_address'])) : '—' ?></span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>Items</strong></div>
            <div class="card-body p-0">
                <table class="bill-doc-table mb-0">
                    <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="4" class="text-muted">No line items recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= esc($item['description']) ?></td>
                            <td class="text-end"><?= rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.') ?></td>
                            <td class="text-end"><?= number_format((float) $item['rate'], 2) ?></td>
                            <td class="text-end"><?= number_format((float) $item['amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="bill-totals">
                    <div class="bill-meta-row"><span class="k">Subtotal</span><span class="v"><?= number_format((float) $bill['subtotal'], 2) ?></span></div>
                    <?php if ((float) $bill['discount_amount'] > 0): ?>
                    <div class="bill-meta-row"><span class="k">Discount (<?= rtrim(rtrim(number_format((float) $bill['discount_percent'], 2), '0'), '.') ?>%)</span><span class="v">- <?= number_format((float) $bill['discount_amount'], 2) ?></span></div>
                    <?php endif; ?>
                    <?php if ((float) $bill['cgst_amount'] > 0): ?>
                    <div class="bill-meta-row"><span class="k">CGST</span><span class="v"><?= number_format((float) $bill['cgst_amount'], 2) ?></span></div>
                    <div class="bill-meta-row"><span class="k">SGST</span><span class="v"><?= number_format((float) $bill['sgst_amount'], 2) ?></span></div>
                    <?php endif; ?>
                    <?php if ((float) $bill['igst_amount'] > 0): ?>
                    <div class="bill-meta-row"><span class="k">IGST</span><span class="v"><?= number_format((float) $bill['igst_amount'], 2) ?></span></div>
                    <?php endif; ?>
                    <div class="bill-meta-row grand"><span class="k">Grand Total</span><span class="v"><?= number_format((float) $bill['amount'], 2) ?></span></div>
                    <div class="bill-meta-row"><span class="k">Paid</span><span class="v"><?= number_format($paid, 2) ?></span></div>
                    <div class="bill-meta-row balance"><span class="k">Balance Due</span><span class="v"><?= number_format($balance, 2) ?></span></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Payments Made</strong></div>
            <div class="card-body">
                <?php if (empty($payments)): ?>
                    <p class="text-muted small mb-0">No payments recorded yet.</p>
                <?php else: ?>
                <table class="bill-doc-table">
                    <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= esc(date('d/m/Y', strtotime($p['payment_date']))) ?></td>
                            <td><?= esc($p['method'] ?? '—') ?></td>
                            <td><?= esc($p['reference'] ?? '—') ?></td>
                            <td class="text-end"><?= number_format($p['amount'], 2) ?></td>
                            <td class="text-end">
                                <?php if (can('bill.edit')): ?>
                                <form action="<?= site_url('accounting/payments/' . $p['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Remove this payment?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($showPaymentPanel): ?>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><strong>Record Payment</strong></div>
            <div class="card-body">
                <form action="<?= site_url('accounting/bills/' . $bill['id'] . '/payments') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small">Amount</label>
                        <input type="number" step="0.01" min="0.01" max="<?= $balance ?>" name="amount" class="form-control form-control-sm" value="<?= $balance ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Method</label>
                        <input type="text" name="method" class="form-control form-control-sm" placeholder="Bank Transfer, Cheque, …">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Reference</label>
                        <input type="text" name="reference" class="form-control form-control-sm">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Record Payment</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
