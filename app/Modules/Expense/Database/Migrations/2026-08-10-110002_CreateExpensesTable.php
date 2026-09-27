<?php

namespace App\Modules\Expense\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExpensesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'      => ['type' => 'INT', 'unsigned' => true],
            'vendor'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'category'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'billing_cycle'   => ['type' => 'ENUM', 'constraint' => ['monthly', 'quarterly', 'yearly', 'one_time'], 'default' => 'monthly'],
            'amount'          => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'renewal_date'    => ['type' => 'DATE', 'null' => true],
            'auto_renewal'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'payment_method'  => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'notes'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'          => ['type' => 'ENUM', 'constraint' => ['active', 'cancelled'], 'default' => 'active'],
            'created_by'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('expenses');
    }

    public function down()
    {
        $this->forge->dropTable('expenses', true);
    }
}
