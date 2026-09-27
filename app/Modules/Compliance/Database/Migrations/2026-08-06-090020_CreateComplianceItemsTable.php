<?php

namespace App\Modules\Compliance\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateComplianceItemsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'           => ['type' => 'INT', 'unsigned' => true],
            'compliance_type_id'   => ['type' => 'INT', 'unsigned' => true],
            'title'                => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true], // optional override, e.g. "GST Filing - July 2026"
            'due_date'             => ['type' => 'DATE'],
            'recurrence'           => ['type' => 'ENUM', 'constraint' => ['none', 'monthly', 'quarterly', 'half_yearly', 'annually'], 'default' => 'none'],
            'responsible_user_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status'               => ['type' => 'ENUM', 'constraint' => ['pending', 'in_progress', 'filed', 'overdue'], 'default' => 'pending'],
            'reminder_days_before' => ['type' => 'INT', 'unsigned' => true, 'default' => 7],
            'notes'                => ['type' => 'TEXT', 'null' => true],
            'filed_at'             => ['type' => 'DATETIME', 'null' => true],
            'previous_item_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true], // links an auto-generated next cycle back to the one it followed
            'created_by'           => ['type' => 'INT', 'unsigned' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'status']);
        $this->forge->addKey('due_date');
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('compliance_type_id', 'compliance_types', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('responsible_user_id', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'RESTRICT');
        // Self-reference to the item this auto-generated next cycle followed —
        // valid within the same CREATE TABLE since the column is defined above.
        $this->forge->addForeignKey('previous_item_id', 'compliance_items', 'id', '', 'SET NULL');
        $this->forge->createTable('compliance_items');
    }

    public function down()
    {
        $this->forge->dropTable('compliance_items', true);
    }
}
