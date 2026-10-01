<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form action="<?= $item ? site_url('compliance/' . $item['id']) : site_url('compliance') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" id="complianceCompanySelect" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($item['company_id'] ?? $defaultCompanyId ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Compliance Type</label>
                    <div class="d-flex gap-2">
                        <select name="compliance_type_id" id="complianceTypeSelect" class="form-select" required>
                            <option value="">Select type</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= (($item['compliance_type_id'] ?? null) == $t['id']) ? 'selected' : '' ?>><?= esc($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-secondary text-nowrap" id="addTypeBtn" title="Add a new compliance type">+ New</button>
                    </div>
                    <div class="form-text">Only shows the types applicable to the selected company, once <a href="<?= site_url('compliance/types/settings') ?>">configured</a> — otherwise every type is shown.</div>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Title <span class="text-muted small">(optional label, e.g. "GST Filing - August 2026")</span></label>
                    <input type="text" name="title" class="form-control" value="<?= esc($item['title'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Regulator <span class="text-muted small">(optional, e.g. "GST Dept", "EU GDPR", "OSHA")</span></label>
                    <input type="text" name="regulator" class="form-control" value="<?= esc($item['regulator'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Period <span class="text-muted small">(optional, e.g. "2024-2025", "Q1 2026")</span></label>
                    <input type="text" name="period" class="form-control" value="<?= esc($item['period'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= esc($item['due_date'] ?? '') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Recurrence</label>
                    <select name="recurrence" class="form-select" required>
                        <?php foreach (['none', 'monthly', 'quarterly', 'half_yearly', 'annually'] as $r): ?>
                            <option value="<?= $r ?>" <?= (($item['recurrence'] ?? 'none') === $r) ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $r))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Reminder (days before due)</label>
                    <input type="number" name="reminder_days_before" class="form-control" min="0" value="<?= esc($item['reminder_days_before'] ?? 7) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Responsible Person</label>
                    <select name="responsible_user_id" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (($item['responsible_user_id'] ?? null) == $u['id']) ? 'selected' : '' ?>><?= esc($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"><?= esc($item['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $item ? 'Update' : 'Create' ?></button>
            <a href="<?= site_url('compliance') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // company_id => [{id, name}, ...] — only present for companies that
    // have configured an explicit subset (see ComplianceController::
    // formData()); a company absent here means "show every type", same
    // fallback the rest of the app uses before this feature existed.
    const typesByCompany = <?= json_encode($typesByCompany) ?>;
    const allTypes        = <?= json_encode(array_map(static fn ($t) => ['id' => $t['id'], 'name' => $t['name']], $types)) ?>;
    const currentTypeId   = <?= json_encode($item['compliance_type_id'] ?? old('compliance_type_id')) ?>;

    const companySelect = document.getElementById('complianceCompanySelect');
    const typeSelect     = document.getElementById('complianceTypeSelect');
    const addTypeBtn     = document.getElementById('addTypeBtn');

    function refreshTypeOptions() {
        const list = typesByCompany[companySelect.value] && typesByCompany[companySelect.value].length
            ? typesByCompany[companySelect.value]
            : allTypes;

        const keep = typeSelect.value;
        typeSelect.innerHTML = '<option value="">Select type</option>';
        list.forEach(function (t) {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            if (String(t.id) === String(currentTypeId || keep)) opt.selected = true;
            typeSelect.appendChild(opt);
        });
    }

    companySelect.addEventListener('change', refreshTypeOptions);

    addTypeBtn.addEventListener('click', function () {
        if (! companySelect.value) {
            alert('Pick a company first.');
            return;
        }
        const name = prompt('New compliance type name:');
        if (! name || ! name.trim()) return;

        const formData = new FormData();
        formData.append('name', name.trim());
        formData.append('company_id', companySelect.value);
        formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

        fetch('<?= site_url('compliance/types') ?>', { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) { alert(data.error); return; }

                // Keep both the per-company cache and the "all types"
                // fallback in sync so switching companies afterward
                // (without a page reload) still shows it correctly.
                if (! typesByCompany[companySelect.value]) typesByCompany[companySelect.value] = [];
                typesByCompany[companySelect.value].push({ id: data.id, name: data.name });
                allTypes.push({ id: data.id, name: data.name });

                refreshTypeOptions();
                typeSelect.value = data.id;
            })
            .catch(function () { alert('Could not add the type — please try again.'); });
    });

    refreshTypeOptions();
});
</script>
<?= $this->endSection() ?>
