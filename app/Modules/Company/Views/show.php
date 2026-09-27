<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('company.edit')): ?>
<a href="<?= site_url('companies/' . $company['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Edit</a>
<?php endif; ?>
<a href="<?= site_url('companies') ?>" class="btn btn-light btn-sm">Back to list</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    .co-meta-grid { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #edf1f4; border-radius: 10px; overflow: hidden; }
    .co-meta-col { border-right: 1px solid #edf1f4; }
    .co-meta-col:last-child { border-right: none; }
    .co-meta-row { display: flex; justify-content: space-between; gap: 16px; padding: 11px 16px; border-bottom: 1px solid #f0f3f6; font-size: .86rem; }
    .co-meta-row:last-child { border-bottom: none; }
    .co-meta-row .k { font-weight: 600; color: #10243f; flex-shrink: 0; }
    .co-meta-row .v { color: #10243f; text-align: right; }
    .co-meta-row .v.muted { color: #8592a3; }
    @media (max-width: 767px) {
        .co-meta-grid { grid-template-columns: 1fr; }
        .co-meta-col { border-right: none; border-bottom: 1px solid #edf1f4; }
        .co-meta-col:last-child { border-bottom: none; }
    }

    /* List/Add sub-tab toggles (Directors, Bank Accounts) — "List" reads
       as a plain tab, "Add ..." reads as the same solid teal action
       button used everywhere else in the app (e.g. "Add Compliance
       Item"), not a muted Bootstrap pill. */
    .sub-toggle-pills .nav-link {
        border-radius: 20px;
        font-size: .82rem;
        font-weight: 600;
        padding: 6px 16px;
        color: #6b7a90;
    }
    .sub-toggle-pills .nav-link:not(.add-action).active {
        background: #eef2f5;
        color: #10243f;
    }
    .sub-toggle-pills .nav-link.add-action {
        background: linear-gradient(135deg, var(--sy-teal) 0%, var(--sy-teal-dark) 100%);
        color: #fff;
    }
    .sub-toggle-pills .nav-link.add-action:hover { color: #fff; filter: brightness(1.05); }
</style>
<div class="row mb-3">
    <div class="col-md-3"><strong>Status:</strong> <span class="badge bg-<?= $company['status'] === 'active' ? 'success' : 'secondary' ?>"><?= esc($company['status']) ?></span></div>
    <div class="col-md-3"><strong>Country:</strong> <?= esc($company['country']) ?></div>
    <div class="col-md-3"><strong>GST:</strong> <?= esc($company['gst'] ?? '—') ?></div>
    <div class="col-md-3"><strong>PAN:</strong> <?= esc($company['pan'] ?? '—') ?></div>
</div>

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button">Overview</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-directors" type="button">Directors</button></li>
    <?php if ($canViewBankAccounts): ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-bank-accounts" type="button">Bank Accounts</button></li>
    <?php else: ?>
    <li class="nav-item"><button class="nav-link disabled" type="button" title="You don't have permission to view bank accounts">Bank Accounts</button></li>
    <?php endif; ?>
    <?php if ($canViewDocuments): ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-documents" type="button">Documents</button></li>
    <?php else: ?>
    <li class="nav-item"><button class="nav-link disabled" type="button" title="You don't have permission to view documents">Documents</button></li>
    <?php endif; ?>
    <?php if (can('compliance.view')): ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-compliance" type="button">Compliance</button></li>
    <?php else: ?>
    <li class="nav-item"><button class="nav-link disabled" type="button" title="You don't have permission to view compliance items">Compliance</button></li>
    <?php endif; ?>
    <?php if ($canViewEmployees): ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-employees" type="button">Employees</button></li>
    <?php else: ?>
    <li class="nav-item"><button class="nav-link disabled" type="button" title="You don't have permission to view employees">Employees</button></li>
    <?php endif; ?>
    <?php if ($canViewWebsites): ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-websites" type="button">Websites &amp; Hosting (<?= count($websites) ?>)</button></li>
    <?php else: ?>
    <li class="nav-item"><button class="nav-link disabled" type="button" title="You don't have permission to view websites">Websites &amp; Hosting</button></li>
    <?php endif; ?>
</ul>

<div class="tab-content border border-top-0 p-3">
    <div class="tab-pane fade show active" id="tab-overview">
        <div class="co-meta-grid">
            <div class="co-meta-col">
                <div class="co-meta-row"><span class="k">Status</span><span class="v"><span class="badge bg-<?= $company['status'] === 'active' ? 'success' : 'secondary' ?>"><?= esc(ucfirst($company['status'])) ?></span></span></div>
                <div class="co-meta-row"><span class="k">Country</span><span class="v"><?= esc($company['country']) ?></span></div>
                <div class="co-meta-row"><span class="k">Owner</span><span class="v <?= $company['owner_name'] ? '' : 'muted' ?>"><?= esc($company['owner_name'] ?? '—') ?></span></div>
                <div class="co-meta-row"><span class="k">CIN</span><span class="v <?= $company['cin'] ? '' : 'muted' ?>"><?= esc($company['cin'] ?? '—') ?></span></div>
                <div class="co-meta-row"><span class="k">GST</span><span class="v <?= $company['gst'] ? '' : 'muted' ?>"><?= esc($company['gst'] ?? '—') ?></span></div>
            </div>
            <div class="co-meta-col">
                <div class="co-meta-row"><span class="k">PAN</span><span class="v <?= $company['pan'] ? '' : 'muted' ?>"><?= esc($company['pan'] ?? '—') ?></span></div>
                <div class="co-meta-row"><span class="k">Incorporation Date</span><span class="v <?= $company['incorporation_date'] ? '' : 'muted' ?>"><?= $company['incorporation_date'] ? esc(date('d/m/Y', strtotime($company['incorporation_date']))) : '—' ?></span></div>
                <div class="co-meta-row"><span class="k">Registered Address</span><span class="v <?= $company['registered_address'] ? '' : 'muted' ?>"><?= nl2br(esc($company['registered_address'] ?? '—')) ?></span></div>
                <div class="co-meta-row"><span class="k">Added On</span><span class="v"><?= $company['created_at'] ? esc(date('d/m/Y, g:i A', strtotime($company['created_at']))) : '—' ?></span></div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-directors">
        <ul class="nav nav-pills mb-3 sub-toggle-pills justify-content-end">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#director-list" type="button">List</button></li>
            <?php if (can('company.edit')): ?>
            <li class="nav-item"><button class="nav-link add-action" data-bs-toggle="pill" data-bs-target="#director-add" type="button"><i class="fas fa-plus me-1"></i>Add Director</button></li>
            <?php endif; ?>
        </ul>
        <div class="tab-content">
        <div class="tab-pane fade show active" id="director-list">
        <table class="table table-sm table-striped">
            <thead><tr><th>Name</th><th>DIN</th><th>Designation</th><th>Email</th><th>Appointed</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($directors as $d): ?>
                <tr>
                    <td><?= esc($d['name']) ?></td>
                    <td><?= esc($d['din'] ?? '—') ?></td>
                    <td><?= esc($d['designation'] ?? '—') ?></td>
                    <td><?= esc($d['email'] ?? '—') ?></td>
                    <td><?= $d['appointed_date'] ? esc(date('d/m/Y', strtotime($d['appointed_date']))) : '—' ?></td>
                    <td class="text-end">
                        <?php if (can('company.edit')): ?>
                        <a href="<?= site_url('companies/' . $company['id'] . '/directors/' . $d['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                        <form action="<?= site_url('companies/' . $company['id'] . '/directors/' . $d['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Remove this director?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($directors)): ?><p class="text-muted small mb-0">No directors on record for this company yet.</p><?php endif; ?>
        </div>

        <div class="tab-pane fade" id="director-add">
        <form action="<?= site_url('companies/' . $company['id'] . '/directors') ?>" method="post" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Name</label>
                <input type="text" name="name" class="form-control form-control-sm" placeholder="Name" pattern="[A-Za-z\s.'\-]+" title="Letters, spaces, apostrophes, hyphens and periods only" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">DIN</label>
                <input type="text" name="din" class="form-control form-control-sm" placeholder="DIN" maxlength="8" inputmode="numeric" pattern="\d{8}" title="Enter exactly 8 digits" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 8)">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Designation</label>
                <input type="text" name="designation" class="form-control form-control-sm" placeholder="Designation">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Email</label>
                <input type="email" name="email" class="form-control form-control-sm" placeholder="Email">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Appointed date</label>
                <input type="date" name="appointed_date" class="form-control form-control-sm">
            </div>
            <div class="col-12 mt-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-check me-1"></i>Save</button>
            </div>
        </form>
        </div>
        </div>
    </div>

    <?php if ($canViewBankAccounts): ?>
    <div class="tab-pane fade" id="tab-bank-accounts">
        <ul class="nav nav-pills mb-3 sub-toggle-pills justify-content-end">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#bank-list" type="button">List</button></li>
            <?php if (can('bank_account.create')): ?>
            <li class="nav-item"><button class="nav-link add-action" data-bs-toggle="pill" data-bs-target="#bank-add" type="button"><i class="fas fa-plus me-1"></i>Add Account</button></li>
            <?php endif; ?>
        </ul>
        <div class="tab-content">
        <div class="tab-pane fade show active" id="bank-list">
        <table class="table table-sm table-striped">
            <thead><tr><th>Bank</th><th>Branch</th><th>Account Holder</th><th>Account No.</th><th>IFSC</th><th>Signatories</th><th>Status</th><th>Document</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($bankAccounts as $b): ?>
                <tr>
                    <td><?= esc($b['bank_name']) ?></td>
                    <td><?= esc($b['branch'] ?? '—') ?></td>
                    <td><?= esc($b['account_holder']) ?></td>
                    <td class="mono">•••• <?= esc($b['account_number_last4']) ?></td>
                    <td><?= esc($b['ifsc'] ?? '—') ?></td>
                    <td class="text-muted small"><?= esc($b['authorized_signatories'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= $b['status'] === 'active' ? 'success' : ($b['status'] === 'closed' ? 'danger' : 'secondary') ?>"><?= esc(ucfirst($b['status'])) ?></span></td>
                    <td>
                        <?php if ($b['document_path']): ?>
                            <a href="<?= site_url('files/download?path=' . urlencode($b['document_path'])) ?>"><?= esc($b['document_name']) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if (can('bank_account.edit')): ?>
                        <a href="<?= site_url('companies/' . $company['id'] . '/bank-accounts/' . $b['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (can('bank_account.delete')): ?>
                        <form action="<?= site_url('companies/' . $company['id'] . '/bank-accounts/' . $b['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Remove this bank account?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($bankAccounts)): ?><p class="text-muted small">No bank accounts on record for this company yet.</p><?php endif; ?>
        </div>

        <?php if (can('bank_account.create')): ?>
        <div class="tab-pane fade" id="bank-add">
        <form action="<?= site_url('companies/' . $company['id'] . '/bank-accounts') ?>" method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Bank name</label>
                <input type="text" name="bank_name" class="form-control form-control-sm" placeholder="Bank name" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Branch</label>
                <input type="text" name="branch" class="form-control form-control-sm" placeholder="Branch">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Account holder</label>
                <input type="text" name="account_holder" class="form-control form-control-sm" placeholder="Account holder" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">IFSC</label>
                <input type="text" name="ifsc" class="form-control form-control-sm" placeholder="IFSC" maxlength="11" style="text-transform:uppercase" pattern="[A-Za-z]{4}0[A-Za-z0-9]{6}" title="Enter a valid 11-character IFSC code, e.g. HDFC0001234">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Account number</label>
                <input type="text" name="account_number" class="form-control form-control-sm" placeholder="Account number" maxlength="34" inputmode="numeric" pattern="\d+" title="Digits only" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Authorized signatories</label>
                <input type="text" name="authorized_signatories" class="form-control form-control-sm" placeholder="Authorized signatories">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <?php foreach (['active', 'inactive', 'closed'] as $s): ?>
                        <option value="<?= $s ?>"><?= esc(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Document</label>
                <input type="file" name="document" class="form-control form-control-sm">
            </div>
            <div class="col-12 mt-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-check me-1"></i>Save</button>
            </div>
        </form>
        <p class="text-muted small mt-2 mb-0">The account number is encrypted before it's stored — only the last 4 digits are ever displayed.</p>
        </div>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canViewDocuments): ?>
    <div class="tab-pane fade" id="tab-documents">
        <ul class="nav nav-pills mb-3 sub-toggle-pills justify-content-end">
            <li class="nav-item"><span class="nav-link active">List</span></li>
            <?php if (can('document.create')): ?>
            <li class="nav-item"><a href="<?= site_url('documents/create?company_id=' . $company['id']) ?>" class="nav-link add-action"><i class="fas fa-plus me-1"></i>Upload Document</a></li>
            <?php endif; ?>
        </ul>
        <table class="table table-sm table-striped">
            <thead><tr><th>Title</th><th>Category</th><th>Type</th><th>Expiry</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($documents as $d): ?>
                <tr class="<?= ($d['expiry_date'] && $d['expiry_date'] < date('Y-m-d')) ? 'table-danger' : '' ?>">
                    <td><a href="<?= site_url('files/download?path=' . urlencode($d['file_path'])) ?>"><?= esc($d['title']) ?></a></td>
                    <td><span class="badge bg-secondary"><?= esc(ucfirst($d['category'])) ?></span></td>
                    <td><?= esc($d['document_type'] ?? '—') ?></td>
                    <td><?= $d['expiry_date'] ? esc(date('d/m/Y', strtotime($d['expiry_date']))) : '—' ?></td>
                    <td class="text-end">
                        <?php if (can('document.edit')): ?>
                        <a href="<?= site_url('documents/' . $d['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($documents)): ?><p class="text-muted small mb-0">No documents uploaded for this company yet.</p><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (can('compliance.view')): ?>
    <div class="tab-pane fade" id="tab-compliance">
        <ul class="nav nav-pills mb-3 sub-toggle-pills justify-content-end">
            <li class="nav-item"><span class="nav-link active">List</span></li>
            <?php if (can('compliance.create')): ?>
            <li class="nav-item"><a href="<?= site_url('compliance/create?company_id=' . $company['id']) ?>" class="nav-link add-action"><i class="fas fa-plus me-1"></i>Add Compliance Item</a></li>
            <?php endif; ?>
        </ul>
        <table class="table table-sm table-striped">
            <thead><tr><th>Type</th><th>Due Date</th><th>Recurrence</th><th>Responsible</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($compliance as $i): ?>
                <tr class="<?= $i['status'] === 'overdue' ? 'table-danger' : '' ?>">
                    <td><a href="<?= site_url('compliance/' . $i['id']) ?>"><?= esc($i['title'] ?: $i['type_name']) ?></a></td>
                    <td><?= esc(date('d/m/Y', strtotime($i['due_date']))) ?></td>
                    <td><?= esc(ucwords(str_replace('_', ' ', $i['recurrence']))) ?></td>
                    <td><?= esc($i['responsible_name'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= ['pending' => 'secondary', 'in_progress' => 'info', 'filed' => 'success', 'overdue' => 'danger'][$i['status']] ?>"><?= esc(ucwords(str_replace('_', ' ', $i['status']))) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($compliance)): ?><p class="text-muted small mb-0">No compliance items for this company yet.</p><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($canViewEmployees): ?>
    <div class="tab-pane fade" id="tab-employees">
        <ul class="nav nav-pills mb-3 sub-toggle-pills justify-content-end">
            <li class="nav-item"><span class="nav-link active">List</span></li>
            <?php if (can('employee.create')): ?>
            <li class="nav-item"><a href="<?= site_url('hr/employees/create') ?>" class="nav-link add-action"><i class="fas fa-plus me-1"></i>Add Employee</a></li>
            <?php endif; ?>
        </ul>
        <table class="table table-sm table-striped">
            <thead><tr><th>Code</th><th>Name</th><th>Department</th><th>Designation</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($employees as $e): ?>
                <tr>
                    <td><?= esc($e['employee_code']) ?></td>
                    <td><a href="<?= site_url('hr/employees/' . $e['id']) ?>"><?= esc($e['user_name']) ?></a></td>
                    <td><?= esc($e['department_name'] ?? '—') ?></td>
                    <td><?= esc($e['designation'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= ['active' => 'success', 'on_leave' => 'warning', 'resigned' => 'secondary', 'terminated' => 'danger'][$e['status']] ?>"><?= esc(ucwords(str_replace('_', ' ', $e['status']))) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($employees)): ?><p class="text-muted small mb-0">No employees on record for this company yet.</p><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($canViewWebsites): ?>
    <div class="tab-pane fade" id="tab-websites">
        <ul class="nav nav-pills mb-3 sub-toggle-pills justify-content-end">
            <li class="nav-item"><span class="nav-link active">List</span></li>
            <?php if (can('website.create')): ?>
            <li class="nav-item"><a href="<?= site_url('websites/create?company_id=' . $company['id']) ?>" class="nav-link add-action"><i class="fas fa-plus me-1"></i>Add Website</a></li>
            <?php endif; ?>
        </ul>
        <table class="table table-sm table-striped">
            <thead><tr><th>Domain</th><th>Hosting Provider</th><th>SSL Expiry</th><th>Renewal Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($websites as $w): ?>
                <tr>
                    <td><a href="<?= site_url('websites/' . $w['id']) ?>"><?= esc($w['domain']) ?></a></td>
                    <td><?= esc($w['hosting_provider'] ?? '—') ?></td>
                    <td><?= $w['ssl_expiry'] ? esc(date('d/m/Y', strtotime($w['ssl_expiry']))) : '—' ?></td>
                    <td><?= $w['renewal_date'] ? esc(date('d/m/Y', strtotime($w['renewal_date']))) : '—' ?></td>
                    <td><span class="badge bg-<?= ['active' => 'success', 'inactive' => 'secondary', 'expired' => 'danger'][$w['status']] ?>"><?= esc(ucfirst($w['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($websites)): ?><p class="text-muted small mb-0">No websites or hosting records for this company yet.</p><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
