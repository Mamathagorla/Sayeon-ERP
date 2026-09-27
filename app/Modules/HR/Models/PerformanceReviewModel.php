<?php

namespace App\Modules\HR\Models;

use CodeIgniter\Model;

class PerformanceReviewModel extends Model
{
    public const STATUSES = ['draft', 'submitted', 'acknowledged'];

    protected $table         = 'performance_reviews';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'cycle_id', 'user_id', 'reviewer_id', 'rating', 'strengths',
        'improvements', 'goals_next', 'status', 'submitted_at', 'acknowledged_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // reviewer_id is set server-side from the logged-in user, not
    // submitted via the form, so it's deliberately excluded here —
    // the controller validates this ruleset against raw POST data.
    protected $validationRules = [
        'cycle_id' => 'required|integer',
        'user_id'  => 'required|integer',
        'rating'   => 'permit_empty|integer|greater_than[0]|less_than_equal_to[5]',
    ];

    /**
     * DB has a unique(cycle_id, user_id) key too, but checking here
     * lets the controller show a friendly error instead of a raw
     * duplicate-key exception.
     */
    public function alreadyReviewed(int $cycleId, int $userId): bool
    {
        return $this->where('cycle_id', $cycleId)->where('user_id', $userId)->countAllResults() > 0;
    }

    public function filtered(array $filters = [])
    {
        // employee_profiles/departments joins are purely additive
        // (LEFT — a reviewee without a profile yet still returns their
        // review row, just with nulls for these two columns) and only
        // used for display (Super Admin's performance list shows
        // Designation/Department); they don't change which rows this
        // returns or any filter/permission behavior below.
        $builder = $this->select('performance_reviews.*, users.name as user_name, reviewer.name as reviewer_name,
                review_cycles.name as cycle_name, review_cycles.start_date as cycle_start_date,
                review_cycles.end_date as cycle_end_date, employee_profiles.designation as employee_designation,
                departments.name as department_name')
            ->join('users', 'users.id = performance_reviews.user_id')
            ->join('users as reviewer', 'reviewer.id = performance_reviews.reviewer_id')
            ->join('review_cycles', 'review_cycles.id = performance_reviews.cycle_id')
            ->join('employee_profiles', 'employee_profiles.user_id = performance_reviews.user_id', 'left')
            ->join('departments', 'departments.id = employee_profiles.department_id', 'left');

        if (! empty($filters['user_id'])) {
            // Array (the Performance list's employee-centric aggregation)
            // vs a single id — every other existing caller still passes
            // a plain int/string.
            is_array($filters['user_id'])
                ? $builder->whereIn('performance_reviews.user_id', $filters['user_id'])
                : $builder->where('performance_reviews.user_id', $filters['user_id']);
        }
        if (! empty($filters['reviewer_id'])) {
            $builder->where('performance_reviews.reviewer_id', $filters['reviewer_id']);
        }
        if (! empty($filters['cycle_id'])) {
            $builder->where('performance_reviews.cycle_id', $filters['cycle_id']);
        }
        if (! empty($filters['status'])) {
            $builder->where('performance_reviews.status', $filters['status']);
        }

        return $builder->orderBy('performance_reviews.created_at', 'DESC');
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('performance_reviews.id', $id)->first();
    }
}
