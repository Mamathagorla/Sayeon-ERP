<?php

namespace App\Modules\Offboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Admin-manageable process phases (Add/Edit/Reorder/Delete — see
 * OffboardingPhaseController), replacing OffboardingTaskModel::PHASES as
 * the source of truth for how the Offboarding tab groups tasks. Seeded
 * here with the same 6 phases that constant already defined, so nothing
 * about the existing display changes until HR actually edits something.
 *
 * legacy_key mirrors OnboardingPhasesTable's own — a stable handle
 * OffboardingTaskModel::seedDefaultTasks() uses to find the right phase
 * for a brand-new exit's auto-created checklist regardless of renames.
 * 'exit_completed' has no task types mapped to it (same as the original
 * constant) — it's rendered from the record's own status, not from
 * tasks — see OffboardingPhaseController's docblock for why deleting
 * (not deactivating) that one specific phase is refused.
 */
class CreateOffboardingPhasesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'legacy_key'  => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'sort_order'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('offboarding_phases');

        $now = date('Y-m-d H:i:s');
        $this->db->table('offboarding_phases')->insertBatch([
            ['name' => 'Exit Request & Approval',      'description' => 'Resignation is reviewed and approved.',        'legacy_key' => 'exit_request_approval',      'sort_order' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Handover & Transition',         'description' => 'Work and responsibilities are handed over.',   'legacy_key' => 'handover_transition',         'sort_order' => 2, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Clearance & Asset Return',       'description' => 'Department clearance and company assets.',     'legacy_key' => 'clearance_asset_return',      'sort_order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Access Revocation',              'description' => 'System, email and software access is revoked.', 'legacy_key' => 'access_revocation',           'sort_order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Final Settlement & Documents',   'description' => 'Final pay, exit interview and documents.',     'legacy_key' => 'final_settlement_documents',  'sort_order' => 5, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Exit Completed',                 'description' => 'The exit process is fully complete.',          'legacy_key' => 'exit_completed',              'sort_order' => 6, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('offboarding_phases', true);
    }
}
