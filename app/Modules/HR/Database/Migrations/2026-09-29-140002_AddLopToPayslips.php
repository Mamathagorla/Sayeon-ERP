<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * "Integrate Attendance and Leave with Payroll" — Loss-of-Pay days/amount
 * computed from that month's attendance.status='absent' rows plus
 * approved leave_requests on an is_paid=0 leave type (see
 * AddIsPaidToLeaveTypes), kept as its own pair of columns rather than
 * folded into the existing `deductions` field so a payslip still shows
 * "manual deductions" and "attendance-driven LOP" as separately readable
 * numbers instead of one opaque total.
 */
class AddLopToPayslips extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('lop_days', 'payslips')) {
            $this->forge->addColumn('payslips', [
                'lop_days'   => ['type' => 'DECIMAL', 'constraint' => '5,1', 'default' => 0, 'after' => 'deductions'],
                'lop_amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'lop_days'],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('payslips', ['lop_days', 'lop_amount']);
    }
}
