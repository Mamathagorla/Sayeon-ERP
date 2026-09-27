<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form action="<?= $task ? site_url('tasks/' . $task['id']) : site_url('tasks') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-12 mb-3">
                    <label class="form-label">Task Title</label>
                    <input type="text" name="title" class="form-control" value="<?= esc($task['title'] ?? old('title')) ?>" required>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= esc($task['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" id="taskCompanySelect" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($task['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select" required>
                        <option value="">Select department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= (($task['department_id'] ?? null) == $d['id']) ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Project</label>
                    <select name="project_id" id="projectSelect" class="form-select" required>
                        <option value="">Select project</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" data-company-id="<?= $p['company_id'] ?>" <?= (($task['project_id'] ?? null) == $p['id']) ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($projects)): ?>
                        <div class="form-text text-danger">No projects exist yet for this company — <a href="<?= site_url('projects/create') ?>">create one first</a>.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select" required>
                        <?php foreach ($priorities as $p): ?>
                            <option value="<?= $p ?>" <?= (($task['priority'] ?? 'medium') === $p) ? 'selected' : '' ?>><?= esc(ucfirst($p)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Assigned To</label>
                    <select name="assigned_to" id="assignedToSelect" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" data-company-id="<?= $u['company_id'] ?? '' ?>" <?= (($task['assigned_to'] ?? null) == $u['id']) ? 'selected' : '' ?>><?= esc($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= esc($task['start_date'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= esc($task['due_date'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= (($task['status'] ?? 'new') === $s) ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $task ? 'Update Task' : 'Create Task' ?></button>
            <a href="<?= site_url('tasks') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // getElementById (not a name= selector) — the topbar's own "viewing
    // company" switcher (Super Admin only, see layouts/main.php) is also
    // a <select name="company_id">, rendered before this form in the
    // page. A querySelector by name silently bound to that one instead
    // of this form's own field, so for Super Admin the Project/Assigned
    // To lists never actually filtered as this company field changed.
    const companySelect = document.getElementById('taskCompanySelect');
    if (! companySelect) return;

    // Narrows a dropdown to only the options whose data-company-id
    // matches the currently selected company — used for both Project
    // and Assigned To, since a task can only ever be accepted if both
    // belong to the same company as the task itself (server-side
    // validation in TaskController rejects the mismatch otherwise).
    function makeCompanyFilter(select) {
        if (! select) return function () {};

        const allOptions = Array.from(select.options).filter(function (o) { return o.value !== ''; });

        return function () {
            const companyId = companySelect.value;
            const currentValue = select.value;
            let stillValid = false;

            allOptions.forEach(function (option) {
                const matches = ! companyId || option.dataset.companyId === companyId;
                option.hidden = ! matches;
                if (matches && option.value === currentValue) stillValid = true;
            });

            if (! stillValid) select.value = '';
        };
    }

    const filterProjects = makeCompanyFilter(document.getElementById('projectSelect'));
    const filterAssignees = makeCompanyFilter(document.getElementById('assignedToSelect'));

    companySelect.addEventListener('change', function () {
        filterProjects();
        filterAssignees();
    });
    filterProjects();
    filterAssignees();
});
</script>
<?= $this->endSection() ?>
