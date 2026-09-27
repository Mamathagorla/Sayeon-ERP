<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('policy.edit')): ?>
<a href="<?= site_url('policies/' . $policy['id'] . '/edit') ?>" class="btn btn-light btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<?php endif; ?>
<?php if (can('policy.delete')): ?>
<form action="<?= site_url('policies/' . $policy['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Remove this policy/manual? This cannot be undone.');">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Delete</button>
</form>
<?php endif; ?>
<a href="<?= site_url('policies') ?>" class="btn btn-light btn-sm">Back to list</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h5 class="mb-1"><?= esc($policy['title']) ?></h5>
                <div class="text-muted small"><?= esc(ucfirst($policy['type'])) ?> &middot; <?= esc($policy['company_name']) ?></div>
            </div>
            <span class="badge bg-<?= ['draft' => 'info', 'published' => 'success', 'archived' => 'secondary'][$policy['status']] ?> fs-6"><?= esc(ucfirst($policy['status'])) ?></span>
        </div>

        <hr>

        <div class="row small">
            <div class="col-md-3 mb-2"><span class="text-muted">Owner</span><br><?= esc($policy['owner_name'] ?? '—') ?></div>
            <div class="col-md-3 mb-2"><span class="text-muted">Version</span><br><?= esc($policy['version'] ?: '—') ?></div>
            <div class="col-md-3 mb-2"><span class="text-muted">Last Review</span><br><?= $policy['last_review_date'] ? esc(date('d/m/Y', strtotime($policy['last_review_date']))) : '—' ?></div>
            <div class="col-md-3 mb-2"><span class="text-muted">Retention</span><br><?= $policy['retention_years'] !== null ? esc($policy['retention_years']) . ' Year' . ($policy['retention_years'] == 1 ? '' : 's') : '—' ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Document</strong>
        <?php if (can('policy.edit')): ?>
        <a href="<?= site_url('documents/create?policy_id=' . $policy['id'] . '&company_id=' . $policy['company_id']) ?>"><?= $policy['document_id'] ? 'Replace' : 'Attach' ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($policy['file_path'])): ?>
            <p class="text-muted small mb-0">No document attached yet.</p>
        <?php else: ?>
            <a href="<?= site_url('files/download?path=' . urlencode($policy['file_path'])) ?>" class="text-decoration-none fw-semibold"><i class="fas fa-file-arrow-down me-1"></i><?= esc($policy['file_name']) ?></a>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
