<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
    $selectedCompany = null;
    foreach ($companies as $c) {
        if ((int) $c['id'] === (int) $selectedCompanyId) {
            $selectedCompany = $c;
        }
    }
?>
<div class="card col-lg-7">
    <div class="card-body">
        <p class="text-muted small">Choose which compliance types apply to this company. Leave everything unchecked to keep showing every type (the default, unrestricted behaviour).</p>

        <?php if (! empty($addedName)): ?>
            <div class="alert alert-success py-2 small">Added "<?= esc($addedName) ?>" to this company's list.</div>
        <?php endif; ?>

        <div class="d-flex gap-2 align-items-end mb-3">
            <div class="flex-grow-1">
                <?php if (count($companies) > 1): ?>
                <form method="get" action="<?= site_url('compliance/types/settings') ?>">
                    <label class="form-label small">Company</label>
                    <select name="company_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $selectedCompanyId == $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php elseif ($selectedCompany !== null): ?>
                    <label class="form-label small">Company</label>
                    <div class="fw-semibold"><?= esc($selectedCompany['name']) ?></div>
                <?php endif; ?>
            </div>
            <?php if ($selectedCompanyId !== 0 && can('compliance.create')): ?>
            <button type="button" class="btn btn-outline-secondary text-nowrap" data-bs-toggle="modal" data-bs-target="#addCompanyTypeModal" title="Add a new compliance type to this company">
                <i class="fas fa-plus me-1"></i>New
            </button>
            <?php endif; ?>
        </div>

        <?php if ($selectedCompanyId === 0): ?>
            <p class="text-muted small mb-0">No company available to configure.</p>
        <?php else: ?>
        <form method="post" action="<?= site_url('compliance/types/settings') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">

            <?php if (empty($types)): ?>
                <p class="text-muted small">No compliance types exist yet.</p>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($types as $t): ?>
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="compliance_type_ids[]" value="<?= $t['id'] ?>" id="type<?= $t['id'] ?>" <?= in_array((int) $t['id'], $applicableIds, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="type<?= $t['id'] ?>"><?= esc($t['name']) ?></label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary mt-2">Save</button>
            <a href="<?= site_url('compliance') ?>" class="btn btn-light mt-2">Cancel</a>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($selectedCompanyId !== 0 && can('compliance.create')): ?>
<div class="modal fade" id="addCompanyTypeModal" tabindex="-1" aria-labelledby="addCompanyTypeTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addCompanyTypeForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCompanyTypeTitle">Add a compliance type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Add a compliance type for <strong><?= esc($selectedCompany['name'] ?? '') ?></strong>.
                        It will appear in this company's list.
                    </p>
                    <label class="form-label" for="newCompanyTypeName">Compliance type name</label>
                    <input type="text" id="newCompanyTypeName" class="form-control" maxlength="150" placeholder="e.g. Professional Tax Return" required>
                    <div class="text-danger small mt-2" id="newCompanyTypeError" hidden></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add type</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('addCompanyTypeForm');
    if (! form) return;

    const modalEl    = document.getElementById('addCompanyTypeModal');
    const nameInput  = document.getElementById('newCompanyTypeName');
    const errorBox   = document.getElementById('newCompanyTypeError');
    const submitBtn  = form.querySelector('button[type="submit"]');
    const companyId  = <?= (int) $selectedCompanyId ?>;

    function showError(message) {
        errorBox.textContent = message;
        errorBox.hidden = false;
    }

    modalEl.addEventListener('shown.bs.modal', function () { nameInput.focus(); });
    modalEl.addEventListener('hidden.bs.modal', function () {
        form.reset();
        errorBox.hidden = true;
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const name = nameInput.value.trim();
        if (name === '') {
            showError('Enter a name for the compliance type.');
            return;
        }

        const data = new FormData();
        data.append('name', name);
        data.append('company_id', companyId);
        data.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

        submitBtn.disabled = true;

        fetch('<?= site_url('compliance/types') ?>', { method: 'POST', body: data, headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
            .then(function (result) {
                if (! result.ok || result.body.error) {
                    showError(result.body.error || 'Could not add the compliance type.');
                    return;
                }

                // Reload this company's page so the new type shows in its list.
                window.location.href = '<?= site_url('compliance/types/settings') ?>?company_id=' + companyId
                    + '&added=' + encodeURIComponent(result.body.name);
            })
            .catch(function () { showError('Could not add the compliance type. Please try again.'); })
            .finally(function () { submitBtn.disabled = false; });
    });
});
</script>
<?= $this->endSection() ?>
