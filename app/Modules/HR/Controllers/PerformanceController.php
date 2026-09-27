<?php

namespace App\Modules\HR\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Department\Models\DepartmentModel;
use App\Modules\HR\Models\EmployeeProfileModel;
use App\Modules\HR\Models\PerformanceReviewModel;
use App\Modules\HR\Models\ReviewCycleModel;
use App\Modules\Notification\Services\NotificationService;

class PerformanceController extends BaseController
{
    protected PerformanceReviewModel $reviewModel;
    protected ReviewCycleModel $cycleModel;
    protected UserModel $userModel;
    protected EmployeeProfileModel $employeeModel;
    protected DepartmentModel $departmentModel;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->reviewModel   = new PerformanceReviewModel();
        $this->cycleModel    = new ReviewCycleModel();
        $this->userModel     = new UserModel();
        $this->employeeModel = new EmployeeProfileModel();
        $this->departmentModel = new DepartmentModel();
        $this->notificationService = new NotificationService();
    }

    /**
     * Employees always see only their own reviews (and only once the
     * reviewer has submitted â€” a draft is the reviewer's working copy).
     * Anyone with performance.create sees reviews they wrote too.
     */
    public function index()
    {
        $roleSlug = session('roleSlug');

        // HR/Super Admin get the employee-centric list (who's reviewed,
        // who's due) — every other role (Manager, Admin, Accountant,
        // Compliance Officer, and Employee's own personal view) keeps
        // the existing review-centric list exactly as it was.
        if (in_array($roleSlug, ['hr', 'super_admin'], true)) {
            return $this->indexForHr();
        }

        $filters = array_filter($this->request->getGet(['user_id', 'cycle_id', 'status']) ?? []);

        $isPersonalScope = $roleSlug === 'employee';
        if ($isPersonalScope) {
            $filters['user_id'] = session('userId');
        }

        $reviews = $this->reviewModel->filtered($filters)->findAll();

        if ($isPersonalScope) {
            $reviews = array_values(array_filter($reviews, static fn (array $r) => $r['status'] !== 'draft'));
        }

        return view('App\Modules\HR\performance/index', [
            'title'           => $isPersonalScope ? 'My Performance Reviews' : 'Performance Reviews',
            'navActive'       => 'hr-performance',
            'isPersonalScope' => $isPersonalScope,
            'reviews'         => $reviews,
            'filters'         => $filters,
        ]);
    }

    /**
     * Employee-centric list for HR/Super Admin: one row per employee
     * (their latest review + whether they're due for the current open
     * cycle), not one row per review record. Built entirely from
     * existing tables (employee_profiles, performance_reviews,
     * review_cycles) — no new schema.
     */
    private function indexForHr()
    {
        $filters = array_filter($this->request->getGet(['department_id', 'cycle_id', 'status', 'q']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        $employeeFilters = array_filter(['department_id' => $filters['department_id'] ?? null, 'q' => $filters['q'] ?? null]);
        if ($companyScope !== null) {
            $employeeFilters['company_id'] = $companyScope;
        }

        $employees = $this->employeeModel->filtered($employeeFilters)->findAll();
        $userIds   = array_column($employees, 'user_id');

        $reviewsByUser = [];
        if ($userIds !== []) {
            $reviewFilters = ['user_id' => $userIds];
            if (! empty($filters['cycle_id'])) {
                $reviewFilters['cycle_id'] = $filters['cycle_id'];
            }
            foreach ($this->reviewModel->filtered($reviewFilters)->findAll() as $r) {
                // filtered() already orders by created_at DESC, so the
                // first row seen per user is their latest review.
                $reviewsByUser[$r['user_id']][] = $r;
            }
        }

        $openCycles = $this->cycleModel->where('status', 'open')->findAll();

        $rows = [];
        $ratings = [];
        $dueCount = 0;
        $completedCount = 0;

        foreach ($employees as $e) {
            $reviews = $reviewsByUser[$e['user_id']] ?? [];
            $latest  = $reviews[0] ?? null;

            // "Due" = an open cycle exists that this employee has no
            // review for yet. Only real, existing cycle data decides
            // this — never a fabricated future date.
            $reviewedCycleIds = array_column($reviews, 'cycle_id');
            $dueCycle = null;
            foreach ($openCycles as $c) {
                if (! in_array((int) $c['id'], array_map('intval', $reviewedCycleIds), true)) {
                    $dueCycle = $c;
                    break;
                }
            }

            if ($dueCycle !== null) {
                $status = 'due';
                $dueCount++;
            } elseif ($latest !== null) {
                $status = 'completed';
                $completedCount++;
            } else {
                $status = 'none';
            }

            if (! empty($filters['status']) && $filters['status'] !== $status) {
                continue;
            }

            if ($latest !== null && $latest['rating']) {
                $ratings[] = (int) $latest['rating'];
            }

            $rows[] = [
                'user_id'      => $e['user_id'],
                'name'         => $e['user_name'],
                'department'   => $e['department_name'],
                'last_review'  => $latest,
                'due_cycle'    => $dueCycle,
                'status'       => $status,
            ];
        }

        return view('App\Modules\HR\performance/index_hr', [
            'title'       => 'Performance',
            'navActive'   => 'hr-performance',
            'rows'        => $rows,
            'kpis'        => [
                'employees' => count($employees),
                'due'       => $dueCount,
                'completed' => $completedCount,
                'avgRating' => $ratings !== [] ? round(array_sum($ratings) / count($ratings), 1) : null,
            ],
            'departments' => $this->departmentModel->optionsList(),
            'cycles'      => $this->cycleModel->optionsList(),
            'filters'     => $filters,
        ]);
    }

    /**
     * A single employee's performance profile — reached by clicking
     * their name from the HR/Super Admin list. Same company-scope
     * guard used across Employee Profile / Attendance / Leave History.
     */
    public function employee(int $userId)
    {
        $employee = $this->employeeModel->byUserId($userId);

        if ($employee === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $employee['company_id'] ?? null)) {
            return redirect()->to('/hr/performance')->with('error', 'Employee not found.');
        }

        $reviews = $this->reviewModel->filtered(['user_id' => $userId])->findAll();
        $latest  = $reviews[0] ?? null;

        $rated = array_values(array_filter($reviews, static fn (array $r) => $r['rating'] !== null));
        $avgRating = $rated !== [] ? round(array_sum(array_column($rated, 'rating')) / count($rated), 1) : null;

        $reviewedCycleIds = array_column($reviews, 'cycle_id');
        $dueCycle = null;
        foreach ($this->cycleModel->where('status', 'open')->findAll() as $c) {
            if (! in_array((int) $c['id'], array_map('intval', $reviewedCycleIds), true)) {
                $dueCycle = $c;
                break;
            }
        }

        return view('App\Modules\HR\performance/employee', [
            'title'     => 'Performance Profile',
            'navActive' => 'hr-performance',
            'employee'  => $employee,
            'reviews'   => $reviews,
            'latest'    => $latest,
            'avgRating' => $avgRating,
            'dueCycle'  => $dueCycle,
        ]);
    }

    public function cycles()
    {
        return view('App\Modules\HR\performance/cycles', [
            'title'     => 'Review Cycles',
            'navActive' => 'hr-performance',
            'cycles'    => $this->cycleModel->optionsList(),
        ]);
    }

    public function storeCycle()
    {
        if (! $this->validate($this->cycleModel->getValidationRules())) {
            return redirect()->to('/hr/performance/cycles')->with('errors', $this->validator->getErrors());
        }

        $this->cycleModel->insert([
            'name'       => $this->request->getPost('name'),
            'start_date' => $this->request->getPost('start_date'),
            'end_date'   => $this->request->getPost('end_date'),
        ]);

        return redirect()->to('/hr/performance/cycles')->with('success', 'Review cycle created.');
    }

    public function create()
    {
        return view('App\Modules\HR\performance/form', [
            'title'     => 'New Performance Review',
            'navActive' => 'hr-performance',
            'review'    => null,
            // Pre-selects the employee when arriving from their
            // Performance Profile's "Start Review" button — purely a
            // form convenience, store() still validates user_id fresh.
            'presetUserId' => $this->request->getGet('user_id'),
            'cycles'    => $this->cycleModel->optionsList(),
            'users'     => $this->scopedUserOptions($this->userModel->listForOptions()),
        ]);
    }

    public function store()
    {
        $cycleId = (int) $this->request->getPost('cycle_id');
        $userId  = (int) $this->request->getPost('user_id');

        if ($this->reviewModel->alreadyReviewed($cycleId, $userId)) {
            return redirect()->back()->withInput()->with('error', 'This employee already has a review for that cycle.');
        }

        if (! $this->validate($this->reviewModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $id = $this->reviewModel->insert([
            'cycle_id'    => $cycleId,
            'user_id'     => $userId,
            'reviewer_id' => $this->currentUserId(),
            'rating'      => $this->request->getPost('rating') ?: null,
            'strengths'   => $this->request->getPost('strengths'),
            'improvements' => $this->request->getPost('improvements'),
            'goals_next'  => $this->request->getPost('goals_next'),
            'status'      => 'draft',
        ]);

        $this->logActivity('performance', 'create', $id, 'Created performance review #' . $id);

        return redirect()->to('/hr/performance/' . $id)->with('success', 'Review saved as draft.');
    }

    public function show(int $id)
    {
        $review = $this->reviewModel->withRelations($id);

        if ($review === null) {
            return redirect()->to('/hr/performance')->with('error', 'Review not found.');
        }

        $isOwner = (int) $review['user_id'] === (int) $this->currentUserId();

        if ($isOwner && $review['status'] === 'draft' && ! can('performance.edit')) {
            return redirect()->to('/hr/performance')->with('error', 'This review is not yet available.');
        }

        return view('App\Modules\HR\performance/show', [
            'title'     => 'Performance Review - ' . $review['cycle_name'],
            'navActive' => 'hr-performance',
            'review'    => $review,
            'isOwner'   => $isOwner,
        ]);
    }

    public function edit(int $id)
    {
        $review = $this->reviewModel->find($id);

        if ($review === null) {
            return redirect()->to('/hr/performance')->with('error', 'Review not found.');
        }

        return view('App\Modules\HR\performance/form', [
            'title'     => 'Edit Performance Review',
            'navActive' => 'hr-performance',
            'review'    => $this->reviewModel->withRelations($id),
            'cycles'    => $this->cycleModel->optionsList(),
            'users'     => $this->scopedUserOptions($this->userModel->listForOptions()),
        ]);
    }

    public function update(int $id)
    {
        if ($this->reviewModel->find($id) === null) {
            return redirect()->to('/hr/performance')->with('error', 'Review not found.');
        }

        $this->reviewModel->update($id, [
            'rating'       => $this->request->getPost('rating') ?: null,
            'strengths'    => $this->request->getPost('strengths'),
            'improvements' => $this->request->getPost('improvements'),
            'goals_next'   => $this->request->getPost('goals_next'),
        ]);

        $this->logActivity('performance', 'update', $id, 'Updated performance review #' . $id);

        return redirect()->to('/hr/performance/' . $id)->with('success', 'Review updated.');
    }

    public function submit(int $id)
    {
        $review = $this->reviewModel->find($id);

        if ($review === null || $review['status'] !== 'draft') {
            return redirect()->to('/hr/performance')->with('error', 'This review cannot be submitted.');
        }

        $this->reviewModel->update($id, ['status' => 'submitted', 'submitted_at' => date('Y-m-d H:i:s')]);
        $this->logActivity('performance', 'update', $id, 'Submitted performance review #' . $id);

        $this->notificationService->notify(
            (int) $review['user_id'],
            'performance_review_ready',
            'Your performance review is ready',
            'A new performance review has been submitted for you to view.',
            'performance',
            $id
        );

        return redirect()->to('/hr/performance/' . $id)->with('success', 'Review submitted.');
    }

    public function acknowledge(int $id)
    {
        $review = $this->reviewModel->find($id);

        if ($review === null || (int) $review['user_id'] !== (int) $this->currentUserId() || $review['status'] !== 'submitted') {
            return redirect()->to('/hr/performance')->with('error', 'This review cannot be acknowledged.');
        }

        $this->reviewModel->update($id, ['status' => 'acknowledged', 'acknowledged_at' => date('Y-m-d H:i:s')]);

        return redirect()->to('/hr/performance/' . $id)->with('success', 'Review acknowledged.');
    }
}
