<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-8">
    <div class="card-body">
        <form action="<?= $meeting ? site_url('meetings/' . $meeting['id']) : site_url('meetings') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Meeting Title</label>
                    <input type="text" name="title" class="form-control" value="<?= esc($meeting['title'] ?? old('title')) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($meeting['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Agenda</label>
                    <textarea name="agenda" class="form-control" rows="3"><?= esc($meeting['agenda'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="meeting_date" class="form-control" value="<?= esc($meeting['meeting_date'] ?? '') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Start Time</label>
                    <input type="time" name="start_time" class="form-control" value="<?= esc($meeting['start_time'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">End Time</label>
                    <input type="time" name="end_time" class="form-control" value="<?= esc($meeting['end_time'] ?? '') ?>">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Location / Meeting Link</label>
                    <input type="text" name="location" class="form-control" value="<?= esc($meeting['location'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $meeting ? 'Update Meeting' : 'Schedule Meeting' ?></button>
            <a href="<?= site_url('meetings') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
