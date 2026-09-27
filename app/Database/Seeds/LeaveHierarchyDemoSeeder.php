<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Demonstrates the leave approval chain end-to-end within one company
 * (Sayeon, company_id=2) — Employee -> Manager -> Company Admin -> Super
 * Admin — plus solo-company edge cases where a request has no local
 * Manager/Admin and must fall through to Super Admin. Existing users
 * (ids 1-7) and their company assignments are left untouched; this only
 * adds new employee-level accounts and leave_requests rows.
 * Run with: php spark db:seed LeaveHierarchyDemoSeeder
 */
class LeaveHierarchyDemoSeeder extends Seeder
{
    public function run()
    {
        if ($this->db->table('users')->where('email', 'manager2@sanvima.com')->countAllResults() > 0) {
            return; // already seeded
        }

        $password  = password_hash('Test@123', PASSWORD_DEFAULT);
        $managerRoleId  = $this->db->table('roles')->select('id')->where('slug', 'manager')->get()->getRowArray()['id'];
        $employeeRoleId = $this->db->table('roles')->select('id')->where('slug', 'employee')->get()->getRowArray()['id'];

        // New Manager + a second Employee, both in Sayeon (company_id=2),
        // which already has Test Admin(2)/admin and Test Employee(7)/employee
        // — this completes the full 4-tier chain in one company.
        $this->db->table('users')->insert([
            'name'          => 'Rohan Mehta',
            'email'         => 'manager2@sanvima.com',
            'phone'         => null,
            'password_hash' => $password,
            'role_id'       => $managerRoleId,
            'status'        => 'active',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        $rohanId = $this->db->insertID();

        $this->db->table('users')->insert([
            'name'          => 'Ananya Iyer',
            'email'         => 'employee2@sanvima.com',
            'phone'         => null,
            'password_hash' => $password,
            'role_id'       => $employeeRoleId,
            'status'        => 'active',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        $ananyaId = $this->db->insertID();

        $this->db->table('employee_profiles')->insert([
            'user_id'              => $rohanId,
            'company_id'           => 2,
            'department_id'        => 6, // IT
            'employee_code'        => 'EMP-0008',
            'designation'          => 'Engineering Manager',
            'reporting_manager_id' => 2, // Test Admin
            'employment_type'      => 'full_time',
            'status'               => 'active',
            'date_of_joining'      => date('Y-m-d', strtotime('-400 days')),
            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('employee_profiles')->insert([
            'user_id'              => $ananyaId,
            'company_id'           => 2,
            'department_id'        => 6, // IT
            'employee_code'        => 'EMP-0009',
            'designation'          => 'Software Engineer',
            'reporting_manager_id' => $rohanId,
            'employment_type'      => 'full_time',
            'status'               => 'active',
            'date_of_joining'      => date('Y-m-d', strtotime('-180 days')),
            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);

        // Existing Test Employee (7) now reports to the new manager too,
        // so Rohan has two direct reports to review leave for.
        $this->db->table('employee_profiles')->where('user_id', 7)->update([
            'reporting_manager_id' => $rohanId,
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);

        $leaveTypeIds = array_column($this->db->table('leave_types')->select('id')->get()->getResultArray(), 'id');
        $pick = static fn () => $leaveTypeIds[array_rand($leaveTypeIds)];

        $rows = [];

        // --- Sayeon (company 2): full chain, all pending so they show up
        // as actionable "to review" items for the right approver. ---
        $rows[] = $this->pendingRow($ananyaId, $pick(), 5, 'Family function out of town.');
        $rows[] = $this->pendingRow(7, $pick(), 12, 'Planned vacation.'); // Test Employee -> Rohan
        $rows[] = $this->pendingRow($rohanId, $pick(), 20, 'Personal work — needs Company Admin approval.'); // Rohan -> Test Admin
        $rows[] = $this->pendingRow(2, $pick(), 30, 'Offsite travel — needs Super Admin approval.'); // Test Admin -> Super Admin

        // A rejected one, decided by the manager, for status variety.
        $rows[] = $this->decidedRow($ananyaId, $pick(), 45, 2, $rohanId, 'rejected', '-6 days');

        // --- Solo companies: no local Manager/Admin, so only Super Admin
        // can act — confirms the fallback works and that Sayeon's Manager
        // can't see or approve these (different company). ---
        $rows[] = $this->pendingRow(3, $pick(), 8, 'Festive Retail — no local Admin, Super Admin only.');   // Test Manager, company 3
        $rows[] = $this->pendingRow(6, $pick(), 15, 'Sanvima — no local Manager/Admin here.');              // Compliance Officer, company 1
        $rows[] = $this->decidedRow(4, $pick(), 50, 3, 1, 'approved', '-10 days');  // Test Accountant, company 4, approved by Super Admin
        $rows[] = $this->decidedRow(5, $pick(), 60, 2, 1, 'approved', '-15 days'); // Test HR, company 5, approved by Super Admin

        $this->db->table('leave_requests')->insertBatch($rows);
    }

    private function pendingRow(int $userId, int $leaveTypeId, int $inDays, string $reason): array
    {
        $days      = random_int(1, 3);
        $startDate = date('Y-m-d', strtotime("+{$inDays} days"));
        $endDate   = date('Y-m-d', strtotime($startDate . " +{$days} days"));

        return [
            'user_id'       => $userId,
            'leave_type_id' => $leaveTypeId,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'days'          => $days,
            'reason'        => $reason,
            'status'        => 'pending',
            'approved_by'   => null,
            'approved_at'   => null,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ];
    }

    private function decidedRow(int $userId, int $leaveTypeId, int $startedDaysAgo, int $lengthDays, int $approverId, string $status, string $approvedOffset): array
    {
        $startDate = date('Y-m-d', strtotime("-{$startedDaysAgo} days"));
        $endDate   = date('Y-m-d', strtotime($startDate . " +{$lengthDays} days"));

        return [
            'user_id'       => $userId,
            'leave_type_id' => $leaveTypeId,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'days'          => (float) $lengthDays,
            'reason'        => 'Demo leave request for approval history.',
            'status'        => $status,
            'approved_by'   => $approverId,
            'approved_at'   => date('Y-m-d H:i:s', strtotime($approvedOffset)),
            'created_at'    => date('Y-m-d H:i:s', strtotime($approvedOffset . ' -1 day')),
            'updated_at'    => date('Y-m-d H:i:s', strtotime($approvedOffset)),
        ];
    }
}
