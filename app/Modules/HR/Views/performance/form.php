<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-7">
    <div class="card-body">
        <form action="<?= $review ? site_url('hr/performance/' . $review['id']) : site_url('hr/performance') ?>" method="post">
            <?= csrf_field() ?>
            <?php if (! $review): ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Employee</label>
                    <select name="user_id" class="form-select" required>
                        <option value="">Select employee</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= old('user_id', $presetUserId ?? null) == $u['id'] ? 'selected' : '' ?>><?= esc($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Review Cycle</label>
                    <select name="cycle_id" class="form-select" required>
                        <option value="">Select cycle</option>
                        <?php foreach ($cycles as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= old('cycle_id') == $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php else: ?>
                <p class="text-muted small">Reviewing <strong><?= esc($review['user_name']) ?></strong> for <strong><?= esc($review['cycle_name']) ?></strong></p>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Rating (1–5)</label>
                <select name="rating" class="form-select">
                    <option value="">Not rated</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>" <?= old('rating', $review['rating'] ?? null) == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Strengths</label>
                <textarea name="strengths" class="form-control" rows="3"><?= esc(old('strengths', $review['strengths'] ?? '')) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Areas for Improvement</label>
                <textarea name="improvements" class="form-control" rows="3"><?= esc(old('improvements', $review['improvements'] ?? '')) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Goals for Next Cycle</label>
                <textarea name="goals_next" class="form-control" rows="3"><?= esc(old('goals_next', $review['goals_next'] ?? '')) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><?= $review ? 'Save Draft' : 'Create Review' ?></button>
            <a href="<?= site_url('hr/performance') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
