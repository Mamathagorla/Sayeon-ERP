<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('project.edit')): ?>
<a href="<?= site_url('projects/' . $project['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<?php endif; ?>
<a href="<?= site_url('projects') ?>" class="btn btn-light btn-sm">Back to list</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-3">
    <div class="col-md-3"><strong>Company:</strong> <?= esc($project['company_name'] ?? '—') ?></div>
    <div class="col-md-3">
        <strong>Status:</strong>
        <span class="badge bg-<?= ['active' => 'success', 'completed' => 'secondary', 'on_hold' => 'warning', 'cancelled' => 'danger'][$project['status']] ?>">
            <?= esc(ucwords(str_replace('_', ' ', $project['status']))) ?>
        </span>
    </div>
    <div class="col-md-3"><strong>Owner:</strong> <?= esc($project['owner_name'] ?? '—') ?></div>
    <div class="col-md-3"><strong>Tasks:</strong> <?= $taskCounts['completed'] ?>/<?= $taskCounts['total'] ?> completed</div>
</div>

<?php if (! empty($project['description'])): ?>
<div class="card mb-3">
    <div class="card-body">
        <p class="mb-0"><?= nl2br(esc($project['description'])) ?></p>
    </div>
</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body row">
        <div class="col-md-6"><strong>Start Date:</strong> <?= $project['start_date'] ? esc(date('d/m/Y', strtotime($project['start_date']))) : '—' ?></div>
        <div class="col-md-6"><strong>End Date:</strong> <?= $project['end_date'] ? esc(date('d/m/Y', strtotime($project['end_date']))) : '—' ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Tasks in this project</strong>
        <?php if (can('task.create')): ?>
        <a href="<?= site_url('tasks/create') ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>Add Task</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($tasks)): ?>
            <p class="text-muted mb-0">No tasks linked to this project yet.</p>
        <?php else: ?>
        <table class="table table-striped table-hover">
            <thead>
                <tr><th>Title</th><th>Department</th><th>Assigned To</th><th>Priority</th><th>Due Date</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $t): ?>
                <?php $isOverdue = $t['due_date'] && $t['due_date'] < date('Y-m-d') && ! in_array($t['status'], ['completed', 'cancelled'], true); ?>
                <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                    <td><a href="<?= site_url('tasks/' . $t['id']) ?>"><?= esc($t['title']) ?></a></td>
                    <td><?= esc($t['department_name']) ?></td>
                    <td><?= esc($t['assignee_name'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= ['low' => 'secondary', 'medium' => 'info', 'high' => 'warning', 'urgent' => 'danger'][$t['priority']] ?>"><?= esc(ucfirst($t['priority'])) ?></span></td>
                    <td><?= $t['due_date'] ? esc(date('d/m/Y', strtotime($t['due_date']))) : '—' ?></td>
                    <td><span class="badge bg-dark"><?= esc(ucwords(str_replace('_', ' ', $t['status']))) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
