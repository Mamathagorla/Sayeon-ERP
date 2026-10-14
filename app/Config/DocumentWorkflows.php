<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Which ERP workflow(s) offer each document type.
 *
 * The defaults live in App\Modules\Document\Services\DocumentTypeRegistry::TYPES ('workflows'). List a type here
 * only to CHANGE where it is offered; it replaces that type's default list. Workflow names:
 *   onboarding, offboarding, employee (employee profile), hr, payroll, accounting, purchase, expense
 *
 * Example: show the Internship Completion Certificate on the employee profile only, not in Offboarding:
 *   'internship_completion_certificate' => ['employee'],
 *
 * A single uploaded template can also be pinned to one workflow when it is uploaded (Document Templates →
 * "Show in workflow"); that applies to that template only.
 */
class DocumentWorkflows extends BaseConfig
{
    /** @var array<string, string[]> */
    public array $overrides = [];
}
