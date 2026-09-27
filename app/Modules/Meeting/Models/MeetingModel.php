<?php

namespace App\Modules\Meeting\Models;

use CodeIgniter\Model;

class MeetingModel extends Model
{
    protected $table            = 'meetings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'company_id', 'title', 'agenda', 'meeting_date', 'start_time',
        'end_time', 'location', 'mom', 'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'title'        => 'required|min_length[2]|max_length[200]',
        'company_id'   => 'required|integer',
        'meeting_date' => 'required|valid_date',
    ];

    /**
     * List with company name, filterable by company/date-range —
     * powers the Meeting index page and the dashboard's upcoming count.
     */
    public function filtered(array $filters = [])
    {
        $builder = $this->select('meetings.*, companies.name as company_name')
            ->join('companies', 'companies.id = meetings.company_id');

        // array_key_exists (not empty()) — 0 means "Company Admin has no
        // employee_profiles company assigned yet" and must match zero
        // rows, not fall through to showing every company's meetings.
        if (array_key_exists('company_id', $filters)) {
            $builder->where('meetings.company_id', $filters['company_id']);
        }
        if (! empty($filters['upcoming'])) {
            $builder->where('meetings.meeting_date >=', date('Y-m-d'));
        }
        if (! empty($filters['participant_user_id'])) {
            $builder->join('meeting_participants', 'meeting_participants.meeting_id = meetings.id')
                ->where('meeting_participants.user_id', $filters['participant_user_id']);
        }

        return $builder->orderBy('meetings.meeting_date', 'ASC')->orderBy('meetings.start_time', 'ASC');
    }

    public function withCompany(int $id): ?array
    {
        return $this->select('meetings.*, companies.name as company_name')
            ->join('companies', 'companies.id = meetings.company_id')
            ->where('meetings.id', $id)
            ->first();
    }

    /**
     * Pass $userId to scope to meetings the user is a participant in
     * (used for the Employee role's personal dashboard) instead of
     * every upcoming meeting org-wide. Pass $companyId for Company
     * Admin / Super Admin's active-company dashboard scoping.
     */
    public function upcomingCount(?int $userId = null, ?int $companyId = null): int
    {
        $builder = $this->where('meeting_date >=', date('Y-m-d'));

        if ($userId !== null) {
            $builder->join('meeting_participants', 'meeting_participants.meeting_id = meetings.id')
                ->where('meeting_participants.user_id', $userId);
        }
        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }

        return $builder->countAllResults();
    }

    public function upcomingForDashboard(int $limit = 5, ?int $userId = null, ?int $companyId = null): array
    {
        $filters = ['upcoming' => true];
        if ($companyId !== null) {
            $filters['company_id'] = $companyId;
        }

        $builder = $this->filtered($filters);

        if ($userId !== null) {
            $builder->join('meeting_participants', 'meeting_participants.meeting_id = meetings.id')
                ->where('meeting_participants.user_id', $userId);
        }

        return $builder->findAll($limit);
    }
}
