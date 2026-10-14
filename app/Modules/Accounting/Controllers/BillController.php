<?php

namespace App\Modules\Accounting\Controllers;

use App\Controllers\BaseController;
use App\Modules\Accounting\Models\BillItemModel;
use App\Modules\Accounting\Models\BillModel;
use App\Modules\Purchase\Models\PurchaseOrderModel;
use App\Modules\Accounting\Models\PaymentModel;
use App\Modules\Company\Models\CompanyModel;
use Dompdf\Dompdf;
use Dompdf\Options;

class BillController extends BaseController
{
    protected BillModel $billModel;
    protected BillItemModel $itemModel;
    protected PaymentModel $paymentModel;
    protected CompanyModel $companyModel;

    public function __construct()
    {
        $this->billModel    = new BillModel();
        $this->itemModel    = new BillItemModel();
        $this->paymentModel = new PaymentModel();
        $this->companyModel = new CompanyModel();
    }

    public function index()
    {
        $this->billModel->refreshOverdueStatuses();

        $filters = array_filter($this->request->getGet(['company_id', 'status', 'q', 'issue_from', 'issue_to']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Accounting\bills/index', [
            'title'     => 'Bills',
            'navActive' => 'accounting',
            'bills'     => $this->billModel->filtered($filters)->findAll(),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'  => BillModel::STATUSES,
            'filters'   => $filters,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Accounting\bills/form', $this->formData(null));
    }

    public function store()
    {
        if (! $this->validate($this->billModel->getValidationRules(), $this->billModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only record bills for your own company.');
        }

        if (($poError = $this->purchaseOrderError()) !== null) {
            return redirect()->back()->withInput()->with('error', $poError);
        }

        $items = $this->parseItems();

        if ($items === null) {
            return redirect()->back()->withInput()->with('error', 'Add at least one item with a description, quantity and rate.');
        }

        $totals = $this->billModel->calculateTotals(
            $items,
            (float) ($this->request->getPost('discount_percent') ?: 0),
            (float) ($this->request->getPost('gst_percent') ?: 0),
            (string) $this->request->getPost('gst_type')
        );

        if ($totals['amount'] > BillModel::MAX_AMOUNT) {
            return redirect()->back()->withInput()->with('error', 'The bill total is too large.');
        }

        // Same reasoning as InvoiceController::store() — already fully
        // validated above, and Model::insert() would otherwise re-run
        // $validationRules a second time using CI4's own {id}
        // placeholder, which can't resolve on a fresh insert.
        $this->billModel->skipValidation(true);
        $id = $this->billModel->insert($this->payload(true, $totals));
        $this->itemModel->replaceForBill($id, $totals['items']);
        $this->logActivity('accounting', 'create', $id, 'Created bill ' . $this->request->getPost('bill_number'));
        (new PurchaseOrderModel())->syncPaymentStatus($this->request->getPost('purchase_order_id') ? (int) $this->request->getPost('purchase_order_id') : null);

        return redirect()->to('/accounting/bills/' . $id)->with('success', 'Bill created.');
    }

    public function show(int $id)
    {
        $bill = $this->billModel->withRelations($id);

        if ($bill === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $bill['company_id'])) {
            return redirect()->to('/accounting/bills')->with('error', 'Bill not found.');
        }

        $paid = $this->paymentModel->totalForBill($id);

        return view('App\Modules\Accounting\bills/show', [
            'title'     => $bill['bill_number'],
            'navActive' => 'accounting',
            'bill'      => $bill,
            'company'   => $this->companyModel->find($bill['company_id']),
            'items'     => $this->itemModel->forBill($id),
            'payments'  => $this->paymentModel->forBill($id),
            'paid'      => $paid,
            'balance'   => max(0, (float) $bill['amount'] - $paid),
        ]);
    }

    public function print(int $id)
    {
        [$bill, $data] = $this->printData($id);

        if ($bill === null) {
            return redirect()->to('/accounting/bills')->with('error', 'Bill not found.');
        }

        if (($templated = $this->templatedDocument('bill', $id, true)) !== null) {
            return $templated;
        }

        return view('App\Modules\Accounting\bills/print', $data);
    }

    /**
     * Genuine downloadable PDF, alongside the browser-print view above —
     * same Dompdf setup InvoiceController::downloadPdf() uses.
     */
    public function downloadPdf(int $id)
    {
        [$bill, $data] = $this->printData($id);

        if ($bill === null) {
            return redirect()->to('/accounting/bills')->with('error', 'Bill not found.');
        }

        if (($templated = $this->templatedDocument('bill', $id, false)) !== null) {
            return $templated;
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('App\Modules\Accounting\bills/pdf', $data));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $bill['bill_number'] . '.pdf"')
            ->setBody($dompdf->output());
    }

    /**
     * Shared lookup + company-scope check + view data for print() and
     * downloadPdf() — same pattern as InvoiceController::printData().
     */
    private function printData(int $id): array
    {
        $bill = $this->billModel->withRelations($id);

        if ($bill === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $bill['company_id'])) {
            return [null, []];
        }

        $paid = $this->paymentModel->totalForBill($id);

        return [$bill, [
            'title'    => 'Bill ' . $bill['bill_number'],
            'bill'     => $bill,
            'company'  => $this->companyModel->find($bill['company_id']),
            'items'    => $this->itemModel->forBill($id),
            'payments' => $this->paymentModel->forBill($id),
            'paid'     => $paid,
            'balance'  => max(0, (float) $bill['amount'] - $paid),
        ]];
    }

