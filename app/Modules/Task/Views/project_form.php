<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-7">
    <div class="card-body">
        <form data-erp-sections data-erp-first-section="Project Details" action="<?= $project ? site_url('projects/' . $project['id']) : site_url('projects') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-12 mb-3">
                    <label class="form-label">Project Name</label>
                    <input type="text" name="name" class="form-control" value="<?= esc($project['name'] ?? old('name')) ?>" required>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= esc($project['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Project Code <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="project_code" id="projectCodeInput" class="form-control" value="<?= esc($project['project_code'] ?? old('project_code')) ?>" maxlength="30">
                    <?php if (! $project): ?><div class="form-text">Suggested once a company is picked.</div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" id="projectCompanySelect" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($project['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 erp-section-marker" data-section-title="Status &amp; Timeline"></div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= (($project['status'] ?? 'active') === $s) ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Owner</label>
                    <select name="owner_id" id="projectOwnerSelect" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" data-company-id="<?= esc($u['company_id'] ?? '', 'attr') ?>" <?= (($project['owner_id'] ?? null) == $u['id']) ? 'selected' : '' ?>><?= esc($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= esc($project['start_date'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= esc($project['end_date'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $project ? 'Update Project' : 'Create Project' ?></button>
            <a href="<?= site_url('projects') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const companySelect = document.getElementById('projectCompanySelect');

    <?php if (! $project): ?>
    const nextCodeByCompany = <?= json_encode($nextCodeByCompany) ?>;
    const codeInput = document.getElementById('projectCodeInput');
    let codeEdited = false;
    codeInput.addEventListener('input', function () { codeEdited = true; });
    companySelect.addEventListener('change', function () {
        if (codeEdited) return;
        const suggestion = nextCodeByCompany[companySelect.value];
        codeInput.value = suggestion || '';
    });
    <?php endif; ?>

    // Owner narrows to the selected Company — same pattern as the Task
    // form's Project/Assigned To filtering (see its own comment for why
    // this is getElementById, not a name= selector: the topbar's own
    // company switcher is also a <select name="company_id">).
    const ownerSelect = document.getElementById('projectOwnerSelect');
    if (ownerSelect) {
        const ownerOptions = Array.from(ownerSelect.options).filter(function (o) { return o.value !== ''; });

        function filterOwners() {
            const companyId = companySelect.value;
            const currentValue = ownerSelect.value;
            let stillValid = false;

            ownerOptions.forEach(function (option) {
                const matches = ! companyId || option.dataset.companyId === companyId;
                option.hidden = ! matches;
                if (matches && option.value === currentValue) stillValid = true;
            });

            if (! stillValid) ownerSelect.value = '';
        }

        companySelect.addEventListener('change', filterOwners);
        filterOwners();
    }
});
</script>
<?= $this->endSection() ?>
