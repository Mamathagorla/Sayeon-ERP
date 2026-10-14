<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form data-erp-sections data-erp-first-section="Company Details" action="<?= $company ? site_url('companies/' . $company['id']) : site_url('companies') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company Name</label>
                    <input type="text" name="name" class="form-control" value="<?= esc($company['name'] ?? old('name')) ?>" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Short Code</label>
                    <input type="text" name="short_code" class="form-control" value="<?= esc($company['short_code'] ?? old('short_code')) ?>" maxlength="6" style="text-transform:uppercase" pattern="[A-Za-z0-9]*" title="Letters and numbers only" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="active" <?= (($company['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($company['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12 erp-section-marker" data-section-title="Location &amp; Ownership"></div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Country</label>
                    <select name="country_id" id="companyCountry" class="form-select" required>
                        <option value="">Select country</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($company['country_id'] ?? old('country_id')) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">State</label>
                    <select name="state_id" id="companyState" class="form-select">
                        <option value="">— Not set —</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Owner</label>
                    <?php $ownerName = ''; foreach ($owners as $o) { if (($company['owner_id'] ?? null) == $o['id']) { $ownerName = $o['name']; } } ?>
                    <input type="text" id="companyOwnerInput" class="form-control" list="companyOwnerList" value="<?= esc($ownerName) ?>" placeholder="Type a name" autocomplete="off">
                    <datalist id="companyOwnerList">
                        <?php foreach ($owners as $o): ?><option value="<?= esc($o['name']) ?>" data-id="<?= $o['id'] ?>"></option><?php endforeach; ?>
                    </datalist>
                    <input type="hidden" name="owner_id" id="companyOwnerId" value="<?= esc($company['owner_id'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Incorporation Date</label>
                    <input type="date" name="incorporation_date" class="form-control" value="<?= esc($company['incorporation_date'] ?? '') ?>">
                </div>
                <div class="col-12 erp-section-marker" data-section-title="Registration &amp; Tax"></div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">CIN</label>
                    <input type="text" name="cin" class="form-control" value="<?= esc($company['cin'] ?? '') ?>" maxlength="21" style="text-transform:uppercase" pattern="[LUlu][0-9]{5}[A-Za-z]{2}[0-9]{4}[A-Za-z]{3}[0-9]{6}" title="Enter a valid 21-character CIN, e.g. U74999MH2019PTC321001">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">GST</label>
                    <input type="text" name="gst" class="form-control" value="<?= esc($company['gst'] ?? '') ?>" maxlength="15" style="text-transform:uppercase" pattern="[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}[1-9A-Za-z]{1}Z[0-9A-Za-z]{1}" title="Enter a valid 15-character GSTIN, e.g. 27ABCDE1234F1Z5">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">PAN</label>
                    <input type="text" name="pan" class="form-control" value="<?= esc($company['pan'] ?? '') ?>" maxlength="10" style="text-transform:uppercase" pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}" title="Enter a valid 10-character PAN, e.g. ABCDE1234F">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Registered Address</label>
                    <textarea name="registered_address" class="form-control" rows="3"><?= esc($company['registered_address'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $company ? 'Update Company' : 'Create Company' ?></button>
            <a href="<?= site_url('companies') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // country_id => [{id, name, gstStateCode}, ...] — the same data the invoice and customer forms use.
    const statesByCountry = <?= json_encode($statesByCountry ?? []) ?>;
    const savedStateId    = <?= json_encode($company['state_id'] ?? old('state_id')) ?>;
    const countrySelect   = document.getElementById('companyCountry');
    const stateSelect     = document.getElementById('companyState');

    // Refills the State list for the chosen Country. A state from another
    // country is never kept, so the server's country check can't fail silently.
    function fillStates(selectedId) {
        stateSelect.innerHTML = '<option value="">— Not set —</option>';
        (statesByCountry[countrySelect.value] || []).forEach(function (s) {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            if (String(s.id) === String(selectedId)) opt.selected = true;
            stateSelect.appendChild(opt);
        });
    }

    // Owner: type a name; the matching user's id is what gets submitted (empty = unassigned).
    const ownerInput = document.getElementById('companyOwnerInput');
    const ownerId    = document.getElementById('companyOwnerId');
    const ownerIds   = {};
    document.querySelectorAll('#companyOwnerList option').forEach(function (o) { ownerIds[o.value.trim().toLowerCase()] = o.dataset.id; });
    function syncOwner() {
        const v = ownerInput.value.trim().toLowerCase();
        ownerId.value = v === '' ? '' : (ownerIds[v] || '');
        ownerInput.setCustomValidity(v !== '' && !ownerIds[v] ? 'Choose a name from the list.' : '');
    }
    ownerInput.addEventListener('input', syncOwner);

    fillStates(savedStateId);
    countrySelect.addEventListener('change', function () { fillStates(null); });
});
</script>
<?= $this->endSection() ?>
