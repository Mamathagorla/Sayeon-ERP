<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('document.create')): ?>
<a href="<?= site_url('documents/templates/clone') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-clone me-1"></i>Clone Template</a>
<a href="<?= site_url('documents/templates/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-upload me-1"></i>Upload Template</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<form method="get" class="card mb-3 filter-form">
    <div class="card-body d-flex gap-2 flex-wrap align-items-end">
        <?php if (count($companies) !== 1): ?>
        <div>
            <label class="form-label small mb-1">Company</label>
            <select name="company_id" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (($filters['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label class="form-label small mb-1">Document Type</label>
            <select name="document_type" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($types as $key => $t): ?>
                    <option value="<?= $key ?>" <?= (($filters['document_type'] ?? '') === $key) ? 'selected' : '' ?>><?= esc($t['group'] . ' — ' . $t['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>Active</option>
                <option value="draft" <?= (($filters['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft</option>
                <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= site_url('documents/templates') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<?php $workflowNames = \App\Modules\Document\Services\DocumentTypeRegistry::WORKFLOW_LABELS; ?>
<div class="card mb-3">
    <div class="card-body py-2">
        <button class="btn btn-sm btn-link text-decoration-none p-0 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#docOverview" aria-expanded="false" aria-controls="docOverview">
            <i class="fas fa-list-check me-1"></i>What users are offered <span class="text-muted fw-normal">(from active templates)</span>
        </button>
        <div class="collapse mt-2" id="docOverview">
            <?php foreach ($companies as $c): $byWf = $overview[(int) $c['id']] ?? []; ?>
                <div class="mb-2">
                    <div class="fw-semibold small"><?= esc($c['name']) ?></div>
                    <?php if (! $byWf): ?>
                        <div class="small text-muted">No active templates: no document actions are shown for this company's users.</div>
                    <?php else: foreach ($byWf as $wf => $labels): ?>
                        <div class="small"><span class="text-muted"><?= esc($workflowNames[$wf] ?? ucfirst($wf)) ?>:</span>
                            <?php foreach ($labels as $l): ?><span class="badge bg-success-subtle text-success-emphasis border me-1"><?= esc($l) ?></span><?php endforeach; ?>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="small text-muted">Activating or deactivating a template changes this immediately; no employee record needs editing.</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($templates)): ?>
            <div class="text-center py-4">
                <i class="fas fa-file-code fa-2x text-muted mb-2"></i>
                <p class="text-muted mb-2">No templates uploaded yet.</p>
                <?php if (can('document.create')): ?>
                <a href="<?= site_url('documents/templates/create') ?>" class="btn btn-primary btn-sm">Upload the first template</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
            <thead><tr><th>Document Type</th><th>Template</th><th>Company</th><th>Version</th><th>Status</th><th>Updated</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($templates as $t): ?>
                <tr>
                    <td>
                        <?= esc($types[$t['document_type']]['label'] ?? $t['document_type']) ?>
                        <div class="small text-muted"><?= esc($types[$t['document_type']]['group'] ?? '') ?></div>
                    </td>
                    <td>
                        <a href="<?= site_url('documents/templates/' . $t['id']) ?>"><?= esc($t['template_name']) ?></a>
                        <?php if (! empty($t['source_template_id']) && ! empty($t['source_company_name'])): ?><div class="small text-muted">Cloned from <?= esc($t['source_company_name']) ?> v<?= (int) $t['source_version'] ?></div><?php endif; ?>
                        <?php if (! empty($t['notes'])): ?><div class="small text-muted"><?= esc($t['notes']) ?></div><?php endif; ?>
                    </td>
                    <td><?= esc($t['company_name']) ?></td>
                    <td><span class="badge bg-secondary">v<?= (int) $t['version'] ?></span> <span class="badge bg-light text-dark border"><?= strtoupper(esc($t['file_type'])) ?></span>
                        <?php if ($t['file_type'] === 'pdf'): $n = count(json_decode((string) $t['field_map'], true) ?: []); ?>
                            <span class="badge bg-info text-dark"><?= $n ?> fields detected</span>
                        <?php endif; ?>
                        <?php $blk = \App\Modules\Document\Services\TemplatedDocument::blockingFlags($t); if ($blk): ?>
                            <a href="<?= site_url('documents/templates/' . $t['id']) ?>" class="badge bg-warning text-dark text-decoration-none">Needs review (<?= count($blk) ?>)</a>
                        <?php endif; ?></td>
                    <td>
                        <?php if ($t['status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php elseif ($t['status'] === 'draft'): ?>
                            <span class="badge bg-warning text-dark">Draft</span><?= $t['previewed_at'] ? ' <span class="badge bg-info text-dark">Previewed</span>' : '' ?>
                        <?php else: ?>
                            <span class="badge bg-light text-dark border">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc(erp_datetime($t['updated_at'] ?? $t['created_at'])) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= site_url('documents/templates/' . $t['id']) ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="fas fa-eye"></i></a>
                        <a href="<?= site_url('documents/templates/' . $t['id'] . '/test') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Test with a real record from this company"><i class="fas fa-flask"></i></a>
                        <?php if (can('document.create')): ?>
                        <a href="<?= site_url('documents/templates/clone?source=' . $t['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Clone to a company"><i class="fas fa-clone"></i></a>
                        <?php endif; ?>
                        <a href="<?= site_url('documents/templates/' . $t['id'] . '/download') ?>" class="btn btn-sm btn-outline-secondary" title="Download"><i class="fas fa-download"></i></a>
                        <?php if ($t['file_type'] === 'pdf' && can('document.edit')): ?>
                        <a href="<?= site_url('documents/templates/' . $t['id'] . '/map') ?>" class="btn btn-sm btn-outline-primary" title="Review / adjust fields"><i class="fas fa-crosshairs"></i></a>
                        <?php endif; ?>
                        <?php if (can('document.create')): ?>
                        <a href="<?= site_url('documents/templates/create?draft=1&company_id=' . $t['company_id'] . '&document_type=' . urlencode($t['document_type'])) ?>" class="btn btn-sm btn-outline-secondary" title="Replace / upload as new draft version"><i class="fas fa-code-branch"></i></a>
                        <?php endif; ?>
                        <?php if (can('document.edit')): ?>
                            <?php if ($t['status'] === 'active'): ?>
                            <form action="<?= site_url('documents/templates/' . $t['id'] . '/deactivate') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-warning" title="Deactivate"><i class="fas fa-toggle-on"></i></button>
                            </form>
                            <?php elseif (empty($blk) && ($t['status'] !== 'draft' || ! empty($t['previewed_at']))): ?>
                            <form action="<?= site_url('documents/templates/' . $t['id'] . '/activate') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-success" title="Activate this version"><i class="fas fa-toggle-off"></i></button>
                            </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (can('document.delete') && $t['status'] !== 'active'): ?>
                        <form action="<?= site_url('documents/templates/' . $t['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Delete this template version?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="small text-muted mb-0 mt-2">Only the <strong>Active</strong> version of each document type is used for new documents. Documents already generated are never changed.</p>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
