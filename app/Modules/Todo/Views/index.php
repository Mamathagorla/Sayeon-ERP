<?= $this->extend('layouts/main') ?>

<?php
$today = date('Y-m-d');

$navLinks = [
    'all'      => ['label' => 'All To-Dos', 'icon' => 'fa-list-check'],
    'today'    => ['label' => 'Today',      'icon' => 'fa-calendar-day'],
    'upcoming' => ['label' => 'Upcoming',   'icon' => 'fa-calendar-week'],
    'overdue'  => ['label' => 'Overdue',    'icon' => 'fa-triangle-exclamation'],
    'completed' => ['label' => 'Completed', 'icon' => 'fa-check-double'],
    'starred'  => ['label' => 'Starred',    'icon' => 'fa-star'],
];
$priorityLinks = [
    'important' => ['label' => 'Important', 'dot' => 'var(--sy-danger)'],
    'normal'    => ['label' => 'Normal',    'dot' => 'var(--sy-success)'],
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

<?= $this->section('pageActions') ?>
<a href="<?= site_url('todos/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-circle-plus me-1"></i>Add To-Do</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
    .sy-todo-nav a { display: flex; justify-content: space-between; align-items: center; padding: 9px 12px; border-radius: var(--sy-radius-sm); font-size: .86rem; font-weight: 600; text-decoration: none; color: var(--sy-ink) !important; }
    .sy-todo-nav a:hover { background: var(--sy-hover-bg); }
    .sy-todo-nav a.active { background: var(--sy-accent-soft); color: var(--sy-accent-ink) !important; }
    .sy-todo-nav .count { min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; background: var(--sy-hover-bg); color: var(--sy-muted); font-size: .7rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
    .sy-todo-nav a.active .count { background: var(--sy-accent-ink); color: #fff; }
    .sy-todo-sec { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--sy-muted); margin: 16px 12px 6px; padding-top: 14px; border-top: 1px solid var(--sy-border-soft); }
    .sy-todo-sec:first-child { margin-top: 0; padding-top: 0; border-top: 0; }
    .sy-todo-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 10px; flex: none; }
    .sy-todo-clear { font-size: .78rem; font-weight: 600; color: var(--sy-accent-ink); text-decoration: none; white-space: nowrap; }
    .sy-todo-row td { vertical-align: middle; }
    .sy-todo-check { width: 19px; height: 19px; border: 2px solid var(--sy-border); border-radius: 6px; background: none; display: inline-flex; align-items: center; justify-content: center; color: transparent; flex: none; cursor: pointer; }
    .sy-todo-check.done { background: var(--sy-success); border-color: var(--sy-success); color: #fff; }
    .sy-todo-page { min-width: 30px; height: 30px; padding: 0 6px; border-radius: 8px; border: 1px solid var(--sy-border); background: var(--sy-surface); color: var(--sy-ink); font-size: .8rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
    .sy-todo-page.active { background: var(--sy-accent-ink); border-color: var(--sy-accent-ink); color: #fff; }
    .sy-todo-page:not(.active):hover { background: var(--sy-hover-bg); }
    .sy-todo-page.disabled { opacity: .4; pointer-events: none; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <p class="text-muted mb-0">Stay organized, track your to-dos and get things done.</p>
    <span class="sy-week-chip"><i class="fas fa-calendar-days me-2" style="color:var(--sy-accent-ink)"></i><?= esc(date('D, d/m/Y')) ?></span>
</div>

<!-- KPI cards -->
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <?php foreach ([
        ['fa-list-check', 'TOTAL TO-DOS', (int) $counts['all'], 'Not trashed', 'all'],
        ['fa-hourglass-half', 'PENDING', (int) $pendingCount, 'Not yet due', 'pending'],
        ['fa-triangle-exclamation', 'OVERDUE', (int) $counts['overdue'], 'Needs attention', 'overdue'],
        ['fa-check-double', 'COMPLETED', (int) $counts['completed'], 'Done', 'completed'],
    ] as [$icon, $kLabel, $value, $sub, $kFilter]): ?>
    <div class="col">
        <a href="<?= $qs(['filter' => $kFilter, 'page' => null]) ?>" class="text-decoration-none">
            <div class="sy-stat-card sy-hero">
                <div class="sy-stat-icon"><i class="fas <?= $icon ?>"></i></div>
                <div class="sy-stat-label"><?= $kLabel ?></div>
                <div class="sy-stat-value"><?= $value ?></div>
                <div class="sy-stat-trend"><?= $sub ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <!-- Left: nav / priority / filters -->
    <div class="col-lg-3 sy-todo-left">
        <div class="sy-card" style="height:auto">
            <div class="sy-card-body">
                <div class="sy-todo-sec">My To-Dos</div>
                <div class="sy-todo-nav">
                    <?php foreach ($navLinks as $key => $meta): ?>
                    <a href="<?= $qs(['filter' => $key, 'page' => null]) ?>" class="<?= $filter === $key ? 'active' : '' ?>">
                        <span><i class="fas <?= $meta['icon'] ?> me-2"></i><?= esc($meta['label']) ?></span>
                        <span class="count"><?= (int) ($counts[$key] ?? 0) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>

                <div class="sy-todo-sec">Priority</div>
                <div class="sy-todo-nav">
                    <?php foreach ($priorityLinks as $key => $meta): ?>
                    <a href="<?= $qs(['filter' => $key, 'page' => null]) ?>" class="<?= $filter === $key ? 'active' : '' ?>">
                        <span><span class="sy-todo-dot" style="background:<?= $meta['dot'] ?>"></span><?= esc($meta['label']) ?></span>
                        <span class="count"><?= (int) $counts[$key] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>

                <div class="sy-todo-sec">Other</div>
                <div class="sy-todo-nav">
                    <a href="<?= $qs(['filter' => 'trashed', 'page' => null]) ?>" class="<?= $filter === 'trashed' ? 'active' : '' ?>">
                        <span><i class="fas fa-trash me-2"></i>Trash</span>
                        <span class="count"><?= (int) $counts['trashed'] ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Center: search + filters + table -->
    <div class="col-lg-9">
        <div class="sy-card" style="height:auto">
            <div class="sy-card-body">
                <form method="get" action="<?= site_url('todos') ?>" class="row g-2 align-items-end mb-3 filter-form" id="todoToolbar">
                    <input type="hidden" name="filter" value="<?= esc($filter) ?>">
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Search</label>
                        <div class="position-relative">
                            <i class="fas fa-search position-absolute text-muted" style="left:12px;top:50%;transform:translateY(-50%);font-size:.8rem"></i>
                            <input type="text" name="q" class="form-control form-control-sm ps-4" placeholder="Search to-dos…" value="<?= esc($search) ?>">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Sort By</label>
                        <select name="sort" class="form-select form-select-sm">
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
                    <div class="col-6 col-md-2 d-flex align-items-center gap-3">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
                        <?php if ($sort !== 'default' || $from !== '' || $to !== '' || $search !== ''): ?>
                            <a href="<?= $qs(['sort' => null, 'from' => null, 'to' => null, 'q' => null, 'page' => null]) ?>" class="sy-todo-clear"><i class="fas fa-rotate-left me-1"></i>Clear</a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (empty($todos)): ?>
                    <p class="text-muted small mb-0"><?= esc($emptyMessages[$filter] ?? 'Nothing here.') ?></p>
                <?php else: ?>
                <div class="table-responsive">
                <table class="table table-hover mb-0 sy-todo-row" id="todoList">
                    <thead>
                        <tr><th></th><th>To-Do Title</th><th>Priority</th><th>Status</th><th>Due</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($todos as $t): ?>
                    <?php
                        $overdue = ! $t['is_completed'] && $t['due_date'] && $t['due_date'] < $today;
                        [$statusLabel, $statusBadge] = match (true) {
                            (bool) $t['is_trashed']   => ['Trashed', 'secondary'],
                            (bool) $t['is_completed'] => ['Completed', 'success'],
                            $overdue                  => ['Overdue', 'danger'],
                            default                   => ['Pending', 'warning'],
                        };
                        $isImportant = $t['priority'] === 'important';
                    ?>
                    <tr>
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
                            <div class="fw-semibold <?= $t['is_completed'] ? 'text-decoration-line-through text-muted' : '' ?>"><?= esc($t['title']) ?></div>
                            <?php if ($t['description']): ?>
                            <div class="text-muted small"><?= esc(mb_strimwidth($t['description'], 0, 70, '…')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= $isImportant ? 'danger' : 'success' ?>"><?= $isImportant ? 'Important' : 'Normal' ?></span></td>
                        <td><span class="badge bg-<?= $statusBadge ?>"><?= $statusLabel ?></span></td>
                        <td class="text-muted small text-nowrap"><?= $t['due_date'] ? esc(date('d/m/Y', strtotime($t['due_date']))) : '—' ?></td>
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
                                    <button class="btn btn-sm border-0" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-vertical"></i></button>
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
                                            <form action="<?= site_url('todos/' . $t['id'] . '/trash') ?>" method="post">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Move to Trash</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <div class="dropdown">
                                    <button class="btn btn-sm border-0" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-vertical"></i></button>
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
                    <?php endforeach; ?>
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
    </div>

</div>
<?= $this->endSection() ?>
