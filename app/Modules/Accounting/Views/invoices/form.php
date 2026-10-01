<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<style>
    .inv-item-row td { vertical-align: middle; }
    .inv-item-row .form-control, .inv-item-row .form-control-sm { font-size: .85rem; }
    #invoiceTotals .row-line { display: flex; justify-content: space-between; padding: 6px 0; font-size: .88rem; border-bottom: 1px solid #f0f3f6; }
    #invoiceTotals .row-line:last-child { border-bottom: none; }
    #invoiceTotals .row-line.grand { font-weight: 700; font-size: 1rem; }
</style>
<div class="card">
    <div class="card-body">
        <form action="<?= $invoice ? site_url('accounting/invoices/' . $invoice['id']) : site_url('accounting/invoices') ?>" method="post" id="invoiceForm">
            <?= csrf_field() ?>

            <h6 class="text-muted text-uppercase small fw-bold mb-2">Invoice Details</h6>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Invoice Number</label>
                    <input type="text" name="invoice_number" id="invoiceNumberInput" class="form-control" value="<?= esc($invoice['invoice_number'] ?? $nextNumber) ?>" maxlength="40" pattern="[A-Za-z0-9\-]*" title="Letters, numbers and hyphens only" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" id="invoiceCompanySelect" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($invoice['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= (($invoice['status'] ?? 'draft') === $s) ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Issue Date</label>
                    <input type="date" name="issue_date" class="form-control" value="<?= esc($invoice['issue_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= esc($invoice['due_date'] ?? '') ?>">
                </div>
            </div>

            <h6 class="text-muted text-uppercase small fw-bold mb-2 mt-2">Customer</h6>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Existing Customer <span class="text-muted small">(optional — fills in the fields below, still editable after)</span></label>
                    <select id="existingCustomerSelect" class="form-select">
                        <option value="">— Enter manually / new customer —</option>
                    </select>
                    <input type="hidden" name="customer_id" id="customerIdInput" value="<?= esc($invoice['customer_id'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Customer Name</label>
                    <input type="text" name="customer_name" id="customerNameInput" class="form-control" value="<?= esc($invoice['customer_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="customer_email" id="customerEmailInput" class="form-control" value="<?= esc($invoice['customer_email'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="customer_phone" id="customerPhoneInput" class="form-control" value="<?= esc($invoice['customer_phone'] ?? '') ?>" maxlength="20" pattern="[0-9+\-\s]{6,20}" title="Enter a valid phone number (digits, spaces, + and - only)">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Customer GSTIN <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="customer_gstin" id="customerGstinInput" class="form-control" value="<?= esc($invoice['customer_gstin'] ?? '') ?>" maxlength="15" style="text-transform:uppercase" pattern="[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}[1-9A-Za-z]{1}Z[0-9A-Za-z]{1}" title="Enter a valid 15-character GSTIN, e.g. 27ABCDE1234F1Z5">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Billing Address</label>
                    <textarea name="customer_address" id="customerAddressInput" class="form-control" rows="1"><?= esc($invoice['customer_address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Country</label>
                    <select name="customer_country_id" id="customerCountry" class="form-select" required>
                        <option value="">Select country</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($invoice['customer_country_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">State</label>
                    <select name="customer_state_id" id="customerState" class="form-select">
                        <option value="">— Not set —</option>
                    </select>
                    <div class="form-text">Determines CGST+SGST vs IGST below — only India has states listed today.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">City</label>
                    <input type="text" name="customer_city" id="customerCityInput" class="form-control" value="<?= esc($invoice['customer_city'] ?? '') ?>" maxlength="100">
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
                        <tr class="inv-item-row">
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
                            <input type="number" name="discount_percent" id="discountPercent" class="form-control" step="0.01" min="0" max="100" value="<?= esc($invoice['discount_percent'] ?? 0) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">GST %</label>
                            <input type="number" name="gst_percent" id="gstPercent" class="form-control" step="0.01" min="0" max="100" value="<?= esc($invoice['gst_percent'] ?? gst_default_percent()) ?>" list="gstRateSlabs">
                            <datalist id="gstRateSlabs">
                                <?php foreach ($gstRateSlabs as $slab): ?>
                                    <option value="<?= $slab ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">GST Type</label>
                            <select name="gst_type" id="gstType" class="form-select">
                                <?php foreach ($gstTypes as $t): ?>
                                    <option value="<?= $t ?>" <?= (($invoice['gst_type'] ?? 'intra_state') === $t) ? 'selected' : '' ?>><?= $t === 'inter_state' ? 'Inter-state (IGST)' : 'Intra-state (CGST + SGST)' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" id="gstTypeHint">Auto-set from the company's and customer's state once both are picked — change it yourself if this invoice is an exception.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= esc($invoice['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted text-uppercase small fw-bold mb-2">Totals</h6>
                    <div class="card bg-light border-0" id="invoiceTotals">
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

            <button type="submit" class="btn btn-primary"><?= $invoice ? 'Update Invoice' : 'Create Invoice' ?></button>
            <a href="<?= site_url('accounting/invoices') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // country_id => [{id, name}, ...] — same shape/data the Company form uses.
    const statesByCountry  = <?= json_encode($statesByCountry) ?>;
    const companyStateById = <?= json_encode($companyStateById) ?>;
    const currentStateId   = <?= json_encode($invoice['customer_state_id'] ?? old('customer_state_id')) ?>;
    // company_id => [{id, name, email, phone, gstin, address, country_id, state_id, city}, ...]
    const customersByCompany = <?= json_encode($customersByCompany) ?>;
    const currentCustomerId  = <?= json_encode($invoice['customer_id'] ?? old('customer_id')) ?>;
    <?php if (! $invoice): ?>
    // Only on create — an existing invoice's number is never resuggested.
    const nextNumberByCompany = <?= json_encode($nextNumberByCompany) ?>;
    const numberInput = document.getElementById('invoiceNumberInput');
    let numberEdited = false;
    numberInput.addEventListener('input', function () { numberEdited = true; });
    <?php endif; ?>

    const companySelect = document.getElementById('invoiceCompanySelect');
    const countrySelect = document.getElementById('customerCountry');
    const stateSelect    = document.getElementById('customerState');
    const gstTypeSelect  = document.getElementById('gstType');
    const gstTypeHint    = document.getElementById('gstTypeHint');

    // "Pick an existing customer" — refills the option list whenever the
    // Company changes (a customer only ever belongs to one company, see
    // CustomerModel), and autofills the plain customer_* fields below
    // from the picked row. The fields stay ordinary editable inputs
    // afterwards — picking a customer is a convenience starting point,
    // not a locked/read-only link, and the invoice still keeps its own
    // independent snapshot columns (see the customer_id migration's
    // docblock) regardless of what's picked here.
    const existingCustomerSelect = document.getElementById('existingCustomerSelect');
    const customerIdInput      = document.getElementById('customerIdInput');
    const customerNameInput    = document.getElementById('customerNameInput');
    const customerEmailInput   = document.getElementById('customerEmailInput');
    const customerPhoneInput   = document.getElementById('customerPhoneInput');
    const customerGstinInput   = document.getElementById('customerGstinInput');
    const customerAddressInput = document.getElementById('customerAddressInput');
    const customerCityInput    = document.getElementById('customerCityInput');

    function refreshExistingCustomerOptions() {
        const customers = customersByCompany[companySelect.value] || [];
        existingCustomerSelect.innerHTML = '<option value="">— Enter manually / new customer —</option>';
        customers.forEach(function (cust) {
            const opt = document.createElement('option');
            opt.value = cust.id;
            opt.textContent = cust.name + (cust.gstin ? ' (' + cust.gstin + ')' : '');
            if (String(cust.id) === String(currentCustomerId)) opt.selected = true;
            existingCustomerSelect.appendChild(opt);
        });
    }

    existingCustomerSelect.addEventListener('change', function () {
        const customers = customersByCompany[companySelect.value] || [];
        const picked = customers.find(function (c) { return String(c.id) === existingCustomerSelect.value; });

        customerIdInput.value = picked ? picked.id : '';
        if (! picked) return;

        customerNameInput.value    = picked.name || '';
        customerEmailInput.value   = picked.email || '';
        customerPhoneInput.value   = picked.phone || '';
        customerGstinInput.value   = picked.gstin || '';
        customerAddressInput.value = picked.address || '';
        customerCityInput.value    = picked.city || '';
        if (picked.country_id) countrySelect.value = picked.country_id;
        refreshCustomerStates();
        if (picked.state_id) stateSelect.value = picked.state_id;
        autoSetGstType();
    });

    // A manual edit to any customer_* field after picking one just means
    // this invoice's details have diverged from that Customer record —
    // still fine to keep customer_id pointing at it (it's only ever a
    // "copied from" reference), so nothing here clears the hidden field.

    function refreshCustomerStates() {
        const states = statesByCountry[countrySelect.value] || [];
        const keep = stateSelect.value;
        stateSelect.innerHTML = '<option value="">— Not set —</option>';
        states.forEach(function (s) {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            if (String(s.id) === String(currentStateId || keep)) opt.selected = true;
            stateSelect.appendChild(opt);
        });
    }

    // Mirrors determine_gst_type() in gst_helper.php — same company vs
    // customer state comparison — so the dropdown already shows the right
    // value before submit; the server recomputes it independently anyway.
    function autoSetGstType() {
        const companyStateId  = companyStateById[companySelect.value];
        const customerStateId = stateSelect.value;

        if (! companyStateId || ! customerStateId) {
            gstTypeHint.textContent = 'Pick the company and the customer\'s state to auto-set this — change it yourself if this invoice is an exception.';
            return;
        }

        gstTypeSelect.value = (String(companyStateId) === String(customerStateId)) ? 'intra_state' : 'inter_state';
        gstTypeHint.textContent = 'Auto-set: ' + (gstTypeSelect.value === 'intra_state' ? 'customer is in the same state as the company.' : 'customer is in a different state from the company.');
        recalc();
    }

    countrySelect.addEventListener('change', function () { refreshCustomerStates(); autoSetGstType(); });
    stateSelect.addEventListener('change', autoSetGstType);
    companySelect.addEventListener('change', autoSetGstType);
    companySelect.addEventListener('change', refreshExistingCustomerOptions);
    <?php if (! $invoice): ?>
    companySelect.addEventListener('change', function () {
        if (numberEdited) return; // don't clobber a value already typed
        const suggestion = nextNumberByCompany[companySelect.value];
        if (suggestion) numberInput.value = suggestion;
    });
    <?php endif; ?>

    const itemsBody = document.getElementById('itemsBody');
    const rowTemplate = itemsBody.querySelector('.inv-item-row').cloneNode(true);
    rowTemplate.querySelectorAll('input').forEach(function (el) {
        if (! el.readOnly) el.value = el.classList.contains('item-quantity') ? '1' : '';
    });

    function fmt(n) {
        return (Math.round(n * 100) / 100).toFixed(2);
    }

    function recalc() {
        let subtotal = 0;

        itemsBody.querySelectorAll('.inv-item-row').forEach(function (row) {
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
        if (itemsBody.querySelectorAll('.inv-item-row').length > 1) {
            btn.closest('.inv-item-row').remove();
            recalc();
        }
    });

    document.getElementById('invoiceForm').addEventListener('input', function (e) {
        if (e.target.closest('.inv-item-row') || e.target.id === 'discountPercent' || e.target.id === 'gstPercent') recalc();
    });
    document.getElementById('gstType').addEventListener('change', recalc);

    refreshExistingCustomerOptions();
    refreshCustomerStates();
    autoSetGstType(); // no-op (and no recalc()) until both states are known
    recalc();
});
</script>
<?= $this->endSection() ?>
