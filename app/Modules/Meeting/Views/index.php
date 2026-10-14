<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (in_array('meeting.create', session('permissions') ?? [], true) || session('roleSlug') === 'super_admin'): ?>
<a href="<?= site_url('meetings/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Schedule Meeting</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php if ($isPersonalScope): ?>
<div class="alert alert-info py-2 small"><i class="fas fa-user me-1"></i>Showing only meetings you're a participant in.</div>
<?php endif; ?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 filter-form">
            <div class="col-md-3">
                <select name="company_id" class="form-select form-select-sm">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (($filters['company_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 form-check mt-2">
                <input type="checkbox" name="upcoming" value="1" class="form-check-input" id="upcomingChk" <?= ! empty($filters['upcoming']) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="upcomingChk">Upcoming only</label>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-striped table-hover" id="meetingsTable">
            <thead><tr><th>Title</th><th>Company</th><th>Date</th><th>Time</th><th>Location</th></tr></thead>
            <tbody>
            <?php foreach ($meetings as $m): ?>
                <tr>
                    <td><a href="<?= site_url('meetings/' . $m['id']) ?>"><?= esc($m['title']) ?></a></td>
                    <td><?= esc($m['company_name']) ?></td>
                    <?php $when = erp_meeting_when($m['meeting_date'], $m['start_time'], $m['end_time'], $m['end_date'] ?? null, $m['timezone'] ?? null); ?>
                    <td><?= esc($when['date']) ?></td>
                    <td><?= esc($when['start']) ?><?= $when['end'] !== null ? ' - ' . esc($when['end']) : '' ?><?= $when['nextDay'] ? ' (next day)' : '' ?></td>
                    <td><?php if ($m['location'] && filter_var($m['location'], FILTER_VALIDATE_URL)): ?><a href="<?= esc($m['location'], 'attr') ?>" target="_blank" rel="noopener"><?= esc($m['location']) ?></a><?php elseif ($m['location']): ?><?= esc($m['location']) ?><?php else: ?>—<?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>$(function () { $('#meetingsTable').DataTable({ order: [[2, 'asc']] }); });</script>
<?= $this->endSection() ?>
