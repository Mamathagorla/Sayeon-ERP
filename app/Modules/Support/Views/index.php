<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('help/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Raise a Ticket</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 filter-form">
            <div class="col-md-3">
                <?php $selectedTicketStatuses = (array) ($filters['status'] ?? []); ?>
                <div class="dropdown sy-msel" data-placeholder="All Statuses">
                    <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span class="sy-msel-label">All Statuses</span>
                    </button>
                    <div class="dropdown-menu sy-msel-menu">
                        <?php foreach ($statuses as $s): ?>
                            <label class="sy-msel-item" data-label="<?= esc(ucwords(str_replace('_', ' ', $s))) ?>">
                                <input type="checkbox" class="sy-msel-opt" name="status[]" value="<?= $s ?>" <?= in_array($s, $selectedTicketStatuses, true) ? 'checked' : '' ?>>
                                <?= esc(ucwords(str_replace('_', ' ', $s))) ?>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($tickets)): ?>
            <p class="text-muted small mb-0"><?= $isResolver ? 'No tickets raised yet.' : "You haven't raised any tickets yet." ?></p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Ticket</th><th>Subject</th><?php if ($isResolver): ?><th>Company</th><th>Raised By</th><?php endif; ?><th>Category</th><th>Priority</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td class="text-muted small">#TKT<?= str_pad((string) $t['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td><a href="<?= site_url('help/' . $t['id']) ?>" class="fw-semibold text-decoration-none"><?= esc($t['subject']) ?></a></td>
                    <?php if ($isResolver): ?>
                    <td class="text-muted small"><?= esc($t['company_name'] ?? 'General') ?></td>
                    <td class="text-muted small"><?= esc($t['raiser_name']) ?></td>
                    <?php endif; ?>
                    <td class="text-muted small"><?= esc($categoryLabels[$t['category']] ?? 'General') ?></td>
                    <td><span class="badge bg-<?= ['low' => 'secondary', 'medium' => 'info text-dark', 'high' => 'danger'][$t['priority']] ?>"><?= esc(ucfirst($t['priority'])) ?></span></td>
                    <td><span class="badge bg-<?= ['open' => 'warning text-dark', 'in_progress' => 'info text-dark', 'resolved' => 'success', 'closed' => 'secondary'][$t['status']] ?>"><?= esc(ucwords(str_replace('_', ' ', $t['status']))) ?></span></td>
                    <td class="text-muted small"><?= esc(date('d/m/Y', strtotime($t['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
