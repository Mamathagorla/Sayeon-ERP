<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<style>
    .bill-item-row td { vertical-align: middle; }
    .bill-item-row .form-control, .bill-item-row .form-control-sm { font-size: .85rem; }
    #billTotals .row-line { display: flex; justify-content: space-between; padding: 6px 0; font-size: .88rem; border-bottom: 1px solid #f0f3f6; }
    #billTotals .row-line:last-child { border-bottom: none; }
    #billTotals .row-line.grand { font-weight: 700; font-size: 1rem; }
</style>
<div class="card">
    <div class="card-body">
        <form action="<?= $bill ? site_url('accounting/bills/' . $bill['id']) : site_url('accounting/bills') ?>" method="post" id="billForm">
            <?= csrf_field() ?>

            <h6 class="text-muted text-uppercase small fw-bold mb-2">Bill Details</h6>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Bill Number</label>
                    <input type="text" name="bill_number" class="form-control" value="<?= esc($bill['bill_number'] ?? $nextNumber) ?>" maxlength="40" pattern="[A-Za-z0-9\-]*" title="Letters, numbers and hyphens only" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($bill['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= (($bill['status'] ?? 'unpaid') === $s) ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Bill Date</label>
                    <input type="date" name="issue_date" class="form-control" value="<?= esc($bill['issue_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= esc($bill['due_date'] ?? '') ?>">
                </div>
            </div>

            <h6 class="text-muted text-uppercase small fw-bold mb-2 mt-2">Vendor</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Vendor Name</label>
                    <input type="text" name="vendor_name" class="form-control" value="<?= esc($bill['vendor_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Vendor GSTIN <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="vendor_gstin" class="form-control" value="<?= esc($bill['vendor_gstin'] ?? '') ?>" maxlength="15" style="text-transform:uppercase" pattern="[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}[1-9A-Za-z]{1}Z[0-9A-Za-z]{1}" title="Enter a valid 15-character GSTIN, e.g. 27ABCDE1234F1Z5">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Vendor Address</label>
                    <textarea name="vendor_address" class="form-control" rows="1"><?= esc($bill['vendor_address'] ?? '') ?></textarea>
                </div>
            </div>

            <h6 class="text-muted text-uppercase small fw-bold mb-2 mt-2">Items</h6>
            <div class="table-responsive mb-2">
                <table class="table table-sm align-middle" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width:46%">Description</th>
                            <th style="width:14%">Quantity</th>
                            <th style="width:16%">Rate</th>
                            <th style="width:16%">Amount</th>
                            <th style="width:8%"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <?php $existingItems = ! empty($items) ? $items : [['description' => '', 'quantity' => 1, 'rate' => '']]; ?>
                        <?php foreach ($existingItems as $item): ?>
                        <tr class="bill-item-row">
                            <td><input type="text" name="item_description[]" class="form-control form-control-sm item-description" value="<?= esc($item['description'] ?? '') ?>" placeholder="Item / service description"></td>
                            <td><input type="number" name="item_quantity[]" class="form-control form-control-sm item-quantity" step="0.01" min="0.01" value="<?= esc($item['quantity'] ?? 1) ?>"></td>
                            <td><input type="number" name="item_rate[]" class="form-control form-control-sm item-rate" step="0.01" min="0" value="<?= esc($item['rate'] ?? '') ?>"></td>
                            <td><input type="text" class="form-control form-control-sm item-amount" value="0.00" readonly tabindex="-1"></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger removeItemRow"><i class="fas fa-trash"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addItemRow"><i class="fas fa-plus me-1"></i>Add Item</button>

            <div class="row">
                <div class="col-md-8">
                    <h6 class="text-muted text-uppercase small fw-bold mb-2">Tax &amp; Discount</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Discount %</label>
                            <input type="number" name="discount_percent" id="discountPercent" class="form-control" step="0.01" min="0" max="100" value="<?= esc($bill['discount_percent'] ?? 0) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">GST %</label>
                            <input type="number" name="gst_percent" id="gstPercent" class="form-control" step="0.01" min="0" max="100" value="<?= esc($bill['gst_percent'] ?? 18) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">GST Type</label>
                            <select name="gst_type" id="gstType" class="form-select">
                                <?php foreach ($gstTypes as $t): ?>
                                    <option value="<?= $t ?>" <?= (($bill['gst_type'] ?? 'intra_state') === $t) ? 'selected' : '' ?>><?= $t === 'inter_state' ? 'Inter-state (IGST)' : 'Intra-state (CGST + SGST)' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes / Terms</label>
                        <textarea name="notes" class="form-control" rows="2"><?= esc($bill['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted text-uppercase small fw-bold mb-2">Totals</h6>
                    <div class="card bg-light border-0" id="billTotals">
                        <div class="card-body">
                            <div class="row-line"><span>Subtotal</span><span id="totalSubtotal">0.00</span></div>
                            <div class="row-line"><span>Discount</span><span id="totalDiscount">0.00</span></div>
                            <div class="row-line" id="cgstLine"><span>CGST</span><span id="totalCgst">0.00</span></div>
                            <div class="row-line" id="sgstLine"><span>SGST</span><span id="totalSgst">0.00</span></div>
                            <div class="row-line" id="igstLine"><span>IGST</span><span id="totalIgst">0.00</span></div>
                            <div class="row-line grand"><span>Grand Total</span><span id="totalGrand">0.00</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><?= $bill ? 'Update Bill' : 'Record Bill' ?></button>
            <a href="<?= site_url('accounting/bills') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemsBody = document.getElementById('itemsBody');
    const rowTemplate = itemsBody.querySelector('.bill-item-row').cloneNode(true);
    rowTemplate.querySelectorAll('input').forEach(function (el) {
        if (! el.readOnly) el.value = el.classList.contains('item-quantity') ? '1' : '';
    });

    function fmt(n) {
        return (Math.round(n * 100) / 100).toFixed(2);
    }

    function recalc() {
        let subtotal = 0;

        itemsBody.querySelectorAll('.bill-item-row').forEach(function (row) {
            const qty  = parseFloat(row.querySelector('.item-quantity').value) || 0;
            const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
            const amt  = qty * rate;
            row.querySelector('.item-amount').value = fmt(amt);
            subtotal += amt;
        });

        const discountPercent = parseFloat(document.getElementById('discountPercent').value) || 0;
        const gstPercent      = parseFloat(document.getElementById('gstPercent').value) || 0;
        const gstType         = document.getElementById('gstType').value;

        const discountAmount = subtotal * discountPercent / 100;
        const taxable        = Math.max(0, subtotal - discountAmount);

        let cgst = 0, sgst = 0, igst = 0;
        if (gstType === 'inter_state') {
            igst = taxable * gstPercent / 100;
        } else {
            cgst = taxable * gstPercent / 200;
            sgst = taxable * gstPercent / 200;
        }

        const grand = taxable + cgst + sgst + igst;

        document.getElementById('totalSubtotal').textContent = fmt(subtotal);
        document.getElementById('totalDiscount').textContent = '- ' + fmt(discountAmount);
        document.getElementById('totalCgst').textContent = fmt(cgst);
        document.getElementById('totalSgst').textContent = fmt(sgst);
        document.getElementById('totalIgst').textContent = fmt(igst);
        document.getElementById('totalGrand').textContent = fmt(grand);

        document.getElementById('cgstLine').hidden = gstType === 'inter_state';
        document.getElementById('sgstLine').hidden = gstType === 'inter_state';
        document.getElementById('igstLine').hidden = gstType !== 'inter_state';
    }

    document.getElementById('addItemRow').addEventListener('click', function () {
        itemsBody.appendChild(rowTemplate.cloneNode(true));
        recalc();
    });

    itemsBody.addEventListener('click', function (e) {
        const btn = e.target.closest('.removeItemRow');
        if (! btn) return;
        if (itemsBody.querySelectorAll('.bill-item-row').length > 1) {
            btn.closest('.bill-item-row').remove();
            recalc();
        }
    });

    document.getElementById('billForm').addEventListener('input', function (e) {
        if (e.target.closest('.bill-item-row') || e.target.id === 'discountPercent' || e.target.id === 'gstPercent') recalc();
    });
    document.getElementById('gstType').addEventListener('change', recalc);

    recalc();
});
</script>
<?= $this->endSection() ?>
