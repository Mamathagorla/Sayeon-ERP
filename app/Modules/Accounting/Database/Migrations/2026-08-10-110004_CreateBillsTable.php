<?php

namespace App\Modules\Accounting\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBillsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'  => ['type' => 'INT', 'unsigned' => true],
            'bill_number' => ['type' => 'VARCHAR', 'constraint' => 40],
            'vendor_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'amount'      => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'issue_date'  => ['type' => 'DATE'],
            'due_date'    => ['type' => 'DATE', 'null' => true],
            'status'      => ['type' => 'ENUM', 'constraint' => ['unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled'], 'default' => 'unpaid'],
            'notes'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('bill_number');
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('bills');
    }

    public function down()
    {
        $this->forge->dropTable('bills', true);
    }
}
