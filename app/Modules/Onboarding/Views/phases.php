<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/onboarding') ?>" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Onboarding</a>
<a href="<?= site_url('hr/onboarding/phases/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Phase</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="alert alert-info small">
    These are the phases every candidate's checklist is grouped into (see the Onboarding tab on a candidate's page). Reordering or renaming a phase here updates it everywhere it's shown; deleting one is blocked while it still has active or completed checklist items.
</div>

<div class="sy-card" style="height:auto">
    <div class="sy-card-header"><strong>Phases</strong> <span class="text-muted small"><?= count($phases) ?></span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($phases)): ?>
            <p class="text-muted small mb-0 p-3">No phases yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th style="width:90px">Order</th><th>Name</th><th>Description</th><th class="text-center">Tasks</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($phases as $i => $p): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-1">
                            <span class="text-muted small"><?= (int) $p['sort_order'] ?></span>
                            <div class="btn-group btn-group-sm">
                                <form action="<?= site_url('hr/onboarding/phases/' . $p['id'] . '/move-up') ?>" method="post">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-light btn-sm" title="Move up" <?= $i === 0 ? 'disabled' : '' ?>><i class="fas fa-arrow-up"></i></button>
                                </form>
                                <form action="<?= site_url('hr/onboarding/phases/' . $p['id'] . '/move-down') ?>" method="post">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-light btn-sm" title="Move down" <?= $i === count($phases) - 1 ? 'disabled' : '' ?>><i class="fas fa-arrow-down"></i></button>
                                </form>
                            </div>
                        </div>
                    </td>
                    <td class="fw-semibold"><?= esc($p['name']) ?></td>
                    <td class="text-muted small"><?= esc($p['description'] ?? '—') ?></td>
                    <td class="text-center"><?= (int) ($taskCounts[$p['id']] ?? 0) ?></td>
                    <td><span class="badge bg-<?= $p['is_active'] ? 'success' : 'secondary' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td class="text-end">
                        <div class="d-inline-flex align-items-center gap-2">
                            <a href="<?= site_url('hr/onboarding/phases/' . $p['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                            <form action="<?= site_url('hr/onboarding/phases/' . $p['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Delete this phase? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
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
