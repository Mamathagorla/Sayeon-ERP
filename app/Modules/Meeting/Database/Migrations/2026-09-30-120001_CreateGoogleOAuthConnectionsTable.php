<?php

namespace App\Modules\Meeting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per ERP user who has connected their own Google account for
 * real Google Meet creation (see GoogleOAuthController / GoogleMeetProvider).
 * Per-user, not per-company — domain-wide delegation (a single shared
 * credential for the whole company) only works on a paid Google
 * Workspace account with admin console access; a regular personal
 * Gmail account has no such thing, so each organizer who wants to use
 * Google Meet connects their own account once instead.
 */
class CreateGoogleOAuthConnectionsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('google_oauth_connections')) {
            return;
        }

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'INT', 'unsigned' => true],
            'google_email'  => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            // OAuth tokens are opaque bearer credentials, not user-typed
            // data — TEXT rather than a short VARCHAR since Google's
            // access/refresh tokens don't have a fixed short length
            // guarantee.
            'access_token'  => ['type' => 'TEXT', 'null' => true],
            'refresh_token' => ['type' => 'TEXT', 'null' => true],
            'token_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'scope'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('google_oauth_connections');
    }

    public function down()
    {
        $this->forge->dropTable('google_oauth_connections', true);
    }
}
