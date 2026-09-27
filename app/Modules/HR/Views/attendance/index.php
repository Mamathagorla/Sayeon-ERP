<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (session('roleSlug') !== 'super_admin'): ?>
<a href="<?= site_url('hr/attendance/mine') ?>" class="btn btn-primary btn-sm"><i class="fas fa-user-clock me-1"></i>My Attendance</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php if ($isPersonalScope): ?>
<div class="alert alert-info py-2 small">Showing only your own attendance.</div>
<?php endif; ?>

<form method="get" class="card mb-3 filter-form">
    <div class="card-body d-flex gap-2 flex-wrap align-items-end">
        <?php if (! $isPersonalScope): ?>
        <div style="min-width:170px">
            <label class="form-label small mb-1">Employee</label>
            <?php $selectedUserIds = array_map('strval', (array) ($filters['user_id'] ?? [])); ?>
            <div class="dropdown sy-msel" data-placeholder="All">
                <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <span class="sy-msel-label">All</span>
                </button>
                <div class="dropdown-menu sy-msel-menu">
                    <?php foreach ($users as $u): ?>
                        <label class="sy-msel-item" data-label="<?= esc($u['name']) ?>">
                            <input type="checkbox" class="sy-msel-opt" name="user_id[]" value="<?= $u['id'] ?>" <?= in_array((string) $u['id'], $selectedUserIds, true) ? 'checked' : '' ?>>
                            <?= esc($u['name']) ?>
                        </label>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div>
            <label class="form-label small mb-1">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= esc($filters['date_from'] ?? '') ?>">
        </div>
        <div>
            <label class="form-label small mb-1">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= esc($filters['date_to'] ?? '') ?>">
        </div>
        <div style="min-width:160px">
            <label class="form-label small mb-1">Status</label>
            <?php $selectedAttStatuses = (array) ($filters['status'] ?? []); ?>
            <div class="dropdown sy-msel" data-placeholder="All">
                <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <span class="sy-msel-label">All</span>
                </button>
                <div class="dropdown-menu sy-msel-menu">
                    <?php foreach ($statuses as $s): ?>
                        <label class="sy-msel-item" data-label="<?= esc(ucwords(str_replace('_', ' ', $s))) ?>">
                            <input type="checkbox" class="sy-msel-opt" name="status[]" value="<?= $s ?>" <?= in_array($s, $selectedAttStatuses, true) ? 'checked' : '' ?>>
                            <?= esc(ucwords(str_replace('_', ' ', $s))) ?>
                        </label>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                </div>
            </div>
        </div>
        <a href="<?= site_url('hr/attendance') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <?php if (empty($records)): ?>
            <p class="text-muted mb-0">No attendance records match these filters.</p>
        <?php else: ?>
        <table class="table table-sm table-striped">
            <thead><tr>
                <?php if (! $isPersonalScope): ?><th>Employee</th><th>Company</th><?php endif; ?>
                <th>Date</th><th>Check In</th><th>Check Out</th><th>Status</th>
                <?php if ($canSeeLogins): ?><th>Last Login</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <tr>
                    <?php if (! $isPersonalScope): ?>
                        <td><a href="<?= site_url('hr/attendance/employee/' . $r['user_id']) ?>"><?= esc($r['user_name']) ?></a></td>
                        <td><?= esc($r['company_name'] ?? '—') ?></td>
                    <?php endif; ?>
                    <td><?= esc(date('d/m/Y', strtotime($r['date']))) ?></td>
                    <td><?= $r['check_in'] ? esc(date('g:i A', strtotime($r['check_in']))) : '—' ?></td>
                    <td><?= $r['check_out'] ? esc(date('g:i A', strtotime($r['check_out']))) : '—' ?></td>
                    <td><span class="badge bg-secondary"><?= esc(ucwords(str_replace('_', ' ', $r['status']))) ?></span></td>
                    <?php if ($canSeeLogins): ?>
                        <td><?= $r['last_login_at'] ? esc(date('d/m/Y, g:i A', strtotime($r['last_login_at']))) : '—' ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
