<?php

namespace App\Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-user localization preferences. NULL means "use the organization
 * default from app_settings", so every existing user keeps today's behaviour
 * without a backfill.
 */
class AddTimezoneAndLocaleToUsers extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('timezone', 'users')) {
            return;
        }

        $this->forge->addColumn('users', [
            'timezone' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'phone'],
            'locale'   => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'after' => 'timezone'],
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('timezone', 'users')) {
            $this->forge->dropColumn('users', ['timezone', 'locale']);
        }
    }
}
