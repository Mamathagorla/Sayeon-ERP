<?php

namespace App\Modules\Offboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOffboardingRecordsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            // Always an existing employee — unlike Onboarding's pre-hire
            // candidates, no separate identity fields are needed here at
            // all; name/email/department/manager all come via the join.
            'employee_profile_id'  => ['type' => 'INT', 'unsigned' => true],
            'company_id'           => ['type' => 'INT', 'unsigned' => true],
            'exit_type'            => ['type' => 'ENUM', 'constraint' => ['resignation', 'termination']],
            'reason'               => ['type' => 'TEXT', 'null' => true],
            'exit_date'            => ['type' => 'DATE'],
            'last_working_day'     => ['type' => 'DATE', 'null' => true],
            'notice_period_days'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            // The 7 requested section statuses — written only by
            // OffboardingTaskController as it recomputes each section
            // from that section's own checklist tasks, never edited
            // directly, so they can't drift from the checklist.
            'handover_status'             => ['type' => 'ENUM', 'constraint' => ['not_started', 'in_progress', 'completed'], 'default' => 'not_started'],
            'department_clearance_status' => ['type' => 'ENUM', 'constraint' => ['not_started', 'in_progress', 'completed'], 'default' => 'not_started'],
            'asset_return_status'         => ['type' => 'ENUM', 'constraint' => ['not_started', 'in_progress', 'completed'], 'default' => 'not_started'],
            'access_revocation_status'    => ['type' => 'ENUM', 'constraint' => ['not_started', 'in_progress', 'completed'], 'default' => 'not_started'],
            'final_settlement_status'     => ['type' => 'ENUM', 'constraint' => ['not_started', 'in_progress', 'completed'], 'default' => 'not_started'],
            'exit_interview_status'       => ['type' => 'ENUM', 'constraint' => ['not_started', 'in_progress', 'completed'], 'default' => 'not_started'],
            'final_document_status'       => ['type' => 'ENUM', 'constraint' => ['not_started', 'in_progress', 'completed'], 'default' => 'not_started'],
            // Overall exit status — the 12 requested stages plus
            // 'rescinded' (withdrawn resignation / reversed termination),
            // the one operationally-necessary addition beyond the
            // literal list, same reasoning as Onboarding's 'withdrawn'.
            'status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'resignation_submitted', 'manager_review', 'hr_approval', 'exit_initiated',
                    'handover', 'department_clearances', 'asset_return', 'access_revocation',
                    'final_settlement', 'exit_interview', 'final_documents', 'exit_completed',
                    'rescinded',
                ],
                'default' => 'resignation_submitted',
            ],
            'initiated_by'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'manager_reviewed_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'manager_reviewed_at'  => ['type' => 'DATETIME', 'null' => true],
            'hr_approved_by'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'hr_approved_at'       => ['type' => 'DATETIME', 'null' => true],
            'completed_at'         => ['type' => 'DATETIME', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('employee_profile_id', 'employee_profiles', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('initiated_by', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('manager_reviewed_by', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('hr_approved_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('offboarding_records');
    }

    public function down()
    {
        $this->forge->dropTable('offboarding_records', true);
    }
}
