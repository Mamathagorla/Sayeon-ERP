<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('documents/templates') ?>" class="btn btn-light btn-sm">Back</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <div class="alert alert-info py-2 small">The clone is an <strong>independent draft</strong>: editing, replacing or activating it never changes the source template or documents already issued. HTML copies are fully editable (header, body, footer, logo, layout); Word copies are edited in Word and re-uploaded; fixed-layout PDFs keep their original page and only their detected fields are replaced. Placeholders stay bound to the data.</div>
                <form action="<?= site_url('documents/templates/clone') ?>" method="post" id="cloneForm">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Target company</label>
                        <select name="company_id" class="form-select" required>
                            <?php if (count($companies) !== 1): ?><option value="">Select company</option><?php endif; ?>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (string) old('company_id') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">The company that will own the new template.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Document type</label>
                        <select name="document_type" id="cloneType" class="form-select" required>
                            <option value="">Select type</option>
                            <?php foreach ($types as $key => $t): ?>
                                <option value="<?= $key ?>"><?= esc($t['group'] . ' — ' . $t['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Source template</label>
                        <select name="source_template_id" id="cloneSource" class="form-select" required>
                            <option value="">Select a document type first</option>
                            <?php foreach ($sources as $t): ?>
                                <option value="<?= $t['id'] ?>" data-type="<?= esc($t['document_type']) ?>" <?= $sourceId === (int) $t['id'] ? 'selected' : '' ?>>
                                    <?= esc($t['company_name'] . ' — ' . $t['template_name'] . ' v' . $t['version'] . ' (' . strtoupper($t['file_type']) . ', ' . $t['status'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alert alert-warning small">
                        The clone is a <strong>separate draft</strong>. The source template and everything already issued stay exactly as they are.
                        The clone can't be used until you preview it and activate it. Static text copied from the source (company name, logo, address) must be replaced.
                    </div>
                    <button class="btn btn-primary">Create draft</button>
                    <a href="<?= site_url('documents/templates') ?>" class="btn btn-light">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const type = document.getElementById('cloneType'), src = document.getElementById('cloneSource');
    const all = Array.from(src.querySelectorAll('option[data-type]'));
    function filter() {
        let any = false;
        all.forEach((o) => { const ok = o.dataset.type === type.value; o.hidden = !ok; o.disabled = !ok; if (ok) any = true; });
        if (src.selectedOptions[0] && src.selectedOptions[0].disabled) src.value = '';
        src.options[0].textContent = !type.value ? 'Select a document type first' : (any ? 'Select source template' : 'No template of this type is available to you');
    }
    // Preselected source (from the list's Clone button) sets the type too.
    const pre = src.selectedOptions[0];
    if (pre && pre.dataset && pre.dataset.type) type.value = pre.dataset.type;
    type.addEventListener('change', () => { src.value = ''; filter(); });
    filter();
})();
</script>
<?= $this->endSection() ?>
