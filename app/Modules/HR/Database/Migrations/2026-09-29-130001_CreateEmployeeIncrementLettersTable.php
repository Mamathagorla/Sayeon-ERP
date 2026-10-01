<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The 3rd step of "Offer Letter -> Joining Letter -> Increment Letter" —
 * unlike the first two (generated live from onboarding_records fields,
 * no dedicated table), an increment recurs over an employee's whole
 * tenure, so it needs its own history table: HR can issue one any time
 * after hire, and re-open/re-print a past one later. previous_ctc is
 * captured at issue time (not re-derived later) so the letter stays
 * accurate even if the employee's salary structure is edited afterwards.
 */
class CreateEmployeeIncrementLettersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'employee_profile_id' => ['type' => 'INT', 'unsigned' => true],
            'company_id'          => ['type' => 'INT', 'unsigned' => true],
            'previous_ctc'        => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'new_ctc'             => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'effective_date'      => ['type' => 'DATE'],
            'reason'              => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'issued_by'           => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_profile_id');
        $this->forge->addForeignKey('employee_profile_id', 'employee_profiles', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('issued_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('employee_increment_letters');
    }

    public function down()
    {
        $this->forge->dropTable('employee_increment_letters', true);
    }
}
