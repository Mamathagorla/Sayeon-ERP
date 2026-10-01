<?php

namespace App\Modules\Meeting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds real video-conferencing provider support on top of the existing
 * meetings table — additive only. `meeting_link` (already existing,
 * already the one field join()/joinUrl() treat as "the actual call URL")
 * keeps that exact role: when provider is 'google_meet'/'zoom', it holds
 * the REAL join URL the provider's API returned, never a fabricated one;
 * when provider is 'none' it holds whatever the organizer typed manually
 * (unchanged existing behaviour). provider_meeting_id is the provider's
 * own external event/meeting id, needed to update or cancel that same
 * external meeting later without ever creating a second one.
 */
class AddProviderToMeetings extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('provider', 'meetings')) {
            return;
        }

        $this->forge->addColumn('meetings', [
            'provider'            => ['type' => 'ENUM', 'constraint' => ['none', 'google_meet', 'zoom'], 'default' => 'none', 'after' => 'meeting_link'],
            'provider_meeting_id' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'provider'],
        ]);
    }

    public function down()
    {
        if (! $this->db->fieldExists('provider', 'meetings')) {
            return;
        }

        $this->forge->dropColumn('meetings', ['provider', 'provider_meeting_id']);
    }
}
