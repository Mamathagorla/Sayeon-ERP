<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold"><?= number_format($outstandingReceivables, 2) ?></div><div class="text-muted small">Outstanding Receivables</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold"><?= number_format($outstandingPayables, 2) ?></div><div class="text-muted small">Outstanding Payables</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold text-danger"><?= $overdueInvoiceCount ?></div><div class="text-muted small">Overdue Invoices</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-center"><div class="card-body"><div class="fs-4 fw-bold text-danger"><?= $overdueBillCount ?></div><div class="text-muted small">Overdue Bills</div></div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Invoices</h5>
                <p class="card-text text-muted small">Money owed to the company by customers. Record payments received against each invoice.</p>
                <a href="<?= site_url('accounting/invoices') ?>" class="btn btn-primary btn-sm">Open Invoices</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-file-invoice text-primary me-2"></i>Bills</h5>
                <p class="card-text text-muted small">Money the company owes to vendors. Record payments made against each bill.</p>
                <a href="<?= site_url('accounting/bills') ?>" class="btn btn-primary btn-sm">Open Bills</a>
            </div>
        </div>
    </div>
</div>
<p class="text-muted small mt-3">Profit &amp; Loss and Cash Flow summaries are available under
<a href="<?= site_url('reports/financials') ?>">Reports → Financial Summary</a>.</p>
<?= $this->endSection() ?>
