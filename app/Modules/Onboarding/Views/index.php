<?= $this->extend('layouts/main') ?>

<?php
// Status column/legend — 3 simplified buckets (see
// OnboardingRecordModel::SIMPLE_BUCKETS), not the 11 exact stages —
// this list cares about "has the checklist started/finished", the
// candidate's own record page still shows the exact stage.
$bucketMeta = [
    'not_started' => ['label' => 'Not Started', 'color' => '#8a929e', 'icon' => 'fa-circle'],
    'in_progress' => ['label' => 'In Progress', 'color' => '#2563eb', 'icon' => 'fa-circle'],
    'completed'   => ['label' => 'Completed',   'color' => '#16a34a', 'icon' => 'fa-circle-check'],
];
?>

<?= $this->section('pageActions') ?>
<?php if ($isOwner): ?>
<a href="<?= site_url('hr/onboarding/phases') ?>" class="btn btn-light btn-sm"><i class="fas fa-diagram-project me-1"></i>Manage Phases</a>
<?php endif; ?>
<?php if (can('onboarding.create')): ?>
<a href="<?= site_url('hr/onboarding/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Start Onboarding</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
    .sy-onb-progress { height: 6px; border-radius: 6px; background: var(--sy-border-soft); overflow: hidden; min-width: 70px; }
    .sy-onb-progress > span { display: block; height: 100%; background: var(--sy-accent); border-radius: 6px; }
    .sy-onb-status-dot { font-size: .55rem; margin-right: 6px; }
    .sy-onb-filters .form-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--sy-muted); margin-bottom: 4px; }
    .sy-onb-clear { font-size: .78rem; font-weight: 600; color: var(--sy-accent-ink); text-decoration: none; white-space: nowrap; }
</style>

<p class="text-muted mb-3">Manage new joiners and track onboarding progress.</p>

<!-- KPI cards -->
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <div class="col">
        <a href="<?= site_url('hr/onboarding') ?>" class="text-decoration-none">
            <div class="sy-stat-card">
                <div class="sy-stat-label">TOTAL</div>
                <div class="sy-stat-value"><?= array_sum($statusCounts) ?></div>
            </div>
        </a>
    </div>
    <?php foreach ($bucketMeta as $bucket => $meta): ?>
    <div class="col">
        <a href="<?= site_url('hr/onboarding?status=' . $bucket) ?>" class="text-decoration-none">
            <div class="sy-stat-card">
                <div class="sy-stat-label"><?= esc(mb_strtoupper($meta['label'])) ?></div>
                <div class="sy-stat-value"><?= (int) $statusCounts[$bucket] ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="sy-card mb-3 sy-onb-filters" style="height:auto">
    <div class="sy-card-body">
        <form method="get" class="row g-3 align-items-end filter-form">
            <div class="col-12 col-md-4">
                <label class="form-label">Search</label>
                <div class="position-relative">
                    <i class="fas fa-search position-absolute text-muted" style="left:12px;top:50%;transform:translateY(-50%);font-size:.8rem"></i>
                    <input type="text" name="q" class="form-control form-control-sm ps-4" placeholder="Search employees…" value="<?= esc($filters['q'] ?? '') ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Status</label>
                <?php $selectedBuckets = (array) ($filters['status'] ?? []); ?>
                <div class="dropdown sy-msel" data-placeholder="All">
                    <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span class="sy-msel-label">All</span>
                    </button>
                    <div class="dropdown-menu sy-msel-menu">
                        <?php foreach ($bucketMeta as $bucket => $meta): ?>
                            <label class="sy-msel-item" data-label="<?= esc($meta['label']) ?>">
                                <input type="checkbox" class="sy-msel-opt" name="status[]" value="<?= $bucket ?>" <?= in_array($bucket, $selectedBuckets, true) ? 'checked' : '' ?>>
                                <?= esc($meta['label']) ?>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= (string) ($filters['department_id'] ?? '') === (string) $d['id'] ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Joining Date</label>
                <input type="date" name="joining_from" class="form-control form-control-sm" value="<?= esc($filters['joining_from'] ?? '') ?>">
            </div>
            <?php if (array_filter($filters)): ?>
            <div class="col-auto">
                <a href="<?= site_url('hr/onboarding') ?>" class="sy-onb-clear"><i class="fas fa-rotate-left me-1"></i>Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Candidates -->
<div class="sy-card" style="height:auto">
    <div class="sy-card-body p-0">
        <?php if (empty($records)): ?>
            <p class="text-muted small mb-0 p-3">No onboarding candidates match these filters.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Employee</th><th>Role</th><th>Joining Date</th><th>Progress</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <?php
                    $bucket = \App\Modules\Onboarding\Models\OnboardingRecordModel::bucketFor($r['status']) ?? 'not_started';
                    $meta   = $bucketMeta[$bucket];
                    $pct    = $progress[$r['id']]['pct'] ?? 0;
                ?>
                <tr>
                    <td><a href="<?= site_url('hr/onboarding/' . $r['id']) ?>" class="text-decoration-none fw-semibold text-dark"><?= esc($r['candidate_name']) ?></a></td>
                    <td class="text-muted small"><?= esc($r['designation'] ?? '—') ?></td>
                    <td class="text-muted small"><?= $r['joining_date'] ? esc(date('d M Y', strtotime($r['joining_date']))) : '—' ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="sy-onb-progress flex-grow-1"><span style="width:<?= $pct ?>%;background:<?= $meta['color'] ?>"></span></div>
                            <span class="small text-muted text-nowrap"><?= $pct ?>%</span>
                        </div>
                    </td>
                    <td class="text-nowrap"><i class="fas <?= $meta['icon'] ?> sy-onb-status-dot" style="color:<?= $meta['color'] ?>"></i><span style="color:<?= $meta['color'] ?>;font-weight:600"><?= esc($meta['label']) ?></span></td>
                    <td class="text-end"><a href="<?= site_url('hr/onboarding/' . $r['id']) ?>" class="btn btn-sm btn-light" title="View"><i class="fas fa-eye"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
