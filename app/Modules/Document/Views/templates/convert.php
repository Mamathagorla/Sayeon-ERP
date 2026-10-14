<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('documents/templates/' . $template['id']) ?>" class="btn btn-light btn-sm">Back to template</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-4 mb-3">
        <div class="card">
            <div class="card-body">
                <p class="small text-muted">
                    <strong><?= esc($template['template_name']) ?> v<?= (int) $template['version'] ?></strong> is a fixed-layout PDF, so its header, footer and
                    wording cannot be edited directly. This rebuilds its text as an <strong>editable HTML template</strong> in which every detected field
                    stays a <code>{{placeholder}}</code>. You can then change the logo, company name, address, header, footer, clauses, tables, fonts and spacing for any company.
                </p>
                <ul class="small text-muted ps-3">
                    <li>Carried over: text, bold, alignment, spacing, simple tables, page breaks, header/footer text.</li>
                    <li>Not carried over: logos/images, lines and shapes, exact positions. Add your logo in the header with an <code>&lt;img&gt;</code>.</li>
                    <li>The original PDF template is never changed; the result is a new draft.</li>
                </ul>
                <form method="post" action="<?= site_url('documents/templates/' . $template['id'] . '/editable') ?>" id="cvForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="html" id="cvHtml">
                    <div class="mb-3">
                        <label class="form-label">For company</label>
                        <select name="company_id" class="form-select" required>
                            <?php if (count($companies) !== 1): ?><option value="">Select company</option><?php endif; ?>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ((string) old('company_id', $template['company_id']) === (string) $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">The company that will own the new editable draft.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Template name</label>
                        <input type="text" name="template_name" class="form-control" maxlength="150" value="<?= esc(old('template_name', $template['template_name'] . ' (editable)')) ?>">
                    </div>
                    <button class="btn btn-primary" id="cvSubmit" disabled>Create editable draft</button>
                    <span class="small text-muted ms-2" id="cvState"><span class="spinner-border spinner-border-sm me-1"></span>Reading the PDF…</span>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8 mb-3">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-2">Rebuilt text <span class="text-muted small fw-normal">(approximation — you can refine it on the next screen)</span></h6>
                <iframe id="cvPreview" sandbox="" style="width:100%;height:760px;border:1px solid var(--sy-border, #dee2e6);background:#fff"></iframe>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function () {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    const MAP = <?= json_encode($map) ?>;
    const PDF_URL = <?= json_encode(site_url('documents/templates/' . $template['id'] . '/download?inline=1')) ?>;
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const norm = (s) => s.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim().toLowerCase();

    async function readPages(pdf) {
        const pages = [];
        for (let p = 1; p <= pdf.numPages; p++) {
            const page = await pdf.getPage(p);
            const vp = page.getViewport({ scale: 1 });
            const tc = await page.getTextContent();
            try { await page.getOperatorList(); } catch (e) {}
            const items = [];
            tc.items.forEach((i) => {
                if (!i.str.trim() || !(i.width > 0)) return;
                const t = pdfjsLib.Util.transform(vp.transform, i.transform);
                const fh = Math.hypot(t[2], t[3]);
                let name = '';
                try { name = (page.commonObjs.has(i.fontName) ? page.commonObjs.get(i.fontName).name : '') || ''; } catch (e) {}
                items.push({
                    x: t[4], base: t[5], size: fh, w: i.width, str: i.str,
                    bold: /bold|black|heavy|semibold/i.test(name),
                    serif: name ? (/times|roman|serif|georgia|garamond|cambria|minion|palatino|bookman/i.test(name) && !/sans/i.test(name)) : false,
                });
            });
            pages.push({ n: p, w: vp.width, h: vp.height, items });
        }
        return pages;
    }

    // The PDF's detected fields: every item inside a field box becomes one placeholder item (so data stays bound).
    function applyMap(page) {
        let items = page.items.slice();
        MAP.filter((r) => r.page === page.n).forEach((r) => {
            const inside = items.filter((it) => {
                const cx = it.x + it.w / 2, cy = it.base - 0.3 * it.size;
                return cx >= r.x - 1 && cx <= r.x + r.w + 1 && cy >= r.y - 1 && cy <= r.y + r.h + 1;
            });
            const text = r.field === '__blank' ? '' : (r.field === '__text' ? r.text : '{{' + r.field + '}}');
            if (!inside.length) return;
            items = items.filter((it) => inside.indexOf(it) < 0);
            if (text.trim() === '') return;
            items.push({ x: r.x, base: r.base, size: r.size, w: r.w, str: text, bold: !!r.bold, serif: !!r.serif, field: true });
        });
        return items;
    }

    function toLines(items) {
        const sorted = items.slice().sort((a, b) => a.base - b.base || a.x - b.x);
        const lines = [];
        sorted.forEach((it) => {
            const ln = lines.find((l) => Math.abs(l.base - it.base) < 0.5 * Math.max(l.size, it.size, 6));
            if (ln) { ln.items.push(it); ln.size = Math.max(ln.size, it.size); } else { lines.push({ base: it.base, size: it.size, items: [it] }); }
        });
        lines.forEach((l) => {
            l.items.sort((a, b) => a.x - b.x);
            const segs = [];
            l.items.forEach((it) => {
                const prev = segs[segs.length - 1];
                const gap = prev ? it.x - prev.x1 : 0;
                const html = (it.bold ? '<strong>' : '') + esc(it.str.replace(/\s+$/, '')) + (it.bold ? '</strong>' : '');
                const solo = !!it.field && /^\{\{\w+\}\}$/.test(it.str.trim());   // a bare data field is its own cell (value columns)
                if (prev && !solo && !prev.solo && gap < 1.4 * Math.max(it.size, 6)) {
                    prev.html += (gap > 0.15 * it.size && !/\s$/.test(prev.raw) && !/^\s/.test(it.str) ? ' ' : '') + html;
                    prev.raw = it.str; prev.x1 = it.x + it.w; prev.bold = prev.bold && it.bold;
                } else {
                    segs.push({ x: it.x, x1: it.x + it.w, html, raw: it.str, bold: it.bold, serif: it.serif, solo });
                }
            });
            l.segs = segs; l.x0 = segs[0].x; l.x1 = segs[segs.length - 1].x1;
            l.bold = segs.every((s) => s.bold); l.serif = l.items.filter((i) => i.serif).length > l.items.length / 2;
        });
        return lines.sort((a, b) => a.base - b.base);
    }

    function build(pages) {
        const all = pages.map((p) => ({ p, lines: toLines(applyMap(p)) }));
        const lines = all.flatMap((a) => a.lines);
        if (!lines.length) return '';
        const minL = Math.min(...lines.map((l) => l.x0)), maxR = Math.max(...lines.map((l) => l.x1)), width = Math.max(maxR - minL, 1);
        const sizes = {}; lines.forEach((l) => { const k = Math.round(l.size * 2) / 2; sizes[k] = (sizes[k] || 0) + l.items.length; });
        const baseSize = parseFloat(Object.keys(sizes).sort((a, b) => sizes[b] - sizes[a])[0]);
        const serif = lines.filter((l) => l.serif).length > lines.length / 2;
        const plain = (l) => norm(l.segs.map((s) => s.html).join(' '));

        // header / footer text of the first page; the same text is not repeated on other pages
        const first = all[0];
        const headLines = first.lines.filter((l) => l.base < 0.075 * first.p.h);
        const footLines = first.lines.filter((l) => l.base > 0.93 * first.p.h);
        const seenHead = new Set(headLines.map(plain)), seenFoot = new Set(footLines.map(plain));
        const bodyLinesOf = (a, idx) => a.lines.filter((l) => {
            if (idx === 0) return !headLines.includes(l) && !footLines.includes(l);
            return !((l.base < 0.075 * a.p.h && seenHead.has(plain(l))) || (l.base > 0.93 * a.p.h && seenFoot.has(plain(l))));
        });

        const align = (l) => {
            if (l.segs.length !== 1) return 'left';
            const c = (l.x0 + l.x1) / 2;
            if (Math.abs(c - (minL + maxR) / 2) < 8 && l.x0 - minL > 18) return 'center';
            if (l.x0 - minL > 0.5 * width) return 'right';
            return 'left';
        };
        const pStyle = (l, extra) => {
            const st = [];
            if (Math.abs(l.size - baseSize) > 0.4) st.push('font-size:' + (Math.round(l.size * 2) / 2) + 'pt');
            const a = align(l); if (a !== 'left') st.push('text-align:' + a);
            if (a === 'left' && l.x0 - minL > 8) st.push('margin-left:' + Math.round(l.x0 - minL) + 'pt');
            if (a === 'right' && maxR - l.x1 > 8) st.push('margin-right:' + Math.round(maxR - l.x1) + 'pt');
            return st.concat(extra || []).join(';');
        };
        const lineHtml = (l) => l.segs.map((s) => s.html).join(' ');

        const renderBlock = (ls) => {
            const out = [];
            let i = 0, prevBase = null, prevSize = baseSize;
            while (i < ls.length) {
                const l = ls[i];
                const gapTop = prevBase === null ? 0 : Math.max(0, l.base - prevBase - 1.3 * Math.max(l.size, prevSize));
                const mt = Math.min(40, Math.round(gapTop));
                if (l.segs.length > 1) {
                    // consecutive multi-column lines (and one-cell lines under the first column) form one table
                    const rows = [l]; let j = i + 1;
                    while (j < ls.length && ls[j].base - ls[j - 1].base < 2.2 * Math.max(ls[j].size, 6) &&
                        (ls[j].segs.length > 1 || Math.abs(ls[j].x0 - l.x0) < 4)) { rows.push(ls[j]); j++; }
                    const cols = Math.max(...rows.map((r) => r.segs.length));
                    out.push('<table style="width:100%;border-collapse:collapse' + (mt ? ';margin-top:' + mt + 'pt' : '') + '">' + rows.map((r) =>
                        '<tr>' + r.segs.map((s, k) => '<td' + (k === r.segs.length - 1 && r.segs.length < cols ? ' colspan="' + (cols - k) + '"' : '') + ' style="padding:1pt 4pt;vertical-align:top;font-size:' + (Math.round(r.size * 2) / 2) + 'pt' + (k === r.segs.length - 1 && k > 0 && maxR - s.x1 < 8 ? ';text-align:right' : '') + '">' + s.html + '</td>').join('') + '</tr>').join('') + '</table>');
                    prevBase = rows[rows.length - 1].base; prevSize = rows[rows.length - 1].size; i = j; continue;
                }
                // paragraph: following lines of the same block continue it
                let html = lineHtml(l), cur = l, j = i + 1;
                while (j < ls.length) {
                    const n = ls[j], gap = n.base - cur.base;
                    if (n.segs.length === 1 && align(n) === 'left' && align(cur) === 'left' && Math.abs(n.x0 - cur.x0) < 4 && Math.abs(n.size - cur.size) < 0.8 &&
                        n.bold === cur.bold && gap > 0.85 * cur.size && gap < 1.75 * cur.size && cur.x1 > maxR - 0.12 * width) {
                        html += ' ' + lineHtml(n); cur = n; j++;
                    } else break;
                }
                out.push('<p style="' + pStyle(l, mt ? ['margin-top:' + mt + 'pt'] : []) + '">' + html + '</p>');
                prevBase = cur.base; prevSize = cur.size; i = j;
            }
            return out.join('\n');
        };

        const header = renderBlock(headLines), footer = renderBlock(footLines);
        const body = all.map((a, idx) => (idx > 0 ? '<div class="pg"></div>\n' : '') + renderBlock(bodyLinesOf(a, idx))).join('\n');
        const font = serif ? "'Times New Roman', Times, serif" : 'Arial, Helvetica, sans-serif';
        return '<!doctype html>\n<html><head><meta charset="utf-8"><style>\n@page { margin: 14mm 16mm; }\nbody { font-family: ' + font + '; font-size: ' + baseSize + 'pt; line-height: 1.45; color: #000; }\np { margin: 0 0 4pt; }\n.pg { page-break-before: always; }\n</style></head><body>\n' +
            '<!--tpl:header-->' + header + '<!--/tpl:header-->\n<!--tpl:body-->' + body + '<!--/tpl:body-->\n<!--tpl:footer-->' + footer + '<!--/tpl:footer-->\n</body></html>';
    }

    (async function () {
        const state = document.getElementById('cvState'), btn = document.getElementById('cvSubmit');
        try {
            const pdf = await pdfjsLib.getDocument({ url: PDF_URL, withCredentials: true }).promise;
            const html = build(await readPages(pdf));
            if (!html) { state.textContent = 'No text could be read from this PDF (scanned image?).'; return; }
            document.getElementById('cvHtml').value = html;
            document.getElementById('cvPreview').srcdoc = html;
            state.textContent = 'Ready.'; btn.disabled = false;
        } catch (e) {
            state.textContent = 'Could not read the PDF: ' + e.message;
        }
    })();
})();
</script>
<?= $this->endSection() ?>
