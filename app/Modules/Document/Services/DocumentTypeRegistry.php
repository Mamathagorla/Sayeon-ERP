<?php

namespace App\Modules\Document\Services;

use App\Modules\Accounting\Models\BillItemModel;
use App\Modules\Accounting\Models\BillModel;
use App\Modules\Accounting\Models\InvoiceItemModel;
use App\Modules\Accounting\Models\InvoiceModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\HR\Models\EmployeeIncrementLetterModel;
use App\Modules\HR\Models\EmployeeProfileModel;
use App\Modules\HR\Models\PayslipModel;
use App\Modules\HR\Models\SalaryStructureModel;
use App\Modules\Offboarding\Models\OffboardingRecordModel;
use App\Modules\Onboarding\Models\OnboardingRecordModel;

/**
 * Single place that knows which document types exist, who may generate
 * them, which ERP records feed them, and which placeholders each one
 * exposes. Adding a document type = one entry in TYPES + one
 * resolve<Type>() method; the template UI, generator and renderer pick
 * it up automatically. Placeholders are simply the keys of the array
 * resolve() returns (plus the common company keys) — there is no fixed
 * placeholder list anywhere else.
 *
 * Every records()/resolve() call returns company_id so callers can run
 * the standard outOfScope() check and pick that exact company's
 * template.
 */
class DocumentTypeRegistry
{
    /**
     * label/group: UI. permission/roles: who may open the document through
     * documents/render (existing RBAC slugs). required: placeholders that
     * must have a value before a document can be produced.
     */
    /** Same owner roles the existing Onboarding/Offboarding letters use. */
    public const HR_ROLES = ['hr', 'super_admin'];

    public const TYPES = [
        'offer_letter' => [
            'label' => 'Offer Letter', 'workflows' => ['onboarding'], 'record' => 'onboarding_record', 'group' => 'Onboarding', 'permission' => 'onboarding.edit', 'roles' => self::HR_ROLES,
            'required' => ['employee_name', 'designation', 'joining_date', 'annual_salary'],
        ],
        'joining_letter' => [
            'label' => 'Letter of Appointment / Joining Letter', 'workflows' => ['onboarding'], 'record' => 'onboarding_record', 'group' => 'Onboarding', 'permission' => 'onboarding.edit', 'roles' => self::HR_ROLES,
            'required' => ['employee_name', 'designation', 'joining_date'],
        ],
        'increment_letter' => [
            'label' => 'Increment Letter', 'workflows' => ['hr', 'employee'], 'record' => 'increment_letter', 'group' => 'Onboarding', 'permission' => 'employee.edit', 'roles' => self::HR_ROLES,
            'required' => ['employee_name', 'new_ctc', 'effective_date'],
        ],
        'salary_structure' => [
            'label' => 'Salary Structure', 'workflows' => ['payroll', 'hr', 'employee'], 'record' => 'salary_structure', 'group' => 'Employee', 'permission' => 'payroll.edit',
            'required' => ['employee_name', 'basic'],
        ],
        'payslip' => [
            'label' => 'Payslip', 'workflows' => ['payroll', 'employee'], 'record' => 'payslip', 'group' => 'Employee', 'permission' => 'payroll.edit',
            'required' => ['employee_name', 'period', 'net_pay'],
        ],
        'experience_certificate' => [
            'label' => 'Experience Certificate', 'workflows' => ['offboarding', 'employee'], 'record' => 'employee_profile', 'group' => 'Employee', 'permission' => 'employee.edit', 'roles' => self::HR_ROLES,
            'required' => ['employee_name', 'designation', 'joining_date', 'last_working_day'],
        ],
        'internship_completion_certificate' => [
            'label' => 'Internship Completion Certificate', 'workflows' => ['offboarding', 'employee'], 'record' => 'employee_profile', 'group' => 'Employee', 'permission' => 'employee.edit', 'roles' => self::HR_ROLES,
            'required' => ['employee_name', 'internship_start_date', 'internship_end_date'],
        ],
        'appreciation_letter' => [
            'label' => 'Appreciation Letter', 'workflows' => ['employee', 'hr'], 'record' => 'employee_profile', 'group' => 'Employee', 'permission' => 'employee.edit', 'roles' => self::HR_ROLES,
            'required' => ['employee_name', 'designation'],
        ],
        'exit_letter' => [
            'label' => 'Exit Letter', 'workflows' => ['offboarding'], 'record' => 'offboarding_record', 'group' => 'Offboarding', 'permission' => 'offboarding.edit', 'roles' => self::HR_ROLES,
            'required' => ['employee_name', 'last_working_day'],
        ],
        'relieving_letter' => [
            'label' => 'Relieving Letter', 'workflows' => ['offboarding'], 'record' => 'offboarding_record', 'group' => 'Offboarding', 'permission' => 'offboarding.edit', 'roles' => self::HR_ROLES,
            'required' => ['employee_name', 'designation', 'last_working_day'],
        ],
        'appointment_letter' => [
            'label' => 'Appointment Letter', 'workflows' => ['onboarding'], 'record' => 'onboarding_record', 'group' => 'Onboarding', 'permission' => 'onboarding.edit', 'roles' => self::HR_ROLES,
            'required' => ['employee_name', 'designation', 'joining_date'],
        ],
        'internship_offer_letter' => [
            'label' => 'Internship Offer Letter', 'workflows' => ['onboarding'], 'record' => 'onboarding_record', 'group' => 'Onboarding', 'permission' => 'onboarding.edit', 'roles' => self::HR_ROLES,
            'required' => ['employee_name', 'designation', 'joining_date'],
        ],
        'invoice' => [
            'label' => 'Invoice', 'workflows' => ['accounting'], 'record' => 'invoice', 'group' => 'Accounting', 'permission' => 'invoice.edit',
            'required' => ['invoice_number', 'customer_name', 'total'],
        ],
        'purchase_order' => [
            'label' => 'Purchase Order', 'workflows' => ['purchase'], 'record' => 'purchase_order', 'group' => 'Purchase', 'permission' => 'purchase_order.view',
            'required' => ['po_number', 'vendor_name', 'total'],
        ],
        'expense_report' => [
            'label' => 'Expense Report / Reimbursement', 'workflows' => ['expense'], 'record' => 'expense', 'group' => 'Expense', 'permission' => 'expense.view',
            'required' => ['expense_reference', 'vendor', 'amount'],
        ],
        'bill' => [
            'label' => 'Bill', 'workflows' => ['accounting'], 'record' => 'bill', 'group' => 'Accounting', 'permission' => 'bill.edit',
            'required' => ['bill_number', 'vendor_name', 'total'],
        ],
    ];

