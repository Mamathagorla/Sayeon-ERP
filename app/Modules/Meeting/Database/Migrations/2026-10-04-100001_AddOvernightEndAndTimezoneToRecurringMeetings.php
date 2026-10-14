<?php

namespace App\Modules\Meeting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Additive only. ends_next_day lets a template represent an overnight
 * meeting (e.g. 23:30 to 00:30) explicitly, so an end time earlier than
 * the start time is never silently accepted as a mistake. meetings.end_date
 * records the calendar day a generated occurrence actually ends on (the
 * day after for overnight meetings; NULL means same day as meeting_date).
 * timezone is carried from the template onto each generated meeting so the
 * template's timezone is preserved; existing templates are backfilled with
 * the app's configured timezone, which is what they were effectively using.
 */
class AddOvernightEndAndTimezoneToRecurringMeetings extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('ends_next_day', 'recurring_meetings')) {
            $this->forge->addColumn('recurring_meetings', [
                'ends_next_day' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'end_time'],
                'timezone'      => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'ends_next_day'],
            ]);
        }

        if (! $this->db->fieldExists('end_date', 'meetings')) {
            $this->forge->addColumn('meetings', [
                'end_date' => ['type' => 'DATE', 'null' => true, 'after' => 'meeting_date'],
            ]);
        }

        if (! $this->db->fieldExists('timezone', 'meetings')) {
            $this->forge->addColumn('meetings', [
                'timezone' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'end_date'],
            ]);
        }

        $this->db->table('recurring_meetings')
            ->where('timezone IS NULL', null, false)
            ->update(['timezone' => config('App')->appTimezone]);
    }

    public function down()
    {
        if ($this->db->fieldExists('timezone', 'meetings')) {
            $this->forge->dropColumn('meetings', 'timezone');
        }
        if ($this->db->fieldExists('end_date', 'meetings')) {
            $this->forge->dropColumn('meetings', 'end_date');
        }
        if ($this->db->fieldExists('timezone', 'recurring_meetings')) {
            $this->forge->dropColumn('recurring_meetings', ['timezone', 'ends_next_day']);
        }
    }
}
