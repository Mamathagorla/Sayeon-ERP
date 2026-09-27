<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('meetings/' . $meeting['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<a href="<?= site_url('meetings') ?>" class="btn btn-light btn-sm">Back to list</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-3">
    <div class="col-md-3"><strong>Company:</strong> <?= esc($meeting['company_name']) ?></div>
    <div class="col-md-3"><strong>Date:</strong> <?= esc(date('d/m/Y', strtotime($meeting['meeting_date']))) ?></div>
    <div class="col-md-3"><strong>Time:</strong> <?= esc($meeting['start_time'] ? substr($meeting['start_time'], 0, 5) : '—') ?><?= $meeting['end_time'] ? ' - ' . substr($meeting['end_time'], 0, 5) : '' ?></div>
    <div class="col-md-3"><strong>Location:</strong> <?php if ($meeting['location'] && filter_var($meeting['location'], FILTER_VALIDATE_URL)): ?><a href="<?= esc($meeting['location'], 'attr') ?>" target="_blank" rel="noopener"><?= esc($meeting['location']) ?></a><?php else: ?><?= esc($meeting['location'] ?? '—') ?><?php endif; ?></div>
</div>

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-agenda" type="button">Agenda</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-participants" type="button">Participants (<?= count($participants) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-mom" type="button">Minutes of Meeting</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-actions" type="button">Action Items (<?= count($actionItems) ?>)</button></li>
</ul>

<div class="tab-content border border-top-0 p-3">
    <div class="tab-pane fade show active" id="tab-agenda">
        <p><?= nl2br(esc($meeting['agenda'] ?: 'No agenda recorded.')) ?></p>
    </div>

    <div class="tab-pane fade" id="tab-participants">
        <ul class="list-group mb-3">
            <?php foreach ($participants as $p): ?>
                <li class="list-group-item d-flex align-items-center">
                    <?= esc($participantModel->displayName($p)) ?>
                    <?php if (empty($p['user_id'])): ?><span class="badge bg-secondary ms-2">External</span><?php endif; ?>
                    <form action="<?= site_url('meetings/' . $meeting['id'] . '/participants/' . $p['id'] . '/delete') ?>" method="post" class="ms-auto">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form action="<?= site_url('meetings/' . $meeting['id'] . '/participants') ?>" method="post" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">— External participant instead —</option>
                    <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= esc($u['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><input type="text" name="external_name" class="form-control form-control-sm" placeholder="External name" maxlength="150" pattern="[A-Za-z\s.'\-]+" title="Letters, spaces, apostrophes, hyphens and periods only"></div>
            <div class="col-md-3"><input type="email" name="external_email" class="form-control form-control-sm" placeholder="External email"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100">Add</button></div>
        </form>
    </div>

    <div class="tab-pane fade" id="tab-mom">
        <form action="<?= site_url('meetings/' . $meeting['id'] . '/mom') ?>" method="post">
            <?= csrf_field() ?>
            <textarea name="mom" class="form-control mb-2" rows="8" placeholder="Record the minutes of this meeting..."><?= esc($meeting['mom'] ?? '') ?></textarea>
            <button type="submit" class="btn btn-sm btn-primary">Save Minutes</button>
        </form>
    </div>

    <div class="tab-pane fade" id="tab-actions">
        <table class="table table-sm table-striped mb-3">
            <thead><tr><th>Description</th><th>Assigned To</th><th>Due</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($actionItems as $a): ?>
                <tr>
                    <td><?= esc($a['description']) ?></td>
                    <td><?= esc($a['assignee_name'] ?? '—') ?></td>
                    <td><?= esc($a['due_date'] ?? '—') ?></td>
                    <td>
                        <form action="<?= site_url('meetings/' . $meeting['id'] . '/action-items/' . $a['id'] . '/status') ?>" method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <select name="status" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                                <option value="pending" <?= $a['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="in_progress" <?= $a['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="completed" <?= $a['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </form>
                    </td>
                    <td class="text-end">
                        <?php if ($a['linked_task_id']): ?>
                            <a href="<?= site_url('tasks/' . $a['linked_task_id']) ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-link"></i> View Task</a>
                        <?php else: ?>
                            <form action="<?= site_url('meetings/' . $meeting['id'] . '/action-items/' . $a['id'] . '/convert') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <select name="department_id" class="form-select form-select-sm d-inline w-auto" required>
                                    <option value="">Dept…</option>
                                    <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= esc($d['name']) ?></option><?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Convert to follow-up task"><i class="fas fa-arrow-right"></i></button>
                            </form>
                        <?php endif; ?>
                        <form action="<?= site_url('meetings/' . $meeting['id'] . '/action-items/' . $a['id'] . '/delete') ?>" method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h6>Add Action Item</h6>
        <form action="<?= site_url('meetings/' . $meeting['id'] . '/action-items') ?>" method="post" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-md-5"><input type="text" name="description" class="form-control form-control-sm" placeholder="What needs to happen?" required></div>
            <div class="col-md-3">
                <select name="assigned_to" class="form-select form-select-sm">
                    <option value="">Unassigned</option>
                    <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= esc($u['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="due_date" class="form-control form-control-sm"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100">Add</button></div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
