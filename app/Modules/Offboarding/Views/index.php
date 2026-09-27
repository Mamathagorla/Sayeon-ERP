<?= $this->extend('layouts/main') ?>

<?php
$recordModel = \App\Modules\Offboarding\Models\OffboardingRecordModel::class;
$groupColor = [
    'pending'   => ['fg' => '#d97706', 'bg' => '#fef3e2'],
    'progress'  => ['fg' => '#2563eb', 'bg' => '#eaf1fe'],
    'completed' => ['fg' => '#16a34a', 'bg' => '#e8f8ee'],
    'rescinded' => ['fg' => '#64748b', 'bg' => '#eef1f4'],
];

// Summary buckets from the per-status counts the controller already
// computes — no extra query.
$bucketCount = static function (array $statuses) use ($stageCounts): int {
    return array_sum(array_intersect_key($stageCounts, array_flip($statuses)));
};
$summary = [
    ['key' => '',          'label' => 'All Exits',        'count' => array_sum($stageCounts)],
    ['key' => 'pending',   'label' => 'Pending Approval', 'count' => $bucketCount($recordModel::STAGE_GROUPS['pending'])],
    ['key' => 'progress',  'label' => 'In Progress',      'count' => $bucketCount($recordModel::STAGE_GROUPS['progress'])],
    ['key' => 'completed', 'label' => 'Completed',        'count' => $bucketCount($recordModel::STAGE_GROUPS['completed'])],
];
$activeStage = $filters['stage_group'] ?? '';
$activeType  = $filters['exit_type'] ?? '';

// Links keep the other filter when one changes.
$link = static function (string $stage, string $type): string {
    $qs = array_filter(['stage' => $stage, 'exit_type' => $type]);

    return site_url('hr/offboarding') . ($qs ? '?' . http_build_query($qs) : '');
};
?>

<?= $this->section('pageActions') ?>
<?php if ($isOwner): ?>
<a href="<?= site_url('hr/offboarding/phases') ?>" class="btn btn-light btn-sm"><i class="fas fa-diagram-project me-1"></i>Manage Phases</a>
<?php endif; ?>
<?php if ($isEmployee && ! $hasActiveExit): ?>
<a href="<?= site_url('hr/offboarding/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-person-walking-arrow-right me-1"></i>Submit Resignation</a>
<?php elseif (can('offboarding.create') && ! $isEmployee): ?>
<a href="<?= site_url('hr/offboarding/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Initiate Exit</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
    .sy-off-summary { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
    @media (min-width: 768px) { .sy-off-summary { grid-template-columns: repeat(4, 1fr); } }
    .sy-off-summary a { display: block; padding: 10px 14px; border-radius: var(--sy-radius-sm); border: 1px solid var(--sy-border-soft); text-decoration: none; }
    .sy-off-summary a.active { background: var(--sy-hover-bg); border-color: var(--sy-border); }
    .sy-off-summary .num { font-size: 1.5rem; font-weight: 700; line-height: 1.1; color: var(--sy-ink); }
    .sy-off-summary .lbl { font-size: .74rem; color: var(--sy-muted); font-weight: 600; }
    .sy-off-filters { display: flex; flex-wrap: wrap; gap: 6px; }
    /* !important: the generic "a:not(.btn)..." link-color rule in
       sayeon-theme.css has higher specificity than a plain class. */
    .sy-off-filters a { padding: 4px 12px; border-radius: 999px; font-size: .78rem; font-weight: 600; text-decoration: none; color: var(--sy-ink) !important; border: 1px solid var(--sy-border); }
    .sy-off-filters a.active { background: var(--sy-ink); color: var(--sy-surface) !important; border-color: var(--sy-ink); }
    .sy-off-progress { display: flex; align-items: center; gap: 8px; min-width: 110px; }
    .sy-off-progress .progress { height: 6px; flex: 1; }
</style>

<?php if (! $isEmployee): ?>
<div class="sy-card mb-3" style="height:auto">
    <div class="sy-card-body">
        <div class="sy-off-summary mb-3">
            <?php foreach ($summary as $s): ?>
                <a href="<?= $link($s['key'], $activeType) ?>" class="<?= $activeStage === $s['key'] ? 'active' : '' ?>">
                    <div class="num"><?= $s['count'] ?></div>
                    <div class="lbl"><?= esc($s['label']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="sy-off-filters">
            <a href="<?= $link($activeStage, '') ?>" class="<?= $activeType === '' ? 'active' : '' ?>">All</a>
            <?php foreach ($recordModel::EXIT_TYPES as $t): ?>
                <a href="<?= $link($activeStage, $t) ?>" class="<?= $activeType === $t ? 'active' : '' ?>"><?= esc(ucfirst($t)) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12">
        <div class="sy-card">
            <div class="sy-card-header"><strong><?= $isEmployee ? 'My Exit Record' : 'Exits' ?></strong></div>
            <div class="sy-card-body p-0">
                <?php if (empty($records)): ?>
                    <p class="text-muted small mb-0 p-3"><?= $isEmployee ? 'You have no exit record on file.' : 'No offboarding records match these filters.' ?></p>
                <?php else: ?>
                <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Employee</th><th>Department</th><th>Exit Type</th><th>Last Working Day</th><th>Progress</th><th>Current Stage</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($records as $r): ?>
                        <?php
                            $total = (int) $r['tasks_total'];
                            $done  = (int) $r['tasks_done'];
                            $pct   = $total > 0 ? (int) round($done / $total * 100) : 0;
                            $group = $recordModel::groupFor($r['status']);
                        ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('hr/offboarding/' . $r['id']) ?>" class="text-decoration-none fw-semibold text-dark"><?= esc($r['employee_name']) ?></a>
                                <div class="text-muted small"><?= esc($r['company_name']) ?></div>
                            </td>
                            <td class="text-muted small"><?= esc($r['department_name'] ?? '—') ?></td>
                            <td class="small"><?= esc(ucfirst($r['exit_type'])) ?></td>
                            <td class="text-muted small"><?= $r['last_working_day'] ? esc(date('d/m/Y', strtotime($r['last_working_day']))) : '—' ?></td>
                            <td>
                                <div class="sy-off-progress">
                                    <div class="progress"><div class="progress-bar" style="width:<?= $pct ?>%;background:var(--sy-accent-ink)"></div></div>
                                    <span class="small text-muted text-nowrap"><?= $done ?>/<?= $total ?></span>
                                </div>
                            </td>
                            <td><span class="badge" style="background:<?= $groupColor[$group]['bg'] ?>;color:<?= $groupColor[$group]['fg'] ?>"><?= esc($stageLabels[$r['status']]) ?></span></td>
                            <td class="text-end"><a href="<?= site_url('hr/offboarding/' . $r['id']) ?>" class="btn btn-sm btn-light" title="View"><i class="fas fa-eye"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
