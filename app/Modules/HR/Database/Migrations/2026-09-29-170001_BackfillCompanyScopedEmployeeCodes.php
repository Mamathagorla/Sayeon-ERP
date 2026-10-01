<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Renumbers every existing employee_profiles row into the new
 * company-prefixed format (e.g. 'SAY001'), replacing the old shared
 * 'EMP-####' sequence — part of the explicit "short, company-specific ID
 * system" request. Ordered by id within each company so the renumbering
 * is stable/reproducible and existing employees keep the same relative
 * seniority ordering they already had. This only rewrites the display
 * value in employee_code; every relationship elsewhere (leave, payroll,
 * attendance, onboarding, etc.) is keyed on employee_profiles.id /
 * users.id, never on this string, so nothing else breaks.
 */
class BackfillCompanyScopedEmployeeCodes extends Migration
{
    public function up()
    {
        $companies = $this->db->table('companies')->select('id, short_code')->where('short_code IS NOT NULL')->get()->getResultArray();

        foreach ($companies as $company) {
            $rows = $this->db->table('employee_profiles')
                ->select('id')
                ->where('company_id', $company['id'])
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            $n = 1;
            foreach ($rows as $row) {
                $code = $company['short_code'] . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
                $this->db->table('employee_profiles')->where('id', $row['id'])->update(['employee_code' => $code]);
                $n++;
            }
        }
    }

    public function down()
    {
        // Renumbering is one-directional by design (see class docblock) —
        // the original EMP-#### values aren't recoverable here since
        // nothing preserved them.
    }
}
