<?php

namespace App\Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Organization-wide localization profile — the one settings mechanism this
 * ERP had been missing (its only configuration was Config/App.php). A plain
 * key/value table keeps it extensible without a column per setting.
 *
 * Seeded defaults reproduce how the app already behaves (India, INR, Asia/
 * Kolkata, dd/mm/yyyy, 12-hour clock), so existing screens look unchanged
 * until an administrator changes them.
 */
class CreateAppSettingsTable extends Migration
{
    private const DEFAULTS = [
        'country_iso2' => 'IN',
        'currency_code' => 'INR',
        'timezone'      => 'Asia/Kolkata',
        'locale'        => 'en_IN',
        'date_format'   => 'd/m/Y',
        'time_format'   => '12h',
    ];

    public function up()
    {
        if (! $this->db->tableExists('app_settings')) {
            $this->forge->addField([
                'setting_key'   => ['type' => 'VARCHAR', 'constraint' => 64],
                'setting_value' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_by'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            ]);
            $this->forge->addKey('setting_key', true);
            $this->forge->addForeignKey('updated_by', 'users', 'id', '', 'SET NULL');
            $this->forge->createTable('app_settings');
        }

        foreach (self::DEFAULTS as $key => $value) {
            $exists = $this->db->table('app_settings')->where('setting_key', $key)->countAllResults() > 0;
            if (! $exists) {
                $this->db->table('app_settings')->insert([
                    'setting_key'   => $key,
                    'setting_value' => $value,
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('app_settings', true);
    }
}
