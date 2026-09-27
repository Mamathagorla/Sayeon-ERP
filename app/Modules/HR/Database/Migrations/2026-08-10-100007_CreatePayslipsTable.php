<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePayslipsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'payroll_run_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id'        => ['type' => 'INT', 'unsigned' => true],
            'basic'          => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'hra'            => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'allowances'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'deductions'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'gross'          => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'net'            => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['payroll_run_id', 'user_id']);
        $this->forge->addForeignKey('payroll_run_id', 'payroll_runs', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('payslips');
    }

    public function down()
    {
        $this->forge->dropTable('payslips', true);
    }
}
