<?php

namespace App\Modules\Support\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSupportTicketAttachmentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'message_id'    => ['type' => 'INT', 'unsigned' => true],
            'file_path'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_size'     => ['type' => 'INT', 'unsigned' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('message_id');
        $this->forge->addForeignKey('message_id', 'support_ticket_messages', 'id', '', 'CASCADE');
        $this->forge->createTable('support_ticket_attachments');
    }

    public function down()
    {
        $this->forge->dropTable('support_ticket_attachments', true);
    }
}
