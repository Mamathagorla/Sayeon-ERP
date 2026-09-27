<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSalaryStructuresTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'        => ['type' => 'INT', 'unsigned' => true],
            'basic'          => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'hra'            => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'allowances'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'deductions'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'effective_from' => ['type' => 'DATE'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('salary_structures');
    }

    public function down()
    {
        $this->forge->dropTable('salary_structures', true);
    }
}
