<?php

namespace App\Modules\Meeting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Reverts only the Microsoft Outlook / Teams integration.
 *
 * Kept as a new forward migration rather than editing the two earlier ones,
 * because those have already been applied. Their shared parts stay:
 * meetings.sync_status and meetings.sync_error are used by the Google Meet and
 * Zoom paths as well. Removed here:
 *   - the 'outlook_teams' value of meetings.provider
 *   - the microsoft_oauth_connections table
 *
 * Existing meeting rows are never deleted. Any row that was set to
 * 'outlook_teams' is moved to 'none' and keeps its own data.
 */
class RevertMicrosoftOutlookTeamsIntegration extends Migration
{
    public function up()
    {
        $this->db->query("UPDATE meetings SET provider = 'none', sync_status = 'not_synced' WHERE provider = 'outlook_teams'");
        $this->db->query("ALTER TABLE meetings MODIFY provider ENUM('none','google_meet','zoom') NOT NULL DEFAULT 'none'");

        if ($this->db->tableExists('microsoft_oauth_connections')) {
            $this->forge->dropTable('microsoft_oauth_connections', true);
        }
    }

    public function down()
    {
        $this->db->query("ALTER TABLE meetings MODIFY provider ENUM('none','google_meet','outlook_teams','zoom') NOT NULL DEFAULT 'none'");

        if (! $this->db->tableExists('microsoft_oauth_connections')) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'          => ['type' => 'INT', 'unsigned' => true],
                'microsoft_email'  => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'access_token'     => ['type' => 'TEXT', 'null' => true],
                'refresh_token'    => ['type' => 'TEXT', 'null' => true],
                'token_expires_at' => ['type' => 'DATETIME', 'null' => true],
                'scope'            => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
                'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('user_id');
            $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
            $this->forge->createTable('microsoft_oauth_connections');
        }
    }
}
