<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('customer.edit')): ?>
<a href="<?= site_url('customers/' . $customer['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<?php endif; ?>
<a href="<?= site_url('customers') ?>" class="btn btn-light btn-sm">Back</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $val = static fn ($v) => ($v === null || $v === '') ? '—' : esc((string) $v); ?>
<div class="card col-lg-8">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h5 class="mb-1"><?= esc($customer['name']) ?></h5>
                <span class="text-muted small"><?= esc($customer['company_name']) ?></span>
            </div>
            <span class="badge bg-<?= $customer['status'] === 'active' ? 'success' : 'secondary' ?>"><?= esc(ucfirst($customer['status'])) ?></span>
        </div>
        <div class="row g-3">
            <div class="col-md-6"><strong>Contact Person:</strong> <?= $val($customer['contact_person']) ?></div>
            <div class="col-md-6"><strong>Email:</strong> <?= $val($customer['email']) ?></div>
            <div class="col-md-6"><strong>Phone:</strong> <?= $val($customer['phone']) ?></div>
            <div class="col-md-6"><strong>GSTIN:</strong> <?= $val($customer['gstin']) ?></div>
            <div class="col-md-6"><strong>PAN:</strong> <?= $val($customer['pan']) ?></div>
            <div class="col-md-6"><strong>City:</strong> <?= $val($customer['city']) ?></div>
            <div class="col-md-6"><strong>State:</strong> <?= $val($customer['state_name']) ?></div>
            <div class="col-md-6"><strong>Country:</strong> <?= $val($customer['country_name']) ?></div>
            <div class="col-12"><strong>Address:</strong> <?= $customer['address'] ? nl2br(esc($customer['address'])) : '—' ?></div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
