<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('documents/templates/' . $template['id'] . '/test') ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="fas fa-flask me-1"></i>Preview</a>
<?php if ($template['file_type'] === 'html' && $template['status'] !== 'active' && $usage === 0 && can('document.edit')): ?>
<a href="<?= site_url('documents/templates/' . $template['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Edit header / body / footer</a>
<?php endif; ?>
<?php if ($template['file_type'] === 'pdf' && can('document.create')): ?>
<a href="<?= site_url('documents/templates/' . $template['id'] . '/editable') ?>" class="btn btn-outline-primary btn-sm" title="Rebuild this PDF as an editable HTML template (for this or another company)"><i class="fas fa-pen-ruler me-1"></i>Make editable (HTML)</a>
<?php endif; ?>
<?php if (can('document.create')): ?>
<a href="<?= site_url('documents/templates/create?draft=1&company_id=' . $template['company_id'] . '&document_type=' . urlencode($template['document_type'])) ?>" class="btn btn-outline-secondary btn-sm" title="Upload a replacement file as a new draft version"><i class="fas fa-file-arrow-up me-1"></i>Replace file</a>
<a href="<?= site_url('documents/templates/clone?source=' . $template['id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-clone me-1"></i>Clone</a>
<?php endif; ?>
<a href="<?= site_url('documents/templates/' . $template['id'] . '/download') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-download me-1"></i>Download</a>
<?php if ($template['file_type'] === 'pdf' && can('document.edit')): ?>
<a href="<?= site_url('documents/templates/' . $template['id'] . '/map') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-crosshairs me-1"></i>Review fields</a>
<?php endif; ?>
<a href="<?= site_url('documents/templates') ?>" class="btn btn-light btn-sm">Back</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <strong><?= esc($template['template_name']) ?></strong>
                    <span>
                        <span class="badge bg-secondary">v<?= (int) $template['version'] ?></span> <span class="badge bg-light text-dark border"><?= strtoupper(esc($template["file_type"])) ?></span>
                        <span class="badge <?= $template['status'] === 'active' ? 'bg-success' : 'bg-light text-dark border' ?>"><?= ucfirst($template['status']) ?></span>
                    </span>
                </div>
                <?php if ($template['file_type'] === 'pdf'): ?>
                <div class="alert alert-secondary py-2 small"><strong>Fixed-layout PDF.</strong> The page design, letterhead, logo and fixed text come from the uploaded PDF and cannot be edited here. Only the detected fields (shown in <em>Review fields</em>) are replaced with each record's data, printed over the original. To change the header, footer, wording or layout, use <em>Make editable (HTML)</em> to rebuild its text as an editable template for this or another company, or upload a new PDF (<em>Replace file</em>).</div>
                <?php elseif ($template['file_type'] === 'docx'): ?>
                <div class="alert alert-secondary py-2 small"><strong>Word template.</strong> Its layout, fonts and spacing are kept as uploaded. To change the header, footer, logo or clauses: <em>Download</em>, edit in Word keeping the <code>{{placeholders}}</code>, then <em>Replace file</em>.</div>
                <?php else: ?>
                <div class="alert alert-secondary py-2 small"><strong>HTML template.</strong> Header, body and footer are fully editable (logo, text, tables, fonts, margins, alignment) using <em>Edit header / body / footer</em> while it is a draft.</div>
                <?php endif; ?>
                <?php if ($template['file_type'] === 'html'): ?>
                <!-- Sandboxed with no permissions: scripts/forms/navigation in the template can never run. -->
                <iframe sandbox="" srcdoc="<?= esc($html, 'attr') ?>" style="width:100%;height:700px;border:1px solid var(--sy-border, #dee2e6);background:#fff"></iframe>
                <?php elseif ($template['file_type'] === 'docx'): ?>
                <div class="small text-muted mb-1">Text preview — download the file to see the full Word layout.</div>
                <pre style="white-space:pre-wrap;max-height:700px;overflow:auto" class="border rounded p-3 mb-0"><?= esc($docxText) ?></pre>
                <?php else: ?>
                <iframe src="<?= site_url('documents/templates/' . $template['id'] . '/download?inline=1') ?>" style="width:100%;height:700px;border:1px solid var(--sy-border, #dee2e6)"></iframe>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body small">
                <h6>Status</h6>
                <div class="mb-2">
                    <?php if ($template['status'] === 'active'): ?><span class="badge bg-success">Active</span> — in use for new documents.
                    <?php elseif ($template['status'] === 'draft'): ?><span class="badge bg-warning text-dark">Draft</span> — not used until previewed and activated.
                    <?php else: ?><span class="badge bg-light text-dark border">Inactive</span><?php endif; ?>
                </div>
                <?php if (! empty($source)): ?>
                    <div class="text-muted mb-2">Cloned from <strong><?= esc($source['company_name']) ?></strong> — <?= esc($source['template_name']) ?> v<?= (int) $source['version'] ?>. The source is unaffected by changes here.</div>
                <?php endif; ?>
                <?php if ($template['status'] !== 'active' && can('document.edit')): ?>
                    <?php $ready = $template['status'] !== 'draft' || ! empty($template['previewed_at']); ?>
                    <?php if (! $blocking): ?>
                    <form method="post" action="<?= site_url('documents/templates/' . $template['id'] . '/activate') ?>" class="mb-2">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-success" <?= $ready ? '' : 'disabled' ?>>Activate this version</button>
                        <?php if (! $ready): ?><span class="text-muted ms-1">Preview it first.</span><?php endif; ?>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>
                <hr>
                <h6>Placeholders used</h6>
                <?php if ($template['file_type'] === 'pdf'): ?>
                    <div class="text-muted">This PDF format is kept exactly as uploaded. Detected fields are replaced automatically; use <strong>Review fields</strong> only to adjust them. Available data: <?= esc(implode(', ', $requiredFields)) ?>.</div>
                <?php elseif (empty($placeholders)): ?>
                    <p class="text-muted mb-0">None found — this template would print identically for every record.</p>
                <?php else: ?>
                    <?php foreach ($placeholders as $p): ?><code class="d-inline-block me-1 mb-1">{{<?= esc($p) ?>}}</code><?php endforeach; ?>
                <?php endif; ?>
                <?php if (! empty($flags)): ?>
                    <hr>
                    <h6>Automatic detection</h6>
                    <?php foreach ($flags as $f): ?>
                        <div class="mb-1 <?= ! empty($f['blocking']) ? 'text-warning-emphasis' : 'text-muted' ?>">
                            <i class="fas <?= ! empty($f['blocking']) ? 'fa-triangle-exclamation' : 'fa-circle-info' ?> me-1"></i>
                            <strong><?= esc($f['field']) ?></strong><?= $f['label'] !== '' ? ' (“' . esc($f['label']) . '”)' : '' ?> — <?= esc($f['reason']) ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($blocking && can('document.edit')): ?>
                        <div class="mt-2 d-flex flex-wrap gap-1">
                            <?php if ($template['file_type'] === 'pdf'): ?>
                            <a class="btn btn-sm btn-primary" href="<?= site_url('documents/templates/' . $template['id'] . '/map') ?>">Review fields</a>
                            <?php endif; ?>
                            <form method="post" action="<?= site_url('documents/templates/' . $template['id'] . '/accept') ?>" onsubmit="return confirm('Accept these findings and use the template as it is?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="activate" value="1">
                                <button class="btn btn-sm btn-outline-success">Accept &amp; activate</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <hr>
                <div class="text-muted">File: <?= esc($template['original_name']) ?></div>
                <div class="text-muted">Uploaded: <?= esc(erp_datetime($template['created_at'])) ?></div>
                <?php if (! empty($template['notes'])): ?><div class="text-muted">Notes: <?= esc($template['notes']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
