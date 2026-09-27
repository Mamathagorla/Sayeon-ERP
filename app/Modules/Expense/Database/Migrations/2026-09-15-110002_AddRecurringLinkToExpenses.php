<?php

namespace App\Modules\Expense\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Purely additive — both columns are nullable and every existing row
 * gets NULL, so manually-created expenses (the entire existing flow)
 * are completely unaffected. A generated expense sets both: which
 * recurring_expenses template it came from, and which 1-based
 * occurrence of that template it is.
 *
 * The unique index is the hard duplicate-prevention guarantee behind
 * RecurringExpenseGenerator's own "already generated?" check — MySQL
 * treats each NULL in a unique index as distinct, so it only ever
 * constrains generated rows against each other, never plain expenses.
 */
class AddRecurringLinkToExpenses extends Migration
{
    public function up()
    {
        $this->forge->addColumn('expenses', [
            'recurring_expense_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'company_id'],
            'occurrence_number'    => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'recurring_expense_id'],
        ]);

        // addForeignKey()/addUniqueKey() only queue the definitions —
        // processIndexes() is what actually issues the ALTER TABLE ...
        // ADD CONSTRAINT against the already-existing `expenses` table
        // (same two-step pattern as Document's AddOnboardingRecordIdToDocuments).
        $this->forge->addForeignKey('recurring_expense_id', 'recurring_expenses', 'id', '', 'SET NULL');
        $this->forge->addUniqueKey(['recurring_expense_id', 'occurrence_number'], 'uniq_recurring_occurrence');
        $this->forge->processIndexes('expenses');
    }

    public function down()
    {
        $this->forge->dropForeignKey('expenses', 'expenses_recurring_expense_id_foreign');
        $this->forge->dropKey('expenses', 'uniq_recurring_occurrence');
        $this->forge->dropColumn('expenses', ['recurring_expense_id', 'occurrence_number']);
    }
}
