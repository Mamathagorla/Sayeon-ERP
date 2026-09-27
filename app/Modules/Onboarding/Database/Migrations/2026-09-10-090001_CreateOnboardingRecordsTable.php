<?php

namespace App\Modules\Onboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOnboardingRecordsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'            => ['type' => 'INT', 'unsigned' => true],
            // Candidate identity lives here (not on `users`) because most
            // of the pipeline — Selected through Ready to Join — happens
            // before the person has any system login. `employee_profile_id`
            // below is the bridge to the real employee record once they
            // actually join, so nothing is duplicated once that happens.
            'candidate_name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'candidate_email'       => ['type' => 'VARCHAR', 'constraint' => 190],
            'candidate_phone'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'department_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'designation'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'reporting_manager_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'employee_profile_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status'                => [
                'type'       => 'ENUM',
                'constraint' => [
                    'selected', 'offer_sent', 'offer_accepted', 'documents_pending', 'bgv',
                    'hr_verification', 'ready_to_join', 'joined', 'induction', 'probation',
                    'confirmed', 'withdrawn',
                ],
                'default' => 'selected',
            ],
            'offer_sent_at'         => ['type' => 'DATETIME', 'null' => true],
            'offer_accepted_at'     => ['type' => 'DATETIME', 'null' => true],
            'bgv_status'            => ['type' => 'ENUM', 'constraint' => ['not_started', 'in_progress', 'cleared', 'flagged'], 'default' => 'not_started'],
            'bgv_notes'             => ['type' => 'TEXT', 'null' => true],
            'hr_verified_by'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'hr_verified_at'        => ['type' => 'DATETIME', 'null' => true],
            'joining_date'          => ['type' => 'DATE', 'null' => true],
            'joined_at'             => ['type' => 'DATETIME', 'null' => true],
            'probation_end_date'    => ['type' => 'DATE', 'null' => true],
            'confirmed_at'          => ['type' => 'DATETIME', 'null' => true],
            'created_by'            => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('department_id', 'departments', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('reporting_manager_id', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('employee_profile_id', 'employee_profiles', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('hr_verified_by', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('onboarding_records');
    }

    public function down()
    {
        $this->forge->dropTable('onboarding_records', true);
    }
}
