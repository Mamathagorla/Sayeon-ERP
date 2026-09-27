<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Master seeder — run with: php spark db:seed DatabaseSeeder
 * Order matters: roles/permissions before role_permissions,
 * default admin before companies (companies.owner_id references it).
 */
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(RolePermissionSeeder::class);
        $this->call(DefaultAdminSeeder::class);
        $this->call(TestUsersSeeder::class);
        $this->call(\App\Modules\Department\Database\Seeds\DepartmentSeeder::class);
        $this->call(\App\Modules\Company\Database\Seeds\CompanySeeder::class);
        $this->call(\App\Modules\Compliance\Database\Seeds\ComplianceTypeSeeder::class);
        $this->call(\App\Modules\HR\Database\Seeds\LeaveTypeSeeder::class);
        $this->call(\App\Modules\Expense\Database\Seeds\ExpenseCategorySeeder::class);
        // Needs users/companies/departments already seeded above.
        $this->call(TestUserCompanyAssignmentSeeder::class);
    }
}
