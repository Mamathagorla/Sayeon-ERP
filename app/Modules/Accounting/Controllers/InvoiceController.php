<?php

namespace App\Modules\Accounting\Controllers;

use App\Controllers\BaseController;
use App\Modules\Accounting\Models\InvoiceItemModel;
use App\Modules\Accounting\Models\InvoiceModel;
use App\Modules\Accounting\Models\PaymentModel;
use App\Modules\Company\Models\BankAccountModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Company\Models\CountryModel;
use App\Modules\Company\Models\StateModel;
use App\Modules\Customer\Models\CustomerModel;
use Dompdf\Dompdf;
use Dompdf\Options;

class InvoiceController extends BaseController
{
    protected InvoiceModel $invoiceModel;
    protected InvoiceItemModel $itemModel;
    protected PaymentModel $paymentModel;
    protected CompanyModel $companyModel;
    protected BankAccountModel $bankModel;
    protected CountryModel $countryModel;
    protected StateModel $stateModel;
    protected CustomerModel $customerModel;

    public function __construct()
    {
        $this->invoiceModel = new InvoiceModel();
        $this->itemModel    = new InvoiceItemModel();
        $this->paymentModel = new PaymentModel();
        $this->companyModel = new CompanyModel();
        $this->bankModel    = new BankAccountModel();
        $this->countryModel = new CountryModel();
        $this->stateModel   = new StateModel();
        $this->customerModel = new CustomerModel();
    }

    public function index()
    {
        $this->invoiceModel->refreshOverdueStatuses();

        $filters = array_filter($this->request->getGet(['company_id', 'status', 'q', 'issue_from', 'issue_to']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Accounting\invoices/index', [
            'title'     => 'Invoices',
            'navActive' => 'accounting',
            'invoices'  => $this->invoiceModel->filtered($filters)->findAll(),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'  => InvoiceModel::STATUSES,
            'filters'   => $filters,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Accounting\invoices/form', $this->formData(null));
    }

    public function store()
    {
        if (! $this->validate($this->invoiceModel->getValidationRules(), $this->invoiceModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only create invoices for your own company.');
        }

        if ($stateError = $this->stateError()) {
            return redirect()->back()->withInput()->with('error', $stateError);
        }

        $items = $this->parseItems();

        if ($items === null) {
            return redirect()->back()->withInput()->with('error', 'Add at least one item with a description, quantity and rate.');
        }

        $gstType = $this->resolveGstType();

        $totals = $this->invoiceModel->calculateTotals(
            $items,
            (float) ($this->request->getPost('discount_percent') ?: 0),
            (float) ($this->request->getPost('gst_percent') ?: 0),
            $gstType
        );

        if ($totals['amount'] > InvoiceModel::MAX_AMOUNT) {
            return redirect()->back()->withInput()->with('error', 'The invoice total is too large.');
        }

        // Already fully validated above via $this->validate() — that
        // call correctly substitutes the real id into invoice_number's
        // is_unique rule. Model::insert()/update() would otherwise
        // re-run $validationRules a second time using CI4's own {id}
        // placeholder, which only resolves from a primary key inside
        // $data — absent here, so on update() it silently evaluates the
        // unique check against an empty id and update() returns false
        // without throwing, discarding the change with no visible error.
        $this->invoiceModel->skipValidation(true);
        $id = $this->invoiceModel->insert($this->payload(true, $totals, $gstType));
        $this->itemModel->replaceForInvoice($id, $totals['items']);
        $this->logActivity('accounting', 'create', $id, 'Created invoice ' . $this->request->getPost('invoice_number'));

        return redirect()->to('/accounting/invoices/' . $id)->with('success', 'Invoice created.');
    }

    public function show(int $id)
    {
        $invoice = $this->invoiceModel->withRelations($id);

        if ($invoice === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $invoice['company_id'])) {
            return redirect()->to('/accounting/invoices')->with('error', 'Invoice not found.');
        }

        $paid = $this->paymentModel->totalForInvoice($id);

        return view('App\Modules\Accounting\invoices/show', [
            'title'     => $invoice['invoice_number'],
            'navActive' => 'accounting',
            'invoice'   => $invoice,
            'company'   => $this->companyModel->find($invoice['company_id']),
            'items'     => $this->itemModel->forInvoice($id),
            'payments'  => $this->paymentModel->forInvoice($id),
            'paid'      => $paid,
            'balance'   => max(0, (float) $invoice['amount'] - $paid),
        ]);
    }

    public function print(int $id)
    {
        [$invoice, $data] = $this->printData($id);

        if ($invoice === null) {
            return redirect()->to('/accounting/invoices')->with('error', 'Invoice not found.');
        }

        if (($templated = $this->templatedDocument('invoice', $id, true)) !== null) {
            return $templated;
        }

        return view('App\Modules\Accounting\invoices/print', $data);
    }

    /**
     * Genuine downloadable PDF, alongside the browser-print view above —
     * same Dompdf setup PayrollController::payslipPdf() and
     * ReportController::renderPdf() already use elsewhere in the app,
     * reused here rather than introducing a different PDF approach.
     */
    public function downloadPdf(int $id)
    {
        [$invoice, $data] = $this->printData($id);

        if ($invoice === null) {
            return redirect()->to('/accounting/invoices')->with('error', 'Invoice not found.');
        }

        if (($templated = $this->templatedDocument('invoice', $id, false)) !== null) {
            return $templated;
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('App\Modules\Accounting\invoices/pdf', $data));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $invoice['invoice_number'] . '.pdf"')
            ->setBody($dompdf->output());
    }

    /**
     * Shared lookup + company-scope check + view data for print() and
     * downloadPdf() — both render the same invoice content, just
     * through different templates (browser HTML vs Dompdf-safe HTML).
     * Returns [null, []] when the invoice doesn't exist or is out of
     * the viewer's company scope, same "not found" treatment as
     * everywhere else so scope leaks via URL-guessing don't confirm
     * the record exists.
     */
    private function printData(int $id): array
    {
        $invoice = $this->invoiceModel->withRelations($id);

        if ($invoice === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $invoice['company_id'])) {
            return [null, []];
        }

        $paid = $this->paymentModel->totalForInvoice($id);

        // First active bank account on file for the invoice's own
        // company — shown on the document so the customer knows where
        // to send payment. null (none on file) is handled by the view;
        // no fabricated account details.
        $bankAccount = null;
        foreach ($this->bankModel->forCompany($invoice['company_id']) as $account) {
            if ($account['status'] === 'active') {
                $bankAccount = $account;
                break;
            }
        }

        return [$invoice, [
            'title'       => 'Invoice ' . $invoice['invoice_number'],
            'invoice'     => $invoice,
            'company'     => $this->companyModel->find($invoice['company_id']),
            'items'       => $this->itemModel->forInvoice($id),
            'payments'    => $this->paymentModel->forInvoice($id),
            'paid'        => $paid,
            'balance'     => max(0, (float) $invoice['amount'] - $paid),
            'bankAccount' => $bankAccount,
        ]];
    }

