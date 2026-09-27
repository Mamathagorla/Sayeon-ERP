<?php

namespace App\Modules\Task\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTasksTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uuid'          => ['type' => 'CHAR', 'constraint' => 36],
            'title'         => ['type' => 'VARCHAR', 'constraint' => 200],
            'description'   => ['type' => 'TEXT', 'null' => true],
            'company_id'    => ['type' => 'INT', 'unsigned' => true],
            'department_id' => ['type' => 'INT', 'unsigned' => true],
            'project_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'priority'      => ['type' => 'ENUM', 'constraint' => ['low', 'medium', 'high', 'urgent'], 'default' => 'medium'],
            'assigned_to'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_by'    => ['type' => 'INT', 'unsigned' => true],
            'start_date'    => ['type' => 'DATE', 'null' => true],
            'due_date'      => ['type' => 'DATE', 'null' => true],
            'status'        => [
                'type'       => 'ENUM',
                'constraint' => ['new', 'assigned', 'in_progress', 'waiting', 'review', 'completed', 'on_hold', 'cancelled'],
                'default'    => 'new',
            ],
            'completed_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'status']);
        $this->forge->addKey('due_date');
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('department_id', 'departments', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('project_id', 'projects', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('assigned_to', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'RESTRICT');
        $this->forge->createTable('tasks');
    }

    public function down()
    {
        $this->forge->dropTable('tasks', true);
    }
}
