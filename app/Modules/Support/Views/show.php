<?= $this->extend('layouts/main') ?>

<?php
$statusColors   = ['open' => 'warning text-dark', 'in_progress' => 'info text-dark', 'resolved' => 'success', 'closed' => 'secondary'];
$priorityColors = ['low' => 'secondary', 'medium' => 'info text-dark', 'high' => 'danger'];
$ticketNumber   = '#TKT' . str_pad((string) $ticket['id'], 4, '0', STR_PAD_LEFT);
?>

<?= $this->section('pageActions') ?>
<?php if ($isResolver): ?>
<form action="<?= site_url('help/' . $ticket['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Remove this ticket? This cannot be undone.');">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Delete</button>
</form>
<?php endif; ?>
<a href="<?= site_url('help') ?>" class="btn btn-light btn-sm">Back to list</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <h5 class="mb-0"><?= esc($ticketNumber) ?></h5>
                    <div class="d-flex gap-1">
                        <span class="badge bg-<?= $statusColors[$ticket['status']] ?>"><?= esc(ucwords(str_replace('_', ' ', $ticket['status']))) ?></span>
                        <span class="badge bg-<?= $priorityColors[$ticket['priority']] ?>"><?= esc(ucfirst($ticket['priority'])) ?></span>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="sy-avatar flex-shrink-0"><?= esc(mb_strtoupper(mb_substr($ticket['raiser_name'], 0, 1))) ?></span>
                    <div>
                        <div class="fw-semibold"><?= esc($ticket['raiser_name']) ?></div>
                        <div class="text-muted small"><?= esc($ticket['raiser_email']) ?></div>
                    </div>
                </div>

                <hr>

                <dl class="row small mb-0">
                    <dt class="col-6 text-muted fw-normal">Assigned To</dt>
                    <dd class="col-6 text-end"><?= esc($ticket['assignee_name'] ?? 'Unassigned') ?></dd>

                    <dt class="col-6 text-muted fw-normal">Category</dt>
                    <dd class="col-6 text-end"><?= esc($categoryLabels[$ticket['category']] ?? 'General') ?></dd>

                    <dt class="col-6 text-muted fw-normal">Created On</dt>
                    <dd class="col-6 text-end"><?= esc(date('d/m/Y', strtotime($ticket['created_at']))) ?></dd>

                    <dt class="col-6 text-muted fw-normal">Due Date</dt>
                    <dd class="col-6 text-end"><?= $ticket['due_date'] ? esc(date('d/m/Y', strtotime($ticket['due_date']))) : '—' ?></dd>

                    <dt class="col-6 text-muted fw-normal">Last Updated</dt>
                    <dd class="col-6 text-end"><?= esc(date('d/m/Y', strtotime($ticket['updated_at']))) ?></dd>

                    <dt class="col-6 text-muted fw-normal">SLA</dt>
                    <dd class="col-6 text-end"><span class="badge bg-<?= $sla['class'] ?>"><?= esc($sla['label']) ?></span></dd>
                </dl>
            </div>
        </div>

        <?php if ($isResolver): ?>
        <div class="card">
            <div class="card-header"><strong>Ticket Properties</strong></div>
            <div class="card-body">
                <form action="<?= site_url('help/' . $ticket['id'] . '/resolve') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label small mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= $s ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small mb-1">Priority</label>
                        <select name="priority" class="form-select form-select-sm">
                            <?php foreach ($priorities as $p): ?>
                                <option value="<?= $p ?>" <?= $ticket['priority'] === $p ? 'selected' : '' ?>><?= esc(ucfirst($p)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small mb-1">Assigned To</label>
                        <select name="assigned_to" class="form-select form-select-sm">
                            <option value="">Unassigned</option>
                            <?php foreach ($assignees as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= (int) $ticket['assigned_to'] === (int) $u['id'] ? 'selected' : '' ?>><?= esc($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-dark btn-sm w-100">Save Changes</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h5 class="mb-1"><?= esc($ticket['subject']) ?></h5>
                <div class="text-muted small mb-4"><?= esc($ticketNumber) ?> &middot; <?= esc($categoryLabels[$ticket['category']] ?? 'General') ?> &middot; Created <?= esc(date('d/m/Y', strtotime($ticket['created_at']))) ?></div>

                <div class="d-flex gap-2 mb-4">
                    <span class="sy-avatar flex-shrink-0"><?= esc(mb_strtoupper(mb_substr($ticket['raiser_name'], 0, 1))) ?></span>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <strong><?= esc($ticket['raiser_name']) ?></strong>
                            <span class="text-muted small"><?= esc(date('d/m/Y, g:i A', strtotime($ticket['created_at']))) ?></span>
                        </div>
                        <div class="bg-light rounded p-3 mt-1"><?= nl2br(esc($ticket['description'])) ?></div>
                    </div>
                </div>

                <?php if ($messages): ?>
                <h6 class="text-muted mb-3">Conversation</h6>
                <?php foreach ($messages as $m): ?>
                <div id="reply-<?= $m['id'] ?>" class="d-flex gap-2 mb-4">
                    <span class="sy-avatar flex-shrink-0"><?= esc(mb_strtoupper(mb_substr($m['author_name'], 0, 1))) ?></span>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <strong><?= esc($m['author_name']) ?></strong>
                            <?php if ($m['is_internal_note']): ?>
                                <span class="badge bg-warning text-dark">Internal Note</span>
                            <?php elseif ((int) $m['user_id'] !== (int) $ticket['raised_by']): ?>
                                <span class="badge bg-info text-dark">Support Agent</span>
                            <?php endif; ?>
                            <span class="text-muted small"><?= esc(date('d/m/Y, g:i A', strtotime($m['created_at']))) ?></span>
                        </div>
                        <div class="rounded p-3 mt-1 <?= $m['is_internal_note'] ? 'bg-warning-subtle' : 'bg-light' ?>">
                            <?= nl2br(esc($m['message'])) ?>
                        </div>
                        <?php foreach ($attachmentsByMessage[$m['id']] ?? [] as $att): ?>
                        <a href="<?= site_url('files/download?path=' . urlencode($att['file_path'])) ?>" class="d-inline-flex align-items-center gap-1 small text-decoration-none mt-2 border rounded px-2 py-1">
                            <i class="fas fa-paperclip text-muted"></i> <?= esc($att['original_name']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($ticket['status'] !== 'closed'): ?>
                <hr>
                <h6 class="mb-2">Reply</h6>
                <form action="<?= site_url('help/' . $ticket['id'] . '/reply') ?>" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <textarea name="message" class="form-control mb-2" rows="3" placeholder="Type your reply..." required></textarea>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <label class="btn btn-outline-secondary btn-sm mb-0">
                                <i class="fas fa-paperclip me-1"></i>Attach
                                <input type="file" name="attachment" class="d-none">
                            </label>
                            <?php if ($isResolver): ?>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="is_internal_note" value="1" id="internalNote">
                                <label class="form-check-label small" for="internalNote">Internal note</label>
                            </div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-dark"><i class="fas fa-paper-plane me-1"></i>Send Reply</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
