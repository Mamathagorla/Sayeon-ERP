<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php if (! $employee && ! empty($onboardingCandidates)): ?>
<div class="alert alert-info col-lg-8 small">
    <strong>Completed onboarding candidates without an employee record:</strong>
    <?php foreach ($onboardingCandidates as $c): ?>
        <div class="d-flex align-items-center gap-2 mt-2">
            <a href="<?= site_url('hr/onboarding/' . $c['id']) ?>"><?= esc($c['candidate_name']) ?></a>
            <?php if (can('onboarding.edit') && can('employee.create')): ?>
            <form action="<?= site_url('hr/onboarding/' . $c['id'] . '/create-employee') ?>" method="post" class="d-inline" data-erp-form="off">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-primary py-0">Create Employee Record</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <div class="mt-2 text-muted">“Create Employee Record” creates (or links) the login and the employee profile from the candidate's onboarding details, then opens the profile so you can complete the remaining details.</div>
</div>
<?php endif; ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form data-erp-sections data-erp-first-section="Account &amp; Company" enctype="multipart/form-data" action="<?= $employee ? site_url('hr/employees/' . $employee['id']) : site_url('hr/employees') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">User</label>
                    <?php if ($employee): ?>
                        <input type="text" class="form-control" value="<?= esc(($employee['user_name'] ?? '') . (isset($employee['user_email']) ? ' (' . $employee['user_email'] . ')' : '')) ?>" disabled>
                        <input type="hidden" name="user_id" value="<?= $employee['user_id'] ?>">
                    <?php else: ?>
                        <?php $oldUserId = (string) old('user_id', ''); $oldUserLabel = ''; ?>
                        <datalist id="employeeUserList">
                            <?php foreach ($users as $u): ?>
                                <?php $uLabel = $u['name'] . ' (' . $u['email'] . ')'; if ((string) $u['id'] === $oldUserId) { $oldUserLabel = $uLabel; } ?>
                                <option value="<?= esc($uLabel) ?>" data-id="<?= (int) $u['id'] ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <input type="text" id="employeeUserInput" class="form-control" list="employeeUserList" autocomplete="off" placeholder="Type a name or email…" value="<?= esc($oldUserLabel) ?>" required>
                        <input type="hidden" name="user_id" id="employeeUserId" value="<?= esc($oldUserId) ?>">
                        <div class="form-text">Start typing — matching users appear as suggestions.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Employee Code</label>
                    <input type="text" name="employee_code" id="employeeCodeInput" class="form-control" value="<?= esc($employee['employee_code'] ?? $nextCode) ?>" maxlength="20" pattern="[A-Za-z0-9\-]*" title="Letters, numbers and hyphens only" required>
                    <?php if (! $employee): ?><div class="form-text">Suggested — each company has its own prefix/sequence; edit freely before saving.</div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" id="employeeCompanySelect" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($employee['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Employee Photo</label>
                    <div class="d-flex align-items-center gap-3">
                        <?php if (! empty($employee['user_avatar'])): ?>
                            <img src="<?= base_url($employee['user_avatar']) ?>" alt="" class="rounded-circle" style="width:48px;height:48px;object-fit:cover;">
                        <?php endif; ?>
                        <input type="file" name="photo" id="employeePhotoInput" class="form-control" accept="image/png,image/jpeg,image/webp">
                        <button type="button" id="employeePhotoClear" class="btn btn-outline-secondary btn-sm text-nowrap" hidden>Clear</button>
                    </div>
                    <?php if (! empty($employee['user_avatar'])): ?>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="employeeRemovePhoto">
                            <label class="form-check-label small" for="employeeRemovePhoto">Remove current photo</label>
                        </div>
                    <?php endif; ?>
                    <div class="form-text">Optional — JPG, PNG or WEBP, up to 2 MB.</div>
                </div>
                <div class="col-12 erp-section-marker" data-section-title="Employment Details"></div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= (($employee['department_id'] ?? null) == $d['id']) ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Designation</label>
                    <input type="text" name="designation" class="form-control" value="<?= esc($employee['designation'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Reporting Manager</label>
                    <select name="reporting_manager_id" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (($employee['reporting_manager_id'] ?? null) == $u['id']) ? 'selected' : '' ?>><?= esc($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Employment Type</label>
                    <select name="employment_type" class="form-select" required>
                        <?php foreach ($employmentTypes as $t): ?>
                            <option value="<?= $t ?>" <?= (($employee['employment_type'] ?? 'full_time') === $t) ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $t))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= (($employee['status'] ?? 'active') === $s) ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Date of Joining</label>
                    <input type="date" name="date_of_joining" class="form-control" value="<?= esc($employee['date_of_joining'] ?? '') ?>">
                </div>
                <div class="col-12 erp-section-marker" data-section-title="Personal &amp; Emergency Contact"></div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?= esc($employee['date_of_birth'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= esc($employee['address'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control" value="<?= esc($employee['emergency_contact_name'] ?? '') ?>" pattern="[A-Za-z\s.'\-]+" title="Letters, spaces, apostrophes, hyphens and periods only">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Emergency Contact Phone</label>
                    <input type="text" name="emergency_contact_phone" class="form-control" value="<?= esc($employee['emergency_contact_phone'] ?? '') ?>" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" title="Enter exactly 10 digits" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $employee ? 'Update Profile' : 'Create Profile' ?></button>
            <a href="<?= site_url('hr/employees') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>#employeeUserInput::-webkit-calendar-picker-indicator { display: none !important; }</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // User picker: type-to-search (no dropdown list). The chosen suggestion's id goes into the hidden user_id.
    var uIn = document.getElementById('employeeUserInput'), uId = document.getElementById('employeeUserId');
    if (uIn && uId) {
        var sync = function () {
            var id = '';
            document.querySelectorAll('#employeeUserList option').forEach(function (o) { if (o.value === uIn.value) { id = o.getAttribute('data-id'); } });
            uId.value = id;
            uIn.setCustomValidity(id || !uIn.value ? '' : 'Pick a user from the suggestions.');
        };
        uIn.addEventListener('input', sync);
        uIn.addEventListener('change', sync);
        sync();
    }
    // "Clear" un-picks a photo chosen in this form (before saving); the checkbox above removes the saved one.
    var input = document.getElementById('employeePhotoInput'), clear = document.getElementById('employeePhotoClear');
    if (!input || !clear) return;
    input.addEventListener('change', function () { clear.hidden = !input.value; });
    clear.addEventListener('click', function () { input.value = ''; clear.hidden = true; });
});
</script>
<?= $this->endSection() ?>

<?php if (! $employee): ?>
<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // company_id => suggested employee_code — only ever used to swap the
    // preview shown before saving; the field stays freely editable and
    // whatever is actually submitted is what gets saved.
    const nextCodeByCompany = <?= json_encode($nextCodeByCompany) ?>;
    const companySelect = document.getElementById('employeeCompanySelect');
    const codeInput      = document.getElementById('employeeCodeInput');
    let userEdited = false;

    codeInput.addEventListener('input', function () { userEdited = true; });
    companySelect.addEventListener('change', function () {
        if (userEdited) return; // don't clobber a value the admin already typed
        const suggestion = nextCodeByCompany[companySelect.value];
        if (suggestion) codeInput.value = suggestion;
    });
});
</script>
<?= $this->endSection() ?>
<?php endif; ?>
