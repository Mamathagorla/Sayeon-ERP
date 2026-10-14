<?php

namespace App\Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the setting.view / setting.create / setting.edit / setting.delete
 * permissions used by the Localization screen. Inserted by migration rather
 * than by re-running PermissionSeeder, because that seeder also deletes the
 * accounting module's permissions on every run. Idempotent: existing slugs
 * are left untouched.
 */
class AddSettingPermissions extends Migration
{
    public function up()
    {
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $slug = "setting.{$action}";

            if ($this->db->table('permissions')->where('slug', $slug)->countAllResults() === 0) {
                $this->db->table('permissions')->insert([
                    'slug'        => $slug,
                    'module'      => 'setting',
                    'description' => ucfirst($action) . ' setting',
                ]);
            }
        }
    }

    public function down()
    {
        $this->db->table('permissions')->where('module', 'setting')->delete();
    }
}
