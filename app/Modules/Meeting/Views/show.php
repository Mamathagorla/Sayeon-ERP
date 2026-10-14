<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    .sy-mt-back { font-size: .85rem; font-weight: 600; color: var(--sy-accent-ink); text-decoration: none; }
    .sy-mt-back:hover { text-decoration: underline; }
    .sy-mt-title { font-size: 1.6rem; font-weight: 700; color: var(--sy-ink); margin: 0; letter-spacing: -.01em; }
    .sy-mt-status { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: var(--sy-radius-pill); font-size: .76rem; font-weight: 600; background: var(--sy-info-soft); color: var(--sy-info); }
    .sy-mt-status.is-past { background: var(--sy-secondary-soft); color: var(--sy-secondary); }
    .sy-mt-fact { display: flex; gap: 12px; align-items: flex-start; }
    .sy-mt-fact .ico { width: 34px; height: 34px; border-radius: var(--sy-radius-sm); flex: none; display: flex; align-items: center; justify-content: center; background: var(--sy-accent-soft); color: var(--sy-accent-ink); }
    .sy-mt-fact .k { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--sy-muted); }
    .sy-mt-fact .v { font-size: .92rem; font-weight: 600; color: var(--sy-ink); word-break: break-word; }
    .sy-mt-fact .sub { font-size: .76rem; color: var(--sy-muted); }
    .sy-mt-section-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--sy-muted); margin-bottom: 10px; }
    .sy-mt-tabs-body { padding: 18px; }

    /* Two-column layout: main content (about 75%) and a right action panel (about 25%).
       On narrow screens the panel drops below the information card. */
    .sy-mt-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px 16px; align-items: start; grid-template-areas: "info" "side" "tabs"; }
    .sy-mt-grid > .sy-card { height: auto; }
    .sy-mt-area-info { grid-area: info; }
    .sy-mt-area-side { grid-area: side; display: flex; flex-direction: column; gap: 12px; }
    .sy-mt-area-tabs { grid-area: tabs; }
    @media (min-width: 992px) {
        .sy-mt-grid { grid-template-columns: minmax(0, 3fr) minmax(0, 1fr); grid-template-areas: "info side" "tabs side"; }
    }

    .sy-mt-join { width: 100%; font-weight: 700; padding: 10px 14px; }
    .sy-mt-ext { display: flex; align-items: center; gap: 8px; min-width: 0; padding: 10px 12px; border: 1px solid var(--sy-border-soft); border-radius: var(--sy-radius-sm); color: var(--sy-accent-ink); font-size: .84rem; font-weight: 600; text-decoration: none; }
    .sy-mt-ext:hover { background: var(--sy-accent-soft); color: var(--sy-accent-ink); }
    .sy-mt-ext .txt { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sy-mt-ext .arrow { flex: none; font-size: .8rem; }
    .sy-mt-actions .btn { width: 100%; text-align: left; }
    .sy-mt-side-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--sy-muted); margin-bottom: 8px; }
    .sy-mt-side-card { padding: 16px; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $when = erp_meeting_when($meeting['meeting_date'], $meeting['start_time'], $meeting['end_time'], $meeting['end_date'] ?? null, $meeting['timezone'] ?? null);
    $providerNames = ['google_meet' => 'Google Meet', 'zoom' => 'Zoom', 'none' => 'No online meeting'];
    $syncBadge = ['synced' => ['success', 'Synced to calendar'], 'sync_failed' => ['danger', 'Calendar sync failed'], 'cancelled' => ['secondary', 'Cancelled'], 'not_synced' => ['secondary', 'Not linked to a calendar']];
    $sync = $syncBadge[$meeting['sync_status'] ?? 'not_synced'] ?? $syncBadge['not_synced'];
    $isPast = $meeting['meeting_date'] < date('Y-m-d');
    // Only the start of a long external link is shown; the full address stays in the tooltip and the href.
    $externalFull  = (string) ($meeting['meeting_link'] ?? '');
    $externalShort = mb_strlen($externalFull) > 30 ? mb_substr($externalFull, 0, 30) . '…' : $externalFull;
?>

<?php /* The page header (layout) already shows the meeting title, so it is not repeated here. */ ?>
<div class="mb-3">
    <span class="sy-mt-status<?= $isPast ? ' is-past' : '' ?>"><i class="far fa-calendar-check"></i><?= $isPast ? 'Past meeting' : 'Upcoming' ?></span>
</div>

<div class="sy-mt-grid">

    <div class="sy-mt-area-info">
        <div class="sy-card">
            <div class="sy-card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-xl-4">
                        <div class="sy-mt-fact">
                            <div class="ico"><i class="far fa-calendar"></i></div>
                            <div><div class="k">Date</div><div class="v"><?= esc($when['date']) ?></div></div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                        <div class="sy-mt-fact">
                            <div class="ico"><i class="far fa-clock"></i></div>
                            <div>
                                <div class="k">Time</div>
                                <div class="v"><?= esc($when['start']) ?><?= $when['end'] !== null ? ' - ' . esc($when['end']) : '' ?><?= $when['nextDay'] ? ' (next day)' : '' ?></div>
                                <div class="sub"><?= esc($when['zone']) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                        <div class="sy-mt-fact">
                            <div class="ico"><i class="fas fa-location-dot"></i></div>
                            <div>
                                <div class="k">Location</div>
                                <div class="v">
                                    <?php if ($meeting['location'] && filter_var($meeting['location'], FILTER_VALIDATE_URL)): ?>
                                        <a href="<?= esc($meeting['location'], 'attr') ?>" target="_blank" rel="noopener"><?= esc($meeting['location']) ?></a>
                                    <?php else: ?>
                                        <?= esc($meeting['location'] ?? '—') ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                        <div class="sy-mt-fact">
                            <div class="ico"><i class="far fa-building"></i></div>
                            <div><div class="k">Company</div><div class="v"><?= esc($meeting['company_name']) ?></div></div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                        <div class="sy-mt-fact">
                            <div class="ico"><i class="fas fa-video"></i></div>
                            <div><div class="k">Provider</div><div class="v"><?= esc($providerNames[$meeting['provider'] ?? 'none'] ?? $meeting['provider']) ?></div></div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                        <div class="sy-mt-fact">
                            <div class="ico"><i class="far fa-calendar-plus"></i></div>
                            <div>
                                <div class="k">Calendar sync</div>
                                <div class="v"><span class="badge bg-<?= $sync[0] ?>"><?= esc($sync[1]) ?></span></div>
                                <?php if (! empty($meeting['sync_error'])): ?><div class="small text-danger"><?= esc($meeting['sync_error']) ?></div><?php endif; ?>
                                <?php if (! empty($meeting['provider_meeting_id'])): ?><div class="sub">Event ID <code><?= esc($meeting['provider_meeting_id']) ?></code></div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="sy-mt-area-side">
        <div class="sy-card sy-mt-side-card">
            <div class="sy-mt-side-title">Join</div>
            <a href="<?= esc($joinUrl, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-primary sy-mt-join"><i class="fas fa-video me-2"></i>Join Meeting</a>
        </div>

        <div class="sy-card sy-mt-side-card">
            <div class="sy-mt-side-title">External call</div>
            <?php if ($externalFull !== ''): ?>
            <div class="d-flex align-items-center gap-2">
                <a class="sy-mt-ext flex-grow-1" href="<?= esc($externalFull, 'attr') ?>" target="_blank" rel="noopener" title="<?= esc($externalFull, 'attr') ?>">
                    <span class="txt"><?= esc($externalShort) ?></span>
                    <i class="fas fa-arrow-up-right-from-square arrow"></i>
                </a>
                <button type="button" class="btn btn-light btn-sm" id="mtCopyLink" title="Copy join link"><i class="fas fa-copy"></i></button>
            </div>
            <?php else: ?>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted flex-grow-1">No external link</span>
                <button type="button" class="btn btn-light btn-sm" id="mtCopyLink" title="Copy join link"><i class="fas fa-copy"></i></button>
            </div>
            <?php endif; ?>
        </div>

        <div class="sy-card sy-mt-side-card">
            <div class="sy-mt-side-title">Quick Actions</div>
            <div class="sy-mt-actions d-flex flex-column gap-2">
                <a href="<?= site_url('meetings/' . $meeting['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-2"></i>Edit Meeting</a>
                <?php if (can('meeting.delete')): ?>
                <form action="<?= site_url('meetings/' . $meeting['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Delete this meeting?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-2"></i>Delete Meeting</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="sy-mt-area-tabs">
        <div class="sy-card">
            <div class="sy-card-header p-0 border-0">
                <ul class="nav nav-tabs px-3 pt-2 w-100" role="tablist" style="border-bottom:0">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-agenda" type="button"><i class="far fa-calendar me-1"></i>Agenda</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-participants" type="button"><i class="fas fa-users me-1"></i>Participants (<?= count($participants) ?>)</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-mom" type="button"><i class="far fa-file-lines me-1"></i>Minutes of Meeting</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-actions" type="button"><i class="far fa-square-check me-1"></i>Action Items (<?= count($actionItems) ?>)</button></li>
                </ul>
            </div>

            <div class="tab-content sy-mt-tabs-body">
                <div class="tab-pane fade show active" id="tab-agenda">
                    <div class="sy-mt-section-title">Agenda</div>
                    <p class="mb-0"><?= nl2br(esc($meeting['agenda'] ?: 'No agenda recorded.')) ?></p>
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
                    <div class="table-responsive">
                    <table class="table table-sm mb-3">
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
                                            <select name="project_id" class="form-select form-select-sm d-inline w-auto" required>
                                                <option value="">Project…</option>
                                                <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option><?php endforeach; ?>
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
                    </div>

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
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('mtCopyLink');
    if (! btn) return;
    btn.addEventListener('click', function () {
        navigator.clipboard.writeText(<?= json_encode($joinUrl) ?>).then(function () {
            var icon = btn.querySelector('i');
            icon.className = 'fas fa-check text-success';
            setTimeout(function () { icon.className = 'fas fa-copy'; }, 1500);
        });
    });
});
</script>
<?= $this->endSection() ?>
