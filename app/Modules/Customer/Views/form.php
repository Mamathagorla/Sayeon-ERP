<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form data-erp-sections data-erp-first-section="Basic Information" action="<?= $customer ? site_url('customers/' . $customer['id']) : site_url('customers') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Customer / Business Name</label>
                    <input type="text" name="name" class="form-control" value="<?= esc($customer['name'] ?? old('name')) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Person <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="contact_person" class="form-control" value="<?= esc($customer['contact_person'] ?? old('contact_person')) ?>" maxlength="150">
                </div>
                <?php if (! $customer): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <input type="text" class="form-control" value="<?= esc($customer['company_name']) ?>" disabled>
                </div>
                <?php endif; ?>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= (($customer['status'] ?? 'active') === $s) ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 erp-section-marker" data-section-title="Contact &amp; Tax"></div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Email <span class="text-muted small">(optional)</span></label>
                    <input type="email" name="email" class="form-control" value="<?= esc($customer['email'] ?? old('email')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Phone <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="phone" class="form-control" value="<?= esc($customer['phone'] ?? old('phone')) ?>" maxlength="20">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">GSTIN <span class="text-muted small">(optional)</span></label>
                    <div class="input-group">
                        <span class="input-group-text px-2" id="gstinPrefixWrap" style="min-width:44px;justify-content:center" title="GST state code, determined automatically from the selected state">
                            <input type="text" id="gstinPrefixInput" maxlength="2" style="width:26px;border:0;background:transparent;padding:0;text-align:center;font-weight:600" value="" placeholder="--">
                        </span>
                        <input type="text" id="gstinRestInput" class="form-control" maxlength="13" style="text-transform:uppercase" placeholder="ABCDE0000A1Z5" title="Remaining 13 characters of the GSTIN">
                    </div>
                    <input type="hidden" name="gstin" id="gstinHiddenInput" value="<?= esc($customer['gstin'] ?? old('gstin')) ?>">
                    <div class="form-text" id="gstinHint">Select a State to auto-fill the GST state-code prefix.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">PAN <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="pan" class="form-control" value="<?= esc($customer['pan'] ?? old('pan')) ?>" maxlength="10" style="text-transform:uppercase" pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}" title="Enter a valid 10-character PAN, e.g. ABCDE1234F">
                </div>
                <div class="col-12 erp-section-marker" data-section-title="Location"></div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Country</label>
                    <select name="country_id" id="customerCountry" class="form-select">
                        <option value="">Select country</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($customer['country_id'] ?? old('country_id')) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">State</label>
                    <select name="state_id" id="customerState" class="form-select">
                        <option value="">— Not set —</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">City <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="city" class="form-control" value="<?= esc($customer['city'] ?? old('city')) ?>" maxlength="100">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Address <span class="text-muted small">(optional)</span></label>
                    <textarea name="address" class="form-control" rows="2"><?= esc($customer['address'] ?? old('address')) ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $customer ? 'Save Changes' : 'Add Customer' ?></button>
            <a href="<?= site_url('customers') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statesByCountry = <?= json_encode($statesByCountry) ?>;
    const currentStateId  = <?= json_encode($customer['state_id'] ?? old('state_id')) ?>;
    const countrySelect = document.getElementById('customerCountry');
    const stateSelect   = document.getElementById('customerState');

    function refreshStates() {
        const states = statesByCountry[countrySelect.value] || [];
        const keep = stateSelect.value;
        stateSelect.innerHTML = '<option value="">— Not set —</option>';
        states.forEach(function (s) {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            opt.dataset.gstCode = s.gstStateCode || '';
            if (String(s.id) === String(currentStateId || keep)) opt.selected = true;
            stateSelect.appendChild(opt);
        });
    }

    // GSTIN prefix — "[27][_____________]". The first two characters are
    // never typed by the user: they're the selected state's own GST
    // state code (states.gst_state_code, India-only — see StateModel::
    // allGroupedByCountry()). Nothing here ever invents the REST of a
    // GSTIN, only that fixed, known prefix.
    const gstinPrefixInput = document.getElementById('gstinPrefixInput');
    const gstinRestInput   = document.getElementById('gstinRestInput');
    const gstinHiddenInput = document.getElementById('gstinHiddenInput');
    const gstinHint        = document.getElementById('gstinHint');

    // Split whatever full GSTIN already exists (edit mode) into its
    // prefix/rest halves once, up front.
    (function seedGstinParts() {
        const existing = (gstinHiddenInput.value || '').toUpperCase();
        gstinPrefixInput.value = existing.slice(0, 2);
        gstinRestInput.value   = existing.slice(2);
    })();

    function syncGstinHidden() {
        gstinHiddenInput.value = (gstinPrefixInput.value + gstinRestInput.value).toUpperCase();
    }

    function applyStateGstCode() {
        const opt = stateSelect.options[stateSelect.selectedIndex];
        const code = opt ? opt.dataset.gstCode : '';

        if (code) {
            gstinPrefixInput.value = code;
            gstinPrefixInput.readOnly = true;
            gstinHint.textContent = 'Prefix ' + code + ' is fixed to the selected state — enter the remaining 13 characters.';
        } else {
            gstinPrefixInput.readOnly = false;
            gstinHint.textContent = stateSelect.value
                ? 'No GST state code for this state/country — enter the full GSTIN manually.'
                : 'Select a State to auto-fill the GST state-code prefix.';
        }

        syncGstinHidden();
    }

    gstinRestInput.addEventListener('input', function () {
        gstinRestInput.value = gstinRestInput.value.toUpperCase();
        syncGstinHidden();
    });
    gstinPrefixInput.addEventListener('input', function () {
        gstinPrefixInput.value = gstinPrefixInput.value.toUpperCase().slice(0, 2);
        syncGstinHidden();
    });

    countrySelect.addEventListener('change', function () { refreshStates(); applyStateGstCode(); });
    stateSelect.addEventListener('change', applyStateGstCode);

    refreshStates();
    applyStateGstCode();
});
</script>
<?= $this->endSection() ?>
