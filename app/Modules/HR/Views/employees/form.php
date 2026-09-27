<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form action="<?= $employee ? site_url('hr/employees/' . $employee['id']) : site_url('hr/employees') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">User</label>
                    <select name="user_id" class="form-select" required <?= $employee ? 'disabled' : '' ?>>
                        <option value="">Select user</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (($employee['user_id'] ?? null) == $u['id']) ? 'selected' : '' ?>><?= esc($u['name']) ?> (<?= esc($u['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($employee): ?><input type="hidden" name="user_id" value="<?= $employee['user_id'] ?>"><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Employee Code</label>
                    <input type="text" name="employee_code" class="form-control" value="<?= esc($employee['employee_code'] ?? $nextCode) ?>" maxlength="20" pattern="[A-Za-z0-9\-]*" title="Letters, numbers and hyphens only" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($employee['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
