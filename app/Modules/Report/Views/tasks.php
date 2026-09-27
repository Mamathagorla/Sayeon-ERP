<?= $this->extend('layouts/main') ?>

<?php $qs = http_build_query($filters); ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('reports/tasks/export/pdf?' . $qs) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-pdf me-1"></i>PDF</a>
<a href="<?= site_url('reports/tasks/export/csv?' . $qs) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-csv me-1"></i>CSV</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<form method="get" class="card mb-3 filter-form">
    <div class="card-body d-flex gap-2 flex-wrap align-items-end">
        <div>
            <label class="form-label small mb-1">Company</label>
            <select name="company_id" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (($filters['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Department</label>
            <select name="department_id" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (($filters['department_id'] ?? null) == $d['id']) ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= (($filters['status'] ?? null) === $s) ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <a href="<?= site_url('reports/tasks') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold"><?= $total ?></div><div class="text-muted small">Total Tasks</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold text-danger"><?= $overdue ?></div><div class="text-muted small">Overdue</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold text-success"><?= $byStatus['completed'] ?></div><div class="text-muted small">Completed</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold text-warning"><?= $byPriority['urgent'] + $byPriority['high'] ?></div><div class="text-muted small">High + Urgent Priority</div></div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <h6 class="card-title">By Status</h6>
        <div class="d-flex gap-3 flex-wrap small">
            <?php foreach ($byStatus as $s => $count): ?>
                <span class="badge bg-dark"><?= esc(ucwords(str_replace('_', ' ', $s))) ?>: <?= $count ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($tasks)): ?>
            <p class="text-muted mb-0">No tasks match these filters.</p>
        <?php else: ?>
        <table class="table table-striped table-sm">
            <thead><tr><th>Title</th><th>Company</th><th>Department</th><th>Assignee</th><th>Priority</th><th>Status</th><th>Due Date</th></tr></thead>
            <tbody>
            <?php foreach ($tasks as $t): ?>
                <tr class="<?= ($t['due_date'] && $t['due_date'] < date('Y-m-d') && ! in_array($t['status'], ['completed', 'cancelled'], true)) ? 'table-danger' : '' ?>">
                    <td><a href="<?= site_url('tasks/' . $t['id']) ?>"><?= esc($t['title']) ?></a></td>
                    <td><?= esc($t['company_name']) ?></td>
                    <td><?= esc($t['department_name']) ?></td>
                    <td><?= esc($t['assignee_name'] ?? 'Unassigned') ?></td>
                    <td><?= esc(ucfirst($t['priority'])) ?></td>
                    <td><?= esc(ucwords(str_replace('_', ' ', $t['status']))) ?></td>
                    <td><?= $t['due_date'] ? esc(date('d/m/Y', strtotime($t['due_date']))) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
