<?php

namespace App\Modules\Purchase\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Purchase\Models\PurchaseOrderItemModel;
use App\Modules\Purchase\Models\PurchaseOrderModel;
use App\Modules\Purchase\Models\VendorModel;

class PurchaseOrderController extends BaseController
{
    protected PurchaseOrderModel $poModel;
    protected PurchaseOrderItemModel $itemModel;
    protected VendorModel $vendorModel;
    protected CompanyModel $companyModel;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->poModel             = new PurchaseOrderModel();
        $this->itemModel           = new PurchaseOrderItemModel();
        $this->vendorModel         = new VendorModel();
        $this->companyModel        = new CompanyModel();
        $this->notificationService = new NotificationService();
    }

    /**
     * "Purchase Orders" — every request regardless of outcome, the
     * approval-workflow view.
     */
    public function index()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'approval_status']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Purchase\orders\index', [
            'title'     => 'Purchase Orders',
            'navActive' => 'purchase-orders',
            'orders'    => $this->poModel->filtered($filters)->findAll(),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'  => PurchaseOrderModel::APPROVAL_STATUSES,
            'filters'   => $filters,
            'canApprove' => can('purchase_order.approve'),
        ]);
    }

    /**
     * "Purchases" — approved orders only, the fulfillment view.
     */
    public function purchases()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'fulfillment_status']) ?? []);
        $filters['approved_only'] = true;

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Purchase\orders\purchases', [
            'title'     => 'Purchases',
            'navActive' => 'purchases',
            'orders'    => $this->poModel->filtered($filters)->findAll(),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'fulfillmentStatuses' => PurchaseOrderModel::FULFILLMENT_STATUSES,
            'filters'   => $filters,
        ]);
    }

    public function create()
    {
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        $defaultCompanyId = $companyScope ?? $this->request->getGet('company_id');

        return view('App\Modules\Purchase\orders\form', $this->formData(null, $defaultCompanyId ? (int) $defaultCompanyId : null));
    }

    public function store()
    {
        $rules = $this->poModel->getValidationRules();
        unset($rules['po_number']); // auto-generated, not user-submitted.

        if (! $this->validate($rules, $this->poModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyId = (int) $this->request->getPost('company_id');

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $companyId)) {
            return redirect()->back()->withInput()->with('error', 'You can only request purchases for your own company.');
        }

        $vendor = $this->vendorModel->find((int) $this->request->getPost('vendor_id'));

        if ($vendor === null || (int) $vendor['company_id'] !== $companyId) {
            return redirect()->back()->withInput()->with('error', 'Select a valid vendor from the same company.');
        }

        $items = $this->parseItems();

        if ($items === null) {
            return redirect()->back()->withInput()->with('error', 'Add at least one item with a name, quantity and unit price.');
        }

        $totals = $this->poModel->calculateTotals($items);

        if ($totals['amount'] > PurchaseOrderModel::MAX_AMOUNT) {
            return redirect()->back()->withInput()->with('error', 'The purchase order total is too large.');
        }

        $id = $this->poModel->insert([
            'company_id'    => $companyId,
            'vendor_id'     => $vendor['id'],
            'po_number'     => $this->poModel->nextPoNumber(),
            'requested_by'  => $this->currentUserId(),
            'order_date'    => $this->request->getPost('order_date'),
            'expected_date' => $this->request->getPost('expected_date') ?: null,
            'items_count'   => $totals['items_count'],
            'amount'        => $totals['amount'],
            'notes'         => $this->request->getPost('notes') ?: null,
        ]);
        $this->itemModel->replaceForOrder($id, $totals['items']);

        $this->logActivity('purchase_order', 'create', $id, 'Requested purchase order for ' . $vendor['name']);

        return redirect()->to('/purchase-orders/' . $id)->with('success', 'Purchase order requested.');
    }

    public function show(int $id)
    {
        $order = $this->poModel->withRelations($id);

        if ($order === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $order['company_id'])) {
            return redirect()->to('/purchase-orders')->with('error', 'Purchase order not found.');
        }

        return view('App\Modules\Purchase\orders\show', [
            'title'      => $order['po_number'],
            'navActive'  => 'purchase-orders',
            'order'      => $order,
            'items'      => $this->itemModel->forOrder($id),
            'canApprove' => can('purchase_order.approve') && (int) $order['requested_by'] !== (int) $this->currentUserId(),
            'canEdit'    => can('purchase_order.edit'),
            'fulfillmentStatuses' => PurchaseOrderModel::FULFILLMENT_STATUSES,
            'paymentStatuses'     => PurchaseOrderModel::PAYMENT_STATUSES,
        ]);
    }

    public function edit(int $id)
    {
        // withRelations() (not find()) — the form's disabled "Company"
        // field reads $order['company_name'], which only a plain find()
        // wouldn't have joined in.
        $order = $this->poModel->withRelations($id);

        if ($order === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $order['company_id'])) {
            return redirect()->to('/purchase-orders')->with('error', 'Purchase order not found.');
        }
        if ($order['approval_status'] !== 'pending') {
            return redirect()->to('/purchase-orders/' . $id)->with('error', 'Only pending requests can be edited.');
        }

        return view('App\Modules\Purchase\orders\form', $this->formData($order, null, $this->itemModel->forOrder($id)));
    }

    public function update(int $id)
    {
        $order = $this->poModel->find($id);

        if ($order === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $order['company_id'])) {
            return redirect()->to('/purchase-orders')->with('error', 'Purchase order not found.');
        }
        if ($order['approval_status'] !== 'pending') {
            return redirect()->to('/purchase-orders/' . $id)->with('error', 'Only pending requests can be edited.');
        }

        $rules = $this->poModel->getValidationRules();
        unset($rules['company_id'], $rules['po_number']);

        if (! $this->validate($rules, $this->poModel->getValidationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $vendor = $this->vendorModel->find((int) $this->request->getPost('vendor_id'));

        if ($vendor === null || (int) $vendor['company_id'] !== (int) $order['company_id']) {
            return redirect()->back()->withInput()->with('error', 'Select a valid vendor from the same company.');
        }

        $items = $this->parseItems();

        if ($items === null) {
            return redirect()->back()->withInput()->with('error', 'Add at least one item with a name, quantity and unit price.');
        }

        $totals = $this->poModel->calculateTotals($items);

        if ($totals['amount'] > PurchaseOrderModel::MAX_AMOUNT) {
            return redirect()->back()->withInput()->with('error', 'The purchase order total is too large.');
        }

        $this->poModel->update($id, [
            'vendor_id'     => $vendor['id'],
            'order_date'    => $this->request->getPost('order_date'),
            'expected_date' => $this->request->getPost('expected_date') ?: null,
            'items_count'   => $totals['items_count'],
            'amount'        => $totals['amount'],
            'notes'         => $this->request->getPost('notes') ?: null,
        ]);
        $this->itemModel->replaceForOrder($id, $totals['items']);

        $this->logActivity('purchase_order', 'update', $id, 'Updated purchase order ' . $order['po_number']);

        return redirect()->to('/purchase-orders/' . $id)->with('success', 'Purchase order updated.');
    }

    public function delete(int $id)
    {
        $order = $this->poModel->find($id);

        if ($order !== null) {
            if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $order['company_id'])) {
                return redirect()->to('/purchase-orders')->with('error', 'Purchase order not found.');
            }

            $this->poModel->delete($id);
            $this->logActivity('purchase_order', 'delete', $id, 'Deleted purchase order ' . $order['po_number']);
        }

        return redirect()->to('/purchase-orders')->with('success', 'Purchase order removed.');
    }

    public function approve(int $id)
    {
        return $this->decide($id, 'approved');
    }

    public function reject(int $id)
    {
        return $this->decide($id, 'rejected');
    }

    /**
     * The actual approval workflow: only a pending request can be
     * decided, and — same segregation-of-duties rule Leave already
     * enforces — a requester can't approve/reject their own request
     * (Super Admin excepted, same as LeaveController).
     */
    private function decide(int $id, string $status)
    {
        $order = $this->poModel->withRelations($id);

        if ($order === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $order['company_id'])) {
            return redirect()->to('/purchase-orders')->with('error', 'Purchase order not found.');
        }
        if ($order['approval_status'] !== 'pending') {
            return redirect()->to('/purchase-orders/' . $id)->with('error', 'This request has already been actioned.');
        }
        if ((int) $order['requested_by'] === (int) $this->currentUserId() && session('roleSlug') !== 'super_admin') {
            return redirect()->to('/purchase-orders/' . $id)->with('error', 'You cannot approve or reject your own purchase order request.');
        }

        $data = [
            'approval_status' => $status,
            'approved_by'     => $this->currentUserId(),
            'approved_at'     => date('Y-m-d H:i:s'),
        ];
        if ($status === 'approved') {
            $data['fulfillment_status'] = 'ordered';
        }

        $this->poModel->update($id, $data);
        $this->logActivity('purchase_order', 'update', $id, ucfirst($status) . ' purchase order ' . $order['po_number']);

        if (! empty($order['requested_by'])) {
            $this->notificationService->notify(
                (int) $order['requested_by'],
                'purchase_order_' . $status,
                'Purchase order ' . $status . ': ' . $order['po_number'],
                'Your purchase order ' . $order['po_number'] . ' for ' . $order['vendor_name'] . ' was ' . $status . '.',
                'purchase_order',
                $id
            );
        }

        return redirect()->to('/purchase-orders/' . $id)->with('success', 'Purchase order ' . $status . '.');
    }

    /**
     * Fulfillment/payment tracking for an already-approved order — the
     * "Purchases" screen's own action, distinct from the approval
     * decision above (which a requester can't touch either way — see
     * canApprove/canEdit in show()).
     */
    public function updateStatus(int $id)
    {
        $order = $this->poModel->find($id);

        if ($order === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $order['company_id'])) {
            return redirect()->to('/purchase-orders')->with('error', 'Purchase order not found.');
        }
        if ($order['approval_status'] !== 'approved') {
            return redirect()->to('/purchase-orders/' . $id)->with('error', 'Only approved purchase orders can be updated here.');
        }

        $fulfillment = $this->request->getPost('fulfillment_status');
        $payment     = $this->request->getPost('payment_status');

        $data = [];
        if (in_array($fulfillment, PurchaseOrderModel::FULFILLMENT_STATUSES, true)) {
            $data['fulfillment_status'] = $fulfillment;
        }
        if (in_array($payment, PurchaseOrderModel::PAYMENT_STATUSES, true)) {
            $data['payment_status'] = $payment;
        }

        if ($data !== []) {
            $this->poModel->update($id, $data);
            $this->logActivity('purchase_order', 'update', $id, 'Updated fulfillment/payment status for ' . $order['po_number']);
        }

        return redirect()->to('/purchase-orders/' . $id)->with('success', 'Status updated.');
    }

    /**
     * Reads the item_name[]/item_quantity[]/item_unit_price[] arrays the
     * form posts (see orders/form.php's add/remove-row JS), drops any
     * row left blank, and validates what's left — same convention as
     * InvoiceController::parseItems(). Returns null (not an empty array)
     * when nothing usable was submitted.
     */
    private function parseItems(): ?array
    {
        $names      = $this->request->getPost('item_name') ?? [];
        $quantities = $this->request->getPost('item_quantity') ?? [];
        $unitPrices = $this->request->getPost('item_unit_price') ?? [];

        $items = [];

        foreach ($names as $i => $name) {
            $name       = trim((string) $name);
            $quantity   = $quantities[$i] ?? null;
            $unitPrice  = $unitPrices[$i] ?? null;

            if ($name === '' && $quantity === null && $unitPrice === null) {
                continue;
            }

            if ($name === '' || ! is_numeric($quantity) || ! is_numeric($unitPrice) || (float) $quantity <= 0 || (float) $unitPrice < 0) {
                return null;
            }

            $items[] = ['item_name' => $name, 'quantity' => (float) $quantity, 'unit_price' => (float) $unitPrice];
        }

        return $items === [] ? null : $items;
    }

    private function formData(?array $order, ?int $defaultCompanyId = null, array $items = []): array
    {
        $companyId = $order['company_id'] ?? $defaultCompanyId;

        // A scoped role always has a known company, so the vendor list
        // is pre-filtered to it. Super Admin (no default company until
        // they pick one in the form) sees every vendor with its company
        // labeled instead of a dead-empty dropdown — store()/update()
        // still re-validate the chosen vendor's company matches the
        // submitted company_id, so this is a display convenience only,
        // not a scoping relaxation.
        $vendors = $companyId
            ? $this->vendorModel->optionsForCompany((int) $companyId)
            : $this->vendorModel->filtered(['status' => 'active'])->findAll();

        return [
            'title'     => $order ? 'Edit Purchase Order' : 'New Purchase Order',
            'navActive' => 'purchase-orders',
            'order'     => $order,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'vendors'   => $vendors,
            'defaultCompanyId' => $defaultCompanyId,
            'items'     => $items,
        ];
    }
}
