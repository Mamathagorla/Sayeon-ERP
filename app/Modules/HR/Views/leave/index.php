<?= $this->extend('layouts/main') ?>

<?php
$statusBadge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'];
$statusLabel = static fn (string $s): string => ucfirst($s);
$totalCount  = array_sum($statusCounts);

$donutColors = ['#d62431', '#f5a524', '#4b5260', '#b8bec9'];
$totalQuota  = array_sum(array_column($balances, 'quota'));
?>

<?= $this->section('pageActions') ?>
<?php if (can('leave.create') && session('roleSlug') !== 'super_admin'): ?>
<a href="<?= site_url('hr/leave/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Create Leave Request</a>
<?php endif; ?>
<?php if (can('leave.edit')): ?>
<a href="<?= site_url('hr/leave/types') ?>" class="btn btn-outline-secondary btn-sm">Manage Leave Types</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
    .sy-leave-donut { position: relative; width: 130px; height: 130px; border-radius: 50%; flex: none; }
    .sy-leave-donut::after { content: ''; position: absolute; inset: 18px; border-radius: 50%; background: var(--sy-surface); }
    .sy-leave-donut .mid { position: absolute; inset: 0; z-index: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .sy-leave-leg > div { display: flex; align-items: center; gap: 8px; padding: 3px 0; font-size: .8rem; }
    .sy-leave-leg .dot { width: 9px; height: 9px; border-radius: 50%; flex: none; }
</style>


<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:#eef0f3;color:#3a404b"><i class="fas fa-calendar-days"></i></div>
            <div class="sy-stat-label">TOTAL LEAVE REQUESTS</div>
            <div class="sy-stat-value"><?= $totalCount ?></div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:#dcfce7;color:#16a34a"><i class="fas fa-circle-check"></i></div>
            <div class="sy-stat-label">APPROVED</div>
            <div class="sy-stat-value"><?= $statusCounts['approved'] ?></div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:#ffedd5;color:#d97706"><i class="fas fa-hourglass-half"></i></div>
            <div class="sy-stat-label">PENDING</div>
            <div class="sy-stat-value"><?= $statusCounts['pending'] ?></div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-circle-xmark"></i></div>
            <div class="sy-stat-label">REJECTED</div>
            <div class="sy-stat-value"><?= $statusCounts['rejected'] ?></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="<?= $isSuperAdmin ? 'col-12' : 'col-xl-9' ?>">
        <div class="sy-card mb-3" style="height:auto">
            <div class="sy-card-body">
                <form method="get" class="row g-2 align-items-end filter-form">
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Department</label>
                        <select name="department_id" class="form-select form-select-sm">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= (($filters['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Leave Type</label>
                        <select name="leave_type_id" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <?php foreach ($leaveTypes as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= (($filters['leave_type_id'] ?? '') == $t['id']) ? 'selected' : '' ?>><?= esc($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <?php foreach (['pending', 'approved', 'rejected', 'cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= (($filters['status'] ?? '') === $s) ? 'selected' : '' ?>><?= esc($statusLabel($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Search</label>
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search employees…" value="<?= esc($filters['q'] ?? '') ?>">
                    </div>
                </form>
            </div>
        </div>

        <div class="sy-card" style="height:auto">
            <div class="sy-card-header"><strong><?= $isSuperAdmin ? 'All Leave Requests' : 'Leave Requests' ?></strong> <span class="text-muted small"><?= count($requests) ?> total</span></div>
            <div class="sy-card-body p-0">
                <?php if (empty($requests)): ?>
                    <p class="text-muted small mb-0 p-3">No leave requests match these filters.</p>
                <?php else: ?>
                <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Employee</th><th>Employee ID</th><th>Leave Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Approved By</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requests as $r): ?>
                        <?php $isMine = (int) $r['user_id'] === (int) session('userId'); ?>
                        <tr>
                            <td>
                                <?php if ($isMine): ?>
                                    <?= esc($r['user_name']) ?> <span class="badge bg-light text-dark border">You</span>
                                <?php else: ?>
                                    <a href="<?= site_url('hr/leave/employee/' . $r['user_id']) ?>"><?= esc($r['user_name']) ?></a>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= $r['employee_code'] ? esc($r['employee_code']) : '—' ?></td>
                            <td><?= esc($r['leave_type_name']) ?></td>
                            <td class="text-muted small"><?= esc(erp_date($r['start_date'])) ?></td>
                            <td class="text-muted small"><?= esc(erp_date($r['end_date'])) ?></td>
                            <td><?= esc($r['days']) ?></td>
                            <td><span class="badge bg-<?= $statusBadge[$r['status']] ?>"><?= esc($statusLabel($r['status'])) ?></span></td>
                            <td class="text-muted small"><?= $r['approver_name'] ? esc($r['approver_name']) : '—' ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($r['status'] === 'pending' && $isMine): ?>
                                    <form action="<?= site_url('hr/leave/' . $r['id'] . '/cancel') ?>" method="post" class="d-inline" onsubmit="return confirm('Cancel this request?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Cancel</button>
                                    </form>
                                <?php elseif ($r['status'] === 'pending' && ! $isMine): ?>
                                    <form action="<?= site_url('hr/leave/' . $r['id'] . '/approve') ?>" method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Approve"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form action="<?= site_url('hr/leave/' . $r['id'] . '/reject') ?>" method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject"><i class="fas fa-xmark"></i></button>
                                    </form>
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
    </div>

    <?php if (! $isSuperAdmin): ?>
    <div class="col-xl-3">
        <div class="sy-card mb-3">
            <div class="sy-card-header"><strong>Leave Balance</strong></div>
            <div class="sy-card-body">
                <?php if (empty($balances) || $totalQuota <= 0): ?>
                    <p class="text-muted small mb-0">No leave quota configured.</p>
                <?php else: ?>
                <div class="d-flex align-items-center gap-3">
                    <?php
                        $acc = 0.0; $stops = [];
                        foreach ($balances as $i => $b) {
                            if ($b['remaining'] <= 0) { continue; }
                            $from = $acc; $acc += $b['remaining'] / $totalQuota * 100;
                            $stops[] = ($donutColors[$i % count($donutColors)]) . ' ' . round($from, 2) . '% ' . round($acc, 2) . '%';
                        }
                        $grad = $stops ? 'conic-gradient(' . implode(', ', $stops) . ')' : 'conic-gradient(var(--sy-border) 0 100%)';
                    ?>
                    <div class="sy-leave-donut" style="background:<?= $grad ?>">
                        <div class="mid"><div class="fs-5 fw-bold lh-1"><?= (int) array_sum(array_column($balances, 'remaining')) ?></div><div class="text-muted" style="font-size:.65rem">Total Days</div></div>
                    </div>
                    <div class="sy-leave-leg flex-grow-1">
                        <?php foreach ($balances as $i => $b): ?>
                            <div><span class="dot" style="background:<?= $donutColors[$i % count($donutColors)] ?>"></span><span class="flex-grow-1"><?= esc($b['name']) ?></span><strong><?= $b['remaining'] ?> days</strong></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
