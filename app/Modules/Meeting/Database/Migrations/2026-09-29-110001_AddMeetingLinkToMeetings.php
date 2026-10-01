<?php

namespace App\Modules\Meeting\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMeetingLinkToMeetings extends Migration
{
    /**
     * A dedicated video-call-link field, separate from `location` (which
     * was previously dual-purposed as "room, or a meeting link" per its
     * own original column comment) — so a meeting can carry both a
     * physical room AND a link at once, and so the link can be surfaced
     * distinctly (a "Join Meeting" button, and in the participant-added
     * notification) instead of relying on location happening to look
     * like a URL. Nullable/additive — every existing meeting keeps
     * working with location exactly as before.
     */
    public function up()
    {
        if (! $this->db->fieldExists('meeting_link', 'meetings')) {
            $this->forge->addColumn('meetings', [
                'meeting_link' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'location'],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('meetings', 'meeting_link');
    }
}
