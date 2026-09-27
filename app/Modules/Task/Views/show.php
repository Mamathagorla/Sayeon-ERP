<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('tasks/' . $task['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<a href="<?= site_url('tasks') ?>" class="btn btn-light btn-sm">Back to list</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-3">
    <div class="col-md-3"><strong>Company:</strong> <?= esc($task['company_name']) ?></div>
    <div class="col-md-3">
        <strong>Project:</strong>
        <?php if ($task['project_id']): ?>
            <a href="<?= site_url('projects/' . $task['project_id']) ?>"><?= esc($task['project_name']) ?></a>
        <?php else: ?>
            —
        <?php endif; ?>
    </div>
    <div class="col-md-3"><strong>Department:</strong> <?= esc($task['department_name']) ?></div>
    <div class="col-md-3"><strong>Assigned To:</strong> <?= esc($task['assignee_name'] ?? '—') ?></div>
</div>
<div class="row mb-3">
    <div class="col-md-3"><strong>Due:</strong> <?= $task['due_date'] ? esc(date('d/m/Y', strtotime($task['due_date']))) : '—' ?></div>
</div>

<div class="card mb-3">
    <div class="card-body d-flex align-items-center gap-3">
        <strong>Status:</strong>
        <form action="<?= site_url('tasks/' . $task['id'] . '/status') ?>" method="post" class="d-flex gap-2">
            <?= csrf_field() ?>
            <select name="status" class="form-select form-select-sm">
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $task['status'] === $s ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Update</button>
        </form>
        <span class="ms-auto small text-muted">Checklist: <?= $progress['done'] ?>/<?= $progress['total'] ?> (<?= $progress['percent'] ?>%)</span>
    </div>
</div>

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button">Overview</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-checklist" type="button">Checklist</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-comments" type="button">Comments (<?= count($comments) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-attachments" type="button">Attachments (<?= count($attachments) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-history" type="button">History</button></li>
</ul>

<div class="tab-content border border-top-0 p-3">
    <div class="tab-pane fade show active" id="tab-overview">
        <p><?= nl2br(esc($task['description'] ?: 'No description provided.')) ?></p>
    </div>

    <div class="tab-pane fade" id="tab-checklist">
        <ul class="list-group mb-3">
            <?php foreach ($checklist as $item): ?>
                <li class="list-group-item d-flex align-items-center">
                    <form action="<?= site_url('tasks/' . $task['id'] . '/checklist/' . $item['id'] . '/toggle') ?>" method="post" class="me-2">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm <?= $item['is_done'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                            <i class="fas fa-check"></i>
                        </button>
                    </form>
                    <span class="<?= $item['is_done'] ? 'text-decoration-line-through text-muted' : '' ?>"><?= esc($item['title']) ?></span>
                    <form action="<?= site_url('tasks/' . $task['id'] . '/checklist/' . $item['id'] . '/delete') ?>" method="post" class="ms-auto">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form action="<?= site_url('tasks/' . $task['id'] . '/checklist') ?>" method="post" class="d-flex gap-2">
            <?= csrf_field() ?>
            <input type="text" name="title" class="form-control form-control-sm" placeholder="New checklist item" required>
            <button type="submit" class="btn btn-sm btn-primary">Add</button>
        </form>
    </div>

    <div class="tab-pane fade" id="tab-comments">
        <?php foreach ($comments as $c): ?>
            <div class="mb-2 border-bottom pb-2">
                <strong><?= esc($c['user_name']) ?></strong>
                <span class="text-muted small ms-2"><?= esc(date('d/m/Y, g:i A', strtotime($c['created_at']))) ?></span>
                <p class="mb-0"><?= nl2br(esc($c['comment'])) ?></p>
            </div>
        <?php endforeach; ?>
        <form action="<?= site_url('tasks/' . $task['id'] . '/comments') ?>" method="post" class="mt-3">
            <?= csrf_field() ?>
            <textarea name="comment" class="form-control mb-2" rows="2" placeholder="Add a comment..." required></textarea>
            <button type="submit" class="btn btn-sm btn-primary">Post Comment</button>
        </form>
    </div>

    <div class="tab-pane fade" id="tab-attachments">
        <ul class="list-group mb-3">
            <?php foreach ($attachments as $a): ?>
                <li class="list-group-item d-flex align-items-center">
                    <a href="<?= site_url('files/download?path=' . urlencode($a['file_path'])) ?>" target="_blank">
                        <i class="fas fa-paperclip me-1"></i><?= esc($a['original_name']) ?>
                    </a>
                    <span class="text-muted small ms-2">by <?= esc($a['uploaded_by_name']) ?> · <?= esc(date('d/m/Y, g:i A', strtotime($a['created_at']))) ?></span>
                    <form action="<?= site_url('tasks/' . $task['id'] . '/attachments/' . $a['id'] . '/delete') ?>" method="post" class="ms-auto">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form action="<?= site_url('tasks/' . $task['id'] . '/attachments') ?>" method="post" enctype="multipart/form-data" class="d-flex gap-2">
            <?= csrf_field() ?>
            <input type="file" name="attachment" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary">Upload</button>
        </form>
    </div>

    <div class="tab-pane fade" id="tab-history">
        <ul class="list-group">
            <?php foreach ($history as $h): ?>
                <li class="list-group-item small">
                    <?= esc($h['from_status'] ?? '—') ?> → <strong><?= esc($h['to_status']) ?></strong>
                    by <?= esc($h['changed_by_name']) ?> on <?= esc(date('d/m/Y, g:i A', strtotime($h['changed_at']))) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?= $this->endSection() ?>
