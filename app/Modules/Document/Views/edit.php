<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <form action="<?= site_url('documents/' . $document['id']) ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= $document['category'] === $cat ? 'selected' : '' ?>><?= esc(ucfirst($cat)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" value="<?= esc($document['title']) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Document Type <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="document_type" class="form-control" list="docTypes" value="<?= esc($document['document_type'] ?? '') ?>">
                    <datalist id="docTypes">
                        <option value="GST Certificate"><option value="PAN"><option value="Incorporation Certificate">
                        <option value="Agreement"><option value="Contract"><option value="Property Document">
                        <option value="Bank Document"><option value="License"><option value="Employee Document">
                    </datalist>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Linked Employee <span class="text-muted small">(optional)</span></label>
                    <select name="employee_user_id" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (($document['employee_user_id'] ?? null) == $u['id']) ? 'selected' : '' ?>><?= esc($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Expiry Date <span class="text-muted small">(optional)</span></label>
                    <input type="date" name="expiry_date" class="form-control" value="<?= esc($document['expiry_date'] ?? '') ?>">
                </div>
                <div class="col-md-9 mb-3">
                    <label class="form-label">File <span class="text-muted small">(leave blank to keep the current file)</span></label>
                    <input type="file" name="file" class="form-control">
                    <div class="form-text">Current: <a href="<?= site_url('files/download?path=' . urlencode($document['file_path'])) ?>"><?= esc($document['original_name']) ?></a> &middot; Maximum 20MB.</div>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                <div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="<?= site_url('documents') ?>" class="btn btn-light">Cancel</a>
                </div>
                <?php if (can('document.delete')): ?>
                <button type="submit" form="deleteDocumentForm" class="btn btn-outline-danger" onclick="return confirm('Delete this document? This cannot be undone.');"><i class="fas fa-trash me-1"></i>Delete Document</button>
                <?php endif; ?>
            </div>
        </form>

        <?php if (can('document.delete')): ?>
        <form id="deleteDocumentForm" action="<?= site_url('documents/' . $document['id'] . '/delete') ?>" method="post" class="d-none">
            <?= csrf_field() ?>
        </form>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
