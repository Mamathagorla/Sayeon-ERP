<?php

namespace App\Modules\HR\Controllers;

use App\Controllers\BaseController;
use App\Modules\HR\Models\EmployeeIncrementLetterModel;
use App\Modules\HR\Models\EmployeeProfileModel;
use App\Modules\HR\Models\SalaryStructureModel;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * The 3rd step of the onboarding letter workflow (Offer -> Joining ->
 * Increment), but scoped to Employees rather than Onboarding — an
 * increment is a post-hire, recurring event over an employee's tenure,
 * not a one-time candidate-pipeline milestone, so it lives alongside the
 * rest of the Employees module and reuses employee.edit/employee.view
 * (same roles that can already open an employee's profile at all).
 */
class IncrementLetterController extends BaseController
{
    protected EmployeeIncrementLetterModel $letterModel;
    protected EmployeeProfileModel $profileModel;
    protected SalaryStructureModel $salaryModel;

    public function __construct()
    {
        $this->letterModel  = new EmployeeIncrementLetterModel();
        $this->profileModel = new EmployeeProfileModel();
        $this->salaryModel  = new SalaryStructureModel();
    }

    public function create(int $employeeId)
    {
        $employee = $this->profileModel->withRelations($employeeId);

        if ($employee === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $employee['company_id'] ?? null)) {
            return redirect()->to('/hr/employees')->with('error', 'Employee not found.');
        }

        return view('App\Modules\HR\increment_letter_form', [
            'title'        => 'Issue Increment Letter',
            'navActive'    => 'hr-employees',
            'employee'     => $employee,
            'currentCtc'   => $this->currentAnnualCtc((int) $employee['user_id']),
        ]);
    }

    public function store(int $employeeId)
    {
        $employee = $this->profileModel->withRelations($employeeId);

        if ($employee === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $employee['company_id'] ?? null)) {
            return redirect()->to('/hr/employees')->with('error', 'Employee not found.');
        }

        // Only the fields the form actually submits are validated here —
        // employee_profile_id/company_id are derived server-side below,
        // never taken from POST, so they're deliberately excluded from
        // the model's full validation rule set (which requires them).
        $formRules = [
            'new_ctc'        => $this->letterModel->getValidationRules()['new_ctc'],
            'effective_date' => $this->letterModel->getValidationRules()['effective_date'],
            'reason'         => $this->letterModel->getValidationRules()['reason'],
        ];

        if (! $this->validate($formRules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'employee_profile_id' => $employeeId,
            'company_id'          => $employee['company_id'],
            // Read live from the salary structure, never trusted from
            // POST — the letter must reflect what was actually on file
            // the moment the increment was issued.
            'previous_ctc'        => $this->currentAnnualCtc((int) $employee['user_id']),
            'new_ctc'             => $this->request->getPost('new_ctc'),
            'effective_date'      => $this->request->getPost('effective_date'),
            'reason'              => $this->request->getPost('reason') ?: null,
            'issued_by'           => $this->currentUserId(),
        ];

        $id = $this->letterModel->insert($data);
        $this->logActivity('employee', 'create', $employeeId, 'Issued an increment letter for ' . $employee['user_name']);

        return redirect()->to('/hr/employees/' . $employeeId . '/increment-letters/' . $id)->with('success', 'Increment letter issued.');
    }

    public function show(int $employeeId, int $id)
    {
        $letter = $this->letterModel->withRelations($id);

        if ($letter === null || (int) $letter['employee_profile_id'] !== $employeeId || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $letter['company_id'])) {
            return redirect()->to('/hr/employees')->with('error', 'Increment letter not found.');
        }

        return view('App\Modules\HR\increment_letter', [
            'title'     => 'Increment Letter — ' . $letter['user_name'],
            'letter'    => $letter,
            'autoPrint' => $this->request->getGet('print') === '1',
        ]);
    }

    public function pdf(int $employeeId, int $id)
    {
        $letter = $this->letterModel->withRelations($id);

        if ($letter === null || (int) $letter['employee_profile_id'] !== $employeeId || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $letter['company_id'])) {
            return redirect()->to('/hr/employees')->with('error', 'Increment letter not found.');
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('App\Modules\HR\exports\increment_letter_pdf', ['letter' => $letter]));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="increment-letter-' . url_title($letter['user_name'], '-', true) . '.pdf"')
            ->setBody($dompdf->output());
    }

    private function currentAnnualCtc(int $userId): ?float
    {
        $salary = $this->salaryModel->byUserId($userId);

        if ($salary === null) {
            return null;
        }

        return ((float) $salary['basic'] + (float) $salary['hra'] + (float) $salary['allowances']) * 12;
    }
}
