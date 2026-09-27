<?php

namespace App\Modules\HR\Controllers;

use App\Controllers\BaseController;
use App\Modules\HR\Models\EmployeeProfileModel;
use App\Modules\HR\Models\PayrollRunModel;
use App\Modules\HR\Models\PayslipModel;
use App\Modules\HR\Models\SalaryStructureModel;
use App\Modules\Notification\Services\NotificationService;
use Dompdf\Dompdf;
use Dompdf\Options;

class PayrollController extends BaseController
{
    protected PayrollRunModel $runModel;
    protected PayslipModel $payslipModel;
    protected SalaryStructureModel $structureModel;
    protected EmployeeProfileModel $employeeModel;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->runModel       = new PayrollRunModel();
        $this->payslipModel   = new PayslipModel();
        $this->structureModel = new SalaryStructureModel();
        $this->employeeModel  = new EmployeeProfileModel();
        $this->notificationService = new NotificationService();
    }

    public function index()
    {
        $runs         = $this->runModel->listAll();
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        $summary      = $this->runSummary($runs);

        return view('App\Modules\HR\payroll/index', [
            'title'     => 'Payroll',
            'navActive' => 'hr-payroll',
            'runs'      => $runs,
            'months'    => $this->monthOptions(),
            'summary'   => $summary,
            'slips'     => $summary ? $this->payslipModel->forRun((int) $summary['run']['id'], $companyScope) : [],
        ]);
    }

    /**
     * A single employee's payroll details — reached by clicking their
     * name in the payroll table. Same company-scope guard used across
     * Employee Profile / Attendance / Leave / Performance.
     */
    public function employee(int $userId)
    {
        if (session('roleSlug') === 'employee') {
            return redirect()->to('/hr/payroll/my-payslips')->with('error', 'You do not have permission to view this page.');
        }

        $employee = $this->employeeModel->byUserId($userId);

        if ($employee === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $employee['company_id'] ?? null)) {
            return redirect()->to('/hr/payroll')->with('error', 'Employee not found.');
        }

        $structure = $this->structureModel->byUserId($userId);
        $gross = $net = $ctc = null;
        if ($structure !== null) {
            $gross = (float) $structure['basic'] + (float) $structure['hra'] + (float) $structure['allowances'];
            $net   = $gross - (float) $structure['deductions'];
            $ctc   = $gross * 12;
        }

        return view('App\Modules\HR\payroll/employee', [
            'title'     => 'Payroll Details',
            'navActive' => 'hr-payroll',
            'employee'  => $employee,
            'structure' => $structure,
            'gross'     => $gross,
            'net'       => $net,
            'ctc'       => $ctc,
            'payslips'  => $this->payslipModel->forUser($userId),
            'months'    => $this->monthOptions(),
        ]);
    }

    /**
     * Totals for the run shown at the top of the Payroll page (the one
     * picked with ?run=, else the latest). Same visibility rules as
     * show(): Employees never see org pay figures, and everyone else
     * only totals their own company's payslips.
     */
    private function runSummary(array $runs): ?array
    {
        if ($runs === [] || session('roleSlug') === 'employee') {
            return null;
        }

        $selected = $runs[0];
        $wanted   = (int) $this->request->getGet('run');
        foreach ($runs as $r) {
            if ((int) $r['id'] === $wanted) {
                $selected = $r;
                break;
            }
        }

        $slips = $this->payslipModel->forRun((int) $selected['id'], $this->companyScopeFor(self::COMPANY_SCOPED_ROLES));
        $sum   = static fn (string $col): float => (float) array_sum(array_column($slips, $col));

        return [
            'run'        => $selected,
            'employees'  => count($slips),
            'basic'      => $sum('basic'),
            'hra'        => $sum('hra'),
            'allowances' => $sum('allowances'),
            'gross'      => $sum('gross'),
            'deductions' => $sum('deductions'),
            'net'        => $sum('net'),
        ];
    }

    public function generate()
    {
        $rules = [
            'month' => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[12]',
            'year'  => 'required|integer|greater_than_equal_to[2000]|less_than_equal_to[2100]',
        ];
        $messages = [
            'month' => ['greater_than_equal_to' => 'Select a valid month.', 'less_than_equal_to' => 'Select a valid month.'],
            'year'  => ['greater_than_equal_to' => 'Enter a valid 4-digit year between 2000 and 2100.', 'less_than_equal_to' => 'Enter a valid 4-digit year between 2000 and 2100.'],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->to('/hr/payroll')->with('errors', $this->validator->getErrors());
        }

        $month = (int) $this->request->getPost('month');
        $year  = (int) $this->request->getPost('year');

        if ($this->runModel->forPeriod($month, $year) !== null) {
            return redirect()->to('/hr/payroll')->with('error', 'A payroll run for this period already exists.');
        }

        // HR Manager (or Company Admin) only pays their own company's
        // staff — Super Admin's "All Companies" keeps the org-wide run.
        $payable = $this->structureModel->payableUsers($this->companyScopeFor(self::COMPANY_SCOPED_ROLES));

        if (empty($payable)) {
            return redirect()->to('/hr/payroll')->with('error', 'No employees have a salary structure configured yet.');
        }

        $runId = $this->runModel->insert([
            'month'      => $month,
            'year'       => $year,
            'status'     => 'processed',
            'processed_at' => date('Y-m-d H:i:s'),
            'created_by' => $this->currentUserId(),
        ]);

        foreach ($payable as $s) {
            $gross = (float) $s['basic'] + (float) $s['hra'] + (float) $s['allowances'];
            $net   = $gross - (float) $s['deductions'];

            $this->payslipModel->insert([
                'payroll_run_id' => $runId,
                'user_id'        => $s['user_id'],
                'basic'          => $s['basic'],
                'hra'            => $s['hra'],
                'allowances'     => $s['allowances'],
                'deductions'     => $s['deductions'],
                'gross'          => $gross,
                'net'            => $net,
            ]);

            $this->notificationService->notify(
                (int) $s['user_id'],
                'payroll_processed',
                'Payslip ready: ' . $this->monthOptions()[$month] . ' ' . $year,
                'Your payslip for ' . $this->monthOptions()[$month] . ' ' . $year . ' is ready.',
                'payroll',
                $runId
            );
        }

        $this->logActivity('payroll', 'create', $runId, 'Generated payroll run for ' . $month . '/' . $year . ' (' . count($payable) . ' employees)');

        return redirect()->to('/hr/payroll/' . $runId)->with('success', 'Payroll generated for ' . count($payable) . ' employee(s).');
    }

    public function show(int $id)
    {
        // payroll.view is also held by Employee for their own payslips
        // (see myPayslips/payslip) â€” the full run lists every employee's
        // pay, so it needs the org-wide roles only, not just the permission.
        if (session('roleSlug') === 'employee') {
            return redirect()->to('/hr/payroll/my-payslips')->with('error', 'You do not have permission to view this page.');
        }

        $run = $this->runModel->find($id);

        if ($run === null) {
            return redirect()->to('/hr/payroll')->with('error', 'Payroll run not found.');
        }

        return view('App\Modules\HR\payroll/show', [
            'title'     => 'Payroll - ' . $this->monthOptions()[$run['month']] . ' ' . $run['year'],
            'navActive' => 'hr-payroll',
            'run'       => $run,
            'payslips'  => $this->payslipModel->forRun($id, $this->companyScopeFor(self::COMPANY_SCOPED_ROLES)),
        ]);
    }

    public function markPaid(int $id)
    {
        if ($this->runModel->find($id) === null) {
            return redirect()->to('/hr/payroll')->with('error', 'Payroll run not found.');
        }

        $this->runModel->update($id, ['status' => 'paid']);
        $this->logActivity('payroll', 'update', $id, 'Marked payroll run #' . $id . ' as paid');

        return redirect()->to('/hr/payroll/' . $id)->with('success', 'Payroll run marked as paid.');
    }

    public function myPayslips()
    {
        return view('App\Modules\HR\payroll/mine', [
            'title'     => 'My Payslips',
            'navActive' => 'hr-payroll',
            'payslips'  => $this->payslipModel->forUser((int) $this->currentUserId()),
            'months'    => $this->monthOptions(),
        ]);
    }

    public function payslip(int $id)
    {
        $payslip = $this->payslipModel->withRelations($id);

        if ($payslip === null) {
            return redirect()->to('/hr/payroll/my-payslips')->with('error', 'Payslip not found.');
        }

        if ((int) $payslip['user_id'] !== (int) $this->currentUserId() && (! can('payroll.edit') || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $this->employeeCompanyId((int) $payslip['user_id'])))) {
            return redirect()->to('/hr/payroll/my-payslips')->with('error', 'You do not have permission to view this payslip.');
        }

        return view('App\Modules\HR\payroll/payslip', [
            'title'     => 'Payslip - ' . $this->monthOptions()[$payslip['month']] . ' ' . $payslip['year'],
            'navActive' => 'hr-payroll',
            'payslip'   => $payslip,
            'monthName' => $this->monthOptions()[$payslip['month']],
        ]);
    }

    public function payslipPdf(int $id)
    {
        $payslip = $this->payslipModel->withRelations($id);

        if ($payslip === null) {
            return redirect()->to('/hr/payroll/my-payslips')->with('error', 'Payslip not found.');
        }

        if ((int) $payslip['user_id'] !== (int) $this->currentUserId() && (! can('payroll.edit') || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $this->employeeCompanyId((int) $payslip['user_id'])))) {
            return redirect()->to('/hr/payroll/my-payslips')->with('error', 'You do not have permission to view this payslip.');
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('App\Modules\HR\exports/payslip_pdf', [
            'payslip'   => $payslip,
            'monthName' => $this->monthOptions()[$payslip['month']],
        ]));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="payslip-' . $this->monthOptions()[$payslip['month']] . '-' . $payslip['year'] . '.pdf"')
            ->setBody($dompdf->output());
    }

    public function editStructure(int $userId)
    {
        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $this->employeeCompanyId($userId))) {
            return redirect()->to('/hr/employees')->with('error', 'Employee not found.');
        }

        return view('App\Modules\HR\payroll/structure', [
            'title'     => 'Salary Structure',
            'navActive' => 'hr-payroll',
            'userId'    => $userId,
            'structure' => $this->structureModel->byUserId($userId),
        ]);
    }

    public function saveStructure(int $userId)
    {
        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $this->employeeCompanyId($userId))) {
            return redirect()->to('/hr/employees')->with('error', 'Employee not found.');
        }

        if (! $this->validate($this->structureModel->getValidationRules(), $this->structureModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'user_id'        => $userId,
            'basic'          => $this->request->getPost('basic') ?: 0,
            'hra'            => $this->request->getPost('hra') ?: 0,
            'allowances'     => $this->request->getPost('allowances') ?: 0,
            'deductions'     => $this->request->getPost('deductions') ?: 0,
            'effective_from' => $this->request->getPost('effective_from') ?: date('Y-m-d'),
        ];

        $existing = $this->structureModel->byUserId($userId);

        if ($existing !== null) {
            $this->structureModel->update($existing['id'], $data);
        } else {
            $this->structureModel->insert($data);
        }

        $this->logActivity('payroll', 'update', $userId, 'Updated salary structure for user #' . $userId);

        return redirect()->to('/hr/employees')->with('success', 'Salary structure saved.');
    }

    /**
     * Payroll/payslip records only carry a user_id — no company_id of
     * their own (see PayrollRunModel/PayslipModel) — so every direct-id
     * access check here goes through the target user's employee_profiles
     * row instead. null (no profile yet) always fails outOfScope() for
     * a scoped viewer, same "match nothing" convention as
     * companyScopeFor() itself.
     */
    private function employeeCompanyId(int $userId): ?int
    {
        $profile = $this->employeeModel->byUserId($userId);

        return $profile !== null ? (int) $profile['company_id'] : null;
    }

    private function monthOptions(): array
    {
        return [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];
    }
}
