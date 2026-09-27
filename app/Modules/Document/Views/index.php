<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<?php if (can('document.create')): ?>
<a href="<?= site_url('documents/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-upload me-1"></i>Upload Document</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<form method="get" class="card mb-3 filter-form">
    <div class="card-body d-flex gap-2 flex-wrap align-items-end">
        <div>
            <label class="form-label small mb-1">Company</label>
            <select name="company_id" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (($filters['company_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width:160px">
            <label class="form-label small mb-1">Category</label>
            <?php $selectedCategories = (array) ($filters['category'] ?? []); ?>
            <div class="dropdown sy-msel" data-placeholder="All">
                <button type="button" class="btn btn-sm dropdown-toggle sy-msel-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <span class="sy-msel-label">All</span>
                </button>
                <div class="dropdown-menu sy-msel-menu">
                    <?php foreach ($categories as $cat): ?>
                        <label class="sy-msel-item" data-label="<?= esc(ucfirst($cat)) ?>">
                            <input type="checkbox" class="sy-msel-opt" name="category[]" value="<?= $cat ?>" <?= in_array($cat, $selectedCategories, true) ? 'checked' : '' ?>>
                            <?= esc(ucfirst($cat)) ?>
                        </label>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary btn-sm w-100 sy-msel-apply">Apply</button>
                </div>
            </div>
        </div>
        <a href="<?= site_url('documents') ?>" class="btn btn-light btn-sm">Reset</a>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <?php if (empty($documents)): ?>
            <p class="text-muted mb-0">No documents uploaded yet.</p>
        <?php else: ?>
        <table class="table table-sm table-striped">
            <thead><tr><th>Title</th><th>Company</th><th>Category</th><th>Type</th><th>Employee</th><th>Expiry</th><th>Uploaded By</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($documents as $d): ?>
                <tr class="<?= ($d['expiry_date'] && $d['expiry_date'] < date('Y-m-d')) ? 'table-danger' : '' ?>">
                    <td><a href="<?= site_url('files/download?path=' . urlencode($d['file_path'])) ?>"><?= esc($d['title']) ?></a></td>
                    <td><?= esc($d['company_name']) ?></td>
                    <td><span class="badge bg-secondary"><?= esc(ucfirst($d['category'])) ?></span></td>
                    <td><?= esc($d['document_type'] ?? '—') ?></td>
                    <td><?= esc($d['employee_name'] ?? '—') ?></td>
                    <td><?= $d['expiry_date'] ? esc(date('d/m/Y', strtotime($d['expiry_date']))) : '—' ?></td>
                    <td><?= esc($d['uploaded_by_name'] ?? '—') ?></td>
                    <td class="text-end">
                        <?php if (can('document.edit')): ?>
                        <a href="<?= site_url('documents/' . $d['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
