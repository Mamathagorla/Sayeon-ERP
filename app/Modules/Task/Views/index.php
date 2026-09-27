<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (in_array('task.create', session('permissions') ?? [], true) || session('roleSlug') === 'super_admin'): ?>
<a href="<?= site_url('tasks/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Task</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php if ($isPersonalScope): ?>
<div class="alert alert-info py-2 small"><i class="fas fa-user me-1"></i>Showing only tasks assigned to you.</div>
<?php endif; ?>

<style>
    .sy-task-filters .form-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--sy-muted); margin-bottom: 4px; }
    .sy-task-filters .form-select, .sy-task-filters .form-control { font-size: .85rem; }
    .sy-task-overdue { display: flex; align-items: center; gap: 8px; height: 33px; padding: 0 12px; border-radius: var(--sy-radius-sm); border: 1px solid var(--sy-border); background: var(--sy-surface); cursor: pointer; }
    .sy-task-overdue input { cursor: pointer; }
    .sy-task-overdue label { font-size: .82rem; font-weight: 600; color: var(--sy-ink); cursor: pointer; margin: 0; white-space: nowrap; }
    .sy-task-overdue:has(input:checked) { border-color: var(--sy-accent-ink); background: var(--sy-accent-soft); }
    .sy-task-clear { display: inline-flex; align-items: center; gap: 6px; font-size: .82rem; font-weight: 600; color: var(--sy-accent-ink); text-decoration: none; height: 33px; }
</style>

<div class="sy-card mb-3 sy-task-filters" style="height:auto">
    <div class="sy-card-body">
        <form method="get" class="row g-3 align-items-end filter-form">
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label">Company</label>
                <select name="company_id" class="form-select form-select-sm">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (($filters['company_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= (($filters['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label">Project</label>
                <select name="project_id" class="form-select form-select-sm">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (($filters['project_id'] ?? '') == $p['id']) ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label">Status</label>
                <?php $selectedStatuses = (array) ($filters['status'] ?? []); ?>
                <div class="dropdown sy-msel" data-placeholder="All Statuses">
                    <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span class="sy-msel-label">All Statuses</span>
                    </button>
                    <div class="dropdown-menu sy-msel-menu">
                        <?php foreach ($statuses as $s): ?>
                            <label class="sy-msel-item" data-label="<?= esc(ucwords(str_replace('_', ' ', $s))) ?>">
                                <input type="checkbox" class="sy-msel-opt" name="status[]" value="<?= $s ?>" <?= in_array($s, $selectedStatuses, true) ? 'checked' : '' ?>>
                                <?= esc(ucwords(str_replace('_', ' ', $s))) ?>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label">Priority</label>
                <?php $selectedPriorities = (array) ($filters['priority'] ?? []); ?>
                <div class="dropdown sy-msel" data-placeholder="All Priorities">
                    <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span class="sy-msel-label">All Priorities</span>
                    </button>
                    <div class="dropdown-menu sy-msel-menu">
                        <?php foreach ($priorities as $p): ?>
                            <label class="sy-msel-item" data-label="<?= esc(ucfirst($p)) ?>">
                                <input type="checkbox" class="sy-msel-opt" name="priority[]" value="<?= $p ?>" <?= in_array($p, $selectedPriorities, true) ? 'checked' : '' ?>>
                                <?= esc(ucfirst($p)) ?>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                    </div>
                </div>
            </div>
            <?php if (! $isPersonalScope): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label">Assigned To</label>
                <?php $selectedAssignees = array_map('strval', (array) ($filters['assigned_to'] ?? [])); ?>
                <div class="dropdown sy-msel" data-placeholder="Anyone">
                    <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span class="sy-msel-label">Anyone</span>
                    </button>
                    <div class="dropdown-menu sy-msel-menu">
                        <?php foreach ($users as $u): ?>
                            <label class="sy-msel-item" data-label="<?= esc($u['name']) ?>">
                                <input type="checkbox" class="sy-msel-opt" name="assigned_to[]" value="<?= $u['id'] ?>" <?= in_array((string) $u['id'], $selectedAssignees, true) ? 'checked' : '' ?>>
                                <?= esc($u['name']) ?>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-auto">
                <label class="sy-task-overdue mb-0">
                    <input type="checkbox" name="overdue" value="1" class="form-check-input m-0" id="overdueChk" <?= ! empty($filters['overdue']) ? 'checked' : '' ?>>
                    <span><i class="fas fa-triangle-exclamation me-1 text-danger"></i>Overdue only</span>
                </label>
            </div>
            <?php if (array_filter($filters)): ?>
            <div class="col-auto">
                <a href="<?= site_url('tasks') ?>" class="sy-task-clear"><i class="fas fa-rotate-left"></i>Clear filters</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-striped table-hover" id="tasksTable">
            <thead>
                <tr><th>Title</th><th>Company</th><th>Project</th><th>Department</th><th>Assigned To</th><th>Priority</th><th>Due Date</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $t): ?>
                <?php $isOverdue = $t['due_date'] && $t['due_date'] < date('Y-m-d') && ! in_array($t['status'], ['completed', 'cancelled'], true); ?>
                <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                    <td><a href="<?= site_url('tasks/' . $t['id']) ?>"><?= esc($t['title']) ?></a></td>
                    <td><?= esc($t['company_name']) ?></td>
                    <td><?php if ($t['project_id']): ?><a href="<?= site_url('projects/' . $t['project_id']) ?>"><?= esc($t['project_name']) ?></a><?php else: ?>—<?php endif; ?></td>
                    <td><?= esc($t['department_name']) ?></td>
                    <td><?= esc($t['assignee_name'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= ['low' => 'secondary', 'medium' => 'info', 'high' => 'warning', 'urgent' => 'danger'][$t['priority']] ?>"><?= esc(ucfirst($t['priority'])) ?></span></td>
                    <td><?= $t['due_date'] ? esc(date('d/m/Y', strtotime($t['due_date']))) : '—' ?></td>
                    <td><span class="badge bg-dark"><?= esc(ucwords(str_replace('_', ' ', $t['status']))) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>$(function () { $('#tasksTable').DataTable({ order: [] }); });</script>
<?= $this->endSection() ?>
