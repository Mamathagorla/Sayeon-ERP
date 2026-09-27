<?= $this->extend('layouts/main') ?>

<?php
$statusColors = ['active' => 'success', 'paused' => 'warning text-dark', 'completed' => 'secondary', 'cancelled' => 'danger'];
?>

<?= $this->section('pageActions') ?>
<?php if (can('expense.edit')): ?>
<a href="<?= site_url('recurring-expenses/' . $template['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<?php endif; ?>
<a href="<?= site_url('recurring-expenses') ?>" class="btn btn-light btn-sm">Back to list</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h5 class="mb-1"><?= esc($template['title']) ?></h5>
                <div class="text-muted small"><?= esc($template['company_name']) ?> &middot; <?= esc($template['category']) ?></div>
            </div>
            <span class="badge bg-<?= $statusColors[$template['status']] ?> fs-6"><?= esc(ucfirst($template['status'])) ?></span>
        </div>

        <hr>

        <dl class="row small mb-0">
            <dt class="col-sm-3 text-muted fw-normal">Amount</dt>
            <dd class="col-sm-9 fw-semibold"><?= number_format($template['amount'], 2) ?> / <?= esc($template['frequency']) ?></dd>

            <dt class="col-sm-3 text-muted fw-normal">Start Date</dt>
            <dd class="col-sm-9"><?= esc(date('d/m/Y', strtotime($template['start_date']))) ?></dd>

            <dt class="col-sm-3 text-muted fw-normal">Ends</dt>
            <dd class="col-sm-9"><?= $template['end_type'] === 'occurrences' ? (int) $template['occurrences_total'] . ' occurrences' : 'on ' . esc(date('d/m/Y', strtotime($template['end_date']))) ?></dd>

            <dt class="col-sm-3 text-muted fw-normal">Next Generation Date</dt>
            <dd class="col-sm-9"><?= esc($template['next_generation_date'] ?? '—') ?></dd>

            <dt class="col-sm-3 text-muted fw-normal">Generated So Far</dt>
            <dd class="col-sm-9"><?= (int) $template['occurrences_generated'] ?><?= $template['end_type'] === 'occurrences' ? ' / ' . (int) $template['occurrences_total'] : '' ?></dd>

            <?php if ($template['description']): ?>
            <dt class="col-sm-3 text-muted fw-normal">Description</dt>
            <dd class="col-sm-9"><?= nl2br(esc($template['description'])) ?></dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<div class="sy-card">
    <div class="sy-card-header"><strong>Generated Expense History</strong> <span class="text-muted small"><?= count($history) ?> records</span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($history)): ?>
            <p class="text-muted mb-0 p-3">No expenses generated yet. They'll appear here as soon as this recurring expense's schedule generates them.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Occurrence</th>
                    <th>Expense</th>
                    <th>For Date</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th>Generated On</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td class="text-muted small">#<?= (int) $h['occurrence_number'] ?></td>
                    <td><a href="<?= site_url('expenses/' . $h['id'] . '/edit') ?>" class="text-decoration-none">#EXP<?= str_pad((string) $h['id'], 4, '0', STR_PAD_LEFT) ?></a></td>
                    <td class="text-muted small"><?= esc($h['renewal_date'] ?? '—') ?></td>
                    <td class="text-end fw-semibold"><?= number_format($h['amount'], 2) ?></td>
                    <td><span class="badge bg-<?= $h['status'] === 'active' ? 'success' : 'secondary' ?>"><?= esc(ucfirst($h['status'])) ?></span></td>
                    <td class="text-muted small"><?= esc(date('d/m/Y, g:i A', strtotime($h['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
