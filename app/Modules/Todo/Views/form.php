<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card col-lg-7">
    <div class="card-body">
        <form action="<?= $todo ? site_url('todos/' . $todo['id']) : site_url('todos') ?>" method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" value="<?= esc(old('title', $todo['title'] ?? '')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Description <span class="text-muted small">(optional)</span></label>
                <textarea name="description" class="form-control" rows="4"><?= esc(old('description', $todo['description'] ?? '')) ?></textarea>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Due Date <span class="text-muted small">(optional)</span></label>
                    <input type="date" name="due_date" class="form-control" value="<?= esc(old('due_date', $todo['due_date'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <?php foreach ($priorities as $p): ?>
                            <option value="<?= $p ?>" <?= old('priority', $todo['priority'] ?? 'normal') === $p ? 'selected' : '' ?>><?= esc(ucfirst($p)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-check mt-3 mb-3">
                <input class="form-check-input" type="checkbox" name="is_starred" value="1" id="isStarred" <?= old('is_starred', $todo['is_starred'] ?? false) ? 'checked' : '' ?>>
                <label class="form-check-label" for="isStarred"><i class="fas fa-star text-warning me-1"></i>Starred</label>
            </div>
            <button type="submit" class="btn btn-primary"><?= $todo ? 'Save Changes' : 'Add To-Do' ?></button>
            <a href="<?= site_url('todos') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
