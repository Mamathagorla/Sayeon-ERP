<?php

namespace App\Modules\Onboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOnboardingTasksTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'onboarding_record_id' => ['type' => 'INT', 'unsigned' => true],
            'task_type'            => [
                'type'       => 'ENUM',
                'constraint' => [
                    'document_collection', 'payroll_setup', 'it_access_setup', 'asset_setup',
                    'induction', 'manager_onboarding', 'review_30', 'review_60', 'review_90', 'other',
                ],
            ],
            'title'         => ['type' => 'VARCHAR', 'constraint' => 150],
            // Who is responsible for this checklist item — drives which
            // role can complete it (see OnboardingTaskController).
            // 'admin' stands in for IT/Admin, 'accountant' for Finance —
            // this app has no dedicated IT/Finance role.
            'owner_role'    => ['type' => 'ENUM', 'constraint' => ['hr', 'manager', 'admin', 'accountant']],
            'assigned_to'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status'        => ['type' => 'ENUM', 'constraint' => ['pending', 'in_progress', 'completed', 'skipped'], 'default' => 'pending'],
            'due_date'      => ['type' => 'DATE', 'null' => true],
            'completed_at'  => ['type' => 'DATETIME', 'null' => true],
            'completed_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'notes'         => ['type' => 'TEXT', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('onboarding_record_id');
        // Tasks live and die with their onboarding record.
        $this->forge->addForeignKey('onboarding_record_id', 'onboarding_records', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('assigned_to', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('completed_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('onboarding_tasks');
    }

    public function down()
    {
        $this->forge->dropTable('onboarding_tasks', true);
    }
}
