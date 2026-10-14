<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('documents/templates') ?>" class="btn btn-light btn-sm">Back</a>
<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<style>
    .pm-wrap { position: relative; display: inline-block; border: 1px solid var(--sy-border, #dee2e6); background: #fff; user-select: none; }
    .pm-wrap canvas { display: block; }
    .pm-layer { position: absolute; inset: 0; cursor: crosshair; }
    .pm-item { position: absolute; border-radius: 2px; cursor: pointer; }
    .pm-item:hover { background: rgba(13, 110, 253, .18); outline: 1px solid rgba(13, 110, 253, .6); }
    .pm-sel { position: absolute; border: 2px solid #dc3545; background: rgba(220, 53, 69, .15); pointer-events: none; }
    .pm-map { position: absolute; border: 1px solid #198754; background: rgba(25, 135, 84, .15); pointer-events: none; }
    .pm-map span { position: absolute; left: 0; top: -14px; font: 10px/12px sans-serif; background: #198754; color: #fff; padding: 0 3px; white-space: nowrap; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-8 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="small text-muted">
                        <strong><?= esc($typeLabel) ?></strong> — <?= esc($template['template_name']) ?> v<?= (int) $template['version'] ?>.
                        <strong>Click</strong> a sample value to select it (Shift+click to add neighbouring text), or <strong>drag</strong> a box over any area.
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="pmPrev">&laquo;</button>
                        <span class="btn btn-outline-secondary disabled" id="pmPageLbl">1 / 1</span>
                        <button type="button" class="btn btn-outline-secondary" id="pmNext">&raquo;</button>
                    </div>
                </div>
                <div style="overflow:auto">
                    <div class="pm-wrap" id="pmWrap"><canvas id="pmCanvas"></canvas><div class="pm-layer" id="pmLayer"></div></div>
                </div>
                <div id="pmError" class="alert alert-danger mt-2 d-none"></div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-2">Selected value</h6>
                <div id="pmNone" class="small text-muted">Nothing selected. Click the sample text you want replaced.</div>
                <div id="pmForm" class="d-none">
                    <div class="small mb-2">Sample text: <code id="pmSample"></code></div>
                    <label class="form-label small mb-1">Replace with ERP field</label>
                    <select id="pmField" class="form-select form-select-sm mb-2">
                        <option value="">Select field…</option>
                        <option value="__blank">(clear — leave blank)</option>
                        <option value="__text">(custom text with {{fields}})</option>
                        <?php foreach ($fields as $f): ?><option value="<?= esc($f) ?>"><?= esc($f) ?></option><?php endforeach; ?>
                    </select>
                    <input type="text" id="pmText" class="form-control form-control-sm mb-2 d-none" maxlength="200" placeholder="e.g. Payslip for {{period}}">
                    <div class="row g-2 mb-2">
                        <div class="col-4"><label class="form-label small mb-1">Size</label><input type="number" step="0.5" min="4" max="96" id="pmSize" class="form-control form-control-sm"></div>
                        <div class="col-4"><label class="form-label small mb-1">Align</label>
                            <select id="pmAlign" class="form-select form-select-sm"><option value="L">Left</option><option value="C">Center</option><option value="R">Right</option></select></div>
                        <div class="col-4 pt-4">
                            <div class="form-check"><input type="checkbox" class="form-check-input" id="pmBold"><label class="form-check-label small" for="pmBold">Bold</label></div>
                            <div class="form-check"><input type="checkbox" class="form-check-input" id="pmSerif"><label class="form-check-label small" for="pmSerif">Serif</label></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="pmAssign">Assign field</button>
                    <button type="button" class="btn btn-light btn-sm" id="pmCancel">Cancel</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h6 class="mb-2">Fields <span class="badge bg-secondary" id="pmCount">0</span></h6>
                <button type="button" class="btn btn-outline-primary btn-sm mb-2" id="pmDetect"><i class="fas fa-wand-magic-sparkles me-1"></i>Detect automatically</button>
                <div id="pmFlags" class="small mb-2"></div>
                <div id="pmList" class="small mb-3"></div>
                <form method="post" action="<?= site_url('documents/templates/' . $template['id'] . '/map') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="field_map" id="pmJson">
                    <button class="btn btn-success btn-sm" id="pmSave">Save fields</button>
                </form>
                <p class="small text-muted mt-2 mb-0">Fields are normally detected automatically when the template is uploaded; use this screen only to review or adjust them. The original PDF stays untouched. When generating, each mapped sample value is covered and the real value is printed in the same place. Long values shrink to fit the box you selected.</p>
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
    const SCALE = 1.5;
    const pdfUrl = <?= json_encode(site_url('documents/templates/' . $template['id'] . '/download?inline=1')) ?>;
    let map = <?= json_encode($map) ?>;
    let pdf = null, pageNo = 1, page = null, sel = null;

    const $ = (id) => document.getElementById(id);
    const canvas = $('pmCanvas'), layer = $('pmLayer');

    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    const fail = (m) => { const e = $('pmError'); e.textContent = m; e.classList.remove('d-none'); };

    pdfjsLib.getDocument(pdfUrl).promise.then((doc) => { pdf = doc; show(1); }, () => fail('Could not load the PDF.'));

    function box(cls, r, label) {
        const d = document.createElement('div');
        d.className = cls;
        d.style.left = r.x * SCALE + 'px'; d.style.top = r.y * SCALE + 'px';
        d.style.width = r.w * SCALE + 'px'; d.style.height = r.h * SCALE + 'px';
        if (label) { const s = document.createElement('span'); s.textContent = label; d.appendChild(s); }
        layer.appendChild(d);
        return d;
    }

    async function show(n) {
        pageNo = n;
        page = await pdf.getPage(n);
        const vp = page.getViewport({ scale: SCALE });
        canvas.width = vp.width; canvas.height = vp.height;
        $('pmWrap').style.width = vp.width + 'px'; $('pmWrap').style.height = vp.height + 'px';
        await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
        $('pmPageLbl').textContent = n + ' / ' + pdf.numPages;
        const tc = await page.getTextContent();
        items = tc.items.filter((i) => i.str.trim() !== '' && i.width > 0).map((i) => {
            const t = pdfjsLib.Util.transform(vp.transform, i.transform);
            const fh = Math.hypot(t[2], t[3]);
            let name = '';
            try { name = (page.commonObjs.has(i.fontName) ? page.commonObjs.get(i.fontName).name : '') || ''; } catch (e) {}
            return {
                str: i.str, x: t[4] / SCALE, y: (t[5] - 0.85 * fh) / SCALE, w: i.width * SCALE / SCALE, h: 1.1 * fh / SCALE,
                base: t[5] / SCALE, size: fh / SCALE,
                bold: /bold|black|heavy|semibold/i.test(name), serif: /times|serif|georgia|garamond|cambria/i.test(name) && !/sans/i.test(name),
            };
        });
        draw();
    }
    let items = [];

    function draw() {
        layer.innerHTML = '';
        items.forEach((it) => {
            const d = box('pm-item', it);
            d.addEventListener('click', (ev) => { ev.stopPropagation(); pick(it, ev.shiftKey); });
        });
        map.filter((m) => m.page === pageNo).forEach((m) => box('pm-map', m, m.field === '__blank' ? '(blank)' : (m.field === '__text' ? m.text : m.field)));
        if (sel && sel.page === pageNo) box('pm-sel', sel);
        list();
    }

    function pick(it, add) {
        if (add && sel && sel.page === pageNo) {
            const x1 = Math.min(sel.x, it.x), y1 = Math.min(sel.y, it.y);
            const x2 = Math.max(sel.x + sel.w, it.x + it.w), y2 = Math.max(sel.y + sel.h, it.y + it.h);
            sel = Object.assign({}, sel, { x: x1, y: y1, w: x2 - x1, h: y2 - y1, str: sel.str + ' ' + it.str });
        } else {
            sel = { page: pageNo, x: it.x, y: it.y, w: it.w, h: it.h, base: it.base, size: it.size, bold: it.bold, serif: it.serif, str: it.str };
        }
        openForm();
    }

    function openForm() {
        $('pmNone').classList.add('d-none'); $('pmForm').classList.remove('d-none');
        $('pmSample').textContent = sel.str || '(area)';
        $('pmSize').value = Math.round(sel.size * 2) / 2;
        $('pmBold').checked = !!sel.bold; $('pmSerif').checked = !!sel.serif;
        $('pmAlign').value = 'L'; $('pmField').value = ''; $('pmText').value = ''; $('pmText').classList.add('d-none');
        draw();
    }

    function closeForm() { sel = null; $('pmForm').classList.add('d-none'); $('pmNone').classList.remove('d-none'); draw(); }

    // Drag-select any rectangle.
    let drag = null;
    layer.addEventListener('mousedown', (ev) => {
        if (ev.target !== layer) return;
        const r = layer.getBoundingClientRect();
        drag = { x: ev.clientX - r.left, y: ev.clientY - r.top, el: null };
    });
    window.addEventListener('mousemove', (ev) => {
        if (!drag) return;
        const r = layer.getBoundingClientRect();
        const x = ev.clientX - r.left, y = ev.clientY - r.top;
        if (!drag.el && Math.hypot(x - drag.x, y - drag.y) < 6) return;
        if (!drag.el) drag.el = box('pm-sel', { x: 0, y: 0, w: 0, h: 0 });
        drag.el.style.left = Math.min(x, drag.x) + 'px'; drag.el.style.top = Math.min(y, drag.y) + 'px';
        drag.el.style.width = Math.abs(x - drag.x) + 'px'; drag.el.style.height = Math.abs(y - drag.y) + 'px';
    });
    window.addEventListener('mouseup', () => {
        if (!drag) return;
        if (drag.el) {
            const w = parseFloat(drag.el.style.width) / SCALE, h = parseFloat(drag.el.style.height) / SCALE;
            const x = parseFloat(drag.el.style.left) / SCALE, y = parseFloat(drag.el.style.top) / SCALE;
            if (w > 2 && h > 2) {
                const size = Math.max(5, Math.round(h * 0.8 * 2) / 2);
                sel = { page: pageNo, x, y, w, h, base: y + h * 0.78, size, bold: false, serif: false, str: '' };
                openForm();
            } else { draw(); }
        }
        drag = null;
    });

    $('pmAssign').addEventListener('click', () => {
        if (!sel || !$('pmField').value) { fail('Choose which ERP field replaces this value.'); return; }
        $('pmError').classList.add('d-none');
        const size = parseFloat($('pmSize').value) || sel.size;
        if ($('pmField').value === '__text' && !$('pmText').value.trim()) { fail('Enter the text to print.'); return; }
        // Keep the baseline tied to the font size the user settled on.
        const base = sel.base;
        map.push({ page: sel.page, x: sel.x, y: sel.y, w: sel.w, h: sel.h, base, field: $('pmField').value, text: $('pmField').value === '__text' ? $('pmText').value : '', size,
                   align: $('pmAlign').value, bold: $('pmBold').checked, serif: $('pmSerif').checked, sample: sel.str || '' });
        closeForm();
    });
    $('pmCancel').addEventListener('click', closeForm);
    $('pmField').addEventListener('change', () => $('pmText').classList.toggle('d-none', $('pmField').value !== '__text'));

    function list() {
        $('pmCount').textContent = map.length;
        const el = $('pmList'); el.innerHTML = '';
        if (!map.length) { el.innerHTML = '<span class="text-muted">None yet.</span>'; return; }
        map.forEach((m, i) => {
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center border-bottom py-1';
            const lbl = document.createElement('span');
            lbl.innerHTML = '<code></code> <span class="text-muted"></span>';
            lbl.children[0].textContent = m.field === '__blank' ? '(blank)' : (m.field === '__text' ? 'text' : m.field);
            lbl.children[1].textContent = 'p' + m.page + (m.sample ? ' · ' + m.sample.slice(0, 24) : '');
            const rm = document.createElement('button');
            rm.type = 'button'; rm.className = 'btn btn-sm btn-outline-danger py-0'; rm.textContent = '×';
            rm.addEventListener('click', () => { map.splice(i, 1); draw(); });
            row.append(lbl, rm); el.appendChild(row);
        });
    }

    const DEF = <?= json_encode($def) ?>;
    $('pmDetect').addEventListener('click', async () => {
        if (!pdf || !DEF) return;
        if (map.length && !confirm('Replace the current fields with a fresh automatic detection?')) return;
        const r = await PdfDetect.run(pdf, DEF);
        map = r.map; closeForm(); draw();
        const esc = (x) => String(x).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        $('pmFlags').innerHTML = r.flags.map((f) => '<div class="' + (f.blocking ? 'text-warning-emphasis' : 'text-muted') + '">' + (f.blocking ? '⚠ ' : 'ℹ ') + esc(f.field) + (f.label ? ' (' + esc(f.label) + ')' : '') + ' — ' + esc(f.reason) + '</div>').join('');
    });
    $('pmPrev').addEventListener('click', () => { if (pageNo > 1) { closeForm(); show(pageNo - 1); } });
    $('pmNext').addEventListener('click', () => { if (pdf && pageNo < pdf.numPages) { closeForm(); show(pageNo + 1); } });
    $('pmSave').closest('form').addEventListener('submit', () => { $('pmJson').value = JSON.stringify(map); });
})();
</script>
<?= $this->endSection() ?>
