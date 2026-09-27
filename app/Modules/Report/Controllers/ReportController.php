<?php

namespace App\Modules\Report\Controllers;

use App\Controllers\BaseController;
use App\Modules\Accounting\Models\BillModel;
use App\Modules\Accounting\Models\InvoiceModel;
use App\Modules\Accounting\Models\PaymentModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Compliance\Models\ComplianceItemModel;
use App\Modules\Department\Models\DepartmentModel;
use App\Modules\Expense\Models\ExpenseModel;
use App\Modules\Marketing\Models\CampaignModel;
use App\Modules\Meeting\Models\MeetingModel;
use App\Modules\Task\Models\ProjectModel;
use App\Modules\Task\Models\TaskModel;
use Dompdf\Dompdf;
use Dompdf\Options;

class ReportController extends BaseController
{
    protected TaskModel $taskModel;
    protected ComplianceItemModel $complianceModel;
    protected CompanyModel $companyModel;
    protected DepartmentModel $departmentModel;
    protected MeetingModel $meetingModel;
    protected ProjectModel $projectModel;
    protected ExpenseModel $expenseModel;
    protected InvoiceModel $invoiceModel;
    protected BillModel $billModel;
    protected PaymentModel $paymentModel;
    protected CampaignModel $campaignModel;

    public function __construct()
    {
        $this->taskModel       = new TaskModel();
        $this->complianceModel = new ComplianceItemModel();
        $this->companyModel    = new CompanyModel();
        $this->departmentModel = new DepartmentModel();
        $this->meetingModel    = new MeetingModel();
        $this->projectModel    = new ProjectModel();
        $this->expenseModel    = new ExpenseModel();
        $this->invoiceModel    = new InvoiceModel();
        $this->billModel       = new BillModel();
        $this->paymentModel    = new PaymentModel();
        $this->campaignModel   = new CampaignModel();
    }

    public function tasks()
    {
        $filters = $this->withCompanyScope(array_filter($this->request->getGet(['company_id', 'department_id', 'status']) ?? []));

        return view('App\Modules\Report\tasks', array_merge(
            ['title' => 'Task Summary Report', 'navActive' => 'reports', 'filters' => $filters],
            $this->taskReportData($filters)
        ));
    }

    public function exportTasks(string $format)
    {
        $filters = $this->withCompanyScope(array_filter($this->request->getGet(['company_id', 'department_id', 'status']) ?? []));
        $data    = $this->taskReportData($filters);

        if ($format === 'csv') {
            return $this->streamCsv(
                'task-summary-' . date('Y-m-d') . '.csv',
                ['Title', 'Company', 'Department', 'Assignee', 'Priority', 'Status', 'Due Date'],
                array_map(static fn (array $t) => [
                    $t['title'], $t['company_name'], $t['department_name'],
                    $t['assignee_name'] ?? 'Unassigned', ucfirst($t['priority']),
                    ucwords(str_replace('_', ' ', $t['status'])), $t['due_date'] ?? '',
                ], $data['tasks'])
            );
        }

        return $this->renderPdf('Report/exports/tasks_pdf', array_merge(['generatedAt' => date('d/m/Y, g:i A')], $data), 'task-summary-' . date('Y-m-d') . '.pdf');
    }

    public function compliance()
    {
        $filters = $this->withCompanyScope(array_filter($this->request->getGet(['company_id', 'compliance_type_id', 'status']) ?? []));

        return view('App\Modules\Report\compliance', array_merge(
            ['title' => 'Compliance Status Report', 'navActive' => 'reports', 'filters' => $filters],
            $this->complianceReportData($filters)
        ));
    }

    public function exportCompliance(string $format)
    {
        $filters = $this->withCompanyScope(array_filter($this->request->getGet(['company_id', 'compliance_type_id', 'status']) ?? []));
        $data    = $this->complianceReportData($filters);

        if ($format === 'csv') {
            return $this->streamCsv(
                'compliance-status-' . date('Y-m-d') . '.csv',
                ['Title', 'Company', 'Type', 'Responsible', 'Status', 'Due Date'],
                array_map(static fn (array $i) => [
                    $i['title'], $i['company_name'], $i['type_name'],
                    $i['responsible_name'] ?? 'Unassigned',
                    ucwords(str_replace('_', ' ', $i['status'])), $i['due_date'],
                ], $data['items'])
            );
        }

        return $this->renderPdf('Report/exports/compliance_pdf', array_merge(['generatedAt' => date('d/m/Y, g:i A')], $data), 'compliance-status-' . date('Y-m-d') . '.pdf');
    }

    public function companies()
    {
        return view('App\Modules\Report\companies', array_merge(
            ['title' => 'Company Overview Report', 'navActive' => 'reports'],
            ['rows' => $this->companyOverviewData($this->companyScopeFor(self::COMPANY_SCOPED_ROLES))]
        ));
    }

