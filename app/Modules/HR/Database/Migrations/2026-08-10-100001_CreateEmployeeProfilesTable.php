<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeeProfilesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'                 => ['type' => 'INT', 'unsigned' => true],
            'company_id'              => ['type' => 'INT', 'unsigned' => true],
            'department_id'           => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'employee_code'           => ['type' => 'VARCHAR', 'constraint' => 20],
            'designation'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'reporting_manager_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'employment_type'         => ['type' => 'ENUM', 'constraint' => ['full_time', 'part_time', 'contract', 'intern'], 'default' => 'full_time'],
            'status'                  => ['type' => 'ENUM', 'constraint' => ['active', 'on_leave', 'resigned', 'terminated'], 'default' => 'active'],
            'date_of_joining'         => ['type' => 'DATE', 'null' => true],
            'date_of_birth'           => ['type' => 'DATE', 'null' => true],
            'address'                 => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'emergency_contact_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'emergency_contact_phone' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
            'updated_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('user_id');
        $this->forge->addUniqueKey('employee_code');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('department_id', 'departments', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('reporting_manager_id', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('employee_profiles');
    }

    public function down()
    {
        $this->forge->dropTable('employee_profiles', true);
    }
}
