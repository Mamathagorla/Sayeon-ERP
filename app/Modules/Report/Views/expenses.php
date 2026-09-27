<?= $this->extend('layouts/main') ?>

<?php $qs = http_build_query($filters + ['period' => $periodType, 'month' => $periodMonth, 'year' => $periodYear]); ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('reports/expenses/export/pdf?' . $qs) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-pdf me-1"></i>PDF</a>
<a href="<?= site_url('reports/expenses/export/csv?' . $qs) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-csv me-1"></i>CSV</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <div class="col">
        <div class="sy-stat-card">
            <div class="sy-stat-icon" style="background:#dcfce7;color:#16a34a"><i class="fas fa-calendar-check"></i></div>
            <div class="sy-stat-label">TOTAL EXPENSES</div>
            <div class="sy-stat-value fs-4"><?= number_format($total, 2) ?></div>
            <div class="sy-stat-trend text-muted">As of <?= esc($periodLabel) ?></div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card">
            <div class="sy-stat-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-table-cells"></i></div>
            <div class="sy-stat-label">HIGHEST CATEGORY</div>
            <div class="sy-stat-value fs-4"><?= $topCategory ? esc($topCategory) : '—' ?></div>
            <div class="sy-stat-trend text-muted"><?= $topCategory ? number_format($byCategory[$topCategory], 2) . ' spent' : 'No expenses yet' ?></div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card">
            <div class="sy-stat-icon" style="background:#ffedd5;color:#d97706"><i class="fas fa-chart-column"></i></div>
            <div class="sy-stat-label">AVERAGE EXPENSE</div>
            <div class="sy-stat-value fs-4"><?= number_format(count($expenses) > 0 ? $total / count($expenses) : 0, 2) ?></div>
            <div class="sy-stat-trend text-muted"><?= count($expenses) ?> record(s)</div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card">
            <div class="sy-stat-icon" style="background:#eef0f3;color:#3a404b"><i class="fas fa-arrow-trend-up"></i></div>
            <div class="sy-stat-label">EXPENSE GROWTH</div>
            <?php if ($growthPercent === null): ?>
                <div class="sy-stat-value fs-4">—</div>
                <div class="sy-stat-trend text-muted">No prior period to compare</div>
            <?php else: ?>
                <div class="sy-stat-value fs-4 text-<?= $growthPercent >= 0 ? 'danger' : 'success' ?>"><?= $growthPercent >= 0 ? '+' : '' ?><?= $growthPercent ?>%</div>
                <div class="sy-stat-trend text-muted">vs previous <?= $periodType === 'year' ? 'year' : 'month' ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<form method="get" class="sy-card mb-3 filter-form">
    <div class="sy-card-body py-2 d-flex gap-2 flex-wrap align-items-end">
        <div>
            <label class="form-label small mb-1">Report</label>
            <select name="period" class="form-select form-select-sm">
                <option value="month" <?= $periodType === 'month' ? 'selected' : '' ?>>Monthly</option>
                <option value="year" <?= $periodType === 'year' ? 'selected' : '' ?>>Yearly</option>
            </select>
        </div>
        <?php if ($periodType === 'month'): ?>
        <div>
            <label class="form-label small mb-1">Month</label>
            <select name="month" class="form-select form-select-sm">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $periodMonth === $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label class="form-label small mb-1">Year</label>
            <select name="year" class="form-select form-select-sm">
                <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= $periodYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
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
        <a href="<?= site_url('reports/expenses') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<?php if ($periodType === 'year'): ?>
<div class="sy-card mb-3">
    <div class="sy-card-header"><strong>Monthly Trend — <?= esc($periodLabel) ?></strong></div>
    <div class="sy-card-body">
        <canvas id="expenseYearChart" height="90"></canvas>
    </div>
</div>
<?php endif; ?>

<div class="sy-card">
    <div class="sy-card-header"><strong>Expense Summary</strong> <span class="text-muted small"><?= count($expenses) ?> records</span></div>
    <div class="sy-card-body p-0">
        <?php if (empty($expenses)): ?>
            <p class="text-muted mb-0 p-3">No expenses match these filters.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Expense ID</th>
                    <th>Expense Name</th>
                    <th>Expense Category</th>
                    <th class="text-end">Amount</th>
                    <th>Payment Method</th>
                    <th>Renewal Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($expenses as $e): ?>
                <tr>
                    <td class="text-muted small">#EXP<?= str_pad((string) $e['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <div class="fw-semibold"><?= esc($e['vendor']) ?></div>
                        <div class="text-muted small"><?= esc($e['company_name']) ?> &middot; <?= esc(ucfirst($e['billing_cycle'])) ?></div>
                    </td>
                    <td class="text-muted small"><?= esc($e['category']) ?></td>
                    <td class="text-end fw-semibold"><?= number_format($e['amount'], 2) ?></td>
                    <td class="text-muted small"><?= esc($e['payment_method'] ?? '—') ?></td>
                    <td class="text-muted small"><?= esc($e['renewal_date'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= $e['status'] === 'active' ? 'success' : 'secondary' ?>"><?= esc(ucfirst($e['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?php if ($periodType === 'year'): ?>
<?= $this->section('scripts') ?>
<script>
new Chart(document.getElementById('expenseYearChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(static fn ($m) => date('M', mktime(0, 0, 0, $m, 1)), array_keys($monthlyBreakdown))) ?>,
        datasets: [{
            label: 'Monthly-equivalent spend',
            data: <?= json_encode(array_values($monthlyBreakdown)) ?>,
            backgroundColor: '#d62431'
        }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
</script>
<?= $this->endSection() ?>
<?php endif; ?>