    public function exportCompanies(string $format)
    {
        $rows = $this->companyOverviewData($this->companyScopeFor(self::COMPANY_SCOPED_ROLES));

        if ($format === 'csv') {
            return $this->streamCsv(
                'company-overview-' . date('Y-m-d') . '.csv',
                ['Company', 'Active Tasks', 'Overdue Tasks', 'Completed Tasks', 'Projects', 'Compliance Pending', 'Compliance Overdue', 'Upcoming Meetings'],
                array_map(static fn (array $r) => [
                    $r['company'], $r['tasks_active'], $r['tasks_overdue'], $r['tasks_completed'],
                    $r['projects'], $r['compliance_pending'], $r['compliance_overdue'], $r['meetings_upcoming'],
                ], $rows)
            );
        }

        return $this->renderPdf('Report/exports/companies_pdf', ['rows' => $rows, 'generatedAt' => date('d/m/Y, g:i A')], 'company-overview-' . date('Y-m-d') . '.pdf');
    }

    public function expenses()
    {
        $filters = $this->withCompanyScope(array_filter($this->request->getGet(['company_id', 'category', 'status']) ?? []));
        $period  = $this->expensePeriod();

        return view('App\Modules\Report\expenses', array_merge(
            ['title' => 'Expense Report', 'navActive' => 'reports', 'filters' => $filters],
            $period,
            $this->expenseReportData($filters, $period)
        ));
    }

    public function exportExpenses(string $format)
    {
        $filters = $this->withCompanyScope(array_filter($this->request->getGet(['company_id', 'category', 'status']) ?? []));
        $period  = $this->expensePeriod();
        $data    = array_merge($period, $this->expenseReportData($filters, $period));

        $slug = str_replace(' ', '-', strtolower($period['periodLabel']));

        if ($format === 'csv') {
            return $this->streamCsv(
                'expense-report-' . $slug . '.csv',
                ['Vendor', 'Company', 'Category', 'Billing Cycle', 'Amount', 'Renewal Date', 'Status'],
                array_map(static fn (array $e) => [
                    $e['vendor'], $e['company_name'], $e['category'], ucfirst($e['billing_cycle']),
                    $e['amount'], $e['renewal_date'] ?? '', ucfirst($e['status']),
                ], $data['expenses'])
            );
        }

        return $this->renderPdf('Report/exports/expenses_pdf', array_merge(['generatedAt' => date('d/m/Y, g:i A')], $data), 'expense-report-' . $slug . '.pdf');
    }

    public function receivables()
    {
        $filters = $this->withCompanyScope(array_filter($this->request->getGet(['company_id']) ?? []));

        return view('App\Modules\Report\receivables', array_merge(
            ['title' => 'Receivables Report', 'navActive' => 'reports', 'filters' => $filters],
            $this->receivablesData($filters)
        ));
    }

    public function exportReceivables(string $format)
    {
        $filters = $this->withCompanyScope(array_filter($this->request->getGet(['company_id']) ?? []));
        $data    = $this->receivablesData($filters);

        if ($format === 'csv') {
            return $this->streamCsv(
                'receivables-' . date('Y-m-d') . '.csv',
                ['Invoice', 'Company', 'Customer', 'Amount', 'Due Date', 'Status'],
                array_map(static fn (array $i) => [
                    $i['invoice_number'], $i['company_name'], $i['customer_name'],
                    $i['amount'], $i['due_date'] ?? '', ucwords(str_replace('_', ' ', $i['status'])),
                ], $data['invoices'])
            );
        }

        return $this->renderPdf('Report/exports/receivables_pdf', array_merge(['generatedAt' => date('d/m/Y, g:i A')], $data), 'receivables-' . date('Y-m-d') . '.pdf');
    }

    public function payables()
    {
        $filters = $this->withCompanyScope(array_filter($this->request->getGet(['company_id']) ?? []));

        return view('App\Modules\Report\payables', array_merge(
            ['title' => 'Payables Report', 'navActive' => 'reports', 'filters' => $filters],
            $this->payablesData($filters)
        ));
    }

    public function exportPayables(string $format)
    {
        $filters = $this->withCompanyScope(array_filter($this->request->getGet(['company_id']) ?? []));
        $data    = $this->payablesData($filters);

        if ($format === 'csv') {
            return $this->streamCsv(
                'payables-' . date('Y-m-d') . '.csv',
                ['Bill', 'Company', 'Vendor', 'Amount', 'Due Date', 'Status'],
                array_map(static fn (array $b) => [
                    $b['bill_number'], $b['company_name'], $b['vendor_name'],
                    $b['amount'], $b['due_date'] ?? '', ucwords(str_replace('_', ' ', $b['status'])),
                ], $data['bills'])
            );
        }

        return $this->renderPdf('Report/exports/payables_pdf', array_merge(['generatedAt' => date('d/m/Y, g:i A')], $data), 'payables-' . date('Y-m-d') . '.pdf');
    }

