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
        // Left join (not inner) so a request from a user with no
        // employee_profiles row yet still shows up, just with a blank
        // Employee ID — filtering by company_id/department_id below is
        // unaffected, since a WHERE on a LEFT-joined NULL column still
        // excludes that row exactly as an INNER join would.
        $builder = $this->select('leave_requests.*, users.name as user_name, leave_types.name as leave_type_name,
                approver.name as approver_name, employee_profiles.employee_code as employee_code')
            ->join('users', 'users.id = leave_requests.user_id')
            ->join('leave_types', 'leave_types.id = leave_requests.leave_type_id')
            ->join('users as approver', 'approver.id = leave_requests.approved_by', 'left')
            ->join('employee_profiles', 'employee_profiles.user_id = leave_requests.user_id', 'left');

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
        if (array_key_exists('company_id', $filters)) {
            $builder->where('employee_profiles.company_id', $filters['company_id']);
        }
        if (! empty($filters['department_id'])) {
            $builder->where('employee_profiles.department_id', $filters['department_id']);
        }
        if (! empty($filters['q'])) {
            $builder->like('users.name', $filters['q']);
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

    /**
     * Approved days on an unpaid leave type (leave_types.is_paid = 0)
     * starting in $month/$year — feeds PayrollController::generate()'s
     * Loss-of-Pay calculation. Keyed by start_date's month/year, same
     * simplification approvedDaysThisYear() already uses (no cross-month
     * split for a leave spanning two months) rather than a real date-range
     * overlap, consistent with this model's existing "no half-day/
     * working-day logic in v1" scope.
     */
    public function unpaidDaysInMonth(int $userId, int $month, int $year): float
    {
        return (float) ($this->select('COALESCE(SUM(leave_requests.days), 0) as total')
            ->join('leave_types', 'leave_types.id = leave_requests.leave_type_id')
            ->where('leave_requests.user_id', $userId)
            ->where('leave_requests.status', 'approved')
            ->where('leave_types.is_paid', 0)
            ->where('MONTH(leave_requests.start_date)', $month)
            ->where('YEAR(leave_requests.start_date)', $year)
            ->first()['total'] ?? 0);
    }

    /**
     * Days in $year already taken or requested (pending + approved) on one leave type - what a new request has to fit
     * into together with the type's annual quota. Keyed by start date's year, like approvedDaysThisYear().
     */
    public function committedDays(int $userId, int $leaveTypeId, int $year): float
    {
        return (float) ($this->selectSum('days')
            ->where('user_id', $userId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereIn('status', ['pending', 'approved'])
            ->where('YEAR(start_date)', $year)
            ->first()['days'] ?? 0);
    }
}
