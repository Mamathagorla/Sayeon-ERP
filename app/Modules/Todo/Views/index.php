<?= $this->extend('layouts/main') ?>

<?php
$today = date('Y-m-d');

// One flat, grouped Status list — replaces the old split between a left
// sidebar's "My To-Dos"/"Priority"/"Other" nav-link groups and this
// form's own Search/Sort bar. Same underlying single-value ?filter=
// param/semantics as before (TodoModel::FILTERS is still one mutually
// exclusive enum, not a combinable pair) — this only changes where and
// how it's presented, not what it can express.
$statusGroups = [
    'My To-Dos' => [
        'all'      => 'All To-Dos',
        'today'    => 'Today',
        'upcoming' => 'Upcoming',
        'overdue'  => 'Overdue',
        'completed' => 'Completed',
        'starred'  => 'Starred',
    ],
    'Priority' => [
        'important' => 'Important',
        'normal'    => 'Normal',
    ],
    'Other' => [
        'trashed' => 'Trash',
    ],
];
$emptyMessages = [
    'all'       => "You're all caught up. Click \"Add To-Do\" to create a reminder.",
    'today'     => 'Nothing due today.',
    'upcoming'  => 'No upcoming to-dos.',
    'overdue'   => "Nothing overdue — you're on top of things.",
    'starred'   => 'No starred to-dos yet.',
    'important' => 'No important to-dos yet.',
    'normal'    => 'No normal-priority to-dos.',
    'completed' => 'Nothing completed yet.',
    'trashed'   => 'Trash is empty.',
];
$sortLabels = ['default' => 'Default', 'newest' => 'Newest first', 'oldest' => 'Oldest first', 'due' => 'Due date', 'title' => 'Title (A–Z)'];

// Every link on this page re-shares the current filters, only overriding
// the one param it controls (filter, sort, page, …) — built once here so
// pagination/sort/nav links can't silently drop the others.
$qs = static function (array $overrides) use ($filter, $search, $sort, $from, $to) {
    $params = array_filter(array_merge([
        'filter' => $filter, 'q' => $search, 'sort' => $sort, 'from' => $from, 'to' => $to,
    ], $overrides), static fn ($v) => $v !== '' && $v !== null);

    return site_url('todos') . (empty($params) ? '' : '?' . http_build_query($params));
};
?>

<?= $this->section('content') ?>

