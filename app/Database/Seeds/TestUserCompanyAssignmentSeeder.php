<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Gives every TestUsersSeeder login an employee_profiles row so
 * BaseController::companyScopeFor() has something to resolve at login
 * instead of falling through to "0 = no company assigned". The company
 * for each test user is looked up by slug (companies.slug /
 * departments.slug) — never a hardcoded numeric id — so this stays
 * correct however CompanySeeder happens to have ordered/numbered rows.
 *
 * Two Company Admin logins are deliberately pinned to two *different*
 * companies (festive.admin -> Festive, sayeon.admin -> Sayeon). That's
 * the proof that company scope comes from each user's own
 * employee_profiles row, not from their role — if it were role-derived,
 * both admins would end up seeing the same company.
 *
 * Every one of the five companies (Sanvima Solutions, Sayeon, Festive
 * Retail, Nihira Power & Infra, Intrepid Professionals) is given one
 * login per role (Admin/Manager/Accountant/HR/Compliance Officer/
 * Employee), so each company has a full role roster — the only way to
 * actually log in and see "does this company's Admin see this
 * company's Manager/HR/Accountant/Compliance Officer/Employees, and
 * nothing from any other company" rather than just asserting it from
 * the code.
 *
 * Idempotent: run any time (e.g. after `db:seed TestUsersSeeder`)
 * without duplicating a profile a user already has.
 */
class TestUserCompanyAssignmentSeeder extends Seeder
{
    public function run()
    {
        // email => [company slug, department slug, designation]
        $assignments = [
            // Festive Retail Pvt Ltd
            'festive.admin@sanvima.com'      => ['festive-retail-pvt-ltd', 'admin', 'Company Admin'],
            'festive.accountant@sanvima.com' => ['festive-retail-pvt-ltd', 'accounting', 'Accountant'],
            'festive.manager@sanvima.com'    => ['festive-retail-pvt-ltd', 'operations', 'Project Manager'],
            'festive.hr@sanvima.com'         => ['festive-retail-pvt-ltd', 'hr', 'HR Manager'],
            'festive.compliance@sanvima.com' => ['festive-retail-pvt-ltd', 'compliance', 'Compliance Officer'],
            'festive.employee@sanvima.com'   => ['festive-retail-pvt-ltd', 'operations', 'Employee'],

            // Sayeon
            'sayeon.admin@sanvima.com'       => ['sayeon', 'admin', 'Company Admin'],
            'sayeon.manager@sanvima.com'     => ['sayeon', 'operations', 'Project Manager'],
            'sayeon.employee@sanvima.com'    => ['sayeon', 'operations', 'Employee'],
            'sayeon.accountant@sanvima.com'  => ['sayeon', 'accounting', 'Accountant'],
            'sayeon.hr@sanvima.com'          => ['sayeon', 'hr', 'HR Manager'],
            'sayeon.compliance@sanvima.com'  => ['sayeon', 'compliance', 'Compliance Officer'],

            // Sanvima Solutions
            'sanvima.hr@sanvima.com'         => ['sanvima-solutions', 'hr', 'HR Manager'],
            'sanvima.admin@sanvima.com'      => ['sanvima-solutions', 'admin', 'Company Admin'],
            'sanvima.manager@sanvima.com'    => ['sanvima-solutions', 'operations', 'Project Manager'],
            'sanvima.accountant@sanvima.com' => ['sanvima-solutions', 'accounting', 'Accountant'],
            'sanvima.compliance@sanvima.com' => ['sanvima-solutions', 'compliance', 'Compliance Officer'],
            'sanvima.employee@sanvima.com'   => ['sanvima-solutions', 'operations', 'Employee'],

            // Nihira Power & Infra Pvt Ltd
            'nihira.compliance@sanvima.com'  => ['nihira-power-infra-pvt-ltd', 'compliance', 'Compliance Officer'],
            'nihira.admin@sanvima.com'       => ['nihira-power-infra-pvt-ltd', 'admin', 'Company Admin'],
            'nihira.manager@sanvima.com'     => ['nihira-power-infra-pvt-ltd', 'operations', 'Project Manager'],
            'nihira.accountant@sanvima.com'  => ['nihira-power-infra-pvt-ltd', 'accounting', 'Accountant'],
            'nihira.hr@sanvima.com'          => ['nihira-power-infra-pvt-ltd', 'hr', 'HR Manager'],
            'nihira.employee@sanvima.com'    => ['nihira-power-infra-pvt-ltd', 'operations', 'Employee'],

            // Intrepid Professionals
            'intrepid.admin@sanvima.com'       => ['intrepid-professionals', 'admin', 'Company Admin'],
            'intrepid.manager@sanvima.com'     => ['intrepid-professionals', 'operations', 'Project Manager'],
            'intrepid.accountant@sanvima.com'  => ['intrepid-professionals', 'accounting', 'Accountant'],
            'intrepid.hr@sanvima.com'          => ['intrepid-professionals', 'hr', 'HR Manager'],
            'intrepid.compliance@sanvima.com'  => ['intrepid-professionals', 'compliance', 'Compliance Officer'],
            'intrepid.employee@sanvima.com'    => ['intrepid-professionals', 'operations', 'Employee'],
        ];

        foreach ($assignments as $email => [$companySlug, $departmentSlug, $designation]) {
            $user = $this->db->table('users')->select('id')->where('email', $email)->get()->getRowArray();

            if ($user === null) {
                continue;
            }

            $alreadyAssigned = $this->db->table('employee_profiles')->where('user_id', $user['id'])->countAllResults() > 0;

            if ($alreadyAssigned) {
                continue;
            }

            $company = $this->db->table('companies')->select('id')->where('slug', $companySlug)->get()->getRowArray();

            if ($company === null) {
                continue;
            }

            $department = $this->db->table('departments')->select('id')->where('slug', $departmentSlug)->get()->getRowArray();

            $this->db->table('employee_profiles')->insert([
                'user_id'                => $user['id'],
                'company_id'             => $company['id'],
                'department_id'          => $department['id'] ?? null,
                'employee_code'          => $this->nextEmployeeCode(),
                'designation'            => $designation,
                'reporting_manager_id'   => null,
                'employment_type'        => 'full_time',
                'status'                 => 'active',
                'date_of_joining'        => date('Y-m-d'),
                'date_of_birth'          => null,
                'address'                => null,
                'emergency_contact_name' => null,
                'emergency_contact_phone' => null,
                'created_at'             => date('Y-m-d H:i:s'),
                'updated_at'             => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function nextEmployeeCode(): string
    {
        $last       = $this->db->table('employee_profiles')->select('employee_code')->orderBy('id', 'DESC')->get()->getRowArray();
        $nextNumber = 1;

        if ($last && preg_match('/(\d+)$/', $last['employee_code'], $m)) {
            $nextNumber = (int) $m[1] + 1;
        }

        return 'EMP-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
