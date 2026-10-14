<?php

namespace App\Modules\Document\Services;

use App\Modules\Document\Models\DocumentTemplateModel;

/**
 * Which document actions should an ERP page offer for a given company?
 *
 * The answer comes from exactly one place: that company's ACTIVE templates (the same rows
 * TemplatedDocument uses to generate), minus any whose automatic-detection review is still
 * blocking. A document type with no usable template for the company is simply not offered,
 * so nobody is shown a button that ends in "No active … template is configured". Nothing is
 * stored separately: activating or deactivating a template changes the next page view.
 *
 * The company id must always come from a server-side record the viewer was already authorised
 * to open (candidate, employee profile, exit, invoice …), never from the browser.
 */
class DocumentAvailability
{
    private DocumentTemplateModel $templates;

    /** @var array<int, array<string, array>> company_id => type => template row (per instance) */
    private array $cache = [];

    private static ?self $shared = null;

    public function __construct()
    {
        $this->templates = new DocumentTemplateModel();
    }

    /** One instance per request, for view partials that are rendered many times. */
    public static function shared(): self
    {
        return self::$shared ??= new self();
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    /** @return array<string, array> type => active template row, for templates that can actually generate. */
    public function usable(int $companyId): array
    {
        if (isset($this->cache[$companyId])) {
            return $this->cache[$companyId];
        }

        $out = [];
        foreach ($this->templates->where(['company_id' => $companyId, 'status' => 'active'])->findAll() as $tpl) {
            if (! DocumentTypeRegistry::exists($tpl['document_type']) || TemplatedDocument::blockingFlags($tpl) !== []) {
                continue;
            }
            $out[$tpl['document_type']] = $tpl;
        }

        return $this->cache[$companyId] = $out;
    }

    /** Does this company have a usable active template for the type? (Says nothing about the viewer's permission.) */
    public function has(string $type, int $companyId): bool
    {
        return isset($this->usable($companyId)[$type]);
    }

    /** Is this usable template offered in $workflow? A pinned template only in its own workflow, otherwise by type. */
    private function offeredIn(array $tpl, string $workflow): bool
    {
        $pinned = $tpl['workflow'] ?? null;
        if ($pinned !== null && $pinned !== '') {
            return $pinned === $workflow;
        }

        return in_array($workflow, DocumentTypeRegistry::workflowsOf($tpl['document_type']), true);
    }

    /**
     * Document types a workflow offers for this company: ACTIVE usable template + the central type->workflow
     * mapping (or the template's own pin) + the current user's permission.
     *
     * @return array<string, string> type => label (registry order)
     */
    public function forWorkflow(string $workflow, int $companyId, bool $checkPermission = true): array
    {
        $usable = $this->usable($companyId);
        $out    = [];
        foreach (array_keys(DocumentTypeRegistry::TYPES) as $type) {
            if (isset($usable[$type]) && $this->offeredIn($usable[$type], $workflow) && (! $checkPermission || DocumentTypeRegistry::canGenerate($type))) {
                $out[$type] = DocumentTypeRegistry::label($type);
            }
        }

        return $out;
    }

    /**
     * What a page should render for a workflow: [type, label, recordId] for every available document that can be
     * built from the records the page has. $context maps a record kind (onboarding_record, employee_profile,
     * offboarding_record, payslip, salary_structure, increment_letter, invoice, bill, purchase_order, expense)
     * to that record's id; a type whose record kind is missing/0 on this page is skipped, never guessed.
     *
     * @param array<string,int> $context
     * @param string[]          $except  types the page already renders with its own special actions
     */
    public function items(string $workflow, int $companyId, array $context, array $except = []): array
    {
        $out = [];
        foreach ($this->forWorkflow($workflow, $companyId) as $type => $label) {
            $rid = (int) ($context[DocumentTypeRegistry::TYPES[$type]['record'] ?? ''] ?? 0);
            if ($rid > 0 && ! in_array($type, $except, true)) {
                $out[] = ['type' => $type, 'label' => $label, 'recordId' => $rid];
            }
        }

        return $out;
    }

    /** Company of an employee's user account (for pages that only know the user, e.g. "my payslips"). */
    public function companyOfUser(int $userId): int
    {
        $row = db_connect()->table('employee_profiles')->select('company_id')->where('user_id', $userId)->get()->getRowArray();

        return (int) ($row['company_id'] ?? 0);
    }

    /**
     * Overview for the template-management screen: for each company id, the workflow => [labels]
     * its users will be offered. Pass the viewer's company scope so another company is never listed.
     *
     * @param int[] $companyIds
     */
    public function overview(array $companyIds): array
    {
        $out = [];
        foreach ($companyIds as $cid) {
            $usable = $this->usable((int) $cid);
            $byWorkflow = [];
            foreach (array_keys(DocumentTypeRegistry::TYPES) as $type) {
                if (! isset($usable[$type])) {
                    continue;
                }
                $pinned = $usable[$type]['workflow'] ?? null;
                foreach ($pinned ? [$pinned] : DocumentTypeRegistry::workflowsOf($type) as $wf) {
                    $byWorkflow[$wf][] = DocumentTypeRegistry::label($type) . ' v' . (int) $usable[$type]['version'];
                }
            }
            $out[(int) $cid] = $byWorkflow;
        }

        return $out;
    }
}
