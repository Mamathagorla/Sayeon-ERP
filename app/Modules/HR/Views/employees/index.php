<?= $this->extend('layouts/main') ?>

<?php
$statusColor = ['active' => 'success', 'on_leave' => 'warning', 'resigned' => 'secondary', 'terminated' => 'danger'];
?>

<?= $this->section('pageActions') ?>
<div class="btn-group me-1" role="group" aria-label="View">
    <button type="button" class="btn btn-light btn-sm" id="empViewList" title="List view"><i class="fas fa-list"></i></button>
    <button type="button" class="btn btn-light btn-sm" id="empViewGrid" title="Grid view"><i class="fas fa-table-cells-large"></i></button>
</div>
<?php if (can('employee.create')): ?>
<a href="<?= site_url('hr/employees/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Employee</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
    .sy-emp-card { background: var(--sy-surface); border: 1px solid var(--sy-border-soft); border-radius: var(--sy-radius); padding: 24px 20px 18px; text-align: center; height: 100%; transition: box-shadow .15s ease, transform .15s ease; }
    .sy-emp-card:hover { box-shadow: var(--sy-shadow); transform: translateY(-2px); }
    .sy-emp-photo { width: 84px; height: 84px; border-radius: 50%; object-fit: cover; margin: 0 auto 14px; display: block; }
    .sy-emp-photo.initial { display: flex; align-items: center; justify-content: center; font-size: 1.9rem; }
    .sy-emp-name { font-size: 1.02rem; font-weight: 700; color: var(--sy-ink) !important; text-decoration: none; }
    .sy-emp-role { font-size: .88rem; color: var(--sy-muted); margin: 2px 0 10px; }
    .sy-emp-dept { display: inline-block; padding: 3px 12px; border-radius: 6px; font-size: .78rem; font-weight: 600; background: var(--sy-accent-warm-soft); color: var(--sy-accent-warm-ink); }
    .sy-emp-contact { border-top: 1px solid var(--sy-border-soft); margin-top: 16px; padding-top: 14px; font-size: .82rem; color: var(--sy-muted); }
    .sy-emp-contact div { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
    .sy-emp-contact i { margin-right: 6px; }
</style>

<form method="get" class="sy-card mb-3 filter-form" style="height:auto">
    <div class="sy-card-body d-flex gap-2 flex-wrap align-items-end py-2">
        <div class="flex-grow-1" style="min-width:200px;max-width:320px">
            <label class="form-label small mb-1">Search</label>
            <input type="text" id="empSearch" class="form-control form-control-sm" placeholder="Name, role, email…">
        </div>
        <div>
            <label class="form-label small mb-1">Company</label>
            <select name="company_id" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (($filters['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Department</label>
            <select name="department_id" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (($filters['department_id'] ?? null) == $d['id']) ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= (($filters['status'] ?? null) === $s) ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <a href="<?= site_url('hr/employees') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<?php if (empty($employees)): ?>
    <div class="sy-card" style="height:auto"><div class="sy-card-body"><p class="text-muted mb-0">No employee profiles yet.</p></div></div>
<?php else: ?>

<!-- Grid view -->
<div id="empGrid" class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3">
    <?php foreach ($employees as $e): ?>
    <div class="col emp-item" data-search="<?= esc(mb_strtolower($e['user_name'] . ' ' . ($e['designation'] ?? '') . ' ' . ($e['department_name'] ?? '') . ' ' . ($e['user_email'] ?? '') . ' ' . $e['employee_code'])) ?>">
        <div class="sy-emp-card">
            <?php if (! empty($e['user_avatar'])): ?>
                <img class="sy-emp-photo" src="<?= base_url($e['user_avatar']) ?>" alt="">
            <?php else: ?>
                <span class="sy-avatar sy-emp-photo initial"><?= esc(mb_strtoupper(mb_substr($e['user_name'], 0, 1))) ?></span>
            <?php endif; ?>
            <a href="<?= site_url('hr/employees/' . $e['id']) ?>" class="sy-emp-name d-block"><?= esc($e['user_name']) ?></a>
            <div class="sy-emp-role"><?= esc($e['designation'] ?? '—') ?></div>
            <?php if (! empty($e['department_name'])): ?><span class="sy-emp-dept"><?= esc($e['department_name']) ?></span><?php endif; ?>
            <?php if ($e['status'] !== 'active'): ?>
                <div class="mt-2"><span class="badge bg-<?= $statusColor[$e['status']] ?>"><?= esc(ucwords(str_replace('_', ' ', $e['status']))) ?></span></div>
            <?php endif; ?>
            <div class="sy-emp-contact">
                <div><i class="far fa-envelope"></i><?= esc($e['user_email'] ?? '—') ?></div>
                <div><i class="fas fa-phone"></i><?= esc($e['user_phone'] ?: '—') ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- List view (the original table) -->
<div id="empList" class="sy-card d-none" style="height:auto">
    <div class="sy-card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>Company</th><th>Department</th><th>Designation</th><th>Manager</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($employees as $e): ?>
                <tr class="emp-item" data-search="<?= esc(mb_strtolower($e['user_name'] . ' ' . ($e['designation'] ?? '') . ' ' . ($e['department_name'] ?? '') . ' ' . ($e['user_email'] ?? '') . ' ' . $e['employee_code'])) ?>">
                    <td><?= esc($e['employee_code']) ?></td>
                    <td><a href="<?= site_url('hr/employees/' . $e['id']) ?>"><?= esc($e['user_name']) ?></a></td>
                    <td><?= esc($e['company_name']) ?></td>
                    <td><?= esc($e['department_name'] ?? '—') ?></td>
                    <td><?= esc($e['designation'] ?? '—') ?></td>
                    <td><?= esc($e['manager_name'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= $statusColor[$e['status']] ?>"><?= esc(ucwords(str_replace('_', ' ', $e['status']))) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<p class="text-muted small mt-3 d-none" id="empNoMatches">No employees match your search.</p>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var grid = document.getElementById('empGrid');
    var list = document.getElementById('empList');
    if (! grid) { return; }

    var btnGrid = document.getElementById('empViewGrid');
    var btnList = document.getElementById('empViewList');
    function setView(v) {
        grid.classList.toggle('d-none', v !== 'grid');
        list.classList.toggle('d-none', v !== 'list');
        btnGrid.classList.toggle('active', v === 'grid');
        btnList.classList.toggle('active', v === 'list');
        try { localStorage.setItem('sy-emp-view', v); } catch (e) { }
    }
    var saved = 'grid';
    try { saved = localStorage.getItem('sy-emp-view') === 'list' ? 'list' : 'grid'; } catch (e) { }
    setView(saved);
    btnGrid.addEventListener('click', function () { setView('grid'); });
    btnList.addEventListener('click', function () { setView('list'); });

    // Live search across name / role / department / email / code.
    var search = document.getElementById('empSearch');
    var none = document.getElementById('empNoMatches');
    search.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        var visible = 0;
        document.querySelectorAll('.emp-item').forEach(function (el) {
            var match = el.dataset.search.indexOf(q) !== -1;
            el.classList.toggle('d-none', ! match);
            if (match && el.closest('#empGrid')) { visible++; }
        });
        none.classList.toggle('d-none', visible !== 0);
    });
})();
</script>
<?= $this->endSection() ?>