    public function financials()
    {
        $companyId = $this->scopedCompanyId($this->request->getGet('company_id') ?: null);
        $from      = $this->request->getGet('from') ?: date('Y-m-01');
        $to        = $this->request->getGet('to') ?: date('Y-m-d');

        return view('App\Modules\Report\financials', array_merge(
            ['title' => 'Financial Summary', 'navActive' => 'reports', 'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()), 'companyId' => $companyId, 'from' => $from, 'to' => $to],
            $this->financialsData($companyId, $from, $to)
        ));
    }

    public function exportFinancials(string $format)
    {
        $companyId = $this->scopedCompanyId($this->request->getGet('company_id') ?: null);
        $from      = $this->request->getGet('from') ?: date('Y-m-01');
        $to        = $this->request->getGet('to') ?: date('Y-m-d');
        $data      = $this->financialsData($companyId, $from, $to);

        if ($format === 'csv') {
            return $this->streamCsv(
                'financial-summary-' . date('Y-m-d') . '.csv',
                ['Metric', 'Amount'],
                [
                    ['Revenue Collected (cash in)', $data['cashIn']],
                    ['Bills Paid (cash out)', $data['cashOut']],
                    ['Operating Expenses (in period)', $data['periodExpenses']],
                    ['Net Cash Flow', $data['netCashFlow']],
                    ['Net Profit / Loss', $data['netProfitLoss']],
                    ['Outstanding Receivables', $data['outstandingReceivables']],
                    ['Outstanding Payables', $data['outstandingPayables']],
                ]
            );
        }

        return $this->renderPdf('Report/exports/financials_pdf', array_merge(['generatedAt' => date('d/m/Y, g:i A'), 'from' => $from, 'to' => $to], $data), 'financial-summary-' . date('Y-m-d') . '.pdf');
    }

    public function profitLoss()
    {
        $companyId    = $this->scopedCompanyId($this->request->getGet('company_id') ?: null);
        $companyIdInt = $companyId !== null ? (int) $companyId : null;
        [$year, $month] = $this->parseMonthParam($this->request->getGet('month'));

        return view('App\Modules\Report\profit_loss', array_merge(
            ['title' => 'Profit & Loss', 'navActive' => 'reports', 'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()), 'companyId' => $companyId],
            $this->profitLossMonthlyData($companyIdInt, $year, $month)
        ));
    }

    public function exportProfitLoss(string $format)
    {
        $companyId    = $this->scopedCompanyId($this->request->getGet('company_id') ?: null);
        $companyIdInt = $companyId !== null ? (int) $companyId : null;
        [$year, $month] = $this->parseMonthParam($this->request->getGet('month'));
        $data = $this->profitLossMonthlyData($companyIdInt, $year, $month);

        if ($format === 'csv') {
            $header = ['Line Item', 'Category'];
            foreach ($data['perMonth'] as $m) {
                $header[] = $m['label'];
            }
            $header[] = 'Total';

            $rows = [];

            $revenueRow = ['Invoiced Revenue', 'Income'];
            foreach ($data['perMonth'] as $m) {
                $revenueRow[] = $m['revenue'];
            }
            $revenueRow[] = $data['totalRevenue'];
            $rows[] = $revenueRow;

            foreach ($data['categories'] as $cat) {
                $row = [$cat, 'Operating'];
                foreach ($data['perMonth'] as $m) {
                    $row[] = $m['byCategory'][$cat] ?? 0;
                }
                $row[] = $data['categoryTotals'][$cat];
                $rows[] = $row;
            }

            $billsRow = ['Vendor Bills', 'Payables'];
            foreach ($data['perMonth'] as $m) {
                $billsRow[] = $m['vendorBills'];
            }
            $billsRow[] = $data['totalVendorBills'];
            $rows[] = $billsRow;

            $netRow = ['NET PROFIT', ''];
            foreach ($data['perMonth'] as $m) {
                $netRow[] = $m['netProfit'];
            }
            $netRow[] = $data['netProfit'];
            $rows[] = $netRow;

            return $this->streamCsv('profit-loss-' . date('Y-m-d') . '.csv', $header, $rows);
        }

        return $this->renderPdf('Report/exports/profit_loss_pdf', array_merge(['generatedAt' => date('d/m/Y, g:i A')], $data), 'profit-loss-' . date('Y-m-d') . '.pdf');
    }

    public function incomeVsExpense()
    {
        $companyId = $this->scopedCompanyId($this->request->getGet('company_id') ?: null);
        $from      = $this->request->getGet('from') ?: date('Y-m-01', strtotime('-5 months'));
        $to        = $this->request->getGet('to') ?: date('Y-m-d');

        return view('App\Modules\Report\income_vs_expense', array_merge(
            ['title' => 'Income vs Expense', 'navActive' => 'reports', 'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()), 'companyId' => $companyId, 'from' => $from, 'to' => $to],
            $this->incomeVsExpenseData($companyId, $from, $to)
        ));
    }

    public function exportIncomeVsExpense(string $format)
    {
        $companyId = $this->scopedCompanyId($this->request->getGet('company_id') ?: null);
        $from      = $this->request->getGet('from') ?: date('Y-m-01', strtotime('-5 months'));
        $to        = $this->request->getGet('to') ?: date('Y-m-d');
        $data      = $this->incomeVsExpenseData($companyId, $from, $to);

        if ($format === 'csv') {
            return $this->streamCsv(
                'income-vs-expense-' . date('Y-m-d') . '.csv',
                ['Period', 'Income', 'Expense', 'Difference', 'Trend %'],
                array_map(static fn (array $r) => [
                    $r['label'], $r['income'], $r['expense'], $r['difference'],
                    match ($r['trend']['state']) {
                        'new'     => 'New',
                        'percent' => $r['trend']['percent'],
                        default   => '',
                    },
                ], $data['rows'])
            );
        }

        return $this->renderPdf('Report/exports/income_vs_expense_pdf', array_merge(['generatedAt' => date('d/m/Y, g:i A'), 'from' => $from, 'to' => $to], $data), 'income-vs-expense-' . date('Y-m-d') . '.pdf');
    }

    public function campaigns()
    {
        $filters = $this->withCompanyScope(array_filter($this->request->getGet(['company_id', 'channel', 'status']) ?? []));

        return view('App\Modules\Report\campaigns', array_merge(
            ['title' => 'Campaign Performance Report', 'navActive' => 'reports', 'filters' => $filters],
            $this->campaignReportData($filters)
        ));
    }

    public function exportCampaigns(string $format)
    {
        $filters = $this->withCompanyScope(array_filter($this->request->getGet(['company_id', 'channel', 'status']) ?? []));
        $data    = $this->campaignReportData($filters);

        if ($format === 'csv') {
            return $this->streamCsv(
                'campaign-performance-' . date('Y-m-d') . '.csv',
                ['Campaign', 'Company', 'Channel', 'Budget', 'Leads', 'Conversions', 'Revenue', 'ROI %', 'Status'],
                array_map(fn (array $c) => [
                    $c['name'], $c['company_name'], CampaignModel::CHANNEL_LABELS[$c['channel']],
                    $c['budget'], $c['leads'], $c['conversions'], $c['revenue'],
                    $this->campaignModel->roiPercent($c) ?? 'N/A', ucfirst($c['status']),
                ], $data['campaigns'])
            );
        }

        return $this->renderPdf('Report/exports/campaigns_pdf', array_merge(['generatedAt' => date('d/m/Y, g:i A')], $data), 'campaign-performance-' . date('Y-m-d') . '.pdf');
    }

    private function campaignReportData(array $filters): array
    {
        $campaigns = $this->campaignModel->filtered($filters)->findAll();

        $byChannel = [];
        foreach ($campaigns as &$c) {
            $c['roi'] = $this->campaignModel->roiPercent($c);
            $byChannel[$c['channel']] = ($byChannel[$c['channel']] ?? 0) + 1;
        }
        unset($c);

        $totalBudget  = array_sum(array_column($campaigns, 'budget'));
        $totalRevenue = array_sum(array_column($campaigns, 'revenue'));

        return [
            'campaigns'    => $campaigns,
            'byChannel'    => $byChannel,
            'totalBudget'  => $totalBudget,
            'totalRevenue' => $totalRevenue,
            'totalLeads'   => array_sum(array_column($campaigns, 'leads')),
            'totalConversions' => array_sum(array_column($campaigns, 'conversions')),
            'overallRoi'   => $totalBudget > 0 ? round((($totalRevenue - $totalBudget) / $totalBudget) * 100, 1) : null,
            'companies'    => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'channels'     => CampaignModel::CHANNELS,
            'statuses'     => CampaignModel::STATUSES,
        ];
    }

    /**
     * Reads ?period=month|year, ?month=1-12, ?year=YYYY from the request
     * (defaulting to the current month/year) and turns them into the
     * date bounds expenseReportData() and the view both need.
     */
    private function expensePeriod(): array
    {
        $periodType = $this->request->getGet('period') === 'year' ? 'year' : 'month';
        $year       = (int) ($this->request->getGet('year') ?: date('Y'));
        $month      = (int) ($this->request->getGet('month') ?: date('n'));
        $month      = max(1, min(12, $month));

        $periodEnd = $periodType === 'year'
            ? date('Y-m-d 23:59:59', strtotime("{$year}-12-31"))
            : date('Y-m-t 23:59:59', strtotime("{$year}-{$month}-01"));

        return [
            'periodType'  => $periodType,
            'periodYear'  => $year,
            'periodMonth' => $month,
            'periodEnd'   => $periodEnd,
            'periodLabel' => $periodType === 'year' ? (string) $year : date('F Y', strtotime("{$year}-{$month}-01")),
        ];
    }

    private function expenseReportData(array $filters, array $period): array
    {
        $expenses = $this->expenseModel->filtered($filters)
            ->where('expenses.created_at <=', $period['periodEnd'])
            ->findAll();

        $byCategory = [];
        $total      = 0.0;

        foreach ($expenses as $e) {
            $byCategory[$e['category']] = ($byCategory[$e['category']] ?? 0) + (float) $e['amount'];
            $total += (float) $e['amount'];
        }

        $companyId    = $filters['company_id'] ?? null;
        $monthlyTotal = $this->expenseModel->monthlyEquivalentAsOf($period['periodEnd'], $companyId);

        $monthlyBreakdown = [];
        if ($period['periodType'] === 'year') {
            for ($m = 1; $m <= 12; $m++) {
                $asOf                 = date('Y-m-t 23:59:59', strtotime("{$period['periodYear']}-{$m}-01"));
                $monthlyBreakdown[$m] = $this->expenseModel->monthlyEquivalentAsOf($asOf, $companyId);
            }
        }

        // Same monthlyEquivalentAsOf() the yearly breakdown above already
        // uses, just pointed at the prior period — a genuine period-over-
        // period comparison for the report's "growth" stat tile, not a
        // fabricated number. Null (not 0) when there's nothing in the
        // prior period to compare against, so the view can show "—"
        // instead of a meaningless "+∞%".
        $previousAsOf = $period['periodType'] === 'year'
            ? date('Y-m-d 23:59:59', strtotime(($period['periodYear'] - 1) . '-12-31'))
            : date('Y-m-t 23:59:59', strtotime(date('Y-m-01', strtotime($period['periodEnd'])) . ' -1 month'));
        $previousTotal = $this->expenseModel->monthlyEquivalentAsOf($previousAsOf, $companyId);
        $growthPercent = $previousTotal > 0 ? round((($monthlyTotal - $previousTotal) / $previousTotal) * 100, 1) : null;

        $topCategory = null;
        if (! empty($byCategory)) {
            arsort($byCategory);
            $topCategory = array_key_first($byCategory);
        }

        return [
            'expenses'         => $expenses,
            'byCategory'       => $byCategory,
            'total'            => $total,
            'monthlyTotal'     => $monthlyTotal,
            'yearlyTotal'      => $period['periodType'] === 'year' ? array_sum($monthlyBreakdown) : $monthlyTotal * 12,
            'monthlyBreakdown' => $monthlyBreakdown,
            'companies'        => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'topCategory'      => $topCategory,
            'growthPercent'    => $growthPercent,
        ];
    }

    private function receivablesData(array $filters): array
    {
        $this->invoiceModel->refreshOverdueStatuses();

        $invoices = $this->invoiceModel->filtered(array_merge($filters, []))
            ->whereIn('invoices.status', InvoiceModel::OPEN_STATUSES)
            ->findAll();

        return [
            'invoices'  => $invoices,
            'total'     => array_sum(array_column($invoices, 'amount')),
            'overdue'   => count(array_filter($invoices, static fn ($i) => $i['status'] === 'overdue')),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
        ];
    }

    private function payablesData(array $filters): array
    {
        $this->billModel->refreshOverdueStatuses();

        $bills = $this->billModel->filtered(array_merge($filters, []))
            ->whereIn('bills.status', BillModel::OPEN_STATUSES)
            ->findAll();

        return [
            'bills'     => $bills,
            'total'     => array_sum(array_column($bills, 'amount')),
            'overdue'   => count(array_filter($bills, static fn ($b) => $b['status'] === 'overdue')),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
        ];
    }

    /**
     * A simplified, informational financial snapshot â€” not a formal P&L
     * or balance sheet. Revenue/Bills-paid/Cash-flow come straight from
     * the payments ledger (accurate); "Operating Expenses" is a proxy
     * (active subscriptions whose renewal falls inside the period), not
     * full accrual accounting. See HR Payroll's snapshot pattern for the
     * same "disclose the simplification" approach.
     */
    private function financialsData(?string $companyId, string $from, string $to): array
    {
        $companyIdInt = $companyId !== null ? (int) $companyId : null;

        $cashTotals = $this->paymentModel->cashInOutTotals($companyIdInt, $from, $to);

        $expenseBuilder = $this->expenseModel->where('status', 'active')
            ->where('renewal_date >=', $from)
            ->where('renewal_date <=', $to);
        if ($companyIdInt !== null) {
            $expenseBuilder->where('company_id', $companyIdInt);
        }
        $periodExpenses = (float) ($expenseBuilder->selectSum('amount')->first()['amount'] ?? 0);

        $receivablesBuilder = $this->invoiceModel->whereIn('status', InvoiceModel::OPEN_STATUSES);
        $payablesBuilder    = $this->billModel->whereIn('status', BillModel::OPEN_STATUSES);
        if ($companyIdInt !== null) {
            $receivablesBuilder->where('company_id', $companyIdInt);
            $payablesBuilder->where('company_id', $companyIdInt);
        }

        return [
            'cashIn'                  => $cashTotals['in'],
            'cashOut'                 => $cashTotals['out'],
            'netCashFlow'             => $cashTotals['in'] - $cashTotals['out'],
            'periodExpenses'          => $periodExpenses,
            'netProfitLoss'           => $cashTotals['in'] - $cashTotals['out'] - $periodExpenses,
            'outstandingReceivables'  => (float) ($receivablesBuilder->selectSum('amount')->first()['amount'] ?? 0),
            'outstandingPayables'     => (float) ($payablesBuilder->selectSum('amount')->first()['amount'] ?? 0),
        ];
    }

    /**
     * A genuine (if simplified) accrual Profit & Loss statement, laid
     * out as a Line Item x Month statement (the selected month plus the
     * 2 before it, plus a Total column) — deliberately different
     * methodology from financialsData(), which is explicitly cash-basis.
     * Revenue is every non-draft, non-cancelled invoice *issued* in each
     * month (recognized when earned). There's no real sub-category for
     * invoice revenue in this data model (Invoice has no category field),
     * so Revenue stays a single line — inventing named revenue streams
     * that don't exist in the schema would misrepresent the data.
     * Expenses DO have a real category dimension (ExpenseModel::category,
     * same expense_categories every other Expense screen uses), so each
     * month's "EXPENSES" rows are real per-category monthly-equivalent
     * snapshots (see ExpenseModel::monthlyEquivalentByCategoryAsOf()),
     * evaluated as of that month's end — subscriptions aren't discrete
     * dated transactions, so this is an as-of figure, not a period sum.
     * Vendor Bills has no category field either, so it stays its own
     * single line.
     */
    private function profitLossMonthlyData(?int $companyId, int $selectedYear, int $selectedMonth): array
    {
        $perMonth       = [];
        $categoriesSeen = [];

        for ($offset = -2; $offset <= 0; $offset++) {
            [$y, $mo] = $this->shiftMonth($selectedYear, $selectedMonth, $offset);
            $bounds   = $this->monthBounds($y, $mo);

            $revenue     = $this->periodInvoiceTotal($companyId, $bounds['start'], $bounds['end']);
            $vendorBills = $this->periodBillTotal($companyId, $bounds['start'], $bounds['end']);

            $byCategory = $this->expenseModel->monthlyEquivalentByCategoryAsOf($bounds['end'] . ' 23:59:59', $companyId);
            foreach (array_keys($byCategory) as $cat) {
                $categoriesSeen[$cat] = true;
            }

            $totalExpenses = array_sum($byCategory) + $vendorBills;

            $perMonth[] = [
                'label'         => $bounds['label'],
                'revenue'       => $revenue,
                'vendorBills'   => $vendorBills,
                'byCategory'    => $byCategory,
                'totalExpenses' => $totalExpenses,
                'netProfit'     => $revenue - $totalExpenses,
            ];
        }

        $categories = array_keys($categoriesSeen);
        sort($categories);

        $categoryTotals = [];
        foreach ($categories as $cat) {
            $categoryTotals[$cat] = round(array_sum(array_map(
                static fn (array $month) => $month['byCategory'][$cat] ?? 0.0,
                $perMonth
            )), 2);
        }

        $totalRevenue     = array_sum(array_column($perMonth, 'revenue'));
        $totalVendorBills = array_sum(array_column($perMonth, 'vendorBills'));
        $totalExpenses    = array_sum(array_column($perMonth, 'totalExpenses'));
        $netProfit        = $totalRevenue - $totalExpenses;

        return [
            'perMonth'         => $perMonth,
            'categories'       => $categories,
            'categoryTotals'   => $categoryTotals,
            'totalRevenue'     => $totalRevenue,
            'totalVendorBills' => $totalVendorBills,
            'totalExpenses'    => $totalExpenses,
            'netProfit'        => $netProfit,
            'margin'           => $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 1) : null,
            'selectedYear'     => $selectedYear,
            'selectedMonth'    => $selectedMonth,
            'monthValue'       => sprintf('%04d-%02d', $selectedYear, $selectedMonth),
            'monthOptions'     => $this->recentMonthOptions(),
        ];
    }

    /**
     * "YYYY-MM" (the <select> value the month dropdown submits) back
     * into [year, month] — defaults to the current month for a
     * missing/malformed value, same "sane default over an error"
     * convention expensePeriod() already uses for its month/year params.
     */
    private function parseMonthParam(?string $param): array
    {
        if ($param && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $param, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [(int) date('Y'), (int) date('n')];
    }

    private function monthBounds(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);

        return [
            'start' => $start,
            'end'   => date('Y-m-t', strtotime($start)),
            'label' => date('M Y', strtotime($start)),
        ];
    }

    /**
     * Adds $delta months to (year, month) — e.g. shiftMonth(2026, 1, -2)
     * = [2025, 11]. Used to walk back to "the 2 months before the
     * selected one" for the statement's 3 comparison columns.
     */
    private function shiftMonth(int $year, int $month, int $delta): array
    {
        $absolute = ($year * 12) + ($month - 1) + $delta;

        return [intdiv($absolute, 12), ($absolute % 12) + 1];
    }

    /**
     * The month picker's option list — the current month and the 11
     * before it, newest first.
     */
    private function recentMonthOptions(int $count = 12): array
    {
        $options = [];
        for ($i = 0; $i < $count; $i++) {
            [$y, $mo] = $this->shiftMonth((int) date('Y'), (int) date('n'), -$i);
            $options[] = ['value' => sprintf('%04d-%02d', $y, $mo), 'label' => date('M Y', strtotime(sprintf('%04d-%02d-01', $y, $mo)))];
        }

        return $options;
    }

    /**
     * Month-by-month Income vs Expense — same two components profitLossData()
     * already uses (invoices issued in the window vs vendor bills issued +
     * the operating-expense monthly-equivalent snapshot), just broken out
     * per calendar month instead of summed once over the whole range. Each
     * row's "Trend %" is the swing in that month's Income-minus-Expense
     * versus the previous row's, same "null when there's no baseline"
     * convention as expenseReportData()'s growthPercent.
     */
    private function incomeVsExpenseData(?string $companyId, string $from, string $to): array
    {
        $companyIdInt = $companyId !== null ? (int) $companyId : null;

        $rows                = [];
        $previousDifference  = null;

        foreach ($this->monthsBetween($from, $to) as $month) {
            $income     = $this->periodInvoiceTotal($companyIdInt, $month['start'], $month['end']);
            $expense    = $this->periodExpenseTotal($companyIdInt, $month['start'], $month['end']);
            $difference = $income - $expense;

            $rows[] = [
                'label'      => $month['label'],
                'income'     => $income,
                'expense'    => $expense,
                'difference' => $difference,
                'trend'      => $this->trendState($difference, $previousDifference),
            ];

            $previousDifference = $difference;
        }

        $totalIncome   = array_sum(array_column($rows, 'income'));
        $totalExpenses = array_sum(array_column($rows, 'expense'));
        $netDifference = $totalIncome - $totalExpenses;
        $expenseRatio  = $totalIncome > 0 ? round(($totalExpenses / $totalIncome) * 100, 1) : null;

        // Prior period of equal length immediately before $from — what
        // each of the four stat-tile badges compares against, same
        // "compare against the immediately preceding window" idea as
        // expenseReportData()'s own growth tile.
        $days      = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
        $priorTo   = date('Y-m-d', strtotime($from . ' -1 day'));
        $priorFrom = date('Y-m-d', strtotime($priorTo . ' -' . ($days - 1) . ' days'));

        $priorIncome     = $this->periodInvoiceTotal($companyIdInt, $priorFrom, $priorTo);
        $priorExpenses   = $this->periodExpenseTotal($companyIdInt, $priorFrom, $priorTo);
        $priorDifference = $priorIncome - $priorExpenses;
        $priorRatio      = $priorIncome > 0 ? ($priorExpenses / $priorIncome) * 100 : null;

        return [
            'rows'            => $rows,
            'totalIncome'     => $totalIncome,
            'totalExpenses'   => $totalExpenses,
            'netDifference'   => $netDifference,
            'expenseRatio'    => $expenseRatio,
            'incomeTrend'     => $this->trendState($totalIncome, $priorIncome),
            'expenseTrend'    => $this->trendState($totalExpenses, $priorExpenses),
            'differenceTrend' => $this->trendState($netDifference, $priorDifference),
            'ratioTrend'      => $this->trendState($expenseRatio, $priorRatio),
        ];
    }

    /**
     * Every calendar month touched by [$from, $to], inclusive of partial
     * months at either end — each with its own clamped start/end so the
     * first and last months don't pull in totals from outside the
     * requested range.
     */
    private function monthsBetween(string $from, string $to): array
    {
        $months  = [];
        $cursor  = strtotime(date('Y-m-01', strtotime($from)));
        $endMark = strtotime(date('Y-m-01', strtotime($to)));

        while ($cursor <= $endMark) {
            $monthStart = date('Y-m-01', $cursor);
            $monthEnd   = date('Y-m-t', $cursor);

            $months[] = [
                'start' => max($monthStart, $from),
                'end'   => min($monthEnd, $to),
                'label' => date('F Y', $cursor),
            ];

            $cursor = strtotime('+1 month', $cursor);
        }

        return $months;
    }

    private function periodInvoiceTotal(?int $companyId, string $start, string $end): float
    {
        $builder = $this->invoiceModel->whereNotIn('status', ['draft', 'cancelled'])
            ->where('issue_date >=', $start)
            ->where('issue_date <=', $end);
        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }

        return (float) ($builder->selectSum('amount')->first()['amount'] ?? 0);
    }

    private function periodBillTotal(?int $companyId, string $start, string $end): float
    {
        $builder = $this->billModel->where('status !=', 'cancelled')
            ->where('issue_date >=', $start)
            ->where('issue_date <=', $end);
        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }

        return (float) ($builder->selectSum('amount')->first()['amount'] ?? 0);
    }

