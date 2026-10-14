<?php

namespace App\Modules\Document\Services;

/**
 * Turns "this operation cannot complete because these values are missing"
 * into structured guidance: which record each value lives on, which edit
 * page + form field captures it, and where to come back to afterwards.
 *
 * Callers pass machine keys (document placeholder keys, never message
 * text). The key -> (entity, form field) mapping lives in ENTITIES below,
 * so supporting another workflow = add its entity / fields here and call
 * MissingDetails::flash() with that workflow's targets.
 *
 * The payload is flashed as 'missing_details' and rendered by the layout
 * as a message plus a "Complete Missing Details" button per record. The
 * edit page then highlights the fields (?missing=a,b&return=...) and offers
 * a way back to the original operation. Destinations are only offered when
 * the current user holds the edit permission (the destination controller
 * still applies its own authorisation and company scope).
 */
class MissingDetails
{
    /** entity => label, edit/show URL templates ({id}), permission, roles (optional), form fields by key. */
    private const ENTITIES = [
        'onboarding' => [
            'label' => 'Candidate', 'edit' => 'hr/onboarding/{id}/edit', 'show' => 'hr/onboarding/{id}',
            'permission' => 'onboarding.edit', 'roles' => ['hr', 'super_admin'],
            'fields' => [
                'employee_name' => ['candidate_name'], 'employee_email' => ['candidate_email'], 'employee_phone' => ['candidate_phone'],
                'designation' => ['designation'], 'department' => ['department_id'], 'manager_name' => ['reporting_manager_id'],
                'joining_date' => ['joining_date'], 'annual_salary' => ['offered_ctc'], 'annual_salary_in_words' => ['offered_ctc'],
                'employee_address_1' => ['address_line1'], 'employee_address_2' => ['city', 'state', 'postal_code'],
            ],
        ],
        'employee' => [
            'label' => 'Employee', 'edit' => 'hr/employees/{id}/edit', 'show' => 'hr/employees/{id}',
            'permission' => 'employee.edit',
            'fields' => [
                'employee_address_1' => ['address'], 'employee_address_2' => ['address'], 'designation' => ['designation'],
                'department' => ['department_id'], 'manager_name' => ['reporting_manager_id'], 'joining_date' => ['date_of_joining'],
                'employment_type' => ['employment_type'], 'employee_id' => ['employee_code'],
            ],
        ],
        'offboarding' => [
            'label' => 'Exit record', 'edit' => 'hr/offboarding/{id}/edit', 'show' => 'hr/offboarding/{id}',
            'permission' => 'offboarding.edit', 'roles' => ['hr', 'super_admin'],
            'fields' => [
                'last_working_day' => ['last_working_day'], 'exit_date' => ['exit_date'], 'exit_type' => ['exit_type'],
                'notice_period_days' => ['notice_period_days'],
            ],
        ],
        'company' => [
            'label' => 'Company', 'edit' => 'companies/{id}/edit', 'show' => 'companies/{id}', 'permission' => 'company.edit',
            'fields' => [
                'company_name' => ['name'], 'company_address' => ['registered_address'], 'company_gst' => ['gst'],
                'company_pan' => ['pan'], 'company_cin' => ['cin'],
            ],
        ],
        'customer' => [
            'label' => 'Customer', 'edit' => 'customers/{id}/edit', 'show' => 'customers/{id}', 'permission' => 'customer.edit',
            'fields' => [
                'customer_name' => ['name'], 'customer_email' => ['email'], 'customer_phone' => ['phone'],
                'customer_gstin' => ['gstin'], 'customer_address' => ['address'],
            ],
        ],
        'vendor' => [
            'label' => 'Vendor', 'edit' => 'vendors/{id}/edit', 'show' => 'vendors', 'permission' => 'purchase_order.edit',
            'fields' => ['vendor_name' => ['name'], 'vendor_address' => ['address']],
        ],
    ];

    /** Human labels for keys that read badly when derived from the key. */
    private const LABELS = ['employee_address_1' => 'Employee Address 1', 'employee_address_2' => 'Employee Address 2', 'company_gst' => 'Company GST', 'company_pan' => 'Company PAN', 'company_cin' => 'Company CIN', 'customer_gstin' => 'Customer GSTIN', 'vendor_gstin' => 'Vendor GSTIN'];

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    /** "A", "A and B", "A, B and C". */
    public static function sentence(array $keys): string
    {
        $labels = array_map([self::class, 'label'], $keys);
        $last   = array_pop($labels);

        return $labels === [] ? (string) $last : implode(', ', $labels) . ' and ' . $last;
    }

    public static function documentMessage(string $docLabel, array $keys): string
    {
        return $docLabel . ' cannot be generated because ' . self::sentence($keys) . (count($keys) === 1 ? ' is' : ' are') . ' missing.';
    }