    /**
     * Official HR documents (letters, certificates, payslips, salary structure)
     * must come from the employee's own company template: when none is active,
     * generation is blocked instead of falling back to a built-in format.
     */
    public static function isStrict(string $type): bool
    {
        return in_array(self::TYPES[$type]['group'] ?? '', ['Onboarding', 'Employee', 'Offboarding'], true);
    }

    /** Obviously-fake values for every placeholder of a type, for previews when no real record is complete. */
    public function sampleData(string $type): array
    {
        $data = [];
        foreach ($this->fieldKeys($type) as $key) {
            $data[$key] = match (self::kindOf($key)) {
                'date'   => '01/01/2026',
                'amount' => '10,000.00',
                'id'     => 'SAMPLE-001',
                default  => 'Sample ' . str_replace('_', ' ', $key),
            };
        }
        if (in_array($type, ['invoice', 'bill'], true)) {
            $data['items'] = [
                ['sno' => '1', 'description' => 'Sample item one', 'quantity' => '2.00', 'rate' => '1,000.00', 'amount' => '2,000.00'],
                ['sno' => '2', 'description' => 'Sample item two', 'quantity' => '1.00', 'rate' => '500.00', 'amount' => '500.00'],
            ];
        }

        return $data;
    }

    /**
     * "Title Name" when a title is explicitly stored, otherwise just the name.
     * Never guesses a title; never repeats one the name already starts with.
     */
    public static function salutation(string $name, ?string $title = null): string
    {
        $name  = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $title = trim((string) $title);

        if ($title === '' || $name === '') {
            return $name;
        }
        $bare = rtrim($title, '.');
        if (preg_match('/^' . preg_quote($bare, '/') . '\.?\s/i', $name . ' ')) {
            return $name; // the name already carries this title
        }

        return $title . ' ' . $name;
    }

    /** Human names for the workflows (also the order they are listed in). */
    public const WORKFLOW_LABELS = [
        'onboarding' => 'Onboarding', 'offboarding' => 'Offboarding', 'employee' => 'Employee profile', 'hr' => 'HR / Salary revision',
        'payroll' => 'Payroll', 'accounting' => 'Accounting', 'purchase' => 'Purchase', 'expense' => 'Expenses',
    ];

    /**
     * Workflows that offer a type: the defaults in TYPES, replaced by Config\DocumentWorkflows::$overrides when the
     * type is listed there. This is the ONE place pages ask; no page keeps its own list of document types.
     *
     * @return string[]
     */
    public static function workflowsOf(string $type): array
    {
        $override = config('DocumentWorkflows')->overrides[$type] ?? null;

        return is_array($override) && $override !== [] ? array_values($override) : (self::TYPES[$type]['workflows'] ?? []);
    }

    /** @return string[] type keys offered by a workflow (registry order). */
    public static function forWorkflow(string $workflow): array
    {
        return array_values(array_filter(array_keys(self::TYPES), static fn (string $t) => in_array($workflow, self::workflowsOf($t), true)));
    }

    /**
     * Placeholders that prove a template carries record-specific data: the type's first required key
     * (employee_name / invoice_number / bill_number) or, for a person, the greeting forms built from it.
     * A template that prints none of them would come out identical for every record.
     */
    public static function identityPlaceholders(string $type): array
    {
        $key = self::TYPES[$type]['required'][0] ?? '';

        return $key === 'employee_name' ? ['employee_name', 'salutation_name', 'greeting'] : ($key === '' ? [] : [$key]);
    }

