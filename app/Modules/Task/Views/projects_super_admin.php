<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('project.create')): ?>
<a href="<?= site_url('projects/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Project</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?php
/**
 * Super Admin's own Projects view — same $projects array
 * ProjectController::index() already builds for every role (see
 * projects.php, the sibling view every other role still uses); this
 * just presents it with the richer stat-card + table treatment
 * Super Admin's cross-company view benefits from. No new queries: the
 * stat counts below are plain array_filter() over the already-fetched
 * $projects, not a fresh DB call.
 */
$statusMeta = [
    'active'    => ['label' => 'Active', 'badge' => 'success', 'color' => '#3a404b', 'bg' => '#eef0f3'],
    'completed' => ['label' => 'Completed', 'badge' => 'secondary', 'color' => '#16a34a', 'bg' => '#dcfce7'],
    'on_hold'   => ['label' => 'On Hold', 'badge' => 'warning', 'color' => '#d97706', 'bg' => '#ffedd5'],
    'cancelled' => ['label' => 'Cancelled', 'badge' => 'danger', 'color' => '#dc2626', 'bg' => '#fee2e2'],
];
$countByStatus = array_fill_keys(array_keys($statusMeta), 0);
foreach ($projects as $p) {
    if (isset($countByStatus[$p['status']])) {
        $countByStatus[$p['status']]++;
    }
}

$iconPalette = [
    ['icon' => 'fa-diagram-project', 'bg' => '#dbeafe', 'color' => '#2563eb'],
    ['icon' => 'fa-building-columns', 'bg' => '#dcfce7', 'color' => '#16a34a'],
    ['icon' => 'fa-graduation-cap', 'bg' => '#ffedd5', 'color' => '#d97706'],
    ['icon' => 'fa-comments', 'bg' => '#fce7f3', 'color' => '#db2777'],
    ['icon' => 'fa-cash-register', 'bg' => '#eef0f3', 'color' => '#3a404b'],
    ['icon' => 'fa-briefcase', 'bg' => '#e0f2fe', 'color' => '#0891b2'],
];
?>

<?= $this->section('content') ?>

<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:#e0f2fe;color:#0891b2"><i class="fas fa-diagram-project"></i></div>
            <div class="sy-stat-label">TOTAL PROJECTS</div>
            <div class="sy-stat-value"><?= count($projects) ?></div>
            <div class="sy-stat-trend">Across all companies</div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:<?= $statusMeta['active']['bg'] ?>;color:<?= $statusMeta['active']['color'] ?>"><i class="fas fa-bolt"></i></div>
            <div class="sy-stat-label">ACTIVE</div>
            <div class="sy-stat-value"><?= $countByStatus['active'] ?></div>
            <div class="sy-stat-trend">In progress now</div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:<?= $statusMeta['completed']['bg'] ?>;color:<?= $statusMeta['completed']['color'] ?>"><i class="fas fa-circle-check"></i></div>
            <div class="sy-stat-label">COMPLETED</div>
            <div class="sy-stat-value"><?= $countByStatus['completed'] ?></div>
            <div class="sy-stat-trend">Finished projects</div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:<?= $statusMeta['on_hold']['bg'] ?>;color:<?= $statusMeta['on_hold']['color'] ?>"><i class="fas fa-pause"></i></div>
            <div class="sy-stat-label">ON HOLD / CANCELLED</div>
            <div class="sy-stat-value"><?= $countByStatus['on_hold'] + $countByStatus['cancelled'] ?></div>
            <div class="sy-stat-trend">Needs attention</div>
        </div>
    </div>
</div>

<div class="sy-card mb-3">
    <div class="sy-card-body py-2">
        <form method="get" class="row g-2 filter-form align-items-center">
            <div class="col-md-3">
                <select name="company_id" class="form-select form-select-sm">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (($filters['company_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= (($filters['status'] ?? '') === $s) ? 'selected' : '' ?>><?= esc($statusMeta[$s]['label'] ?? ucwords(str_replace('_', ' ', $s))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="sy-card">
    <div class="sy-card-header"><strong>All Projects</strong> <span class="text-muted small"><?= count($projects) ?> total</span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($projects)): ?>
            <p class="text-muted mb-0 p-3">No projects yet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Project</th>
                    <th>Company</th>
                    <th>Project Lead</th>
                    <th>Tasks</th>
                    <th>Status</th>
                    <th>Start</th>
                    <th>End</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($projects as $p): ?>
                <?php
                    $icon = $iconPalette[$p['id'] % count($iconPalette)];
                    $meta = $statusMeta[$p['status']] ?? ['label' => ucwords(str_replace('_', ' ', $p['status'])), 'badge' => 'secondary'];
                    $initials = $p['owner_name'] ? mb_strtoupper(mb_substr($p['owner_name'], 0, 1)) : null;
                ?>
                <tr>
                    <td class="text-muted small">#PRJ<?= str_pad((string) $p['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;border-radius:9px;background:<?= $icon['bg'] ?>;color:<?= $icon['color'] ?>">
                                <i class="fas <?= $icon['icon'] ?>"></i>
                            </span>
                            <div>
                                <a href="<?= site_url('projects/' . $p['id']) ?>" class="text-decoration-none fw-semibold text-dark"><?= esc($p['name']) ?></a>
                                <?php if (! empty($p['description'])): ?>
                                    <div class="text-muted small"><?= esc($p['description']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="text-muted small"><?= esc($p['company_name'] ?? '—') ?></td>
                    <td>
                        <?php if ($p['owner_name']): ?>
                        <div class="d-flex align-items-center gap-2">
                            <span class="sy-avatar" style="width:26px;height:26px;font-size:.72rem"><?= esc($initials) ?></span>
                            <span class="small"><?= esc($p['owner_name']) ?></span>
                        </div>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="<?= site_url('tasks?project_id=' . $p['id']) ?>" class="text-decoration-none"><?= (int) $p['task_count'] ?></a></td>
                    <td><span class="badge bg-<?= $meta['badge'] ?>"><?= esc($meta['label']) ?></span></td>
                    <td class="text-muted small"><?= $p['start_date'] ? esc(date('d/m/Y', strtotime($p['start_date']))) : '—' ?></td>
                    <td class="text-muted small"><?= $p['end_date'] ? esc(date('d/m/Y', strtotime($p['end_date']))) : '—' ?></td>
                    <td class="text-end">
                        <?php if (can('project.edit')): ?>
                        <a href="<?= site_url('projects/' . $p['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (can('project.delete')): ?>
                        <form action="<?= site_url('projects/' . $p['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Delete this project?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
