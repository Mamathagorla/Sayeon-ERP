<?php
/**
 * Dropdown entries for company-template documents of one record.
 *
 * Offers only documents that (a) the company has an ACTIVE, usable template for and (b) this user may
 * generate, so nobody is shown an action that ends in "No active … template is configured".
 * When nothing applies it renders a short informational line instead of buttons.
 *
 * Expects:
 *   $companyId  int   company of the record (server-side value, never from the browser)
 *   $docItems   list  of [type, recordId] — recordId 0/null = that document cannot exist for this record yet
 * Optional:
 *   $emptyText  string  message when nothing is offered (default below); $showEmpty bool (default true)
 */
$avail     = \App\Modules\Document\Services\DocumentAvailability::shared();
$offered   = 0;
$showEmpty = $showEmpty ?? true;

foreach ($docItems as [$docType, $docRecordId]):
    if (! $docRecordId || ! \App\Modules\Document\Services\DocumentTypeRegistry::canGenerate($docType) || ! $avail->has($docType, (int) $companyId)) {
        continue;
    }
    $offered++; ?>
        <li><a class="dropdown-item" target="_blank" rel="noopener" data-erp-form="off" href="<?= site_url('documents/render/' . $docType . '/' . (int) $docRecordId . '?inline=1') ?>"><i class="fas fa-file-pdf me-2 text-muted"></i><?= esc(\App\Modules\Document\Services\DocumentTypeRegistry::label($docType)) ?></a></li>
<?php endforeach;

if ($offered === 0 && $showEmpty): ?>
        <li><span class="dropdown-item-text text-muted small"><i class="far fa-circle-question me-1"></i><?= esc($emptyText ?? 'No documents are configured for this company yet.') ?></span></li>
        <?php if (can('document.edit')): ?>
        <li><a class="dropdown-item small" data-erp-form="off" href="<?= site_url('documents/templates') ?>"><i class="fas fa-file-code me-2 text-muted"></i>Manage document templates</a></li>
        <?php endif; ?>
<?php endif;
