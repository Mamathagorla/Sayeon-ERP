<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('campaign.edit')): ?>
<a href="<?= site_url('campaigns/' . $campaign['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<?php endif; ?>
<?php if (can('campaign.delete')): ?>
<form action="<?= site_url('campaigns/' . $campaign['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Delete this campaign?');">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Delete</button>
</form>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><?= esc($campaign['name']) ?> <span class="badge bg-<?= ['draft' => 'secondary', 'active' => 'success', 'paused' => 'warning', 'completed' => 'dark'][$campaign['status']] ?>"><?= esc(ucfirst($campaign['status'])) ?></span></h5>
                <p class="text-muted small"><?= esc($campaign['company_name']) ?> &middot; <?= esc(\App\Modules\Marketing\Models\CampaignModel::CHANNEL_LABELS[$campaign['channel']]) ?></p>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Start Date</dt><dd class="col-sm-8"><?= $campaign['start_date'] ? esc(date('d/m/Y', strtotime($campaign['start_date']))) : '—' ?></dd>
                    <dt class="col-sm-4">End Date</dt><dd class="col-sm-8"><?= $campaign['end_date'] ? esc(date('d/m/Y', strtotime($campaign['end_date']))) : '—' ?></dd>
                    <dt class="col-sm-4">Notes</dt><dd class="col-sm-8"><?= esc($campaign['notes'] ?? '—') ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Performance</h6>
                <dl class="row mb-0 small">
                    <dt class="col-sm-6">Budget</dt><dd class="col-sm-6 text-end"><?= number_format($campaign['budget'], 2) ?></dd>
                    <dt class="col-sm-6">Revenue</dt><dd class="col-sm-6 text-end"><?= number_format($campaign['revenue'], 2) ?></dd>
                    <dt class="col-sm-6">Leads</dt><dd class="col-sm-6 text-end"><?= $campaign['leads'] ?></dd>
                    <dt class="col-sm-6">Conversions</dt><dd class="col-sm-6 text-end"><?= $campaign['conversions'] ?></dd>
                    <dt class="col-sm-6">Conversion Rate</dt><dd class="col-sm-6 text-end"><?= $conversionRate !== null ? $conversionRate . '%' : '—' ?></dd>
                    <dt class="col-sm-6 fw-bold">ROI</dt>
                    <dd class="col-sm-6 text-end fw-bold <?= $roi !== null && $roi >= 0 ? 'text-success' : 'text-danger' ?>"><?= $roi !== null ? $roi . '%' : '—' ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
