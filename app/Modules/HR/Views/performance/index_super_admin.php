<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('performance.create')): ?>
<a href="<?= site_url('hr/performance/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Review</a>
<?php endif; ?>
<?php if (can('performance.edit')): ?>
<a href="<?= site_url('hr/performance/cycles') ?>" class="btn btn-outline-secondary btn-sm">Manage Cycles</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?php
/**
 * Super Admin's own Performance Reviews view — same $reviews array
 * PerformanceController::index() already builds for every role (see
 * performance/index.php, the sibling view every other role still
 * uses); this just presents it with a richer table, including two
 * columns (Designation/Department) pulled in via an additive LEFT
 * JOIN in PerformanceReviewModel::filtered() — real, existing
 * employee_profiles/departments data, not fabricated.
 *
 * The "Excellent / Good / Average / Needs Improvement" quality label
 * is derived here, purely for display, from the review's own real
 * numeric rating — it isn't a stored field or a new status value; the
 * underlying workflow status (draft/submitted/acknowledged) is
 * unchanged and still what every permission/business-logic check in
 * the controller uses.
 */
$qualityLabel = static function (?int $rating): ?array {
    if ($rating === null) {
        return null;
    }

    return match (true) {
        $rating >= 5 => ['label' => 'Excellent', 'badge' => 'success'],
        $rating >= 4 => ['label' => 'Good', 'badge' => 'success'],
        $rating >= 3 => ['label' => 'Average', 'badge' => 'warning'],
        default      => ['label' => 'Needs Improvement', 'badge' => 'danger'],
    };
};
$statusLabel = ['draft' => ['label' => 'Draft', 'badge' => 'secondary'], 'submitted' => ['label' => 'Submitted', 'badge' => 'info'], 'acknowledged' => ['label' => 'Acknowledged', 'badge' => 'success']];
?>

<?= $this->section('content') ?>
<?php if ($isPersonalScope): ?>
<div class="alert alert-info py-2 small">Showing only your own performance reviews.</div>
<?php endif; ?>

<div class="sy-card">
    <div class="sy-card-header"><strong>Performance Appraisal</strong> <span class="text-muted small"><?= count($reviews) ?> reviews</span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($reviews)): ?>
            <p class="text-muted mb-0 p-3">No performance reviews yet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <?php if (! $isPersonalScope): ?><th>Employee</th><?php endif; ?>
                    <th>Designation</th>
                    <th>Department</th>
                    <th>Review Period</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reviews as $r): ?>
                <?php
                    $quality = $qualityLabel($r['rating'] !== null ? (int) $r['rating'] : null);
                    $status  = $statusLabel[$r['status']] ?? ['label' => ucfirst($r['status']), 'badge' => 'secondary'];
                    $initials = $r['user_name'] ? mb_strtoupper(mb_substr($r['user_name'], 0, 1)) : '?';
                ?>
                <tr>
                    <td class="text-muted small">#PA<?= str_pad((string) $r['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <?php if (! $isPersonalScope): ?>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="sy-avatar" style="width:30px;height:30px;font-size:.76rem"><?= esc($initials) ?></span>
                            <span class="fw-semibold"><?= esc($r['user_name']) ?></span>
                        </div>
                    </td>
                    <?php endif; ?>
                    <td class="text-muted small"><?= esc($r['employee_designation'] ?? '—') ?></td>
                    <td class="text-muted small"><?= esc($r['department_name'] ?? '—') ?></td>
                    <td><a href="<?= site_url('hr/performance/' . $r['id']) ?>" class="text-decoration-none"><?= esc($r['cycle_name']) ?></a></td>
                    <td class="fw-semibold"><?= $r['rating'] ? esc($r['rating']) . '/5' : '—' ?></td>
                    <td>
                        <?php if ($quality): ?>
                            <span class="badge bg-<?= $quality['badge'] ?>"><?= esc($quality['label']) ?></span>
                        <?php else: ?>
                            <span class="badge bg-<?= $status['badge'] ?>"><?= esc($status['label']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="<?= site_url('hr/performance/' . $r['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
                        <?php if (can('performance.edit')): ?>
                        <a href="<?= site_url('hr/performance/' . $r['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
