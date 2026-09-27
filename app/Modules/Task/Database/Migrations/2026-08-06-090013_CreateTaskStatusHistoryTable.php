<?php

namespace App\Modules\Task\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTaskStatusHistoryTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'task_id'     => ['type' => 'INT', 'unsigned' => true],
            'from_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'to_status'   => ['type' => 'VARCHAR', 'constraint' => 20],
            'changed_by'  => ['type' => 'INT', 'unsigned' => true],
            'changed_at'  => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('task_id', 'tasks', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('changed_by', 'users', 'id', '', 'RESTRICT');
        $this->forge->createTable('task_status_history');
    }

    public function down()
    {
        $this->forge->dropTable('task_status_history', true);
    }
}
