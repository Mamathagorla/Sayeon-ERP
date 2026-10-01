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
        'user_id', 'parent_id', 'title', 'description', 'due_date', 'priority',
        'is_starred', 'is_completed', 'completed_at', 'is_trashed', 'trashed_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'title'    => 'required|min_length[1]|max_length[150]',
        'priority' => 'permit_empty|in_list[normal,important]',
        'due_date' => 'permit_empty|valid_date',
        'parent_id' => 'permit_empty|integer',
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

        // Every filter except 'trashed' is top-level only — a Sub-To-Do
        // lives nested under its Parent (see childrenFor()) rather than
        // as its own row here. 'trashed' is the one deliberate exception:
        // it's a flat bin of everything deleted, parent or child, so a
        // Sub-To-Do trashed on its own (its Parent left active) is still
        // reachable to restore or permanently delete — nesting it under
        // a non-trashed Parent here would hide it from Trash entirely.
        if ($filter !== 'trashed') {
            $builder->where('parent_id', null);
        }

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
            // Matches on the row's own title/description, OR on any of
            // its Sub-To-Dos' — searching "cake" should surface a
            // "Birthday" parent whose only match is its child "Buy
            // cake", not just parents whose own text happens to match.
            $builder->groupStart()
                ->like('title', $search)
                ->orLike('description', $search)
                ->orWhereIn('id', static function ($sub) use ($userId, $search) {
                    return $sub->select('parent_id')->from('todos')
                        ->where('user_id', $userId)
                        ->where('parent_id IS NOT NULL')
                        ->groupStart()->like('title', $search)->orLike('description', $search)->groupEnd();
                })
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
        // Top-level only (see filteredBuilder()) — a Sub-To-Do isn't
        // counted a second time on top of the Parent it belongs to.
        $base  = fn () => $this->where('user_id', $userId)->where('parent_id', null);
        $open  = static fn ($b) => $b->where('is_completed', 0)->where('is_trashed', 0);

        return [
            'all'       => $base()->where('is_trashed', 0)->countAllResults(),
            'today'     => $open($base())->where('due_date', $today)->countAllResults(),
            'upcoming'  => $open($base())->where('due_date >', $today)->countAllResults(),
            'overdue'   => $open($base())->where('due_date <', $today)->countAllResults(),
            'starred'   => $base()->where('is_trashed', 0)->where('is_starred', 1)->countAllResults(),
            'important' => $base()->where('is_trashed', 0)->where('priority', 'important')->countAllResults(),
            'normal'    => $base()->where('is_trashed', 0)->where('priority', 'normal')->countAllResults(),
            'pending'   => $open($base())->groupStart()->where('due_date', null)->orWhere('due_date >=', $today)->groupEnd()->countAllResults(),
            'completed' => $base()->where('is_trashed', 0)->where('is_completed', 1)->countAllResults(),
            // Flat count, parent_id notwithstanding — matches the
            // 'trashed' exception in filteredBuilder() above, so this
            // badge always agrees with what the Trash filter shows.
            'trashed'   => $this->where('user_id', $userId)->where('is_trashed', 1)->countAllResults(),
        ];
    }

    /**
     * Every Sub-To-Do for the given Parent To-Do ids, grouped by
     * parent_id — one query for a whole page of parents rather than one
     * per row. Sub-To-Dos travel with their parent's trashed state (see
     * TodoController::trash()/restore(), which cascade explicitly), so
     * this deliberately does NOT filter is_trashed — the view renders
     * each child with the same status logic as a top-level row.
     */
    public function childrenFor(array $parentIds, int $userId): array
    {
        if ($parentIds === []) {
            return [];
        }

        $rows = $this->where('user_id', $userId)
            ->whereIn('parent_id', $parentIds)
            ->orderBy('is_completed', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->findAll();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['parent_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * "2/4 completed" for one Parent To-Do — same shape as
     * TaskChecklistItemModel::progressFor() in the Task module, the
     * closest existing precedent for a parent/children completion
     * rollup in this app. A trashed Sub-To-Do no longer counts (it's
     * effectively removed from the list), so total/done can both drop
     * if one is trashed individually.
     */
    public function progressFor(array $children): array
    {
        $active = array_filter($children, static fn ($c) => ! $c['is_trashed']);
        $total  = count($active);
        $done   = count(array_filter($active, static fn ($c) => (int) $c['is_completed'] === 1));

        return ['total' => $total, 'done' => $done, 'percent' => $total > 0 ? (int) round($done / $total * 100) : 0];
    }

    /**
     * True only if $id is a Parent-eligible To-Do owned by $userId — it
     * exists, isn't trashed, and (one level of nesting only, matching
     * the "Birthday -> Buy cake / ..." example) doesn't itself already
     * have a parent. TodoController::store() checks this before letting
     * a posted parent_id attach a new Sub-To-Do to it.
     */
    public function isEligibleParent(int $id, int $userId): bool
    {
        $todo = $this->where('id', $id)->where('user_id', $userId)->where('is_trashed', 0)->first();

        return $todo !== null && $todo['parent_id'] === null;
    }
}
