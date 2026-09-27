<?php

namespace App\Modules\HR\Models;

use CodeIgniter\Model;

class LeaveRequestModel extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected $table         = 'leave_requests';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'user_id', 'leave_type_id', 'start_date', 'end_date', 'days',
        'reason', 'status', 'approved_by', 'approved_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'leave_type_id' => 'required|integer',
        'start_date'    => 'required|valid_date',
        'end_date'      => 'required|valid_date',
    ];

    /**
     * Inclusive calendar-day count between the two dates. No half-day
     * or working-day-only logic in v1 — see HR module notes.
     */
    public function calculateDays(string $startDate, string $endDate): float
    {
        $start = new \DateTime($startDate);
        $end   = new \DateTime($endDate);

        return (float) ($start->diff($end)->days + 1);
    }

    public function filtered(array $filters = [])
    {
        $builder = $this->select('leave_requests.*, users.name as user_name, leave_types.name as leave_type_name,
                approver.name as approver_name')
            ->join('users', 'users.id = leave_requests.user_id')
            ->join('leave_types', 'leave_types.id = leave_requests.leave_type_id')
            ->join('users as approver', 'approver.id = leave_requests.approved_by', 'left');

        if (! empty($filters['user_id'])) {
            $builder->where('leave_requests.user_id', $filters['user_id']);
        }
        if (! empty($filters['status'])) {
            is_array($filters['status'])
                ? $builder->whereIn('leave_requests.status', $filters['status'])
                : $builder->where('leave_requests.status', $filters['status']);
        }
        if (! empty($filters['leave_type_id'])) {
            is_array($filters['leave_type_id'])
                ? $builder->whereIn('leave_requests.leave_type_id', $filters['leave_type_id'])
                : $builder->where('leave_requests.leave_type_id', $filters['leave_type_id']);
        }
        if (! empty($filters['year'])) {
            $builder->where('YEAR(leave_requests.start_date)', $filters['year']);
        }
        // Present (even as 0) only when the caller wants company scoping —
        // 0 means "approver has no company assigned yet", which should
        // match zero rows rather than falling through to showing everyone.
        if (array_key_exists('company_id', $filters)) {
            $builder->join('employee_profiles', 'employee_profiles.user_id = leave_requests.user_id')
                ->where('employee_profiles.company_id', $filters['company_id']);
        }
        // Restricts to requesters whose role is within the approver's
        // authority (see LeaveController::ROLE_RANK). An empty array
        // means "no rank the caller can act on" — must match zero rows.
        if (isset($filters['requester_role_slugs'])) {
            $builder->join('roles', 'roles.id = users.role_id');

            if ($filters['requester_role_slugs'] === []) {
                $builder->where('1', '0');
            } else {
                $builder->whereIn('roles.slug', $filters['requester_role_slugs']);
            }
        }

        return $builder->orderBy('leave_requests.created_at', 'DESC');
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('leave_requests.id', $id)->first();
    }

    /**
     * Days already approved this calendar year, for the given leave
     * type — quota minus this is the employee's remaining balance.
     */
    public function approvedDaysThisYear(int $userId, int $leaveTypeId): float
    {
        return (float) ($this->selectSum('days')
            ->where('user_id', $userId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('status', 'approved')
            ->where('YEAR(start_date)', date('Y'))
            ->first()['days'] ?? 0);
    }
}
