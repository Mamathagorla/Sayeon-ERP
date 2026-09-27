<?php

namespace App\Modules\Expense\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Expense\Models\ExpenseCategoryModel;
use App\Modules\Expense\Models\ExpenseModel;

class ExpenseController extends BaseController
{
    protected ExpenseModel $expenseModel;
    protected ExpenseCategoryModel $categoryModel;
    protected CompanyModel $companyModel;

    public function __construct()
    {
        $this->expenseModel   = new ExpenseModel();
        $this->categoryModel  = new ExpenseCategoryModel();
        $this->companyModel   = new CompanyModel();
    }

    public function index()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'category', 'status']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Expense\index', [
            'title'     => 'Expenses & Subscriptions',
            'navActive' => 'expenses',
            'expenses'  => $this->expenseModel->filtered($filters)->findAll(),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'billingCycles' => ExpenseModel::BILLING_CYCLES,
            'statuses'  => ExpenseModel::STATUSES,
            'filters'   => $filters,
            'monthlyTotal' => $this->expenseModel->monthlyEquivalentTotal($companyScope),
        ]);
    }

    public function create()
    {
        return view('App\Modules\Expense\form', $this->formData(null));
    }

    public function store()
    {
        if (! $this->validate($this->expenseModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only add expenses for your own company.');
        }

        $id = $this->expenseModel->insert($this->payload(true));
        $this->logActivity('expense', 'create', $id, 'Added expense: ' . $this->request->getPost('vendor'));

        return redirect()->to('/expenses')->with('success', 'Expense added.');
    }

    public function edit(int $id)
    {
        $expense = $this->expenseModel->find($id);

        if ($expense === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $expense['company_id'])) {
            return redirect()->to('/expenses')->with('error', 'Expense not found.');
        }

        return view('App\Modules\Expense\form', $this->formData($expense));
    }

    public function update(int $id)
    {
        $expense = $this->expenseModel->find($id);

        if ($expense === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $expense['company_id'])) {
            return redirect()->to('/expenses')->with('error', 'Expense not found.');
        }

        if (! $this->validate($this->expenseModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only assign expenses to your own company.');
        }

        $this->expenseModel->update($id, $this->payload(false));
        $this->logActivity('expense', 'update', $id, 'Updated expense #' . $id);

        return redirect()->to('/expenses')->with('success', 'Expense updated.');
    }

    public function delete(int $id)
    {
        $expense = $this->expenseModel->find($id);

        if ($expense === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $expense['company_id'])) {
            return redirect()->to('/expenses')->with('error', 'Expense not found.');
        }

        $this->expenseModel->delete($id);
        $this->logActivity('expense', 'delete', $id, 'Deleted expense #' . $id);

        return redirect()->to('/expenses')->with('success', 'Expense deleted.');
    }

    private function formData(?array $expense): array
    {
        return [
            'title'     => $expense ? 'Edit Expense' : 'Add Expense',
            'navActive' => 'expenses',
            'expense'   => $expense,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'billingCycles' => ExpenseModel::BILLING_CYCLES,
            'statuses'  => ExpenseModel::STATUSES,
            // Active categories, plus this expense's own current
            // category if it's since gone inactive (or predates the
            // category table) — see optionsListIncluding().
            'categories' => $this->categoryModel->optionsListIncluding($expense['category'] ?? null),
        ];
    }

    private function payload(bool $isNew): array
    {
        $data = [
            'company_id'     => (int) $this->request->getPost('company_id'),
            'vendor'         => $this->request->getPost('vendor'),
            'category'       => $this->request->getPost('category'),
            'billing_cycle'  => $this->request->getPost('billing_cycle') ?: 'monthly',
            'amount'         => $this->request->getPost('amount'),
            'renewal_date'   => $this->request->getPost('renewal_date') ?: null,
            'payment_method' => $this->request->getPost('payment_method'),
            'notes'          => $this->request->getPost('notes'),
            'status'         => $this->request->getPost('status') ?: 'active',
        ];

        if ($isNew) {
            $data['created_by'] = $this->currentUserId();
        }

        return $data;
    }
}
