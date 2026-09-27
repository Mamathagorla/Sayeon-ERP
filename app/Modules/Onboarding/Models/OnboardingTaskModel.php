<?php

namespace App\Modules\Onboarding\Models;

use CodeIgniter\Model;

class OnboardingTaskModel extends Model
{
    public const TASK_TYPES = [
        'document_collection', 'payroll_setup', 'it_access_setup', 'asset_setup',
        'induction', 'manager_onboarding', 'review_30', 'review_60', 'review_90', 'other',
    ];
    public const OWNER_ROLES = ['hr', 'manager', 'admin', 'accountant'];
    public const STATUSES    = ['pending', 'in_progress', 'completed', 'skipped'];

    public const OWNER_ROLE_LABELS = [
        'hr'         => 'HR',
        'manager'    => 'Manager',
        'admin'      => 'IT / Admin',
        'accountant' => 'Finance',
    ];

    /**
     * Auto-created for every new onboarding record — one row per bullet
     * in the "Include" checklist, each pre-assigned to the role that
     * owns it. 'admin' (Company Admin) stands in for IT/Admin and
     * 'accountant' for Finance — this app has no dedicated IT/Finance
     * role (confirmed with the user before building this module).
     */
    public const DEFAULT_TASKS = [
        ['type' => 'document_collection', 'title' => 'Collect ID proof, address proof & educational certificates', 'owner_role' => 'hr'],
        ['type' => 'payroll_setup',       'title' => 'Configure salary structure & statutory details (PF/ESI/PAN)', 'owner_role' => 'accountant'],
        ['type' => 'it_access_setup',     'title' => 'Create system login, email & software access', 'owner_role' => 'admin'],
        ['type' => 'asset_setup',         'title' => 'Issue laptop / ID card & workstation setup', 'owner_role' => 'admin'],
        ['type' => 'induction',           'title' => 'Conduct company induction & policy walkthrough', 'owner_role' => 'hr'],
        ['type' => 'manager_onboarding',  'title' => 'Manager introduction & role expectations briefing', 'owner_role' => 'manager'],
        ['type' => 'review_30',           'title' => '30-day check-in review', 'owner_role' => 'manager'],
        ['type' => 'review_60',           'title' => '60-day check-in review', 'owner_role' => 'manager'],
        ['type' => 'review_90',           'title' => '90-day check-in review', 'owner_role' => 'manager'],
    ];

    /**
     * Pure display grouping — the checklist read by WHEN it happens
     * relative to the join date, instead of WHO owns it (owner_role is
     * still shown per task, just no longer the primary grouping). Not
     * used for any validation/workflow logic, same spirit as
     * OnboardingRecordModel::STAGE_GROUPS — that constant groups the
     * separate 11-stage recruitment pipeline (Hiring/Pre-Joining/
     * Joining/Closed) and is intentionally not reused here even though
     * two of these names overlap; this one groups checklist TASKS, not
     * the candidate's recruitment STATUS.
     */
    public const PHASES = [
        'Pre-Joining'  => ['document_collection', 'payroll_setup', 'it_access_setup'],
        'Joining'      => ['asset_setup', 'induction', 'manager_onboarding'],
        'Post-Joining' => ['review_30', 'review_60', 'review_90'],
    ];

    public static function phaseFor(string $taskType): string
    {
        foreach (self::PHASES as $phase => $types) {
            if (in_array($taskType, $types, true)) {
                return $phase;
            }
        }

        return 'Post-Joining';
    }

