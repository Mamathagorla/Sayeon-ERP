<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-md-7">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Review Cycles</h6>
                <?php if (empty($cycles)): ?>
                    <p class="text-muted mb-0">No review cycles yet.</p>
                <?php else: ?>
                <table class="table table-sm table-striped">
                    <thead><tr><th>Name</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($cycles as $c): ?>
                        <tr>
                            <td><?= esc($c['name']) ?></td>
                            <td><?= esc(date('d/m/Y', strtotime($c['start_date']))) ?></td>
                            <td><?= esc(date('d/m/Y', strtotime($c['end_date']))) ?></td>
                            <td><span class="badge bg-<?= $c['status'] === 'open' ? 'success' : 'secondary' ?>"><?= esc(ucfirst($c['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">New Cycle</h6>
                <form action="<?= site_url('hr/performance/cycles') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small">Name</label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. H1 2026" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Start Date</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">End Date</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Create</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
