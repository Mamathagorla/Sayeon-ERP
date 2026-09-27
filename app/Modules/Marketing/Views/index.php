<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('campaign.create')): ?>
<a href="<?= site_url('campaigns/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Campaign</a>
<?php endif; ?>
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
        <div style="min-width:160px">
            <label class="form-label small mb-1">Channel</label>
            <?php $selectedChannels = (array) ($filters['channel'] ?? []); ?>
            <div class="dropdown sy-msel" data-placeholder="All">
                <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <span class="sy-msel-label">All</span>
                </button>
                <div class="dropdown-menu sy-msel-menu">
                    <?php foreach ($channels as $ch): ?>
                        <label class="sy-msel-item" data-label="<?= esc(\App\Modules\Marketing\Models\CampaignModel::CHANNEL_LABELS[$ch]) ?>">
                            <input type="checkbox" class="sy-msel-opt" name="channel[]" value="<?= $ch ?>" <?= in_array($ch, $selectedChannels, true) ? 'checked' : '' ?>>
                            <?= esc(\App\Modules\Marketing\Models\CampaignModel::CHANNEL_LABELS[$ch]) ?>
                        </label>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                </div>
            </div>
        </div>
        <div style="min-width:150px">
            <label class="form-label small mb-1">Status</label>
            <?php $selectedCampaignStatuses = (array) ($filters['status'] ?? []); ?>
            <div class="dropdown sy-msel" data-placeholder="All">
                <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <span class="sy-msel-label">All</span>
                </button>
                <div class="dropdown-menu sy-msel-menu">
                    <?php foreach ($statuses as $s): ?>
                        <label class="sy-msel-item" data-label="<?= esc(ucfirst($s)) ?>">
                            <input type="checkbox" class="sy-msel-opt" name="status[]" value="<?= $s ?>" <?= in_array($s, $selectedCampaignStatuses, true) ? 'checked' : '' ?>>
                            <?= esc(ucfirst($s)) ?>
                        </label>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                </div>
            </div>
        </div>
        <a href="<?= site_url('campaigns') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold"><?= number_format($totalBudget, 2) ?></div><div class="text-muted small">Total Budget</div></div></div>
    </div>
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold"><?= number_format($totalRevenue, 2) ?></div><div class="text-muted small">Total Revenue</div></div></div>
    </div>
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold <?= $overallRoi !== null && $overallRoi >= 0 ? 'text-success' : 'text-danger' ?>"><?= $overallRoi !== null ? $overallRoi . '%' : '—' ?></div><div class="text-muted small">Overall ROI</div></div></div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($campaigns)): ?>
            <p class="text-muted mb-0">No campaigns yet.</p>
        <?php else: ?>
        <table class="table table-sm table-striped">
            <thead><tr><th>Campaign</th><th>Company</th><th>Channel</th><th class="text-end">Budget</th><th class="text-end">Leads</th><th class="text-end">Conversions</th><th class="text-end">Revenue</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($campaigns as $c): ?>
                <tr>
                    <td><a href="<?= site_url('campaigns/' . $c['id']) ?>"><?= esc($c['name']) ?></a></td>
                    <td><?= esc($c['company_name']) ?></td>
                    <td><?= esc(\App\Modules\Marketing\Models\CampaignModel::CHANNEL_LABELS[$c['channel']]) ?></td>
                    <td class="text-end"><?= number_format($c['budget'], 2) ?></td>
                    <td class="text-end"><?= $c['leads'] ?></td>
                    <td class="text-end"><?= $c['conversions'] ?></td>
                    <td class="text-end"><?= number_format($c['revenue'], 2) ?></td>
                    <td><span class="badge bg-<?= ['draft' => 'secondary', 'active' => 'success', 'paused' => 'warning', 'completed' => 'dark'][$c['status']] ?>"><?= esc(ucfirst($c['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
