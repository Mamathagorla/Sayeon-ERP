<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/attendance') ?>" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Attendance</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card col-lg-7">
    <div class="card-body">
        <h5 class="mb-3">Bulk Upload Attendance</h5>
        <p class="text-muted small">Upload a CSV with the columns below, one row per employee per day. Existing rows for the same employee and date are updated, not duplicated. Employees from another company are skipped.</p>
        <div class="bg-light rounded p-2 mb-3 small font-monospace">employee_code,date,status,check_in,check_out<br>EMP-0001,2026-09-29,present,09:05,18:10<br>EMP-0002,2026-09-29,absent,,</div>
        <ul class="small text-muted">
            <li><strong>employee_code</strong> — must match an existing employee's code exactly.</li>
            <li><strong>date</strong> — YYYY-MM-DD.</li>
            <li><strong>status</strong> — one of: <?= esc(implode(', ', \App\Modules\HR\Models\AttendanceModel::STATUSES)) ?>.</li>
            <li><strong>check_in</strong> / <strong>check_out</strong> — HH:MM (24-hour), optional. Left blank leaves any existing time untouched.</li>
        </ul>
        <form action="<?= site_url('hr/attendance/bulk-upload') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="mb-3">
                <input type="file" name="csv" class="form-control" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>Upload</button>
            <a href="<?= site_url('hr/attendance') ?>" class="btn btn-light">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
