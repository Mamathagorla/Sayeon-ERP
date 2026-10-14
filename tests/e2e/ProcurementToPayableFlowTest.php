<?php

namespace Tests\E2E;

use Tests\Support\E2ETestCase;

/**
 * WF-02  Procurement: purchase request (= pending purchase order) -> approval/rejection -> goods receipt
 * (fulfillment status) -> vendor bill -> payment -> payables.
 *
 * The codebase has ONE purchase document: a purchase order that starts as a request ("pending"). There is no
 * separate purchase-request entity, no goods-receipt document and no link from a PO to a bill or payment.
 */
final class ProcurementToPayableFlowTest extends E2ETestCase
{
    private array $w;
    private int $vendorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->w = $this->world();

        $A = $this->w['A'];
        $this->actAs($A['admin'])->submit('/vendors', [
            'company_id' => $A['company']['id'], 'name' => 'Acme Supplies', 'contact_person' => 'Ravi Kumar', 'email' => 'sales@acme.test', 'phone' => '9811122233', 'status' => 'active',
        ]);
        $this->vendorId = (int) $this->row('vendors', ['name' => 'Acme Supplies'])['id'];
    }

    private function raisePo(array $as, array $over = []): int
    {
        $A = $this->w['A'];
        $this->actAs($as)->submit('/purchase-orders', $over + [
            'company_id' => $A['company']['id'], 'vendor_id' => $this->vendorId, 'order_date' => date('Y-m-d'), 'expected_date' => date('Y-m-d', strtotime('+7 days')),
            'item_name' => ['Laptop', 'Mouse'], 'item_quantity' => ['2', '3'], 'item_unit_price' => ['1500.50', '200'], 'notes' => 'Team hardware',
        ]);
        $row = db_connect()->table('purchase_orders')->orderBy('id', 'DESC')->get()->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    public function testRequestApproveReceiveBillAndPay(): void
    {
        $A  = $this->w['A'];
        $id = $this->raisePo($A['employee']);

        // 1. The request: server-computed total, auto PO number, pending, no fulfillment yet.
        $po = $this->row('purchase_orders', ['id' => $id]);
        $this->assertNotNull($po, 'PO requested: ' . json_encode($this->flash('errors') ?? $this->flash('error')));
        $this->assertMatchesRegularExpression('/^PO-\d{4}-0001$/', $po['po_number']);
        $this->assertSame('pending', $po['approval_status']);
        $this->assertNull($po['fulfillment_status']);
        $this->assertSame('pending', $po['payment_status']);
        $this->assertEquals(2 * 1500.50 + 3 * 200, (float) $po['amount']);
        $this->assertSame(2, (int) $po['items_count']);
        $this->assertSame(2, $this->countRows('purchase_order_items', ['purchase_order_id' => $id]));
        $this->assertSame((string) $A['employee']['user_id'], (string) $po['requested_by']);

        // The PO cannot be advanced before approval.
        $this->actAs($A['admin'])->submit("/purchase-orders/$id/status", ['fulfillment_status' => 'received', 'payment_status' => 'paid']);
        $this->assertNull($this->row('purchase_orders', ['id' => $id])['fulfillment_status']);
        $this->assertFlash('error', 'Only approved purchase orders');

        // 2. Approval: the requester (no approve permission) cannot, nor can HR / Compliance / another company.
        foreach ([$A['employee'], $A['hr'], $A['compliance'], $this->w['B']['admin']] as $who) {
            $this->actAs($who)->submit("/purchase-orders/$id/approve");
            $this->assertSame('pending', $this->row('purchase_orders', ['id' => $id])['approval_status'], $who['role'] . ' must not approve');
        }
        $this->actAs($A['accountant'])->submit("/purchase-orders/$id/approve");
        $po = $this->row('purchase_orders', ['id' => $id]);
        $this->assertSame('approved', $po['approval_status']);
        $this->assertSame('ordered', $po['fulfillment_status']);
        $this->assertSame((string) $A['accountant']['user_id'], (string) $po['approved_by']);
        $this->assertNotNull($po['approved_at']);
        $this->assertCount(1, $this->notificationsFor($A['employee']['user_id'], 'purchase_order_approved'));

        // Deciding twice does not re-notify or flip the result.
        $this->actAs($A['admin'])->submit("/purchase-orders/$id/reject");
        $this->assertSame('approved', $this->row('purchase_orders', ['id' => $id])['approval_status']);
        $this->assertFlash('error', 'already been actioned');
        $this->assertCount(1, $this->notificationsFor($A['employee']['user_id']));

        // An approved order is no longer editable.
        $this->actAs($A['admin'])->submit("/purchase-orders/$id", ['vendor_id' => $this->vendorId, 'order_date' => date('Y-m-d'), 'item_name' => ['X'], 'item_quantity' => ['1'], 'item_unit_price' => ['1']]);
        $this->assertEquals(3601.00, (float) $this->row('purchase_orders', ['id' => $id])['amount']);

        // 3. Goods received (status only). Accountant approves but holds no purchase_order.edit, so only Company Admin can track fulfillment.
        $this->actAs($A['accountant'])->submit("/purchase-orders/$id/status", ['fulfillment_status' => 'in_transit']);
        $this->assertDenied('Accountant cannot update fulfillment');
        $this->assertSame('ordered', $this->row('purchase_orders', ['id' => $id])['fulfillment_status']);
        $this->actAs($A['admin'])->submit("/purchase-orders/$id/status", ['fulfillment_status' => 'in_transit']);
        $this->assertSame('in_transit', $this->row('purchase_orders', ['id' => $id])['fulfillment_status']);
        $this->actAs($A['admin'])->submit("/purchase-orders/$id/status", ['fulfillment_status' => 'received']);
        $this->assertSame('received', $this->row('purchase_orders', ['id' => $id])['fulfillment_status']);

        // 4. The vendor's invoice is booked as a Bill and paid - a separate module with NO link to the PO.
        $this->actAs($A['accountant'])->submit('/accounting/bills', [
            'company_id' => $A['company']['id'], 'bill_number' => 'ACME-INV-77', 'vendor_name' => 'Acme Supplies', 'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+15 days')), 'gst_type' => 'intra_state', 'gst_percent' => '18', 'discount_percent' => '0',
            'item_description' => ['Laptop x2, Mouse x3'], 'item_quantity' => ['1'], 'item_rate' => ['3601'], 'purchase_order_id' => $id,
        ]);
        $bill = $this->row('bills', ['bill_number' => 'ACME-INV-77']);
        $this->assertNotNull($bill, 'bill created: ' . json_encode($this->flash('errors') ?? $this->flash('error')));
        $this->assertSame((string) $id, (string) $bill['purchase_order_id'], 'the bill is linked to the purchase order it settles');
        $this->assertEquals(round(3601 * 1.18, 2), (float) $bill['amount'], 'GST added on top');
        $this->assertEquals(round(3601 * 0.09, 2), (float) $bill['cgst_amount']);
        $this->assertEquals(round(3601 * 0.09, 2), (float) $bill['sgst_amount']);
        $this->assertEquals(0, (float) $bill['igst_amount']);

        $this->actAs($A['accountant'])->submit("/accounting/bills/{$bill['id']}/payments", ['amount' => '1000', 'payment_date' => date('Y-m-d'), 'method' => 'bank_transfer', 'reference' => 'UTR1']);
        $this->assertSame('partially_paid', $this->row('bills', ['id' => $bill['id']])['status']);
        $this->actAs($A['accountant'])->submit("/accounting/bills/{$bill['id']}/payments", ['amount' => (string) round(3601 * 1.18 - 1000, 2), 'payment_date' => date('Y-m-d'), 'method' => 'bank_transfer']);
        $this->assertSame('paid', $this->row('bills', ['id' => $bill['id']])['status']);
        $this->assertSame(2, $this->countRows('payments', ['bill_id' => $bill['id'], 'direction' => 'out']));

        // Accounting now drives the order's payment status through the linked bill, and it can no longer be flagged by hand.
        $this->assertSame('paid', $this->row('purchase_orders', ['id' => $id])['payment_status']);
        $this->actAs($A['admin'])->submit("/purchase-orders/$id/status", ['payment_status' => 'pending']);
        $this->assertSame('paid', $this->row('purchase_orders', ['id' => $id])['payment_status']);

        // A linked order cannot be deleted, and a bill may only point at an approved order of its own company.
        $this->actAs($A['admin'])->submit("/purchase-orders/$id/delete");
        $this->assertNotNull($this->row('purchase_orders', ['id' => $id]));
        $pending = $this->raisePo($A['employee']);
        $this->actAs($A['accountant'])->submit('/accounting/bills', [
            'company_id' => $A['company']['id'], 'bill_number' => 'ACME-INV-78', 'vendor_name' => 'Acme Supplies', 'issue_date' => date('Y-m-d'), 'gst_type' => 'intra_state',
            'item_description' => ['x'], 'item_quantity' => ['1'], 'item_rate' => ['10'], 'purchase_order_id' => $pending,
        ]);
        $this->assertNull($this->row('bills', ['bill_number' => 'ACME-INV-78']), 'a pending (unapproved) order cannot be billed');
    }

    public function testRejectedRequestStaysClosed(): void
    {
        $A  = $this->w['A'];
        $id = $this->raisePo($A['employee']);

        $this->actAs($A['admin'])->submit("/purchase-orders/$id/reject");
        $po = $this->row('purchase_orders', ['id' => $id]);
        $this->assertSame('rejected', $po['approval_status']);
        $this->assertNull($po['fulfillment_status']);
        $this->assertCount(1, $this->notificationsFor($A['employee']['user_id'], 'purchase_order_rejected'));

        $this->actAs($A['accountant'])->submit("/purchase-orders/$id/approve");
        $this->assertSame('rejected', $this->row('purchase_orders', ['id' => $id])['approval_status'], 'a rejected request cannot be approved afterwards');
        $this->actAs($A['admin'])->submit("/purchase-orders/$id/status", ['fulfillment_status' => 'received', 'payment_status' => 'paid']);
        $this->assertNull($this->row('purchase_orders', ['id' => $id])['fulfillment_status']);
        $this->assertSame('pending', $this->row('purchase_orders', ['id' => $id])['payment_status']);
        $this->actAs($A['admin'])->visit("/purchase-orders/$id/edit");
        $this->assertFlash('error', 'Only pending requests can be edited');
    }

    public function testRequesterCannotApproveOwnRequestEvenWithApprovePermission(): void
    {
        $A  = $this->w['A'];
        $id = $this->raisePo($A['accountant']);    // accountant holds purchase_order.approve AND create

        $this->actAs($A['accountant'])->submit("/purchase-orders/$id/approve");
        $this->assertSame('pending', $this->row('purchase_orders', ['id' => $id])['approval_status']);
        $this->assertFlash('error', 'cannot approve or reject your own');

        $this->actAs($A['admin'])->submit("/purchase-orders/$id/approve");   // someone else can
        $this->assertSame('approved', $this->row('purchase_orders', ['id' => $id])['approval_status']);
    }

    public function testRequestValidationAndCompanyBoundaries(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];

        // No items / bad quantity / negative price / oversize total are refused and leave nothing behind.
        foreach ([
            ['item_name' => [''], 'item_quantity' => [''], 'item_unit_price' => ['']],
            ['item_name' => ['Chair'], 'item_quantity' => ['0'], 'item_unit_price' => ['10']],
            ['item_name' => ['Chair'], 'item_quantity' => ['1'], 'item_unit_price' => ['-1']],
            ['item_name' => ['Chair'], 'item_quantity' => ['abc'], 'item_unit_price' => ['10']],
            ['item_name' => ['Gold'], 'item_quantity' => ['1000000'], 'item_unit_price' => ['9999999']],
        ] as $bad) {
            $this->raisePo($A['employee'], $bad);
        }
        $this->assertSame(0, $this->countRows('purchase_orders'));
        $this->assertSame(0, $this->countRows('purchase_order_items'));

        // A vendor of another company, or a request filed for another company, is refused.
        $this->actAs($B['admin'])->submit('/vendors', ['company_id' => $B['company']['id'], 'name' => 'Beta Vendor', 'status' => 'active']);
        $bVendor = (int) $this->row('vendors', ['name' => 'Beta Vendor'])['id'];
        $this->raisePo($A['employee'], ['vendor_id' => $bVendor]);
        $this->raisePo($A['employee'], ['company_id' => $B['company']['id']]);
        $this->assertSame(0, $this->countRows('purchase_orders'));

        // Visibility: company B cannot open or act on company A's order.
        $id = $this->raisePo($A['employee']);
        $this->actAs($B['admin'])->visit("/purchase-orders/$id");
        $this->assertTrue($this->res->isRedirect());
        $this->assertFlash('error', 'not found');
        $this->actAs($B['accountant'])->submit("/purchase-orders/$id/approve");
        $this->assertSame('pending', $this->row('purchase_orders', ['id' => $id])['approval_status']);
        $this->actAs($B['admin'])->submit("/purchase-orders/$id/delete");
        $this->assertNotNull($this->row('purchase_orders', ['id' => $id]));

        // A request can be edited while pending, by Company Admin only (purchase_order.edit).
        $this->actAs($A['employee'])->submit("/purchase-orders/$id", ['vendor_id' => $this->vendorId, 'order_date' => date('Y-m-d'), 'item_name' => ['Cheaper'], 'item_quantity' => ['1'], 'item_unit_price' => ['5']]);
        $this->assertEquals(3601.00, (float) $this->row('purchase_orders', ['id' => $id])['amount'], 'employee cannot edit');
        $this->actAs($A['admin'])->submit("/purchase-orders/$id", ['vendor_id' => $this->vendorId, 'order_date' => date('Y-m-d'), 'item_name' => ['Cheaper'], 'item_quantity' => ['1'], 'item_unit_price' => ['5']]);
        $this->assertEquals(5.00, (float) $this->row('purchase_orders', ['id' => $id])['amount']);
        $this->assertSame(1, $this->countRows('purchase_order_items', ['purchase_order_id' => $id]), 'line items were replaced, not appended');
    }

    public function testPurchaseOrderNumbersAreUniqueAndSequential(): void
    {
        $A   = $this->w['A'];
        $ids = [$this->raisePo($A['employee']), $this->raisePo($A['employee']), $this->raisePo($A['manager'])];
        $nos = array_map(fn ($i) => $this->row('purchase_orders', ['id' => $i])['po_number'], $ids);

        $this->assertCount(3, array_unique($nos));
        $this->assertStringEndsWith('0003', $nos[2]);
    }

    public function testVendorsWithOrdersCannotBeDeleted(): void
    {
        $A = $this->w['A'];
        $this->raisePo($A['employee']);

        $this->actAs($A['admin'])->submit("/vendors/{$this->vendorId}/delete");
        $this->assertNotNull($this->row('vendors', ['id' => $this->vendorId]));
        $this->assertFlash('error', 'cannot be deleted');

        $this->actAs($this->w['B']['admin'])->submit("/vendors/{$this->vendorId}/delete");
        $this->assertNotNull($this->row('vendors', ['id' => $this->vendorId]), 'other company cannot delete it either');
    }

    public function testFulfillmentAndPaymentStatusRulesThatAreNotEnforced(): void
    {
        $A  = $this->w['A'];
        $id = $this->raisePo($A['employee']);
        $this->actAs($A['admin'])->submit("/purchase-orders/$id/approve");

        // D-13: payment can be flagged "paid" with no Bill/Payment, and "received" can be taken back to "ordered".
        $this->actAs($A['admin'])->submit("/purchase-orders/$id/status", ['fulfillment_status' => 'received', 'payment_status' => 'paid']);
        $this->actAs($A['admin'])->submit("/purchase-orders/$id/status", ['fulfillment_status' => 'ordered']);
        $po = $this->row('purchase_orders', ['id' => $id]);

        $this->assertRuleOrKnownDefect(
            ! ($po['payment_status'] === 'paid' && $this->countRows('payments') === 0) && $po['fulfillment_status'] !== 'ordered',
            'D-13',
            'PO fulfillment/payment status are free-form flags: "paid" can be set with no payment recorded anywhere and a received order can be reset to "ordered"; the Accounting module is never consulted.'
        );
    }

    public function testApprovedAndPaidOrdersCanBeDeleted(): void
    {
        $A  = $this->w['A'];
        $id = $this->raisePo($A['employee']);
        $this->actAs($A['admin'])->submit("/purchase-orders/$id/approve");
        $this->actAs($A['admin'])->submit("/purchase-orders/$id/status", ['fulfillment_status' => 'received', 'payment_status' => 'paid']);

        $this->actAs($A['admin'])->submit("/purchase-orders/$id/delete");
        $this->assertRuleOrKnownDefect(
            $this->row('purchase_orders', ['id' => $id]) !== null,
            'D-14',
            'A purchase order that is approved, received and paid can be hard-deleted by Company Admin (PurchaseOrderController::delete has no status guard and the table has no soft delete), erasing procurement history.'
        );
    }

    public function testRolesWithoutPurchasePermissions(): void
    {
        $A = $this->w['A'];
        $this->actAs($A['hr'])->submit('/purchase-orders', ['company_id' => $A['company']['id'], 'vendor_id' => $this->vendorId, 'order_date' => date('Y-m-d'), 'item_name' => ['X'], 'item_quantity' => ['1'], 'item_unit_price' => ['1']]);
        $this->assertSame(0, $this->countRows('purchase_orders'), 'HR has no purchase_order.create');
        $this->actAs($A['compliance'])->visit('/purchase-orders');
        $this->assertDenied();
    }
}
