<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form action="<?= $record ? site_url('hr/onboarding/' . $record['id']) : site_url('hr/onboarding') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Candidate Name</label>
                    <input type="text" name="candidate_name" class="form-control" value="<?= esc($record['candidate_name'] ?? old('candidate_name')) ?>" pattern="[A-Za-z\s.'\-]+" title="Letters, spaces, apostrophes, hyphens and periods only" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="candidate_email" class="form-control" value="<?= esc($record['candidate_email'] ?? old('candidate_email')) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="candidate_phone" class="form-control" value="<?= esc($record['candidate_phone'] ?? '') ?>" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" title="Enter exactly 10 digits" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)">
                </div>
                <?php if (! $record): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Department <span class="text-muted small">(optional)</span></label>
                    <select name="department_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= (($record['department_id'] ?? null) == $d['id']) ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Target Designation <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="designation" class="form-control" value="<?= esc($record['designation'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Offered CTC — Annual <span class="text-muted small">(optional, appears on the offer letter)</span></label>
                    <input type="number" step="0.01" min="0" max="9999999999.99" name="offered_ctc" class="form-control" value="<?= esc($record['offered_ctc'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Reporting / Onboarding Manager <span class="text-muted small">(optional)</span></label>
                    <select name="reporting_manager_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($managers as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= (($record['reporting_manager_id'] ?? null) == $m['id']) ? 'selected' : '' ?>><?= esc($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($record): ?>
                <div class="col-md-4 mb-3">
                    <label class="form-label">BGV Status</label>
                    <select name="bgv_status" class="form-select">
                        <?php foreach ($bgvStatuses as $bs): ?>
                            <option value="<?= $bs ?>" <?= $record['bgv_status'] === $bs ? 'selected' : '' ?>><?= esc(ucfirst(str_replace('_', ' ', $bs))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">BGV Notes <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="bgv_notes" class="form-control" value="<?= esc($record['bgv_notes'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Joining Date <span class="text-muted small">(optional)</span></label>
                    <input type="date" name="joining_date" class="form-control" value="<?= esc($record['joining_date'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Probation End Date <span class="text-muted small">(optional)</span></label>
                    <input type="date" name="probation_end_date" class="form-control" value="<?= esc($record['probation_end_date'] ?? '') ?>">
                </div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary"><?= $record ? 'Save Changes' : 'Add Candidate' ?></button>
            <a href="<?= $record ? site_url('hr/onboarding/' . $record['id']) : site_url('hr/onboarding') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
