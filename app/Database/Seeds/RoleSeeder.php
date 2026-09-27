<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeded roles (assumption — the source spec didn't define roles;
 * see Assumptions §10). Super Admin always bypasses the permission
 * matrix (see PermissionFilter); the rest are granted permissions
 * explicitly via RolePermissionSeeder.
 */
class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Full, unrestricted access to every module.'],
            // Slug stays 'admin' — only the display name changed to
            // "Company Admin" — so the role-rank/scoping checks keyed on
            // slug elsewhere (LeaveController, BaseController::companyScopeFor)
            // don't need to change.
            ['name' => 'Company Admin', 'slug' => 'admin', 'description' => 'Manages one company\'s employees, tasks, meetings, expenses and bills.'],
            ['name' => 'Project Manager', 'slug' => 'manager', 'description' => 'Manages projects, tasks and meetings — no cross-module access.'],
            ['name' => 'Accountant', 'slug' => 'accountant', 'description' => 'Finance-focused access.'],
            ['name' => 'HR Manager', 'slug' => 'hr', 'description' => 'HR-focused access — employees, attendance, leave, payroll.'],
            ['name' => 'Compliance Officer', 'slug' => 'compliance_officer', 'description' => 'Compliance-focused access.'],
            ['name' => 'Employee', 'slug' => 'employee', 'description' => 'Standard staff — works their assigned tasks.'],
        ];

        foreach ($roles as $role) {
            $existing = $this->db->table('roles')->where('slug', $role['slug'])->get()->getRowArray();

            if ($existing === null) {
                $this->db->table('roles')->insert($role + [
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                continue;
            }

            // Upsert: re-running the seeder after a name/description
            // change (e.g. Admin → Company Admin) must actually apply
            // it to rows already in the database, not just skip them.
            if ($existing['name'] !== $role['name'] || $existing['description'] !== $role['description']) {
                $this->db->table('roles')->where('slug', $role['slug'])->update([
                    'name'        => $role['name'],
                    'description' => $role['description'],
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
