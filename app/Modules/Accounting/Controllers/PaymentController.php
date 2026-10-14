<?php

namespace App\Modules\Accounting\Controllers;

use App\Controllers\BaseController;
use App\Modules\Accounting\Models\BillModel;
use App\Modules\Accounting\Models\InvoiceModel;
use App\Modules\Accounting\Models\PaymentModel;
use App\Modules\Purchase\Models\PurchaseOrderModel;

class PaymentController extends BaseController
{
    protected PaymentModel $paymentModel;
    protected InvoiceModel $invoiceModel;
    protected BillModel $billModel;

    public function __construct()
    {
        $this->paymentModel = new PaymentModel();
        $this->invoiceModel = new InvoiceModel();
        $this->billModel    = new BillModel();
    }

    public function storeForInvoice(int $invoiceId)
    {
        $invoice = $this->invoiceModel->find($invoiceId);

        if ($invoice === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $invoice['company_id'])) {
            return redirect()->to('/accounting/invoices')->with('error', 'Invoice not found.');
        }

        if (! $this->validate($this->paymentModel->getValidationRules())) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        if ($invoice['status'] === 'cancelled') {
            return redirect()->back()->with('error', 'Payments cannot be recorded against a cancelled invoice.');
        }

        $balance = round((float) $invoice['amount'] - $this->paymentModel->totalForInvoice($invoiceId), 2);
        if (round((float) $this->request->getPost('amount'), 2) > $balance) {
            return redirect()->back()->with('error', 'The payment exceeds the outstanding balance of ' . number_format(max(0, $balance), 2) . '.');
        }

        $this->paymentModel->insert([
            'company_id'   => $invoice['company_id'],
            'invoice_id'   => $invoiceId,
            'direction'    => 'in',
            'amount'       => $this->request->getPost('amount'),
            'payment_date' => $this->request->getPost('payment_date'),
            'method'       => $this->request->getPost('method'),
            'reference'    => $this->request->getPost('reference'),
            'notes'        => $this->request->getPost('notes'),
            'created_by'   => $this->currentUserId(),
        ]);

        $this->refreshInvoiceStatus($invoiceId, (float) $invoice['amount']);
        $this->logActivity('accounting', 'update', $invoiceId, 'Recorded payment against invoice #' . $invoiceId);

        return redirect()->to('/accounting/invoices/' . $invoiceId)->with('success', 'Payment recorded.');
    }

    public function storeForBill(int $billId)
    {
        $bill = $this->billModel->find($billId);

        if ($bill === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $bill['company_id'])) {
            return redirect()->to('/accounting/bills')->with('error', 'Bill not found.');
        }

        if (! $this->validate($this->paymentModel->getValidationRules())) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        if ($bill['status'] === 'cancelled') {
            return redirect()->back()->with('error', 'Payments cannot be recorded against a cancelled bill.');
        }

        $balance = round((float) $bill['amount'] - $this->paymentModel->totalForBill($billId), 2);
        if (round((float) $this->request->getPost('amount'), 2) > $balance) {
            return redirect()->back()->with('error', 'The payment exceeds the outstanding balance of ' . number_format(max(0, $balance), 2) . '.');
        }

        $this->paymentModel->insert([
            'company_id'   => $bill['company_id'],
            'bill_id'      => $billId,
            'direction'    => 'out',
            'amount'       => $this->request->getPost('amount'),
            'payment_date' => $this->request->getPost('payment_date'),
            'method'       => $this->request->getPost('method'),
            'reference'    => $this->request->getPost('reference'),
            'notes'        => $this->request->getPost('notes'),
            'created_by'   => $this->currentUserId(),
        ]);

        $this->refreshBillStatus($billId, (float) $bill['amount']);
        (new PurchaseOrderModel())->syncPaymentStatus(($bill['purchase_order_id'] ?? null) ? (int) $bill['purchase_order_id'] : null);
        $this->logActivity('accounting', 'update', $billId, 'Recorded payment against bill #' . $billId);

        return redirect()->to('/accounting/bills/' . $billId)->with('success', 'Payment recorded.');
    }

    public function delete(int $paymentId)
    {
        $payment = $this->paymentModel->find($paymentId);

        if ($payment === null) {
            return redirect()->back()->with('error', 'Payment not found.');
        }

        // Payments booked by Payroll or by an expense-claim reimbursement belong to that module, not to an invoice/bill.
        if (empty($payment['invoice_id']) && empty($payment['bill_id'])) {
            return redirect()->back()->with('error', 'This payment was booked by Payroll or an expense claim and cannot be deleted here.');
        }

        // Route-level filter can't express "invoice.edit or bill.edit
        // depending on which this payment is against" with one static
        // slug, so it's checked here once we know which type it is.
        if (! can($payment['invoice_id'] ? 'invoice.edit' : 'bill.edit')) {
            return redirect()->back()->with('error', 'You do not have permission to perform this action.');
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $payment['company_id'])) {
            return redirect()->back()->with('error', 'Payment not found.');
        }

        $this->paymentModel->delete($paymentId);

        if ($payment['invoice_id']) {
            $invoice = $this->invoiceModel->find($payment['invoice_id']);
            $this->refreshInvoiceStatus($payment['invoice_id'], (float) $invoice['amount']);

            return redirect()->to('/accounting/invoices/' . $payment['invoice_id'])->with('success', 'Payment removed.');
        }

        $bill = $this->billModel->find($payment['bill_id']);
        $this->refreshBillStatus($payment['bill_id'], (float) $bill['amount']);
        (new PurchaseOrderModel())->syncPaymentStatus(($bill['purchase_order_id'] ?? null) ? (int) $bill['purchase_order_id'] : null);

        return redirect()->to('/accounting/bills/' . $payment['bill_id'])->with('success', 'Payment removed.');
    }

    private function refreshInvoiceStatus(int $invoiceId, float $invoiceAmount): void
    {
        $invoice = $this->invoiceModel->find($invoiceId);

        if ($invoice['status'] === 'cancelled') {
            return;
        }

        $paid = $this->paymentModel->totalForInvoice($invoiceId);
        $status = match (true) {
            $paid >= $invoiceAmount && $paid > 0 => 'paid',
            $paid > 0                            => 'partially_paid',
            default                              => $invoice['status'] === 'draft' ? 'draft' : 'sent',
        };

        $this->invoiceModel->update($invoiceId, ['status' => $status]);
    }

    private function refreshBillStatus(int $billId, float $billAmount): void
    {
        $bill = $this->billModel->find($billId);

        if ($bill['status'] === 'cancelled') {
            return;
        }

        $paid = $this->paymentModel->totalForBill($billId);
        $status = match (true) {
            $paid >= $billAmount && $paid > 0 => 'paid',
            $paid > 0                         => 'partially_paid',
            default                           => 'unpaid',
        };

        $this->billModel->update($billId, ['status' => $status]);
    }
}
