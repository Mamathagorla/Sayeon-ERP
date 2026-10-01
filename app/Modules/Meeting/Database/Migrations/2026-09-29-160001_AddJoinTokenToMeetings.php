<?php

namespace App\Modules\Meeting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A random, unguessable per-meeting token — the "unique meeting link" is
 * built from this (see MeetingModel::joinUrl()), not from the numeric id
 * alone. A plain incrementing id would let anyone in the same company
 * enumerate other meetings' join links just by changing a number in the
 * URL; this token is what MeetingController::join() actually checks
 * before granting access, on top of the participant-or-staff check.
 * Existing meetings are backfilled here so every meeting — old or new —
 * has a real token once this migrates, same "auto-derive a default"
 * spirit as the earlier companies.short_code backfill.
 */
class AddJoinTokenToMeetings extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('join_token', 'meetings')) {
            $this->forge->addColumn('meetings', [
                'join_token' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'meeting_link'],
            ]);
            $this->forge->addUniqueKey('join_token');
            $this->forge->processIndexes('meetings');
        }

        $rows = $this->db->table('meetings')->select('id')->where('join_token', null)->get()->getResultArray();
        foreach ($rows as $row) {
            $this->db->table('meetings')->where('id', $row['id'])->update(['join_token' => bin2hex(random_bytes(20))]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('meetings', 'join_token');
    }
}
