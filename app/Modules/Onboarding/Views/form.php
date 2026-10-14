<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form data-erp-sections data-erp-first-section="Candidate" action="<?= $record ? site_url('hr/onboarding/' . $record['id']) : site_url('hr/onboarding') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="candidate_name" class="form-control" value="<?= esc($record['candidate_name'] ?? old('candidate_name')) ?>" pattern="[A-Za-z\s.'\-]+" title="Letters, spaces, apostrophes, hyphens and periods only" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="candidate_email" class="form-control" value="<?= esc($record['candidate_email'] ?? old('candidate_email')) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="candidate_phone" class="form-control" value="<?= esc($record['candidate_phone'] ?? old('candidate_phone')) ?>" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" title="Enter exactly 10 digits" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)" required>
                </div>
                <?php if (! $record): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= old('company_id') == $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-12 erp-section-marker" data-section-title="Role &amp; Offer"></div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select" required>
                        <option value="">Select department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= (($record['department_id'] ?? old('department_id')) == $d['id']) ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Applied Role</label>
                    <input type="text" name="designation" class="form-control" value="<?= esc($record['designation'] ?? old('designation')) ?>" maxlength="100" required>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Job Description &amp; Key Responsibilities <span class="text-muted small">(optional - shown to the candidate and used to pick their role-specific questions)</span></label>
                    <textarea name="job_description" class="form-control" rows="3" maxlength="5000"><?= esc($record['job_description'] ?? old('job_description')) ?></textarea>
                </div>
                <?php if (! $record): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Candidate Source</label>
                    <select name="candidate_source" class="form-select" required>
                        <?php foreach ($candidateSources as $value => $label): ?>
                            <option value="<?= esc($value) ?>" <?= old('candidate_source', 'direct_hire') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Source Details <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="source_detail" class="form-control" value="<?= esc(old('source_detail')) ?>" maxlength="190" placeholder="Referrer, agency or job board">
                </div>
                <?php endif; ?>
                <?php if ($record): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Offered CTC — Annual <span class="text-muted small">(optional, appears on the offer letter)</span></label>
                    <input type="number" step="0.01" min="0" max="9999999999.99" name="offered_ctc" class="form-control" value="<?= esc($record['offered_ctc'] ?? '') ?>">
                </div>
                <?php endif; ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Reporting Manager</label>
                    <select name="reporting_manager_id" class="form-select" required>
                        <option value="">Select reporting manager</option>
                        <?php foreach ($managers as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= (($record['reporting_manager_id'] ?? old('reporting_manager_id')) == $m['id']) ? 'selected' : '' ?>><?= esc($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($record): ?>
                <div class="col-12 erp-section-marker" data-section-title="Verification &amp; Joining"></div>
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
                <div class="col-12 erp-section-marker" data-section-title="Address"></div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Address Line 1 <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="address_line1" class="form-control" value="<?= esc($record['address_line1'] ?? '') ?>" maxlength="190">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Address Line 2 <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="address_line2" class="form-control" value="<?= esc($record['address_line2'] ?? '') ?>" maxlength="190">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">City <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="city" class="form-control" value="<?= esc($record['city'] ?? '') ?>" maxlength="190">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">State <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="state" class="form-control" value="<?= esc($record['state'] ?? '') ?>" maxlength="190">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Postal Code <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="postal_code" class="form-control" value="<?= esc($record['postal_code'] ?? '') ?>" maxlength="20">
                </div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary"><?= $record ? 'Save Changes' : 'Add Candidate' ?></button>
            <a href="<?= $record ? site_url('hr/onboarding/' . $record['id']) : site_url('hr/onboarding') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
