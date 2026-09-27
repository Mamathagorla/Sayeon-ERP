<?php

namespace App\Modules\Support\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Basic ticket system — every role can raise a ticket, Company Admin
 * (+ Super Admin) resolves. `company_id` is nullable (unlike every
 * other company-scoped table) because Super Admin has no company of
 * their own and may still want to log a general/HQ-level ticket that
 * isn't any single company's — everyone else's ticket always has one,
 * derived server-side from their own employee_profiles row.
 */
class CreateSupportTicketsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'subject'           => ['type' => 'VARCHAR', 'constraint' => 150],
            'description'       => ['type' => 'TEXT'],
            'priority'          => ['type' => 'ENUM', 'constraint' => ['low', 'medium', 'high'], 'default' => 'medium'],
            'status'            => ['type' => 'ENUM', 'constraint' => ['open', 'in_progress', 'resolved', 'closed'], 'default' => 'open'],
            'raised_by'         => ['type' => 'INT', 'unsigned' => true],
            'assigned_to'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'resolution_notes'  => ['type' => 'TEXT', 'null' => true],
            'resolved_at'       => ['type' => 'DATETIME', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('raised_by', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('assigned_to', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('support_tickets');
    }

    public function down()
    {
        $this->forge->dropTable('support_tickets', true);
    }
}
