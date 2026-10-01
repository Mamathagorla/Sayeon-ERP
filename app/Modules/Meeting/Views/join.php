<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-7">
    <div class="card-body">
        <div class="alert alert-info small mb-3"><i class="fas fa-circle-info me-1"></i>No external call link (Zoom/Teams/etc.) has been set for this meeting yet — here are the details.</div>
        <h4><?= esc($meeting['title']) ?></h4>
        <div class="row my-3">
            <div class="col-md-4"><strong>Date:</strong> <?= esc(date('d/m/Y', strtotime($meeting['meeting_date']))) ?></div>
            <div class="col-md-4"><strong>Time:</strong> <?= esc($meeting['start_time'] ? substr($meeting['start_time'], 0, 5) : '—') ?><?= $meeting['end_time'] ? ' - ' . substr($meeting['end_time'], 0, 5) : '' ?></div>
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
