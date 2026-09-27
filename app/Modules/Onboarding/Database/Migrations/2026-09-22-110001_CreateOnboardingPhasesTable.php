<?php

namespace App\Modules\Onboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Admin-manageable checklist phases (Add/Edit/Reorder/Delete — see
 * OnboardingPhaseController), replacing OnboardingTaskModel::PHASES as
 * the source of truth for how the checklist tab groups tasks. Seeded
 * here with the same 3 phases that constant already defined, so nothing
 * about the existing checklist display changes until HR actually edits
 * something.
 *
 * legacy_key is NOT shown/editable in the UI — it's a stable handle
 * (independent of the admin-editable `name`) that
 * OnboardingTaskModel::seedDefaultTasks() uses to find "the phase that
 * currently represents Pre-Joining work" for a brand-new candidate's
 * auto-created checklist, so renaming a phase never breaks that lookup.
 * Phases HR creates from scratch get legacy_key = NULL; they're purely
 * organizational until/unless a future feature assigns tasks to them.
 */
class CreateOnboardingPhasesTable extends Migration
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
        $this->forge->createTable('onboarding_phases');

        $this->db->table('onboarding_phases')->insertBatch([
            ['name' => 'Pre-Joining',  'description' => 'Paperwork and setup completed before the candidate joins.', 'legacy_key' => 'pre_joining',  'sort_order' => 1, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['name' => 'Joining',      'description' => 'What happens on and around the joining date.',              'legacy_key' => 'joining',      'sort_order' => 2, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['name' => 'Post-Joining', 'description' => 'Follow-up check-ins after the candidate has joined.',       'legacy_key' => 'post_joining', 'sort_order' => 3, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('onboarding_phases', true);
    }
}