    /**
     * Same relationships as PHASES above, keyed by OnboardingPhaseModel's
     * stable legacy_key instead of a display name — this is what
     * seedDefaultTasks() actually uses to look up a real phase_id, so a
     * renamed phase (name is admin-editable; legacy_key isn't) never
     * breaks a brand-new candidate's auto-created checklist.
     */
    public const TASK_TYPE_PHASE_KEY = [
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

    protected $table         = 'onboarding_tasks';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'onboarding_record_id', 'task_type', 'phase_id', 'title', 'owner_role', 'assigned_to',
        'status', 'due_date', 'completed_at', 'completed_by', 'notes',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'onboarding_record_id' => 'required|integer',
        'task_type'            => 'required|in_list[document_collection,payroll_setup,it_access_setup,asset_setup,induction,manager_onboarding,review_30,review_60,review_90,other]',
        'title'                => 'required|max_length[150]',
        'owner_role'           => 'required|in_list[hr,manager,admin,accountant]',
        'status'               => 'permit_empty|in_list[pending,in_progress,completed,skipped]',
        'due_date'             => 'permit_empty|valid_date',
    ];

    public function forRecord(int $onboardingRecordId): array
    {
        // phases.name is joined in as phase_name purely for display (the
        // one direct phaseFor()-style lookup outside the phase-grouping
        // loop, e.g. a "Pre-Joining" caption next to a task) — every
        // other field/behavior of a task row is unaffected.
        return $this->select('onboarding_tasks.*, onboarding_phases.name as phase_name')
            ->join('onboarding_phases', 'onboarding_phases.id = onboarding_tasks.phase_id', 'left')
            ->where('onboarding_record_id', $onboardingRecordId)
            ->orderBy('FIELD(onboarding_tasks.task_type,' . implode(',', array_map(static fn ($t) => "'{$t}'", self::TASK_TYPES)) . ')', '', false)
            ->findAll();
    }

    /**
     * Checklist completion % for several records in one query — feeds the
     * Onboarding list page's "Progress" column without an N+1 query per
     * row. 'completed'/'skipped' both count as done, same convention as
     * the Employee profile's task-progress calculation.
     */
    public function progressByRecord(array $recordIds): array
    {
        if ($recordIds === []) {
            return [];
        }

        $rows = $this->select('onboarding_record_id,
                COUNT(*) as total,
                SUM(CASE WHEN status IN ("completed", "skipped") THEN 1 ELSE 0 END) as done')
            ->whereIn('onboarding_record_id', $recordIds)
            ->groupBy('onboarding_record_id')
            ->findAll();

        $progress = [];
        foreach ($rows as $r) {
            $total = (int) $r['total'];
            $done  = (int) $r['done'];
            $progress[(int) $r['onboarding_record_id']] = [
                'done'  => $done,
                'total' => $total,
                'pct'   => $total > 0 ? (int) round($done / $total * 100) : 0,
            ];
        }

        return $progress;
    }

    public function seedDefaultTasks(int $onboardingRecordId): void
    {
        $phaseModel = new OnboardingPhaseModel();

        $rows = array_map(static function (array $t) use ($phaseModel) {
            return [
                'onboarding_record_id' => $onboardingRecordId,
                'task_type'            => $t['type'],
                // Looked up by the stable legacy_key, not a hardcoded id
                // — null (falls into the checklist's "Other" bucket) if
                // that phase has since been deactivated or deleted.
                'phase_id'             => $phaseModel->idForLegacyKey(self::TASK_TYPE_PHASE_KEY[$t['type']] ?? ''),
                'title'                => $t['title'],
                'owner_role'           => $t['owner_role'],
            ];
        }, self::DEFAULT_TASKS);

        $this->insertBatch($rows);
    }

    /**
     * Pending/in-progress tasks owned by $role across every onboarding
     * record the viewer can see — powers each role's "My Tasks" widget
     * on the onboarding dashboard.
     */
    public function pendingForRole(string $role, ?int $companyScope): array
    {
        $builder = $this->select('onboarding_tasks.*, onboarding_records.candidate_name, onboarding_records.company_id')
            ->join('onboarding_records', 'onboarding_records.id = onboarding_tasks.onboarding_record_id')
            ->where('onboarding_tasks.owner_role', $role)
            ->whereIn('onboarding_tasks.status', ['pending', 'in_progress']);

        if ($companyScope !== null) {
            $builder->where('onboarding_records.company_id', $companyScope);
        }

        return $builder->orderBy('onboarding_tasks.due_date', 'ASC')->findAll();
    }
}
