<?php

namespace App\Modules\Todo\Controllers;

use App\Controllers\BaseController;
use App\Modules\Todo\Models\TodoModel;

/**
 * Personal quick reminders, open to every authenticated role — no
 * permission gate beyond the global 'auth' filter (see Routes.php),
 * same precedent as the Notification module. Every action re-derives
 * the current user from the session and every model lookup filters by
 * that user_id (TodoModel::findForUser()), so there's nothing here
 * that trusts an id in the URL alone.
 */
class TodoController extends BaseController
{
    protected TodoModel $todoModel;

    public function __construct()
    {
        $this->todoModel = new TodoModel();
    }

    private const PER_PAGE = 8;

    public function index()
    {
        $userId = (int) $this->currentUserId();
        $filter = $this->request->getGet('filter') ?: 'all';
        $search = trim((string) $this->request->getGet('q'));

        if (! in_array($filter, TodoModel::FILTERS, true)) {
            $filter = 'all';
        }

        $sort = (string) $this->request->getGet('sort');
        if (! in_array($sort, TodoModel::SORTS, true)) {
            $sort = 'default';
        }

        // Only well-formed Y-m-d values reach the query.
        $dateOrEmpty = static fn ($v): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';
        $from = $dateOrEmpty($this->request->getGet('from'));
        $to   = $dateOrEmpty($this->request->getGet('to'));

        $total = $this->todoModel->countForUser($userId, $filter, $search, $from, $to);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = max(1, min($pages, (int) ($this->request->getGet('page') ?: 1)));

        $counts = $this->todoModel->countsFor($userId);
        $todos  = $this->todoModel->forUser($userId, $filter, $search, $sort, $from, $to, $page, self::PER_PAGE);

        // One query for every Parent To-Do on this page's Sub-To-Dos,
        // then a progress rollup per parent computed from that — not
        // one query per row. Skipped for 'trashed': that filter already
        // returns every trashed row flat, parent or child alike (see
        // TodoModel::filteredBuilder()), so nesting them here too would
        // render an independently-trashed Sub-To-Do twice.
        $childrenByParent = $filter !== 'trashed' ? $this->todoModel->childrenFor(array_column($todos, 'id'), $userId) : [];
        $progressByParent = [];
        foreach ($childrenByParent as $parentId => $children) {
            $progressByParent[$parentId] = $this->todoModel->progressFor($children);
        }

        return view('App\Modules\Todo\index', [
            'title'     => 'To-Do List',
            'navActive' => 'todos',
            'todos'     => $todos,
            'childrenByParent' => $childrenByParent,
            'progressByParent' => $progressByParent,
            'counts'    => $counts,
            // Mutually exclusive with completed/overdue, so the three
            // KPI cards always sum to counts['all'] — see countsFor().
            'pendingCount' => $counts['pending'],
            'filter'    => $filter,
            'search'    => $search,
            'sort'      => $sort,
            'from'      => $from,
            'to'        => $to,
            'page'      => $page,
            'pages'     => $pages,
            'total'     => $total,
            'perPage'   => self::PER_PAGE,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Todo\form', [
            'title'      => 'Add To-Do',
            'navActive'  => 'todos',
            'todo'       => null,
            'priorities' => TodoModel::PRIORITIES,
        ]);
    }

