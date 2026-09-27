<?php

namespace App\Modules\Accounting\Controllers;

use App\Controllers\BaseController;
use App\Modules\Accounting\Models\BillModel;
use App\Modules\Accounting\Models\InvoiceModel;

class AccountingController extends BaseController
{
    protected InvoiceModel $invoiceModel;
    protected BillModel $billModel;

    public function __construct()
    {
        $this->invoiceModel = new InvoiceModel();
        $this->billModel    = new BillModel();
    }

    public function index()
    {
        $this->invoiceModel->refreshOverdueStatuses();
        $this->billModel->refreshOverdueStatuses();

        // Same company scope every other accounting screen (Invoices,
        // Bills, Reports → Financial Summary) already applies — without
        // this, these four figures were the one place on this page that
        // quietly summed every company's invoices/bills together.
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);

        // clone() before each independent query — $this->invoiceModel/
        // billModel are shared instances, and Model query-builder calls
        // return $this, so building multiple queries off the same
        // instance without executing between them would stack every
        // where() onto one accumulating builder instead of four
        // separate ones (same reason DashboardController::weekOverWeek()
        // clones before building).
        $scoped = static function ($model) use ($companyScope) {
            $query = clone $model;

            return $companyScope !== null ? $query->where('company_id', $companyScope) : $query;
        };

        return view('App\Modules\Accounting\index', [
            'title'     => 'Accounting',
            'navActive' => 'accounting',
            'outstandingReceivables' => $scoped($this->invoiceModel)->whereIn('status', InvoiceModel::OPEN_STATUSES)->selectSum('amount')->first()['amount'] ?? 0,
            'outstandingPayables'    => $scoped($this->billModel)->whereIn('status', BillModel::OPEN_STATUSES)->selectSum('amount')->first()['amount'] ?? 0,
            'overdueInvoiceCount'    => $scoped($this->invoiceModel)->where('status', 'overdue')->countAllResults(),
            'overdueBillCount'       => $scoped($this->billModel)->where('status', 'overdue')->countAllResults(),
        ]);
    }
}
