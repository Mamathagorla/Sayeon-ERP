<?php

namespace App\Modules\Onboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Links each existing task to its onboarding_phases row, backfilled from
 * the exact same task_type → phase mapping OnboardingTaskModel::PHASES
 * already encoded (duplicated here as plain values, not by calling the
 * model, since a migration shouldn't depend on application code that
 * might itself change later). ON DELETE SET NULL: deleting a phase never
 * deletes or blocks on a task by itself — OnboardingPhaseController
 * enforces the "no active/completed tasks" rule before allowing a
 * delete; this FK behavior is just the safety net under that.
 */
class AddPhaseIdToOnboardingTasks extends Migration
{
    private const TASK_TYPE_LEGACY_KEY = [
        'document_collection' => 'pre_joining',
        'payroll_setup'       => 'pre_joining',
        'it_access_setup'     => 'pre_joining',
        'asset_setup'         => 'joining',
        'induction'           => 'joining',
        'manager_onboarding'  => 'joining',
        'review_30'           => 'post_joining',
        'review_60'           => 'post_joining',
        'review_90'           => 'post_joining',
        'other'               => 'post_joining',
    ];

    public function up()
    {
        $this->forge->addColumn('onboarding_tasks', [
            'phase_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'task_type'],
        ]);
        $this->forge->addForeignKey('phase_id', 'onboarding_phases', 'id', '', 'SET NULL');
        $this->forge->processIndexes('onboarding_tasks');

        $phases = $this->db->table('onboarding_phases')->select('id, legacy_key')->get()->getResultArray();
        $idForKey = array_column($phases, 'id', 'legacy_key');

        foreach (self::TASK_TYPE_LEGACY_KEY as $taskType => $legacyKey) {
            if (! isset($idForKey[$legacyKey])) {
                continue;
            }
            $this->db->table('onboarding_tasks')
                ->where('task_type', $taskType)
                ->update(['phase_id' => $idForKey[$legacyKey]]);
        }
    }

    public function down()
    {
        $this->forge->dropForeignKey('onboarding_tasks', 'onboarding_tasks_phase_id_foreign');
        $this->forge->dropColumn('onboarding_tasks', 'phase_id');
    }
}
