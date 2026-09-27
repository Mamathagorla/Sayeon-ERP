<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('compliance/' . $item['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<a href="<?= site_url('compliance') ?>" class="btn btn-light btn-sm">Back to list</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-3">
    <div class="col-md-3"><strong>Company:</strong> <?= esc($item['company_name']) ?></div>
    <div class="col-md-3"><strong>Type:</strong> <?= esc($item['type_name']) ?></div>
    <div class="col-md-3"><strong>Due Date:</strong> <?= esc(date('d/m/Y', strtotime($item['due_date']))) ?></div>
    <div class="col-md-3"><strong>Recurrence:</strong> <?= esc(ucwords(str_replace('_', ' ', $item['recurrence']))) ?></div>
    <?php if (! empty($item['regulator'])): ?>
    <div class="col-md-3 mt-2"><strong>Regulator:</strong> <?= esc($item['regulator']) ?></div>
    <?php endif; ?>
    <?php if (! empty($item['period'])): ?>
    <div class="col-md-3 mt-2"><strong>Period:</strong> <?= esc($item['period']) ?></div>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <div class="card-body d-flex align-items-center gap-3">
        <strong>Status:</strong>
        <span class="badge bg-<?= ['pending' => 'secondary', 'in_progress' => 'info', 'filed' => 'success', 'overdue' => 'danger'][$item['status']] ?> fs-6">
            <?= esc(ucwords(str_replace('_', ' ', $item['status']))) ?>
        </span>
        <span class="small text-muted">Responsible: <?= esc($item['responsible_name'] ?? '—') ?></span>
        <span class="small text-muted">Reminder: <?= (int) $item['reminder_days_before'] ?> day(s) before due</span>

        <?php if ($item['status'] !== 'filed'): ?>
            <form action="<?= site_url('compliance/' . $item['id'] . '/mark-filed') ?>" method="post" class="ms-auto" onsubmit="return confirm('Mark this as filed? <?= $item['recurrence'] !== 'none' ? 'The next cycle will be scheduled automatically.' : '' ?>');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Mark as Filed</button>
            </form>
        <?php else: ?>
            <span class="ms-auto small text-success"><i class="fas fa-check-circle me-1"></i>Filed on <?= esc(date('d/m/Y, g:i A', strtotime($item['filed_at']))) ?></span>
        <?php endif; ?>
    </div>
</div>

<?php if ($item['notes']): ?>
    <div class="card mb-3"><div class="card-body"><strong>Notes:</strong> <?= nl2br(esc($item['notes'])) ?></div></div>
<?php endif; ?>

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-attachments" type="button">Attachments (<?= count($attachments) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-history" type="button">Filing History</button></li>
</ul>

<div class="tab-content border border-top-0 p-3">
    <div class="tab-pane fade show active" id="tab-attachments">
        <ul class="list-group mb-3">
            <?php foreach ($attachments as $a): ?>
                <li class="list-group-item d-flex align-items-center">
                    <a href="<?= site_url('files/download?path=' . urlencode($a['file_path'])) ?>" target="_blank">
                        <i class="fas fa-paperclip me-1"></i><?= esc($a['original_name']) ?>
                    </a>
                    <span class="text-muted small ms-2">by <?= esc($a['uploaded_by_name']) ?> · <?= esc(date('d/m/Y, g:i A', strtotime($a['created_at']))) ?></span>
                    <form action="<?= site_url('compliance/' . $item['id'] . '/attachments/' . $a['id'] . '/delete') ?>" method="post" class="ms-auto">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form action="<?= site_url('compliance/' . $item['id'] . '/attachments') ?>" method="post" enctype="multipart/form-data" class="d-flex gap-2">
            <?= csrf_field() ?>
            <input type="file" name="attachment" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary">Upload</button>
        </form>
    </div>

    <div class="tab-pane fade" id="tab-history">
        <?php if (empty($history)): ?>
            <p class="text-muted small mb-0">This is the first recorded cycle for this compliance item.</p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($history as $h): ?>
                    <li class="list-group-item small d-flex justify-content-between">
                        <span>Due <?= esc(date('d/m/Y', strtotime($h['due_date']))) ?> — <span class="badge bg-<?= ['pending' => 'secondary', 'in_progress' => 'info', 'filed' => 'success', 'overdue' => 'danger'][$h['status']] ?>"><?= esc(ucwords(str_replace('_', ' ', $h['status']))) ?></span></span>
                        <a href="<?= site_url('compliance/' . $h['id']) ?>">View</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
