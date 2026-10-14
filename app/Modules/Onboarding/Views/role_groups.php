<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/onboarding/role-groups/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Role Group</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="alert alert-info small">
    The candidate form adapts to the role. Each <strong>role group</strong> below holds the extra questions candidates answer and the criteria HR can score them on.
    A candidate is matched to a group when its <em>keywords</em> appear in the job title (strongest), department or job description; if nothing matches, the
    <strong>General</strong> group is used. Shared defaults are read-only for companies — use <em>Customise for my company</em> to make your own editable copy
    (it replaces the default for your company only).
</div>

<div class="sy-card" style="height:auto">
    <div class="sy-card-header"><strong>Role groups</strong> <span class="text-muted small"><?= count($groups) ?></span></div>
    <div class="sy-card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>Name</th><th>Applies to</th><th>Matches on</th><th class="text-center">Questions</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($groups as $g): ?>
                    <?php $shared = $g['company_id'] === null; $hidden = $shared && $scope !== null && isset($overridden[$g['slug']]); ?>
                    <tr class="<?= $hidden ? 'text-muted' : '' ?>">
                        <td class="fw-semibold"><?= esc($g['name']) ?><div class="text-muted small fw-normal"><?= esc($g['slug']) ?></div></td>
                        <td class="small">
                            <?php if ($shared): ?><span class="badge bg-light text-dark border">Shared default</span><?php else: ?><span class="badge bg-info text-dark"><?= esc($companyNames[(int) $g['company_id']] ?? 'Company') ?></span><?php endif; ?>
                            <?php if ($hidden): ?><div class="text-muted">Overridden by your company copy</div><?php endif; ?>
                        </td>
                        <td class="small text-muted" style="max-width:320px"><?= $g['keywords'] ? esc(mb_strimwidth($g['keywords'], 0, 110, '…')) : '<em>fallback — used when nothing else matches</em>' ?></td>
                        <td class="text-center"><?= (int) ($questionCounts[(int) $g['id']] ?? 0) ?></td>
                        <td><span class="badge bg-<?= $g['is_active'] ? 'success' : 'secondary' ?>"><?= $g['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">
                                <a href="<?= site_url('hr/onboarding/role-groups/' . $g['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><?= $canEdit($g) ? '<i class="fas fa-pen"></i>' : '<i class="fas fa-eye"></i>' ?></a>
                                <?php if ($shared && $scope !== null && $scope > 0 && ! $hidden): ?>
                                    <form action="<?= site_url('hr/onboarding/role-groups/' . $g['id'] . '/clone') ?>" method="post"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary" type="submit">Customise for my company</button></form>
                                <?php endif; ?>
                                <?php if ($canEdit($g) && ! ($shared && $g['slug'] === 'general')): ?>
                                    <form action="<?= site_url('hr/onboarding/role-groups/' . $g['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Delete this role group, its questions and criteria? Answers candidates already gave are kept.');"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash"></i></button></form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
