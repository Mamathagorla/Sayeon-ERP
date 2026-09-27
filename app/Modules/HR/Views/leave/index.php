<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('leave.create') && session('roleSlug') !== 'super_admin'): ?>
<a href="<?= site_url('hr/leave/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Apply for Leave</a>
<?php endif; ?>
<?php if (can('leave.edit')): ?>
<a href="<?= site_url('hr/leave/types') ?>" class="btn btn-outline-secondary btn-sm">Manage Leave Types</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php if (session('roleSlug') !== 'super_admin'): ?>
<div class="card mb-3">
    <div class="card-body">
        <h6 class="card-title">Your Leave Balance</h6>
        <div class="d-flex gap-3 flex-wrap small">
            <?php foreach ($balances as $b): ?>
                <span class="badge bg-dark"><?= esc($b['name']) ?>: <?= $b['remaining'] ?>/<?= $b['quota'] ?> left</span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">My Leave Requests</h6></div>
    <div class="card-body">
        <?php if (empty($myRequests)): ?>
            <p class="text-muted mb-0">You haven't applied for any leave yet.</p>
        <?php else: ?>
        <table class="table table-sm table-striped">
            <thead>
                <tr>
                    <th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($myRequests as $r): ?>
                <tr>
                    <td><?= esc($r['leave_type_name']) ?></td>
                    <td><?= esc(date('d/m/Y', strtotime($r['start_date']))) ?></td>
                    <td><?= esc(date('d/m/Y', strtotime($r['end_date']))) ?></td>
                    <td><?= esc($r['days']) ?></td>
                    <td class="text-muted small"><?= esc($r['reason'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'][$r['status']] ?>"><?= esc(ucfirst($r['status'])) ?></span></td>
                    <td class="text-end">
                        <?php if ($r['status'] === 'pending'): ?>
                            <form action="<?= site_url('hr/leave/' . $r['id'] . '/cancel') ?>" method="post" class="d-inline" onsubmit="return confirm('Cancel this request?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Cancel</button>
                            </form>
                        <?php else: ?>
                            <?= $r['approver_name'] ? '<span class="text-muted small">by ' . esc($r['approver_name']) . '</span>' : '' ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($canApprove): ?>
<div class="card">
    <div class="card-header"><h6 class="mb-0">Leave Requests to Review</h6></div>
    <div class="card-body">
        <?php if (empty($teamRequests)): ?>
            <p class="text-muted mb-0">No leave requests from your team.</p>
        <?php else: ?>
        <table class="table table-sm table-striped">
            <thead>
                <tr>
                    <th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($teamRequests as $r): ?>
                <tr>
                    <td><a href="<?= site_url('hr/leave/employee/' . $r['user_id']) ?>"><?= esc($r['user_name']) ?></a></td>
                    <td><?= esc($r['leave_type_name']) ?></td>
                    <td><?= esc(date('d/m/Y', strtotime($r['start_date']))) ?></td>
                    <td><?= esc(date('d/m/Y', strtotime($r['end_date']))) ?></td>
                    <td><?= esc($r['days']) ?></td>
                    <td class="text-muted small"><?= esc($r['reason'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'][$r['status']] ?>"><?= esc(ucfirst($r['status'])) ?></span></td>
                    <td class="text-end">
                        <?php if ($r['status'] === 'pending'): ?>
                            <form action="<?= site_url('hr/leave/' . $r['id'] . '/approve') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Approve"><i class="fas fa-check"></i></button>
                            </form>
                            <form action="<?= site_url('hr/leave/' . $r['id'] . '/reject') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject"><i class="fas fa-xmark"></i></button>
                            </form>
                        <?php else: ?>
                            <?= $r['approver_name'] ? '<span class="text-muted small">by ' . esc($r['approver_name']) . '</span>' : '' ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>
