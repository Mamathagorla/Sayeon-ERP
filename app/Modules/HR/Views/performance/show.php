<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if ($review['status'] === 'draft' && can('performance.edit')): ?>
<a href="<?= site_url('hr/performance/' . $review['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<form action="<?= site_url('hr/performance/' . $review['id'] . '/submit') ?>" method="post" class="d-inline" onsubmit="return confirm('Submit this review? The employee will be notified.');">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane me-1"></i>Submit</button>
</form>
<?php endif; ?>
<?php if ($isOwner && $review['status'] === 'submitted'): ?>
<form action="<?= site_url('hr/performance/' . $review['id'] . '/acknowledge') ?>" method="post" class="d-inline">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>Acknowledge</button>
</form>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <h5 class="card-title">
            <?= esc($review['user_name']) ?> — <?= esc($review['cycle_name']) ?>
            <span class="badge bg-<?= ['draft' => 'secondary', 'submitted' => 'info', 'acknowledged' => 'success'][$review['status']] ?>"><?= esc(ucfirst($review['status'])) ?></span>
        </h5>
        <p class="text-muted small">Reviewer: <?= esc($review['reviewer_name']) ?></p>

        <dl class="row">
            <dt class="col-sm-3">Rating</dt><dd class="col-sm-9"><?= $review['rating'] ? esc($review['rating']) . ' / 5' : 'Not rated' ?></dd>
            <dt class="col-sm-3">Strengths</dt><dd class="col-sm-9"><?= nl2br(esc($review['strengths'] ?? '—')) ?></dd>
            <dt class="col-sm-3">Areas for Improvement</dt><dd class="col-sm-9"><?= nl2br(esc($review['improvements'] ?? '—')) ?></dd>
            <dt class="col-sm-3">Goals for Next Cycle</dt><dd class="col-sm-9"><?= nl2br(esc($review['goals_next'] ?? '—')) ?></dd>
        </dl>
    </div>
</div>
<?= $this->endSection() ?>
