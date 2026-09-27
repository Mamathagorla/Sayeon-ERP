<?php

namespace App\Modules\Todo\Models;

use CodeIgniter\Model;

class TodoModel extends Model
{
    public const PRIORITIES = ['normal', 'important'];
    public const FILTERS    = ['all', 'today', 'upcoming', 'overdue', 'starred', 'important', 'normal', 'pending', 'completed', 'trashed'];
    public const SORTS      = ['default', 'newest', 'oldest', 'due', 'title'];

    protected $table         = 'todos';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'user_id', 'title', 'description', 'due_date', 'priority',
        'is_starred', 'is_completed', 'completed_at', 'is_trashed', 'trashed_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'title'    => 'required|min_length[1]|max_length[150]',
        'priority' => 'permit_empty|in_list[normal,important]',
        'due_date' => 'permit_empty|valid_date',
    ];

    /**
     * The only lookup every controller action uses to load a single
     * row — scoping by user_id here (not just id) is what stops one
     * user reaching another user's to-do by editing the id in the URL,
     * same IDOR-proofing precedent as NotificationModel::markRead().
     */
    public function findForUser(int $id, int $userId): ?array
    {
        return $this->where('id', $id)->where('user_id', $userId)->first();
    }

    /**
     * Builds the shared filter/search/date-range conditions on a fresh
     * builder — used by both forUser() (paged results) and countForUser()
     * (the sidebar/KPI badges), so a count can never drift from what the
     * matching page of results actually shows. Deliberately NOT built on
     * $this (the model's own builder), since the same conditions get
     * applied to two independent queries (count, then a separate
     * limited/ordered fetch) and reusing one builder across both would
     * carry orderBy/limit from one into the other.
     */
    private function filteredBuilder(int $userId, string $filter, string $search, string $from, string $to)
    {
        $today   = date('Y-m-d');
        $builder = $this->db->table('todos')->where('user_id', $userId);

        switch ($filter) {
            case 'today':
                $builder->where('due_date', $today)->where('is_completed', 0)->where('is_trashed', 0);
                break;
            case 'upcoming':
                $builder->where('due_date >', $today)->where('is_completed', 0)->where('is_trashed', 0);
                break;
            case 'overdue':
                $builder->where('due_date <', $today)->where('is_completed', 0)->where('is_trashed', 0);
                break;
            case 'normal':
                $builder->where('priority', 'normal')->where('is_trashed', 0);
                break;
            case 'starred':
                $builder->where('is_starred', 1)->where('is_trashed', 0);
                break;
            case 'important':
                $builder->where('priority', 'important')->where('is_trashed', 0);
                break;
            case 'pending':
                $builder->where('is_completed', 0)->where('is_trashed', 0);
                break;
            case 'completed':
                $builder->where('is_completed', 1)->where('is_trashed', 0);
                break;
            case 'trashed':
                $builder->where('is_trashed', 1);
                break;
            default:
                $builder->where('is_trashed', 0);
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('title', $search)
                ->orLike('description', $search)
                ->groupEnd();
        }

        // Created-date range (inclusive of the whole "to" day).
        if ($from !== '') {
            $builder->where('created_at >=', $from . ' 00:00:00');
        }
        if ($to !== '') {
            $builder->where('created_at <=', $to . ' 23:59:59');
        }

        return $builder;
    }

    /**
     * One page of results for the given filter/search/date-range —
     * $page is 1-based. Pair with countForUser() (same arguments minus
     * page/perPage) to render "Showing X–Y of Z" and page links.
     */
    public function forUser(int $userId, string $filter = 'all', string $search = '', string $sort = 'default', string $from = '', string $to = '', int $page = 1, int $perPage = 8): array
    {
        $builder = $this->filteredBuilder($userId, $filter, $search, $from, $to);

        switch ($sort) {
            case 'newest':
                $builder->orderBy('created_at', 'DESC');
                break;
            case 'oldest':
                $builder->orderBy('created_at', 'ASC');
                break;
            case 'due':
                $builder->orderBy('due_date IS NULL', 'ASC', false)->orderBy('due_date', 'ASC');
                break;
            case 'title':
                $builder->orderBy('title', 'ASC');
                break;
            default:
                $builder->orderBy('is_completed', 'ASC')->orderBy('due_date IS NULL', 'ASC', false)->orderBy('due_date', 'ASC')->orderBy('created_at', 'DESC');
        }

        return $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
    }

    public function countForUser(int $userId, string $filter = 'all', string $search = '', string $from = '', string $to = ''): int
    {
        return $this->filteredBuilder($userId, $filter, $search, $from, $to)->countAllResults();
    }

    /**
     * Sidebar nav / KPI badges — one query per bucket, scoped the same
     * way forUser() is, so a badge always matches what tapping it shows.
     * 'pending' and 'overdue' are mutually exclusive (pending excludes
     * anything already overdue) so the three top-row KPI cards
     * (pending + overdue + completed) always sum to 'all'.
     */
    public function countsFor(int $userId): array
    {
        $today = date('Y-m-d');
        $open  = static fn ($b) => $b->where('is_completed', 0)->where('is_trashed', 0);

        return [
            'all'       => $this->where('user_id', $userId)->where('is_trashed', 0)->countAllResults(),
            'today'     => $open($this->where('user_id', $userId))->where('due_date', $today)->countAllResults(),
            'upcoming'  => $open($this->where('user_id', $userId))->where('due_date >', $today)->countAllResults(),
            'overdue'   => $open($this->where('user_id', $userId))->where('due_date <', $today)->countAllResults(),
            'starred'   => $this->where('user_id', $userId)->where('is_trashed', 0)->where('is_starred', 1)->countAllResults(),
            'important' => $this->where('user_id', $userId)->where('is_trashed', 0)->where('priority', 'important')->countAllResults(),
            'normal'    => $this->where('user_id', $userId)->where('is_trashed', 0)->where('priority', 'normal')->countAllResults(),
            'pending'   => $open($this->where('user_id', $userId))->groupStart()->where('due_date', null)->orWhere('due_date >=', $today)->groupEnd()->countAllResults(),
            'completed' => $this->where('user_id', $userId)->where('is_trashed', 0)->where('is_completed', 1)->countAllResults(),
            'trashed'   => $this->where('user_id', $userId)->where('is_trashed', 1)->countAllResults(),
        ];
    }
}
