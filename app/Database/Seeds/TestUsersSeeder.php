<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * One login per non-super-admin role, for exercising RBAC permission
 * boundaries in Phase 1. All share the same password so they're easy
 * to cycle through manually. Not meant for production data.
 */
class TestUsersSeeder extends Seeder
{
    public function run()
    {
        $password = password_hash('Test@123', PASSWORD_DEFAULT);

        $users = [
            // Festive Retail and Sayeon each get one login per role (see
            // TestUserCompanyAssignmentSeeder for the company mapping) so
            // a single company can be logged into as every role at once —
            // that's what proves a Company Admin genuinely sees every
            // other role's data within their own company, not just their
            // own, and that the same role logging in for a *different*
            // company sees a completely different, non-overlapping set of
            // records. Every login follows the standardized
            // (company-slug).role@sanvima.com format (super_admin's own
            // admin@sanvima.com is the one deliberate exception — it
            // isn't company-scoped).
            ['name' => 'Test Admin', 'email' => 'festive.admin@sanvima.com', 'role_slug' => 'admin'],
            ['name' => 'Test Admin 2', 'email' => 'sayeon.admin@sanvima.com', 'role_slug' => 'admin'],
            ['name' => 'Test Manager', 'email' => 'sayeon.manager@sanvima.com', 'role_slug' => 'manager'],
            ['name' => 'Test Accountant', 'email' => 'festive.accountant@sanvima.com', 'role_slug' => 'accountant'],
            ['name' => 'Test HR', 'email' => 'sanvima.hr@sanvima.com', 'role_slug' => 'hr'],
            ['name' => 'Test Compliance Officer', 'email' => 'nihira.compliance@sanvima.com', 'role_slug' => 'compliance_officer'],
            ['name' => 'Test Employee', 'email' => 'sayeon.employee@sanvima.com', 'role_slug' => 'employee'],

            // Fills out Festive Retail's roster (already has festive.admin/
            // Company Admin and festive.accountant/Accountant above).
            ['name' => 'Festive Manager', 'email' => 'festive.manager@sanvima.com', 'role_slug' => 'manager'],
            ['name' => 'Festive HR', 'email' => 'festive.hr@sanvima.com', 'role_slug' => 'hr'],
            ['name' => 'Festive Compliance Officer', 'email' => 'festive.compliance@sanvima.com', 'role_slug' => 'compliance_officer'],
            ['name' => 'Festive Employee', 'email' => 'festive.employee@sanvima.com', 'role_slug' => 'employee'],

            // Fills out Sayeon's roster (already has sayeon.admin/Company
            // Admin, sayeon.manager/Manager and sayeon.employee/Employee
            // above).
            ['name' => 'Sayeon Accountant', 'email' => 'sayeon.accountant@sanvima.com', 'role_slug' => 'accountant'],
            ['name' => 'Sayeon HR', 'email' => 'sayeon.hr@sanvima.com', 'role_slug' => 'hr'],
            ['name' => 'Sayeon Compliance Officer', 'email' => 'sayeon.compliance@sanvima.com', 'role_slug' => 'compliance_officer'],

            // Fills out Sanvima Solutions' roster (already has sanvima.hr/
            // HR Manager above).
            ['name' => 'Sanvima Admin', 'email' => 'sanvima.admin@sanvima.com', 'role_slug' => 'admin'],
            ['name' => 'Sanvima Manager', 'email' => 'sanvima.manager@sanvima.com', 'role_slug' => 'manager'],
            ['name' => 'Sanvima Accountant', 'email' => 'sanvima.accountant@sanvima.com', 'role_slug' => 'accountant'],
            ['name' => 'Sanvima Compliance Officer', 'email' => 'sanvima.compliance@sanvima.com', 'role_slug' => 'compliance_officer'],
            ['name' => 'Sanvima Employee', 'email' => 'sanvima.employee@sanvima.com', 'role_slug' => 'employee'],

            // Fills out Nihira Power & Infra's roster (already has
            // nihira.compliance/Compliance Officer above).
            ['name' => 'Nihira Admin', 'email' => 'nihira.admin@sanvima.com', 'role_slug' => 'admin'],
            ['name' => 'Nihira Manager', 'email' => 'nihira.manager@sanvima.com', 'role_slug' => 'manager'],
            ['name' => 'Nihira Accountant', 'email' => 'nihira.accountant@sanvima.com', 'role_slug' => 'accountant'],
            ['name' => 'Nihira HR', 'email' => 'nihira.hr@sanvima.com', 'role_slug' => 'hr'],
            ['name' => 'Nihira Employee', 'email' => 'nihira.employee@sanvima.com', 'role_slug' => 'employee'],

            // Intrepid Professionals had no test logins at all — full
            // roster, all six roles.
            ['name' => 'Intrepid Admin', 'email' => 'intrepid.admin@sanvima.com', 'role_slug' => 'admin'],
            ['name' => 'Intrepid Manager', 'email' => 'intrepid.manager@sanvima.com', 'role_slug' => 'manager'],
            ['name' => 'Intrepid Accountant', 'email' => 'intrepid.accountant@sanvima.com', 'role_slug' => 'accountant'],
            ['name' => 'Intrepid HR', 'email' => 'intrepid.hr@sanvima.com', 'role_slug' => 'hr'],
            ['name' => 'Intrepid Compliance Officer', 'email' => 'intrepid.compliance@sanvima.com', 'role_slug' => 'compliance_officer'],
            ['name' => 'Intrepid Employee', 'email' => 'intrepid.employee@sanvima.com', 'role_slug' => 'employee'],
        ];

        foreach ($users as $user) {
            $exists = $this->db->table('users')->where('email', $user['email'])->countAllResults() > 0;

            if ($exists) {
                continue;
            }

            $role = $this->db->table('roles')->select('id')->where('slug', $user['role_slug'])->get()->getRowArray();

            if ($role === null) {
                continue;
            }

            $this->db->table('users')->insert([
                'name'          => $user['name'],
                'email'         => $user['email'],
                'phone'         => null,
                'password_hash' => $password,
                'role_id'       => $role['id'],
                'status'        => 'active',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
