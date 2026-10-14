<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-7">
    <div class="card-body">
        <div class="alert alert-info small mb-3"><i class="fas fa-circle-info me-1"></i>No meeting link (Google Meet or Zoom) has been set for this meeting yet — here are the details.</div>
        <h4><?= esc($meeting['title']) ?></h4>
        <div class="row my-3">
            <?php $when = erp_meeting_when($meeting['meeting_date'], $meeting['start_time'], $meeting['end_time'], $meeting['end_date'] ?? null, $meeting['timezone'] ?? null); ?>
            <div class="col-md-4"><strong>Date:</strong> <?= esc($when['date']) ?></div>
            <div class="col-md-4"><strong>Time:</strong> <?= esc($when['start']) ?><?= $when['end'] !== null ? ' - ' . esc($when['end']) : '' ?><?= $when['nextDay'] ? ' (next day)' : '' ?> <small class="text-muted">(<?= esc($when['zone']) ?>)</small></div>
            <div class="col-md-4"><strong>Location:</strong> <?= esc($meeting['location'] ?? '—') ?></div>
        </div>
        <?php if (! empty($meeting['agenda'])): ?>
            <h6>Agenda</h6>
            <p><?= nl2br(esc($meeting['agenda'])) ?></p>
        <?php endif; ?>
        <a href="<?= site_url('meetings/' . $meeting['id']) ?>" class="btn btn-primary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Meeting Details</a>
    </div>
</div>
<?= $this->endSection() ?>