    /**
     * Vendor bills issued in the window (real dated transactions) plus
     * the operating-expense monthly-equivalent as of the window's end —
     * the exact same two-part formula profitLossMonthlyData() uses for
     * its per-month totalExpenses, just evaluated per month here.
     */
    private function periodExpenseTotal(?int $companyId, string $start, string $end): float
    {
        return $this->periodBillTotal($companyId, $start, $end)
            + $this->expenseModel->monthlyEquivalentAsOf($end . ' 23:59:59', $companyId);
    }

    /**
     * A display-ready trend for a (current, previous) pair — used for
     * every trend badge on the Income vs Expense report (the 4 header
     * tiles and each monthly row). A plain previous-is-zero guard (as
     * expenseReportData()'s growthPercent uses) avoids a nonsensical
     * "+∞%", but silently returning null for a genuine jump from $0 to a
     * real number reads as "nothing happened" when the opposite is
     * true — so that case gets its own 'new' state instead of being
     * folded into 'none'. 'none' is reserved for when there's truly
     * nothing to report: no prior period at all, or both periods zero.
     *
     * @return array{state: 'none'|'new'|'percent', percent: ?float}
     */
    private function trendState(?float $current, ?float $previous): array
    {
        if ($current === null || $previous === null) {
            return ['state' => 'none', 'percent' => null];
        }
        if ($previous == 0.0 && $current == 0.0) {
            return ['state' => 'none', 'percent' => null];
        }
        if ($previous == 0.0) {
            return ['state' => 'new', 'percent' => null];
        }

        return ['state' => 'percent', 'percent' => round((($current - $previous) / abs($previous)) * 100, 1)];
    }

