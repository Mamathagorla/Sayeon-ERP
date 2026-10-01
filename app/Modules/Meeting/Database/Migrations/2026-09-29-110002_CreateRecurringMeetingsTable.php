<?php

namespace App\Modules\Meeting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * "Automatic meeting creation based on time" — mirrors the Expense
 * module's recurring_expenses/RecurringExpenseGenerator shape (template
 * table + next_generation_date + a daily generator), the only existing
 * precedent in this app for turning a schedule into concrete rows.
 * Meetings recur on shorter cycles than expenses, so frequency is
 * daily/weekly/monthly rather than monthly/quarterly/yearly.
 */
class CreateRecurringMeetingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'            => ['type' => 'INT', 'unsigned' => true],
            'title'                 => ['type' => 'VARCHAR', 'constraint' => 200],
            'agenda'                => ['type' => 'TEXT', 'null' => true],
            'location'              => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'meeting_link'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'start_time'            => ['type' => 'TIME', 'null' => true],
            'end_time'              => ['type' => 'TIME', 'null' => true],
            'frequency'             => ['type' => 'ENUM', 'constraint' => ['daily', 'weekly', 'monthly'], 'default' => 'weekly'],
            'start_date'            => ['type' => 'DATE'],
            'end_type'              => ['type' => 'ENUM', 'constraint' => ['occurrences', 'end_date'], 'default' => 'occurrences'],
            'end_date'              => ['type' => 'DATE', 'null' => true],
            'occurrences_total'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'occurrences_generated' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'next_generation_date'  => ['type' => 'DATE', 'null' => true],
            'status'                => ['type' => 'ENUM', 'constraint' => ['active', 'paused', 'completed', 'cancelled'], 'default' => 'active'],
            'created_by'            => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('recurring_meetings');

        // The standing attendee list a template's generated meetings all
        // get — copied into meeting_participants for each new occurrence
        // by RecurringMeetingGenerator, exactly once per generated
        // meeting (this join table itself is never read by anything
        // else). Internal users only (external invitees need a name to
        // attach to each new occurrence, which a template can't supply).
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'recurring_meeting_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id'             => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['recurring_meeting_id', 'user_id']);
        $this->forge->addForeignKey('recurring_meeting_id', 'recurring_meetings', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('recurring_meeting_participants');

        // Links each generated meeting back to its template, and
        // (paired with a unique index) is the idempotency guard
        // RecurringMeetingGenerator::runOne() relies on — exact mirror
        // of expenses.recurring_expense_id/occurrence_number.
        if (! $this->db->fieldExists('recurring_meeting_id', 'meetings')) {
            $this->forge->addColumn('meetings', [
                'recurring_meeting_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'meeting_link'],
                'occurrence_number'    => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'recurring_meeting_id'],
            ]);
            $this->forge->addForeignKey('recurring_meeting_id', 'recurring_meetings', 'id', '', 'SET NULL');
            $this->forge->addUniqueKey(['recurring_meeting_id', 'occurrence_number'], 'uniq_recurring_meeting_occurrence');
            $this->forge->processIndexes('meetings');
        }
    }

    public function down()
    {
        $this->forge->dropForeignKey('meetings', 'meetings_recurring_meeting_id_foreign');
        $this->forge->dropKey('meetings', 'uniq_recurring_meeting_occurrence', true);
        $this->forge->dropColumn('meetings', ['recurring_meeting_id', 'occurrence_number']);
        $this->forge->dropTable('recurring_meeting_participants', true);
        $this->forge->dropTable('recurring_meetings', true);
    }
}
