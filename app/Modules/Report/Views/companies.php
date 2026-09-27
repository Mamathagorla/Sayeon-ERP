<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('reports/companies/export/pdf') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-pdf me-1"></i>PDF</a>
<a href="<?= site_url('reports/companies/export/csv') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-csv me-1"></i>CSV</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <table class="table table-striped table-sm align-middle">
            <thead>
                <tr>
                    <th>Company</th>
                    <th class="text-end">Active Tasks</th>
                    <th class="text-end">Overdue Tasks</th>
                    <th class="text-end">Completed Tasks</th>
                    <th class="text-end">Projects</th>
                    <th class="text-end">Compliance Pending</th>
                    <th class="text-end">Compliance Overdue</th>
                    <th class="text-end">Upcoming Meetings</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($r['company']) ?></td>
                    <td class="text-end"><?= $r['tasks_active'] ?></td>
                    <td class="text-end <?= $r['tasks_overdue'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= $r['tasks_overdue'] ?></td>
                    <td class="text-end"><?= $r['tasks_completed'] ?></td>
                    <td class="text-end"><?= $r['projects'] ?></td>
                    <td class="text-end"><?= $r['compliance_pending'] ?></td>
                    <td class="text-end <?= $r['compliance_overdue'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= $r['compliance_overdue'] ?></td>
                    <td class="text-end"><?= $r['meetings_upcoming'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