    private function taskReportData(array $filters): array
    {
        $tasks = $this->taskModel->filtered($filters)->findAll();

        $byStatus = [];
        foreach (TaskModel::STATUSES as $s) {
            $byStatus[$s] = 0;
        }
        $byPriority = [];
        foreach (TaskModel::PRIORITIES as $p) {
            $byPriority[$p] = 0;
        }
        $overdue = 0;

        foreach ($tasks as $t) {
            $byStatus[$t['status']]++;
            $byPriority[$t['priority']]++;
            if ($t['due_date'] && $t['due_date'] < date('Y-m-d') && ! in_array($t['status'], ['completed', 'cancelled'], true)) {
                $overdue++;
            }
        }

        return [
            'tasks'       => $tasks,
            'byStatus'    => $byStatus,
            'byPriority'  => $byPriority,
            'overdue'     => $overdue,
            'total'       => count($tasks),
            'companies'   => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'departments' => $this->departmentModel->optionsList(),
            'statuses'    => TaskModel::STATUSES,
        ];
    }

    private function complianceReportData(array $filters): array
    {
        $items = $this->complianceModel->filtered($filters)->findAll();

        $byStatus = [];
        foreach (ComplianceItemModel::STATUSES as $s) {
            $byStatus[$s] = 0;
        }

        foreach ($items as $i) {
            $byStatus[$i['status']]++;
        }

        return [
            'items'     => $items,
            'byStatus'  => $byStatus,
            'total'     => count($items),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'  => ComplianceItemModel::STATUSES,
        ];
    }

