/*
 * ERP form layout helpers
 * -----------------------
 * 1. Required-field markers: a red asterisk on every label whose control is required.
 * 2. Sectioned forms: a <form> carrying data-erp-sections is shown as a set of information cards
 *    (Personal Information, Employment Details, ...) stacked vertically inside the form's single card, separated by dividers.
 *    Sections start at any element marked data-section-title="Title" (an empty .erp-section-marker, with an
 *    optional data-section-note) and, with data-erp-sections="headings", at every direct-child <h6>.
 *    Fields are only MOVED into the cards; they stay inside the same <form>, so names, ids, validation,
 *    the page's own scripts and the single POST are exactly what they were.
 */
(function () {
    'use strict';

    function el(tag, cls, text) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (text !== undefined) { n.textContent = text; } return n; }

    function markRequired(root) {
        root.querySelectorAll('form label').forEach(function (label) {
            if (label.classList.contains('form-check-label') || label.classList.contains('erp-req') || /\*/.test(label.textContent)) { return; }
            var control = null;
            if (label.htmlFor) { control = document.getElementById(label.htmlFor); }
            if (!control) {
                var holder = label.parentElement;
                control = holder && holder.querySelector('input:not([type=hidden]), select, textarea');
            }
            if (control && control.required) { label.classList.add('erp-req'); }
        });
    }

    function isCol(node) { return /(^|\s)col(-|\s|$)/.test(node.className || ''); }

    function buildSections(form) {
        var headingMode = form.getAttribute('data-erp-sections') === 'headings';
        var sections = [{ title: form.getAttribute('data-erp-first-section') || '', note: '', units: [] }];
        var actions = [];

        function start(title, note) {
            var cur = sections[sections.length - 1];
            if (cur.units.length || sections.length > 1) { sections.push({ title: title, note: note || '', units: [] }); }
            else { cur.title = title; cur.note = note || ''; }
        }

        Array.prototype.forEach.call(form.children, function (c) {
            var tag = c.tagName;
            if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'TEMPLATE' || (tag === 'INPUT' && c.type === 'hidden')) { return; }
            var isSubmitHolder = c.matches('button[type=submit], input[type=submit]') ||
                (c.querySelector('button[type=submit], input[type=submit]') && !c.querySelector('input:not([type=hidden]):not([type=submit]), select, textarea'));
            if (isSubmitHolder || (c.matches('a.btn') && /^(cancel|back|close)$/i.test(c.textContent.trim()))) { actions.push(c); return; }
            if (c.hasAttribute('data-section-title')) { start(c.getAttribute('data-section-title'), c.getAttribute('data-section-note')); }
            else if (headingMode && tag === 'H6') { start('', ''); }
            if (c.classList.contains('row')) {
                Array.prototype.forEach.call(c.children, function (k) {
                    if (k.hasAttribute('data-section-title')) { start(k.getAttribute('data-section-title'), k.getAttribute('data-section-note')); }
                    sections[sections.length - 1].units.push({ node: k, col: true, from: c });
                });
            } else {
                sections[sections.length - 1].units.push({ node: c, col: isCol(c), from: null });
            }
        });
        return { sections: sections, actions: actions, headingMode: headingMode };
    }

    function layoutSections(form) {
        var built = buildSections(form);
        var sections = built.sections.filter(function (s) {
            return s.units.some(function (u) { return !u.node.classList.contains('erp-section-marker'); });
        });
        if (sections.length < 2) { return; }

        var grid = el('div', 'erp-sections');
        var oldRows = [];
        sections.forEach(function (s) {
            var card = el('section', 'erp-section');
            var body = el('div', 'erp-section-body');
            if (s.title) {
                var head = el('div', 'erp-section-head');
                head.appendChild(el('h5', 'erp-section-title', s.title));
                if (s.note) { head.appendChild(el('div', 'erp-section-note', s.note)); }
                card.appendChild(head);
            }
            var row = null;
            s.units.forEach(function (u) {
                if (u.node.classList.contains('erp-section-marker')) { return; }
                if (u.from && oldRows.indexOf(u.from) === -1) { oldRows.push(u.from); }
                if (u.col) {
                    if (!row) { row = el('div', 'row'); body.appendChild(row); }
                    row.appendChild(u.node);
                } else {
                    row = null;
                    body.appendChild(u.node);
                }
            });
            card.appendChild(body);
            grid.appendChild(card);
        });

        var anchor = null;
        Array.prototype.forEach.call(form.children, function (c) { if (!anchor && c.tagName !== 'INPUT' && c.tagName !== 'SCRIPT') { anchor = c; } });
        form.insertBefore(grid, anchor || null);

        // the original rows are empty shells now
        oldRows.forEach(function (r) { if (!r.querySelector('input, select, textarea, table, label')) { r.remove(); } });
        // markers carry no content
        form.querySelectorAll('.erp-section-marker').forEach(function (m) { m.remove(); });

        var bar = el('div', 'erp-form-actions');
        built.actions.forEach(function (a) { bar.appendChild(a); });
        // keep the original left-to-right order: Cancel before the primary action
        var primary = bar.querySelector('button[type=submit], input[type=submit]');
        if (primary) { bar.appendChild(primary); }
        form.appendChild(bar);

        form.classList.remove('row');
        form.classList.add('erp-sectioned');
    }

    // Every create/edit form lives in ONE card: sized by its content (compact for short forms), centered.
    function sizeFormCard() {
        if (document.body.classList.contains('erp-embed')) { return; }
        var host = document.querySelector('.app-content > .container-fluid');
        if (!host) { return; }
        Array.prototype.forEach.call(host.children, function (card) {
            if (!card.classList || !card.classList.contains('card')) { return; }
            var forms = card.querySelectorAll('form');
            var form = forms.length === 1 ? forms[0] : null;
            if (!form || form.classList.contains('filter-form') || (form.method || '').toLowerCase() !== 'post' || card.querySelector('table.dataTable, .table-responsive > table:not(#itemsTable):not(#poItemsTable)')) { return; }
            var fields = form.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea').length;
            if (fields < 1) { return; }
            card.classList.add('erp-form-card');
            card.classList.toggle('erp-form-card--compact', fields <= 6 && !form.hasAttribute('data-erp-sections'));
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.app-content form[data-erp-sections], main form[data-erp-sections]');
        if (form) { layoutSections(form); }
        sizeFormCard();
        markRequired(document);
        focusRequestedField();
    });

    // ?focus=<field name> (used by "Action Required" links on the employee profile) scrolls to that
    // input and focuses it, so the person lands exactly on the field they have to fill in.
    function focusRequestedField() {
        var name = '';
        try { name = new URLSearchParams(window.location.search).get('focus') || ''; } catch (e) { return; }
        if (!/^[A-Za-z0-9_\[\]]+$/.test(name)) { return; }
        var el = document.querySelector('form [name="' + name + '"]:not([type=hidden]):not([disabled])');
        if (!el) { return; }
        el.scrollIntoView({ block: 'center' });
        try { el.focus({ preventScroll: true }); } catch (e) { /* ignore */ }
        el.classList.add('is-highlighted');
        el.style.boxShadow = '0 0 0 .25rem rgba(214,36,49,.25)';
        setTimeout(function () { el.style.boxShadow = ''; }, 3500);
    }
})();
