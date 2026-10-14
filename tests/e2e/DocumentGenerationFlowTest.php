<?php

namespace Tests\E2E;

use Tests\Support\E2ETestCase;

/**
 * WF-09  Document generation: company template -> activation (preview gate, one active version) -> data mapping from
 * the ERP record -> generation -> download -> archive/history (immutable once issued) -> company isolation.
 *
 * Templates are inserted as html files (the same shape the upload screen stores); the upload/auto-detect/mapping UI
 * itself is exercised by the application's own `php spark documents:selftest`, not here.
 */
final class DocumentGenerationFlowTest extends E2ETestCase
{
    private array $w;

    protected function setUp(): void
    {
        parent::setUp();
        $this->w = $this->world();
    }

    /** An offer-sent onboarding record with every field the offer letter needs. */
    private function offerRecord(array $over = []): int
    {
        $A  = $this->w['A'];
        $db = db_connect();
        $db->table('onboarding_records')->insert($over + [
            'company_id' => $A['company']['id'], 'candidate_name' => 'Asha Verma', 'candidate_email' => 'asha@c.test', 'candidate_phone' => '9876543210',
            'department_id' => $this->w['dept'], 'designation' => 'Software Engineer', 'reporting_manager_id' => $A['manager']['user_id'],
            'offered_ctc' => 1200000, 'joining_date' => date('Y-m-d', strtotime('+30 days')), 'status' => 'offer_sent', 'offer_sent_at' => date('Y-m-d H:i:s'),
            'created_by' => $A['hr']['user_id'], 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    private const OFFER_HTML = '<html><body><h1>Offer</h1><p>Dear {{employee_name}}, we offer you the role of {{designation}} from {{joining_date}} at {{annual_salary}} per annum.</p></body></html>';

    public function testOfferLetterIsGeneratedFromTheCompanyTemplateAndArchived(): void
    {
        $A  = $this->w['A'];
        $id = $this->offerRecord();
        $t1 = $this->template($A['company'], 'offer_letter', self::OFFER_HTML);

        $this->actAs($A['hr']);
        $this->downloadArchived("/hr/onboarding/$id/offer-letter/pdf", 'offer_letter', $id);
        $this->assertTrue($this->res->isOK(), 'HTTP ' . $this->res->response()->getStatusCode() . ' ' . json_encode($this->flash('error')));
        $this->assertStringStartsWith('%PDF', $this->body());
        $this->assertStringContainsString('attachment', $this->res->response()->getHeaderLine('Content-Disposition'));

        $docs = $this->rows('generated_documents', ['document_type' => 'offer_letter', 'record_id' => $id]);
        $this->assertCount(1, $docs);
        $this->assertSame((string) $t1, (string) $docs[0]['template_id']);
        $this->assertSame((string) $A['hr']['user_id'], (string) $docs[0]['generated_by']);
        $this->assertSame((string) $A['company']['id'], (string) $docs[0]['company_id']);
        $snap = json_decode($docs[0]['data_snapshot'], true);
        $this->assertSame('Software Engineer', $snap['designation']);
        $this->assertSame('Asha Verma', $snap['employee_name']);
        $this->assertFileExists(WRITEPATH . 'uploads/' . $docs[0]['file_path']);

        // Downloading again with unchanged data re-serves the archived file: no second document.
        $this->visit("/hr/onboarding/$id/offer-letter/pdf");
        $this->assertCount(1, $this->rows('generated_documents', ['document_type' => 'offer_letter']));
        $this->assertSame(sha1($this->body()), sha1((string) file_get_contents(WRITEPATH . 'uploads/' . $docs[0]['file_path'])), 'byte-identical to the issued file');
    }

    public function testChangedDataOrANewTemplateVersionNeverRewritesAnIssuedDocument(): void
    {
        $A  = $this->w['A'];
        $id = $this->offerRecord();
        $t1 = $this->template($A['company'], 'offer_letter', self::OFFER_HTML);
        $this->actAs($A['hr']);
        $this->downloadArchived("/hr/onboarding/$id/offer-letter/pdf", 'offer_letter', $id);
        $first = $this->row('generated_documents', ['document_type' => 'offer_letter']);

        // The salary is revised -> a NEW document is archived; the first stays exactly as issued.
        db_connect()->table('onboarding_records')->where('id', $id)->update(['offered_ctc' => 1500000]);
        $this->downloadArchived("/hr/onboarding/$id/offer-letter/pdf", 'offer_letter', $id, 2);
        $rows = db_connect()->table('generated_documents')->where('document_type', 'offer_letter')->orderBy('id')->get()->getResultArray();
        $this->assertCount(2, $rows);
        $this->assertSame($first['file_path'], $rows[0]['file_path']);
        $this->assertSame($first['data_hash'], $rows[0]['data_hash']);
        $this->assertNotSame($rows[0]['data_hash'], $rows[1]['data_hash']);
        $this->assertFileExists(WRITEPATH . 'uploads/' . $rows[0]['file_path']);

        // A newer template version becomes active: history keeps pointing at the version that produced each file.
        $t2 = $this->template($A['company'], 'offer_letter', '<p>V2 {{employee_name}} {{designation}} {{joining_date}} {{annual_salary}}</p>', 'draft', 2);
        $this->actAs($A['admin'])->submit("/documents/templates/$t2/activate");
        $this->assertFlash('error', 'Preview this draft');
        $this->actAs($A['admin'])->visit("/documents/templates/$t2/test");
        $this->assertTrue($this->res->isOK(), json_encode($this->flash('error')));
        $this->actAs($A['admin'])->submit("/documents/templates/$t2/activate");
        $this->assertSame('active', $this->row('document_templates', ['id' => $t2])['status']);
        $this->assertSame('inactive', $this->row('document_templates', ['id' => $t1])['status'], 'only one active version per company and type');
        $this->assertSame(1, $this->countRows('document_templates', ['company_id' => $A['company']['id'], 'document_type' => 'offer_letter', 'status' => 'active']));

        $this->actAs($A['hr']);
        $this->downloadArchived("/hr/onboarding/$id/offer-letter/pdf", 'offer_letter', $id, 3);
        $rows = db_connect()->table('generated_documents')->where('document_type', 'offer_letter')->orderBy('id')->get()->getResultArray();
        $this->assertCount(3, $rows, 'a new template version builds a fresh document for the same data');
        $this->assertSame([(string) $t1, (string) $t1, (string) $t2], array_map('strval', array_column($rows, 'template_id')));

        // Templates that produced documents can never be deleted; an active one must be deactivated first.
        $this->actAs($A['admin'])->submit("/documents/templates/$t2/delete");
        $this->assertFlash('error', 'Deactivate');
        $this->actAs($A['admin'])->submit("/documents/templates/$t2/deactivate");
        $this->actAs($A['admin'])->submit("/documents/templates/$t2/delete");
        $this->assertNotNull($this->row('document_templates', ['id' => $t2]));
        $this->assertFlash('error', 'generated documents');
    }

    public function testIncompleteRecordsAndBadTemplatesProduceNoDocument(): void
    {
        $A = $this->w['A'];
        $this->template($A['company'], 'offer_letter', self::OFFER_HTML);

        $noSalary = $this->offerRecord(['offered_ctc' => null, 'candidate_email' => 'nosal@c.test']);
        $this->actAs($A['hr'])->visit("/hr/onboarding/$noSalary/offer-letter/pdf");
        $this->assertTrue($this->res->isRedirect());
        $this->assertFlash('error', 'Annual Salary is missing');
        $this->assertSame(0, $this->countRows('generated_documents'));

        // A template that uses a field this document type does not provide is refused, never printed blank.
        db_connect()->table('document_templates')->where('company_id', $A['company']['id'])->update(['status' => 'inactive']);
        $this->template($A['company'], 'offer_letter', '<p>{{employee_name}} {{favourite_colour}} {{designation}} {{joining_date}} {{annual_salary}}</p>', 'active', 2);
        $ok = $this->offerRecord(['candidate_email' => 'ok@c.test']);
        $this->actAs($A['hr'])->visit("/hr/onboarding/$ok/offer-letter/pdf");
        $this->assertTrue($this->res->isRedirect());
        $this->assertFlash('error', 'favourite_colour');
        $this->assertSame(0, $this->countRows('generated_documents'));

        // A template with no place for the record's identity would print the same letter for everyone.
        db_connect()->table('document_templates')->where('company_id', $A['company']['id'])->update(['status' => 'inactive']);
        $this->template($A['company'], 'offer_letter', '<p>Generic text only</p>', 'active', 3);
        $this->actAs($A['hr'])->visit("/hr/onboarding/$ok/offer-letter/pdf");
        $this->assertTrue($this->res->isRedirect());
        $this->assertSame(0, $this->countRows('generated_documents'));
    }

    public function testTemplatesAndDocumentsAreCompanyIsolated(): void
    {
        $A  = $this->w['A'];
        $B  = $this->w['B'];
        $id = $this->offerRecord();
        $tB = $this->template($B['company'], 'offer_letter', self::OFFER_HTML);

        // Company B's template is never used for company A's record.
        $this->actAs($A['hr'])->visit("/hr/onboarding/$id/offer-letter/pdf");
        $this->assertTrue($this->res->isRedirect());
        $this->assertFlash('error', 'No active Offer Letter template');

        // Company A's admin cannot see or change company B's template.
        foreach (['view' => "/documents/templates/$tB", 'test' => "/documents/templates/$tB/test"] as $url) {
            $this->actAs($A['admin'])->visit($url);
            $this->assertTrue($this->res->isRedirect());
        }
        $this->actAs($A['admin'])->submit("/documents/templates/$tB/deactivate");
        $this->actAs($A['admin'])->submit("/documents/templates/$tB/delete");
        $this->assertSame('active', $this->row('document_templates', ['id' => $tB])['status']);
        $this->assertNotNull($this->row('document_templates', ['id' => $tB]));

        // Company B's HR cannot generate company A's letter even with its own active template.
        $this->actAs($B['hr'])->visit("/hr/onboarding/$id/offer-letter/pdf");
        $this->assertFlash('error', 'not found');
        $this->actAs($B['hr'])->visit("/documents/render/offer_letter/$id");
        $this->assertTrue($this->res->isRedirect());
        $this->assertSame(0, $this->countRows('generated_documents'));
    }

    public function testOnlyAuthorisedRolesCanGenerateOrManageTemplates(): void
    {
        $A  = $this->w['A'];
        $id = $this->offerRecord();
        $t  = $this->template($A['company'], 'offer_letter', self::OFFER_HTML);

        foreach (['employee', 'accountant', 'compliance', 'manager'] as $role) {
            $this->actAs($A[$role])->visit("/documents/render/offer_letter/$id");
            $this->assertTrue($this->res->isRedirect(), "$role must not generate an offer letter");
            $this->assertSame(0, $this->countRows('generated_documents'));
        }
        foreach (['employee', 'hr', 'accountant', 'manager'] as $role) {
            $this->actAs($A[$role])->submit("/documents/templates/$t/deactivate");
            $this->assertSame('active', $this->row('document_templates', ['id' => $t])['status'], "$role cannot manage templates");
        }
        $this->actAs($A['hr'])->visit("/documents/render/offer_letter/$id?inline=1");
        $this->assertTrue($this->res->isOK());
        $this->assertStringContainsString('inline', $this->res->response()->getHeaderLine('Content-Disposition'));
    }

    public function testDeactivatingTheTemplateStopsNewLettersButKeepsIssuedOnes(): void
    {
        $A  = $this->w['A'];
        $id = $this->offerRecord();
        $t  = $this->template($A['company'], 'offer_letter', self::OFFER_HTML);
        $this->actAs($A['hr']);
        $this->downloadArchived("/hr/onboarding/$id/offer-letter/pdf", 'offer_letter', $id);

        $this->actAs($A['admin'])->submit("/documents/templates/$t/deactivate");
        $this->assertFlash('success', 'deactivated');

        // The issued letter for unchanged data is still retrievable ...
        $this->actAs($A['hr'])->visit("/hr/onboarding/$id/offer-letter/pdf");
        $this->assertTrue($this->res->isOK());
        $this->assertStringStartsWith('%PDF', $this->body());
        // ... but a letter for a record that changed (or a new record) cannot be produced.
        db_connect()->table('onboarding_records')->where('id', $id)->update(['offered_ctc' => 1]);
        $this->actAs($A['hr'])->visit("/hr/onboarding/$id/offer-letter/pdf");
        $this->assertTrue($this->res->isRedirect());
        $this->assertFlash('error', 'No active Offer Letter template');
        $this->assertSame(1, $this->countRows('generated_documents'));
    }

    public function testAccountingDocumentsFallBackToBuiltInButHrDocumentsDoNot(): void
    {
        $A = $this->w['A'];
        $this->assertTrue(\App\Modules\Document\Services\DocumentTypeRegistry::isStrict('offer_letter'));
        $this->assertTrue(\App\Modules\Document\Services\DocumentTypeRegistry::isStrict('payslip'));
        $this->assertTrue(\App\Modules\Document\Services\DocumentTypeRegistry::isStrict('relieving_letter'));
        $this->assertFalse(\App\Modules\Document\Services\DocumentTypeRegistry::isStrict('invoice'));
        $this->assertFalse(\App\Modules\Document\Services\DocumentTypeRegistry::isStrict('bill'));
        $this->assertFalse(\App\Modules\Document\Services\DocumentTypeRegistry::isStrict('purchase_order'));
        $this->assertFalse(\App\Modules\Document\Services\DocumentTypeRegistry::isStrict('expense_report'));
    }
}
