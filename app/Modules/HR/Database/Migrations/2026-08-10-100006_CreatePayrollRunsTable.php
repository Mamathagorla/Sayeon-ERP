<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePayrollRunsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'month'        => ['type' => 'TINYINT', 'unsigned' => true],
            'year'         => ['type' => 'SMALLINT', 'unsigned' => true],
            'status'       => ['type' => 'ENUM', 'constraint' => ['draft', 'processed', 'paid'], 'default' => 'draft'],
            'processed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['month', 'year']);
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('payroll_runs');
    }

    public function down()
    {
        $this->forge->dropTable('payroll_runs', true);
    }
}
