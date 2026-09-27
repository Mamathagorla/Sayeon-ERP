<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <?php if (empty($vendors)): ?>
        <div class="alert alert-warning small">No vendors found for this company yet. <a href="<?= site_url('vendors/create') ?>">Add a vendor</a> first.</div>
        <?php endif; ?>
        <form action="<?= $order ? site_url('purchase-orders/' . $order['id']) : site_url('purchase-orders') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <?php if (! $order): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($defaultCompanyId == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <input type="text" class="form-control" value="<?= esc($order['company_name']) ?>" disabled>
                </div>
                <?php endif; ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Supplier</label>
                    <select name="vendor_id" class="form-select" required>
                        <option value="">Select vendor</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= (($order['vendor_id'] ?? null) == $v['id']) ? 'selected' : '' ?>><?= esc($v['name']) ?><?= empty($order) && ! empty($v['company_name']) ? ' (' . esc($v['company_name']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Order Date</label>
                    <input type="date" name="order_date" class="form-control" value="<?= esc($order['order_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Expected Date <span class="text-muted small">(optional)</span></label>
                    <input type="date" name="expected_date" class="form-control" value="<?= esc($order['expected_date'] ?? '') ?>">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Notes <span class="text-muted small">(optional)</span></label>
                    <textarea name="notes" class="form-control" rows="2"><?= esc($order['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <h6 class="text-muted text-uppercase small fw-bold mb-2 mt-2">Items — what's being purchased</h6>
            <div class="table-responsive mb-2">
                <table class="table table-sm align-middle" id="poItemsTable">
                    <thead>
                        <tr>
                            <th style="width:46%">Item</th>
                            <th style="width:16%">Quantity</th>
                            <th style="width:18%">Unit Price</th>
                            <th style="width:16%">Line Total</th>
                            <th style="width:4%"></th>
                        </tr>
                    </thead>
                    <tbody id="poItemsBody">
                        <?php $existingItems = ! empty($items) ? $items : [['item_name' => '', 'quantity' => 1, 'unit_price' => '']]; ?>
                        <?php foreach ($existingItems as $item): ?>
                        <tr class="po-item-row">
                            <td><input type="text" name="item_name[]" class="form-control form-control-sm po-item-name" value="<?= esc($item['item_name'] ?? '') ?>" placeholder="e.g. Dell Latitude laptop" maxlength="150"></td>
                            <td><input type="number" name="item_quantity[]" class="form-control form-control-sm po-item-quantity" step="1" min="1" value="<?= esc($item['quantity'] ?? 1) ?>"></td>
                            <td><input type="number" name="item_unit_price[]" class="form-control form-control-sm po-item-unit-price" step="0.01" min="0" value="<?= esc($item['unit_price'] ?? '') ?>"></td>
                            <td><input type="text" class="form-control form-control-sm po-item-line-total" value="0.00" readonly tabindex="-1"></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger removePoItemRow"><i class="fas fa-trash"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end fw-semibold">Total (<span id="poItemsCount">0</span> items)</td>
                            <td class="fw-semibold" id="poItemsAmount">0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addPoItemRow"><i class="fas fa-plus me-1"></i>Add Item</button>

            <div>
                <button type="submit" class="btn btn-primary"><?= $order ? 'Save Changes' : 'Submit Request' ?></button>
                <a href="<?= $order ? site_url('purchase-orders/' . $order['id']) : site_url('purchase-orders') ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemsBody = document.getElementById('poItemsBody');
    const rowTemplate = itemsBody.querySelector('.po-item-row').cloneNode(true);
    rowTemplate.querySelectorAll('input').forEach(function (el) {
        if (! el.readOnly) el.value = el.classList.contains('po-item-quantity') ? '1' : '';
    });

    function fmt(n) {
        return (Math.round(n * 100) / 100).toFixed(2);
    }

    function recalc() {
        let amount = 0;
        let count = 0;

        itemsBody.querySelectorAll('.po-item-row').forEach(function (row) {
            const qty   = parseFloat(row.querySelector('.po-item-quantity').value) || 0;
            const price = parseFloat(row.querySelector('.po-item-unit-price').value) || 0;
            const total = qty * price;
            row.querySelector('.po-item-line-total').value = fmt(total);
            if (row.querySelector('.po-item-name').value.trim() !== '') {
                amount += total;
                count += 1;
            }
        });

        document.getElementById('poItemsCount').textContent = count;
        document.getElementById('poItemsAmount').textContent = fmt(amount);
    }

    document.getElementById('addPoItemRow').addEventListener('click', function () {
        itemsBody.appendChild(rowTemplate.cloneNode(true));
        recalc();
    });

    itemsBody.addEventListener('click', function (e) {
        const btn = e.target.closest('.removePoItemRow');
        if (! btn) return;
        if (itemsBody.querySelectorAll('.po-item-row').length > 1) {
            btn.closest('.po-item-row').remove();
            recalc();
        }
    });

    itemsBody.addEventListener('input', recalc);

    recalc();
});
</script>
<?= $this->endSection() ?>
