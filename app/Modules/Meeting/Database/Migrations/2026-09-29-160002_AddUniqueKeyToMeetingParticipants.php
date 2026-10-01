<?php

namespace App\Modules\Meeting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Prevents the same internal user being added to a meeting twice — was
 * previously only avoided by UI convention (the participants dropdown
 * doesn't remove already-added users), not enforced by the schema. Now
 * load-bearing: MeetingController's bulk participant-select on create/
 * edit relies on being able to insert a batch without duplicating a user
 * already on the list. NULL user_id (external participants) are exempt
 * — MySQL unique indexes never treat multiple NULLs as a collision, so
 * any number of external invitees per meeting still works.
 */
class AddUniqueKeyToMeetingParticipants extends Migration
{
    public function up()
    {
        $this->forge->addUniqueKey(['meeting_id', 'user_id'], 'uniq_meeting_participant');
        $this->forge->processIndexes('meeting_participants');
    }

    public function down()
    {
        $this->forge->dropKey('meeting_participants', 'uniq_meeting_participant', true);
    }
}
