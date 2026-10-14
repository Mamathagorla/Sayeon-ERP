<?php
/**
 * Automatic field detection for PDF templates (runs in the browser with
 * pdf.js at upload time). It reads the PDF's text with positions and finds,
 * without anyone mapping by hand:
 *   1. literal {{placeholders}} typed into the PDF
 *   2. "Label  value" pairs for labels the ERP knows (Employee Name, Net Amount, D.O.J. ...),
 *      accepted only when the neighbour has the right shape (date / amount / id / text)
 *   3. well-known sentence patterns ("Dear Mr. X", "certify that X has", "from D1 to D2" ...)
 *   4. the same sample value repeated elsewhere in the document
 * Anything recognisable but not confidently resolved is returned as a flag
 * (blocking = the template must be reviewed before use). Unknown text is
 * never touched.
 */
?>
<script>
window.PdfDetect = (function () {
    const MONTHS = 'January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec';
    const DATE_SRC = '(?:\\d{1,2}(?:st|nd|rd|th)?[\\/\\-. ]\\s*(?:\\d{1,2}|[A-Za-z]{3,9})\\.?,?[\\/\\-. ]\\s*\\d{2,4}|[A-Za-z]{3,9}\\.? \\d{1,2}(?:st|nd|rd|th)?,? \\d{4}|\\d{4}-\\d{2}-\\d{2})';
    const DATE_RX = new RegExp(DATE_SRC);
    const AMOUNT_RX = /^[\s₹$]*(?:Rs\.?|INR)?\s*-?[\d,]+(?:\.\d+)?\s*(?:\/-|Lakhs?|Cr)?$/i;
    const NAME_SRC = '\\p{Lu}[\\p{L}.\'-]*(?:\\s+\\p{Lu}[\\p{L}.\'-]*){0,4}';

    const norm = (s) => (s || '').toLowerCase().replace(/ /g, ' ').replace(/[.#]/g, '').replace(/[:：]+\s*$/, '').replace(/\s+/g, ' ').trim();
    const isSep = (s) => /^[\s:：\-–—|]+$/.test(s);

    async function extract(pdf) {
        const items = [];
        for (let p = 1; p <= pdf.numPages; p++) {
            const page = await pdf.getPage(p);
            const vp = page.getViewport({ scale: 1 });
            const tc = await page.getTextContent();
            try { await page.getOperatorList(); } catch (e) {}
            tc.items.forEach((i) => {
                if (!i.str.trim() || !(i.width > 0)) return;
                const t = pdfjsLib.Util.transform(vp.transform, i.transform);
                const fh = Math.hypot(t[2], t[3]);
                let name = '';
                try { name = (page.commonObjs.has(i.fontName) ? page.commonObjs.get(i.fontName).name : '') || ''; } catch (e) {}
                const fam = ((tc.styles[i.fontName] || {}).fontFamily || '') + ' ' + name;
                items.push({
                    id: items.length, page: p, ph: vp.height, str: i.str,
                    x: t[4], y: t[5] - 0.85 * fh, w: i.width, h: 1.1 * fh, base: t[5], size: fh,
                    bold: /bold|black|heavy|semibold/i.test(name),
                    // Trust the embedded font's real name; pdf.js's generic "sans-serif" fallback family says nothing about it.
                    serif: name
                        ? (/times|roman|serif|georgia|garamond|cambria|minion|antiqua|palatino|bookman|baskerville|didot|century/i.test(name) && !/sans/i.test(name))
                        : (/serif/i.test(fam) && !/sans/i.test(fam)),
                });
            });
        }
        return items;
    }

    async function run(pdf, def) {
        const items = await extract(pdf);
        const keys = new Set(def.keys);
        const regions = [];                 // output map
        const used = new Set();             // item ids already claimed
        const samples = {};                 // key -> sample strings (for propagation)
        const labelHit = {};                // key -> label text seen
        const notes = [];

        // label text -> ordered keys (vocabulary order = priority)
        const labelKeys = {};
        Object.keys(def.labels).forEach((k) => def.labels[k].labels.forEach((l) => { (labelKeys[norm(l)] = labelKeys[norm(l)] || []).push(k); }));
        const isLabel = (s) => !!labelKeys[norm(s)];

        const kindOk = (kind, str) => {
            const s = str.trim();
            if (!s || isSep(s) || isLabel(s)) return false;
            if (kind === 'date') return s.length <= 30 && DATE_RX.test(s);
            if (kind === 'amount') return AMOUNT_RX.test(s);
            if (kind === 'id') return s.length <= 30 && /^[A-Za-z0-9][A-Za-z0-9\-\/_.]*$/.test(s) && /\d/.test(s);
            return /\p{L}/u.test(s) && s.length <= 90;
        };
        const sameLine = (a, b) => a.page === b.page && Math.abs(a.base - b.base) < 0.55 * Math.max(a.size, 6);
        const fieldRegion = (it, field, kind) => ({
            page: it.page, x: it.x, y: it.y, w: it.w, h: it.h, base: it.base, size: it.size, field, text: '',
            align: kind === 'amount' ? 'R' : 'L', bold: it.bold, serif: it.serif, sample: it.str.slice(0, 60),
        });
        const textRegion = (it, text) => ({
            page: it.page, x: it.x, y: it.y, w: it.w, h: it.h, base: it.base, size: it.size, field: '__text', text,
            align: 'L', bold: it.bold, serif: it.serif, sample: it.str.slice(0, 60),
        });
        const claim = (it, region, key, sample) => {
            used.add(it.id); regions.push(region);
            if (key) { if (sample && def.labels[key] && def.labels[key].kind !== 'date' && def.labels[key].kind !== 'amount' && sample.trim().length >= 4) { (samples[key] = samples[key] || []).push(sample.trim()); } }
        };

        // ---- 1. literal {{placeholders}} -------------------------------------------------
        items.forEach((it) => {
            const m = it.str.match(/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/g);
            if (!m) return;
            let ok = true;
            m.forEach((tok) => { const k = tok.replace(/[{}\s]/g, ''); if (!keys.has(k)) { ok = false; notes.push({ field: k, label: tok, reason: 'This placeholder is not available for this document type.', blocking: true }); } });
            if (!ok) { used.add(it.id); return; }
            const whole = it.str.trim().match(/^\{\{\s*([A-Za-z0-9_]+)\s*\}\}$/);
            claim(it, whole ? fieldRegion(it, whole[1], (def.labels[whole[1]] || {}).kind) : textRegion(it, it.str), null);
        });

        // ---- 2. label -> value --------------------------------------------------------------
        items.forEach((lab) => {
            if (used.has(lab.id)) return;
            // 2a. "Label : value" inside one item
            const ci = lab.str.indexOf(':');
            if (ci > 0 && ci < lab.str.length - 1) {
                const l = norm(lab.str.slice(0, ci)); const rest = lab.str.slice(ci + 1);
                const ks = (labelKeys[l] || []);
                for (const k of ks) {
                    labelHit[k] = labelHit[k] || lab.str.slice(0, ci).trim();
                    if (kindOk(def.labels[k].kind, rest)) {
                        const start = ci + 1 + (rest.length - rest.trimStart().length);
                        const end = start + rest.trim().length;
                        claim(lab, textRegion(lab, lab.str.slice(0, start) + '{{' + k + '}}' + lab.str.slice(end)), k, rest);
                        return;
                    }
                }
            }
            const ks = labelKeys[norm(lab.str)];
            if (!ks) return;
            ks.forEach((k) => { labelHit[k] = labelHit[k] || lab.str.trim(); });

            // 2b. value to the right on the same line, 2c. value directly below
            const right = items.filter((b) => b.id !== lab.id && sameLine(lab, b) && b.x >= lab.x + lab.w - 2 && !isSep(b.str)).sort((a, b) => a.x - b.x)[0];
            const below = items.filter((b) => b.page === lab.page && b.base > lab.base + 0.4 * lab.size && b.base - lab.base < 2.4 * lab.size && b.x < lab.x + lab.w + 10 && b.x + b.w > lab.x - 10 && !isSep(b.str)).sort((a, b) => a.base - b.base)[0];
            for (const k of ks) {
                const kind = def.labels[k].kind;
                for (const cand of [right, below]) {
                    if (cand && !used.has(cand.id) && kindOk(kind, cand.str)) {
                        claim(cand, fieldRegion(cand, k, kind), k, cand.str);
                        return;
                    }
                }
            }
        });

        // ---- 3. sentence patterns ---------------------------------------------------------------
        const reprint = (it, text, key, sample) => { claim(it, textRegion(it, text), key, sample); };
        const TITLE_RX = /^(?:Mr|Ms|Mrs|Miss|Dr|Shri|Smt|Sir|Madam)\.?$/i;
        const readOrder = items.slice().sort((a, b) => a.page - b.page || a.base - b.base || a.x - b.x);
        const nextOf = (it) => {
            const i = readOrder.findIndex((b) => b.id === it.id);
            for (let j = i + 1; j < readOrder.length && j < i + 4; j++) {
                const b = readOrder[j];
                if (b.page === it.page && b.base - it.base < 2.6 * Math.max(it.size, 6)) return b;
            }
            return null;
        };
        const startsWithDate = (b) => { const m = b && b.str.match(new RegExp('^\\s*(' + DATE_SRC + ')')); return m ? m[1] : null; };
        const dates = (k) => keys.has(k);
        const nameRx = (tail) => new RegExp(tail, 'u');

        items.forEach((it) => {
            if (used.has(it.id)) return;
            const s = it.str;
            let text = s, changed = false, m;

            // "Dear Mr." with the name in the next text run on the same line
            if (keys.has('employee_name') && /^\s*Dear\s+(?:Mr|Ms|Mrs|Miss|Dr|Shri|Smt)?\.?\s*$/i.test(s)) {
                const nx = items.filter((b) => b.id !== it.id && !used.has(b.id) && sameLine(it, b) && b.x >= it.x + it.w - 2).sort((a, b) => a.x - b.x)[0];
                if (nx && nameRx('^\\s*' + NAME_SRC + '\\s*[,:]?\\s*$').test(nx.str) && !TITLE_RX.test(nx.str.trim())) {
                    // one merged greeting; the fixed title is dropped and the separate name run is cleared
                    const tail = (nx.str.match(/[,:]\s*$/) || [''])[0];
                    used.add(it.id); regions.push(Object.assign(textRegion(it, 'Dear {{salutation_name}}' + tail), { w: nx.x + nx.w - it.x }));
                    used.add(nx.id); regions.push(Object.assign(fieldRegion(nx, '__blank', 'text'), { sample: nx.str.slice(0, 60) }));
                    (samples.employee_name = samples.employee_name || []).push(nx.str.replace(/[,:\s]+$/, ''));
                    notes.push({ field: 'employee_name', label: s.trim(), reason: 'The fixed title (Mr./Ms.) was removed: the greeting uses the stored name, plus a title only if one is stored.', blocking: false });
                    return;
                }
            }
            if (keys.has('employee_name')) {
                m = s.match(nameRx('^(\\s*Dear\\s+(?:Mr|Ms|Mrs|Miss|Dr|Shri|Smt)?\\.?\\s*)(' + NAME_SRC + ')(\\s*[,:]?\\s*)$'));
                if (!m) m = s.match(nameRx('(certify that\\s+(?:(?:Mr|Ms|Mrs|Miss|Dr)\\.?\\s+)?)(' + NAME_SRC + ')(\\s+(?:has|have|had|was|is|worked|successfully)\\b.*)$'));
                if (m && !TITLE_RX.test(m[2].trim()) && !/^(Sir|Madam|All|Team|Candidate|Employee)$/i.test(m[2].trim())) {
                    // drop a fixed title ("Mr."/"Ms.") in front of the name: salutation_name = stored title (if any) + name
                    const lead = m[1].replace(/\s*(?:Mr|Ms|Mrs|Miss|Dr|Shri|Smt)\.?\s*$/i, '').replace(/\s*$/, ' ');
                    text = text.replace(m[1] + m[2], lead + '{{salutation_name}}'); changed = true;
                    (samples.employee_name = samples.employee_name || []).push(m[2].trim());
                }
            }
            if (keys.has('period')) {
                m = text.match(new RegExp('(for\\s+(?:the\\s+)?(?:month\\s+of\\s+)?)((?:' + MONTHS + ')\\.?\\s*[-–,]?\\s*\\d{4})', 'i'));
                if (m) { text = text.replace(m[2], '{{period}}'); changed = true; }
            }
            if (keys.has('designation') && (m = s.match(nameRx('(designation\\s+(?:will be|is|as)\\s+(?:a|an|the)?\\s*)(\\p{Lu}[\\p{L}&\\/ -]{2,60}?)\\s*$')))) {
                text = text.replace(m[2], '{{designation}}'); changed = true;
                (samples.designation = samples.designation || []).push(m[2].trim());
            }
            // dates inside one line
            if ((m = s.match(new RegExp('from\\s+(' + DATE_SRC + ')\\s+to\\s+(' + DATE_SRC + ')', 'i')))) {
                const pair = dates('internship_start_date') ? ['internship_start_date', 'internship_end_date'] : (dates('joining_date') && dates('last_working_day') ? ['joining_date', 'last_working_day'] : null);
                if (pair) { text = text.replace(m[1], '{{' + pair[0] + '}}').replace(m[2], '{{' + pair[1] + '}}'); changed = true; }
            } else if ((m = s.match(new RegExp('from\\s+(' + DATE_SRC + ')\\s+to\\s*$', 'i')))) {
                // "... from 21/8/25 to" + next line "21/11/25."
                const nx = nextOf(it), d2 = startsWithDate(nx);
                const pair = dates('internship_start_date') ? ['internship_start_date', 'internship_end_date'] : (dates('joining_date') && dates('last_working_day') ? ['joining_date', 'last_working_day'] : null);
                if (pair && d2 && !used.has(nx.id)) {
                    text = text.replace(m[1], '{{' + pair[0] + '}}'); changed = true;
                    claim(nx, textRegion(nx, nx.str.replace(d2, '{{' + pair[1] + '}}')), null);
                }
            } else if (dates('joining_date') && (m = s.match(new RegExp('(?:commence|start date|join(?:ing)?|begin)[^.\\d]{0,40}?(' + DATE_SRC + ')', 'i')))) {
                text = text.replace(m[1], '{{joining_date}}'); changed = true;
            } else if (dates('joining_date') && /(?:commence|start|join(?:ing)?|begin|report)[^.\d]{0,50}$/i.test(s)) {
                // "... join us on or" + next line "October 15th, 2022. This offer ..."
                const nx = nextOf(it), d2 = startsWithDate(nx);
                if (d2 && !used.has(nx.id)) { claim(nx, textRegion(nx, nx.str.replace(d2, '{{joining_date}}')), null); }
            }
            // annual salary
            if (keys.has('annual_salary') && (m = s.match(/annual\s+(?:salary|ctc|package)[^\d₹R]{0,70}?((?:Rs\.?|INR|₹)\s*[\d.,]+(?:\s*Lakhs?)?)/i))) {
                text = text.replace(m[1], '{{annual_salary}}'); changed = true; labelHit.salary_sentence = true;
            } else if (keys.has('annual_salary') && /annual\s+(?:salary|ctc|package)(?:\s+of)?\s*:?\s*$/i.test(s)) {
                const nx = nextOf(it);
                if (nx && !used.has(nx.id) && /^\s*(?:Rs\.?|INR|₹)\s*[\d.,]+\s*(?:Lakhs?|Cr)?\s*$/i.test(nx.str)) {
                    claim(nx, fieldRegion(nx, 'annual_salary', 'text'), null); labelHit.salary_sentence = true;
                }
            }
            if (keys.has('annual_salary_in_words') && /\([^)]*\bOnly\)/i.test(text)) {
                text = text.replace(/\([^)]*\bOnly\)/i, '({{annual_salary_in_words}})'); changed = true; labelHit.salary_words_done = true;
            }
            if (changed) reprint(it, text, null);
        });

        // letter date: a date standing alone near the top of the first page
        if (keys.has('current_date') && !regions.some((r) => r.field === 'current_date' || /\{\{current_date\}\}/.test(r.text))) {
            const top = items.find((b) => !used.has(b.id) && b.page === 1 && b.base < 0.3 * (b.ph || 842) && new RegExp('^\\s*' + DATE_SRC + '\\s*[.,]?\\s*$').test(b.str));
            if (top) { claim(top, fieldRegion(top, 'current_date', 'date'), null); notes.push({ field: 'current_date', label: top.str.trim(), reason: 'Assumed this is the letter date.', blocking: false }); }
        }

        // ---- 4. repeated sample values (exact same text elsewhere) ---------------------------------
        const flat = [];
        Object.keys(samples).forEach((k) => samples[k].forEach((v) => flat.push([k, v])));
        flat.sort((a, b) => b[1].length - a[1].length);
        items.forEach((it) => {
            if (used.has(it.id)) return;
            let text = it.str, changed = false;
            flat.forEach(([k, v]) => {
                const esc = v.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                const title = k === 'employee_name' ? '(?:\\b(?:Mr|Ms|Mrs|Miss|Dr|Shri|Smt)\\.?\\s+)?' : '';
                const re = new RegExp('(^|[^\\p{L}\\d])' + title + esc + '(?![\\p{L}\\d])', 'gu');
                if (re.test(text)) { text = text.replace(re, '$1{{' + (k === 'employee_name' ? 'salutation_name' : k) + '}}'); changed = true; }
            });
            if (changed) reprint(it, text, null);
        });

        // ---- 4b. a fixed title ("Mr."/"Ms.") standing alone just left of a name we replace -----------------
        regions.filter((r) => r.field === 'employee_name' || /\{\{(?:salutation_name|employee_name)\}\}/.test(r.text)).forEach((r) => {
            const t = items.find((b) => !used.has(b.id) && b.page === r.page && /^(?:Mr|Ms|Mrs|Miss|Dr|Shri|Smt)\.?$/i.test(b.str.trim()) &&
                Math.abs(b.base - r.base) < 0.55 * Math.max(r.size, 6) && b.x < r.x && r.x - (b.x + b.w) < 14);
            if (!t) return;
            used.add(t.id);
            regions.push({ page: t.page, x: t.x, y: t.y, w: t.w, h: t.h, base: t.base, size: t.size, field: '__blank', text: '', align: 'L', bold: t.bold, serif: t.serif, sample: t.str });
            if (r.field === 'employee_name') { r.field = '__text'; r.text = '{{salutation_name}}'; }
            r.text = r.text.replace(/\{\{employee_name\}\}/g, '{{salutation_name}}');
            r.w = r.x + r.w - t.x; r.x = t.x;
            notes.push({ field: 'employee_name', label: t.str.trim(), reason: 'A fixed title next to the name was removed; a title is printed only if one is stored.', blocking: false });
        });

        // ---- 4c. text that follows a rewritten sentence on the same line moves with it ----------------------
        // A replacement value is rarely as wide as the sample it replaces. Text printed after it on the same line
        // ("... will be employee.   you will be paid ...") would stay where it was and leave a gap (or collide when
        // the value is longer), so the rest of the line becomes part of the same pattern and is reprinted as one run.
        regions.filter((r) => r.field === '__text' && /\{\{\w+\}\}/.test(r.text)).forEach((r) => {
            let end = r.x + r.w, text = r.text, top = r.y, bottom = r.y + r.h, any = false;
            for (let guard = 0; guard < 12; guard++) {
                const nx = items.filter((b) => !used.has(b.id) && b.page === r.page && sameLine(r, b) && b.x >= end - 2 && b.x - end < 2.5 * Math.max(r.size, 6)).sort((a, b) => a.x - b.x)[0];
                if (!nx) break;
                used.add(nx.id); any = true;
                text = text.replace(/\s+$/, '') + ' ' + nx.str.trim();
                end = nx.x + nx.w; top = Math.min(top, nx.y); bottom = Math.max(bottom, nx.y + nx.h);
            }
            if (any) { r.text = text; r.w = Math.round((end - r.x) * 10) / 10; r.y = top; r.h = bottom - top; }
        });

        // ---- 5. stale amounts of rows the ERP has no data for (payslip-style tables) -------------------
        if (def.blankUnknownAmounts) {
            const cols = regions.filter((r) => r.align === 'R' && r.field !== '__text').map((r) => ({ page: r.page, right: r.x + r.w }));
            items.forEach((it) => {
                if (used.has(it.id) || !AMOUNT_RX.test(it.str.trim())) return;
                if (cols.some((c) => c.page === it.page && Math.abs(c.right - (it.x + it.w)) < 6)) {
                    regions.push({ page: it.page, x: it.x, y: it.y, w: it.w, h: it.h, base: it.base, size: it.size, field: '__blank', text: '', align: 'R', bold: it.bold, serif: it.serif, sample: it.str.slice(0, 60) });
                    used.add(it.id);
                    const left = items.filter((b) => sameLine(it, b) && b.x < it.x).sort((a, b) => b.x - a.x)[0];
                    notes.push({ field: '__blank', label: left ? left.str.trim() : '', reason: 'Sample amount cleared (' + it.str.trim() + ') — the ERP has no matching figure.', blocking: false });
                }
            });
        }

        // ---- fixed gendered wording left in the template (would be wrong for other people) -------------------
        {
            const words = new Set();
            const scan = (str) => { (str.replace(/\{\{[^}]*\}\}/g, ' ').match(/\b(?:he|she|him|his|hers?|himself|herself|mr|mrs|ms|miss|mister)\b/gi) || []).forEach((w) => words.add(w.toLowerCase())); };
            items.filter((i) => !used.has(i.id)).forEach((i) => scan(i.str));
            regions.filter((r) => r.field === '__text').forEach((r) => scan(r.text));
            if (words.size) {
                notes.push({ field: 'wording', label: Array.from(words).join(', '), reason: 'The template prints fixed gendered wording/titles that will not change per person. Reword it neutrally, or accept if intended.', blocking: true });
            }
        }

        // ---- flags -----------------------------------------------------------------------------------------
        const resolved = new Set(regions.map((r) => r.field));
        const flags = notes.slice();
        regions.filter((r) => r.field === '__text').forEach((r) => { (r.text.match(/\{\{(\w+)\}\}/g) || []).forEach((t) => resolved.add(t.replace(/[{}]/g, ''))); });
        if (resolved.has('salutation_name') || resolved.has('greeting')) resolved.add('employee_name'); // the person's name is printed via the greeting
        Object.keys(labelHit).forEach((k) => {
            if (k === 'salary_sentence' || k === 'salary_words_done' || resolved.has(k)) return;
            flags.push({ field: k, label: labelHit[k], reason: 'Label found, but the value next to it could not be identified confidently.', blocking: true });
        });
        if (labelHit.salary_sentence && !labelHit.salary_words_done && items.some((i) => /\(.*only\)?\s*$/i.test(i.str.trim()) || /^\s*\(.*lakh/i.test(i.str))) {
            flags.push({ field: 'annual_salary', label: 'amount in words', reason: 'The salary is also written in words in this template; that text will not change automatically.', blocking: true });
        }
        // Dates left behind: a date-like fragment that was neither replaced nor part of a
        // mapped field could be a stale sample value, so make an admin look at it.
        const LEFT_RX = new RegExp(DATE_SRC + '|\b\d{1,2}(?:st|nd|rd|th),?\s+\d{4}\b');
        const mappedDates = regions.some((r) => /date|day|\{\{(?:joining|current|effective|internship)/.test(r.field + r.text));
        if (mappedDates) {
            const seen = new Set();
            const scan = (str, where) => {
                const rest = str.replace(/\{\{\w+\}\}/g, '');
                const m = rest.match(LEFT_RX);
                if (m && !seen.has(m[0])) { seen.add(m[0]); flags.push({ field: 'date', label: m[0], reason: 'Date-like text "' + m[0] + '" remains in ' + where + ' and would not change. Review it or accept it if it is intentional.', blocking: true }); }
            };
            regions.filter((r) => r.field === '__text').forEach((r) => scan(r.text, 'a line that is being updated'));
            items.filter((i) => !used.has(i.id)).forEach((i) => scan(i.str, 'the template'));
        }
        if (def.hasLineItems) {
            flags.push({ field: 'items', label: 'line items', reason: 'PDF templates cannot repeat line-item rows. Use an HTML or Word template, or accept to keep the table as designed.', blocking: true });
        }
        def.required.forEach((k) => {
            if (!resolved.has(k) && !flags.some((f) => f.field === k)) {
                flags.push({ field: k, label: '', reason: 'Not found in this template (fine if the document does not show it).', blocking: false });
            }
        });

        // Room each replacement has before it would run into neighbouring text on its line
        // (to the right for left-aligned values, to the left for right-aligned amounts).
        regions.forEach((r) => {
            if (r.field === '__blank') return;
            const hit = items.filter((b) => b.page === r.page && Math.abs(b.base - r.base) < 0.55 * Math.max(r.size, 6) &&
                (r.align === 'R' ? (b.x + b.w <= r.x + 1) : (b.x >= r.x + r.w - 1)));
            if (!hit.length) return;
            const room = r.align === 'R'
                ? (r.x + r.w) - Math.max(...hit.map((b) => b.x + b.w)) - 4
                : Math.min(...hit.map((b) => b.x)) - 4 - r.x;
            if (room > r.w) r.maxw = Math.round(room * 10) / 10;
        });

        const matched = regions.map((r) => ({ field: r.field === '__text' ? r.text : r.field, sample: r.sample, auto: true }));

        return { map: regions, flags, matched };
    }

    return { run, extract };
})();
</script>
