<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('meeting.create')): ?>
<form action="<?= site_url('recurring-meetings/generate-now') ?>" method="post" class="d-inline">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate me-1"></i>Generate Now</button>
</form>
<a href="<?= site_url('recurring-meetings/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Recurring Meeting</a>
<?php endif; ?>
<a href="<?= site_url('meetings') ?>" class="btn btn-light btn-sm">Back to Meetings</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<form method="get" class="sy-card mb-3 filter-form">
    <div class="sy-card-body py-2 d-flex gap-2 flex-wrap align-items-end">
        <div>
            <label class="form-label small mb-1">Company</label>
            <select name="company_id" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (($filters['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= (($filters['status'] ?? null) === $s) ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <a href="<?= site_url('recurring-meetings') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<div class="sy-card">
    <div class="sy-card-header"><strong>Recurring Meetings</strong> <span class="text-muted small"><?= count($templates) ?> templates</span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($templates)): ?>
            <p class="text-muted mb-0 p-3">No recurring meetings set up yet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Company</th>
                    <th>Frequency</th>
                    <th>Next Occurrence</th>
                    <th>Status</th>
                    <th class="text-end">Generated</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($templates as $t): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($t['title']) ?></td>
                    <td class="text-muted small"><?= esc($t['company_name']) ?></td>
                    <td class="text-muted small"><?= esc(ucfirst($t['frequency'])) ?></td>
                    <td class="text-muted small"><?= $t['next_generation_date'] ? esc(date('d/m/Y', strtotime($t['next_generation_date']))) : '—' ?></td>
                    <td>
                        <?php $statusColors = ['active' => 'success', 'paused' => 'warning text-dark', 'completed' => 'secondary', 'cancelled' => 'danger']; ?>
                        <span class="badge bg-<?= $statusColors[$t['status']] ?>"><?= esc(ucfirst($t['status'])) ?></span>
                    </td>
                    <td class="text-end text-muted small">
                        <?= (int) $t['occurrences_generated'] ?><?= $t['end_type'] === 'occurrences' ? ' / ' . (int) $t['occurrences_total'] : '' ?>
                    </td>
                    <td class="text-end">
                        <?php if (can('meeting.edit')): ?>
                        <a href="<?= site_url('recurring-meetings/' . $t['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (can('meeting.edit') && $t['status'] === 'active'): ?>
                        <form action="<?= site_url('recurring-meetings/' . $t['id'] . '/pause') ?>" method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Pause"><i class="fas fa-pause"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if (can('meeting.edit') && $t['status'] === 'paused'): ?>
                        <form action="<?= site_url('recurring-meetings/' . $t['id'] . '/resume') ?>" method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Resume"><i class="fas fa-play"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if (can('meeting.delete') && ! in_array($t['status'], ['completed', 'cancelled'], true)): ?>
                        <form action="<?= site_url('recurring-meetings/' . $t['id'] . '/cancel') ?>" method="post" class="d-inline" onsubmit="return confirm('Cancel this recurring meeting? Already-scheduled meetings will stay in the Meetings list.');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel"><i class="fas fa-ban"></i></button>
                        </form>
                        <?php endif; ?>
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
