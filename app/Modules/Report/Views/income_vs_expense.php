<?= $this->extend('layouts/main') ?>

<?php
$qs = http_build_query(['company_id' => $companyId, 'from' => $from, 'to' => $to]);

$ivseTrendBadge = static function (array $trend, bool $higherIsGood = true): string {
    if ($trend['state'] === 'none') {
        return '<span class="badge bg-light text-muted border">—</span>';
    }
    if ($trend['state'] === 'new') {
        return '<span class="badge bg-primary-subtle text-primary"><i class="fas fa-sparkles me-1"></i>New</span>';
    }

    $percent = $trend['percent'];
    $good    = $higherIsGood ? $percent >= 0 : $percent <= 0;
    $arrow   = $percent >= 0 ? 'up' : 'down';

    return '<span class="badge ' . ($good ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger') . '">'
        . '<i class="fas fa-arrow-' . $arrow . ' me-1"></i>' . number_format(abs($percent), 2) . '%</span>';
};
?>

<?= $this->section('pageActions') ?>
<button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
<div class="btn-group">
    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-download me-1"></i>Export</button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="<?= site_url('reports/income-vs-expense/export/pdf?' . $qs) ?>"><i class="fas fa-file-pdf me-2"></i>PDF</a></li>
        <li><a class="dropdown-item" href="<?= site_url('reports/income-vs-expense/export/csv?' . $qs) ?>"><i class="fas fa-file-csv me-2"></i>CSV</a></li>
    </ul>
</div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div id="ivseReport">

<div class="alert alert-info small d-print-none">
    Income is invoices issued in each period (excluding drafts/cancelled). Expense is vendor bills issued in that
    period plus the operating-expense monthly-equivalent as of period end — the same two-part method the Profit
    &amp; Loss report uses, just broken out month by month here.
</div>

<form method="get" class="card mb-3 filter-form d-print-none">
    <div class="card-body d-flex gap-2 flex-wrap align-items-end">
        <div>
            <label class="form-label small mb-1">Company</label>
            <select name="company_id" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $companyId == $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">From</label>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= esc($from) ?>">
        </div>
        <div>
            <label class="form-label small mb-1">To</label>
            <input type="date" name="to" class="form-control form-control-sm" value="<?= esc($to) ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
        <a href="<?= site_url('reports/income-vs-expense') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <div class="col">
        <div class="sy-stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="sy-stat-icon" style="background:#dcfce7;color:#16a34a"><i class="fas fa-arrow-trend-up"></i></div>
                <?= $ivseTrendBadge($incomeTrend, true) ?>
            </div>
            <div class="sy-stat-label mt-2">TOTAL INCOME</div>
            <div class="sy-stat-value fs-4"><?= number_format($totalIncome, 2) ?></div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="sy-stat-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-file-invoice"></i></div>
                <?= $ivseTrendBadge($expenseTrend, false) ?>
            </div>
            <div class="sy-stat-label mt-2">TOTAL EXPENSES</div>
            <div class="sy-stat-value fs-4"><?= number_format($totalExpenses, 2) ?></div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="sy-stat-icon" style="background:<?= $netDifference >= 0 ? '#eef0f3;color:#3a404b' : '#fee2e2;color:#dc2626' ?>"><i class="fas fa-sack-dollar"></i></div>
                <?= $ivseTrendBadge($differenceTrend, true) ?>
            </div>
            <div class="sy-stat-label mt-2">NET DIFFERENCE</div>
            <div class="sy-stat-value fs-4 <?= $netDifference >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($netDifference, 2) ?></div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="sy-stat-icon" style="background:#ffedd5;color:#d97706"><i class="fas fa-percent"></i></div>
                <?= $ivseTrendBadge($ratioTrend, false) ?>
            </div>
            <div class="sy-stat-label mt-2">EXPENSE RATIO</div>
            <div class="sy-stat-value fs-4"><?= $expenseRatio === null ? '—' : number_format($expenseRatio, 1) . '%' ?></div>
        </div>
    </div>
</div>

<div class="sy-card">
    <div class="sy-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <strong>Monthly Breakdown</strong>
        <div class="d-flex gap-2 d-print-none">
            <input type="text" id="ivsePeriodSearch" class="form-control form-control-sm" style="width:180px" placeholder="Search period…">
            <button type="button" id="ivseSortToggle" class="btn btn-sm btn-outline-secondary" data-dir="asc"><i class="fas fa-arrow-down-short-wide me-1"></i>Oldest first</button>
        </div>
    </div>
    <div class="sy-card-body p-0">
        <?php if (empty($rows)): ?>
            <p class="text-muted mb-0 p-3">No data in this range.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Period</th>
                    <th class="text-end">Income</th>
                    <th class="text-end">Expense</th>
                    <th class="text-end">Difference</th>
                    <th class="text-end">Trend</th>
                </tr>
            </thead>
            <tbody id="ivseTableBody">
            <?php foreach ($rows as $r): ?>
                <tr data-period="<?= esc(mb_strtolower($r['label'])) ?>">
                    <td class="fw-semibold"><?= esc($r['label']) ?></td>
                    <td class="text-end text-success">+<?= number_format($r['income'], 2) ?></td>
                    <td class="text-end text-danger">-<?= number_format($r['expense'], 2) ?></td>
                    <td class="text-end fw-semibold <?= $r['difference'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= $r['difference'] >= 0 ? '+' : '' ?><?= number_format($r['difference'], 2) ?></td>
                    <td class="text-end"><?= $ivseTrendBadge($r['trend'], true) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="text-muted small mb-0 d-none p-3" id="ivseNoMatches">No periods match your search.</p>
        <?php endif; ?>
    </div>
</div>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>
    @media print {
        body * { visibility: hidden; }
        #ivseReport, #ivseReport * { visibility: visible; }
        #ivseReport { position: absolute; top: 0; left: 0; width: 100%; }
        .d-print-none { display: none !important; }
    }
</style>
<script>
(function () {
    var search = document.getElementById('ivsePeriodSearch');
    var noMatches = document.getElementById('ivseNoMatches');
    var tbody = document.getElementById('ivseTableBody');
    if (search && tbody) {
        search.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            var visible = 0;
            tbody.querySelectorAll('tr').forEach(function (row) {
                var match = row.dataset.period.indexOf(q) !== -1;
                row.classList.toggle('d-none', ! match);
                if (match) { visible++; }
            });
            if (noMatches) { noMatches.classList.toggle('d-none', visible !== 0); }
        });
    }

    var sortBtn = document.getElementById('ivseSortToggle');
    if (sortBtn && tbody) {
        sortBtn.addEventListener('click', function () {
            var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
            rows.reverse();
            rows.forEach(function (row) { tbody.appendChild(row); });
            var nowAsc = this.dataset.dir !== 'asc';
            this.dataset.dir = nowAsc ? 'asc' : 'desc';
            this.innerHTML = nowAsc
                ? '<i class="fas fa-arrow-down-short-wide me-1"></i>Oldest first'
                : '<i class="fas fa-arrow-up-short-wide me-1"></i>Newest first';
        });
    }
})();
</script>
<?= $this->endSection() ?>
