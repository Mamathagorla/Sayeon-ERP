<?php

namespace App\Modules\Meeting\Models;

use CodeIgniter\Model;

class MeetingModel extends Model
{
    public const PROVIDERS = ['none', 'google_meet', 'zoom'];
    public const SYNC_STATUSES = ['not_synced', 'synced', 'sync_failed', 'cancelled'];

    protected $table            = 'meetings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'company_id', 'title', 'agenda', 'meeting_date', 'start_time',
        'end_time', 'location', 'meeting_link', 'join_token', 'recurring_meeting_id',
        'occurrence_number', 'mom', 'created_by', 'provider', 'provider_meeting_id', 'end_date', 'timezone',
        'sync_status', 'sync_error',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'title'        => 'required|min_length[2]|max_length[200]',
        'company_id'   => 'required|integer',
        'meeting_date' => 'required|valid_date',
        'provider'     => 'permit_empty|in_list[none,google_meet,zoom]',
    ];

    protected $beforeInsert = ['assignJoinToken'];

    /**
     * The unique, unguessable token behind every meeting's join link —
     * always system-generated, never accepted from a form (join_token is
     * in $allowedFields only so bulkAdd-style internal updates can touch
     * it if ever needed; every real insert path goes through here, not a
     * caller-supplied value). See joinUrl()/2026-09-29-160001_
     * AddJoinTokenToMeetings for why this exists.
     */
    protected function assignJoinToken(array $data): array
    {
        if (empty($data['data']['join_token'])) {
            $data['data']['join_token'] = bin2hex(random_bytes(20));
        }

        return $data;
    }

    /**
     * A meeting this organizer created in the last few minutes with the same
     * company, title, provider and schedule: what a retried or double-submitted
     * form would otherwise duplicate (and, with a provider, create a second
     * external event for).
     *
     * @param array{meeting_date: string, start_time: ?string, end_time: ?string} $when
     */
    public function recentDuplicate(int $createdBy, int $companyId, string $title, array $when, string $provider, int $withinMinutes = 10): ?array
    {
        $builder = $this->where('created_by', $createdBy)
            ->where('company_id', $companyId)
            ->where('title', $title)
            ->where('provider', $provider)
            ->where('meeting_date', $when['meeting_date'])
            ->where('created_at >=', date('Y-m-d H:i:s', time() - $withinMinutes * 60));

        foreach (['start_time', 'end_time'] as $column) {
            $when[$column] === null ? $builder->where($column . ' IS NULL', null, false) : $builder->where($column, $when[$column]);
        }

        return $builder->orderBy('id', 'DESC')->first();
    }

    /**
     * The link every participant is actually sent — includes the token,
     * so it can't be guessed by walking meeting ids, and is what
     * MeetingController::join() checks against before granting access.
     */
    public function joinUrl(array $meeting): string
    {
        return site_url('meetings/' . $meeting['id'] . '/join/' . $meeting['join_token']);
    }

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
