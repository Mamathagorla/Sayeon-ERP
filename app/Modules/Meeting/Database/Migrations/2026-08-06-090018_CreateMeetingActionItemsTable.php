<?php

namespace App\Modules\Meeting\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMeetingActionItemsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'meeting_id'     => ['type' => 'INT', 'unsigned' => true],
            'description'    => ['type' => 'VARCHAR', 'constraint' => 500],
            'assigned_to'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'due_date'       => ['type' => 'DATE', 'null' => true],
            'status'         => ['type' => 'ENUM', 'constraint' => ['pending', 'in_progress', 'completed'], 'default' => 'pending'],
            'linked_task_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true], // set once "Convert to Task" is used
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('meeting_id', 'meetings', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('assigned_to', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('linked_task_id', 'tasks', 'id', '', 'SET NULL');
        $this->forge->createTable('meeting_action_items');
    }

    public function down()
    {
        $this->forge->dropTable('meeting_action_items', true);
    }
}
