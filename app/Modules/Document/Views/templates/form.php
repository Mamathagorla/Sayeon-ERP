<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <?php if ($prefill): ?>
                    <div class="alert alert-info py-2 small">
                        Uploading a new version of <strong><?= esc($types[$type]['label']) ?></strong>.
                        Current active version: <strong>v<?= (int) $prefill['version'] ?></strong>. This upload becomes <strong>v<?= (int) $nextVersion ?></strong>.
                    </div>
                <?php endif; ?>
                <form action="<?= site_url('documents/templates') ?>" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company</label>
                            <select name="company_id" class="form-select" required>
                                <?php if (count($companies) !== 1): ?><option value="">Select company</option><?php endif; ?>
                                <?php foreach ($companies as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ((string) old('company_id', $companyId) === (string) $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Document Type</label>
                            <select name="document_type" class="form-select" required>
                                <option value="">Select type</option>
                                <?php foreach ($types as $key => $t): ?>
                                    <option value="<?= $key ?>" <?= (old('document_type', $type) === $key) ? 'selected' : '' ?>><?= esc($t['group'] . ' — ' . $t['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Show in workflow <span class="text-muted small">(optional)</span></label>
                            <select name="workflow" class="form-select">
                                <option value="">Automatic — where this document type normally belongs</option>
                                <?php foreach (\App\Modules\Document\Services\DocumentTypeRegistry::WORKFLOW_LABELS as $wk => $wl): ?>
                                    <option value="<?= esc($wk) ?>" <?= (old('workflow') === $wk) ? 'selected' : '' ?>>Only in <?= esc($wl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Template Name</label>
                            <input type="text" name="template_name" class="form-control" required maxlength="150"
                                   value="<?= esc(old('template_name', $prefill['template_name'] ?? '')) ?>" placeholder="e.g. Standard Offer Letter">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Template File <span class="text-muted small">(PDF, DOCX or HTML — PDF/DOCX up to 10MB, HTML up to 2MB)</span></label>
                            <input type="file" name="file" id="tplFile" class="form-control" accept=".pdf,.docx,.html,.htm" required>
                            <input type="hidden" name="field_map" id="tplMap">
                            <?php if (! empty($asDraft)): ?><input type="hidden" name="as_draft" value="1"><?php endif; ?>
                            <input type="hidden" name="field_review" id="tplReview">
                            <div id="tplDetect" class="mt-2 small d-none"></div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Notes <span class="text-muted small">(optional)</span></label>
                            <input type="text" name="notes" class="form-control" maxlength="500" value="<?= esc(old('notes')) ?>" placeholder="What changed in this version?">
                        </div>
                        <div class="col-md-12 mb-3">
                            <?php if (! empty($asDraft)): ?>
                            <div class="alert alert-info py-2 small">This upload is saved as a <strong>draft</strong>. Preview it, then activate it from the template page.</div>
                            <?php else: ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="activate" value="1" id="activate" <?= old('activate', $prefill ? '' : '1') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="activate">Use it right away <span class="text-muted small">(existing ERP actions start using this version for new documents; the first template for a document type always does)</span></label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button class="btn btn-primary" id="tplSubmit">Upload Template</button>
                    <a href="<?= site_url('documents/templates') ?>" class="btn btn-light">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body small">
                <h6 class="mb-2">Supported formats</h6>
                <p class="text-muted"><strong>DOCX</strong> — best for fully automatic documents. Type <code>{{employee_name}}</code>, <code>{{designation}}</code>, <code>{{joining_date}}</code>, <code>{{company_name}}</code> etc. in the Word file; the generated document is a filled .docx with the original layout.</p>
                <p class="text-muted"><strong>HTML</strong> — same placeholders; produces a PDF. Repeating rows (invoice lines) go between <code>{{#items}}</code> and <code>{{/items}}</code>.</p>
                <p class="text-muted"><strong>PDF</strong> — your approved format is kept exactly as uploaded (logo, columns, layout). After uploading, click the sample values (employee name, ID, amounts…) and choose which ERP field replaces each; generated documents are the same PDF with the real data.</p>
                <p class="text-muted mb-0">Every company's templates are private to that company.</p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<?= view('App\Modules\Document\templates/_detect') ?>
<script>
(function () {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    const defs = <?= json_encode($defs) ?>;
    const file = document.getElementById('tplFile');
    const type = document.querySelector('select[name=document_type]');
    const out = document.getElementById('tplDetect');
    const submit = document.getElementById('tplSubmit');
    let seq = 0;

    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    async function analyse() {
        const my = ++seq;
        document.getElementById('tplMap').value = '';
        document.getElementById('tplReview').value = '';
        const f = file.files[0];
        if (!f || !/\.pdf$/i.test(f.name)) { out.classList.add('d-none'); submit.disabled = false; return; }
        if (!type.value || !defs[type.value]) { out.classList.remove('d-none'); out.innerHTML = '<span class="text-muted">Choose the document type to detect fields.</span>'; return; }

        submit.disabled = true;
        out.classList.remove('d-none');
        out.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Reading the PDF and detecting fields…';
        try {
            const pdf = await pdfjsLib.getDocument({ data: new Uint8Array(await f.arrayBuffer()) }).promise;
            const r = await PdfDetect.run(pdf, defs[type.value]);
            if (my !== seq) return;
            if (!r.map.length && !r.flags.some((x) => x.blocking)) { r.flags.push({ field: 'all', label: '', reason: 'No recognisable fields found.', blocking: true }); }
            document.getElementById('tplMap').value = JSON.stringify(r.map);
            document.getElementById('tplReview').value = JSON.stringify(r.flags);
            const blocking = r.flags.filter((x) => x.blocking), notes = r.flags.filter((x) => !x.blocking);
            let h = '<div class="text-success"><i class="fas fa-circle-check me-1"></i><strong>' + r.map.length + '</strong> field(s) detected automatically.</div>';
            if (blocking.length) {
                h += '<div class="text-warning-emphasis mt-1"><i class="fas fa-triangle-exclamation me-1"></i><strong>Needs review before use:</strong><ul class="mb-1">' +
                    blocking.map((x) => '<li>' + esc(x.field) + (x.label ? ' (“' + esc(x.label) + '”)' : '') + ' — ' + esc(x.reason) + '</li>').join('') + '</ul></div>';
            }
            if (notes.length) {
                h += '<details class="text-muted mt-1"><summary>' + notes.length + ' note(s)</summary><ul class="mb-0">' + notes.map((x) => '<li>' + esc(x.label ? x.label + ' — ' : '') + esc(x.reason) + '</li>').join('') + '</ul></details>';
            }
            out.innerHTML = h;
        } catch (e) {
            if (my !== seq) return;
            document.getElementById('tplReview').value = JSON.stringify([{ field: 'all', label: '', reason: 'Automatic detection could not read this PDF in the browser.', blocking: true }]);
            out.innerHTML = '<span class="text-danger">Automatic detection failed (' + esc(e.message || e) + '). You can still upload; the template will be marked for review.</span>';
        }
        submit.disabled = false;
    }
    file.addEventListener('change', analyse);
    type.addEventListener('change', analyse);
})();
</script>
<?= $this->endSection() ?>
