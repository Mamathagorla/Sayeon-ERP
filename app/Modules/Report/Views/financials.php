<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('reports/financials/export/pdf?company_id=' . $companyId . '&from=' . $from . '&to=' . $to) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-pdf me-1"></i>PDF</a>
<a href="<?= site_url('reports/financials/export/csv?company_id=' . $companyId . '&from=' . $from . '&to=' . $to) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-csv me-1"></i>CSV</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="callout alert alert-info small">
    This is a simplified, informational snapshot — not a formal Profit &amp; Loss statement or Balance Sheet.
    Cash In/Out come directly from recorded payments; Operating Expenses is a proxy based on active
    subscriptions renewing in this period, not full accrual accounting.
</div>

<form method="get" class="card mb-3 filter-form">
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
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold text-success"><?= number_format($cashIn, 2) ?></div><div class="text-muted small">Revenue Collected</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold text-danger"><?= number_format($cashOut, 2) ?></div><div class="text-muted small">Bills Paid</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold"><?= number_format($periodExpenses, 2) ?></div><div class="text-muted small">Operating Expenses</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold <?= $netProfitLoss >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($netProfitLoss, 2) ?></div><div class="text-muted small">Net Profit / Loss</div></div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold"><?= number_format($netCashFlow, 2) ?></div><div class="text-muted small">Net Cash Flow</div></div></div>
    </div>
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold"><?= number_format($outstandingReceivables, 2) ?></div><div class="text-muted small">Outstanding Receivables</div></div></div>
    </div>
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold"><?= number_format($outstandingPayables, 2) ?></div><div class="text-muted small">Outstanding Payables</div></div></div>
    </div>
</div>
<?= $this->endSection() ?>
