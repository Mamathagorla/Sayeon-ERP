<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('project.create')): ?>
<a href="<?= site_url('projects/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Project</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 filter-form">
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
                        <option value="<?= $s ?>" <?= (($filters['status'] ?? '') === $s) ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($projects)): ?>
            <p class="text-muted mb-0">No projects yet.</p>
        <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Owner</th>
                    <th>Tasks</th>
                    <th>Start</th>
                    <th>End</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($projects as $p): ?>
                <tr>
                    <td>
                        <a href="<?= site_url('projects/' . $p['id']) ?>"><?= esc($p['name']) ?></a>
                        <?php if (! empty($p['description'])): ?>
                            <div class="text-muted small"><?= esc($p['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($p['company_name'] ?? '—') ?></td>
                    <td>
                        <span class="badge bg-<?= ['active' => 'success', 'completed' => 'secondary', 'on_hold' => 'warning', 'cancelled' => 'danger'][$p['status']] ?>">
                            <?= esc(ucwords(str_replace('_', ' ', $p['status']))) ?>
                        </span>
                    </td>
                    <td><?= esc($p['owner_name'] ?? '—') ?></td>
                    <td><a href="<?= site_url('tasks?project_id=' . $p['id']) ?>"><?= (int) $p['task_count'] ?></a></td>
                    <td><?= $p['start_date'] ? esc(date('d/m/Y', strtotime($p['start_date']))) : '—' ?></td>
                    <td><?= $p['end_date'] ? esc(date('d/m/Y', strtotime($p['end_date']))) : '—' ?></td>
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
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
