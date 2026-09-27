<?php

namespace App\Modules\Task\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTaskAttachmentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'task_id'       => ['type' => 'INT', 'unsigned' => true],
            'file_path'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_size'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'uploaded_by'   => ['type' => 'INT', 'unsigned' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('task_id', 'tasks', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('uploaded_by', 'users', 'id', '', 'RESTRICT');
        $this->forge->createTable('task_attachments');
    }

    public function down()
    {
        $this->forge->dropTable('task_attachments', true);
    }
}
