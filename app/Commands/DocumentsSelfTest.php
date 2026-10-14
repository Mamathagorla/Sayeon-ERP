<?php

namespace App\Commands;

use App\Modules\Document\Controllers\DocumentTemplateController;
use App\Modules\Document\Models\DocumentTemplateModel;
use App\Modules\Document\Models\GeneratedDocumentModel;
use App\Modules\Document\Services\DocumentTypeRegistry;
use App\Modules\Document\Services\TemplatedDocument;
use App\Modules\Document\Services\TemplateWriter;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Self-contained checks for company-specific document templates:
 *   php spark documents:selftest
 *
 * PHPUnit/sqlite are not installed in this project, so this runs the same
 * assertions against the configured database. It creates its own throw-away
 * fixtures (two onboarding records, templates, generated documents) and
 * removes everything it created, pass or fail. All database work happens inside a
 * transaction that is rolled back, so existing templates/documents are never modified.
 */
class DocumentsSelfTest extends BaseCommand
{
    protected $group       = 'Documents';
    protected $name        = 'documents:selftest';
    protected $description = 'Runs the company-template isolation / clone / immutability checks.';

    private int $pass = 0;
    private int $fail = 0;
    private array $createdTemplates = [];
    private array $createdRecords   = [];

    public function run(array $params)
    {
        helper(['localization', 'payroll', 'permission']);
        $db = db_connect();

        // Everything below runs inside ONE transaction that is always rolled back, so the
        // self-test can never retire, change or delete a real template/document (activating
        // a fixture template would otherwise deactivate the company's real active one).
        // Only files are touched outside the DB, and those are removed in cleanup().
        $db->transBegin();

        $companies = $db->table('companies')->select('id, name')->where('deleted_at', null)->orderBy('id')->get()->getResultArray();
        $pick = static function (array $cs, string $like) {
            foreach ($cs as $c) {
                if (stripos($c['name'], $like) !== false) {
                    return $c;
                }
            }

            return null;
        };
        $sanvima = $pick($companies, 'sanvima') ?? $companies[0] ?? null;
        $sayeon  = $pick($companies, 'sayeon') ?? (array_values(array_filter($companies, static fn ($c) => $c['id'] !== ($sanvima['id'] ?? 0)))[0] ?? null);
        if ($sanvima === null || $sayeon === null) {
            CLI::error('Need at least two companies.');

            return EXIT_ERROR;
        }
        CLI::write("Company A = {$sanvima['name']} (#{$sanvima['id']}), Company B = {$sayeon['name']} (#{$sayeon['id']})");

        $templates = new DocumentTemplateModel();
        $generated = new GeneratedDocumentModel();
        $svc       = new TemplatedDocument();
        $type      = 'offer_letter';
        $joining   = 'joining_letter';

        try {
            // --- fixtures: one onboarding record per company -------------------------------------
            $recA = $this->record($db, (int) $sanvima['id'], 'Asha Sanvima');
            $recB = $this->record($db, (int) $sayeon['id'], 'Bala Sayeon');

            $tplA = $this->template($templates, (int) $sanvima['id'], $type, '<html><body>SANVIMA-FORMAT {{employee_name}}</body></html>', 'active');
            $tplB = $this->template($templates, (int) $sayeon['id'], $type, '<html><body>SAYEON-FORMAT {{employee_name}}</body></html>', 'active');

            // 1 + 2. each employee gets only their own company's template -----------------------------
            $a = $svc->forRecord($type, $recA);
            $b = $svc->forRecord($type, $recB);
            $this->ok('Company A employee: document generated', $a['status'] === TemplatedDocument::OK, $a['message'] ?? '');
            $this->ok('Company B employee: document generated', $b['status'] === TemplatedDocument::OK, $b['message'] ?? '');
            $rowA = $generated->where(['document_type' => $type, 'record_id' => $recA])->first();
            $rowB = $generated->where(['document_type' => $type, 'record_id' => $recB])->first();
            $this->ok('Company A employee used Company A template', $rowA && (int) $rowA['template_id'] === $tplA && (int) $rowA['company_id'] === (int) $sanvima['id']);
            $this->ok('Company B employee used only Company B template', $rowB && (int) $rowB['template_id'] === $tplB && (int) $rowB['company_id'] === (int) $sayeon['id']);

            // company mismatch is rejected even if a foreign template is forced in
            $mismatch = $svc->forRecord($type, $recB, null, $templates->find($tplA));
            $this->ok('Employee/template company mismatch is rejected', $mismatch['status'] === TemplatedDocument::FAILED);

            // 5. no active template blocks official HR documents --------------------------------------
            // Use whichever test company really has no active joining-letter template (real data may have one).
            $noTplRec = $templates->activeFor((int) $sanvima['id'], $joining) === null ? $recA : ($templates->activeFor((int) $sayeon['id'], $joining) === null ? $recB : null);
            if ($noTplRec === null) {
                CLI::write('  SKIP  both companies already have a joining-letter template', 'yellow');
            } else {
                $none = $svc->forRecord($joining, $noTplRec);
                $this->ok('No active template: generation blocked', $none['status'] === TemplatedDocument::NONE && ! empty($none['strict']), $none['message'] ?? '');
                $this->ok('No other company template used as fallback', $generated->where(['document_type' => $joining, 'record_id' => $noTplRec])->first() === null);
            }
            $this->ok('HR documents are strict; accounting documents are not', DocumentTypeRegistry::isStrict('payslip') && DocumentTypeRegistry::isStrict('relieving_letter') && ! DocumentTypeRegistry::isStrict('invoice'));

            // 3. cloning A -> B leaves A untouched ----------------------------------------------------------
            $beforeRow  = $templates->find($tplA);
            $beforeFile = md5((string) service('fileStorage')->absolutePath($beforeRow['file_path']) . '|' . md5_file(service('fileStorage')->absolutePath($beforeRow['file_path'])));
            $cloneId = (new TemplateWriter())->cloneTo($beforeRow, (int) $sayeon['id'], $sanvima['name'], null);
            $this->createdTemplates[] = $cloneId;
            $clone = $templates->find($cloneId);
            $afterRow  = $templates->find($tplA);
            $afterFile = md5((string) service('fileStorage')->absolutePath($afterRow['file_path']) . '|' . md5_file(service('fileStorage')->absolutePath($afterRow['file_path'])));
            $this->ok('Clone is a separate Draft owned by the target company', $clone['status'] === 'draft' && (int) $clone['company_id'] === (int) $sayeon['id'] && (int) $clone['id'] !== $tplA);
            $this->ok('Clone records its source template', (int) $clone['source_template_id'] === $tplA);
            $this->ok('Clone has its own file', $clone['file_path'] !== $beforeRow['file_path'] && is_file(service('fileStorage')->absolutePath($clone['file_path'])));
            $this->ok('Source template row unchanged by cloning', $beforeRow === $afterRow);
            $this->ok('Source template file unchanged by cloning', $beforeFile === $afterFile);

            // draft clone is not usable; editing it never alters the source ----------------------------------------
            $this->ok('Draft clone is not an active template', $templates->activeFor((int) $sayeon['id'], $type)['id'] == $tplB);
            (new TemplateWriter())->replaceHtml($clone, '<html><body>EDITED-CLONE {{employee_name}}</body></html>');
            $afterEdit = $templates->find($tplA);
            $this->ok('Editing the clone leaves the source row and file unchanged', $afterEdit === $beforeRow && md5_file(service('fileStorage')->absolutePath($afterEdit['file_path'])) === md5_file(service('fileStorage')->absolutePath($beforeRow['file_path'])));
            $edited = $templates->find($cloneId);
            $this->ok('Edited clone is back to Draft and needs a new preview', $edited['status'] === 'draft' && $edited['previewed_at'] === null);

            // HTML editor: header / body / footer split and rebuild
            $rend  = new \App\Modules\Document\Services\TemplateRenderer();
            $parts = $rend->splitHtml('<html><head><style>p{}</style></head><body><header>HEAD</header><p>BODY</p><footer>FOOT</footer></body></html>');
            $this->ok('HTML splits into header / body / footer', $parts['header'] === 'HEAD' && $parts['body'] === '<p>BODY</p>' && $parts['footer'] === 'FOOT');
            $parts['header'] = 'H2';
            $again = $rend->splitHtml($rend->joinHtml($parts));
            $this->ok('Edited parts rebuild and split back identically', $again['header'] === 'H2' && $again['body'] === '<p>BODY</p>' && $again['footer'] === 'FOOT' && str_contains($again['prefix'], '<style>p{}</style>'));

            // 4. a Company B admin cannot edit/activate Company A's template -----------------------------------------
            session()->set(['roleSlug' => 'admin', 'active_company_id' => (int) $sayeon['id']]);
            $ctl = new DocumentTemplateController();
            $scoped = new \ReflectionMethod($ctl, 'scopedTemplate');
            $scoped->setAccessible(true);
            $this->ok('Company B admin cannot load Company A template', $scoped->invoke($ctl, $tplA) === null);
            $this->ok('Company B admin can load its own template', $scoped->invoke($ctl, $tplB) !== null);
            $templates->update($tplA, ['status' => 'inactive']);
            $templates->update($tplA, ['status' => 'active']); // keep A active for the remaining checks
            $ctl->activate($tplA);
            $ctl->saveEdit($tplA);
            $afterAttack = $templates->find($tplA);
            $this->ok('Activate/edit of Company A template by Company B admin has no effect', $afterAttack['file_path'] === $beforeRow['file_path'] && $afterAttack['status'] === 'active');
            $gate = new \ReflectionMethod($ctl, 'previewGate');
            $gate->setAccessible(true);
            $this->ok('A draft cannot be activated before it is previewed', $gate->invoke($ctl, $templates->find($cloneId)) !== null);
            $templates->update($cloneId, ['previewed_at' => date('Y-m-d H:i:s')]);
            $this->ok('A previewed draft may be activated', $gate->invoke($ctl, $templates->find($cloneId)) === null);
            session()->remove(['roleSlug', 'active_company_id']);

            // 6. issued documents stay unchanged after a new version is activated ---------------------------------------
            $before = $generated->where(['document_type' => $type, 'record_id' => $recA])->orderBy('id')->first();
            $bytesBefore = (string) file_get_contents(service('fileStorage')->absolutePath($before['file_path']));
            $v2 = $this->template($templates, (int) $sanvima['id'], $type, '<html><body>SANVIMA-FORMAT-V2 {{employee_name}}</body></html>', 'draft');
            $templates->update($v2, ['previewed_at' => date('Y-m-d H:i:s')]);
            $templates->activate($v2);
            $again = $svc->forRecord($type, $recA);
            $rowsNow = $generated->where(['document_type' => $type, 'record_id' => $recA])->orderBy('id')->findAll();
            $this->ok('New version is active for the company', $templates->activeFor((int) $sanvima['id'], $type)['id'] == $v2);
            $this->ok('A new template version builds a NEW document for the same record (the archived v1 file is not reused)',
                $again['status'] === TemplatedDocument::OK && $again['body'] !== $bytesBefore && count($rowsNow) === 2 && (int) $rowsNow[1]['template_id'] === $v2);
            $this->ok('The previously issued v1 document row and file are untouched',
                (int) $generated->find($before['id'])['template_version'] === (int) $templates->find($tplA)['version'] && md5_file(service('fileStorage')->absolutePath($before['file_path'])) === md5($bytesBefore));
            $third = $svc->forRecord($type, $recA);
            $this->ok('Repeating the request reuses that v2 document (same record, same template, same data): no extra document', $third['body'] === $again['body'] && $generated->where(['document_type' => $type, 'record_id' => $recA])->countAllResults() === 2);

            $db->table('onboarding_records')->where('id', $recA)->update(['designation' => 'Changed Designation']);
            $new = $svc->forRecord($type, $recA);
            $rows = $generated->where(['document_type' => $type, 'record_id' => $recA])->orderBy('id')->findAll();
            $this->ok('When the record data changes, a new document is built', $new['status'] === TemplatedDocument::OK && count($rows) === 3 && (int) $rows[2]['template_id'] === $v2);
            $this->ok('Earlier issued documents stay untouched', md5_file(service('fileStorage')->absolutePath($rows[0]['file_path'])) === md5($bytesBefore));

            // ===== data correctness, missing fields, preview vs production, greetings =====
            $rend = new \App\Modules\Document\Services\TemplateRenderer();
            $docxUse = $noTplRec ?? $recA;
            $useCo   = (int) $db->table('onboarding_records')->select('company_id')->where('id', $docxUse)->get()->getRow('company_id');
            $useName = (string) $db->table('onboarding_records')->select('candidate_name')->where('id', $docxUse)->get()->getRow('candidate_name');
            $coName  = (string) $db->table('companies')->select('name')->where('id', $useCo)->get()->getRow('name');
            $otherName = $useCo === (int) $sanvima['id'] ? 'Bala Sayeon' : 'Asha Sanvima';

            $mkDocx = function (string $body, string $status = 'active') use ($templates, $useCo, $joining) {
                return $this->template($templates, $useCo, $joining, $this->docx($body), $status, 'docx');
            };

            // real record data only
            $good = $mkDocx('Greeting: {{greeting}} | Name: {{employee_name}} | Role: {{designation}} | Company: {{company_name}} | Joins: {{joining_date}}');
            $doc  = $svc->forRecord($joining, $docxUse);
            $txt  = $doc['status'] === TemplatedDocument::OK ? $rend->docxText($doc['body']) : '';
            $this->ok('Production document is built from the actual record', $doc['status'] === TemplatedDocument::OK && str_contains($txt, "Name: {$useName}") && str_contains($txt, 'Role: Test Engineer') && str_contains($txt, "Company: {$coName}"), $doc['message'] ?? $txt);
            $this->ok('No other person data and no sample text in a real document', ! str_contains($txt, $otherName) && ! str_contains($txt, 'Sample') && ! str_contains($txt, 'PREVIEW'));
            $this->ok('Greeting is neutral (no inferred Mr./Ms.)', str_contains($txt, "Greeting: Dear {$useName}") && ! preg_match('/\b(Mr|Mrs|Ms|Miss)\b/', $txt));

            // snapshot + template/version recorded
            $row  = $generated->where(['document_type' => $joining, 'record_id' => $docxUse])->orderBy('id', 'DESC')->first();
            $snap = json_decode((string) ($row['data_snapshot'] ?? ''), true);
            $this->ok('Template id/version and data snapshot are recorded', $row && (int) $row['template_id'] === $good && ($row['template_version'] ?? 0) > 0 && ($snap['employee_name'] ?? null) === $useName);

            // preview is labelled, never archived, and cannot be mistaken for the real thing
            $before = $generated->where(['document_type' => $joining])->countAllResults();
            $prev   = $svc->previewTemplate($templates->find($good));
            $ptxt   = $prev['status'] === TemplatedDocument::OK ? $rend->docxText($prev['body']) : '';
            $this->ok('Preview output is clearly labelled PREVIEW', $prev['status'] === TemplatedDocument::OK && str_contains($ptxt, 'PREVIEW') && str_starts_with($prev['title'], 'PREVIEW'), $prev['message'] ?? '');
            $this->ok('Preview is never stored as an issued document', $generated->where(['document_type' => $joining])->countAllResults() === $before);

            // missing stored value blocks the real document, but the preview falls back to labelled sample data
            $dept    = $mkDocx('Dept: {{department}} Name: {{employee_name}}');
            $blocked = $svc->forRecord($joining, $this->record($db, $useCo, 'Fresh Person One'));
            $this->ok('Missing stored value blocks generation (no blank, no sample)', $blocked['status'] === TemplatedDocument::INCOMPLETE && stripos($blocked['message'], 'department') !== false, $blocked['message']);
            $prev2 = $svc->previewTemplate($templates->find($dept));
            $p2    = $prev2['status'] === TemplatedDocument::OK ? $rend->docxText($prev2['body']) : '';
            $this->ok('Sample values appear only in the labelled preview', str_contains($p2, 'SAMPLE DATA') && str_contains($p2, 'Sample department'), $prev2['message'] ?? '');

            // placeholders the document type does not provide / fixed titles
            $mkDocx('Hello {{employee_name}} {{no_such_field}}');
            $r1 = $svc->forRecord($joining, $this->record($db, $useCo, 'Fresh Person Two'));
            $this->ok('Unknown placeholder blocks generation with a clear message', $r1['status'] === TemplatedDocument::FAILED && str_contains($r1['message'], '{{no_such_field}}'), $r1['message']);
            $mkDocx('Dear Mr. {{employee_name}},');
            $r2 = $svc->forRecord($joining, $this->record($db, $useCo, 'Fresh Person Three'));
            $this->ok('A fixed title before the name blocks generation', $r2['status'] === TemplatedDocument::FAILED && str_contains($r2['message'], 'fixed title'), $r2['message']);

            // unresolved review findings block every format, not just PDF
            $flagged = $mkDocx('Name: {{employee_name}}');
            $templates->update($flagged, ['field_review' => json_encode([['field' => 'wording', 'label' => 'she', 'reason' => 'test', 'blocking' => true]])]);
            $r3 = $svc->forRecord($joining, $this->record($db, $useCo, 'Fresh Person Four'));
            $this->ok('An active DOCX template with unresolved findings is blocked', $r3['status'] === TemplatedDocument::FAILED, $r3['message']);

            // pure helpers
            $R = DocumentTypeRegistry::class;
            // layout: DOCX runs, spacing, line breaks and exactly-once replacement
            $xmlBody = '<w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Dear </w:t></w:r><w:r><w:t>{{emp</w:t></w:r><w:r><w:rPr><w:i/></w:rPr><w:t>loyee_name}}</w:t></w:r><w:r><w:t>,</w:t></w:r></w:p>'
                . '<w:p><w:r><w:t>Role:</w:t></w:r><w:r><w:tab/><w:t>{{designation}}</w:t></w:r><w:r><w:t xml:space="preserve"> / {{address}} / {{designation}}</w:t></w:r></w:p>';
            $filled = $rend->docxFill($this->docx($xmlBody, true), ['employee_name' => 'A {{x}} B', 'designation' => 'Engineer', 'address' => "Line1
Line2"]);
            $zipT   = $filled === null ? '' : (function () use ($filled) { $t = tempnam(sys_get_temp_dir(), 'dx'); file_put_contents($t, $filled); $z = new \ZipArchive(); $z->open($t); $x = (string) $z->getFromName('word/document.xml'); $z->close(); unlink($t); return $x; })();
            $this->ok('DOCX split placeholder is replaced once, in its own run, keeping bold/italic runs, tabs and trailing spaces',
                substr_count($zipT, 'Engineer') === 2 && str_contains($zipT, '<w:b/></w:rPr><w:t xml:space="preserve">Dear </w:t>') && str_contains($zipT, '<w:tab/>')
                && str_contains($zipT, 'A &#123;&#123;x}} B') && ! str_contains($zipT, '{{emp') && ! str_contains($zipT, 'loyee_name}}'));
            $this->ok('DOCX values with line breaks become Word line breaks (no merged lines)', str_contains($zipT, 'Line1</w:t><w:br/><w:t xml:space="preserve">Line2'));
            $this->ok('HTML values keep line breaks and "{{" in data is never re-read as a placeholder',
                $rend->fill('<p>{{address}} {{employee_name}}</p>', ['address' => "L1
L2", 'employee_name' => '{{address}}']) === "<p>L1<br>
L2 &#123;&#123;address}}</p>");

            $this->ok('Salutation: no stored title -> just the name', $R::salutation('Priya Rao', null) === 'Priya Rao' && $R::salutation('Arun Kumar', '') === 'Arun Kumar');
            $this->ok('Salutation: stored title used once, never duplicated', $R::salutation('Ravi Kumar', 'Dr.') === 'Dr. Ravi Kumar' && $R::salutation('Dr. Ravi Kumar', 'Dr.') === 'Dr. Ravi Kumar' && $R::salutation('Dr Ravi', 'Dr.') === 'Dr Ravi');
            $this->ok('Fixed gendered wording is detected, placeholders are not', $rend->genderedWords('She was here. Her work. Dear {{salutation_name}}') === ['she', 'her'] && $rend->genderedWords('Dear {{salutation_name}}, welcome') === []);
            $aud = $rend->audit('{{#items}}{{description}}{{/items}} {{total}}', ['items' => [], 'total' => '5', 'description' => 'x']);
            $this->ok('A repeating section with no rows is reported', $aud['emptySections'] === ['items']);

            // ===== company-specific, workflow-aware document availability =====
            // Two companies with different template sets. Only companies that have no real active template are used,
            // and everything here is rolled back with the rest of the transaction.
            $freeCompanies = array_values(array_filter($companies, static fn ($c) => $db->table('document_templates')->where(['company_id' => $c['id'], 'status' => 'active'])->countAllResults() === 0));
            if (count($freeCompanies) < 2) {
                CLI::write('  SKIP  availability checks need two companies without real active templates', 'yellow');
            } else {
                [$cA, $cB] = [(int) $freeCompanies[0]['id'], (int) $freeCompanies[1]['id']];
                $HR = ['roleSlug' => 'hr', 'permissions' => ['onboarding.edit', 'employee.edit', 'offboarding.edit', 'payroll.edit']];
                session()->set($HR);

                $mkA = $this->template($templates, $cA, 'offer_letter', '<html><body>A offer {{employee_name}}</body></html>', 'active');
                $this->template($templates, $cA, 'joining_letter', '<html><body>A joining {{employee_name}}</body></html>', 'active');
                $this->template($templates, $cA, 'exit_letter', '<html><body>A exit {{employee_name}}</body></html>', 'active');
                $this->template($templates, $cB, 'offer_letter', '<html><body>B offer {{employee_name}}</body></html>', 'active');
                $this->template($templates, $cB, 'joining_letter', '<html><body>B draft {{employee_name}}</body></html>', 'draft');      // draft: not offered
                $bRelieve = $this->template($templates, $cB, 'relieving_letter', '<html><body>B relieving {{employee_name}}</body></html>', 'active');
                $templates->update($bRelieve, ['status' => 'inactive']);                                                                    // inactive: not offered
                $flagged = $this->template($templates, $cB, 'exit_letter', '<html><body>B exit {{employee_name}}</body></html>', 'active');
                $templates->update($flagged, ['field_review' => json_encode([['field' => 'wording', 'label' => 'she', 'reason' => 'test', 'blocking' => true]])]); // unresolved review: not offered

                $av = new \App\Modules\Document\Services\DocumentAvailability();
                $onA = array_keys($av->forWorkflow('onboarding', $cA));
                $onB = array_keys($av->forWorkflow('onboarding', $cB));
                sort($onA);
                $this->ok('Company A is offered its own onboarding documents (offer + joining)', $onA === ['joining_letter', 'offer_letter'], json_encode($onA));
                $this->ok('Company B is offered only what it has active (offer; its draft joining letter is hidden)', $onB === ['offer_letter'], json_encode($onB));
                $this->ok('Offboarding: A gets its exit letter, B gets nothing (inactive relieving + flagged exit are hidden)', array_keys($av->forWorkflow('offboarding', $cA)) === ['exit_letter'] && $av->forWorkflow('offboarding', $cB) === []);
                $this->ok('Types a company never configured are not offered (no payslip/invoice for A)', ! $av->has('payslip', $cA) && $av->forWorkflow('employee', $cA) === [] && $av->forWorkflow('accounting', $cA) === []);
                $this->ok('A template of one company never shows up for the other', ! $av->has('exit_letter', $cB) && ! $av->has('joining_letter', $cB) && $av->has('exit_letter', $cA));

                // workflow mapping comes from the registry, not a hard-coded list per page
                $this->ok('Registry maps types to workflows (offer=onboarding, payslip=payroll, relieving+internship cert=offboarding, invoice+bill=accounting)',
                    in_array('offer_letter', DocumentTypeRegistry::forWorkflow('onboarding'), true) && in_array('payslip', DocumentTypeRegistry::forWorkflow('payroll'), true)
                    && in_array('relieving_letter', DocumentTypeRegistry::forWorkflow('offboarding'), true) && DocumentTypeRegistry::forWorkflow('accounting') === ['invoice', 'bill']
                    && in_array('internship_completion_certificate', DocumentTypeRegistry::forWorkflow('offboarding'), true));

                // Internship Completion Certificate: active for A only -> shows in A's Offboarding menu (numeric company id), not B's
                $cert = $this->template($templates, $cA, 'internship_completion_certificate', '<html><body>A cert {{employee_name}}</body></html>', 'active');
                $av->flush();
                $this->ok('Active Internship Completion Certificate is offered in the Offboarding workflow for its company only',
                    isset($av->forWorkflow('offboarding', $cA)['internship_completion_certificate']) && ! isset($av->forWorkflow('offboarding', $cB)['internship_completion_certificate']));
                $items = $av->items('offboarding', $cA, ['offboarding_record' => 7, 'employee_profile' => 9]);
                $certItem = array_values(array_filter($items, static fn ($i) => $i['type'] === 'internship_completion_certificate'));
                $this->ok('...with the employee profile as its record, and no item when that record is missing', ($certItem[0]['recordId'] ?? 0) === 9
                    && array_filter($av->items('offboarding', $cA, ['offboarding_record' => 7]), static fn ($i) => $i['type'] === 'internship_completion_certificate') === []);

                // a template pinned to another workflow leaves the default one
                $templates->update($cert, ['workflow' => 'hr']);
                $av->flush();
                $this->ok('A template pinned to a workflow is offered only there', ! isset($av->forWorkflow('offboarding', $cA)['internship_completion_certificate']) && isset($av->forWorkflow('hr', $cA)['internship_completion_certificate']));
                $templates->update($cert, ['workflow' => null]);
                $av->flush();

                // configurable default mapping (Config\DocumentWorkflows)
                $cfg = config(\Config\DocumentWorkflows::class);
                $cfg->overrides = ['internship_completion_certificate' => ['payroll']];
                $this->ok('Config\DocumentWorkflows overrides the default mapping without code changes', DocumentTypeRegistry::workflowsOf('internship_completion_certificate') === ['payroll']);
                $cfg->overrides = [];
                $this->ok('...and clearing the override restores the registry default', in_array('offboarding', DocumentTypeRegistry::workflowsOf('internship_completion_certificate'), true));
                $av->flush();

                // permission: a plain employee is offered nothing even though the company has templates
                session()->set(['roleSlug' => 'employee', 'permissions' => ['payroll.view']]);
                $this->ok('A user without document permissions is offered no actions', $av->forWorkflow('onboarding', $cA) === [] && $av->has('offer_letter', $cA));
                session()->set($HR);

                // newly activated / deactivated templates change availability immediately (no record edits)
                $newB = $this->template($templates, $cB, 'joining_letter', '<html><body>B joining {{employee_name}}</body></html>', 'draft');
                $templates->update($newB, ['previewed_at' => date('Y-m-d H:i:s')]);
                $templates->activate($newB);
                $av->flush();
                $onB2 = array_keys($av->forWorkflow('onboarding', $cB));
                sort($onB2);
                $this->ok('Newly activated template appears at once for its company only', $onB2 === ['joining_letter', 'offer_letter'] && ! $av->has('joining_letter', $cA) === false);
                $templates->update($newB, ['status' => 'inactive']);
                $av->flush();
                $this->ok('Deactivated template disappears at once', array_keys($av->forWorkflow('onboarding', $cB)) === ['offer_letter']);

                // server-side revalidation when the action is clicked
                $recA2 = $this->record($db, $cA, 'Avail Person A');
                $recB2 = $this->record($db, $cB, 'Avail Person B');
                $svcA  = new TemplatedDocument();
                $issued = $svcA->forRecord('offer_letter', $recB2);
                $this->ok('An available action generates with that company\'s own template', $issued['status'] === TemplatedDocument::OK
                    && (int) $generated->where(['document_type' => 'offer_letter', 'record_id' => $recB2])->first()['company_id'] === $cB);

                // HR scoped to company B cannot generate for company A's candidate
                session()->set($HR + ['active_company_id' => $cB]);
                $ctl     = new \App\Modules\Document\Controllers\DocumentRenderController();
                $before2 = $generated->countAllResults();
                $denied  = $ctl->show('offer_letter', $recA2);
                $this->ok('Cross-company generation is refused server-side (no document created)', $denied instanceof \CodeIgniter\HTTP\RedirectResponse && $generated->countAllResults() === $before2);
                $scoped = new \ReflectionMethod(new \App\Modules\Document\Controllers\DocumentTemplateController(), 'scopedTemplate');
                $scoped->setAccessible(true);
                $this->ok('Company B user cannot reach Company A templates', $scoped->invoke(new \App\Modules\Document\Controllers\DocumentTemplateController(), $mkA) === null);
                session()->set($HR);

                // template removed between showing the button and clicking it
                $offB = $templates->where(['company_id' => $cB, 'document_type' => 'offer_letter', 'status' => 'active'])->first();
                $templates->update((int) $offB['id'], ['status' => 'inactive']);
                $av->flush();
                $recB3   = $this->record($db, $cB, 'Avail Person B3');
                $late    = $svcA->forRecord('offer_letter', $recB3);
                $this->ok('If the template was deactivated since the page loaded, the click is blocked with a clear message', $late['status'] === TemplatedDocument::NONE && ! empty($late['strict']) && str_contains($late['message'], 'No active Offer Letter template'), $late['message'] ?? '');
                $this->ok('...and the next page view no longer offers the action', $av->forWorkflow('onboarding', $cB) === []);
                $again2 = $svcA->forRecord('offer_letter', $recB2);
                $this->ok('A document issued earlier stays retrievable and unchanged after its template is deactivated', $again2['status'] === TemplatedDocument::OK && $again2['body'] === $issued['body']);

                // uploading / cloning into one company can never alter another company's availability
                $beforeA = $av->overview([$cA]);
                $cloneToB = (new \App\Modules\Document\Services\TemplateWriter())->cloneTo($templates->find($mkA), $cB, 'A', null);
                $this->createdTemplates[] = $cloneToB;
                $av->flush();
                $this->ok('Cloning A\'s template into B creates only a Draft: neither company\'s offered actions change', $templates->find($cloneToB)['status'] === 'draft' && $av->overview([$cA]) === $beforeA && $av->forWorkflow('onboarding', $cB) === []);

                // clone adapts to the target company, stays independent, and flags what it cannot change
                $nameA = (string) $db->table('companies')->where('id', $cA)->get()->getRow('name');
                $nameB = (string) $db->table('companies')->where('id', $cB)->get()->getRow('name');
                $srcHtml = $this->template($templates, $cA, 'experience_certificate', '<html><body><header><img src="logo.png"> ' . $nameA . '</header><p>This certifies {{employee_name}} worked at ' . $nameA . '.</p></body></html>', 'inactive');
                $srcBefore = $templates->find($srcHtml);
                $srcBytes  = (new \App\Modules\Document\Services\TemplateRenderer())->loadStored($srcBefore['file_path']);
                $writer = new \App\Modules\Document\Services\TemplateWriter();
                $cl = $writer->cloneTo($srcBefore, $cB, $nameA, null);
                $this->createdTemplates[] = $cl;
                $clRow = $templates->find($cl);
                $clHtml = (string) (new \App\Modules\Document\Services\TemplateRenderer())->loadStored($clRow['file_path']);
                $this->ok('Clone: the source company\'s name typed into the text becomes {{company_name}} (target data prints)', $nameA === '' || (! str_contains($clHtml, $nameA) && str_contains($clHtml, '{{company_name}}')));
                $this->ok('Clone is an independent draft with its own file; source row and file are unchanged',
                    $clRow['status'] === 'draft' && $clRow['file_path'] !== $srcBefore['file_path'] && (int) $clRow['source_template_id'] === $srcHtml
                    && $templates->find($srcHtml) === $srcBefore && (new \App\Modules\Document\Services\TemplateRenderer())->loadStored($srcBefore['file_path']) === $srcBytes);
                $this->ok('Clone: the logo image is flagged for the target company\'s own logo', str_contains((string) $clRow['field_review'], '"logo"'));
                $pdfSrc = $this->template($templates, $cA, 'offer_letter', '%PDF-1.4 fake', 'inactive', 'pdf');
                $pdfCl  = $writer->cloneTo($templates->find($pdfSrc), $cB, $nameA, null);
                $this->createdTemplates[] = $pdfCl;
                $this->ok('Clone of a fixed-layout PDF is flagged: letterhead is not editable and blocks activation until reviewed',
                    $templates->find($pdfCl)['status'] === 'draft' && TemplatedDocument::blockingFlags($templates->find($pdfCl)) !== []);

                // PDF -> editable HTML for another company: new HTML draft, brand text becomes the target's data, source untouched
                $pdfBefore = $templates->find($pdfSrc);
                $conv = $writer->fromHtml($pdfBefore, '<!doctype html><html><body><p>Employees of ' . $nameA . ' and ' . strtok($nameA . ' ', ' ') . ': {{salutation_name}}</p></body></html>', $cB, 'Editable copy', [], null);
                $this->createdTemplates[] = $conv;
                $convRow  = $templates->find($conv);
                $convHtml = (string) (new \App\Modules\Document\Services\TemplateRenderer())->loadStored($convRow['file_path']);
                $this->ok('PDF made editable: new HTML draft owned by the target company, linked to its source, with the source company\'s name replaced',
                    $convRow['file_type'] === 'html' && $convRow['status'] === 'draft' && (int) $convRow['company_id'] === $cB && (int) $convRow['source_template_id'] === $pdfSrc
                    && str_contains($convHtml, '{{company_name}}') && ($nameA === '' || ! str_contains($convHtml, strtok($nameA . ' ', ' '))) && str_contains($convHtml, '{{salutation_name}}'));
                $this->ok('...and the source PDF template is unchanged', $templates->find($pdfSrc) === $pdfBefore);

                // preview with the template's own company details + sample employee data
                $prevTpl = $this->template($templates, $cB, 'experience_certificate', $this->docx('{{company_name}} certifies {{employee_name}}'), 'draft', 'docx');
                $prev2 = (new TemplatedDocument())->previewTemplate($templates->find($prevTpl));
                $prevTxt = $prev2['status'] === TemplatedDocument::OK ? (new \App\Modules\Document\Services\TemplateRenderer())->docxText($prev2['body']) : '';
                $this->ok('Preview shows the target company\'s own name, never another company\'s', $prev2['status'] === TemplatedDocument::OK && str_contains($prevTxt, $nameB) && ($nameA === '' || $nameA === $nameB || ! str_contains($prevTxt, $nameA)), $prev2['message'] ?? '');

                session()->remove(['roleSlug', 'permissions', 'active_company_id']);
            }

            // ===== two employees, same company, same template: each document carries only its own data =====
            $realOffer = $templates->where(['company_id' => 1, 'document_type' => 'offer_letter', 'file_type' => 'pdf'])->where('field_map IS NOT NULL')->where('field_review IS NULL')->orderBy('id', 'DESC')->first();
            $hasPdftotext = trim((string) @shell_exec('pdftotext -v 2>&1')) !== '' && stripos((string) @shell_exec('pdftotext -v 2>&1'), 'pdftotext') !== false;
            if ($realOffer === null) {
                CLI::write('  SKIP  two-employee PDF check needs an active PDF offer-letter template for company 1', 'yellow');
            } else {
                // Same official PDF + field map as the live template, as a transaction-only copy.
                $copyId = (new \App\Modules\Document\Services\TemplateWriter())->cloneTo($realOffer, 1, 'test', null);
                $this->createdTemplates[] = $copyId;
                $templates->update($copyId, ['previewed_at' => date('Y-m-d H:i:s'), 'field_review' => null]);
                $templates->activate($copyId);

                $mkEmp = function (string $name, string $desig, string $join, int $ctc, string $street) use ($db) {
                    $id = $this->record($db, 1, $name);
                    $db->table('onboarding_records')->where('id', $id)->update(['designation' => $desig, 'joining_date' => $join, 'offered_ctc' => $ctc, 'address_line1' => $street, 'city' => 'Hyderabad', 'state' => 'Telangana', 'postal_code' => '500001']);

                    return $id;
                };
                $empA = $mkEmp('Aarav Mehta', 'Senior Developer', '2026-11-03', 840000, '12 Lake View Road');
                $empB = $mkEmp('Bhavna Rao', 'Accounts Executive', '2026-12-15', 420000, '48 Hill Crest Avenue');

                $dA = $svc->forRecord('offer_letter', $empA);
                $dB = $svc->forRecord('offer_letter', $empB);
                $this->ok('Both employees get a generated document from the same company template', $dA['status'] === TemplatedDocument::OK && $dB['status'] === TemplatedDocument::OK, ($dA['message'] ?? '') . ' / ' . ($dB['message'] ?? ''));
                $this->ok('The two documents are different files', $dA['status'] === TemplatedDocument::OK && $dB['status'] === TemplatedDocument::OK && $dA['body'] !== $dB['body']);

                $ov = new \App\Modules\Document\Services\PdfOverlay();
                $this->ok('Each document keeps the template\'s page structure (same page count as the official PDF)',
                    $ov->pageCount($dA['body']) === $ov->pageCount((string) service('fileStorage')->absolutePath($realOffer['file_path']) !== '' ? (string) file_get_contents(service('fileStorage')->absolutePath($realOffer['file_path'])) : '')
                    && $ov->pageCount($dB['body']) === $ov->pageCount((string) file_get_contents(service('fileStorage')->absolutePath($realOffer['file_path']))));

                if (! $hasPdftotext) {
                    CLI::write('  SKIP  content checks need the pdftotext tool (poppler/xpdf) on PATH', 'yellow');
                } else {
                    $text = static function (string $bytes): string {
                        $f = tempnam(sys_get_temp_dir(), 'ptx');
                        file_put_contents($f, $bytes);
                        $out = (string) shell_exec('pdftotext -raw ' . escapeshellarg($f) . ' - 2>&1');
                        unlink($f);

                        return $out;
                    };
                    $tA = $text($dA['body']);
                    $tB = $text($dB['body']);
                    $valsA = ['Aarav Mehta', 'Senior Developer', '8,40,000', '03/11/2026', '12 Lake View Road'];
                    $valsB = ['Bhavna Rao', 'Accounts Executive', '4,20,000', '15/12/2026', '48 Hill Crest Avenue'];
                    $has = static fn (string $t, array $vals) => array_values(array_filter($vals, static fn ($v) => ! str_contains($t, $v)));
                    $this->ok('Employee A\'s document contains A\'s name, designation, salary, joining date and address', $has($tA, $valsA) === [], 'missing: ' . implode(', ', $has($tA, $valsA)));
                    $this->ok('Employee B\'s document contains B\'s name, designation, salary, joining date and address', $has($tB, $valsB) === [], 'missing: ' . implode(', ', $has($tB, $valsB)));
                    $this->ok('Neither document contains the other employee\'s data', array_diff($valsB, $has($tA, $valsB)) === [] && array_diff($valsA, $has($tB, $valsA)) === []);
                    $this->ok('The company wording and design text of the template are preserved in both', str_contains($tA, 'Non-Disclosure') && str_contains($tB, 'Non-Disclosure') && str_contains($tA, 'sanvima.com'));
                }

                // archive: keyed to the record (and template), never reused across employees
                $rowA = $generated->where(['document_type' => 'offer_letter', 'record_id' => $empA])->findAll();
                $rowB = $generated->where(['document_type' => 'offer_letter', 'record_id' => $empB])->findAll();
                $this->ok('Each employee has their own archive row and file', count($rowA) === 1 && count($rowB) === 1 && $rowA[0]['file_path'] !== $rowB[0]['file_path'] && $rowA[0]['data_hash'] !== $rowB[0]['data_hash']);
                $rA2 = $svc->forRecord('offer_letter', $empA);
                $rB2 = $svc->forRecord('offer_letter', $empB);
                $this->ok('Repeated generation returns each employee\'s own archived document, never the other\'s', $rA2['body'] === $dA['body'] && $rB2['body'] === $dB['body'] && $rA2['body'] !== $rB2['body']);

                // the same data + a different employee record must not hit the other\'s archive
                $empC = $mkEmp('Aarav Mehta', 'Senior Developer', '2026-11-03', 840000, '12 Lake View Road'); // identical data, different record
                $dC = $svc->forRecord('offer_letter', $empC);
                $this->ok('An employee with identical data but a different record gets their own document row (archive is per record)', $dC['status'] === TemplatedDocument::OK && $generated->where(['document_type' => 'offer_letter', 'record_id' => $empC])->countAllResults() === 1 && $generated->where(['document_type' => 'offer_letter', 'record_id' => $empA])->countAllResults() === 1);
            }

            // ===== templates that cannot carry employee-specific data are refused, not generated unchanged =====
            $staticTpl = $this->template($templates, 1, 'appreciation_letter', $this->docx('Congratulations on your great work. Sanvima.'), 'active', 'docx');
            $profile = $db->table('employee_profiles')->select('id')->where('company_id', 1)->get()->getRowArray();
            if ($profile) {
                $stat = $svc->forRecord('appreciation_letter', (int) $profile['id']);
                $this->ok('A template with no placeholder for the record\'s name is refused with a clear message', $stat['status'] === TemplatedDocument::FAILED && str_contains($stat['message'], 'no place for'), $stat['message'] ?? '');
            }
            $tc = new \App\Modules\Document\Controllers\DocumentTemplateController();
            $rf = new \ReflectionMethod($tc, 'textReviewFlags');
            $rf->setAccessible(true);
            $flags = $rf->invoke($tc, 'offer_letter', 'Static letter without any placeholder');
            $this->ok('Upload review flags such a template as blocking', (bool) array_filter($flags, static fn ($f) => ! empty($f['blocking']) && $f['field'] === 'employee_name'));
            $dropped = 0;
            $kept = (new \App\Modules\Document\Services\PdfOverlay())->sanitizeMap([
                ['page' => 1, 'x' => 10, 'y' => 10, 'w' => 50, 'h' => 10, 'base' => 18, 'size' => 9, 'field' => 'employee_name'],
                ['page' => 1, 'x' => 10, 'y' => 30, 'w' => 50, 'h' => 10, 'base' => 38, 'size' => 9, 'field' => 'not_an_erp_field'],
            ], ['employee_name'], 1, $dropped);
            $this->ok('A mapped field that is not real ERP data is reported as dropped instead of silently ignored', count($kept) === 1 && $dropped === 1);

        } finally {
            $this->cleanup($db, $templates, $generated);
            $db->transRollback();
        }

        CLI::newLine();
        CLI::write("Passed: {$this->pass}   Failed: {$this->fail}", $this->fail ? 'red' : 'green');

        return $this->fail ? EXIT_ERROR : EXIT_SUCCESS;
    }

    private function ok(string $label, bool $cond, string $detail = ''): void
    {
        $cond ? $this->pass++ : $this->fail++;
        CLI::write(($cond ? '  PASS  ' : '  FAIL  ') . $label . (! $cond && $detail !== '' ? "  [{$detail}]" : ''), $cond ? 'green' : 'red');
    }

    /** Inserts a complete onboarding record (all NOT NULL columns without defaults get safe values). */
    private function record($db, int $companyId, string $name): int
    {
        $row = ['company_id' => $companyId, 'candidate_name' => $name, 'designation' => 'Test Engineer', 'joining_date' => '2026-11-01', 'offered_ctc' => 600000, 'status' => 'candidate'];
        foreach ($db->getFieldData('onboarding_records') as $f) {
            if (! $f->nullable && $f->default === null && ! isset($row[$f->name]) && $f->name !== 'id') {
                $row[$f->name] = in_array(strtolower($f->type), ['int', 'bigint', 'tinyint', 'smallint', 'decimal'], true) ? 0 : (str_contains(strtolower($f->type), 'date') ? date('Y-m-d H:i:s') : 'selftest');
            }
        }
        $db->table('onboarding_records')->insert($row);
        $id = (int) $db->insertID();
        $this->createdRecords[] = $id;

        return $id;
    }

    private function template(DocumentTemplateModel $m, int $companyId, string $type, string $html, string $status, string $fileType = 'html'): int
    {
        $path = TemplateWriter::storeBytes($html, $fileType, "document_templates/{$companyId}");
        $id   = (int) $m->insert([
            'company_id' => $companyId, 'document_type' => $type, 'template_name' => 'SELFTEST', 'version' => $m->nextVersion($companyId, $type),
            'status' => $status === 'active' ? 'inactive' : $status, 'file_type' => $fileType, 'file_path' => $path, 'original_name' => 'selftest.' . $fileType, 'file_size' => strlen($html),
        ]);
        $this->createdTemplates[] = $id;
        if ($status === 'active') {
            $m->activate($id);
        }

        return $id;
    }

    /** Minimal valid .docx with one paragraph of text. */
    private function docx(string $text, bool $rawXml = false): string
    {
        $tmp = WRITEPATH . 'temp' . DIRECTORY_SEPARATOR . bin2hex(random_bytes(6)) . '.docx';
        @mkdir(dirname($tmp), 0755, true);
        $z = new \ZipArchive();
        $z->open($tmp, \ZipArchive::CREATE);
        $z->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
        $z->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . ($rawXml ? $text : '<w:p><w:r><w:t xml:space="preserve">' . htmlspecialchars($text, ENT_XML1) . '</w:t></w:r></w:p>') . '</w:body></w:document>');
        $z->close();
        $bytes = (string) file_get_contents($tmp);
        unlink($tmp);

        return $bytes;
    }

    private function cleanup($db, DocumentTemplateModel $templates, GeneratedDocumentModel $generated): void
    {
        $storage = service('fileStorage');
        if ($this->createdRecords !== []) {
            foreach ($generated->whereIn('record_id', $this->createdRecords)->whereIn('document_type', ['offer_letter', 'joining_letter'])->findAll() as $g) {
                $storage->delete($g['file_path']);
                $generated->delete($g['id']);
            }
            $db->table('onboarding_records')->whereIn('id', $this->createdRecords)->delete();
        }
        // clones reference sources: remove newest first
        foreach (array_reverse($this->createdTemplates) as $id) {
            $t = $templates->find($id);
            if ($t) {
                $storage->delete($t['file_path']);
                $templates->delete($id);
            }
        }
    }
}