    /**
     * $companyScope (0 or a real id) limits the overview to that one
     * company — null means unrestricted, same convention as everywhere
     * else companyScopeFor() is used.
     */
    private function companyOverviewData(?int $companyScope = null): array
    {
        $rows  = [];
        $today = date('Y-m-d');

        $companies = $companyScope === null
            ? $this->companyModel->optionsList()
            : array_filter($this->companyModel->optionsList(), static fn (array $c) => (int) $c['id'] === $companyScope);

        foreach ($companies as $c) {
            $cid = $c['id'];

            $rows[] = [
                'company'             => $c['name'],
                'tasks_active'        => $this->taskModel->where('company_id', $cid)->whereNotIn('status', ['completed', 'cancelled'])->countAllResults(),
                'tasks_overdue'       => $this->taskModel->where('company_id', $cid)->where('due_date <', $today)->whereNotIn('status', ['completed', 'cancelled'])->countAllResults(),
                'tasks_completed'     => $this->taskModel->where('company_id', $cid)->where('status', 'completed')->countAllResults(),
                'projects'            => $this->projectModel->where('company_id', $cid)->countAllResults(),
                'compliance_pending'  => $this->complianceModel->where('company_id', $cid)->whereIn('status', ['pending', 'in_progress'])->countAllResults(),
                'compliance_overdue'  => $this->complianceModel->where('company_id', $cid)->where('status', 'overdue')->countAllResults(),
                'meetings_upcoming'   => $this->meetingModel->where('company_id', $cid)->where('meeting_date >=', $today)->countAllResults(),
            ];
        }

        return $rows;
    }

    /**
     * Merges Company Admin's company scope into a filter array — same
     * array_filter()-then-merge ordering used across the app so 0
     * ("no company assigned yet") isn't dropped as falsy.
     */
    private function withCompanyScope(array $filters): array
    {
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return $filters;
    }

    /**
     * Same scoping as withCompanyScope(), for the reports (financials)
     * that take a single company_id scalar instead of a filters array.
     */
    private function scopedCompanyId(?string $companyId): ?string
    {
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);

        return $companyScope !== null ? (string) $companyScope : $companyId;
    }

    private function renderPdf(string $view, array $data, string $filename)
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);

        // $view arrives as 'Report/exports/foo_pdf' (relative to this
        // module) — CI4's locator only resolves module views under their
        // registered PSR-4 namespace (App\Modules\Report\...), same as
        // every other view() call in this controller already uses.
        $namespacedView = 'App\\Modules\\' . str_replace('/', '\\', $view);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($namespacedView, $data));
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($dompdf->output());
    }

    private function streamCsv(string $filename, array $headers, array $rows)
    {
        $out = fopen('php://temp', 'w+');
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setContentType('text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }
}