    public function edit(int $id)
    {
        $invoice = $this->invoiceModel->find($id);

        if ($invoice === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $invoice['company_id'])) {
            return redirect()->to('/accounting/invoices')->with('error', 'Invoice not found.');
        }

        return view('App\Modules\Accounting\invoices/form', $this->formData($invoice));
    }

    public function update(int $id)
    {
        $invoice = $this->invoiceModel->find($id);

        if ($invoice === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $invoice['company_id'])) {
            return redirect()->to('/accounting/invoices')->with('error', 'Invoice not found.');
        }

        $rules = $this->invoiceModel->getValidationRules();
        $rules['invoice_number'] = "required|regex_match[/^[A-Za-z0-9-]+\$/]|max_length[40]|is_unique[invoices.invoice_number,id,{$id}]";

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only assign invoices to your own company.');
        }

        if ($stateError = $this->stateError()) {
            return redirect()->back()->withInput()->with('error', $stateError);
        }

        $items = $this->parseItems();

        if ($items === null) {
            return redirect()->back()->withInput()->with('error', 'Add at least one item with a description, quantity and rate.');
        }

        $gstType = $this->resolveGstType();

        $totals = $this->invoiceModel->calculateTotals(
            $items,
            (float) ($this->request->getPost('discount_percent') ?: 0),
            (float) ($this->request->getPost('gst_percent') ?: 0),
            $gstType
        );

        if ($totals['amount'] > InvoiceModel::MAX_AMOUNT) {
            return redirect()->back()->withInput()->with('error', 'The invoice total is too large.');
        }

        $this->invoiceModel->skipValidation(true);
        $this->invoiceModel->update($id, $this->payload(false, $totals, $gstType));
        $this->itemModel->replaceForInvoice($id, $totals['items']);

        // The total may have changed after payments were taken: keep the status consistent with what was actually paid.
        $posted = (string) ($this->request->getPost('status') ?: 'draft');
        $paid   = $this->paymentModel->totalForInvoice($id);
        if ($paid > 0 && $posted !== 'cancelled') {
            $this->invoiceModel->update($id, ['status' => $paid >= (float) $totals['amount'] ? 'paid' : 'partially_paid']);
        }
        $this->logActivity('accounting', 'update', $id, 'Updated invoice #' . $id);

        return redirect()->to('/accounting/invoices/' . $id)->with('success', 'Invoice updated.');
    }

    public function delete(int $id)
    {
        $invoice = $this->invoiceModel->find($id);

        if ($invoice === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $invoice['company_id'])) {
            return redirect()->to('/accounting/invoices')->with('error', 'Invoice not found.');
        }

        // Deleting would also delete (FK cascade) the payments recorded against it and so rewrite the cash ledger.
        if ($this->paymentModel->where('invoice_id', $id)->countAllResults() > 0) {
            return redirect()->to('/accounting/invoices/' . $id)->with('error', 'This invoice has payments recorded against it and cannot be deleted. Set its status to Cancelled instead.');
        }

        $this->invoiceModel->delete($id);
        $this->logActivity('accounting', 'delete', $id, 'Deleted invoice #' . $id);

        return redirect()->to('/accounting/invoices')->with('success', 'Invoice deleted.');
    }

    private function formData(?array $invoice): array
    {
        // company_id => state_id, so the form's JS can compare the
        // selling company's state against the customer's selected state
        // and auto-set GST Type — the actual authoritative gst_type is
        // still recomputed server-side in payload(), this is just so the
        // dropdown reflects the right value before submit.
        $companyStateById = array_column($this->companyModel->select('id, state_id')->findAll(), 'state_id', 'id');
        $companies        = $this->scopedCompanyOptions($this->companyModel->optionsList());

        // One preview invoice_number per company — same "swap the
        // suggestion via JS when the Company dropdown changes" pattern
        // as the Employee create form's nextCodeByCompany.
        $nextNumberByCompany = [];
        // Customers grouped by company_id, only for companies this user
        // can already see (scopedCompanyOptions() above) — the same
        // scoping the rest of the form's dropdowns rely on, since these
        // rows get embedded directly into page JS for the "pick an
        // existing customer" convenience below.
        $customersByCompany = [];
        foreach ($companies as $c) {
            $nextNumberByCompany[$c['id']] = $this->invoiceModel->nextInvoiceNumber((int) $c['id']);
            $customersByCompany[$c['id']]  = array_map(static fn (array $cust) => [
                'id'         => (int) $cust['id'],
                'name'       => $cust['name'],
                'email'      => $cust['email'],
                'phone'      => $cust['phone'],
                'gstin'      => $cust['gstin'],
                'address'    => $cust['address'],
                'country_id' => $cust['country_id'],
                'state_id'   => $cust['state_id'],
                'city'       => $cust['city'],
            ], $this->customerModel->optionsForCompany((int) $c['id']));
        }

        return [
            'title'            => $invoice ? 'Edit Invoice' : 'Create Invoice',
            'navActive'        => 'accounting',
            'invoice'          => $invoice,
            'items'            => $invoice ? $this->itemModel->forInvoice($invoice['id']) : [],
            'companies'        => $companies,
            'companyStateById' => $companyStateById,
            'countries'        => $this->countryModel->optionsList(),
            'statesByCountry'  => $this->stateModel->allGroupedByCountry(),
            'customersByCompany' => $customersByCompany,
            'statuses'         => InvoiceModel::STATUSES,
            'gstTypes'         => InvoiceModel::GST_TYPES,
            'gstRateSlabs'     => gst_rate_slabs(),
            'nextNumberByCompany' => $nextNumberByCompany,
            'nextNumber'       => $invoice['invoice_number'] ?? ($nextNumberByCompany[$companies[0]['id'] ?? 0] ?? $this->invoiceModel->nextInvoiceNumber()),
        ];
    }

    private function payload(bool $isNew, array $totals, string $gstType): array
    {
        $data = [
            'company_id'          => (int) $this->request->getPost('company_id'),
            'customer_id'         => $this->resolveCustomerId(),
            'invoice_number'      => $this->request->getPost('invoice_number'),
            'customer_name'       => $this->request->getPost('customer_name'),
            'customer_email'      => $this->request->getPost('customer_email') ?: null,
            'customer_phone'      => $this->request->getPost('customer_phone') ?: null,
            'customer_gstin'      => $this->request->getPost('customer_gstin') ?: null,
            'customer_address'    => $this->request->getPost('customer_address') ?: null,
            'customer_country_id' => $this->request->getPost('customer_country_id') ?: null,
            'customer_state_id'   => $this->request->getPost('customer_state_id') ?: null,
            'customer_city'       => $this->request->getPost('customer_city') ?: null,
            'issue_date'        => $this->request->getPost('issue_date'),
            'due_date'          => $this->request->getPost('due_date') ?: null,
            'status'            => $this->request->getPost('status') ?: 'draft',
            'notes'             => $this->request->getPost('notes'),
            'gst_type'          => $gstType,
            'gst_percent'       => $this->request->getPost('gst_percent') ?: 0,
            'discount_percent'  => $this->request->getPost('discount_percent') ?: 0,
            'subtotal'          => $totals['subtotal'],
            'discount_amount'   => $totals['discount_amount'],
            'cgst_amount'       => $totals['cgst_amount'],
            'sgst_amount'       => $totals['sgst_amount'],
            'igst_amount'       => $totals['igst_amount'],
            'amount'            => $totals['amount'],
        ];

        if ($isNew) {
            $data['created_by'] = $this->currentUserId();
        }

        return $data;
    }

    /**
     * Reads the item_description[]/item_quantity[]/item_rate[] arrays
     * the form posts (see invoices/form.php's add/remove-row JS), drops
     * any row left blank, and validates what's left. Returns null (not
     * an empty array) when nothing usable was submitted, so the caller
     * can tell "no items" apart from "zero-row edge case" with one check.
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

    /**
     * The authoritative GST Type for this submission — server-side, not
     * whatever the form posted, so it can't be spoofed or left stale by
     * JS not running. Derived from the invoice's company's state vs the
     * posted customer_state_id (determine_gst_type() — see gst_helper.php);
     * falls back to the posted value only when either state is unknown,
     * which keeps every invoice from before this feature (no state on
     * either side yet) working exactly as it did.
     */
    private function resolveGstType(): string
    {
        $company = $this->companyModel->find((int) $this->request->getPost('company_id'));
        $customerStateId = $this->request->getPost('customer_state_id') ?: null;

        return determine_gst_type($company['state_id'] ?? null, $customerStateId ? (int) $customerStateId : null)
            ?? (string) $this->request->getPost('gst_type');
    }

    /**
     * A customer's state must belong to the customer's country, the same rule
     * as CompanyController::stateError(). A blank state is allowed. Checked
     * before any GST is calculated, so an invalid pair is never saved.
     */
    private function stateError(): ?string
    {
        $stateId = $this->request->getPost('customer_state_id') ?: null;

        if ($stateId === null) {
            return null;
        }

        $state = $this->stateModel->find((int) $stateId);

        return $state !== null && (int) $state['country_id'] === (int) $this->request->getPost('customer_country_id')
            ? null
            : 'Choose a state from the selected customer country.';
    }

    /**
     * The posted customer_id (from the "pick an existing customer"
     * dropdown on the form), only if it's a real, non-deleted Customer
     * that actually belongs to the posted company — never trusted as-is,
     * same reasoning as isEligibleParent() in the To-Do module: a
     * mismatched/spoofed id is silently dropped to null (falls back to
     * the free-text customer_* snapshot fields) rather than erroring,
     * since customer_id is only ever a convenience pointer.
     */
    private function resolveCustomerId(): ?int
    {
        $customerId = $this->request->getPost('customer_id');
        if (! $customerId) {
            return null;
        }

        $customer = $this->customerModel->find((int) $customerId);

        return ($customer !== null && (int) $customer['company_id'] === (int) $this->request->getPost('company_id'))
            ? (int) $customerId
            : null;
    }
}
