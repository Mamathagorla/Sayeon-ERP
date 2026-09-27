<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    #calendar { background: var(--sy-surface); padding: 1rem; border-radius: .375rem; }
    .fc-event { cursor: pointer; }
    .sy-cal-legend span { display: inline-flex; align-items: center; gap: 6px; color: var(--sy-ink); font-weight: 600; }
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
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php if (! $canViewTasks && ! $canViewMeetings && ! $canViewCompliance): ?>
    <p class="text-muted">You don't have permission to view any calendar data yet.</p>
<?php else: ?>
    <div class="d-flex gap-3 mb-3 small flex-wrap sy-cal-legend">
        <?php if ($canViewTasks): ?><span><span class="dot" style="background:#5b6472"></span>Task due</span><?php endif; ?>
        <?php if ($canViewMeetings): ?><span><span class="dot" style="background:#2563eb"></span>Meeting</span><?php endif; ?>
        <?php if ($canViewCompliance): ?>
            <span><span class="dot" style="background:#d97706"></span>Compliance due</span>
            <span><span class="dot" style="background:#cc1f2c"></span>Overdue</span>
        <?php endif; ?>
    </div>
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
            const a    = document.createElement('a');
            a.href      = e.url || '#';
            a.className = 'sy-cal-modal-item';
            a.innerHTML = '<span class="dot" style="background:' + e.backgroundColor + '"></span>'
                + '<span class="flex-grow-1">'
                +   '<span class="title">' + e.title + '</span>'
                +   '<span class="meta">' + (e.extendedProps.type || '') + (time ? ' · ' + time : '') + '</span>'
                + '</span>';
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
            fetch(eventsUrl).then(function (r) { return r.json(); }).then(successCallback).catch(failureCallback);
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
