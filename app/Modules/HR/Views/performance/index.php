<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('performance.create')): ?>
<a href="<?= site_url('hr/performance/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Review</a>
<?php endif; ?>
<?php if (can('performance.edit')): ?>
<a href="<?= site_url('hr/performance/cycles') ?>" class="btn btn-outline-secondary btn-sm">Manage Cycles</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php if ($isPersonalScope): ?>
<div class="alert alert-info py-2 small">Showing only your own performance reviews.</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if (empty($reviews)): ?>
            <p class="text-muted mb-0">No performance reviews yet.</p>
        <?php else: ?>
        <table class="table table-sm table-striped">
            <thead><tr><?php if (! $isPersonalScope): ?><th>Employee</th><?php endif; ?><th>Cycle</th><th>Reviewer</th><th>Rating</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($reviews as $r): ?>
                <tr>
                    <?php if (! $isPersonalScope): ?><td><?= esc($r['user_name']) ?></td><?php endif; ?>
                    <td><a href="<?= site_url('hr/performance/' . $r['id']) ?>"><?= esc($r['cycle_name']) ?></a></td>
                    <td><?= esc($r['reviewer_name']) ?></td>
                    <td><?= $r['rating'] ? esc($r['rating']) . ' / 5' : '—' ?></td>
                    <td><span class="badge bg-<?= ['draft' => 'secondary', 'submitted' => 'info', 'acknowledged' => 'success'][$r['status']] ?>"><?= esc(ucfirst($r['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