<style>
    .sy-todo-row td { vertical-align: middle; }
    .sy-todo-check { width: 19px; height: 19px; border: 2px solid var(--sy-border); border-radius: 6px; background: none; display: inline-flex; align-items: center; justify-content: center; color: transparent; flex: none; cursor: pointer; }
    .sy-todo-check.done { background: var(--sy-success); border-color: var(--sy-success); color: #fff; }
    .sy-todo-page { min-width: 30px; height: 30px; padding: 0 6px; border-radius: 8px; border: 1px solid var(--sy-border); background: var(--sy-surface); color: var(--sy-ink); font-size: .8rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
    .sy-todo-page.active { background: var(--sy-accent-ink); border-color: var(--sy-accent-ink); color: #fff; }
    .sy-todo-page:not(.active):hover { background: var(--sy-hover-bg); }
    .sy-todo-page.disabled { opacity: .4; pointer-events: none; }
    .sy-todo-sub-row td { background: var(--sy-hover-bg); }
    .sy-todo-sub-row .sy-todo-indent { display: inline-flex; align-items: center; padding-left: 28px; position: relative; }
    .sy-todo-sub-row .sy-todo-indent::before { content: "\21B3"; position: absolute; left: 8px; color: var(--sy-muted); font-size: .78rem; }
    .sy-todo-progress { display: inline-flex; align-items: center; gap: 6px; font-size: .72rem; font-weight: 700; color: var(--sy-muted); margin-left: 8px; white-space: nowrap; }
    .sy-todo-progress .bar { width: 46px; height: 5px; border-radius: 3px; background: var(--sy-hover-bg); overflow: hidden; }
    .sy-todo-progress .bar > span { display: block; height: 100%; background: var(--sy-success); }
    td.sy-todo-addsub-row { padding-top: 8px; padding-bottom: 8px; }
    .sy-todo-addsub-row form { display: flex; gap: 8px; align-items: center; padding-left: 28px; }
    .sy-todo-addsub-row input[type="text"] { flex: 1 1 auto; }
    .sy-todo-addsub-row .sy-todo-due-label { font-size: .76rem; color: var(--sy-muted); white-space: nowrap; }
    .sy-todo-addsub-row input[type="date"] { width: 150px; }
    .sy-todo-expand { background: none; border: 0; padding: 0; width: 18px; color: var(--sy-muted); cursor: pointer; flex: none; }
    .sy-todo-expand i { transition: transform .15s ease; }
    .sy-todo-expand.open i { transform: rotate(90deg); }
    .sy-todo-expand:disabled { visibility: hidden; }
    .sy-todo-children.d-none { display: none; }
    .sy-todo-addbar { display: flex; justify-content: flex-end; margin-bottom: 12px; }
    .sy-todo-addform { display: none; gap: 10px; align-items: flex-end; flex-wrap: wrap; padding: 14px; margin-bottom: 14px; background: var(--sy-hover-bg); border-radius: 8px; }
    .sy-todo-addform.open { display: flex; }
    .sy-todo-addform .field-title { flex: 1 1 220px; }
    .sy-todo-addform label { font-size: .76rem; font-weight: 600; color: var(--sy-muted); margin-bottom: 2px; display: block; }

    /* Dropdowns on this page: soft border, custom arrow, neutral focus ring.
       Overrides the red "active filter" highlight for these selects only. */
    .sy-todo-filters .form-label { font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--sy-muted); margin-bottom: 6px; }
    .app-content .sy-todo-select.form-select {
        padding: .3rem 2.3rem .3rem .85rem;
        border: 1px solid var(--sy-border);
        border-radius: 10px;
        background-color: var(--sy-surface);
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%238a929e' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='M4 6l4 4 4-4'/%3E%3C/svg%3E");
        background-position: right .75rem center;
        background-size: 14px 14px;
        background-repeat: no-repeat;
        color: var(--sy-ink);
        font-size: .84rem;
        font-weight: 500;
        box-shadow: none;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .app-content .sy-todo-select.form-select:hover:not(:focus) { border-color: var(--sy-border-strong); }
    .app-content .sy-todo-select.form-select:focus { border-color: var(--sy-ink); box-shadow: 0 0 0 3px rgba(31, 36, 44, .08); outline: none; }
    .app-content .sy-todo-filters .filter-form select.sy-todo-select:has(option[selected]:not([value=""])) { border-color: var(--sy-border); background-color: var(--sy-surface); color: var(--sy-ink); font-weight: 500; }
</style>

<div class="d-flex justify-content-end align-items-center flex-wrap gap-2 mb-3">
    <button type="button" class="sy-week-chip" data-erp-calendar="<?= date('Y-m-d') ?>" aria-haspopup="dialog"><i class="fas fa-calendar-days me-2" style="color:var(--sy-accent-ink)"></i><?= esc(date('D') . ', ' . erp_date(date('Y-m-d'))) ?></button>
</div>

<!-- All filters, above the list -->
<div class="sy-card mb-3 sy-todo-filters">
    <div class="sy-card-body">
        <form method="get" action="<?= site_url('todos') ?>" class="row g-2 align-items-end filter-form">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="filter" class="form-select form-select-sm sy-todo-select" onchange="this.form.submit()">
                    <?php foreach ($statusGroups as $groupLabel => $options): ?>
                        <optgroup label="<?= esc($groupLabel) ?>">
                            <?php foreach ($options as $key => $optLabel): ?>
                                <option value="<?= $key ?>" <?= $filter === $key ? 'selected' : '' ?>><?= esc($optLabel) ?> (<?= (int) ($counts[$key] ?? 0) ?>)</option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small mb-1">Search</label>
                <div class="position-relative">
                    <i class="fas fa-search position-absolute text-muted" style="left:12px;top:50%;transform:translateY(-50%);font-size:.8rem"></i>
                    <input type="text" name="q" class="form-control form-control-sm ps-4" placeholder="Search to-dos…" value="<?= esc($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Sort By</label>
                <select name="sort" class="form-select form-select-sm sy-todo-select">
                    <?php foreach ($sortLabels as $k => $sLabel): ?>
                        <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= esc($sLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Created From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= esc($from) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Created To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= esc($to) ?>">
            </div>
            <div class="col-12 d-flex gap-3 align-items-center">
                <?php if ($sort !== 'default' || $from !== '' || $to !== '' || $search !== '' || $filter !== 'all'): ?>
                    <a href="<?= site_url('todos') ?>" class="sy-todo-clear small fw-semibold text-decoration-none"><i class="fas fa-rotate-left me-1"></i>Clear all filters</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="sy-card" style="height:auto">
    <div class="sy-card-body">
        <div class="sy-todo-addbar">
            <button type="button" class="btn btn-primary btn-sm" id="syTodoAddToggle"><i class="fas fa-circle-plus me-1"></i>Add To-Do</button>
        </div>
        <form action="<?= site_url('todos') ?>" method="post" class="sy-todo-addform" id="syTodoAddForm">
            <?= csrf_field() ?>
            <input type="hidden" name="quick_add" value="1">
            <div class="field-title">
                <label for="syTodoAddTitle">Title</label>
                <input type="text" name="title" id="syTodoAddTitle" class="form-control form-control-sm" placeholder="What needs to be done?" maxlength="150" required>
            </div>
            <div>
                <label for="syTodoAddDue">Due Date</label>
                <input type="date" name="due_date" id="syTodoAddDue" class="form-control form-control-sm">
            </div>
            <div>
                <label for="syTodoAddPriority">Priority</label>
                <select name="priority" id="syTodoAddPriority" class="form-select form-select-sm sy-todo-select">
                    <option value="normal" selected>Normal</option>
                    <option value="important">Important</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add</button>
                <button type="button" class="btn btn-light btn-sm" id="syTodoAddCancel">Cancel</button>
            </div>
        </form>

        <?php if (empty($todos)): ?>
            <p class="text-muted small mb-0"><?= esc($emptyMessages[$filter] ?? 'Nothing here.') ?></p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0 sy-todo-row" id="todoList">
            <thead>
                <tr><th></th><th></th><th>To-Do Title</th><th>Priority</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php
            // One row (Parent or Sub-To-Do) — a closure rather than
            // duplicating this markup once per Parent and once per
            // Sub-To-Do, since the two need the exact same checkbox/
            // star/edit/trash actions (every existing route already
            // works on any todo id regardless of parent_id).
            $renderRow = function (array $t, bool $isSub = false, bool $hasChildRow = false, ?int $childrenGroupOf = null) use ($today, $progressByParent) {
                $overdue = ! $t['is_completed'] && $t['due_date'] && $t['due_date'] < $today;
                [$statusLabel, $statusBadge] = match (true) {
                    (bool) $t['is_trashed']   => ['Trashed', 'secondary'],
                    (bool) $t['is_completed'] => ['Completed', 'success'],
                    $overdue                  => ['Overdue', 'danger'],
                    default                   => ['Pending', 'warning'],
                };
                $isImportant = $t['priority'] === 'important';
                $progress    = ! $isSub ? ($progressByParent[$t['id']] ?? null) : null;

                // $childrenGroupOf tags this row as belonging to a
                // parent's collapsible sub-to-do group — hidden by
                // default, every row sharing one data-children-of value
                // toggled together by the expand chevron's JS.
                $rowClass = trim(($isSub ? 'sy-todo-sub-row ' : '') . ($childrenGroupOf !== null ? 'sy-todo-children d-none' : ''));
                ?>
                <tr class="<?= esc($rowClass, 'attr') ?>" <?= $childrenGroupOf !== null ? 'data-children-of="' . (int) $childrenGroupOf . '"' : '' ?>>
                    <td>
                        <?php if ($hasChildRow): ?>
                        <button type="button" class="sy-todo-expand" data-expand-target="<?= $t['id'] ?>" title="Show sub-to-dos"><i class="fas fa-chevron-right"></i></button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (! $t['is_trashed']): ?>
                        <form action="<?= site_url('todos/' . $t['id'] . '/toggle-complete') ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="sy-todo-check <?= $t['is_completed'] ? 'done' : '' ?>" title="<?= $t['is_completed'] ? 'Mark incomplete' : 'Mark complete' ?>">
                                <i class="fas fa-check" style="font-size:.62rem"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <i class="fa-solid fa-trash text-muted"></i>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="<?= $isSub ? 'sy-todo-indent' : '' ?>">
                            <span class="fw-semibold <?= $t['is_completed'] ? 'text-decoration-line-through text-muted' : '' ?>"><?= esc($t['title']) ?></span>
                            <?php if ($progress && $progress['total'] > 0): ?>
                            <span class="sy-todo-progress" title="<?= $progress['done'] ?>/<?= $progress['total'] ?> sub-to-dos completed">
                                <span class="bar"><span style="width:<?= $progress['percent'] ?>%"></span></span>
                                <?= $progress['done'] ?>/<?= $progress['total'] ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($t['description']): ?>
                        <div class="text-muted small <?= $isSub ? 'sy-todo-indent' : '' ?>"><?= esc(mb_strimwidth($t['description'], 0, 70, '…')) ?></div>
                        <?php endif; ?>
                        <?php if ($t['due_date']): ?>
                        <div class="text-muted small <?= $isSub ? 'sy-todo-indent' : '' ?>"><i class="far fa-calendar me-1"></i><span class="<?= $overdue ? 'text-danger fw-semibold' : '' ?>">Due <?= esc(erp_date($t['due_date'])) ?></span></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= $isImportant ? 'danger' : 'success' ?>"><?= $isImportant ? 'Important' : 'Normal' ?></span></td>
                    <td><span class="badge bg-<?= $statusBadge ?>"><?= $statusLabel ?></span></td>
                    <td class="text-end text-nowrap">
                        <div class="d-inline-flex align-items-center gap-2">
                        <?php if (! $t['is_trashed']): ?>
                            <form action="<?= site_url('todos/' . $t['id'] . '/toggle-star') ?>" method="post">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm border-0 p-0" title="<?= $t['is_starred'] ? 'Unstar' : 'Star' ?>">
                                    <i class="fa-star <?= $t['is_starred'] ? 'fa-solid text-warning' : 'fa-regular text-muted' ?>"></i>
                                </button>
                            </form>
                            <div class="dropdown">
                                <button class="btn btn-sm border-0" type="button" data-bs-toggle="dropdown" title="More options"><i class="fas fa-ellipsis-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?= site_url('todos/' . $t['id'] . '/edit') ?>"><i class="fas fa-pen me-2"></i>Edit</a></li>
                                    <li>
                                        <form action="<?= site_url('todos/' . $t['id'] . '/toggle-important') ?>" method="post">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="dropdown-item"><i class="fas fa-flag me-2"></i><?= $isImportant ? 'Mark Normal' : 'Mark Important' ?></button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="<?= site_url('todos/' . $t['id'] . '/trash') ?>" method="post" <?= (! $isSub && $progress && $progress['total'] > 0) ? 'onsubmit="return confirm(\'This will also move its sub-to-dos to Trash. Continue?\');"' : '' ?>>
                                            <?= csrf_field() ?>
                                            <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Move to Trash</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="dropdown">
                                <button class="btn btn-sm border-0" type="button" data-bs-toggle="dropdown" title="More options"><i class="fas fa-ellipsis-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <form action="<?= site_url('todos/' . $t['id'] . '/restore') ?>" method="post">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="dropdown-item"><i class="fas fa-rotate-left me-2"></i>Restore</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="<?= site_url('todos/' . $t['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Permanently delete this to-do? This cannot be undone.');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash-can me-2"></i>Delete Permanently</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php
            };

            foreach ($todos as $t):
                // Trashed Sub-To-Dos still need to be reachable here so
                // they can be restored/deleted individually — only the
                // "add a new one" control is withheld from an
                // already-trashed parent. A parent gets the expand
                // toggle whenever it isn't trashed, even with zero
                // existing children yet, since expanding is also how you
                // reach "Add Sub-To-Do" for the first one.
                $renderRow($t, false, ! $t['is_trashed']);

                if (! $t['is_trashed']) {
                    foreach ($childrenByParent[$t['id']] ?? [] as $child) {
                        $renderRow($child, true, false, (int) $t['id']);
                    }
                    ?>
                    <tr class="sy-todo-children d-none" data-children-of="<?= $t['id'] ?>">
                        <td colspan="6" class="sy-todo-addsub-row p-0">
                            <form action="<?= site_url('todos') ?>" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="quick_add" value="1">
                                <input type="hidden" name="priority" value="normal">
                                <input type="hidden" name="parent_id" value="<?= $t['id'] ?>">
                                <input type="text" name="title" class="form-control form-control-sm" placeholder="Add a sub-to-do…" maxlength="150" required>
                                <span class="sy-todo-due-label"><i class="far fa-calendar me-1"></i>Due Date</span>
                                <input type="date" name="due_date" class="form-control form-control-sm">
                                <button type="submit" class="btn btn-outline-secondary btn-sm text-nowrap"><i class="fas fa-plus me-1"></i>Add Sub-To-Do</button>
                            </form>
                        </td>
                    </tr>
                    <?php
                } else {
                    // Trashed parent: children (independently trashed or
                    // cascaded with it) still render flat, same as
                    // before — no expand/collapse for a trashed row.
                    foreach ($childrenByParent[$t['id']] ?? [] as $child) {
                        $renderRow($child, true);
                    }
                }
            endforeach;
            ?>
            </tbody>
        </table>
        </div>

        <?php if ($pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 sy-todo-pager">
            <span class="text-muted small">Showing <?= ($page - 1) * $perPage + 1 ?>–<?= min($page * $perPage, $total) ?> of <?= $total ?></span>
            <div class="d-flex gap-1">
                <a class="sy-todo-page <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $qs(['page' => $page - 1]) ?>"><i class="fas fa-chevron-left" style="font-size:.68rem"></i></a>
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <a class="sy-todo-page <?= $p === $page ? 'active' : '' ?>" href="<?= $qs(['page' => $p]) ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a class="sy-todo-page <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= $qs(['page' => $page + 1]) ?>"><i class="fas fa-chevron-right" style="font-size:.68rem"></i></a>
            </div>
        </div>
        <?php elseif ($total > 0): ?>
        <div class="text-muted small mt-3">Showing <?= $total ?> of <?= $total ?></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var addToggle = document.getElementById('syTodoAddToggle');
    var addForm   = document.getElementById('syTodoAddForm');
    var addCancel = document.getElementById('syTodoAddCancel');

    addToggle.addEventListener('click', function () {
        addForm.classList.add('open');
        addToggle.closest('.sy-todo-addbar').style.display = 'none';
        document.getElementById('syTodoAddTitle').focus();
    });
    addCancel.addEventListener('click', function () {
        addForm.classList.remove('open');
        addToggle.closest('.sy-todo-addbar').style.display = '';
        addForm.reset();
    });

    document.querySelectorAll('.sy-todo-expand').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-expand-target');
            // A parent can have any number of Sub-To-Dos plus the "Add
            // Sub-To-Do" row — every one of them shares this same
            // data-children-of value and must open/close together.
            var rows = document.querySelectorAll('[data-children-of="' + id + '"]');
            if (! rows.length) return;
            var willOpen = rows[0].classList.contains('d-none');
            rows.forEach(function (row) { row.classList.toggle('d-none', ! willOpen); });
            btn.classList.toggle('open', willOpen);
        });
    });
});
</script>
<?= $this->endSection() ?>
