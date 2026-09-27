<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<div class="btn-group btn-group-sm" role="group">
    <a href="<?= site_url('notifications') ?>" class="btn btn-outline-secondary <?= ! $onlyUnread ? 'active' : '' ?>">All</a>
    <a href="<?= site_url('notifications?unread=1') ?>" class="btn btn-outline-secondary <?= $onlyUnread ? 'active' : '' ?>">Unread</a>
</div>
<form action="<?= site_url('notifications/read-all') ?>" method="post" class="d-inline">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary btn-sm ms-2"><i class="fas fa-check-double me-1"></i>Mark all read</button>
</form>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <?php if (empty($notifications)): ?>
            <p class="text-muted mb-0"><?= $onlyUnread ? 'No unread notifications.' : 'No notifications yet.' ?></p>
        <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($notifications as $n): ?>
                <div class="list-group-item d-flex justify-content-between align-items-start <?= $n['is_read'] ? '' : 'bg-body-secondary' ?>">
                    <div>
                        <div class="fw-semibold">
                            <?php if (! $n['is_read']): ?><span class="badge bg-primary me-1">New</span><?php endif; ?>
                            <?= $n['link'] ? '<a href="' . $n['link'] . '" class="text-decoration-none">' . esc($n['title']) . '</a>' : esc($n['title']) ?>
                        </div>
                        <div class="text-muted small"><?= esc($n['message']) ?></div>
                        <div class="text-muted small"><?= esc(date('d/m/Y, g:i A', strtotime($n['created_at']))) ?></div>
                    </div>
                    <?php if (! $n['is_read']): ?>
                    <form action="<?= site_url('notifications/' . $n['id'] . '/read') ?>" method="post">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Mark read</button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
