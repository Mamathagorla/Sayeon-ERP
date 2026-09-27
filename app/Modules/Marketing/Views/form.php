<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form action="<?= $campaign ? site_url('campaigns/' . $campaign['id']) : site_url('campaigns') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Campaign Name</label>
                    <input type="text" name="name" class="form-control" value="<?= esc($campaign['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($campaign['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Channel</label>
                    <select name="channel" class="form-select" required>
                        <option value="">Select channel</option>
                        <?php foreach ($channels as $ch): ?>
                            <option value="<?= $ch ?>" <?= (($campaign['channel'] ?? null) === $ch) ? 'selected' : '' ?>><?= esc(\App\Modules\Marketing\Models\CampaignModel::CHANNEL_LABELS[$ch]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Budget</label>
                    <input type="number" step="0.01" min="0" name="budget" class="form-control" value="<?= esc($campaign['budget'] ?? '0') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= (($campaign['status'] ?? 'draft') === $s) ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= esc($campaign['start_date'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= esc($campaign['end_date'] ?? '') ?>">
                </div>

                <div class="col-12"><hr><h6>Performance <span class="text-muted small fw-normal">(update these as the campaign runs)</span></h6></div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Leads</label>
                    <input type="number" min="0" name="leads" class="form-control" value="<?= esc($campaign['leads'] ?? '0') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Conversions</label>
                    <input type="number" min="0" name="conversions" class="form-control" value="<?= esc($campaign['conversions'] ?? '0') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Revenue</label>
                    <input type="number" step="0.01" min="0" name="revenue" class="form-control" value="<?= esc($campaign['revenue'] ?? '0') ?>">
                </div>

                <div class="col-12 mb-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc($campaign['notes'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $campaign ? 'Update Campaign' : 'Create Campaign' ?></button>
            <a href="<?= site_url('campaigns') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
