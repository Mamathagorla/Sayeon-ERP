<?= $this->extend('layouts/main') ?>

<?php
$bucketMeta = [
    'not_started' => ['label' => 'Not Started', 'color' => '#8a929e', 'icon' => 'fa-circle'],
    'in_progress' => ['label' => 'In Progress', 'color' => '#2563eb', 'icon' => 'fa-circle'],
    'completed'   => ['label' => 'Completed', 'color' => '#16a34a', 'icon' => 'fa-circle-check'],
];
$selectedBuckets = (array) ($filters['status'] ?? []);
$statusLabel = static fn (string $status): string => \App\Modules\Onboarding\Models\OnboardingRecordModel::STAGE_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
$nextAction = static fn (string $status): string => match ($status) {
    'candidate' => 'Select candidate',
    'selected_for_onboarding', 'selected' => 'Send onboarding link',
    'link_sent' => 'Await candidate details',
    'details_submitted' => 'Review candidate',
    'hr_review' => 'Complete HR review',
    'approved' => 'Send offer',
    'offer_sent' => 'Await candidate response',
    'offer_accepted' => 'Start joining checklist',
    'rejected' => 'Recruitment closed',
    'withdrawn' => 'Candidate withdrawn',
    'joined' => 'Complete onboarding checklist',
    'confirmed' => 'View employee record',
    default => 'Continue onboarding',
};
$statusBadge = static fn (string $status): string => match ($status) {
    'candidate', 'selected_for_onboarding', 'selected' => 'bg-primary',
    'link_sent' => 'bg-info text-dark',
    'details_submitted', 'hr_review' => 'bg-warning text-dark',
    'approved', 'offer_accepted', 'joined', 'induction', 'probation', 'confirmed' => 'bg-success',
    'offer_sent' => 'bg-warning text-dark',
    'rejected', 'withdrawn' => 'bg-secondary',
    default => 'bg-light text-dark border',
};
?>

<?= $this->section('pageActions') ?>
<?php if ($isOwner): ?><a href="<?= site_url('hr/onboarding/phases') ?>" class="btn btn-light btn-sm"><i class="fas fa-diagram-project me-1"></i>Manage Phases</a><?php endif; ?>
<?php if ($isOwner): ?><a href="<?= site_url('hr/onboarding/role-groups') ?>" class="btn btn-light btn-sm"><i class="fas fa-list-check me-1"></i>Candidate Questions</a><?php endif; ?>
<?php if (can('onboarding.create')): ?><a href="<?= site_url('hr/onboarding/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Candidate</a><?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row row-cols-2 row-cols-lg-4 g-2 mb-3">
    <div class="col"><a href="<?= site_url('hr/onboarding') ?>" class="text-decoration-none"><div class="sy-stat-card"><div class="sy-stat-label">TOTAL CANDIDATES</div><div class="sy-stat-value"><?= array_sum($statusCounts) ?></div></div></a></div>
    <?php foreach ($bucketMeta as $bucket => $meta): ?>
    <div class="col"><a href="<?= site_url('hr/onboarding?status=' . $bucket) ?>" class="text-decoration-none"><div class="sy-stat-card"><div class="sy-stat-label"><?= esc(mb_strtoupper($meta['label'])) ?></div><div class="sy-stat-value"><?= (int) $statusCounts[$bucket] ?></div></div></a></div>
    <?php endforeach; ?>
</div>

<div class="sy-card mb-2" style="height:auto">
    <div class="sy-card-body py-2">
        <form method="get" class="row g-2 align-items-end filter-form">
            <div class="col-12 col-md-4"><label class="form-label small mb-1" for="onb-q">Search</label><input id="onb-q" type="search" name="q" class="form-control form-control-sm" placeholder="Search by candidate name…" value="<?= esc($filters['q'] ?? '') ?>"></div>
            <div class="col-6 col-md-3"><label class="form-label small mb-1" for="onb-department">Department</label><select id="onb-department" name="department_id" class="form-select form-select-sm"><option value="">All Departments</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>" <?= (string) ($filters['department_id'] ?? '') === (string) $department['id'] ? 'selected' : '' ?>><?= esc($department['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1">Status</label><div class="dropdown sy-msel" data-placeholder="All Statuses"><button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle w-100" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><span class="sy-msel-label">All Statuses</span></button><div class="dropdown-menu sy-msel-menu"><?php foreach ($bucketMeta as $bucket => $meta): ?><label class="sy-msel-item" data-label="<?= esc($meta['label']) ?>"><input type="checkbox" class="sy-msel-opt" name="status[]" value="<?= esc($bucket) ?>" <?= in_array($bucket, $selectedBuckets, true) ? 'checked' : '' ?>><?= esc($meta['label']) ?></label><?php endforeach; ?><button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button></div></div></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="onb-joining">Joining Date</label><input id="onb-joining" type="date" name="joining_from" class="form-control form-control-sm" value="<?= esc($filters['joining_from'] ?? '') ?>"></div>
            <div class="col-6 col-md-1 d-flex gap-1"><button type="submit" class="btn btn-primary btn-sm flex-grow-1" title="Apply filters"><i class="fas fa-filter"></i></button><?php if (array_filter($filters)): ?><a href="<?= site_url('hr/onboarding') ?>" class="btn btn-light btn-sm" title="Clear filters"><i class="fas fa-xmark"></i></a><?php endif; ?></div>
        </form>
    </div>
</div>

<div class="sy-card" style="height:auto">
    <div class="sy-card-body p-0">
        <?php if (empty($records)): ?>
            <p class="text-muted small mb-0 p-3">No candidates match these filters.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Candidate</th><th>Role</th><th>Company</th><th>Status</th><th>Next Action</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($records as $candidate): ?>
                    <?php $candidateUrl = site_url('hr/onboarding/' . $candidate['id']); ?>
                    <tr class="onb-click-row" data-href="<?= esc($candidateUrl) ?>" tabindex="0" role="link" aria-label="Open <?= esc($candidate['candidate_name']) ?> onboarding details">
                        <td><a href="<?= esc($candidateUrl) ?>" class="text-decoration-none fw-semibold text-dark"><?= esc($candidate['candidate_name']) ?></a><div class="text-muted small"><?= esc($candidate['candidate_email']) ?></div></td>
                        <td><?= esc($candidate['designation'] ?: '—') ?></td>
                        <td><?= esc($candidate['company_name']) ?></td>
                        <td><span class="badge <?= esc($statusBadge($candidate['status'])) ?>"><?= esc($statusLabel($candidate['status'])) ?></span></td>
                        <td class="text-muted small"><?= esc($nextAction($candidate['status'])) ?></td>
                        <td class="text-end text-nowrap"><a href="<?= esc($candidateUrl) ?>" class="btn btn-sm btn-light" title="View candidate"><i class="fas fa-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<style>
    .onb-click-row { cursor:pointer; }
    .onb-click-row:focus-visible { outline:2px solid var(--sy-accent-ink); outline-offset:-2px; }
</style>
<script>
document.querySelectorAll('.onb-click-row').forEach((row) => {
    row.addEventListener('click', (event) => { if (!event.target.closest('a,button,form')) window.location.href = row.dataset.href; });
    row.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.href = row.dataset.href; } });
});
</script>
<?= $this->endSection() ?>