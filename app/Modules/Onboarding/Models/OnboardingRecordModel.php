<?php

namespace App\Modules\Onboarding\Models;

use CodeIgniter\Model;

class OnboardingRecordModel extends Model
{
    // Ordered — index+1 is "the next stage" (see nextStatus()). 'withdrawn'
    // is a deliberate addition beyond the requested 11 stages: an exit
    // path for a candidate who declines/fails BGV/is rejected, kept out
    // of the linear sequence since it isn't "next" from anywhere.
    public const STATUSES = [
        'selected', 'offer_sent', 'offer_accepted', 'documents_pending', 'bgv',
        'hr_verification', 'ready_to_join', 'joined', 'induction', 'probation', 'confirmed',
    ];
    public const WITHDRAWN = 'withdrawn';

    public const STAGE_LABELS = [
        'selected'          => 'Selected',
        'offer_sent'        => 'Offer Sent',
        'offer_accepted'    => 'Offer Accepted',
        'documents_pending' => 'Documents Pending',
        'bgv'               => 'BGV',
        'hr_verification'   => 'HR Verification',
        'ready_to_join'     => 'Ready to Join',
        'joined'            => 'Joined',
        'induction'         => 'Induction',
        'probation'         => 'Probation',
        'confirmed'         => 'Confirmed',
        'withdrawn'         => 'Withdrawn',
    ];

    public const BGV_STATUSES = ['not_started', 'in_progress', 'cleared', 'flagged'];

    /**
     * Pure display grouping — the 11 linear stages (+ withdrawn) collapsed
     * into 4 phases so the pipeline reads as a small number of milestones
     * instead of a wall of equal-weight tabs. Not used for any validation
     * or workflow logic (STATUSES/nextStatus() above are still the single
     * source of truth for the actual stage sequence) — this only decides
     * how the UI clusters and labels them.
     */
    public const STAGE_GROUPS = [
        'Hiring'      => ['selected', 'offer_sent', 'offer_accepted'],
        'Pre-Joining' => ['documents_pending', 'bgv', 'hr_verification', 'ready_to_join'],
        'Joining'     => ['joined', 'induction', 'probation', 'confirmed'],
        'Closed'      => ['withdrawn'],
    ];

    public static function groupFor(string $status): string
    {
        foreach (self::STAGE_GROUPS as $group => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $group;
            }
        }

