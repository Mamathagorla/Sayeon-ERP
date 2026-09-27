<?php

namespace App\Modules\Offboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Links each existing task to its offboarding_phases row, backfilled
 * from the exact same task_type → phase mapping
 * OffboardingTaskModel::PHASES already encoded (duplicated here as plain
 * values, not by calling the model — a migration shouldn't depend on
 * application code that might itself change later). ON DELETE SET NULL:
 * see OnboardingTasks' sibling migration for why.
 */
class AddPhaseIdToOffboardingTasks extends Migration
{
    private const TASK_TYPE_LEGACY_KEY = [
        'manager_review'        => 'exit_request_approval',
        'hr_approval'           => 'exit_request_approval',
        'handover'              => 'handover_transition',
        'department_clearance'  => 'clearance_asset_return',
        'asset_return'          => 'clearance_asset_return',
        'access_revocation'     => 'access_revocation',
        'final_settlement'      => 'final_settlement_documents',
        'exit_interview'        => 'final_settlement_documents',
        'final_documents'       => 'final_settlement_documents',
        'other'                 => 'final_settlement_documents',
    ];

    public function up()
    {
        $this->forge->addColumn('offboarding_tasks', [
            'phase_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'task_type'],
        ]);
        $this->forge->addForeignKey('phase_id', 'offboarding_phases', 'id', '', 'SET NULL');
        $this->forge->processIndexes('offboarding_tasks');

        $phases   = $this->db->table('offboarding_phases')->select('id, legacy_key')->get()->getResultArray();
        $idForKey = array_column($phases, 'id', 'legacy_key');

        foreach (self::TASK_TYPE_LEGACY_KEY as $taskType => $legacyKey) {
            if (! isset($idForKey[$legacyKey])) {
                continue;
            }
            $this->db->table('offboarding_tasks')
                ->where('task_type', $taskType)
                ->update(['phase_id' => $idForKey[$legacyKey]]);
        }
    }

    public function down()
    {
        $this->forge->dropForeignKey('offboarding_tasks', 'offboarding_tasks_phase_id_foreign');
        $this->forge->dropColumn('offboarding_tasks', 'phase_id');
    }
}
