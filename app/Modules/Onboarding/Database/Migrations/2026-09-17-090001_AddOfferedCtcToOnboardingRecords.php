<?php

namespace App\Modules\Onboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The compensation actually stated in the offer letter — distinct from
 * HR\SalaryStructureModel, which is the post-hire payroll configuration
 * (basic/HRA/allowances breakup, keyed by user_id, only exists once a
 * candidate has become a real employee). A candidate at the offer_sent
 * stage has neither a user_id nor an employee_profile_id yet, so the
 * offer letter needs its own field here rather than reusing that table.
 */
class AddOfferedCtcToOnboardingRecords extends Migration
{
    public function up()
    {
        $this->forge->addColumn('onboarding_records', [
            'offered_ctc' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'after' => 'designation'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('onboarding_records', 'offered_ctc');
    }
}