        return 'Hiring';
    }

    /**
     * The 11 stages collapsed further, to 3 buckets — powers the
     * Onboarding list page's simplified KPI row/Status filter/column,
     * which cares about "has the checklist started/finished" rather than
     * the exact stage. 'withdrawn' deliberately has no bucket: those
     * candidates are excluded from this simplified view entirely (see
     * OnboardingController::index()), same as they were never part of
     * the Total/Not Started/In Progress/Completed counts.
     */
    public const SIMPLE_BUCKETS = [
        'not_started' => ['selected', 'offer_sent', 'offer_accepted', 'documents_pending', 'bgv', 'hr_verification', 'ready_to_join'],
        'in_progress' => ['joined', 'induction', 'probation'],
        'completed'   => ['confirmed'],
    ];

    public static function bucketFor(string $status): ?string
    {
        foreach (self::SIMPLE_BUCKETS as $bucket => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $bucket;
            }
        }

        return null;
    }

    protected $table         = 'onboarding_records';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'candidate_name', 'candidate_email', 'candidate_phone',
        'department_id', 'designation', 'offered_ctc', 'reporting_manager_id', 'employee_profile_id',
        'status', 'offer_sent_at', 'offer_accepted_at', 'bgv_status', 'bgv_notes',
        'hr_verified_by', 'hr_verified_at', 'joining_date', 'joined_at',
        'probation_end_date', 'confirmed_at', 'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'company_id'      => 'required|integer',
        // Same person-name / phone conventions used across the app
        // (EmployeeProfileModel.emergency_contact_name, UserModel.name).
        'candidate_name'  => 'required|min_length[2]|max_length[150]|regex_match[/^[\p{L}\s.\'-]+$/u]',
        'candidate_email' => 'required|valid_email',
        'candidate_phone' => 'permit_empty|regex_match[/^[0-9]{10}$/]',
        'designation'     => 'permit_empty|max_length[100]',
        'offered_ctc'     => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[9999999999.99]',
        'status'          => 'permit_empty|in_list[selected,offer_sent,offer_accepted,documents_pending,bgv,hr_verification,ready_to_join,joined,induction,probation,confirmed,withdrawn]',
        'bgv_status'      => 'permit_empty|in_list[not_started,in_progress,cleared,flagged]',
        'joining_date'    => 'permit_empty|valid_date',
    ];

    protected $validationMessages = [
        'candidate_name' => [
            'regex_match' => 'Name may only contain letters, spaces, apostrophes, hyphens and periods.',
        ],
        'candidate_phone' => [
            'regex_match' => 'Phone number must be exactly 10 digits.',
        ],
    ];

    public function nextStatus(string $current): ?string
    {
        $index = array_search($current, self::STATUSES, true);

        if ($index === false || ! isset(self::STATUSES[$index + 1])) {
            return null;
        }

        return self::STATUSES[$index + 1];
    }

    public function filtered(array $filters = [])
    {
        $builder = $this->select('onboarding_records.*, companies.name as company_name,
                departments.name as department_name, manager.name as manager_name,
                employee_profiles.employee_code as employee_code, employee_profiles.employment_type as employment_type')
            ->join('companies', 'companies.id = onboarding_records.company_id')
            ->join('departments', 'departments.id = onboarding_records.department_id', 'left')
            ->join('users as manager', 'manager.id = onboarding_records.reporting_manager_id', 'left')
            ->join('employee_profiles', 'employee_profiles.id = onboarding_records.employee_profile_id', 'left');

        // array_key_exists (not empty()) — 0 means "Company Admin has no
        // employee_profiles company assigned yet" and must match zero
        // rows, same convention as every other module's filtered().
        if (array_key_exists('company_id', $filters)) {
            $builder->where('onboarding_records.company_id', $filters['company_id']);
        }
        if (! empty($filters['status'])) {
            // Array (e.g. a SIMPLE_BUCKETS status list) vs a single exact
            // stage — the Onboarding list page passes an array, every
            // other existing caller still passes a plain string.
            is_array($filters['status'])
                ? $builder->whereIn('onboarding_records.status', $filters['status'])
                : $builder->where('onboarding_records.status', $filters['status']);
        }
        if (! empty($filters['reporting_manager_id'])) {
            $builder->where('onboarding_records.reporting_manager_id', $filters['reporting_manager_id']);
        }
        if (! empty($filters['department_id'])) {
            $builder->where('onboarding_records.department_id', $filters['department_id']);
        }
        if (! empty($filters['q'])) {
            $builder->like('onboarding_records.candidate_name', $filters['q']);
        }
        if (! empty($filters['joining_from'])) {
            $builder->where('onboarding_records.joining_date >=', $filters['joining_from']);
        }

        return $builder->orderBy('onboarding_records.created_at', 'DESC');
    }

    /**
     * Not Started / In Progress / Completed counts for the Onboarding
     * list page's KPI row — 'withdrawn' candidates are excluded (not
     * folded into any bucket), same as they're excluded from the list
     * itself.
     */
    public function simpleCounts(?int $companyScope): array
    {
        $builder = $this->select('status, COUNT(*) as total')->groupBy('status');

        if ($companyScope !== null) {
            $builder->where('company_id', $companyScope);
        }

        $byStatus = array_column($builder->findAll(), 'total', 'status');

        $counts = ['not_started' => 0, 'in_progress' => 0, 'completed' => 0];
        foreach ($byStatus as $status => $total) {
            $bucket = self::bucketFor($status);
            if ($bucket !== null) {
                $counts[$bucket] += (int) $total;
            }
        }

        return $counts;
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('onboarding_records.id', $id)->first();
    }

    /**
     * Stage-funnel counts for the dashboard — every status (including
     * 'withdrawn') against the viewer's company scope, zero-filled so
     * a stage with no candidates still renders as 0 rather than being
     * silently absent.
     */
    public function countsByStatus(?int $companyScope): array
    {
        $builder = $this->select('status, COUNT(*) as total')->groupBy('status');

        if ($companyScope !== null) {
            $builder->where('company_id', $companyScope);
        }

        $rows   = array_column($builder->findAll(), 'total', 'status');
        $counts = [];

        foreach (array_merge(self::STATUSES, [self::WITHDRAWN]) as $status) {
            $counts[$status] = (int) ($rows[$status] ?? 0);
        }

        return $counts;
    }
}
