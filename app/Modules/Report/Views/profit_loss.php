<?= $this->extend('layouts/main') ?>

<?php $qs = http_build_query(['company_id' => $companyId, 'month' => $monthValue]); ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('reports/profit-loss/export/pdf?' . $qs) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-pdf me-1"></i>PDF</a>
<a href="<?= site_url('reports/profit-loss/export/csv?' . $qs) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-csv me-1"></i>CSV</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="alert alert-info small">
    Revenue and Vendor Bills are accrual-based — every non-draft, non-cancelled invoice/bill <em>issued</em> in each
    month (recognized when earned, not when cash moves — see <a href="<?= site_url('reports/financials') ?>">Financial Summary</a>
    for the cash-basis view). Each expense category is a monthly-equivalent snapshot as of that month's end —
    subscriptions aren't discrete dated transactions in this system, so this is an as-of figure, not a period sum.
</div>

<form method="get" class="card mb-3 filter-form">
    <div class="card-body d-flex gap-2 flex-wrap align-items-end">
        <div>
            <label class="form-label small mb-1">Company</label>
            <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Companies</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $companyId == $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Ending Month</label>
            <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach ($monthOptions as $opt): ?>
                    <option value="<?= $opt['value'] ?>" <?= $opt['value'] === $monthValue ? 'selected' : '' ?>><?= esc($opt['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button type="submit" class="btn btn-primary btn-sm">Apply</button></noscript>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold text-success"><?= number_format($totalRevenue, 2) ?></div><div class="text-muted small">Total Revenue</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold text-danger"><?= number_format($totalExpenses, 2) ?></div><div class="text-muted small">Total Expenses</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($netProfit, 2) ?></div><div class="text-muted small">Net Profit</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold"><?= $margin !== null ? esc($margin) . '%' : '—' ?></div><div class="text-muted small">Margin</div></div></div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <strong>Statement Breakdown</strong>
        <span class="text-muted small"><?= esc($perMonth[0]['label']) ?> &ndash; <?= esc($perMonth[count($perMonth) - 1]['label']) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Line Item</th>
                    <th>Category</th>
                    <?php foreach ($perMonth as $m): ?>
                        <th class="text-end"><?= esc($m['label']) ?></th>
                    <?php endforeach; ?>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr class="table-light"><td colspan="<?= 3 + count($perMonth) ?>"><strong>REVENUE</strong></td></tr>
                <tr>
                    <td>Invoiced Revenue</td>
                    <td class="text-muted small">Income</td>
                    <?php foreach ($perMonth as $m): ?>
                        <td class="text-end"><?= number_format($m['revenue'], 2) ?></td>
                    <?php endforeach; ?>
                    <td class="text-end fw-semibold"><?= number_format($totalRevenue, 2) ?></td>
                </tr>

                <tr class="table-light"><td colspan="<?= 3 + count($perMonth) ?>"><strong>EXPENSES</strong></td></tr>
                <?php if (empty($categories) && $totalVendorBills == 0): ?>
                <tr><td colspan="<?= 3 + count($perMonth) ?>" class="text-muted small">No active expenses or vendor bills.</td></tr>
                <?php endif; ?>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?= esc($cat) ?></td>
                    <td class="text-muted small">Operating</td>
                    <?php foreach ($perMonth as $m): ?>
                        <td class="text-end text-danger">-<?= number_format($m['byCategory'][$cat] ?? 0, 2) ?></td>
                    <?php endforeach; ?>
                    <td class="text-end text-danger fw-semibold">-<?= number_format($categoryTotals[$cat], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($totalVendorBills > 0): ?>
                <tr>
                    <td>Vendor Bills</td>
                    <td class="text-muted small">Payables</td>
                    <?php foreach ($perMonth as $m): ?>
                        <td class="text-end text-danger">-<?= number_format($m['vendorBills'], 2) ?></td>
                    <?php endforeach; ?>
                    <td class="text-end text-danger fw-semibold">-<?= number_format($totalVendorBills, 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="table-secondary">
                    <td colspan="2"><strong>Total Expenses</strong></td>
                    <?php foreach ($perMonth as $m): ?>
                        <td class="text-end"><strong>-<?= number_format($m['totalExpenses'], 2) ?></strong></td>
                    <?php endforeach; ?>
                    <td class="text-end"><strong>-<?= number_format($totalExpenses, 2) ?></strong></td>
                </tr>

                <tr class="<?= $netProfit >= 0 ? 'table-success' : 'table-danger' ?>">
                    <td colspan="2"><strong>NET PROFIT</strong></td>
                    <?php foreach ($perMonth as $m): ?>
                        <td class="text-end"><strong><?= number_format($m['netProfit'], 2) ?></strong></td>
                    <?php endforeach; ?>
                    <td class="text-end"><strong><?= number_format($netProfit, 2) ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
