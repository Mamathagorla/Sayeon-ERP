<?= $this->extend('layouts/main') ?>

<?php $qs = http_build_query($filters); ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('reports/campaigns/export/pdf?' . $qs) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-pdf me-1"></i>PDF</a>
<a href="<?= site_url('reports/campaigns/export/csv?' . $qs) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-csv me-1"></i>CSV</a>
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
        <div>
            <label class="form-label small mb-1">Channel</label>
            <select name="channel" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($channels as $ch): ?>
                    <option value="<?= $ch ?>" <?= (($filters['channel'] ?? null) === $ch) ? 'selected' : '' ?>><?= esc(\App\Modules\Marketing\Models\CampaignModel::CHANNEL_LABELS[$ch]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <a href="<?= site_url('reports/campaigns') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold"><?= number_format($totalBudget, 2) ?></div><div class="text-muted small">Total Budget</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold"><?= number_format($totalRevenue, 2) ?></div><div class="text-muted small">Total Revenue</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold"><?= $totalLeads ?> / <?= $totalConversions ?></div><div class="text-muted small">Leads / Conversions</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold <?= $overallRoi !== null && $overallRoi >= 0 ? 'text-success' : 'text-danger' ?>"><?= $overallRoi !== null ? $overallRoi . '%' : '—' ?></div><div class="text-muted small">Overall ROI</div></div></div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($campaigns)): ?>
            <p class="text-muted mb-0">No campaigns match these filters.</p>
        <?php else: ?>
        <table class="table table-sm table-striped">
            <thead><tr><th>Campaign</th><th>Company</th><th>Channel</th><th class="text-end">Budget</th><th class="text-end">Leads</th><th class="text-end">Conversions</th><th class="text-end">Revenue</th><th class="text-end">ROI</th><th>Status</th></tr></thead>
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
                    <td class="text-end <?= $c['roi'] !== null && $c['roi'] < 0 ? 'text-danger' : '' ?>"><?= $c['roi'] !== null ? $c['roi'] . '%' : '—' ?></td>
                    <td><?= esc(ucfirst($c['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