    public static function exists(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type]['label'] ?? ucwords(str_replace('_', ' ', $type));
    }

    /** @return array<string,string> type => label, for filters/selects. */
    public static function labels(): array
    {
        return array_map(static fn (array $t) => $t['label'], self::TYPES);
    }

    public static function canGenerate(string $type): bool
    {
        $def = self::TYPES[$type] ?? null;

        if ($def === null || (isset($def['roles']) && ! in_array(session('roleSlug'), $def['roles'], true))) {
            return false;
        }

        return can($def['permission']);
    }

    /**
     * Records of $type that can be picked, limited to $companyScope
     * (null = unrestricted). Each: id, label, company_id, company_name.
     */
    public function records(string $type, ?int $companyScope): array
    {
        $db = db_connect();

        switch ($type) {
            case 'offer_letter':
            case 'joining_letter':
            case 'appointment_letter':
            case 'internship_offer_letter':
                $b = $db->table('onboarding_records r')
                    ->select("r.id, r.company_id, c.name AS company_name, CONCAT(r.candidate_name, ' — ', COALESCE(r.designation, 'No designation')) AS label")
                    ->join('companies c', 'c.id = r.company_id');
                break;
            case 'increment_letter':
                $b = $db->table('employee_increment_letters r')
                    ->select("r.id, r.company_id, c.name AS company_name, CONCAT(u.name, ' — effective ', r.effective_date) AS label")
                    ->join('employee_profiles ep', 'ep.id = r.employee_profile_id')
                    ->join('users u', 'u.id = ep.user_id')
                    ->join('companies c', 'c.id = r.company_id');
                break;
            case 'salary_structure':
                $b = $db->table('salary_structures r')
                    ->select("r.id, ep.company_id, c.name AS company_name, CONCAT(u.name, ' (', COALESCE(ep.employee_code, '-'), ')') AS label")
                    ->join('employee_profiles ep', 'ep.user_id = r.user_id')
                    ->join('users u', 'u.id = r.user_id')
                    ->join('companies c', 'c.id = ep.company_id');
                break;
            case 'payslip':
                $b = $db->table('payslips r')
                    ->select("r.id, ep.company_id, c.name AS company_name, CONCAT(u.name, ' — ', pr.month, '/', pr.year) AS label")
                    ->join('payroll_runs pr', 'pr.id = r.payroll_run_id')
                    ->join('employee_profiles ep', 'ep.user_id = r.user_id')
                    ->join('users u', 'u.id = r.user_id')
                    ->join('companies c', 'c.id = ep.company_id')
                    ->orderBy('pr.year', 'DESC')->orderBy('pr.month', 'DESC');
                break;
            case 'experience_certificate':
            case 'internship_completion_certificate':
            case 'appreciation_letter':
                $b = $db->table('employee_profiles r')
                    ->select("r.id, r.company_id, c.name AS company_name, CONCAT(u.name, ' (', COALESCE(r.employee_code, '-'), ')') AS label")
                    ->join('users u', 'u.id = r.user_id')
                    ->join('companies c', 'c.id = r.company_id');
                break;
            case 'relieving_letter':
            case 'exit_letter':
                $b = $db->table('offboarding_records r')
                    ->select("r.id, r.company_id, c.name AS company_name, CONCAT(u.name, ' — ', COALESCE(r.last_working_day, 'LWD not set')) AS label")
                    ->join('employee_profiles ep', 'ep.id = r.employee_profile_id')
                    ->join('users u', 'u.id = ep.user_id')
                    ->join('companies c', 'c.id = r.company_id');
                break;
            case 'purchase_order':
                $b = $db->table('purchase_orders r')
                    ->select("r.id, r.company_id, c.name AS company_name, CONCAT(r.po_number, ' — ', COALESCE(v.name, '')) AS label")
                    ->join('companies c', 'c.id = r.company_id')
                    ->join('vendors v', 'v.id = r.vendor_id', 'left')
                    ->orderBy('r.id', 'DESC');
                break;
            case 'expense_report':
                $b = $db->table('expenses r')
                    ->select("r.id, r.company_id, c.name AS company_name, CONCAT('EXP-', LPAD(r.id, 4, '0'), ' — ', r.vendor) AS label")
                    ->join('companies c', 'c.id = r.company_id')
                    ->orderBy('r.id', 'DESC');
                break;
            case 'invoice':
                $b = $db->table('invoices r')
                    ->select("r.id, r.company_id, c.name AS company_name, CONCAT(r.invoice_number, ' — ', r.customer_name) AS label")
                    ->join('companies c', 'c.id = r.company_id')
                    ->orderBy('r.id', 'DESC');
                break;
            case 'bill':
                $b = $db->table('bills r')
                    ->select("r.id, r.company_id, c.name AS company_name, CONCAT(r.bill_number, ' — ', r.vendor_name) AS label")
                    ->join('companies c', 'c.id = r.company_id')
                    ->orderBy('r.id', 'DESC');
                break;
            default:
                return [];
        }

        if ($companyScope !== null) {
            // Payslip/salary tables carry no company_id of their own —
            // scope through the employee profile's company instead.
            $col = in_array($type, ['salary_structure', 'payslip'], true) ? 'ep.company_id' : 'r.company_id';
            $b->where($col, $companyScope);
        }

        return $b->limit(500)->get()->getResultArray();
    }

    /**
     * Label vocabulary for automatic PDF field detection: placeholder key =>
     * label texts a template may print next to that value (compared
     * case-insensitively, ignoring dots/colons). Order matters: when one
     * label belongs to two keys the earlier key wins. Keys a document type
     * cannot supply are ignored for that type.
     */
    public const LABELS = [
        'employee_name'   => ['employee name', 'name of employee', 'name of the employee', 'candidate name', 'staff name', 'emp name', 'name'],
        'employee_id'     => ['employee id', 'emp id', 'employee code', 'emp code', 'employee no', 'employee number', 'staff id', 'staff code'],
        'designation'     => ['designation', 'position', 'job title', 'role'],
        'department'      => ['department', 'dept'],
        'manager_name'    => ['reporting manager', 'reports to', 'manager'],
        'joining_date'    => ['date of joining', 'doj', 'joining date', 'date of join', 'joined on', 'start date'],
        'last_working_day' => ['last working day', 'last working date', 'lwd', 'relieving date', 'date of relieving'],
        'internship_start_date' => ['internship start date', 'internship from'],
        'internship_end_date'   => ['internship end date', 'internship to'],
        'effective_date'  => ['effective date', 'effective from', 'wef'],
        'annual_salary'   => ['annual salary', 'annual ctc', 'total ctc', 'ctc', 'annual package'],
        'new_ctc'         => ['new ctc', 'revised ctc'],
        'previous_ctc'    => ['previous ctc', 'old ctc', 'current ctc'],
        'period'          => ['pay period', 'salary period', 'period'],
        'month'           => ['month'],
        'year'            => ['year'],
        'basic'           => ['basic salary', 'basic pay', 'basic'],
        'hra'             => ['house rent allowance', 'hra'],
        'allowances'      => ['special allowance', 'spl allowance', 'splallowance', 'other allowances', 'allowances'],
        'lop_days'        => ['lop days', 'loss of pay days'],
        'lop_amount'      => ['lop amount', 'loss of pay'],
        'gross_pay'       => ['total earnings', 'gross pay', 'gross salary', 'gross earnings', 'gross'],
        'monthly_gross'   => ['monthly gross'],
        'deductions'      => ['total deductions', 'deductions'],
        'net_pay'         => ['net amount', 'net pay', 'net salary', 'net payable', 'take home', 'net amount payable'],
        'monthly_net'     => ['monthly net', 'net monthly'],
        'invoice_number'  => ['invoice number', 'invoice no', 'invoice', 'inv no'],
        'bill_number'     => ['bill number', 'bill no', 'bill', 'vendor invoice no'],
        'invoice_date'    => ['invoice date', 'date of issue', 'issue date', 'date'],
        'bill_date'       => ['bill date', 'date of issue', 'issue date', 'date'],
        'due_date'        => ['due date', 'payment due', 'due on'],
        'customer_name'   => ['customer name', 'billed to', 'bill to', 'client name', 'customer', 'buyer', 'consignee'],
        'customer_gstin'  => ['customer gstin', 'gstin of customer', 'buyer gstin'],
        'customer_address' => ['customer address', 'billing address'],
        'customer_email'  => ['customer email'],
        'customer_phone'  => ['customer phone'],
        'vendor_name'     => ['vendor name', 'supplier name', 'vendor', 'supplier', 'bill from'],
        'vendor_gstin'    => ['vendor gstin', 'supplier gstin'],
        'vendor_address'  => ['vendor address', 'supplier address'],
        'subtotal'        => ['sub total', 'subtotal', 'taxable value', 'taxable amount'],
        'discount'        => ['discount'],
        'cgst'            => ['cgst'],
        'sgst'            => ['sgst'],
        'igst'            => ['igst'],
        'tax'             => ['total tax', 'tax amount', 'tax', 'gst'],
        'total'           => ['grand total', 'total amount', 'invoice total', 'amount due', 'total'],
        'total_in_words'  => ['amount in words', 'total in words'],
        'current_date'    => ['dated', 'date'],
    ];

    /** Value shape expected for a key; detection only accepts a matching neighbour. */
    public static function kindOf(string $key): string
    {
        if (preg_match('/(_date|_day)$|^(joining_date|current_date)$/', $key)) {
            return 'date';
        }
        if (in_array($key, ['employee_id', 'invoice_number', 'bill_number'], true)) {
            return 'id';
        }
        if (preg_match('/^(annual_salary|new_ctc|previous_ctc|basic|hra|allowances|deductions|gross_pay|monthly_gross|monthly_net|net_pay|lop_amount|subtotal|discount|cgst|sgst|igst|tax|total)$/', $key)) {
            return 'amount';
        }

        return 'text';
    }

    /** Everything the browser-side detector needs, per document type. */
    public function detectionDefs(): array
    {
        $out = [];
        foreach (self::TYPES as $type => $def) {
            $keys   = $this->fieldKeys($type);
            $labels = [];
            foreach (self::LABELS as $key => $syns) {
                if (in_array($key, $keys, true)) {
                    $labels[$key] = ['kind' => self::kindOf($key), 'labels' => $syns];
                }
            }
            $out[$type] = [
                'keys'     => $keys,
                'required' => $def['required'],
                'labels'   => $labels,
                // payslip-style tables: clear sample amounts the ERP has no figure for
                'blankUnknownAmounts' => in_array($type, ['payslip', 'salary_structure'], true),
                // a PDF cannot repeat line-item rows
                'hasLineItems'        => in_array($type, ['invoice', 'bill'], true),
                'identity'            => self::identityPlaceholders($type),
            ];
        }

        return $out;
    }

    /**
     * Placeholder keys a type can supply (taken from a real record so new
     * resolver keys appear automatically). Used by the PDF field mapper.
     */
    public function fieldKeys(string $type): array
    {
        $keys = self::TYPES[$type]['required'] ?? [];
        $rec  = $this->records($type, null)[0] ?? null;
        $ctx  = $rec ? $this->resolve($type, (int) $rec['id']) : null;

        foreach ($ctx['data'] ?? [] as $k => $v) {
            if (is_scalar($v)) {
                $keys[] = $k;
            }
        }

        // Present whenever a person's name is (resolve() derives them), even if no record exists yet to sample from.
        if (in_array('employee_name', $keys, true)) {
            array_push($keys, 'employee_title', 'salutation_name', 'greeting');
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    /**
     * Loads one record and maps it to placeholders.
     *
     * @return array{company_id:int, title:string, email:?string, data:array}|null null = not found
     */
    public function resolve(string $type, int $id): ?array
    {
        $method = 'resolve' . str_replace(' ', '', ucwords(str_replace('_', ' ', $type)));

        if (! isset(self::TYPES[$type]) || ! method_exists($this, $method)) {
            return null;
        }

        $result = $this->{$method}($id);

        if ($result === null) {
            return null;
        }

        $company = (new CompanyModel())->find($result['company_id']) ?? [];
        $result['data'] = array_merge($this->companyData($company), $result['data']);

        // Greeting keys for every type that has a person: built ONLY from the stored
        // name and an explicitly stored title (none is ever inferred from the name or gender).
        if (array_key_exists('employee_name', $result['data'])) {
            $title = (string) ($result['data']['employee_title'] ?? '');
            $result['data']['employee_title']  = $title;
            $result['data']['salutation_name'] = self::salutation((string) $result['data']['employee_name'], $title);
            $result['data']['greeting']        = $result['data']['salutation_name'] === '' ? '' : 'Dear ' . $result['data']['salutation_name'];
        }

        return $result;
    }

    // ---- common providers ------------------------------------------------

    /** A company's own details (name, address, GST/PAN/CIN, today's date): previews show the owning company, never another one. */
    public function companyDetails(int $companyId): array
    {
        return $this->companyData((new CompanyModel())->find($companyId) ?? []);
    }

    private function companyData(array $company): array
    {
        return [
            'company_name'    => $company['name'] ?? '',
            'company_address' => $company['registered_address'] ?? '',
            'company_gst'     => $company['gst'] ?? '',
            'company_pan'     => $company['pan'] ?? '',
            'company_cin'     => $company['cin'] ?? '',
            'current_date'    => erp_date(date('Y-m-d')),
        ];
    }

    private function employeeData(?array $emp): array
    {
        if ($emp === null) {
            return [];
        }

        return [
            'employee_name'   => $emp['user_name'] ?? '',
            'employee_title'  => trim((string) ($emp['salutation'] ?? $emp['title'] ?? '')),
            'employee_id'     => $emp['employee_code'] ?? '',
            'employee_email'  => $emp['user_email'] ?? '',
            'employee_address_1' => trim((string) ($emp['address'] ?? '')),
            'employee_address_2' => '',
            'designation'     => $emp['designation'] ?? '',
            'department'      => $emp['department_name'] ?? '',
            'manager_name'    => $emp['manager_name'] ?? '',
            'joining_date'    => $this->date($emp['date_of_joining'] ?? null),
            'employment_type' => ucwords(str_replace('_', ' ', (string) ($emp['employment_type'] ?? ''))),
        ];
    }

    private function profileById(int $profileId): ?array
    {
        return (new EmployeeProfileModel())->filtered()->where('employee_profiles.id', $profileId)->first();
    }

    private function profileByUser(int $userId): ?array
    {
        return (new EmployeeProfileModel())->filtered()->where('employee_profiles.user_id', $userId)->first();
    }

    private function date($value): string
    {
        return empty($value) ? '' : erp_date($value);
    }

    private function money($value): string
    {
        return ($value === null || $value === '') ? '' : erp_number((float) $value, 2);
    }

    // ---- per-type resolvers ---------------------------------------------

    private function resolveOfferLetter(int $id): ?array
    {
        $r = (new OnboardingRecordModel())->withRelations($id);

        return $r === null ? null : [
            'company_id' => (int) $r['company_id'],
            'title'      => 'Offer Letter — ' . $r['candidate_name'],
            'email'      => $r['candidate_email'] ?? null,
            'data'       => $this->onboardingData($r),
        ];
    }

    private function resolveAppointmentLetter(int $id): ?array
    {
        return $this->onboardingLetter($id, 'Appointment Letter');
    }

    private function resolveInternshipOfferLetter(int $id): ?array
    {
        return $this->onboardingLetter($id, 'Internship Offer Letter');
    }

    private function onboardingLetter(int $id, string $label): ?array
    {
        $r = (new OnboardingRecordModel())->withRelations($id);

        return $r === null ? null : [
            'company_id' => (int) $r['company_id'],
            'title'      => $label . ' — ' . $r['candidate_name'],
            'email'      => $r['candidate_email'] ?? null,
            'data'       => $this->onboardingData($r),
        ];
    }

    private function resolvePurchaseOrder(int $id): ?array
    {
        $o = (new \App\Modules\Purchase\Models\PurchaseOrderModel())->withRelations($id);
        if ($o === null) {
            return null;
        }
        $items = [];
        foreach ((new \App\Modules\Purchase\Models\PurchaseOrderItemModel())->forOrder($id) as $n => $row) {
            $items[] = ['sno' => (string) ($n + 1), 'description' => $row['item_name'], 'quantity' => erp_number((float) $row['quantity'], 2),
                'rate' => $this->money($row['unit_price']), 'amount' => $this->money($row['line_total'])];
        }

        return [
            'company_id' => (int) $o['company_id'],
            'title'      => 'Purchase Order ' . $o['po_number'],
            'email'      => null,
            'data'       => [
                'po_number'       => $o['po_number'],
                'order_date'      => $this->date($o['order_date'] ?? null),
                'expected_date'   => $this->date($o['expected_date'] ?? null),
                'vendor_name'     => $o['vendor_name'] ?? '',
                'requested_by'    => $o['requester_name'] ?? '',
                'approval_status' => ucfirst((string) ($o['approval_status'] ?? '')),
                'total'           => $this->money($o['amount']),
                'total_in_words'  => amount_in_words((float) $o['amount']),
                'notes'           => $o['notes'] ?? '',
                'items'           => $items,
            ],
        ];
    }

    private function resolveExpenseReport(int $id): ?array
    {
        $e = (new \App\Modules\Expense\Models\ExpenseModel())->filtered()->where('expenses.id', $id)->first();
        if ($e === null) {
            return null;
        }
        $ref = 'EXP-' . str_pad((string) $e['id'], 4, '0', STR_PAD_LEFT);

        return [
            'company_id' => (int) $e['company_id'],
            'title'      => 'Expense ' . $ref,
            'email'      => null,
            'data'       => [
                'expense_reference' => $ref,
                'vendor'            => $e['vendor'] ?? '',
                'category'          => $e['category'] ?? '',
                'billing_cycle'     => ucfirst((string) ($e['billing_cycle'] ?? '')),
                'amount'            => $this->money($e['amount']),
                'total'             => $this->money($e['amount']),
                'renewal_date'      => $this->date($e['renewal_date'] ?? null),
                'payment_method'    => $e['payment_method'] ?? '',
                'status'            => ucfirst((string) ($e['status'] ?? '')),
                'notes'             => $e['notes'] ?? '',
            ],
        ];
    }

    private function resolveJoiningLetter(int $id): ?array
    {
        $r = (new OnboardingRecordModel())->withRelations($id);

        return $r === null ? null : [
            'company_id' => (int) $r['company_id'],
            'title'      => 'Joining Letter — ' . $r['candidate_name'],
            'email'      => $r['candidate_email'] ?? null,
            'data'       => $this->onboardingData($r),
        ];
    }

    private function onboardingData(array $r): array
    {
        return [
            'employee_name'   => $r['candidate_name'],
            'employee_title'  => trim((string) ($r['salutation'] ?? $r['title'] ?? '')),
            'employee_id'     => $r['employee_code'] ?? '',
            'employee_email'  => $r['candidate_email'] ?? '',
            'employee_phone'  => $r['candidate_phone'] ?? '',
            'designation'     => $r['designation'] ?? '',
            'department'      => $r['department_name'] ?? '',
            'manager_name'    => $r['manager_name'] ?? '',
            'joining_date'    => $this->date($r['joining_date'] ?? null),
            'employment_type' => ucwords(str_replace('_', ' ', (string) ($r['employment_type'] ?? ''))),
            'annual_salary'   => $this->money($r['offered_ctc'] ?? null),
            'annual_salary_in_words' => isset($r['offered_ctc']) ? amount_in_words((float) $r['offered_ctc']) : '',
            'reference_number' => 'ONB-' . str_pad((string) $r['id'], 4, '0', STR_PAD_LEFT),
            // Candidate's stored postal address, split the way letters print it:
            //   line 1: street / area      line 2: city, state - postal code
            'employee_address_1' => implode(', ', array_filter([trim((string) ($r['address_line1'] ?? '')), trim((string) ($r['address_line2'] ?? ''))], static fn ($v) => $v !== '')),
            'employee_address_2' => implode(' - ', array_filter([
                implode(', ', array_filter([trim((string) ($r['city'] ?? '')), trim((string) ($r['state'] ?? ''))], static fn ($v) => $v !== '')),
                trim((string) ($r['postal_code'] ?? '')),
            ], static fn ($v) => $v !== '')),
        ];
    }

    private function resolveIncrementLetter(int $id): ?array
    {
        $r = (new EmployeeIncrementLetterModel())->withRelations($id);
        if ($r === null) {
            return null;
        }
        $emp = $this->profileById((int) $r['employee_profile_id']);

        return [
            'company_id' => (int) $r['company_id'],
            'title'      => 'Increment Letter — ' . $r['user_name'],
            'email'      => $r['user_email'] ?? null,
            'data'       => $this->employeeData($emp) + [
                'previous_ctc'   => $this->money($r['previous_ctc']),
                'new_ctc'        => $this->money($r['new_ctc']),
                'annual_salary'  => $this->money($r['new_ctc']),
                'effective_date' => $this->date($r['effective_date']),
                'reason'         => $r['reason'] ?? '',
                'issued_by'      => $r['issued_by_name'] ?? '',
            ],
        ];
    }

    private function resolveSalaryStructure(int $id): ?array
    {
        $s = (new SalaryStructureModel())->find($id);
        if ($s === null || ($emp = $this->profileByUser((int) $s['user_id'])) === null) {
            return null;
        }
        $gross = (float) $s['basic'] + (float) $s['hra'] + (float) $s['allowances'];

        return [
            'company_id' => (int) $emp['company_id'],
            'title'      => 'Salary Structure — ' . $emp['user_name'],
            'email'      => $emp['user_email'] ?? null,
            'data'       => $this->employeeData($emp) + [
                'basic'          => $this->money($s['basic']),
                'hra'            => $this->money($s['hra']),
                'allowances'     => $this->money($s['allowances']),
                'deductions'     => $this->money($s['deductions']),
                'monthly_gross'  => $this->money($gross),
                'monthly_net'    => $this->money($gross - (float) $s['deductions']),
                'annual_salary'  => $this->money($gross * 12),
                'effective_from' => $this->date($s['effective_from']),
            ],
        ];
    }

    private function resolvePayslip(int $id): ?array
    {
        $p = (new PayslipModel())->withRelations($id);
        if ($p === null || ($emp = $this->profileByUser((int) $p['user_id'])) === null) {
            return null;
        }
        $months = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        return [
            'company_id' => (int) $emp['company_id'],
            'title'      => 'Payslip — ' . $p['user_name'] . ' ' . ($months[(int) $p['month']] ?? $p['month']) . ' ' . $p['year'],
            'email'      => $emp['user_email'] ?? null,
            'data'       => $this->employeeData($emp) + [
                'month'      => $months[(int) $p['month']] ?? (string) $p['month'],
                'year'       => (string) $p['year'],
                'period'     => ($months[(int) $p['month']] ?? $p['month']) . ' ' . $p['year'],
                'basic'      => $this->money($p['basic']),
                'hra'        => $this->money($p['hra']),
                'allowances' => $this->money($p['allowances']),
                'deductions' => $this->money($p['deductions']),
                'lop_days'   => (string) ($p['lop_days'] ?? '0'),
                'lop_amount' => $this->money($p['lop_amount'] ?? 0),
                'gross_pay'  => $this->money($p['gross']),
                'net_pay'    => $this->money($p['net']),
                'net_pay_in_words' => amount_in_words((float) $p['net']),
            ],
        ];
    }

    private function resolveExperienceCertificate(int $id): ?array
    {
        $emp = $this->profileById($id);
        if ($emp === null) {
            return null;
        }
        // Latest exit record, if any, supplies the last working day;
        // otherwise it's a "missing field" the user can type in.
        $exit = db_connect()->table('offboarding_records')
            ->where('employee_profile_id', $id)->where('status !=', OffboardingRecordModel::RESCINDED)
            ->orderBy('id', 'DESC')->get()->getRowArray();

        return [
            'company_id' => (int) $emp['company_id'],
            'title'      => 'Experience Certificate — ' . $emp['user_name'],
            'email'      => $emp['user_email'] ?? null,
            'data'       => $this->employeeData($emp) + [
                'last_working_day' => $this->date($exit['last_working_day'] ?? null),
            ],
        ];
    }

    private function resolveInternshipCompletionCertificate(int $id): ?array
    {
        $r = $this->resolveExperienceCertificate($id);
        if ($r === null) {
            return null;
        }
        $r['title'] = 'Internship Completion Certificate — ' . ($r['data']['employee_name'] ?? '');
        // Start = joining date; end = last working day when an exit record
        // exists, otherwise typed in on the generate screen.
        $r['data']['internship_start_date'] = $r['data']['joining_date'] ?? '';
        $r['data']['internship_end_date']   = $r['data']['last_working_day'] ?? '';

        return $r;
    }

    private function resolveAppreciationLetter(int $id): ?array
    {
        $emp = $this->profileById($id);

        return $emp === null ? null : [
            'company_id' => (int) $emp['company_id'],
            'title'      => 'Appreciation Letter — ' . $emp['user_name'],
            'email'      => $emp['user_email'] ?? null,
            'data'       => $this->employeeData($emp),
        ];
    }

    private function resolveExitLetter(int $id): ?array
    {
        $r = $this->resolveRelievingLetter($id);
        if ($r !== null) {
            $r['title'] = 'Exit Letter — ' . ($r['data']['employee_name'] ?? '');
        }

        return $r;
    }

    private function resolveRelievingLetter(int $id): ?array
    {
        $r = (new OffboardingRecordModel())->withRelations($id);
        if ($r === null) {
            return null;
        }
        $emp = $this->profileById((int) $r['employee_profile_id']);

        return [
            'company_id' => (int) $r['company_id'],
            'title'      => 'Relieving Letter — ' . $r['employee_name'],
            'email'      => $emp['user_email'] ?? null,
            'data'       => $this->employeeData($emp) + [
                'last_working_day'   => $this->date($r['last_working_day'] ?? null),
                'exit_date'          => $this->date($r['exit_date'] ?? null),
                'exit_type'          => ucwords(str_replace('_', ' ', (string) ($r['exit_type'] ?? ''))),
                'notice_period_days' => (string) ($r['notice_period_days'] ?? ''),
            ],
        ];
    }

    private function resolveInvoice(int $id): ?array
    {
        $i = (new InvoiceModel())->withRelations($id);
        if ($i === null) {
            return null;
        }
        $items = [];
        foreach ((new InvoiceItemModel())->forInvoice($id) as $n => $row) {
            $items[] = [
                'sno' => (string) ($n + 1), 'description' => $row['description'],
                'quantity' => erp_number((float) $row['quantity'], 2), 'rate' => $this->money($row['rate']), 'amount' => $this->money($row['amount']),
            ];
        }

        return [
            'company_id' => (int) $i['company_id'],
            'title'      => 'Invoice ' . $i['invoice_number'],
            'email'      => $i['customer_email'] ?? null,
            'data'       => [
                'invoice_number'   => $i['invoice_number'],
                'invoice_date'     => $this->date($i['issue_date'] ?? null),
                'due_date'         => $this->date($i['due_date'] ?? null),
                'customer_name'    => $i['customer_name'],
                'customer_email'   => $i['customer_email'] ?? '',
                'customer_phone'   => $i['customer_phone'] ?? '',
                'customer_gstin'   => $i['customer_gstin'] ?? '',
                'customer_address' => $i['customer_address'] ?? '',
                'subtotal'         => $this->money($i['subtotal']),
                'discount'         => $this->money($i['discount_amount']),
                'cgst'             => $this->money($i['cgst_amount']),
                'sgst'             => $this->money($i['sgst_amount']),
                'igst'             => $this->money($i['igst_amount']),
                'tax'              => $this->money((float) $i['cgst_amount'] + (float) $i['sgst_amount'] + (float) $i['igst_amount']),
                'total'            => $this->money($i['amount']),
                'total_in_words'   => amount_in_words((float) $i['amount']),
                'status'           => ucfirst((string) $i['status']),
                'notes'            => $i['notes'] ?? '',
                'items'            => $items,
            ],
        ];
    }

    private function resolveBill(int $id): ?array
    {
        $b = (new BillModel())->withRelations($id);
        if ($b === null) {
            return null;
        }
        $items = [];
        foreach ((new BillItemModel())->forBill($id) as $n => $row) {
            $items[] = [
                'sno' => (string) ($n + 1), 'description' => $row['description'],
                'quantity' => erp_number((float) $row['quantity'], 2), 'rate' => $this->money($row['rate']), 'amount' => $this->money($row['amount']),
            ];
        }

        return [
            'company_id' => (int) $b['company_id'],
            'title'      => 'Bill ' . $b['bill_number'],
            'email'      => null,
            'data'       => [
                'bill_number'    => $b['bill_number'],
                'bill_date'      => $this->date($b['issue_date'] ?? null),
                'due_date'       => $this->date($b['due_date'] ?? null),
                'vendor_name'    => $b['vendor_name'],
                'vendor_gstin'   => $b['vendor_gstin'] ?? '',
                'vendor_address' => $b['vendor_address'] ?? '',
                'subtotal'       => $this->money($b['subtotal']),
                'discount'       => $this->money($b['discount_amount']),
                'cgst'           => $this->money($b['cgst_amount']),
                'sgst'           => $this->money($b['sgst_amount']),
                'igst'           => $this->money($b['igst_amount']),
                'tax'            => $this->money((float) $b['cgst_amount'] + (float) $b['sgst_amount'] + (float) $b['igst_amount']),
                'total'          => $this->money($b['amount']),
                'total_in_words' => amount_in_words((float) $b['amount']),
                'status'         => ucfirst((string) $b['status']),
                'notes'          => $b['notes'] ?? '',
                'items'          => $items,
            ],
        ];
    }
}
