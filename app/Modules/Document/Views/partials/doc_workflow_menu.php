<?php
/**
 * Dropdown <li> entries for every document a workflow offers a company, from the ONE central mapping
 * (DocumentTypeRegistry type -> workflow, or a template pinned to a workflow) and the company's ACTIVE templates.
 * Pages never list document types themselves, so a template uploaded under Document Templates shows up in every
 * workflow that offers its type, for that company only, with no per-page setup.
 *
 * Expects:
 *   $workflow   string  onboarding | offboarding | employee | hr | payroll | accounting | purchase | expense
 *   $companyId  int     company of the record being viewed (server-side value, never from the browser)
 *   $context    array   record kind => id for the records this page has
 *                        (onboarding_record, employee_profile, offboarding_record, payslip, salary_structure,
 *                         increment_letter, invoice, bill, purchase_order, expense)
 * Optional:
 *   $except     string[] types the page renders with its own special actions
 *   $showEmpty  bool     (default true)  $emptyText string
 */
$avail  = \App\Modules\Document\Services\DocumentAvailability::shared();
$items  = $avail->items($workflow, (int) $companyId, $context, $except ?? []);
$showEmpty = $showEmpty ?? true;

foreach ($items as $it): ?>
        <li><a class="dropdown-item" target="_blank" rel="noopener" data-erp-form="off" href="<?= site_url('documents/render/' . $it['type'] . '/' . (int) $it['recordId'] . '?inline=1') ?>"><i class="fas fa-file-pdf me-2 text-muted"></i><?= esc($it['label']) ?></a></li>
<?php endforeach;

if (! $items && $showEmpty): ?>
        <li><span class="dropdown-item-text text-muted small"><i class="far fa-circle-question me-1"></i><?= esc($emptyText ?? 'No document templates are active for this company in this area.') ?></span></li>
        <?php if (can('document.edit')): ?>
        <li><a class="dropdown-item small" data-erp-form="off" href="<?= site_url('documents/templates') ?>"><i class="fas fa-file-code me-2 text-muted"></i>Manage document templates</a></li>
        <?php endif; ?>
<?php endif;
