<?php

namespace App\Modules\Meeting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per ERP user who connected a Microsoft (Outlook/Teams) account.
 * Same shape as google_oauth_connections, so both calendar providers
 * handle tokens identically.
 */
class CreateMicrosoftOAuthConnectionsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('microsoft_oauth_connections')) {
            return;
        }

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

    public function down()
    {
        $this->forge->dropTable('microsoft_oauth_connections', true);
    }
}