    public function store()
    {
        if (! $this->validate($this->todoModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userId   = (int) $this->currentUserId();
        $parentId = $this->request->getPost('parent_id') ?: null;

        // A Sub-To-Do only ever attaches to a Parent To-Do the current
        // user actually owns and that isn't itself already a Sub-To-Do
        // (one level of nesting) — silently dropping an invalid/spoofed
        // parent_id rather than erroring, since the quick-add bar posts
        // no parent_id at all in the common case and that must keep
        // creating an ordinary top-level to-do.
        if ($parentId !== null && ! $this->todoModel->isEligibleParent((int) $parentId, $userId)) {
            $parentId = null;
        }

        $id = $this->todoModel->insert([
            'user_id'     => $userId,
            'parent_id'   => $parentId,
            'title'       => $this->request->getPost('title'),
            'description' => $this->request->getPost('description'),
            'due_date'    => $this->request->getPost('due_date') ?: null,
            'priority'    => $this->request->getPost('priority') ?: 'normal',
            'is_starred'  => $this->request->getPost('is_starred') === '1' ? 1 : 0,
        ]);

        $this->logActivity('todo', 'create', $id, ($parentId ? 'Added sub-to-do: ' : 'Added to-do: ') . $this->request->getPost('title'));

        // The inline quick-add bar / "+ Add sub-to-do" row both post from
        // the list page itself and set quick_add=1 so the response goes
        // back to that same URL (filter/sort/page/search all preserved)
        // via the browser's own Referer header — same redirect()->back()
        // convention as toggle-complete/trash/etc below. The full-page
        // create() form (still reachable, unchanged) has no such field,
        // so it keeps the original redirect to the plain list.
        if ($this->request->getPost('quick_add') === '1') {
            return redirect()->back()->with('success', $parentId ? 'Sub-to-do added.' : 'To-do added.');
        }

        return redirect()->to('/todos')->with('success', 'To-do added.');
    }

    public function edit(int $id)
    {
        $todo = $this->todoModel->findForUser($id, (int) $this->currentUserId());

        if ($todo === null) {
            return redirect()->to('/todos')->with('error', 'To-do not found.');
        }

        return view('App\Modules\Todo\form', [
            'title'      => 'Edit To-Do',
            'navActive'  => 'todos',
            'todo'       => $todo,
            'priorities' => TodoModel::PRIORITIES,
        ]);
    }

    public function update(int $id)
    {
        $todo = $this->todoModel->findForUser($id, (int) $this->currentUserId());

        if ($todo === null) {
            return redirect()->to('/todos')->with('error', 'To-do not found.');
        }

        if (! $this->validate($this->todoModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->todoModel->update($id, [
            'title'       => $this->request->getPost('title'),
            'description' => $this->request->getPost('description'),
            'due_date'    => $this->request->getPost('due_date') ?: null,
            'priority'    => $this->request->getPost('priority') ?: 'normal',
            'is_starred'  => $this->request->getPost('is_starred') === '1' ? 1 : 0,
        ]);

        $this->logActivity('todo', 'update', $id, 'Updated to-do: ' . $this->request->getPost('title'));

        return redirect()->to('/todos')->with('success', 'To-do updated.');
    }

    public function toggleComplete(int $id)
    {
        $todo = $this->todoModel->findForUser($id, (int) $this->currentUserId());

        if ($todo === null) {
            return redirect()->to('/todos')->with('error', 'To-do not found.');
        }

        $completed = ! $todo['is_completed'];
        $this->todoModel->update($id, [
            'is_completed' => $completed ? 1 : 0,
            'completed_at' => $completed ? date('Y-m-d H:i:s') : null,
        ]);

        return redirect()->back();
    }

    public function toggleStar(int $id)
    {
        $todo = $this->todoModel->findForUser($id, (int) $this->currentUserId());

        if ($todo === null) {
            return redirect()->to('/todos')->with('error', 'To-do not found.');
        }

        $this->todoModel->update($id, ['is_starred' => $todo['is_starred'] ? 0 : 1]);

        return redirect()->back();
    }

    public function toggleImportant(int $id)
    {
        $todo = $this->todoModel->findForUser($id, (int) $this->currentUserId());

        if ($todo === null) {
            return redirect()->to('/todos')->with('error', 'To-do not found.');
        }

        $this->todoModel->update($id, ['priority' => $todo['priority'] === 'important' ? 'normal' : 'important']);

        return redirect()->back();
    }

    public function trash(int $id)
    {
        $userId = (int) $this->currentUserId();
        $todo   = $this->todoModel->findForUser($id, $userId);

        if ($todo === null) {
            return redirect()->to('/todos')->with('error', 'To-do not found.');
        }

        $when = date('Y-m-d H:i:s');
        $this->todoModel->update($id, ['is_trashed' => 1, 'trashed_at' => $when]);

        // Only children NOT already trashed on their own — one that was
        // independently moved to Trash earlier keeps its own trashed_at
        // untouched (see restore()'s matching check below, which relies
        // on that timestamp to tell "trashed together with the parent"
        // apart from "already trashed before the parent ever was").
        foreach ($this->todoModel->childrenFor([$id], $userId)[$id] ?? [] as $child) {
            if (! $child['is_trashed']) {
                $this->todoModel->update($child['id'], ['is_trashed' => 1, 'trashed_at' => $when]);
            }
        }

        $this->logActivity('todo', 'trash', $id, 'Moved to-do to trash: ' . $todo['title']);

        return redirect()->back();
    }

    public function restore(int $id)
    {
        $userId = (int) $this->currentUserId();
        $todo   = $this->todoModel->findForUser($id, $userId);

        if ($todo === null) {
            return redirect()->to('/todos')->with('error', 'To-do not found.');
        }

        $parentTrashedAt = $todo['trashed_at'];
        $this->todoModel->update($id, ['is_trashed' => 0, 'trashed_at' => null]);

        // Only children trashed at the EXACT same moment as the parent —
        // i.e. cascaded by trash() above — come back with it. A child
        // trashed independently, at a different time, keeps its own
        // trashed state; restoring the parent must never silently
        // un-trash something the user deliberately removed on its own.
        foreach ($this->todoModel->childrenFor([$id], $userId)[$id] ?? [] as $child) {
            if ($child['is_trashed'] && $child['trashed_at'] === $parentTrashedAt) {
                $this->todoModel->update($child['id'], ['is_trashed' => 0, 'trashed_at' => null]);
            }
        }

        $this->logActivity('todo', 'restore', $id, 'Restored to-do: ' . $todo['title']);

        return redirect()->back();
    }

    public function delete(int $id)
    {
        $todo = $this->todoModel->findForUser($id, (int) $this->currentUserId());

        if ($todo !== null) {
            $this->todoModel->delete($id);
            $this->logActivity('todo', 'delete', $id, 'Permanently deleted to-do: ' . $todo['title']);
        }

        return redirect()->back()->with('success', 'To-do permanently deleted.');
    }
}
