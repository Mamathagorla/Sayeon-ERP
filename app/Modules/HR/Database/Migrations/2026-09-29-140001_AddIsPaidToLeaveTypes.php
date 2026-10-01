<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Makes "is this leave type paid?" an explicit column instead of the
 * implicit convention scattered across EmployeeController/PayrollController
 * ("annual_quota == 0 means unpaid" — see the comment this replaces in
 * EmployeeController::recordSections()). Backfilled from that exact same
 * rule so existing data's behaviour doesn't change the moment this
 * migrates — only new/edited leave types need the Leave Type form's new
 * checkbox going forward.
 */
class AddIsPaidToLeaveTypes extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_paid', 'leave_types')) {
            $this->forge->addColumn('leave_types', [
                'is_paid' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'annual_quota'],
            ]);

            $this->db->table('leave_types')->where('annual_quota', 0)->update(['is_paid' => 0]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('leave_types', 'is_paid');
    }
}
