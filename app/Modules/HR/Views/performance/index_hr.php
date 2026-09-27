<?= $this->extend('layouts/main') ?>

<?php
$statusMeta = [
    'due'       => ['label' => 'Due', 'badge' => 'warning'],
    'completed' => ['label' => 'Completed', 'badge' => 'success'],
    'none'      => ['label' => 'No Reviews', 'badge' => 'secondary'],
];
?>

<?= $this->section('pageActions') ?>
<?php if (can('performance.create')): ?>
<a href="<?= site_url('hr/performance/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Review</a>
<?php endif; ?>
<?php if (can('performance.edit')): ?>
<a href="<?= site_url('hr/performance/cycles') ?>" class="btn btn-outline-secondary btn-sm">Manage Cycles</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
    .sy-perf-filters .form-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--sy-muted); margin-bottom: 4px; }
    .sy-perf-clear { font-size: .78rem; font-weight: 600; color: var(--sy-accent-ink); text-decoration: none; white-space: nowrap; }
</style>

<p class="text-muted mb-3">Track employee performance, reviews and goals.</p>

<!-- KPI cards -->
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">EMPLOYEES</div><div class="sy-stat-value"><?= $kpis['employees'] ?></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">REVIEWS DUE</div><div class="sy-stat-value"><?= $kpis['due'] ?></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">COMPLETED</div><div class="sy-stat-value"><?= $kpis['completed'] ?></div></div></div>
    <div class="col"><div class="sy-stat-card"><div class="sy-stat-label">AVG RATING</div><div class="sy-stat-value"><?= $kpis['avgRating'] !== null ? $kpis['avgRating'] . '/5' : '—' ?></div></div></div>
</div>

<!-- Filters -->
<div class="sy-card mb-3 sy-perf-filters" style="height:auto">
    <div class="sy-card-body">
        <form method="get" class="row g-3 align-items-end filter-form">
            <div class="col-12 col-md-4">
                <label class="form-label">Search</label>
                <div class="position-relative">
                    <i class="fas fa-search position-absolute text-muted" style="left:12px;top:50%;transform:translateY(-50%);font-size:.8rem"></i>
                    <input type="text" name="q" class="form-control form-control-sm ps-4" placeholder="Search employee…" value="<?= esc($filters['q'] ?? '') ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= (string) ($filters['department_id'] ?? '') === (string) $d['id'] ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Review Cycle</label>
                <select name="cycle_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($cycles as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (string) ($filters['cycle_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($statusMeta as $key => $meta): ?>
                        <option value="<?= $key ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= esc($meta['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (array_filter($filters)): ?>
            <div class="col-auto">
                <a href="<?= site_url('hr/performance') ?>" class="sy-perf-clear"><i class="fas fa-rotate-left me-1"></i>Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Employee Performance -->
<div class="sy-card" style="height:auto">
    <div class="sy-card-header"><strong>Employee Performance</strong> <span class="text-muted small"><?= count($rows) ?> employees</span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($rows)): ?>
            <p class="text-muted small mb-0 p-3">No employees match these filters.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Employee</th><th>Department</th><th>Last Review</th><th>Rating</th><th>Next Review</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $meta = $statusMeta[$row['status']]; ?>
                <tr>
                    <td><a href="<?= site_url('hr/performance/employee/' . $row['user_id']) ?>" class="text-decoration-none fw-semibold text-dark"><?= esc($row['name']) ?></a></td>
                    <td class="text-muted small"><?= esc($row['department'] ?? '—') ?></td>
                    <td class="text-muted small"><?= $row['last_review'] ? esc($row['last_review']['cycle_name']) : '—' ?></td>
                    <td class="fw-semibold"><?= $row['last_review'] && $row['last_review']['rating'] ? esc($row['last_review']['rating']) . '/5' : '—' ?></td>
                    <td class="text-muted small"><?= $row['due_cycle'] ? esc($row['due_cycle']['name']) : '—' ?></td>
                    <td><span class="badge bg-<?= $meta['badge'] ?>"><?= esc($meta['label']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
