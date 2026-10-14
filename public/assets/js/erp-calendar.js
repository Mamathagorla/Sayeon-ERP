/*
 * ERP calendar
 * ------------
 * One calendar popup for the whole ERP (no dependency):
 *  - every <input type="date"> opens it instead of the browser's native picker. The input keeps its
 *    type, value format (yyyy-mm-dd), min/max, required and name, and still accepts typing, so forms,
 *    validation and the server see exactly what they did before. 'input' and 'change' events fire on pick.
 *  - any element with data-erp-calendar="yyyy-mm-dd[,yyyy-mm-dd]" (e.g. the dashboard date chip) opens a
 *    read-only calendar that highlights that date or range.
 * Click the month or year in the header to jump straight to any month / year (the arrows still step by one).
 * Keyboard: arrows move by day/week, PageUp/PageDown change month, Enter/Space picks, Esc closes.
 * Opt a date input out with data-native-date.
 */
(function () {
    'use strict';

    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var pop = null, ctx = null;

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function parse(s) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
        if (!m) { return null; }
        var d = new Date(+m[1], +m[2] - 1, +m[3]);
        return d.getMonth() === +m[2] - 1 ? d : null;
    }
    function addDays(d, n) { return new Date(d.getFullYear(), d.getMonth(), d.getDate() + n); }

    function build() {
        pop = document.createElement('div');
        pop.className = 'erp-cal';
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-label', 'Calendar');
        pop.hidden = true;
        document.body.appendChild(pop);
        pop.addEventListener('mousedown', function (e) { if (e.target.closest('button')) { e.preventDefault(); } });
        pop.addEventListener('click', onPopClick);
        pop.addEventListener('keydown', onKey);
    }

    function render() {
        var v = ctx.view, first = new Date(v.getFullYear(), v.getMonth(), 1);
        var start = addDays(first, -first.getDay());
        var today = iso(new Date());
        var h = '<div class="erp-cal-head">' +
            '<button type="button" class="erp-cal-nav" data-nav="-1" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>' +
            '<div class="erp-cal-title" aria-live="polite">' +
            '<button type="button" data-pick="month" aria-label="Choose month">' + MONTHS[v.getMonth()] + '</button>' +
            '<button type="button" data-pick="year" aria-label="Choose year">' + v.getFullYear() + '</button></div>' +
            '<button type="button" class="erp-cal-nav" data-nav="1" aria-label="Next month"><i class="fas fa-chevron-right"></i></button></div>';
        if (ctx.mode === 'month' || ctx.mode === 'year') {
            pop.innerHTML = h + renderPicker(v);
            return;
        }
        h += '<div class="erp-cal-grid" role="grid">';
        DAYS.forEach(function (d) { h += '<div class="erp-cal-dow" role="columnheader">' + d + '</div>'; });
        for (var i = 0; i < 42; i++) {
            var d = addDays(start, i), s = iso(d), cls = 'erp-cal-day';
            var disabled = (ctx.min && s < ctx.min) || (ctx.max && s > ctx.max);
            if (d.getMonth() !== v.getMonth()) { cls += ' is-out'; }
            if (s === today) { cls += ' is-today'; }
            if (ctx.from && ctx.to && s >= ctx.from && s <= ctx.to) { cls += ' in-range'; }
            if (s === ctx.from || s === ctx.to) { cls += ' is-selected'; }
            h += '<button type="button" class="' + cls + '" data-date="' + s + '" tabindex="' + (s === ctx.focus ? '0' : '-1') + '"' +
                (disabled ? ' disabled' : '') + (ctx.readonly ? ' data-readonly="1"' : '') +
                ' aria-label="' + d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() + '"' +
                (s === ctx.from ? ' aria-current="date"' : '') + '>' + d.getDate() + '</button>';
        }
        h += '</div>';
        if (!ctx.readonly) {
            h += '<div class="erp-cal-foot"><button type="button" data-act="today">Today</button>' +
                (ctx.clearable ? '<button type="button" data-act="clear">Clear</button>' : '') + '</div>';
        }
        pop.innerHTML = h;
    }

    // Month / year chooser shown in place of the day grid. Years run in pages of 12 so any past or
    // future year is reachable (clamped to the input's min/max years).
    function renderPicker(v) {
        var h = '<div class="erp-cal-picker" role="listbox">', i;
        var minY = ctx.min ? +ctx.min.slice(0, 4) : 1, maxY = ctx.max ? +ctx.max.slice(0, 4) : 9999;
        if (ctx.mode === 'month') {
            for (i = 0; i < 12; i++) {
                var mStart = v.getFullYear() + '-' + pad(i + 1) + '-01', mEnd = v.getFullYear() + '-' + pad(i + 1) + '-31';
                var off = (ctx.min && mEnd < ctx.min) || (ctx.max && mStart > ctx.max);
                h += '<button type="button" data-month="' + i + '"' + (i === v.getMonth() ? ' class="is-current"' : '') + (off ? ' disabled' : '') + '>' + MONTHS[i].slice(0, 3) + '</button>';
            }
        } else {
            for (i = 0; i < 12; i++) {
                var y = ctx.yearBase + i, offY = y < minY || y > maxY || y < 1;
                h += '<button type="button" data-year="' + y + '"' + (y === v.getFullYear() ? ' class="is-current"' : '') + (offY ? ' disabled' : '') + '>' + y + '</button>';
            }
        }
        return h + '</div>';
    }

    function setMode(mode) {
        ctx.mode = ctx.mode === mode ? 'day' : mode;
        if (ctx.mode === 'year') { ctx.yearBase = ctx.view.getFullYear() - 4; }
        render();
    }

    function place() {
        var r = ctx.anchor.getBoundingClientRect();
        pop.style.visibility = 'hidden';
        pop.hidden = false;
        var w = pop.offsetWidth, hgt = pop.offsetHeight;
        var left = Math.min(Math.max(8, r.left), window.innerWidth - w - 8);
        var top = r.bottom + 6;
        if (top + hgt > window.innerHeight - 8 && r.top - hgt - 6 > 8) { top = r.top - hgt - 6; }
        pop.style.left = left + 'px';
        pop.style.top = Math.max(8, top) + 'px';
        pop.style.visibility = '';
    }

    function open(opts) {
        if (!pop) { build(); }
        close(false);
        var base = parse(opts.value) || parse(opts.from) || new Date();
        ctx = {
            anchor: opts.anchor, input: opts.input || null, min: opts.min || '', max: opts.max || '',
            from: opts.from || opts.value || '', to: opts.to || '', readonly: !!opts.readonly, clearable: !!opts.clearable,
            view: new Date(base.getFullYear(), base.getMonth(), 1), focus: iso(base), mode: 'day', yearBase: 0
        };
        render();
        place();
        ctx.anchor.setAttribute('aria-expanded', 'true');
        document.addEventListener('mousedown', onOutside, true);
        window.addEventListener('resize', onResize);
        document.addEventListener('scroll', onResize, true);
    }

    function close(restore) {
        if (!pop || pop.hidden) { return; }
        pop.hidden = true;
        document.removeEventListener('mousedown', onOutside, true);
        window.removeEventListener('resize', onResize);
        document.removeEventListener('scroll', onResize, true);
        if (ctx) {
            ctx.anchor.removeAttribute('aria-expanded');
            if (restore !== false) { try { ctx.anchor.focus({ preventScroll: true }); } catch (e) { /* ignore */ } }
        }
        ctx = null;
    }

    function onResize(e) { if (e && e.target && pop && pop.contains(e.target)) { return; } close(false); }
    function onOutside(e) { if (pop.contains(e.target) || (ctx && ctx.anchor.contains(e.target))) { return; } close(false); }

    function pick(s) {
        if (!ctx || ctx.readonly || !ctx.input) { return; }
        var input = ctx.input;
        input.value = s;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        close(true);
    }

    function shift(months) {
        ctx.view = new Date(ctx.view.getFullYear(), ctx.view.getMonth() + months, 1);
        var f = parse(ctx.focus) || ctx.view;
        var last = new Date(ctx.view.getFullYear(), ctx.view.getMonth() + 1, 0).getDate();
        ctx.focus = iso(new Date(ctx.view.getFullYear(), ctx.view.getMonth(), Math.min(f.getDate(), last)));
        render();
    }

    // Keep the keyboard-focus date inside the month/year now being viewed.
    function syncFocus() {
        var f = parse(ctx.focus) || ctx.view;
        var last = new Date(ctx.view.getFullYear(), ctx.view.getMonth() + 1, 0).getDate();
        ctx.focus = iso(new Date(ctx.view.getFullYear(), ctx.view.getMonth(), Math.min(f.getDate(), last)));
    }

    function focusDay() { var b = pop.querySelector('[data-date="' + ctx.focus + '"]'); if (b) { b.focus(); } }

    function onPopClick(e) {
        var pk = e.target.closest('[data-pick]');
        if (pk) { setMode(pk.getAttribute('data-pick')); return; }
        var mo = e.target.closest('[data-month]');
        if (mo) { ctx.view = new Date(ctx.view.getFullYear(), +mo.getAttribute('data-month'), 1); ctx.mode = 'day'; syncFocus(); render(); return; }
        var yr = e.target.closest('[data-year]');
        if (yr) { ctx.view = new Date(+yr.getAttribute('data-year'), ctx.view.getMonth(), 1); ctx.mode = 'day'; syncFocus(); render(); return; }
        var nav = e.target.closest('[data-nav]');
        if (nav) {
            var dir = +nav.getAttribute('data-nav');
            if (ctx.mode === 'year') { ctx.yearBase += 12 * dir; render(); }
            else if (ctx.mode === 'month') { ctx.view = new Date(ctx.view.getFullYear() + dir, ctx.view.getMonth(), 1); render(); }
            else { shift(dir); }
            return;
        }
        var act = e.target.closest('[data-act]');
        if (act) { pick(act.getAttribute('data-act') === 'clear' ? '' : iso(new Date())); return; }
        var day = e.target.closest('.erp-cal-day');
        if (day && !day.disabled && !day.getAttribute('data-readonly')) { pick(day.getAttribute('data-date')); }
    }

    function onKey(e) {
        if (!ctx) { return; }
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(true); return; }
        if (!e.target.classList.contains('erp-cal-day')) { return; }
        var cur = parse(ctx.focus), step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
        if (step !== undefined) {
            e.preventDefault();
            var n = addDays(cur, step);
            ctx.focus = iso(n);
            if (n.getMonth() !== ctx.view.getMonth()) { ctx.view = new Date(n.getFullYear(), n.getMonth(), 1); }
            render();
            focusDay();
        } else if (e.key === 'PageUp' || e.key === 'PageDown') {
            e.preventDefault();
            shift(e.key === 'PageUp' ? -1 : 1);
            focusDay();
        } else if (e.key === 'Home' || e.key === 'End') {
            e.preventDefault();
            ctx.focus = iso(addDays(cur, e.key === 'Home' ? -cur.getDay() : 6 - cur.getDay()));
            render();
            focusDay();
        }
    }

    // ---------------------------------------------------------------- wiring

    function usable(input) {
        return input && input.type === 'date' && !input.disabled && !input.readOnly && !input.hasAttribute('data-native-date');
    }

    function openForInput(input) {
        open({ anchor: input, input: input, value: input.value, min: input.min, max: input.max, clearable: !input.required });
        focusDay();
    }

    // Capture phase + preventDefault: stops the browser's own picker from opening.
    document.addEventListener('click', function (e) {
        var chip = e.target.closest && e.target.closest('[data-erp-calendar]');
        if (chip) {
            e.preventDefault();
            if (ctx && ctx.anchor === chip) { close(true); return; }
            open({ anchor: chip, from: iso(new Date()), readonly: true });   // view-only: only today is highlighted
            focusDay();
            return;
        }
        var input = e.target.closest && e.target.closest('input[type=date]');
        if (usable(input)) {
            e.preventDefault();
            if (ctx && ctx.anchor === input) { return; }
            openForInput(input);
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        var input = e.target;
        if (!input || input.tagName !== 'INPUT' || !usable(input)) { return; }
        if ((e.key === 'ArrowDown' && e.altKey) || e.key === 'F4') { e.preventDefault(); openForInput(input); }
    });

    window.ErpCalendar = { open: open, close: close };
})();
