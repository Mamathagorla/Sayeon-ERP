<?php

namespace App\Modules\Expense\Controllers;

use App\Controllers\BaseController;
use App\Modules\Accounting\Models\PaymentModel;
use App\Modules\Expense\Models\ExpenseCategoryModel;
use App\Modules\Expense\Models\ExpenseClaimModel;
use App\Modules\HR\Models\EmployeeProfileModel;
use App\Modules\Notification\Services\NotificationService;

/**
 * Expense claims: submit -> approve/reject -> reimburse (books a payment in Accounting).
 *
 * Rules: a claim belongs to the claimant's own company; nobody approves or reimburses their own claim (Super Admin
 * excepted, as in Leave and Purchase Orders); each step is only possible from the right status, so repeating a click
 * can neither double-approve nor pay a claim twice.
 */
class ExpenseClaimController extends BaseController
{
    protected ExpenseClaimModel $claimModel;
    protected EmployeeProfileModel $employeeModel;
    protected ExpenseCategoryModel $categoryModel;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->claimModel          = new ExpenseClaimModel();
        $this->employeeModel       = new EmployeeProfileModel();
        $this->categoryModel       = new ExpenseCategoryModel();
        $this->notificationService = new NotificationService();
    }

    /** Approvers and Finance see the company's claims; everyone else only their own. */
    private function seesAll(): bool
    {
        return can('expense_claim.approve') || can('expense_claim.reimburse');
    }

    public function index()
    {
        $filters = array_filter($this->request->getGet(['status']) ?? []);

        if (! $this->seesAll()) {
            $filters['user_id'] = $this->currentUserId();
        }

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Expense\claims/index', [
            'title'     => $this->seesAll() ? 'Expense Claims' : 'My Expense Claims',
            'navActive' => 'expense-claims',
            'claims'    => $this->claimModel->filtered($filters)->findAll(),
            'statuses'  => ExpenseClaimModel::STATUSES,
            'filters'   => $filters,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Expense\claims/form', [
            'title'      => 'New Expense Claim',
            'navActive'  => 'expense-claims',
            'categories' => $this->categoryModel->optionsList(),
        ]);
    }

    public function store()
    {
        $profile = $this->employeeModel->byUserId((int) $this->currentUserId());

        if ($profile === null) {
            return redirect()->to('/expense-claims')->with('error', 'Only employees can submit expense claims.');
        }

        if (! $this->validate($this->claimModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $date = (string) $this->request->getPost('expense_date');
        if ($date > date('Y-m-d')) {
            return redirect()->back()->withInput()->with('error', 'The expense date cannot be in the future.');
        }

        $row = [
            'company_id'   => (int) $profile['company_id'],
            'user_id'      => (int) $this->currentUserId(),
            'title'        => trim((string) $this->request->getPost('title')),
            'category'     => (string) $this->request->getPost('category'),
            'amount'       => $this->request->getPost('amount'),
            'expense_date' => $date,
            'description'  => $this->request->getPost('description') ?: null,
            'status'       => 'submitted',
        ];

        // Double click / resubmission: an identical claim that is still waiting is not filed twice.
        $duplicate = $this->claimModel->where([
            'user_id' => $row['user_id'], 'title' => $row['title'], 'amount' => $row['amount'], 'expense_date' => $date, 'status' => 'submitted',
        ])->countAllResults() > 0;
        if ($duplicate) {
            return redirect()->to('/expense-claims')->with('error', 'You already have an identical claim waiting for approval.');
        }

        $id = $this->claimModel->insert($row);
        $this->logActivity('expense_claim', 'create', (int) $id, 'Submitted expense claim: ' . $row['title']);

        if (! empty($profile['reporting_manager_id'])) {
            $this->notificationService->notify(
                (int) $profile['reporting_manager_id'],
                'expense_claim_submitted',
                'Expense claim to review: ' . $row['title'],
                $profile['user_name'] . ' submitted an expense claim of ' . number_format((float) $row['amount'], 2) . '.',
                'expense_claim',
                (int) $id
            );
        }

        return redirect()->to('/expense-claims')->with('success', 'Expense claim submitted for approval.');
    }

    public function approve(int $id)
    {
        return $this->decide($id, 'approved');
    }

    public function reject(int $id)
    {
        return $this->decide($id, 'rejected');
    }

    private function decide(int $id, string $status)
    {
        $claim = $this->scopedClaim($id);

        if ($claim === null) {
            return redirect()->to('/expense-claims')->with('error', 'Expense claim not found.');
        }
        if ($claim['status'] !== 'submitted') {
            return redirect()->to('/expense-claims')->with('error', 'This claim has already been actioned.');
        }
        if ((int) $claim['user_id'] === (int) $this->currentUserId() && session('roleSlug') !== 'super_admin') {
            return redirect()->to('/expense-claims')->with('error', 'You cannot approve or reject your own expense claim.');
        }

        $this->claimModel->update($id, [
            'status'        => $status,
            'decided_by'    => $this->currentUserId(),
            'decided_at'    => date('Y-m-d H:i:s'),
            'decision_note' => mb_substr((string) $this->request->getPost('note'), 0, 255) ?: null,
        ]);
        $this->logActivity('expense_claim', 'update', $id, ucfirst($status) . ' expense claim #' . $id);

        $this->notificationService->notify(
            (int) $claim['user_id'],
            'expense_claim_' . $status,
            'Expense claim ' . $status . ': ' . $claim['title'],
            'Your expense claim "' . $claim['title'] . '" was ' . $status . '.',
            'expense_claim',
            $id
        );

        return redirect()->to('/expense-claims')->with('success', 'Expense claim ' . $status . '.');
    }

    /**
     * Pays an approved claim: one cash-out payment in the payments ledger linked to the claim, all in one
     * transaction. Only an "approved" claim can be reimbursed, so it can never be paid twice.
     */
    public function reimburse(int $id)
    {
        $claim = $this->scopedClaim($id);

        if ($claim === null) {
            return redirect()->to('/expense-claims')->with('error', 'Expense claim not found.');
        }
        if ($claim['status'] !== 'approved') {
            return redirect()->to('/expense-claims')->with('error', 'Only an approved claim can be reimbursed.');
        }
        if ((int) $claim['user_id'] === (int) $this->currentUserId() && session('roleSlug') !== 'super_admin') {
            return redirect()->to('/expense-claims')->with('error', 'You cannot reimburse your own expense claim.');
        }

        $method = trim((string) $this->request->getPost('payment_method')) ?: 'bank_transfer';

        $db = db_connect();
        $db->transStart();

        $paymentId = (new PaymentModel())->insert([
            'company_id'   => (int) $claim['company_id'],
            'direction'    => 'out',
            'amount'       => $claim['amount'],
            'payment_date' => date('Y-m-d'),
            'method'       => mb_substr($method, 0, 60),
            'reference'    => 'CLAIM-' . $id,
            'notes'        => mb_substr('Expense claim reimbursement: ' . $claim['title'], 0, 255),
            'created_by'   => $this->currentUserId(),
        ]);

        $this->claimModel->update($id, [
            'status'         => 'reimbursed',
            'reimbursed_by'  => $this->currentUserId(),
            'reimbursed_at'  => date('Y-m-d H:i:s'),
            'payment_method' => mb_substr($method, 0, 60),
            'payment_id'     => $paymentId,
        ]);

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to('/expense-claims')->with('error', 'Could not record the reimbursement. Nothing was saved.');
        }

        $this->logActivity('expense_claim', 'update', $id, 'Reimbursed expense claim #' . $id);
        $this->notificationService->notify(
            (int) $claim['user_id'],
            'expense_claim_reimbursed',
            'Expense claim reimbursed: ' . $claim['title'],
            'Your expense claim "' . $claim['title'] . '" of ' . number_format((float) $claim['amount'], 2) . ' has been reimbursed.',
            'expense_claim',
            $id
        );

        return redirect()->to('/expense-claims')->with('success', 'Reimbursement recorded in Accounting.');
    }

    public function cancel(int $id)
    {
        $claim = $this->claimModel->find($id);

        if ($claim === null || (int) $claim['user_id'] !== (int) $this->currentUserId() || $claim['status'] !== 'submitted') {
            return redirect()->to('/expense-claims')->with('error', 'This claim can no longer be cancelled.');
        }

        $this->claimModel->update($id, ['status' => 'cancelled']);
        $this->logActivity('expense_claim', 'update', $id, 'Cancelled expense claim #' . $id);

        return redirect()->to('/expense-claims')->with('success', 'Expense claim cancelled.');
    }

    private function scopedClaim(int $id): ?array
    {
        $claim = $this->claimModel->find($id);

        if ($claim === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $claim['company_id'])) {
            return null;
        }

        return $claim;
    }
}
