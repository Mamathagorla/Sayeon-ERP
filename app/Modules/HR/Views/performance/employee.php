<?= $this->extend('layouts/main') ?>

<?php
$statusBadge = ['draft' => 'secondary', 'submitted' => 'info', 'acknowledged' => 'success'];
$bullets     = static fn (?string $text): array => array_values(array_filter(array_map('trim', explode("\n", (string) $text))));
?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/performance') ?>" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Performance</a>
<?php if (can('performance.create')): ?>
<a href="<?= site_url('hr/performance/create?user_id=' . $employee['user_id']) ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Start Review</a>
<?php endif; ?>
<?php if ($latest && $latest['status'] === 'draft' && can('performance.edit')): ?>
<a href="<?= site_url('hr/performance/' . $latest['id'] . '/edit') ?>" class="btn btn-light btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<?php endif; ?>
<div class="dropdown d-inline-block">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">More</button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="<?= site_url('hr/employees/' . $employee['id']) ?>"><i class="fas fa-id-card me-2 text-muted"></i>View Employee Profile</a></li>
    </ul>
</div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
    .sy-perf-bullets { margin: 0; padding-left: 1.1rem; font-size: .85rem; }
    .sy-perf-bullets li { margin-bottom: 3px; }
</style>

<div class="sy-pf-crumb"><a href="<?= site_url('hr/performance') ?>"><i class="fas fa-arrow-left me-1"></i>Back to Performance</a></div>

<!-- Employee header -->
<div class="sy-card sy-pf-head mb-3" style="height:auto">
    <div class="sy-card-body">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <?php if (! empty($employee['user_avatar'])): ?>
                <img class="sy-pf-photo" src="<?= base_url($employee['user_avatar']) ?>" alt="">
            <?php else: ?>
                <span class="sy-avatar sy-pf-photo initial"><?= esc(mb_strtoupper(mb_substr($employee['user_name'], 0, 1))) ?></span>
            <?php endif; ?>
            <div class="flex-grow-1" style="min-width:0">
                <h4><?= esc($employee['user_name']) ?></h4>
                <div class="sy-pf-meta">
                    <span><?= esc($employee['designation'] ?: 'No designation set') ?></span>
                    <?php if (! empty($employee['department_name'])): ?><span><i class="fas fa-sitemap"></i><?= esc($employee['department_name']) ?></span><?php endif; ?>
                    <span><i class="far fa-id-badge"></i><?= esc($employee['employee_code']) ?></span>
                    <span><span class="badge bg-<?= $employee['status'] === 'active' ? 'success' : 'secondary' ?>"><?= esc(ucfirst($employee['status'])) ?></span></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- KPI cards -->
<div class="row row-cols-1 row-cols-md-3 g-3 mb-3">
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">OVERALL</div><div class="sy-stat-value"><?= $avgRating !== null ? $avgRating . ' / 5' : '—' ?></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">LAST REVIEW</div><div class="sy-stat-value fs-5"><?= $latest ? esc($latest['cycle_name']) : '—' ?></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">NEXT REVIEW</div><div class="sy-stat-value fs-5"><?= $dueCycle ? esc($dueCycle['name']) : '—' ?></div></div></div>
</div>

<!-- Current Review -->
<div class="sy-card mb-3" style="height:auto">
    <div class="sy-card-header"><strong>Current Review</strong></div>
    <div class="sy-card-body">
        <?php if ($latest === null): ?>
            <p class="text-muted small mb-0">No performance reviews yet for this employee.</p>
        <?php else: ?>
            <div class="row g-3 mb-3">
                <div class="col-sm-4"><div class="sy-pf-row"><span class="k">Status</span><span class="v"><span class="badge bg-<?= $statusBadge[$latest['status']] ?? 'secondary' ?>"><?= esc(ucfirst($latest['status'])) ?></span></span></div></div>
                <div class="col-sm-4"><div class="sy-pf-row"><span class="k">Reviewer</span><span class="v"><?= esc($latest['reviewer_name']) ?></span></div></div>
                <div class="col-sm-4"><div class="sy-pf-row"><span class="k">Review Period</span><span class="v"><?= $latest['cycle_start_date'] ? esc(date('M Y', strtotime($latest['cycle_start_date']))) . '–' . esc(date('M Y', strtotime($latest['cycle_end_date']))) : '—' ?></span></div></div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="fw-semibold">Rating</span>
                <span class="fs-5 fw-bold"><?= $latest['rating'] ? esc($latest['rating']) . ' / 5' : 'Not rated' ?></span>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="sy-pf-sub-title mb-1"><strong class="small">Strengths</strong></div>
                    <?php $items = $bullets($latest['strengths']); ?>
                    <?php if ($items === []): ?><p class="text-muted small mb-0">—</p><?php else: ?>
                        <ul class="sy-perf-bullets"><?php foreach ($items as $i): ?><li><?= esc($i) ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <div class="sy-pf-sub-title mb-1"><strong class="small">Areas for Improvement</strong></div>
                    <?php $items = $bullets($latest['improvements']); ?>
                    <?php if ($items === []): ?><p class="text-muted small mb-0">—</p><?php else: ?>
                        <ul class="sy-perf-bullets"><?php foreach ($items as $i): ?><li><?= esc($i) ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <div class="sy-pf-sub-title mb-1"><strong class="small">Goals for Next Cycle</strong></div>
                    <?php $items = $bullets($latest['goals_next']); ?>
                    <?php if ($items === []): ?><p class="text-muted small mb-0">—</p><?php else: ?>
                        <ul class="sy-perf-bullets"><?php foreach ($items as $i): ?><li><?= esc($i) ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-end mt-2"><a href="<?= site_url('hr/performance/' . $latest['id']) ?>" class="small">View full review &rarr;</a></div>
        <?php endif; ?>
    </div>
</div>

<!-- Review History -->
<div class="sy-card" style="height:auto">
    <div class="sy-card-header"><strong>Review History</strong> <span class="text-muted small"><?= count($reviews) ?> reviews</span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($reviews)): ?>
            <p class="text-muted small mb-0 p-3">No reviews recorded yet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Cycle</th><th>Reviewer</th><th>Rating</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($reviews as $r): ?>
                <tr>
                    <td><a href="<?= site_url('hr/performance/' . $r['id']) ?>" class="text-decoration-none fw-semibold text-dark"><?= esc($r['cycle_name']) ?></a></td>
                    <td class="text-muted small"><?= esc($r['reviewer_name']) ?></td>
                    <td class="fw-semibold"><?= $r['rating'] ? esc($r['rating']) . '/5' : '—' ?></td>
                    <td><span class="badge bg-<?= $statusBadge[$r['status']] ?? 'secondary' ?>"><?= esc(ucfirst($r['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
