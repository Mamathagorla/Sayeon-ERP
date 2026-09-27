<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/attendance') ?>" class="btn btn-outline-secondary btn-sm">Team View</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-md-5">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted">Today — <?= esc(date('d/m/Y')) ?></h6>
                <?php if (! $today || ! $today['check_in']): ?>
                    <p class="text-muted small">You haven't checked in yet.</p>
                    <form action="<?= site_url('hr/attendance/check-in') ?>" method="post">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-success"><i class="fas fa-right-to-bracket me-1"></i>Check In</button>
                    </form>
                <?php elseif (! $today['check_out']): ?>
                    <p class="small">Checked in at <strong><?= esc(date('g:i A', strtotime($today['check_in']))) ?></strong></p>
                    <form action="<?= site_url('hr/attendance/check-out') ?>" method="post">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-right-from-bracket me-1"></i>Check Out</button>
                    </form>
                <?php else: ?>
                    <p class="small mb-0">Checked in at <strong><?= esc(date('g:i A', strtotime($today['check_in']))) ?></strong></p>
                    <p class="small">Checked out at <strong><?= esc(date('g:i A', strtotime($today['check_out']))) ?></strong></p>
                    <span class="badge bg-success">Day complete</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title"><?= esc($monthLabel) ?> Summary</h6>
                <div class="d-flex gap-3 flex-wrap small">
                    <?php foreach ($summary as $status => $count): ?>
                        <span class="badge bg-dark"><?= esc(ucwords(str_replace('_', ' ', $status))) ?>: <?= $count ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <h6 class="card-title">Recent History</h6>
        <?php if (empty($recent)): ?>
            <p class="text-muted mb-0">No attendance records yet.</p>
        <?php else: ?>
        <table class="table table-sm table-striped">
            <thead><tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?= esc(date('d/m/Y', strtotime($r['date']))) ?></td>
                    <td><?= $r['check_in'] ? esc(date('g:i A', strtotime($r['check_in']))) : '—' ?></td>
                    <td><?= $r['check_out'] ? esc(date('g:i A', strtotime($r['check_out']))) : '—' ?></td>
                    <td><span class="badge bg-secondary"><?= esc(ucwords(str_replace('_', ' ', $r['status']))) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
