/*
 * ERP row actions
 * ---------------
 * Table "Actions" cells that show several buttons (edit pencil, delete bin, view, download ...) are turned
 * into one three-dots menu. The ORIGINAL controls are moved into the menu, not copied, so links, CSRF
 * forms, confirm() prompts, data-bs-* modal triggers, permissions-driven visibility and the in-page form
 * modal all behave exactly as before. A cell with fewer than two controls is left alone.
 * Opt a cell out with data-erp-actions="off".
 */
(function () {
    'use strict';

    var ICON_LABELS = [
        [/fa-(pen|pencil|edit|pen-to-square)/, 'Edit'], [/fa-(trash|trash-can|xmark|times)/, 'Delete'], [/fa-(eye)/, 'View'],
        [/fa-(download|file-pdf|file-arrow-down)/, 'Download'], [/fa-(print)/, 'Print'], [/fa-(copy|clone)/, 'Duplicate'],
        [/fa-(check|circle-check)/, 'Approve'], [/fa-(ban|circle-xmark)/, 'Reject'], [/fa-(envelope|paper-plane)/, 'Send'],
        [/fa-(key|lock)/, 'Reset access'], [/fa-(rotate|arrows-rotate|sync)/, 'Refresh'], [/fa-(file-lines|file-invoice)/, 'Open']
    ];

    function labelFor(node) {
        var text = (node.getAttribute('title') || node.getAttribute('aria-label') || node.textContent || '').replace(/\s+/g, ' ').trim();
        if (text) { return text; }
        var icon = node.querySelector('i[class*="fa-"]');
        var cls = icon ? icon.className : '';
        for (var i = 0; i < ICON_LABELS.length; i++) { if (ICON_LABELS[i][0].test(cls)) { return ICON_LABELS[i][1]; } }
        return 'Action';
    }

    function toItem(ctrl, danger) {
        var label = labelFor(ctrl);
        var icon = ctrl.querySelector('i[class*="fa-"]');
        var iconHtml = icon ? icon.outerHTML : '';
        ctrl.className = ctrl.className.split(/\s+/).filter(function (c) { return c && !/^(btn|btn-.*|text-start|me-\d|ms-\d|mb-\d|d-inline|d-inline-block|w-100)$/.test(c); }).join(' ');
        ctrl.classList.add('dropdown-item');
        if (danger) { ctrl.classList.add('text-danger'); }
        ctrl.removeAttribute('title');
        ctrl.setAttribute('aria-label', label);
        ctrl.innerHTML = iconHtml + '<span>' + label.replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }) + '</span>';
        return ctrl;
    }

    function isDanger(ctrl) {
        return /btn-(outline-)?danger|text-danger/.test(ctrl.className) || !!ctrl.querySelector('i[class*="fa-trash"], i[class*="fa-xmark"], i[class*="fa-ban"]');
    }

    function controlsIn(cell) {
        var out = [];
        cell.querySelectorAll('a.btn, button.btn, form').forEach(function (n) {
            if (n.closest('form') && n.tagName !== 'FORM' && n.closest('form') !== n) { return; }          // handled via its form
            if (n.tagName === 'FORM' && !n.querySelector('button, input[type=submit]')) { return; }
            if (n.tagName !== 'FORM' && n.closest('.dropdown-menu')) { return; }
            out.push(n);
        });
        return out;
    }

    function convert(cell) {
        if (cell.getAttribute('data-erp-actions') || cell.closest('.erp-modal, .erp-drawer')) { return; }
        var controls = controlsIn(cell);
        if (controls.length < 2) { return; }
        cell.setAttribute('data-erp-actions', 'done');

        var wrap = document.createElement('div');
        wrap.className = 'dropdown erp-row-actions';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'erp-kebab';
        btn.setAttribute('data-bs-toggle', 'dropdown');
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-label', 'Actions');
        btn.innerHTML = '<i class="fas fa-ellipsis-vertical" aria-hidden="true"></i>';
        var menu = document.createElement('ul');
        menu.className = 'dropdown-menu dropdown-menu-end';

        var parents = [];
        controls.forEach(function (c) {
            var li = document.createElement('li');
            var danger, target;
            if (c.tagName === 'FORM') {
                var submit = c.querySelector('button[type=submit], button:not([type]), input[type=submit]');
                danger = submit ? isDanger(submit) : false;
                if (submit && submit.tagName === 'INPUT') { var b = document.createElement('button'); b.type = 'submit'; b.className = submit.className; b.textContent = submit.value; submit.replaceWith(b); submit = b; }
                if (submit) { toItem(submit, danger); }
                c.className = (c.className + ' m-0').replace(/\bd-inline(-block)?\b/g, '').trim();
                target = c;
            } else {
                danger = isDanger(c);
                target = toItem(c, danger);
            }
            if (c.parentElement && c.parentElement !== cell && parents.indexOf(c.parentElement) === -1) { parents.push(c.parentElement); }
            li.appendChild(target);
            menu.appendChild(li);
        });

        // wrappers such as <div class="d-flex gap-1"> that only held the buttons
        parents.forEach(function (p) { if (!p.children.length && !p.textContent.trim()) { p.remove(); } });
        wrap.appendChild(btn);
        wrap.appendChild(menu);
        cell.appendChild(wrap);
    }

    function run(root) {
        (root || document).querySelectorAll('table').forEach(function (table) {
            if (table.closest('.erp-modal, .erp-drawer') || table.hasAttribute('data-erp-actions')) { return; }
            var headers = Array.prototype.slice.call(table.querySelectorAll('thead th'));
            var col = -1;
            headers.forEach(function (th, i) { if (/^actions?$/i.test(th.textContent.trim())) { col = i; } });
            table.querySelectorAll('tbody tr').forEach(function (tr) {
                var cells = tr.children;
                if (col !== -1 && cells[col] && cells[col].tagName === 'TD') { convert(cells[col]); return; }
                // tables without an "Actions" header: the right-aligned last cell holding several buttons
                var last = cells[cells.length - 1];
                if (last && last.tagName === 'TD' && /\btext-end\b/.test(last.className)) { convert(last); }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        run(document);
        if (window.jQuery) { window.jQuery(document).on('draw.dt', function () { run(document); }); }
    });
    window.ErpActions = { run: run };
})();
