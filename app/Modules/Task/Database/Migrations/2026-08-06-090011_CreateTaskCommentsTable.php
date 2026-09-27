<?php

namespace App\Modules\Task\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTaskCommentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'task_id'    => ['type' => 'INT', 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'comment'    => ['type' => 'TEXT'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('task_id', 'tasks', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'RESTRICT');
        $this->forge->createTable('task_comments');
    }

    public function down()
    {
        $this->forge->dropTable('task_comments', true);
    }
}
