<?php

namespace App\Modules\Meeting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the Outlook Calendar + Microsoft Teams provider and the sync state
 * of the external calendar event. provider_meeting_id (already present)
 * stores the external event id for every calendar provider, and meeting_link
 * stores the real join URL — so only the provider value and the sync fields
 * are new.
 */
class AddCalendarSyncAndOutlookTeamsProvider extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('sync_status', 'meetings')) {
            $this->forge->addColumn('meetings', [
                'sync_status' => ['type' => 'ENUM', 'constraint' => ['not_synced', 'synced', 'sync_failed', 'cancelled'], 'default' => 'not_synced', 'after' => 'provider_meeting_id'],
                'sync_error'  => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'sync_status'],
            ]);
        }

        $this->db->query("ALTER TABLE meetings MODIFY provider ENUM('none','google_meet','outlook_teams','zoom') NOT NULL DEFAULT 'none'");

        // Rows that already reference a real external event are synced.
        $this->db->query("UPDATE meetings SET sync_status = 'synced' WHERE provider_meeting_id IS NOT NULL AND provider <> 'none'");
    }

    public function down()
    {
        $this->db->query("UPDATE meetings SET provider = 'none' WHERE provider = 'outlook_teams'");
        $this->db->query("ALTER TABLE meetings MODIFY provider ENUM('none','google_meet','zoom') NOT NULL DEFAULT 'none'");

        if ($this->db->fieldExists('sync_status', 'meetings')) {
            $this->forge->dropColumn('meetings', ['sync_status', 'sync_error']);
        }
    }
}
