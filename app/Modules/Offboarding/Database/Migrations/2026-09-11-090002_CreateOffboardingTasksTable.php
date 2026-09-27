<?php

namespace App\Modules\Offboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOffboardingTasksTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'offboarding_record_id' => ['type' => 'INT', 'unsigned' => true],
            'task_type'             => [
                'type'       => 'ENUM',
                'constraint' => [
                    'manager_review', 'hr_approval', 'handover', 'department_clearance',
                    'asset_return', 'access_revocation', 'final_settlement', 'exit_interview',
                    'final_documents', 'other',
                ],
            ],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 150],
            // 'employee' is an owner_role Onboarding's tasks didn't need
            // (a pre-hire candidate isn't a system user) — an exiting
            // employee is, and owns tasks like handover notes/asset
            // return themselves (see Roles list in the request).
            'owner_role'    => ['type' => 'ENUM', 'constraint' => ['hr', 'manager', 'admin', 'accountant', 'employee']],
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
        $this->forge->addKey('offboarding_record_id');
        $this->forge->addForeignKey('offboarding_record_id', 'offboarding_records', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('assigned_to', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('completed_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('offboarding_tasks');
    }

    public function down()
    {
        $this->forge->dropTable('offboarding_tasks', true);
    }
}
