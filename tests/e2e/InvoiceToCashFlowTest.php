<?php

namespace Tests\E2E;

use Tests\Support\E2ETestCase;

/**
 * WF-06  Invoice & Accounting: invoice (tax calculation) -> payments -> status -> overdue -> reports.
 * "Accounting entries" in this ERP are the invoice/bill/payment tables plus the cash-basis reports built on
 * them; there is no journal/ledger and no approval step for invoices.
 */
final class InvoiceToCashFlowTest extends E2ETestCase
{
    private array $w;
    private array $states = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->w = $this->world();
        $india   = db_connect()->table('countries')->where('name', 'India')->get()->getRowArray();
        $this->states = array_column(db_connect()->table('states')->where('country_id', $india['id'])->orderBy('id')->limit(2)->get()->getResultArray(), 'id');
        $this->assertCount(2, $this->states, 'reference data needs two Indian states');
    }

    private function invoiceForm(array $over = []): array
    {
        $A = $this->w['A'];

        return $over + [
            'company_id' => $A['company']['id'], 'invoice_number' => 'ALP-INV001', 'customer_name' => 'Globex Pvt Ltd', 'customer_email' => 'ap@globex.test',
            'customer_country_id' => $A['company']['country_id'], 'customer_state_id' => $A['company']['state_id'],
            'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+30 days')), 'gst_type' => 'inter_state', 'gst_percent' => '18', 'discount_percent' => '10',
            'status' => 'sent', 'notes' => 'Thanks',
            'item_description' => ['Consulting', 'Support'], 'item_quantity' => ['2', '1'], 'item_rate' => ['1000.50', '499.50'],
        ];
    }

    private function create(array $as, array $over = []): ?array
    {
        $this->actAs($as)->submit('/accounting/invoices', $this->invoiceForm($over));

        return $this->row('invoices', ['invoice_number' => $over['invoice_number'] ?? 'ALP-INV001']);
    }

    private function pay(array $as, int $invoiceId, string $amount, ?string $date = null): void
    {
        $this->actAs($as)->submit("/accounting/invoices/$invoiceId/payments", ['amount' => $amount, 'payment_date' => $date ?? date('Y-m-d'), 'method' => 'bank_transfer', 'reference' => 'REF' . rand(100, 999)]);
    }

    private function inv(int $id): array
    {
        return $this->row('invoices', ['id' => $id]);
    }

    public function testIntraStateInvoiceTaxCalculation(): void
    {
        $A   = $this->w['A'];
        $inv = $this->create($A['accountant']);
        $this->assertNotNull($inv, 'invoice created: ' . json_encode($this->flash('errors') ?? $this->flash('error')));

        // 2 x 1000.50 + 1 x 499.50 = 2500.50 ; 10% discount = 250.05 ; taxable 2250.45 ; 18% GST.
        $taxable = round(2500.50 - 250.05, 2);
        $half    = round($taxable * 18 / 200, 2);
        $this->assertEquals(2500.50, (float) $inv['subtotal']);
        $this->assertEquals(250.05, (float) $inv['discount_amount']);
        $this->assertSame('intra_state', $inv['gst_type'], 'same state as the selling company => CGST + SGST, regardless of what the form posted');
        $this->assertEquals($half, (float) $inv['cgst_amount']);
        $this->assertEquals($half, (float) $inv['sgst_amount']);
        $this->assertEquals(0, (float) $inv['igst_amount']);
        $this->assertEquals(round($taxable + 2 * $half, 2), (float) $inv['amount']);
        $this->assertSame('sent', $inv['status']);
        $this->assertSame((string) $A['accountant']['user_id'], (string) $inv['created_by']);
        $this->assertSame(2, $this->countRows('invoice_items', ['invoice_id' => $inv['id']]));
        $this->assertNotNull($this->row('activity_logs', ['module' => 'accounting', 'action' => 'create', 'record_id' => $inv['id']]));
    }

    public function testInterStateInvoiceUsesIgstAndRoundingIsStable(): void
    {
        $A = $this->w['A'];
        $other = $this->states[0] === $A['company']['state_id'] ? $this->states[1] : $this->states[0];
        $inv = $this->create($A['accountant'], ['customer_state_id' => $other, 'gst_type' => 'intra_state', 'discount_percent' => '0', 'gst_percent' => '12',
            'item_description' => ['Widget'], 'item_quantity' => ['3'], 'item_rate' => ['33.33']]);

        $this->assertSame('inter_state', $inv['gst_type'], 'a posted gst_type cannot override the state rule');
        $this->assertEquals(99.99, (float) $inv['subtotal']);
        $this->assertEquals(round(99.99 * 0.12, 2), (float) $inv['igst_amount']);
        $this->assertEquals(0, (float) $inv['cgst_amount']);
        $this->assertEquals(round(99.99 + round(99.99 * 0.12, 2), 2), (float) $inv['amount']);
    }

    public function testInvoiceNumbersAreUniqueAndDuplicateSubmissionIsIgnored(): void
    {
        $A = $this->w['A'];
        $this->create($A['accountant']);
        $this->create($A['accountant']);   // double click / retry with the same number
        $this->assertSame(1, $this->countRows('invoices'));
        $this->assertSame(2, $this->countRows('invoice_items'), 'items are not duplicated either');

        $this->create($A['accountant'], ['invoice_number' => 'ALP-INV002']);
        $this->assertSame(2, $this->countRows('invoices'));

        // Another company cannot reuse the number (table-wide unique).
        $B = $this->w['B'];
        $this->actAs($B['accountant'])->submit('/accounting/invoices', $this->invoiceForm(['company_id' => $B['company']['id'], 'customer_country_id' => $B['company']['country_id'], 'customer_state_id' => $B['company']['state_id']]));
        $this->assertSame(2, $this->countRows('invoices'));
    }

    public function testInvoiceValidation(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];

        foreach ([
            ['customer_name' => ''],
            ['invoice_number' => 'bad number!'],
            ['gst_percent' => '150'],
            ['discount_percent' => '-5'],
            ['item_description' => [''], 'item_quantity' => [''], 'item_rate' => ['']],
            ['item_quantity' => ['0', '1']],
            ['item_rate' => ['-10', '1']],
            ['customer_gstin' => '123'],
            ['company_id' => $B['company']['id']],                       // another company's books
            ['customer_state_id' => 999999],                             // state not in the country
            ['item_description' => ['Gold'], 'item_quantity' => ['1000000'], 'item_rate' => ['9999999']],   // total too large
        ] as $i => $bad) {
            $this->create($A['accountant'], $bad + ['invoice_number' => "ALP-BAD$i"]);
            $this->assertSame(0, $this->countRows('invoices'), 'invalid input #' . $i . ' was stored: ' . json_encode(array_keys($bad)));
        }
        $this->assertSame(0, $this->countRows('invoices'), 'no invalid invoice is stored');
        $this->assertSame(0, $this->countRows('invoice_items'));
    }

    public function testPartialAndFullPaymentsDriveTheInvoiceStatus(): void
    {
        $A   = $this->w['A'];
        $inv = $this->create($A['accountant']);
        $due = (float) $inv['amount'];

        $this->pay($A['accountant'], $inv['id'], '1000');
        $this->assertSame('partially_paid', $this->inv($inv['id'])['status']);
        $p = $this->row('payments', ['invoice_id' => $inv['id']]);
        $this->assertSame('in', $p['direction']);
        $this->assertSame((string) $A['company']['id'], (string) $p['company_id'], 'payment inherits the invoice company');
        $this->assertSame((string) $A['accountant']['user_id'], (string) $p['created_by']);

        $this->pay($A['accountant'], $inv['id'], (string) round($due - 1000, 2));
        $this->assertSame('paid', $this->inv($inv['id'])['status']);
        $this->assertSame(2, $this->countRows('payments', ['invoice_id' => $inv['id']]));

        // Removing the last payment re-opens the balance.
        $last = db_connect()->table('payments')->where('invoice_id', $inv['id'])->orderBy('id', 'DESC')->get()->getRowArray();
        $this->actAs($A['accountant'])->submit("/accounting/payments/{$last['id']}/delete");
        $this->assertSame('partially_paid', $this->inv($inv['id'])['status']);
        $this->actAs($A['accountant'])->submit('/accounting/payments/' . $this->row('payments', ['invoice_id' => $inv['id']])['id'] . '/delete');
        $this->assertSame('sent', $this->inv($inv['id'])['status'], 'no payments left => back to sent');
    }

    public function testPaymentValidationAndPermissions(): void
    {
        $A   = $this->w['A'];
        $B   = $this->w['B'];
        $inv = $this->create($A['accountant']);

        foreach (['0', '-5', 'abc', '', '99999999999.99'] as $bad) {
            $this->pay($A['accountant'], $inv['id'], $bad);
        }
        $this->actAs($A['accountant'])->submit("/accounting/invoices/{$inv['id']}/payments", ['amount' => '10']);   // no date
        $this->assertSame(0, $this->countRows('payments'));

        $this->pay($B['accountant'], $inv['id'], '10');
        $this->assertSame(0, $this->countRows('payments'), "another company's accountant cannot post a payment");

        foreach (['employee', 'manager', 'hr', 'compliance'] as $role) {
            $this->pay($A[$role], $inv['id'], '10');
            $this->assertSame(0, $this->countRows('payments'), "$role cannot record payments");
        }

        $this->pay($A['accountant'], $inv['id'], '10');
        $pid = $this->row('payments', ['invoice_id' => $inv['id']])['id'];
        $this->actAs($B['accountant'])->submit("/accounting/payments/$pid/delete");
        $this->actAs($A['hr'])->submit("/accounting/payments/$pid/delete");
        $this->assertSame(1, $this->countRows('payments'), 'foreign/unauthorised payment deletion refused');
    }

    public function testOverdueInvoicesAreFlaggedAndStillPayable(): void
    {
        $A   = $this->w['A'];
        $inv = $this->create($A['accountant'], ['due_date' => date('Y-m-d', strtotime('-3 days'))]);
        $this->assertSame('sent', $inv['status']);

        $this->actAs($A['accountant'])->visit('/accounting');    // lazy refresh on the dashboard
        $this->assertSame('overdue', $this->inv($inv['id'])['status']);

        $paid = $this->create($A['accountant'], ['invoice_number' => 'ALP-INV050', 'due_date' => date('Y-m-d', strtotime('-3 days')), 'status' => 'paid']);
        $this->actAs($A['accountant'])->visit('/accounting');
        $this->assertSame('paid', $this->inv($paid['id'])['status'], 'settled invoices never flip to overdue');

        $this->pay($A['accountant'], $inv['id'], (string) $inv['amount']);
        $this->assertSame('paid', $this->inv($inv['id'])['status']);
    }

    public function testReportsTieBackToInvoicesAndPayments(): void
    {
        $A   = $this->w['A'];
        $B   = $this->w['B'];
        $inv = $this->create($A['accountant'], ['discount_percent' => '0', 'gst_percent' => '0', 'item_description' => ['Fee'], 'item_quantity' => ['1'], 'item_rate' => ['1000']]);
        $this->pay($A['accountant'], $inv['id'], '400');
        $this->actAs($B['accountant'])->submit('/accounting/invoices', $this->invoiceForm([
            'company_id' => $B['company']['id'], 'invoice_number' => 'BET-INV001', 'customer_country_id' => $B['company']['country_id'], 'customer_state_id' => $B['company']['state_id'],
            'discount_percent' => '0', 'gst_percent' => '0', 'item_description' => ['Fee'], 'item_quantity' => ['1'], 'item_rate' => ['5000'],
        ]));

        $this->actAs($A['accountant'])->visit('/reports/financials/export/csv?from=' . date('Y-m-01') . '&to=' . date('Y-m-d'));
        $csv = array_map('str_getcsv', explode("\n", trim($this->body())));
        $map = array_column($csv, 1, 0);
        $this->assertEquals(400, (float) $map['Revenue Collected (cash in)'], 'cash in = payments of the viewer\'s company only');

        $this->assertRuleOrKnownDefect(
            (float) $map['Outstanding Receivables'] === 600.0,
            'D-20',
            'Receivables figures (Accounting dashboard, Receivables and Financial Summary reports) add up the full invoice amount of every open invoice, so a part-paid 1,000 invoice with 400 received is reported as 1,000 outstanding instead of 600.'
        );
        $this->assertNotSame(5000.0, (float) $map['Outstanding Receivables'], "company B's invoice is not in company A's report");
    }

    public function testInvoicesAreCompanyIsolated(): void
    {
        $A   = $this->w['A'];
        $B   = $this->w['B'];
        $inv = $this->create($A['accountant']);

        foreach (["/accounting/invoices/{$inv['id']}", "/accounting/invoices/{$inv['id']}/edit", "/accounting/invoices/{$inv['id']}/print", "/accounting/invoices/{$inv['id']}/pdf"] as $url) {
            $this->actAs($B['accountant'])->visit($url);
            $this->assertTrue($this->res->isRedirect(), "$url must not be reachable from company B");
            $this->assertFlash('error', 'not found');
        }
        $this->actAs($B['admin'])->submit("/accounting/invoices/{$inv['id']}/delete");
        $this->assertNotNull($this->row('invoices', ['id' => $inv['id']]));
        $this->actAs($B['accountant'])->submit("/accounting/invoices/{$inv['id']}", $this->invoiceForm(['company_id' => $B['company']['id'], 'customer_name' => 'Hijacked']));
        $this->assertSame('Globex Pvt Ltd', $this->inv($inv['id'])['customer_name']);

        $this->actAs($A['accountant'])->visit('/accounting/invoices');
        $this->assertStringContainsString('ALP-INV001', $this->body());
        $this->actAs($B['accountant'])->visit('/accounting/invoices');
        $this->assertStringNotContainsString('ALP-INV001', $this->body());
    }

    public function testInvoiceDocumentUsesBuiltInLayoutWithoutTemplateAndTheCompanyTemplateWhenPresent(): void
    {
        $A   = $this->w['A'];
        $inv = $this->create($A['accountant']);

        // Accounting documents fall back to the built-in layout (HR letters do not).
        $this->actAs($A['accountant'])->visit("/accounting/invoices/{$inv['id']}/pdf");
        $this->assertTrue($this->res->isOK());
        $this->assertStringStartsWith('%PDF', $this->body());
        $this->assertSame(0, $this->countRows('generated_documents'), 'built-in output is not archived');

        $this->template($A['company'], 'invoice', '<h1>Invoice {{invoice_number}}</h1><p>{{customer_name}} owes {{total}}</p>');
        $this->downloadArchived("/accounting/invoices/{$inv['id']}/pdf", 'invoice', (int) $inv['id']);
        $this->assertSame(1, $this->countRows('generated_documents', ['document_type' => 'invoice', 'company_id' => $A['company']['id']]));
    }

    public function testInvoiceLifecycleRulesThatAreNotEnforced(): void
    {
        $A   = $this->w['A'];
        $inv = $this->create($A['accountant'], ['discount_percent' => '0', 'gst_percent' => '0', 'item_description' => ['Fee'], 'item_quantity' => ['1'], 'item_rate' => ['1000']]);

        // D-15: a payment larger than the open balance is accepted (over-payment is booked as cash in).
        $this->pay($A['accountant'], $inv['id'], '1000');
        $this->pay($A['accountant'], $inv['id'], '250');
        $paid = (float) db_connect()->table('payments')->selectSum('amount')->where('invoice_id', $inv['id'])->get()->getRow('amount');
        $this->assertRuleOrKnownDefect($paid <= 1000.0, 'D-15', 'Payments are not capped at the invoice balance: 1,250 can be recorded against a 1,000 invoice (cash-in inflated, no credit-note/advance handling).');
    }

    public function testEditingAnInvoiceAfterPaymentKeepsStatusConsistent(): void
    {
        $A   = $this->w['A'];
        $inv = $this->create($A['accountant'], ['discount_percent' => '0', 'gst_percent' => '0', 'item_description' => ['Fee'], 'item_quantity' => ['1'], 'item_rate' => ['1000']]);
        $this->pay($A['accountant'], $inv['id'], '1000');
        $this->assertSame('paid', $this->inv($inv['id'])['status']);

        // The amount is raised afterwards (status field posted back unchanged as "paid").
        $this->actAs($A['accountant'])->submit("/accounting/invoices/{$inv['id']}", $this->invoiceForm([
            'discount_percent' => '0', 'gst_percent' => '0', 'status' => 'paid', 'item_description' => ['Fee'], 'item_quantity' => ['1'], 'item_rate' => ['1500'],
        ]));
        $row = $this->inv($inv['id']);
        $this->assertEquals(1500, (float) $row['amount']);
        $this->assertRuleOrKnownDefect(
            $row['status'] !== 'paid',
            'D-19',
            'Editing an invoice after payments never re-evaluates its status: a fully paid 1,000 invoice raised to 1,500 stays "paid" with 500 unpaid (and status can also be set to "paid" by hand with no payment at all).'
        );
    }

    public function testCancelledInvoiceStillAcceptsPayments(): void
    {
        $A   = $this->w['A'];
        $inv = $this->create($A['accountant'], ['status' => 'cancelled']);
        $this->pay($A['accountant'], $inv['id'], '100');

        $this->assertRuleOrKnownDefect(
            $this->countRows('payments', ['invoice_id' => $inv['id']]) === 0,
            'D-16',
            'Payments can be recorded against a CANCELLED invoice; the status stays cancelled but the money is counted in cash-in reports.'
        );
    }

    public function testAnInvoiceWithPaymentsCannotBeDeletedSoTheLedgerStaysIntact(): void
    {
        $A   = $this->w['A'];
        $inv = $this->create($A['accountant']);
        $this->pay($A['accountant'], $inv['id'], '500');
        $this->actAs($A['accountant'])->submit("/accounting/invoices/{$inv['id']}/delete");

        // D-17 (fixed): deleting used to cascade-delete the payments, erasing received cash from every report.
        $this->assertNotNull($this->row('invoices', ['id' => $inv['id']]));
        $this->assertSame(1, $this->countRows('payments', ['invoice_id' => $inv['id']]));
        $this->assertFlash('error', 'Cancelled');

        // Cancelling is the supported way out; an invoice without payments can still be deleted.
        $other = $this->create($A['accountant'], ['invoice_number' => 'ALP-INV777']);
        $this->actAs($A['accountant'])->submit("/accounting/invoices/{$other['id']}/delete");
        $this->assertNull($this->row('invoices', ['id' => $other['id']]));
    }

    public function testRolePermissionsForInvoices(): void
    {
        $A = $this->w['A'];
        foreach (['employee', 'manager', 'hr', 'compliance'] as $role) {
            $this->create($A[$role], ['invoice_number' => "ALP-X-$role"]);
            $this->assertNull($this->row('invoices', ['invoice_number' => "ALP-X-$role"]), "$role cannot create invoices");
        }
        $this->anonymous()->visit('/accounting/invoices');
        $this->assertTrue($this->res->isRedirect());
        $this->assertNotNull($this->create($A['admin'], ['invoice_number' => 'ALP-ADM-1']), 'Company Admin can');
        $this->assertNotNull($this->create($this->w['super'], ['invoice_number' => 'ALP-SUP-1']), 'Super Admin can');
    }
}
