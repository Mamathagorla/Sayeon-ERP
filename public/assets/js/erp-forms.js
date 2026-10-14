/*
 * ERP in-page forms
 * -----------------
 * Opens existing create / edit pages inside a right-side drawer (long forms:
 * Employee, Expense, Purchase Order, ...) or a centered modal (short forms:
 * Department, Task) instead of navigating to them. The page is loaded in a same-origin frame ("?embed=1"
 * hides the app chrome), so the module's own controller, validation, CSRF
 * token, file uploads, old() repopulation and page scripts all run unchanged.
 * This file only adds the shell around it: sticky Save/Cancel footer, loading
 * state, errors placed beside their inputs, duplicate-submit guard,
 * unsaved-changes guard, toasts, and "stay on the page" after saving.
 *
 * Anything that cannot be shown this way falls back to normal navigation.
 * Opt a link out with data-erp-form="off"; opt extra links in with
 * data-erp-form="on". Standard URLs (…/create and …/{id}/edit) are picked up
 * automatically. Drawer is the default; MODAL_PATTERNS lists the short forms
 * and a link can force either with data-erp-form-size="modal|drawer".
 */
(function () {
    'use strict';

    // Never run inside the drawer's own frame (no nested drawers).
    if (window.self !== window.top) { return; }

    var TITLE_SUFFIX = / · Sanvima Operations$/;
    var URL_PATTERNS = [
        /\/create\/?$/,
        /\/\d+\/edit\/?$/,
        /\/hr\/attendance\/(mark|bulk-upload)\/?$/,
        /\/hr\/payroll\/structure\/\d+\/?$/,
        /\/documents\/templates\/clone\/?$/
    ];
    // Large forms with a lot to fill in open on their own page (with breadcrumbs and steps), never in the modal.
    var PAGE_PATTERNS = [
        /\/(companies|projects|meetings|recurring-meetings|recurring-expenses|compliance|customers|websites|campaigns|purchase-orders)\/(create|\d+\/edit)\/?$/,
        /\/hr\/(employees|onboarding)\/(create|\d+\/edit)\/?$/,
        /\/accounting\/(invoices|bills)\/(create|\d+\/edit)\/?$/
    ];
    // Short forms open in a centered modal; everything else in the right-side drawer.
    var MODAL_PATTERNS = [
        /^\/departments\/(create|\d+\/edit)\/?$/,
        /^\/tasks\/(create|\d+\/edit)\/?$/
    ];
    var TOAST_KEY = 'erp-toast';
    var SCROLL_KEY = 'erp-scroll';

    // One shell per mode, built on first use; these point at the one currently open.
    var shells = {};
    var drawerEl, drawer, frame, titleEl, footerEl, loadingEl;
    var state = null;

    // ---------------------------------------------------------------- helpers

    function el(tag, attrs, html) {
        var n = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
        if (html !== undefined) { n.innerHTML = html; }
        return n;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function withEmbed(href) {
        var u = new URL(href, location.href);
        u.searchParams.set('embed', '1');
        return u.toString();
    }

    function isFormLink(a, ev) {
        if (!a || !a.getAttribute('href')) { return false; }
        var flag = a.getAttribute('data-erp-form');
        if (flag === 'off') { return false; }
        if (a.hasAttribute('download') || (a.target && a.target !== '_self')) { return false; }
        if (ev && (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey)) { return false; }
        if (a.closest('.erp-drawer, .erp-modal')) { return false; }
        var u;
        try { u = new URL(a.href, location.href); } catch (e) { return false; }
        if (u.origin !== location.origin) { return false; }
        if (flag === 'on') { return true; }
        if (PAGE_PATTERNS.some(function (rx) { return rx.test(u.pathname); })) { return false; }
        return URL_PATTERNS.some(function (rx) { return rx.test(u.pathname); });
    }

    // Short forms get a compact modal sized to their content; everything else a wide modal with a fixed, scrolling body.
    function isShortForm(href) {
        try {
            var path = new URL(href, location.href).pathname.replace(/^.*?(?=\/(departments|tasks)\/)/, '');
            return MODAL_PATTERNS.some(function (rx) { return rx.test(path); });
        } catch (e) { return false; }
    }

    function modeFor(href, opener) {
        var size = opener && opener.getAttribute('data-erp-form-size');
        if (size === 'modal' || size === 'drawer') { return size; }
        return 'modal';
    }

    // ----------------------------------------------------------------- toasts

    function toast(message, kind) {
        if (!message) { return; }
        var box = document.getElementById('erpToasts');
        if (!box) {
            box = el('div', { id: 'erpToasts', class: 'toast-container position-fixed top-0 end-0 p-3', style: 'z-index:2100' });
            document.body.appendChild(box);
        }
        var cls = kind === 'danger' ? 'text-bg-danger' : 'text-bg-success';
        var icon = kind === 'danger' ? 'fa-circle-exclamation' : 'fa-circle-check';
        var t = el('div', { class: 'toast align-items-center border-0 ' + cls, role: kind === 'danger' ? 'alert' : 'status', 'aria-live': kind === 'danger' ? 'assertive' : 'polite', 'aria-atomic': 'true' },
            '<div class="d-flex"><div class="toast-body"><i class="fas ' + icon + ' me-2"></i>' + escapeHtml(message) +
            '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>');
        box.appendChild(t);
        var inst = new bootstrap.Toast(t, { delay: kind === 'danger' ? 7000 : 4000 });
        t.addEventListener('hidden.bs.toast', function () { t.remove(); });
        inst.show();
    }

    function showQueuedToast() {
        try {
            var raw = sessionStorage.getItem(TOAST_KEY);
            if (!raw) { return; }
            sessionStorage.removeItem(TOAST_KEY);
            var t = JSON.parse(raw);
            toast(t.message, t.kind);
        } catch (e) { /* storage unavailable */ }
    }

    function restoreScroll() {
        try {
            var raw = sessionStorage.getItem(SCROLL_KEY);
            if (!raw) { return; }
            sessionStorage.removeItem(SCROLL_KEY);
            var s = JSON.parse(raw);
            var main = document.querySelector('.app-main');
            if (main && s.url === location.href) { main.scrollTop = s.top; }
        } catch (e) { /* ignore */ }
    }

    // ----------------------------------------------------------------- drawer

    function build(mode) {
        if (shells[mode]) { return shells[mode]; }
        var isModal = mode === 'modal';
        var root = el('div', isModal
            ? { class: 'modal fade erp-modal', tabindex: '-1', id: 'erpModal', 'aria-labelledby': 'erpModalTitle', 'aria-hidden': 'true' }
            : { class: 'offcanvas offcanvas-end erp-drawer', tabindex: '-1', id: 'erpDrawer', 'aria-labelledby': 'erpDrawerTitle', 'data-bs-scroll': 'false' });
        var inner =
            '<div class="erp-drawer-header">' +
            '  <h2 class="erp-drawer-title" id="' + (isModal ? 'erpModalTitle' : 'erpDrawerTitle') + '">Loading…</h2>' +
            '  <button type="button" class="btn-close" data-erp-close aria-label="Close"></button>' +
            '</div>' +
            '<div class="erp-drawer-body">' +
            '  <div class="erp-drawer-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span class="visually-hidden">Loading form…</span></div>' +
            '  <iframe class="erp-drawer-frame" title="Form"></iframe>' +
            '</div>' +
            '<div class="erp-drawer-footer"></div>';
        root.innerHTML = isModal
            ? '<div class="modal-dialog modal-dialog-centered"><div class="modal-content erp-modal-content">' + inner + '</div></div>'
            : inner;
        document.body.appendChild(root);

        var sh = {
            mode: mode,
            root: root,
            frame: root.querySelector('iframe'),
            title: root.querySelector('.erp-drawer-title'),
            footer: root.querySelector('.erp-drawer-footer'),
            loading: root.querySelector('.erp-drawer-loading'),
            inst: isModal
                ? new bootstrap.Modal(root, { backdrop: true, keyboard: true })
                : new bootstrap.Offcanvas(root, { backdrop: true, keyboard: true, scroll: false })
        };
        var ev = isModal ? 'bs.modal' : 'bs.offcanvas';
        shells[mode] = sh;

        root.querySelector('[data-erp-close]').addEventListener('click', requestClose);
        sh.frame.addEventListener('load', onFrameLoad);

        // Esc / backdrop / close button all go through Bootstrap's hide event: ask first if there are unsaved edits.
        root.addEventListener('hide.' + ev, function (e) {
            if (state && state.dirty && !state.force && !state.submitting) {
                if (!window.confirm('Discard your unsaved changes?')) { e.preventDefault(); }
            }
        });
        root.addEventListener('hidden.' + ev, function () {
            var opener = state && state.opener;
            if (state && state.resizer) { state.resizer.disconnect(); }
            sh.frame.src = 'about:blank';
            sh.frame.style.height = '';
            sh.root.classList.remove('erp-modal-wide');
            sh.footer.innerHTML = '';
            state = null;
            if (opener && document.contains(opener)) { try { opener.focus(); } catch (e2) { /* ignore */ } }
        });
        return sh;
    }

    function requestClose() { if (drawer) { drawer.hide(); } }

    function open(href, opener) {
        var sh = build(modeFor(href, opener));
        drawerEl = sh.root; drawer = sh.inst; frame = sh.frame;
        titleEl = sh.title; footerEl = sh.footer; loadingEl = sh.loading;
        sh.root.classList.toggle('erp-modal-wide', !isShortForm(href));
        state = { url: href, mode: sh.mode, opener: opener || null, dirty: false, submitting: false, submitted: false, force: false, first: true };
        titleEl.textContent = 'Loading…';
        footerEl.innerHTML = '';
        loadingEl.classList.remove('d-none');
        frame.classList.remove('is-ready');
        frame.src = withEmbed(href);
        drawer.show();
    }

    // The modal has no fixed height: size the frame to the form it holds (the CSS caps it and the frame scrolls beyond that).
    function fitModalFrame(doc) {
        if (!state || state.mode !== 'modal' || drawerEl.classList.contains('erp-modal-wide')) { return; }
        var fit = function () {
            if (!doc.documentElement) { return; }
            frame.style.height = Math.max(120, doc.documentElement.scrollHeight) + 'px';
        };
        fit();
        if (state.resizer) { state.resizer.disconnect(); }
        if (window.ResizeObserver) {
            state.resizer = new ResizeObserver(fit);
            state.resizer.observe(doc.body);
        }
    }

    function fallback() {
        // Not something we can show in the drawer: do what the link would have done.
        var url = state && state.url;
        if (state) { state.force = true; }
        if (drawer) { drawer.hide(); }
        if (url) { window.location.href = url; }
    }

    // ------------------------------------------------------------ frame events

    function onFrameLoad() {
        if (!state || frame.getAttribute('src') === 'about:blank') { return; }
        var doc;
        try { doc = frame.contentDocument; } catch (e) { doc = null; }
        if (!doc || !doc.body) { return fallback(); }

        var embedded = doc.body.classList.contains('erp-embed');
        var form = embedded ? findForm(doc) : null;

        if (form) {
            bindForm(doc, form);
            return;
        }
        if (state.submitted) { return finish(doc); }
        // First load did not give us a form (error page, no permission, not a form page).
        if (!embedded && flashText(doc, '.alert-danger')) {
            toast(flashText(doc, '.alert-danger'), 'danger');
            state.force = true; drawer.hide();
            return;
        }
        fallback();
    }

    function findForm(doc) {
        var forms = Array.prototype.slice.call(doc.querySelectorAll('.app-content form, main form'));
        return forms.filter(function (f) {
            var m = (f.getAttribute('method') || 'get').toLowerCase();
            return m === 'post' && !f.classList.contains('d-none') && f.querySelector('input:not([type=hidden]), select, textarea');
        })[0] || null;
    }

    function flashText(doc, sel) {
        var a = doc.querySelector('.app-content ' + sel + ', main ' + sel);
        return a ? a.textContent.replace(/\s+/g, ' ').trim() : '';
    }

    // After a submit the frame left the form: saved, or sent somewhere with a message.
    function finish(doc) {
        var ok = flashText(doc, '.alert-success');
        var err = flashText(doc, '.alert-danger');
        state.force = true;
        if (err && !ok) {
            toast(err, 'danger');
            drawer.hide();
            return;
        }
        try {
            sessionStorage.setItem(TOAST_KEY, JSON.stringify({ message: ok || 'Saved.', kind: 'success' }));
            var main = document.querySelector('.app-main');
            sessionStorage.setItem(SCROLL_KEY, JSON.stringify({ url: location.href, top: main ? main.scrollTop : 0 }));
        } catch (e) { /* storage unavailable: reload without a toast */ }
        loadingEl.classList.remove('d-none');
        footerEl.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        // Same URL => filters, search and pagination are preserved; only the data refreshes.
        location.reload();
    }

    // ------------------------------------------------------------- form wiring

    function bindForm(doc, form) {
        var heading = (doc.title || '').replace(TITLE_SUFFIX, '').trim();
        titleEl.textContent = heading || 'Form';
        frame.setAttribute('title', heading || 'Form');

        // Focus lives inside the frame, so Esc never reaches the drawer on its own.
        doc.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !ev.defaultPrevented && !state.submitting) { requestClose(); }
        });

        var hadErrors = placeErrors(doc, form);
        state.submitting = false;
        state.dirty = hadErrors || state.dirty && state.submitted;
        form.addEventListener('input', function (ev) {
            state.dirty = true;
            clearInvalid(ev.target);
        });
        form.addEventListener('change', function (ev) { state.dirty = true; clearInvalid(ev.target); });
        form.addEventListener('submit', function (ev) {
            if (state.submitting) { ev.preventDefault(); return; }       // duplicate submit
            if (ev.defaultPrevented) { return; }                          // the page's own validation said no
            state.submitting = true;
            state.submitted = true;
            state.snapshot = snapshotForm(form);
            setBusy(true);
        });

        if (state.snapshot && restoreSnapshot(form, state.snapshot)) { state.dirty = true; }
        state.snapshot = null;
        buildFooter(form, doc);
        loadingEl.classList.add('d-none');
        frame.classList.add('is-ready');
        fitModalFrame(doc);

        var first = form.querySelector('.is-invalid') || form.querySelector('input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled])');
        if (first) { try { first.focus({ preventScroll: false }); } catch (e) { /* ignore */ } }
    }

    // What the user had entered when they pressed Save (file inputs cannot be restored by script).
    function snapshotForm(form) {
        var snap = {};
        new FormData(form).forEach(function (v, k) {
            if (typeof v !== 'string' || k === 'csrf_test_name' || k === '_method') { return; }
            (snap[k] = snap[k] || []).push(v);
        });
        return snap;
    }

    // If the server re-rendered the form but left a field empty (not every view repopulates every field
    // with old()), put the user's value back. Never overwrites something the server did fill in.
    function restoreSnapshot(form, snap) {
        var restored = false;
        Object.keys(snap).forEach(function (name) {
            var els = Array.prototype.slice.call(form.elements).filter(function (e) { return e.name === name || e.name === name + '[]'; });
            if (!els.length) { return; }
            var first = els[0];
            var type = (first.type || '').toLowerCase();
            if (type === 'hidden' || type === 'file' || type === 'password') { return; }
            var vals = snap[name];
            if (type === 'checkbox' || type === 'radio') {
                if (els.some(function (e) { return e.checked; })) { return; }
                els.forEach(function (e) { if (vals.indexOf(e.value) !== -1) { e.checked = true; restored = true; } });
                return;
            }
            if (first.tagName === 'SELECT' && first.multiple) {
                if (Array.prototype.some.call(first.options, function (o) { return o.selected; })) { return; }
                Array.prototype.forEach.call(first.options, function (o) { if (vals.indexOf(o.value) !== -1) { o.selected = true; restored = true; } });
                return;
            }
            if (first.value !== '' && !(first.tagName === 'SELECT' && first.selectedIndex === 0 && first.options[0] && first.options[0].value === '')) { return; }
            if (vals[0] === '') { return; }
            first.value = vals[0];
            if (first.value !== vals[0]) { return; }                         // option no longer exists
            restored = true;
            first.dispatchEvent(new Event('change', { bubbles: true }));     // lets dependent dropdowns react
        });
        return restored;
    }

    function isSubmitControl(n) {
        if (n.tagName === 'INPUT') { return n.type === 'submit'; }
        return n.tagName === 'BUTTON' && (!n.getAttribute('type') || n.getAttribute('type') === 'submit');
    }

    function buildFooter(form, doc) {
        footerEl.innerHTML = '';
        var submits = Array.prototype.slice.call(form.querySelectorAll('button, input[type=submit]')).filter(isSubmitControl);
        var cancels = Array.prototype.slice.call(form.querySelectorAll('a.btn[href]')).filter(function (a) {
            return /^(cancel|back|close)$/i.test(a.textContent.trim());
        });

        // The page's own buttons are replaced by the sticky footer.
        submits.concat(cancels).forEach(function (b) {
            b.style.display = 'none';
            var wrap = b.parentElement;
            if (wrap && wrap !== form && Array.prototype.every.call(wrap.children, function (c) { return c.style.display === 'none'; }) && !wrap.textContent.replace(/\s+/g, '').length) {
                wrap.style.display = 'none';
            }
        });

        var cancel = el('button', { type: 'button', class: 'btn btn-light erp-btn' }, 'Cancel');
        cancel.addEventListener('click', requestClose);
        footerEl.appendChild(cancel);

        var primary = submits.filter(function (b) { return /btn-primary/.test(b.className); })[0] || submits[0];
        submits.forEach(function (b) {
            var label = (b.tagName === 'INPUT' ? b.value : b.textContent).replace(/\s+/g, ' ').trim() || 'Save';
            var isPrimary = b === primary;
            var btn = el('button', { type: 'button', class: 'btn erp-btn ' + (isPrimary ? 'btn-primary' : 'btn-outline-secondary'), 'data-erp-submit': '1' });
            btn.innerHTML = '<span class="erp-btn-label"></span><span class="spinner-border spinner-border-sm ms-2 d-none" aria-hidden="true"></span>';
            btn.querySelector('.erp-btn-label').textContent = niceLabel(label, isPrimary);
            btn.addEventListener('click', function () {
                if (state.submitting) { return; }
                // requestSubmit() runs the browser's required-field validation and keeps the clicked button's name/value.
                if (typeof form.requestSubmit === 'function') { form.requestSubmit(b); } else { b.click(); }
            });
            footerEl.appendChild(btn);
        });

    }

    // Primary action wording: "Create New" for add forms, "Save Changes" for edit forms (other labels are kept).
    function niceLabel(label, isPrimary) {
        if (!isPrimary) { return label; }
        if (/^(create|save|add|submit|update)$/i.test(label)) {
            return /\/\d+\/edit\/?$|\/structure\/\d+/.test(new URL(state.url, location.href).pathname) ? 'Save Changes' : (/^(create|add|save|submit)$/i.test(label) ? 'Create New' : 'Save Changes');
        }
        return label;
    }

    function setBusy(on) {
        footerEl.querySelectorAll('button').forEach(function (b) {
            b.disabled = on;
            var sp = b.querySelector('.spinner-border');
            if (sp && b.classList.contains('btn-primary')) { sp.classList.toggle('d-none', !on); }
        });
        loadingEl.classList.toggle('d-none', !on);
        frame.classList.toggle('is-busy', on);
        drawerEl.setAttribute('aria-busy', on ? 'true' : 'false');
    }

    // Server validation errors arrive as a flash list keyed by field name (layout renders data-field).
    function placeErrors(doc, form) {
        var any = false;
        doc.querySelectorAll('.alert-danger li[data-field]').forEach(function (li) {
            var name = li.getAttribute('data-field');
            var input = null;
            try {
                input = form.querySelector('[name="' + name.replace(/"/g, '\\"') + '"]') || form.querySelector('[name="' + name.replace(/"/g, '\\"') + '[]"]');
            } catch (e) { input = null; }
            if (!input) { return; }
            any = true;
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
            var anchor = input.closest('.input-group') || input;
            var fb = doc.createElement('div');
            fb.className = 'invalid-feedback d-block erp-field-error';
            fb.textContent = li.textContent.trim();
            fb.id = 'err-' + name.replace(/\W+/g, '-');
            input.setAttribute('aria-describedby', fb.id);
            anchor.insertAdjacentElement('afterend', fb);
            li.remove();
        });
        doc.querySelectorAll('.alert-danger').forEach(function (a) {
            if (a.querySelector('ul') && !a.querySelectorAll('li').length) { a.remove(); } else { any = any || true; }
        });
        return any;
    }

    function clearInvalid(input) {
        if (!input || !input.classList || !input.classList.contains('is-invalid')) { return; }
        input.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
        var anchor = input.closest('.input-group') || input;
        var next = anchor.nextElementSibling;
        if (next && next.classList.contains('erp-field-error')) { next.remove(); }
    }

    // ------------------------------------------------------------------ wiring

    document.addEventListener('click', function (ev) {
        var a = ev.target.closest && ev.target.closest('a[href]');
        if (!isFormLink(a, ev)) { return; }
        if (typeof bootstrap === 'undefined' || !window.fetch) { return; }  // no Bootstrap JS: normal navigation
        ev.preventDefault();
        open(a.href, a);
    });

    window.ErpForms = { open: open, toast: toast };
    document.addEventListener('DOMContentLoaded', function () { showQueuedToast(); restoreScroll(); });
})();
