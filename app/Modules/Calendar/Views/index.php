<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    #calendar { background: var(--sy-surface); padding: 1rem; border-radius: .375rem; }
    .fc-event { cursor: pointer; }
    .sy-cal-legend button { display: inline-flex; align-items: center; gap: 6px; color: var(--sy-ink); font-weight: 600; background: none; border: 1px solid transparent; border-radius: 6px; padding: 4px 8px; cursor: pointer; }
    .sy-cal-legend button:hover { background: var(--sy-accent-soft); }
    .sy-cal-legend button.active { background: var(--sy-accent-soft); border-color: var(--sy-border); }
    .sy-cal-legend button.dimmed { opacity: .45; }
    .sy-cal-legend .dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }

    /* Selected-date highlight — applied to whichever cell type is
       showing (month day cell, or week/day column header). */
    .fc-daygrid-day.fc-day-selected .fc-daygrid-day-frame { background: var(--sy-accent-soft); border-radius: 6px; }
    .fc-timegrid-col.fc-day-selected { background: var(--sy-accent-soft); }
    .fc-col-header-cell.fc-day-selected { background: var(--sy-accent-soft); }
    .fc-daygrid-day, .fc-timegrid-col, .fc-col-header-cell { cursor: pointer; }

    .sy-cal-modal-item { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--sy-border-soft); text-decoration: none; }
    .sy-cal-modal-item:last-child { border-bottom: none; }
    .sy-cal-modal-item .dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; margin-top: 6px; }
    .sy-cal-modal-item .title { display: block; font-weight: 600; font-size: .88rem; color: var(--sy-ink); }
    .sy-cal-modal-item .meta { display: block; font-size: .76rem; color: var(--sy-muted); }

    .sy-week-card { background: var(--sy-surface); border: 1px solid var(--sy-border-soft); border-radius: .5rem; padding: 1rem 1.15rem; margin-bottom: 1rem; }
    .sy-week-card .sy-week-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .85rem; }
    .sy-week-card .sy-week-title { font-weight: 700; font-size: .95rem; color: var(--sy-ink); margin: 0; }
    .sy-week-card .sy-week-total { font-size: .8rem; color: var(--sy-muted); }
    .sy-week-card .sy-week-total strong { color: var(--sy-ink); font-size: 1rem; }
    .sy-week-nav { display: flex; gap: 4px; }
    .sy-week-nav button { border: 1px solid var(--sy-border); background: var(--sy-surface); border-radius: 6px; width: 28px; height: 28px; line-height: 1; cursor: pointer; color: var(--sy-ink); }
    .sy-week-nav button:hover { background: var(--sy-accent-soft); }
    .sy-week-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; }
    .sy-week-day { text-align: center; }
    .sy-week-day .dow { font-size: .72rem; font-weight: 700; color: var(--sy-muted); text-transform: uppercase; margin-bottom: 6px; }
    .sy-week-day .bar-track { height: 70px; display: flex; align-items: flex-end; justify-content: center; background: var(--sy-surface-alt, rgba(0,0,0,.03)); border-radius: 6px; padding: 3px; margin-bottom: 6px; }
    .sy-week-day .bar { width: 60%; border-radius: 4px 4px 2px 2px; background: var(--sy-accent, #cc1f2c); min-height: 3px; transition: height .2s ease; }
    .sy-week-day .bar.is-empty { background: var(--sy-border); }
    .sy-week-day .hrs { font-size: .72rem; font-weight: 600; color: var(--sy-ink); }
    .sy-week-day.is-today .dow { color: var(--sy-accent, #cc1f2c); }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php if (! $canViewTasks && ! $canViewMeetings && ! $canViewCompliance): ?>
    <p class="text-muted">You don't have permission to view any calendar data yet.</p>
<?php else: ?>
    <div class="d-flex gap-2 mb-3 small flex-wrap sy-cal-legend">
        <?php if ($canViewTasks): ?><button type="button" data-filter="Task"><span class="dot" style="background:#5b6472"></span>Task due</button><?php endif; ?>
        <?php if ($canViewMeetings): ?><button type="button" data-filter="Meeting"><span class="dot" style="background:#2563eb"></span>Meeting</button><?php endif; ?>
        <?php if ($canViewCompliance): ?>
            <button type="button" data-filter="Compliance"><span class="dot" style="background:#d97706"></span>Compliance due</button>
            <button type="button" data-filter="Overdue"><span class="dot" style="background:#cc1f2c"></span>Overdue</button>
        <?php endif; ?>
    </div>
    <div class="text-muted small mb-2 sy-cal-filter-hint" style="display:none">Showing <strong class="sy-cal-filter-name"></strong> only — <a href="#" class="sy-cal-filter-clear">show all</a></div>

    <?php if ($canViewWeekHours): ?>
    <div class="sy-week-card" id="syWeekCard">
        <div class="sy-week-head">
            <div>
                <p class="sy-week-title mb-0">This Week's Work Time</p>
                <span class="text-muted small" id="syWeekRange"></span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="sy-week-total text-end">Total <br><strong id="syWeekTotal">0h 0m</strong></div>
                <div class="sy-week-nav">
                    <button type="button" id="syWeekPrev" title="Previous week">&lsaquo;</button>
                    <button type="button" id="syWeekToday" title="This week">•</button>
                    <button type="button" id="syWeekNext" title="Next week">&rsaquo;</button>
                </div>
            </div>
        </div>
        <div class="sy-week-days" id="syWeekDays"></div>
    </div>
    <?php endif; ?>

    <div id="calendar"></div>

    <div class="modal fade" id="syCalDayModal" tabindex="-1" aria-hidden="true">
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

    // Legend-as-filter: clicking a legend item shows only that category
    // (or, for "Overdue", only events flagged overdue via importance=0 —
    // see CalendarController::events(), the same signal both Task and
    // Compliance use for their red/overdue colouring). Clicking the same
    // item again clears the filter. Re-fetches on every filter change
    // (cheap, small JSON payload) rather than caching, so the calendar
    // never shows stale data if something changed elsewhere in the app.
    let activeFilter  = null;
    const filterHint  = document.querySelector('.sy-cal-filter-hint');
    const filterName  = document.querySelector('.sy-cal-filter-name');
    const filterButtons = document.querySelectorAll('.sy-cal-legend button');

    function matchesFilter(event) {
        if (! activeFilter) return true;
        if (activeFilter === 'Overdue') return (event.extendedProps.importance ?? 9) === 0;
        return event.extendedProps.type === activeFilter;
    }

    function updateLegendState() {
        filterButtons.forEach(function (btn) {
            const isActive = btn.dataset.filter === activeFilter;
            btn.classList.toggle('active', isActive);
            btn.classList.toggle('dimmed', !! activeFilter && ! isActive);
        });
        if (activeFilter) {
            filterName.textContent = activeFilter === 'Overdue' ? 'Overdue items' : activeFilter + (activeFilter === 'Meeting' ? 's' : ' due');
            filterHint.style.display = '';
        } else {
            filterHint.style.display = 'none';
        }
    }

    filterButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            activeFilter = (activeFilter === btn.dataset.filter) ? null : btn.dataset.filter;
            updateLegendState();
            calendar.refetchEvents();
        });
    });
    document.querySelector('.sy-cal-filter-clear').addEventListener('click', function (e) {
        e.preventDefault();
        activeFilter = null;
        updateLegendState();
        calendar.refetchEvents();
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
            const time = e.allDay ? '' : e.start.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

            // Built as real DOM nodes with .textContent (not innerHTML
            // string concatenation) — e.title is a task/meeting/
            // compliance-item title a normal user can set to anything,
            // so it must never be interpreted as HTML here.
            const dot = document.createElement('span');
            dot.className = 'dot';
            dot.style.background = e.backgroundColor;

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
            a.className = 'sy-cal-modal-item';
            a.append(dot, body);
            modalListEl.appendChild(a);
        });

        syCalModal.show();
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' },
        height: 'auto',
        // Only the top few events per day render directly on the month
        // grid (most important first via eventOrder) — the rest are
        // reached via "+N more" or by clicking the day itself.
        dayMaxEvents: 3,
        eventOrder: 'extendedProps.importance,start',
        events: function (info, successCallback, failureCallback) {
            fetch(eventsUrl).then(function (r) { return r.json(); }).then(function (data) {
                successCallback(data.filter(matchesFilter));
            }).catch(failureCallback);
        },
        dateClick: function (info) {
            selectDate(info.dateStr);
            const dayEvents = eventsOnDate(info.dateStr);
            if (dayEvents.length) {
                openDayModal(info.dateStr, dayEvents);
            }
        },
        // Without this, FullCalendar's own default url-follow behaviour
        // is inconsistent across the three views (month/week/list) and
        // can be swallowed by dayMaxEvents' "+N more" popover — so a
        // click on an event itself did nothing in practice. Explicit and
        // unconditional: every event from CalendarController::events()
        // (task/meeting/compliance) always carries a real `url` to its
        // existing show page.
        eventClick: function (info) {
            if (info.event.url) {
                info.jsEvent.preventDefault();
                window.location.href = info.event.url;
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

    // Weekly time/work view — independent of the calendar above, own
    // fetch/nav state. Reuses check_in/check_out attendance data via
    // CalendarController::weekHours(); no new time-tracking records.
    const weekCard = document.getElementById('syWeekCard');
    if (weekCard) {
        const weekHoursUrl = '<?= site_url('calendar/week-hours') ?>';
        const daysEl   = document.getElementById('syWeekDays');
        const rangeEl  = document.getElementById('syWeekRange');
        const totalEl  = document.getElementById('syWeekTotal');
        let weekAnchor = toISODate(new Date());

        function fmtHours(mins) {
            return Math.floor(mins / 60) + 'h ' + (mins % 60) + 'm';
        }

        function fmtRange(start, end) {
            const s = new Date(start + 'T00:00:00'), e = new Date(end + 'T00:00:00');
            const opts = { month: 'short', day: 'numeric' };
            return s.toLocaleDateString(undefined, opts) + ' – ' + e.toLocaleDateString(undefined, opts);
        }

        function loadWeek() {
            fetch(weekHoursUrl + '?week=' + weekAnchor).then(r => r.json()).then(data => {
                rangeEl.textContent = fmtRange(data.weekStart, data.weekEnd);
                totalEl.textContent = fmtHours(data.totalMinutes);

                const maxMins = Math.max(60, ...data.days.map(d => d.minutes));
                const today = toISODate(new Date());

                daysEl.innerHTML = '';
                data.days.forEach(d => {
                    const pct = d.minutes > 0 ? Math.max(6, Math.round((d.minutes / maxMins) * 100)) : 0;

                    const col = document.createElement('div');
                    col.className = 'sy-week-day' + (d.date === today ? ' is-today' : '');

                    const dow = document.createElement('div');
                    dow.className = 'dow';
                    dow.textContent = d.label;

                    const track = document.createElement('div');
                    track.className = 'bar-track';
                    const bar = document.createElement('div');
                    bar.className = 'bar' + (d.minutes === 0 ? ' is-empty' : '');
                    bar.style.height = (d.minutes > 0 ? pct : 4) + '%';
                    bar.title = d.minutes > 0 ? fmtHours(d.minutes) : (d.status ? d.status.replace('_', ' ') : 'No record');
                    track.appendChild(bar);

                    const hrs = document.createElement('div');
                    hrs.className = 'hrs';
                    hrs.textContent = d.minutes > 0 ? fmtHours(d.minutes) : '—';

                    col.append(dow, track, hrs);
                    daysEl.appendChild(col);
                });
            });
        }

        document.getElementById('syWeekPrev').addEventListener('click', function () {
            weekAnchor = date_add(weekAnchor, -7);
            loadWeek();
        });
        document.getElementById('syWeekNext').addEventListener('click', function () {
            weekAnchor = date_add(weekAnchor, 7);
            loadWeek();
        });
        document.getElementById('syWeekToday').addEventListener('click', function () {
            weekAnchor = toISODate(new Date());
            loadWeek();
        });

        function date_add(dateStr, days) {
            const d = new Date(dateStr + 'T00:00:00');
            d.setDate(d.getDate() + days);
            return toISODate(d);
        }

        loadWeek();
    }
});
</script>
<?= $this->endSection() ?>
