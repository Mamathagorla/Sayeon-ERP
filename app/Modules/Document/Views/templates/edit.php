<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-6 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="small text-muted mb-2">
                    <?= esc($template['template_name']) ?> v<?= (int) $template['version'] ?> — editing this copy never changes any other template.
                    Use <code>{{placeholders}}</code> such as <code>{{employee_name}}</code>; repeating rows go between <code>{{#items}}</code> and <code>{{/items}}</code>.
                </div>
                <div class="mb-3">
                    <div class="small fw-semibold mb-1">Data fields <span class="text-muted fw-normal">(click to insert at the cursor — each stays bound to the record's data)</span></div>
                    <?php foreach ($fields as $f): ?><button type="button" class="btn btn-sm btn-outline-secondary py-0 me-1 mb-1 tpl-ph font-monospace" data-ph="{{<?= esc($f) ?>}}">{{<?= esc($f) ?>}}</button><?php endforeach; ?>
                    <div class="small text-muted mt-1">Company name, address, logo and contact details of THIS company go in the header/footer as text or an <code>&lt;img&gt;</code>. Use tables, <code>style="font-family:…; margin:…; text-align:…"</code> and <code>@page { margin: … }</code> for layout. Saving checks for unknown, missing or doubled placeholders.</div>
                </div>
                <form method="post" action="<?= site_url('documents/templates/' . $template['id'] . '/edit') ?>">
                    <?= csrf_field() ?>
                    <?php foreach (['header' => 'Header', 'body' => 'Body', 'footer' => 'Footer'] as $k => $label): ?>
                        <div class="mb-3">
                            <label class="form-label"><?= $label ?> <span class="text-muted small">(HTML)</span></label>
                            <textarea name="<?= $k ?>" rows="<?= $k === 'body' ? 16 : 5 ?>" class="form-control font-monospace small" spellcheck="false"><?= esc(old($k, $parts[$k])) ?></textarea>
                        </div>
                    <?php endforeach; ?>
                    <button class="btn btn-primary">Save draft &amp; preview</button>
                    <a href="<?= site_url('documents/templates/' . $template['id']) ?>" class="btn btn-light">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-6 mb-3">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-2">Preview</h6>
                <?php if ($preview): ?>
                    <iframe src="<?= site_url('documents/templates/' . $template['id'] . '/test') ?>" style="width:100%;height:780px;border:1px solid var(--sy-border, #dee2e6)"></iframe>
                    <p class="small text-muted mt-2 mb-0">Rendered with a real record from this company when one is complete, otherwise with sample values. Once it looks right, activate it from the template page.</p>
                <?php else: ?>
                    <p class="text-muted mb-0">Save the draft to see the preview here.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var last = null;
    document.querySelectorAll('textarea[name=header],textarea[name=body],textarea[name=footer]').forEach(function (t) { t.addEventListener('focus', function () { last = t; }); });
    document.querySelectorAll('.tpl-ph').forEach(function (b) {
        b.addEventListener('click', function () {
            var t = last || document.querySelector('textarea[name=body]');
            var s = t.selectionStart || 0, e = t.selectionEnd || 0;
            t.value = t.value.slice(0, s) + b.dataset.ph + t.value.slice(e);
            t.focus(); t.selectionStart = t.selectionEnd = s + b.dataset.ph.length;
        });
    });
})();
</script>
<?= $this->endSection() ?>
