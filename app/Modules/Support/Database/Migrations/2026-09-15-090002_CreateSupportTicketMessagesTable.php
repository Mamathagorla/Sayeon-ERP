<?php

namespace App\Modules\Support\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The conversation thread under a ticket — customer/agent replies plus
 * agent-only internal notes (is_internal_note), which SupportTicketMessageModel::
 * forTicket() filters out for non-resolver viewers. The ticket's own
 * `description` column remains the thread's first entry; it isn't
 * duplicated in here.
 */
class CreateSupportTicketMessagesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'ticket_id'         => ['type' => 'INT', 'unsigned' => true],
            'user_id'           => ['type' => 'INT', 'unsigned' => true],
            'message'           => ['type' => 'TEXT'],
            'is_internal_note'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('ticket_id');
        $this->forge->addForeignKey('ticket_id', 'support_tickets', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('support_ticket_messages');
    }

    public function down()
    {
        $this->forge->dropTable('support_ticket_messages', true);
    }
}
