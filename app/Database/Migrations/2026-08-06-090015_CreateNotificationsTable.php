<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotificationsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'        => ['type' => 'INT', 'unsigned' => true],
            'type'           => ['type' => 'VARCHAR', 'constraint' => 60], // e.g. task_due, compliance_due
            'title'          => ['type' => 'VARCHAR', 'constraint' => 200],
            'message'        => ['type' => 'VARCHAR', 'constraint' => 500],
            'related_module' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'related_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'channel'        => ['type' => 'ENUM', 'constraint' => ['in_app', 'email', 'whatsapp'], 'default' => 'in_app'],
            'is_read'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'scheduled_at'   => ['type' => 'DATETIME', 'null' => true],
            'sent_at'        => ['type' => 'DATETIME', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'is_read']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('notifications');
    }

    public function down()
    {
        $this->forge->dropTable('notifications', true);
    }
}
