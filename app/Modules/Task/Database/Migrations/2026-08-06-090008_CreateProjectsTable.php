<?php

namespace App\Modules\Task\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProjectsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'  => ['type' => 'INT', 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            'status'      => ['type' => 'ENUM', 'constraint' => ['active', 'completed', 'on_hold', 'cancelled'], 'default' => 'active'],
            'start_date'  => ['type' => 'DATE', 'null' => true],
            'end_date'    => ['type' => 'DATE', 'null' => true],
            'owner_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('owner_id', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('projects');
    }

    public function down()
    {
        $this->forge->dropTable('projects', true);
    }
}
