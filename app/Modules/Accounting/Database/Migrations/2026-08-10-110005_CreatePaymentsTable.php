<?php

namespace App\Modules\Accounting\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePaymentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'   => ['type' => 'INT', 'unsigned' => true],
            // Exactly one of these two is set, enforced in PaymentModel/PaymentController —
            // a real polymorphic FK isn't expressible as a DB constraint here.
            'invoice_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'bill_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'direction'    => ['type' => 'ENUM', 'constraint' => ['in', 'out']],
            'amount'       => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'payment_date' => ['type' => 'DATE'],
            'method'       => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'reference'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'notes'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('invoice_id', 'invoices', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('bill_id', 'bills', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('payments');
    }

    public function down()
    {
        $this->forge->dropTable('payments', true);
    }
}