    /** Records (entity => id) that feed a document type, in priority order. */
    public static function documentTargets(string $type, int $recordId, int $companyId): array
    {
        $db = db_connect();
        $t  = [];

        switch ($type) {
            case 'offer_letter':
            case 'joining_letter':
                $t['onboarding'] = $recordId;
                break;
            case 'increment_letter':
                $r = $db->table('employee_increment_letters')->select('employee_profile_id')->where('id', $recordId)->get()->getRowArray();
                $t['employee'] = (int) ($r['employee_profile_id'] ?? 0);
                break;
            case 'salary_structure':
            case 'payslip':
                $table = $type === 'payslip' ? 'payslips' : 'salary_structures';
                $r     = $db->table($table)->select('user_id')->where('id', $recordId)->get()->getRowArray();
                $p     = $r ? $db->table('employee_profiles')->select('id')->where('user_id', $r['user_id'])->get()->getRowArray() : null;
                $t['employee'] = (int) ($p['id'] ?? 0);
                break;
            case 'experience_certificate':
            case 'internship_completion_certificate':
            case 'appreciation_letter':
                $t['employee'] = $recordId;
                break;
            case 'exit_letter':
            case 'relieving_letter':
                $r = $db->table('offboarding_records')->select('employee_profile_id')->where('id', $recordId)->get()->getRowArray();
                $t['offboarding'] = $recordId;
                $t['employee']    = (int) ($r['employee_profile_id'] ?? 0);
                break;
            case 'invoice':
                $r = $db->table('invoices')->select('customer_id')->where('id', $recordId)->get()->getRowArray();
                $t['customer'] = (int) ($r['customer_id'] ?? 0);
                break;
            case 'bill':
                $r = $db->table('bills')->select('vendor_id')->where('id', $recordId)->get()->getRowArray();
                $t['vendor'] = (int) ($r['vendor_id'] ?? 0);
                break;
        }

        $t['company'] = $companyId;

        return $t;
    }

    /**
     * Builds the payload: one group per record, each with the fields that
     * belong to it. Keys no known record captures end up in an "other" group
     * that points at the closest valid page (the main record), if any.
     *
     * @param array<string,int> $targets entity => record id (0/absent = unknown)
     */
    public static function build(string $operation, array $keys, array $targets, string $returnUrl): array
    {
        $groups = [];
        $other  = [];

        foreach ($keys as $key) {
            $placed = false;
            foreach ($targets as $entity => $id) {
                $def = self::ENTITIES[$entity] ?? null;
                if ($def === null || ! isset($def['fields'][$key]) || (int) $id < 1) {
                    continue;
                }
                $groups[$entity] ??= ['entity' => $entity, 'title' => $def['label'], 'fields' => [], 'formFields' => []];
                $groups[$entity]['fields'][]     = ['key' => $key, 'label' => self::label($key)];
                $groups[$entity]['formFields']   = array_values(array_unique(array_merge($groups[$entity]['formFields'], $def['fields'][$key])));
                $placed = true;
                break;
            }
            if (! $placed) {
                $other[] = ['key' => $key, 'label' => self::label($key)];
            }
        }

        $out = [];
        foreach ($groups as $entity => $g) {
            $def = self::ENTITIES[$entity];
            $id  = (int) $targets[$entity];
            $g['url'] = self::allowed($def) ? site_url(str_replace('{id}', (string) $id, $def['edit']))
                . '?' . http_build_query(['missing' => implode(',', $g['formFields']), 'return' => $returnUrl, 'op' => $operation]) : null;
            $g['note'] = $g['url'] === null ? 'You do not have permission to edit this ' . strtolower($def['label']) . '. Ask someone with access to complete it.' : null;
            $out[] = $g;
        }

        if ($other !== []) {
            $closest = null;
            foreach ($targets as $entity => $id) {
                $def = self::ENTITIES[$entity] ?? null;
                if ($def !== null && (int) $id > 0 && can(str_replace('.edit', '.view', $def['permission']))) {
                    $closest = [$def, (int) $id];
                    break;
                }
            }
            $out[] = [
                'entity' => 'other', 'title' => 'Other details', 'fields' => $other, 'formFields' => [],
                'url'  => $closest ? site_url(str_replace('{id}', (string) $closest[1], $closest[0]['show'])) : null,
                'note' => 'These values are not entered on a single form. Open the record and complete them there.',
            ];
        }

        return ['operation' => $operation, 'groups' => $out, 'returnUrl' => $returnUrl];
    }

    /** Flashes the payload for the layout to render. $returnUrl defaults to the current request. */
    public static function flash(string $operation, array $keys, array $targets, ?string $returnUrl = null): void
    {
        session()->setFlashdata('missing_details', self::build($operation, $keys, $targets, $returnUrl ?? current_url(true)->__toString()));
    }

    /** Same-origin check for a ?return= value (never an open redirect). */
    public static function safeReturn(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $p = parse_url($url);
        $h = parse_url(base_url());

        return ($p !== false && ($p['host'] ?? $h['host']) === $h['host'] && ! str_starts_with($url, '//')) ? $url : null;
    }

    private static function allowed(array $def): bool
    {
        if (isset($def['roles']) && ! in_array(session('roleSlug'), $def['roles'], true)) {
            return false;
        }

        return can($def['permission']);
    }
}
