<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    /* ============ Calendar design system ============
       One muted palette for every event category. Each category defines its
       colours once here; events, legend chips and the day-detail popup all read
       these tokens. Change a colour in this block only. */
    .sy-cal-page {
        --calendar-meeting-bg: #E8F1FF;
        --calendar-meeting-text: #2563EB;
        --calendar-meeting-accent: #3B82F6;

        --calendar-task-bg: #F1F3F5;
        --calendar-task-text: #495057;
        --calendar-task-accent: #6B7280;

        --calendar-compliance-bg: #FFF4E5;
        --calendar-compliance-text: #B45309;
        --calendar-compliance-accent: #F59E0B;

        --calendar-overdue-bg: #FDECEC;
        --calendar-overdue-text: #C24141;
        --calendar-overdue-accent: #E57373;

        color: var(--sy-ink);
    }
    /* Per-element aliases the components use. Legend chips carry data-category;
       events and popup items carry sy-cal-cat-<category>. */
    .sy-cal-cat-meeting,    [data-category="meeting"]    { --cal-bg: var(--calendar-meeting-bg);    --cal-text: var(--calendar-meeting-text);    --cal-accent: var(--calendar-meeting-accent); }
    .sy-cal-cat-task,       [data-category="task"]       { --cal-bg: var(--calendar-task-bg);       --cal-text: var(--calendar-task-text);       --cal-accent: var(--calendar-task-accent); }
    .sy-cal-cat-compliance, [data-category="compliance"] { --cal-bg: var(--calendar-compliance-bg); --cal-text: var(--calendar-compliance-text); --cal-accent: var(--calendar-compliance-accent); }
    .sy-cal-cat-overdue,    [data-category="overdue"]    { --cal-bg: var(--calendar-overdue-bg);    --cal-text: var(--calendar-overdue-text);    --cal-accent: var(--calendar-overdue-accent); }

    /* ---- Intro and legend ---- */
    .sy-cal-legend { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .85rem; }
    .sy-cal-chip { display: inline-flex; align-items: center; gap: 8px; height: 30px; padding: 0 12px; border: 1px solid transparent; border-radius: 999px; background: var(--cal-bg); color: var(--sy-ink); font: inherit; font-size: .78rem; font-weight: 600; cursor: pointer; transition: box-shadow .15s ease, opacity .15s ease; }
    .sy-cal-chip .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--cal-accent); flex: none; }
    .sy-cal-chip:hover { box-shadow: inset 0 0 0 1px var(--cal-accent); }
    .sy-cal-chip.is-active { box-shadow: inset 0 0 0 1.5px var(--cal-accent); }
    .sy-cal-chip.is-dimmed { opacity: .5; }
    .sy-cal-chip:focus-visible { outline: 2px solid var(--cal-accent); outline-offset: 2px; }
    .sy-cal-filter-hint { font-size: .8rem; color: var(--sy-muted); margin-bottom: .75rem; }
    .sy-cal-filter-hint[hidden] { display: none; }
    .sy-cal-filter-hint strong { color: var(--sy-ink); }
    .sy-cal-filter-hint a { color: var(--sy-accent-ink); font-weight: 600; }

    /* ---- Calendar card ---- */
    .sy-cal-wrap { background: var(--sy-surface); border: 1px solid var(--sy-border-soft); border-radius: 16px; box-shadow: 0 1px 2px rgba(20, 24, 32, .04); padding: 1.1rem 1.2rem; min-width: 0; }
    .sy-cal-page #calendar { --fc-border-color: #edf0f4; --fc-page-bg-color: var(--sy-surface); color: var(--sy-ink); max-width: 100%; }

    /* ---- Header: title, neutral nav buttons, Month/Week/Agenda segmented control ---- */
    .sy-cal-page #calendar .fc-toolbar-title { font-size: 1rem; font-weight: 600; letter-spacing: -.01em; color: var(--sy-ink); }
    .sy-cal-page #calendar .fc .fc-button { height: 34px; padding: 0 12px; border-radius: 10px; background: var(--sy-surface); color: var(--sy-ink); border: 1px solid var(--sy-border); box-shadow: none; font-size: .8rem; font-weight: 600; text-transform: none; }
    .sy-cal-page #calendar .fc .fc-button:hover { background: var(--sy-hover-bg); color: var(--sy-ink); border-color: var(--sy-border-strong); }
    .sy-cal-page #calendar .fc .fc-button:focus-visible { box-shadow: 0 0 0 3px var(--sy-accent-soft); }
    .sy-cal-page #calendar .fc .fc-button-group { display: inline-flex; gap: 4px; }
    .sy-cal-page #calendar .fc .fc-button-group > .fc-button { margin: 0; border-radius: 10px; }
    .sy-cal-page #calendar .fc-toolbar-chunk:last-child .fc-button-group { gap: 2px; padding: 3px; background: var(--sy-subtle-bg); border: 1px solid var(--sy-border-soft); border-radius: 12px; }
    .sy-cal-page #calendar .fc-toolbar-chunk:last-child .fc-button-group > .fc-button { height: 30px; padding: 0 12px; border: 0; border-radius: 9px; background: transparent; color: var(--sy-muted); }
    .sy-cal-page #calendar .fc-toolbar-chunk:last-child .fc-button-group > .fc-button:hover { background: var(--sy-surface); color: var(--sy-ink); }
    .sy-cal-page #calendar .fc-toolbar-chunk:last-child .fc-button-group > .fc-button.fc-button-active { background: var(--sy-accent-ink); color: #fff; box-shadow: 0 2px 6px -3px rgba(204, 31, 44, .5); }

    /* ---- Day headers, day numbers, weekend and today ---- */
    .sy-cal-page #calendar .fc-col-header-cell-cushion { padding: 10px 0; font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--sy-muted); text-decoration: none; }
    .sy-cal-page #calendar .fc-daygrid-day-number { padding: 8px 10px; font-size: .8rem; font-weight: 500; color: var(--sy-ink); text-decoration: none; }
    .sy-cal-page #calendar .fc-day-other .fc-daygrid-day-number { color: var(--sy-muted-soft); }
    .sy-cal-page #calendar .fc-day-sat,
    .sy-cal-page #calendar .fc-day-sun { background: #fafbfc; }
    .sy-cal-page #calendar .fc-day-today { background: transparent; }
    .sy-cal-page #calendar .fc-day-today .fc-daygrid-day-number { display: inline-flex; align-items: center; justify-content: center; min-width: 26px; height: 26px; margin: 4px 6px; padding: 0; border-radius: 999px; background: var(--sy-accent-ink); color: #fff; font-weight: 600; box-shadow: 0 2px 6px -2px rgba(204, 31, 44, .45); }
    .sy-cal-page #calendar .fc-more-link { font-size: .72rem; font-weight: 600; color: var(--sy-accent-ink); }

    /* ---- Events: soft category tint, coloured left edge, readable text ---- */
    .sy-cal-page #calendar .fc-daygrid-event,
    .sy-cal-page #calendar .fc-timegrid-event { margin: 2px 4px; border-radius: 8px; box-shadow: none; transition: filter .15s ease; }
    .sy-cal-page #calendar .fc-daygrid-event { padding: 2px 6px 2px 5px; font-size: .74rem; line-height: 1.3; }
    .sy-cal-page #calendar .fc-daygrid-event:hover,
    .sy-cal-page #calendar .fc-timegrid-event:hover { filter: brightness(.97); }
    .sy-cal-page #calendar .fc-event[class*="sy-cal-cat-"] {
        background-color: var(--cal-bg) !important;
        border-color: var(--cal-bg);
        border-left: 3px solid var(--cal-accent);
        color: var(--cal-text);
        --fc-event-bg-color: var(--cal-bg);
        --fc-event-border-color: var(--cal-accent);
        --fc-event-text-color: var(--cal-text);
    }
    .sy-cal-page #calendar .fc-daygrid-event-dot { border-color: var(--cal-accent) !important; }
    .sy-cal-page #calendar .fc-event-title { font-size: .76rem; font-weight: 600; color: inherit; }
    .sy-cal-page #calendar .fc-event-time { font-size: .7rem; font-weight: 500; color: inherit; opacity: .8; }

    /* Agenda (list) rows: plain rows, the coloured dot carries the category. */
    .sy-cal-page #calendar .fc-list-event[class*="sy-cal-cat-"] { background: transparent !important; border: 0; border-left: 0; color: var(--sy-ink); }
    .sy-cal-page #calendar .fc-list-event-title { color: var(--sy-ink); }
    .sy-cal-page #calendar .fc-list-event:hover td { background-color: var(--sy-hover-bg); }

    /* ---- Day detail popup: same category tints as the events ---- */
    .sy-cal-modal .modal-content { border: 0; border-radius: 16px; box-shadow: 0 16px 40px -20px rgba(20, 24, 32, .35); }
    .sy-cal-modal .modal-title { color: var(--sy-ink); font-size: .95rem; }
    .sy-cal-modal .sy-cal-modal-item { display: flex; align-items: flex-start; gap: 10px; padding: 11px 14px; margin-bottom: 8px; background: var(--sy-surface); border: 1px solid var(--sy-border-soft); border-left: 3px solid var(--sy-muted); border-radius: 10px; color: var(--sy-ink); text-decoration: none; transition: filter .15s ease; }
    .sy-cal-modal .sy-cal-modal-item[class*="sy-cal-cat-"] { background: var(--cal-bg); border-left-color: var(--cal-accent); }
    .sy-cal-modal .sy-cal-modal-item:hover { filter: brightness(.97); }
    .sy-cal-modal .sy-cal-modal-item .title { display: block; font-weight: 600; font-size: .88rem; color: var(--sy-ink); }
    .sy-cal-modal .sy-cal-modal-item .meta { display: block; font-size: .75rem; color: var(--sy-muted); margin-top: 2px; }
    .sy-cal-modal .sy-cal-modal-item .flex-grow-1 { flex: 1 1 auto; min-width: 0; }

    /* ---- Mobile and tablet ---- */
    @media (max-width: 767.98px) {
        .sy-cal-wrap { padding: .75rem; border-radius: 14px; }
        .sy-cal-legend { gap: .4rem; }
        .sy-cal-page #calendar .fc-header-toolbar { flex-direction: column; align-items: stretch; gap: .6rem; }
        .sy-cal-page #calendar .fc-toolbar-chunk { display: flex; flex-wrap: wrap; justify-content: center; gap: .4rem; }
        .sy-cal-page #calendar .fc-toolbar-title { font-size: .95rem; text-align: center; }
        .sy-cal-page #calendar .fc-col-header-cell-cushion { font-size: .62rem; letter-spacing: .02em; }
        .sy-cal-page #calendar .fc-daygrid-day-number { padding: 4px 6px; font-size: .72rem; }
        .sy-cal-page #calendar .fc-daygrid-event .fc-event-time { display: none; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="sy-cal-page">
<?php if (! $canViewTasks && ! $canViewMeetings && ! $canViewCompliance): ?>
    <p class="text-muted">You don't have permission to view any calendar data yet.</p>
<?php else: ?>
    <div class="sy-cal-legend" role="group" aria-label="Show only this type">
        <?php if ($canViewTasks): ?><button type="button" class="sy-cal-chip" data-category="task" aria-pressed="false"><span class="dot"></span>Task due</button><?php endif; ?>
        <?php if ($canViewMeetings): ?><button type="button" class="sy-cal-chip" data-category="meeting" aria-pressed="false"><span class="dot"></span>Meeting</button><?php endif; ?>
        <?php if ($canViewCompliance): ?>
            <button type="button" class="sy-cal-chip" data-category="compliance" aria-pressed="false"><span class="dot"></span>Compliance due</button>
            <button type="button" class="sy-cal-chip" data-category="overdue" aria-pressed="false"><span class="dot"></span>Overdue</button>
        <?php endif; ?>
    </div>
    <div class="sy-cal-filter-hint" hidden>Showing <strong class="sy-cal-filter-name"></strong> only · <a href="#" class="sy-cal-filter-clear">Show all</a></div>

    <div class="sy-cal-wrap">
        <div id="calendar"></div>
    </div>

    <div class="modal fade sy-cal-modal" id="syCalDayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold" id="syCalModalDate"></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="syCalModalList"></div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    if (! calendarEl) return;

    const eventsUrl   = '<?= site_url('calendar/events') ?>';
    const modalEl      = document.getElementById('syCalDayModal');
    const modalDateEl  = document.getElementById('syCalModalDate');
    const modalListEl  = document.getElementById('syCalModalList');
    const syCalModal   = modalEl ? new bootstrap.Modal(modalEl) : null;

    // One category per event, matching the legend tabs. Overdue (importance 0,
    // set by the server for overdue tasks and compliance) takes priority.
    // Colours come from the CSS tokens, so no colour is defined here.
    const TYPE_TO_CATEGORY = { Task: 'task', Meeting: 'meeting', Compliance: 'compliance' };

    function categoryOf(event) {
        if ((event.extendedProps.importance ?? 9) === 0) return 'overdue';
        return TYPE_TO_CATEGORY[event.extendedProps.type] || 'task';
    }

    // ---- Legend tabs: click one to show only that type; click again to show all ----
    let activeCategory = null;
    const legendButtons = document.querySelectorAll('.sy-cal-chip[data-category]');
    const filterHint    = document.querySelector('.sy-cal-filter-hint');
    const filterName    = document.querySelector('.sy-cal-filter-name');
    const legendLabels  = {};
    legendButtons.forEach(btn => { legendLabels[btn.dataset.category] = btn.textContent.trim(); });

    function updateLegend() {
        legendButtons.forEach(btn => {
            const on = btn.dataset.category === activeCategory;
            btn.classList.toggle('is-active', on);
            btn.classList.toggle('is-dimmed', activeCategory !== null && ! on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        filterHint.hidden = activeCategory === null;
        if (activeCategory !== null) {
            filterName.textContent = legendLabels[activeCategory];
        }
    }

    function setCategory(category) {
        activeCategory = category;
        updateLegend();
        calendar.refetchEvents();
    }

    legendButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            setCategory(activeCategory === btn.dataset.category ? null : btn.dataset.category);
        });
    });
    document.querySelector('.sy-cal-filter-clear').addEventListener('click', e => {
        e.preventDefault();
        setCategory(null);
    });

    function toISODate(d) {
        const y = d.getFullYear(), m = String(d.getMonth() + 1).padStart(2, '0'), day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function eventsOnDate(dateStr) {
        return calendar.getEvents()
            .filter(e => e.startStr.substring(0, 10) === dateStr)
            .sort((a, b) => (a.extendedProps.importance ?? 9) - (b.extendedProps.importance ?? 9) || a.start - b.start);
    }

    function selectDate(dateStr) {
        calendarEl.querySelectorAll('.fc-day-selected').forEach(el => el.classList.remove('fc-day-selected'));
        calendarEl.querySelectorAll(`[data-date="${dateStr}"]`).forEach(el => el.classList.add('fc-day-selected'));
    }

    function openDayModal(dateStr, dayEvents) {
        if (! syCalModal) return;

        modalDateEl.textContent = new Date(dateStr + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        modalListEl.innerHTML = '';

        dayEvents.forEach(e => {
            const time = e.allDay ? 'All day' : e.start.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

            // Built with .textContent, not string concatenation: event titles
            // come from user-entered tasks, meetings and compliance items.
            const title = document.createElement('span');
            title.className = 'title';
            title.textContent = e.title;

            const meta = document.createElement('span');
            meta.className = 'meta';
            meta.textContent = (e.extendedProps.type || '') + (time ? ' · ' + time : '');

            const body = document.createElement('span');
            body.className = 'flex-grow-1';
            body.append(title, meta);

            const a = document.createElement('a');
            a.href      = e.url || '#';
            a.className = 'sy-cal-modal-item sy-cal-cat-' + categoryOf(e);
            a.append(body);
            modalListEl.appendChild(a);
        });

        syCalModal.show();
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' },
        buttonText: { today: 'Today', month: 'Month', week: 'Week', list: 'Agenda' },
        eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },
        height: 'auto',
        // Only the top few events per day render directly on the month
        // grid (most important first via eventOrder) — the rest are
        // reached via "+N more" or by clicking the day itself.
        dayMaxEvents: 3,
        eventOrder: 'extendedProps.importance,start',
        events: function (info, successCallback, failureCallback) {
            fetch(eventsUrl).then(function (r) { return r.json(); }).then(function (data) {
                successCallback(activeCategory === null ? data : data.filter(e => categoryOf(e) === activeCategory));
            }).catch(failureCallback);
        },
        eventClassNames: function (arg) {
            return ['sy-cal-cat-' + categoryOf(arg.event)];
        },
        dateClick: function (info) {
            selectDate(info.dateStr);
            const dayEvents = eventsOnDate(info.dateStr);
            if (dayEvents.length) {
                openDayModal(info.dateStr, dayEvents);
            }
        },
        moreLinkClick: function (info) {
            const dateStr = toISODate(info.date);
            selectDate(dateStr);
            openDayModal(dateStr, eventsOnDate(dateStr));
            return 'none';
        },
    });

    calendar.render();
});
</script>
<?= $this->endSection() ?>
