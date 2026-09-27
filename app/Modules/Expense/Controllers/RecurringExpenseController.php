<?php

namespace App\Modules\Expense\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Expense\Models\ExpenseCategoryModel;
use App\Modules\Expense\Models\ExpenseModel;
use App\Modules\Expense\Models\RecurringExpenseModel;
use App\Modules\Expense\Services\RecurringExpenseGenerator;

/**
 * Recurring expense templates — same company isolation and expense.*
 * permissions as ExpenseController (see Routes.php), reusing the same
 * expense_categories/companies structures. Generation itself lives in
 * RecurringExpenseGenerator; this controller only exposes a scoped
 * "Generate Now" trigger for on-demand catch-up, plus the CRUD/
 * pause-resume-cancel actions around a template.
 */
class RecurringExpenseController extends BaseController
{
    protected RecurringExpenseModel $recurringModel;
    protected ExpenseModel $expenseModel;
    protected ExpenseCategoryModel $categoryModel;
    protected CompanyModel $companyModel;

    public function __construct()
    {
        $this->recurringModel = new RecurringExpenseModel();
        $this->expenseModel   = new ExpenseModel();
        $this->categoryModel  = new ExpenseCategoryModel();
        $this->companyModel   = new CompanyModel();
    }

    public function index()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'status']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Expense\recurring_index', [
            'title'      => 'Recurring Expenses',
            'navActive'  => 'recurring-expenses',
            'templates'  => $this->recurringModel->filtered($filters)->findAll(),
            'companies'  => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'   => RecurringExpenseModel::STATUSES,
            'filters'    => $filters,
            'companyScope' => $companyScope,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Expense\recurring_form', $this->formData(null));
    }

    public function store()
    {
        if (! $this->validate($this->recurringModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyId = (int) $this->request->getPost('company_id');

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $companyId)) {
            return redirect()->back()->withInput()->with('error', 'You can only add recurring expenses for your own company.');
        }

        $error = $this->validateEndCondition();
        if ($error !== null) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $startDate = $this->request->getPost('start_date');

        $id = $this->recurringModel->insert([
            'company_id'            => $companyId,
            'title'                 => $this->request->getPost('title'),
            'category'              => $this->request->getPost('category'),
            'amount'                => $this->request->getPost('amount'),
            'description'           => $this->request->getPost('description'),
            'frequency'             => $this->request->getPost('frequency') ?: 'monthly',
            'start_date'            => $startDate,
            'end_type'              => $this->request->getPost('end_type') ?: 'occurrences',
            'end_date'              => $this->request->getPost('end_type') === 'end_date' ? $this->request->getPost('end_date') : null,
            'occurrences_total'     => $this->request->getPost('end_type') === 'occurrences' ? (int) $this->request->getPost('occurrences_total') : null,
            'occurrences_generated' => 0,
            'next_generation_date'  => $startDate,
            'status'                => 'active',
            'created_by'            => $this->currentUserId(),
        ]);

        $this->logActivity('expense', 'create', $id, 'Created recurring expense: ' . $this->request->getPost('title'));

        return redirect()->to('/recurring-expenses')->with('success', 'Recurring expense created. It will generate its first expense on ' . esc($startDate) . '.');
    }

    public function edit(int $id)
    {
        $template = $this->recurringModel->filtered()->where('recurring_expenses.id', $id)->first();

        if ($template === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $template['company_id'])) {
            return redirect()->to('/recurring-expenses')->with('error', 'Recurring expense not found.');
        }

        return view('App\Modules\Expense\recurring_form', $this->formData($template));
    }

    /**
     * Only the editable, forward-looking fields — title/category/amount/
     * description/end condition. frequency and start_date are locked
     * after creation (changing them mid-stream would desync
     * next_generation_date/occurrences_generated from what's already
     * been generated), same reasoning as most modules not allowing a
     * record's identity-defining fields to move after creation.
     */
    public function update(int $id)
    {
        $template = $this->recurringModel->find($id);

        if ($template === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $template['company_id'])) {
            return redirect()->to('/recurring-expenses')->with('error', 'Recurring expense not found.');
        }

        $rules = [
            'title'    => 'required|min_length[2]|max_length[150]',
            'category' => 'required|max_length[100]',
            'amount'   => 'required|decimal|greater_than[0]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->recurringModel->update($id, [
            'title'       => $this->request->getPost('title'),
            'category'    => $this->request->getPost('category'),
            'amount'      => $this->request->getPost('amount'),
            'description' => $this->request->getPost('description'),
        ]);

        $this->logActivity('expense', 'update', $id, 'Updated recurring expense: ' . $this->request->getPost('title'));

        return redirect()->to('/recurring-expenses')->with('success', 'Recurring expense updated.');
    }

    public function show(int $id)
    {
        $template = $this->recurringModel->filtered()->where('recurring_expenses.id', $id)->first();

        if ($template === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $template['company_id'])) {
            return redirect()->to('/recurring-expenses')->with('error', 'Recurring expense not found.');
        }

        return view('App\Modules\Expense\recurring_show', [
            'title'     => $template['title'],
            'navActive' => 'recurring-expenses',
            'template'  => $template,
            'history'   => $this->expenseModel->generatedFor($id),
        ]);
    }

    public function pause(int $id)
    {
        $template = $this->recurringModel->find($id);

        if ($template === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $template['company_id'])) {
            return redirect()->to('/recurring-expenses')->with('error', 'Recurring expense not found.');
        }
        if ($template['status'] !== 'active') {
            return redirect()->to('/recurring-expenses')->with('error', 'Only an active recurring expense can be paused.');
        }

        $this->recurringModel->update($id, ['status' => 'paused']);
        $this->logActivity('expense', 'update', $id, 'Paused recurring expense: ' . $template['title']);

        return redirect()->to('/recurring-expenses')->with('success', 'Recurring expense paused. No further expenses will generate until resumed.');
    }

    public function resume(int $id)
    {
        $template = $this->recurringModel->find($id);

        if ($template === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $template['company_id'])) {
            return redirect()->to('/recurring-expenses')->with('error', 'Recurring expense not found.');
        }
        if ($template['status'] !== 'paused') {
            return redirect()->to('/recurring-expenses')->with('error', 'Only a paused recurring expense can be resumed.');
        }

        // A pause can span an arbitrary stretch of time, so the paused
        // next_generation_date may now be well in the past — pull it up
        // to today rather than letting the very next generator run
        // "catch up" every cycle that silently elapsed while paused.
        $data = ['status' => 'active'];
        if ($template['next_generation_date'] !== null && $template['next_generation_date'] < date('Y-m-d')) {
            $data['next_generation_date'] = date('Y-m-d');
        }

        $this->recurringModel->update($id, $data);
        $this->logActivity('expense', 'update', $id, 'Resumed recurring expense: ' . $template['title']);

        return redirect()->to('/recurring-expenses')->with('success', 'Recurring expense resumed.');
    }

    public function cancel(int $id)
    {
        $template = $this->recurringModel->find($id);

        if ($template === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $template['company_id'])) {
            return redirect()->to('/recurring-expenses')->with('error', 'Recurring expense not found.');
        }
        if (in_array($template['status'], ['completed', 'cancelled'], true)) {
            return redirect()->to('/recurring-expenses')->with('error', 'This recurring expense has already ended.');
        }

        $this->recurringModel->update($id, ['status' => 'cancelled', 'next_generation_date' => null]);
        $this->logActivity('expense', 'update', $id, 'Cancelled recurring expense: ' . $template['title']);

        return redirect()->to('/recurring-expenses')->with('success', 'Recurring expense cancelled. Already-generated expenses are unaffected.');
    }

    /**
     * Manual, on-demand catch-up — scoped to the viewer's own company
     * (or every company only for an unrestricted Super Admin), gated by
     * expense.create the same as adding an expense by hand. Runs the
     * exact same RecurringExpenseGenerator the production cron/CLI
     * command uses, so this is also how the feature is verified to work
     * without waiting for a scheduled trigger.
     */
    public function generateNow()
    {
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);

        $result = $companyScope !== null
            ? (new RecurringExpenseGenerator())->runForCompany($companyScope)
            : (new RecurringExpenseGenerator())->runAll();

        if ($result['expensesGenerated'] > 0) {
            $this->logActivity('expense', 'create', null, "Generated {$result['expensesGenerated']} expense(s) from {$result['templatesProcessed']} recurring template(s).");
        }

        $message = $result['expensesGenerated'] > 0
            ? "Generated {$result['expensesGenerated']} expense(s) from {$result['templatesProcessed']} recurring template(s)."
            : 'Nothing due to generate right now.';

        return redirect()->to('/recurring-expenses')->with('success', $message);
    }

    private function formData(?array $template): array
    {
        return [
            'title'      => $template ? 'Edit Recurring Expense' : 'New Recurring Expense',
            'navActive'  => 'recurring-expenses',
            'template'   => $template,
            'companies'  => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'categories' => $this->categoryModel->optionsListIncluding($template['category'] ?? null),
            'frequencies' => RecurringExpenseModel::FREQUENCIES,
        ];
    }

    /**
     * end_type-conditional fields can't be expressed as a single
     * declarative model rule (which field is required depends on
     * another field's value) — same reasoning as SupportController's
     * manual in_array() checks beyond its model's own validation rules.
     */
    private function validateEndCondition(): ?string
    {
        $endType = $this->request->getPost('end_type');

        if ($endType === 'end_date') {
            $endDate   = $this->request->getPost('end_date');
            $startDate = $this->request->getPost('start_date');
            if (! $endDate) {
                return 'End date is required when ending by date.';
            }
            if ($endDate < $startDate) {
                return 'End date cannot be before the start date.';
            }

            return null;
        }

        if ($endType === 'occurrences') {
            $occurrences = $this->request->getPost('occurrences_total');
            if (! $occurrences || (int) $occurrences < 1) {
                return 'Number of occurrences must be at least 1.';
            }

            return null;
        }

        return 'Choose whether this recurring expense ends by date or by number of occurrences.';
    }
}
