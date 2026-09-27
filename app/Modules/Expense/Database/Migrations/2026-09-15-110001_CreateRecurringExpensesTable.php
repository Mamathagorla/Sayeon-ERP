<?php

namespace App\Modules\Expense\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Templates that drive automatic generation of normal `expenses` rows
 * (see RecurringExpenseGenerator + the new expenses.recurring_expense_id/
 * occurrence_number columns in the next migration) — this table itself
 * never appears in the Expenses list or reports; only the concrete rows
 * it generates do.
 */
class CreateRecurringExpensesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'             => ['type' => 'INT', 'unsigned' => true],
            'title'                  => ['type' => 'VARCHAR', 'constraint' => 150],
            'category'               => ['type' => 'VARCHAR', 'constraint' => 100],
            'amount'                 => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'description'            => ['type' => 'TEXT', 'null' => true],
            'frequency'              => ['type' => 'ENUM', 'constraint' => ['monthly', 'quarterly', 'yearly'], 'default' => 'monthly'],
            'start_date'             => ['type' => 'DATE'],
            'end_type'               => ['type' => 'ENUM', 'constraint' => ['end_date', 'occurrences'], 'default' => 'occurrences'],
            'end_date'               => ['type' => 'DATE', 'null' => true],
            'occurrences_total'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'occurrences_generated'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'next_generation_date'   => ['type' => 'DATE', 'null' => true],
            'status'                 => ['type' => 'ENUM', 'constraint' => ['active', 'paused', 'completed', 'cancelled'], 'default' => 'active'],
            'created_by'             => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
            'updated_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('recurring_expenses');
    }

    public function down()
    {
        $this->forge->dropTable('recurring_expenses', true);
    }
}