    public function edit(int $id)
    {
        $bill = $this->billModel->find($id);

        if ($bill === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $bill['company_id'])) {
            return redirect()->to('/accounting/bills')->with('error', 'Bill not found.');
        }

        return view('App\Modules\Accounting\bills/form', $this->formData($bill));
    }

    public function update(int $id)
    {
        $bill = $this->billModel->find($id);

        if ($bill === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $bill['company_id'])) {
            return redirect()->to('/accounting/bills')->with('error', 'Bill not found.');
        }

        $rules = $this->billModel->getValidationRules();
        $rules['bill_number'] = "required|regex_match[/^[A-Za-z0-9-]+\$/]|max_length[40]|is_unique[bills.bill_number,id,{$id}]";

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only assign bills to your own company.');
        }

        if (($poError = $this->purchaseOrderError()) !== null) {
            return redirect()->back()->withInput()->with('error', $poError);
        }

        $items = $this->parseItems();

        if ($items === null) {
            return redirect()->back()->withInput()->with('error', 'Add at least one item with a description, quantity and rate.');
        }

        $totals = $this->billModel->calculateTotals(
            $items,
            (float) ($this->request->getPost('discount_percent') ?: 0),
            (float) ($this->request->getPost('gst_percent') ?: 0),
            (string) $this->request->getPost('gst_type')
        );

        if ($totals['amount'] > BillModel::MAX_AMOUNT) {
            return redirect()->back()->withInput()->with('error', 'The bill total is too large.');
        }

        $this->billModel->skipValidation(true);
        $this->billModel->update($id, $this->payload(false, $totals));
        $this->itemModel->replaceForBill($id, $totals['items']);

        // Keep the status consistent with what was actually paid if the total changed after payments.
        $paid = (new \App\Modules\Accounting\Models\PaymentModel())->totalForBill($id);
        if ($paid > 0 && (string) ($this->request->getPost('status') ?: 'unpaid') !== 'cancelled') {
            $this->billModel->update($id, ['status' => $paid >= (float) $totals['amount'] ? 'paid' : 'partially_paid']);
        }
        $this->logActivity('accounting', 'update', $id, 'Updated bill #' . $id);
        $poModel = new PurchaseOrderModel();
        $poModel->syncPaymentStatus(($bill['purchase_order_id'] ?? null) ? (int) $bill['purchase_order_id'] : null);   // the order it used to settle
        $poModel->syncPaymentStatus($this->request->getPost('purchase_order_id') ? (int) $this->request->getPost('purchase_order_id') : null);

        return redirect()->to('/accounting/bills/' . $id)->with('success', 'Bill updated.');
    }

    public function delete(int $id)
    {
        $bill = $this->billModel->find($id);

        if ($bill === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $bill['company_id'])) {
            return redirect()->to('/accounting/bills')->with('error', 'Bill not found.');
        }

        // Deleting would also delete (FK cascade) the payments made against it and so rewrite the cash ledger.
        if ((new \App\Modules\Accounting\Models\PaymentModel())->where('bill_id', $id)->countAllResults() > 0) {
            return redirect()->to('/accounting/bills/' . $id)->with('error', 'This bill has payments recorded against it and cannot be deleted. Set its status to Cancelled instead.');
        }

        $this->billModel->delete($id);
        $this->logActivity('accounting', 'delete', $id, 'Deleted bill #' . $id);
        (new PurchaseOrderModel())->syncPaymentStatus(($bill['purchase_order_id'] ?? null) ? (int) $bill['purchase_order_id'] : null);

        return redirect()->to('/accounting/bills')->with('success', 'Bill deleted.');
    }

    /** Approved purchase orders (any company in the viewer's scope) that a bill may settle. */
    private function approvedOrdersInScope(): array
    {
        $builder = db_connect()->table('purchase_orders po')->select('po.id, po.po_number, po.company_id, po.amount, v.name as vendor_name')
            ->join('vendors v', 'v.id = po.vendor_id')->where('po.approval_status', 'approved')->orderBy('po.id', 'DESC');

        $scope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($scope !== null) {
            $builder->where('po.company_id', $scope);
        }

        return $builder->get()->getResultArray();
    }

    /** A linked purchase order must be an approved order of the bill's own company. */
    private function purchaseOrderError(): ?string
    {
        $poId = (int) $this->request->getPost('purchase_order_id');
        if ($poId === 0) {
            return null;
        }

        $po = (new PurchaseOrderModel())->find($poId);

        return ($po !== null && $po['approval_status'] === 'approved' && (int) $po['company_id'] === (int) $this->request->getPost('company_id'))
            ? null
            : 'Choose an approved purchase order of the same company, or leave it blank.';
    }

    private function formData(?array $bill): array
    {
        return [
            'title'      => $bill ? 'Edit Bill' : 'Record Bill',
            'navActive'  => 'accounting',
            'bill'       => $bill,
            'items'      => $bill ? $this->itemModel->forBill($bill['id']) : [],
            'companies'  => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'   => BillModel::STATUSES,
            'gstTypes'   => BillModel::GST_TYPES,
            'nextNumber' => $bill['bill_number'] ?? $this->billModel->nextBillNumber(),
            'purchaseOrders' => $this->approvedOrdersInScope(),
        ];
    }

    private function payload(bool $isNew, array $totals): array
    {
        $data = [
            'company_id'       => (int) $this->request->getPost('company_id'),
            'purchase_order_id' => $this->request->getPost('purchase_order_id') ?: null,
            'bill_number'      => $this->request->getPost('bill_number'),
            'vendor_name'      => $this->request->getPost('vendor_name'),
            'vendor_gstin'     => $this->request->getPost('vendor_gstin') ?: null,
            'vendor_address'   => $this->request->getPost('vendor_address') ?: null,
            'issue_date'       => $this->request->getPost('issue_date'),
            'due_date'         => $this->request->getPost('due_date') ?: null,
            'status'           => $this->request->getPost('status') ?: 'unpaid',
            'notes'            => $this->request->getPost('notes'),
            'gst_type'         => $this->request->getPost('gst_type'),
            'gst_percent'      => $this->request->getPost('gst_percent') ?: 0,
            'discount_percent' => $this->request->getPost('discount_percent') ?: 0,
            'subtotal'         => $totals['subtotal'],
            'discount_amount'  => $totals['discount_amount'],
            'cgst_amount'      => $totals['cgst_amount'],
            'sgst_amount'      => $totals['sgst_amount'],
            'igst_amount'      => $totals['igst_amount'],
            'amount'           => $totals['amount'],
        ];

        if ($isNew) {
            $data['created_by'] = $this->currentUserId();
        }

        return $data;
    }

    /**
     * Same parsing as InvoiceController::parseItems() — reads the
     * item_description[]/item_quantity[]/item_rate[] arrays the form
     * posts, drops any row left blank, and validates what's left.
     */
    private function parseItems(): ?array
    {
        $descriptions = $this->request->getPost('item_description') ?? [];
        $quantities   = $this->request->getPost('item_quantity') ?? [];
        $rates        = $this->request->getPost('item_rate') ?? [];

        $items = [];

        foreach ($descriptions as $i => $description) {
            $description = trim((string) $description);
            $quantity    = $quantities[$i] ?? null;
            $rate        = $rates[$i] ?? null;

            if ($description === '' && $quantity === null && $rate === null) {
                continue;
            }

            if ($description === '' || ! is_numeric($quantity) || ! is_numeric($rate) || (float) $quantity <= 0 || (float) $rate < 0) {
                return null;
            }

            $items[] = ['description' => $description, 'quantity' => (float) $quantity, 'rate' => (float) $rate];
        }

        return $items === [] ? null : $items;
    }
}
