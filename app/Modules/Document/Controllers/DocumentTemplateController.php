<?php

namespace App\Modules\Document\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Document\Models\DocumentTemplateModel;
use App\Modules\Document\Services\DocumentTypeRegistry;
use App\Modules\Document\Services\PdfOverlay;
use App\Modules\Document\Services\TemplateRenderer;
use App\Modules\Document\Services\TemplateWriter;

/**
 * Upload / version / activate / download / delete of company-specific
 * document templates. Every query and every write is pinned to the
 * viewer's company scope (companyScopeFor / outOfScope), so one
 * company can never see, use or modify another's templates.
 */
class DocumentTemplateController extends BaseController
{
    private const MAX_BYTES  = ['html' => 2 * 1024 * 1024, 'docx' => 10 * 1024 * 1024, 'pdf' => 10 * 1024 * 1024];
    private const EXTENSIONS = ['html' => 'html', 'htm' => 'html', 'docx' => 'docx', 'pdf' => 'pdf'];
    private const MIMES      = [
        'html' => ['text/html', 'text/plain', 'application/xhtml+xml', 'text/xml'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'pdf'  => ['application/pdf'],
    ];

    protected DocumentTemplateModel $templates;
    protected CompanyModel $companyModel;
    protected TemplateRenderer $renderer;
    protected PdfOverlay $overlay;

    public function __construct()
    {
        $this->templates    = new DocumentTemplateModel();
        $this->companyModel = new CompanyModel();
        $this->renderer     = new TemplateRenderer();
        $this->overlay      = new PdfOverlay();
    }

    public function index()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'document_type', 'status']) ?? []);

        $scope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($scope !== null) {
            $filters['company_id'] = $scope;
        }

        return view('App\Modules\Document\templates/index', [
            'title'     => 'Document Templates',
            'navActive' => 'document-templates',
            'templates' => $this->templates->filtered($filters)->findAll(),
            'companies' => $companies = $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'types'     => DocumentTypeRegistry::TYPES,
            'filters'   => $filters,
            // What each company's users are offered right now (active, usable templates only).
            'overview'  => (new \App\Modules\Document\Services\DocumentAvailability())->overview(array_map('intval', array_column($companies, 'id'))),
        ]);
    }

    public function create()
    {
        $companies = $this->scopedCompanyOptions($this->companyModel->optionsList());
        $type      = (string) $this->request->getGet('document_type');
        $companyId = $this->request->getGet('company_id');

        // Company Admin (single-company scope) always uploads to their own company.
        if (count($companies) === 1) {
            $companyId = $companies[0]['id'];
        }

        $prefill = null;
        if ($companyId && DocumentTypeRegistry::exists($type)) {
            $prefill = $this->templates->activeFor((int) $companyId, $type);
        }

        return view('App\Modules\Document\templates/form', [
            'title'     => 'Upload Template',
            'navActive' => 'document-templates',
            'companies' => $companies,
            'types'     => DocumentTypeRegistry::TYPES,
            'companyId' => $companyId,
            'type'      => $type,
            'prefill'   => $prefill,
            'defs'      => (new DocumentTypeRegistry())->detectionDefs(),
            'asDraft'   => (bool) $this->request->getGet('draft'),
            'nextVersion' => ($companyId && DocumentTypeRegistry::exists($type)) ? $this->templates->nextVersion((int) $companyId, $type) : 1,
        ]);
    }

    public function store()
    {
        $rules = [
            'company_id'    => 'required|integer',
            'document_type' => 'required|in_list[' . implode(',', array_keys(DocumentTypeRegistry::TYPES)) . ']',
            'template_name' => 'required|min_length[2]|max_length[150]',
            'notes'         => 'permit_empty|max_length[500]',
            'workflow'      => 'permit_empty|in_list[' . implode(',', array_keys(DocumentTypeRegistry::WORKFLOW_LABELS)) . ']',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyId = (int) $this->request->getPost('company_id');
        $type      = (string) $this->request->getPost('document_type');
        $workflow  = (string) $this->request->getPost('workflow') ?: null;

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $companyId) || $this->companyModel->find($companyId) === null) {
            return redirect()->to('/documents/templates')->with('error', 'You can only manage your own company.');
        }

        $file = $this->request->getFile('file');

        if ($file === null || ! $file->isValid()) {
            return redirect()->back()->withInput()->with('error', 'Please choose a template file.');
        }
        $fileType = self::EXTENSIONS[strtolower($file->getClientExtension())] ?? null;
        if ($fileType === null) {
            return redirect()->back()->withInput()->with('error', 'Templates must be PDF (.pdf), Word (.docx) or HTML (.html, .htm) files.');
        }
        if ($file->getSize() > self::MAX_BYTES[$fileType]) {
            return redirect()->back()->withInput()->with('error', 'Template exceeds the ' . (self::MAX_BYTES[$fileType] / 1024 / 1024) . 'MB limit for ' . strtoupper($fileType) . ' files.');
        }
        // Server-detected MIME (not the browser's claim) plus a content check,
        // so a renamed file can't masquerade as a template.
        if (! in_array($file->getMimeType(), self::MIMES[$fileType], true)) {
            return redirect()->back()->withInput()->with('error', 'The file contents do not match a valid ' . strtoupper($fileType) . ' file.');
        }

        $content = file_get_contents($file->getRealPath());
        $valid   = match ($fileType) {
            'pdf'   => $content !== false && str_starts_with(ltrim($content), '%PDF-'),
            'docx'  => $content !== false && $this->renderer->isDocx($content),
            default => $content !== false && trim($content) !== '' && mb_check_encoding($content, 'UTF-8'),
        };
        if (! $valid) {
            return redirect()->back()->withInput()->with('error', match ($fileType) {
                'pdf'   => 'The file is not a valid PDF.',
                'docx'  => 'The file is not a valid Word (.docx) document.',
                default => 'The HTML template is empty or not UTF-8 encoded.',
            });
        }

        $fieldMap  = null;
        $review    = [];
        $registry  = new DocumentTypeRegistry();

        if ($fileType === 'pdf') {
            // Generation imports each page of the original, so refuse PDFs
            // that can't be read (encrypted, unsupported format) up front.
            $pages = $this->overlay->pageCount($content);
            if ($pages === null) {
                return redirect()->back()->withInput()->with('error', 'This PDF cannot be used as a template (it may be encrypted or use an unsupported format). Re-save it as a standard PDF and try again.');
            }

            // Fields were detected automatically in the browser while the file was
            // being chosen; the server re-validates everything it receives.
            $raw = json_decode((string) $this->request->getPost('field_map'), true);
            $dropped = 0;
            $map = is_array($raw) ? $this->overlay->sanitizeMap($raw, $registry->fieldKeys($type), $pages, $dropped) : [];
            $review = $this->cleanReview(json_decode((string) $this->request->getPost('field_review'), true));
            if (! is_array($raw)) {
                $review[] = ['field' => 'all', 'label' => '', 'reason' => 'Fields were not analysed automatically. Open Review fields and use Detect automatically.', 'blocking' => true];
            } elseif ($map === []) {
                $review[] = ['field' => 'all', 'label' => '', 'reason' => 'No recognisable text was found in this PDF (is it a scanned image?). Use a text-based PDF, or map the fields by hand.', 'blocking' => true];
            }
            // Server-side check of what the map prints: unknown {{keys}} in text patterns.
            $mapAudit = $this->renderer->audit($this->renderer->pdfKeysText($map), $registry->sampleData($type));
            if ($mapAudit['fixedTitle']) {
                $review[] = ['field' => 'title', 'label' => 'Mr./Ms.', 'reason' => 'A fixed title is printed before the name. Use Detect automatically (it removes fixed titles) or edit the fields.', 'blocking' => true];
            }
            foreach ($mapAudit['unknown'] as $k) {
                $review[] = ['field' => $k, 'label' => '{{' . $k . '}}', 'reason' => 'A mapped text pattern uses a placeholder this document type does not provide.', 'blocking' => true];
            }
            // A detected field that cannot be matched to ERP data is dropped; its sample text would then stay in every document.
            if ($dropped > 0) {
                $review[] = ['field' => 'mapping', 'label' => $dropped . ' field(s)', 'reason' => 'Some detected fields could not be matched to ERP data and were ignored, so their sample text would stay unchanged in every document.', 'blocking' => true];
            }
            if (is_array($raw) && array_intersect(DocumentTypeRegistry::identityPlaceholders($type), $this->renderer->placeholdersIn($this->renderer->pdfKeysText($map))) === []) {
                $review[] = ['field' => DocumentTypeRegistry::identityPlaceholders($type)[0] ?? 'identity', 'label' => '', 'reason' => 'No place for the record\'s own name/number was found in this template, so documents would be identical for every record.', 'blocking' => true];
            }
            $fieldMap = json_encode($map);
        } else {
            // HTML / DOCX use {{placeholders}}: audit them (unknown keys, fixed gendered wording).
            $review = $this->textReviewFlags($type, $fileType === 'docx' ? $this->renderer->docxText($content) : $content);
        }

        $blocking = array_values(array_filter($review, static fn ($f) => ! empty($f['blocking'])));
        // "Replace as new draft version": never activates by itself; needs preview + explicit activation.
        $asDraft  = (bool) $this->request->getPost('as_draft');

        $path = service('fileStorage')->store($file, "document_templates/{$companyId}");

        $id = $this->templates->insert([
            'company_id'    => $companyId,
            'document_type' => $type,
            'workflow'      => $workflow,
            'template_name' => $this->request->getPost('template_name'),
            'version'       => $this->templates->nextVersion($companyId, $type),
            'status'        => $asDraft ? 'draft' : 'inactive',
            'file_type'     => $fileType,
            'field_map'     => $fieldMap,
            'field_review'  => $review === [] ? null : json_encode($review),
            'file_path'     => $path,
            'original_name' => $file->getClientName(),
            'file_size'     => $file->getSize(),
            'notes'         => $this->request->getPost('notes') ?: null,
            'created_by'    => $this->currentUserId(),
        ]);

        // Activated straight away (the plug-and-play path) when it's the first
        // template for this company + type, or when asked to; but never while
        // detection findings still need a human look.
        $activated = false;
        if (! $asDraft && $blocking === [] && ($this->request->getPost('activate') || $this->templates->activeFor($companyId, $type) === null)) {
            $this->templates->activate((int) $id);
            $activated = true;
        }

        $version = $this->templates->find($id)['version'];
        $label   = DocumentTypeRegistry::label($type);
        $this->logActivity('document_template', 'create', (int) $id, "Uploaded {$label} template v{$version}" . ($fileType === 'pdf' ? ' (' . count($map) . ' fields detected)' : ''));

        if ($blocking !== []) {
            return redirect()->to('/documents/templates/' . $id)->with('error', "{$label} v{$version} uploaded, but " . count($blocking) . ' item(s) need your review before it can be used. Nothing was activated; existing documents keep working as before.');
        }

        if ($asDraft) {
            return redirect()->to('/documents/templates/' . $id)->with('success', "{$label} v{$version} saved as a draft. Preview it, then activate it.");
        }

        $msg = "{$label} v{$version} uploaded" . ($activated ? ' and active — existing ' . $label . ' actions now use it.' : '. Activate it when you are ready.');
        if ($fileType === 'pdf') {
            $msg = "{$label} v{$version}: " . count($map) . ' field(s) detected automatically' . ($activated ? ' and the template is active — existing ' . $label . ' actions now use it.' : '. Activate it when you are ready.');
        }

        return redirect()->to('/documents/templates')->with('success', $msg);
    }

    /** Preview of one specific template (draft, inactive or active). A successful preview unlocks activation. */
    public function test(int $id)
    {
        $tpl = $this->scopedTemplate($id);
        if ($tpl === null) {
            return redirect()->to('/documents/templates')->with('error', 'Template not found.');
        }

        $svc = new \App\Modules\Document\Services\TemplatedDocument();
        $doc = $svc->previewTemplate($tpl);

        if ($doc['status'] !== $svc::OK) {
            return redirect()->back()->with('error', $doc['message']);
        }

        // The preview just rendered the CURRENT stored file; remember that.
        $this->templates->update($id, ['previewed_at' => date('Y-m-d H:i:s')]);

        return $svc->respond($this->response, $doc, true);
    }

    // ---- clone ---------------------------------------------------------------------------

    public function cloneForm()
    {
        $scope = session('roleSlug') === 'super_admin' ? null : $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);

        return view('App\Modules\Document\templates/clone', [
            'title'     => 'Clone Template',
            'navActive' => 'document-templates',
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'types'     => DocumentTypeRegistry::TYPES,
            // Only templates the viewer may already see: a Company Admin's list
            // is their own company's, so another company's format is never exposed.
            'sources'   => $this->templates->filtered($scope !== null ? ['company_id' => $scope] : [])->findAll(),
            'sourceId'  => (int) $this->request->getGet('source'),
        ]);
    }

    public function cloneStore()
    {
        $back = redirect()->back()->withInput();

        // Super Admin may clone any company's format into another company; everyone else
        // only from templates of their own company (another company's format is never exposed).
        $sourceId = (int) $this->request->getPost('source_template_id');
        $source   = session('roleSlug') === 'super_admin' ? $this->templates->find($sourceId) : $this->scopedTemplate($sourceId);
        if ($source === null) {
            return $back->with('error', 'Choose a source template you have access to.');
        }
        if ($source['document_type'] !== (string) $this->request->getPost('document_type')) {
            return $back->with('error', 'The source template is not a ' . DocumentTypeRegistry::label((string) $this->request->getPost('document_type')) . ' template.');
        }

        // Target company is authorised server-side; the posted id alone is never trusted.
        $targetId = (int) $this->request->getPost('company_id');
        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $targetId) || $this->companyModel->find($targetId) === null) {
            return $back->with('error', 'You can only create templates for your own company.');
        }

        $sourceCompany = $this->companyModel->find($source['company_id'])['name'] ?? 'another company';
        $id = (new TemplateWriter())->cloneTo($source, $targetId, $sourceCompany, $this->currentUserId());
        if ($id === null) {
            return $back->with('error', 'The source template file is missing from storage.');
        }

        $this->logActivity('document_template', 'clone', (int) $id, "Cloned template #{$source['id']} into company #{$targetId}");

        return redirect()->to('/documents/templates/' . $id)->with('success', 'Independent draft created for the target company. Edit it (HTML), or Replace file (Word/PDF), review the flagged items, preview it, then activate it. The original template and its issued documents were not changed.');
    }

    // ---- PDF -> editable HTML ------------------------------------------------------------

    /**
     * A fixed-layout PDF cannot be edited, so its text is rebuilt as an editable HTML template (done in the browser with
     * pdf.js, using the PDF's detected fields so every placeholder stays bound to its data) for the chosen company.
     */
    public function toHtml(int $id)
    {
        $tpl = session('roleSlug') === 'super_admin' ? $this->templates->find($id) : $this->scopedTemplate($id);
        if ($tpl === null || $tpl['file_type'] !== 'pdf') {
            return redirect()->to('/documents/templates')->with('error', 'Only PDF templates can be rebuilt as editable HTML.');
        }

        return view('App\Modules\Document\templates/convert', [
            'title'     => 'Make Editable — ' . $tpl['template_name'],
            'navActive' => 'document-templates',
            'template'  => $tpl,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'map'       => json_decode((string) $tpl['field_map'], true) ?: [],
        ]);
    }

    public function toHtmlStore(int $id)
    {
        $back = redirect()->back()->withInput();
        $src  = session('roleSlug') === 'super_admin' ? $this->templates->find($id) : $this->scopedTemplate($id);
        if ($src === null || $src['file_type'] !== 'pdf') {
            return $back->with('error', 'Choose a PDF template you have access to.');
        }

        $targetId = (int) $this->request->getPost('company_id');
        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $targetId) || $this->companyModel->find($targetId) === null) {
            return $back->with('error', 'You can only create templates for your own company.');
        }

        $html = str_replace(["\r\n", "\r"], "\n", (string) $this->request->getPost('html'));
        if (trim(strip_tags($html)) === '' || ! mb_check_encoding($html, 'UTF-8') || strlen($html) > self::MAX_BYTES['html']) {
            return $back->with('error', 'The rebuilt template is empty or too large (2MB limit). Wait for the preview to finish loading and try again.');
        }
        $html  = $this->renderer->joinHtml($this->renderer->splitHtml($this->renderer->sanitize($html)));
        $flags = array_merge($this->textReviewFlags($src['document_type'], $html), [[
            'field'    => 'rebuilt',
            'label'    => 'from PDF',
            'reason'   => 'Rebuilt from a PDF: text, bold, alignment, spacing and simple tables are carried over; logos/images, lines, shapes and exact positions are not. Compare the preview with the original and adjust.',
            'blocking' => false,
        ]]);

        $name   = trim((string) $this->request->getPost('template_name')) ?: ($src['template_name'] . ' (editable)');
        $newId  = (new TemplateWriter())->fromHtml($src, $html, $targetId, $name, $flags, $this->currentUserId());
        $this->logActivity('document_template', 'to_html', $newId, "Rebuilt template #{$src['id']} as editable HTML for company #{$targetId}");

        return redirect()->to('/documents/templates/' . $newId . '/edit?preview=1')->with('success', 'Editable HTML draft created. Adjust the header, body and footer, then preview and activate it. The original PDF template was not changed.');
    }

    // ---- HTML editor ---------------------------------------------------------------------

    /** Header / body / footer editor for HTML templates that are not live and have no issued documents. */
    public function edit(int $id)
    {
        $tpl = $this->editableOrNull($id);
        if (is_string($tpl)) {
            return redirect()->to('/documents/templates/' . $id)->with('error', $tpl);
        }

        $html = $this->renderer->loadStored($tpl['file_path']);
        if ($html === null) {
            return redirect()->to('/documents/templates')->with('error', 'The template file is missing from storage.');
        }

        return view('App\Modules\Document\templates/edit', [
            'title'     => 'Edit Template — ' . $tpl['template_name'],
            'navActive' => 'document-templates',
            'template'  => $tpl,
            'parts'     => $this->renderer->splitHtml($html),
            'fields'    => (new DocumentTypeRegistry())->fieldKeys($tpl['document_type']),
            'preview'   => (bool) $this->request->getGet('preview'),
        ]);
    }

    public function saveEdit(int $id)
    {
        $tpl = $this->editableOrNull($id);
        if (is_string($tpl)) {
            return redirect()->to('/documents/templates/' . $id)->with('error', $tpl);
        }

        $current = $this->renderer->loadStored($tpl['file_path']);
        if ($current === null) {
            return redirect()->to('/documents/templates')->with('error', 'The template file is missing from storage.');
        }

        $parts = $this->renderer->splitHtml($current);
        foreach (['header', 'body', 'footer'] as $k) {
            $parts[$k] = str_replace(["\r\n", "\r"], "\n", (string) $this->request->getPost($k));
        }
        $html = $this->renderer->joinHtml($parts);

        if (trim($parts['body']) === '' || ! mb_check_encoding($html, 'UTF-8') || strlen($html) > self::MAX_BYTES['html']) {
            return redirect()->back()->withInput()->with('error', 'The body must not be empty, and the template must stay under 2MB.');
        }

        // New file for this template only; any edit puts a non-active template back to draft.
        (new TemplateWriter())->replaceHtml($tpl, $html);
        $flags = $this->textReviewFlags($tpl['document_type'], $html);
        $this->templates->update($id, ['field_review' => $flags === [] ? null : json_encode($flags)]);
        $this->logActivity('document_template', 'edit', $id, 'Edited HTML template v' . $tpl['version']);

        $problems = array_filter($flags, static fn ($f) => ! empty($f['blocking']));
        if ($problems) {
            return redirect()->to('/documents/templates/' . $id . '/edit')->with('error', 'Saved as draft, but it cannot be used yet: ' . implode(' | ', array_map(static fn ($f) => ($f['label'] !== '' ? $f['label'] . ' — ' : '') . $f['reason'], $problems)));
        }

        return redirect()->to('/documents/templates/' . $id . '/edit?preview=1')->with('success', 'Saved as draft. Check the preview, then activate it from the template page.');
    }

    /**
     * Findings for an HTML/DOCX template's text. All are blocking: a template that prints a
     * placeholder this document type doesn't provide, or fixed gendered wording that would be
     * wrong for other people, must be corrected or explicitly accepted before use.
     */
    private function textReviewFlags(string $type, string $text): array
    {
        $registry = new DocumentTypeRegistry();
        $data     = $registry->sampleData($type);
        $audit    = $this->renderer->audit($text, $data);
        $flags    = [];

        if (array_intersect(DocumentTypeRegistry::identityPlaceholders($type), $this->renderer->placeholdersIn($text)) === []) {
            $flags[] = ['field' => DocumentTypeRegistry::identityPlaceholders($type)[0] ?? 'identity', 'label' => '', 'reason' => 'This template has no placeholder for the record\'s own name/number, so every document would be identical.', 'blocking' => true, 'fatal' => true];
        }
        if ($audit['fixedTitle']) {
            $flags[] = ['field' => 'title', 'label' => 'Mr./Ms.', 'reason' => 'A fixed title is printed before the name. Use {{salutation_name}}; titles are never guessed.', 'blocking' => true];
        }
        foreach ($audit['unknown'] as $k) {
            $flags[] = ['field' => $k, 'label' => '{{' . $k . '}}', 'reason' => 'This placeholder is not available for this document type; documents using it would be blocked.', 'blocking' => true, 'fatal' => true];
        }
        if (preg_match('/\{\{\s*(\w+)\s*\}\}(?:\s|&nbsp;|<br\s*\/?>|[,;])*\{\{\s*\1\s*\}\}/i', $text, $dup)) {
            $flags[] = ['field' => $dup[1], 'label' => '{{' . $dup[1] . '}} {{' . $dup[1] . '}}', 'reason' => 'The same placeholder is repeated back to back, so the value would print twice. Remove one.', 'blocking' => true];
        }
        if ($this->renderer->placeholdersIn($text) === []) {
            $flags[] = ['field' => 'all', 'label' => '', 'reason' => 'No {{placeholders}} found; every document would print identically.', 'blocking' => true];
        }
        $words = $this->renderer->genderedWords($text);
        if ($words !== []) {
            $flags[] = ['field' => 'wording', 'label' => implode(', ', $words), 'reason' => 'Fixed gendered wording/titles in the template will not change per person. Use {{salutation_name}} / neutral wording, or accept if intended.', 'blocking' => true];
        }

        return $flags;
    }

    /** Template row, or an error string explaining why it cannot be edited. */
    private function editableOrNull(int $id): array|string
    {
        $tpl = $this->scopedTemplate($id);
        if ($tpl === null) {
            return 'Template not found.';
        }
        if ($tpl['file_type'] !== 'html') {
            return 'Only HTML templates can be edited here. Download a Word/PDF template, change it, and upload it as a new draft version.';
        }
        if ($tpl['status'] === 'active') {
            return 'An active template cannot be edited directly. Create an editable draft copy first, preview it, then activate it.';
        }
        if ($this->templates->usageCount($id) > 0) {
            return 'Documents have already been issued with this version, so it is locked. Create an editable draft copy instead.';
        }

        return $tpl;
    }

    /** Marks the blocking findings as reviewed and (optionally) activates the template. */
    public function accept(int $id)
    {
        $tpl = $this->scopedTemplate($id);
        if ($tpl === null) {
            return redirect()->to('/documents/templates')->with('error', 'Template not found.');
        }

        // Findings that make generation impossible (unknown placeholder, no place for the record's name) can't be "accepted": fix the template.
        $kept = array_values(array_filter(json_decode((string) $tpl['field_review'], true) ?: [], static fn ($f) => empty($f['blocking']) || ! empty($f['fatal'])));
        if (array_filter($kept, static fn ($f) => ! empty($f['fatal']))) {
            return redirect()->to('/documents/templates/' . $id)->with('error', 'These cannot be accepted because documents could not be generated: ' . implode('; ', array_map(static fn ($f) => ($f['label'] !== '' ? $f['label'] . ' — ' : '') . $f['reason'], array_filter($kept, static fn ($f) => ! empty($f['fatal'])))));
        }
        $this->templates->update($id, ['field_review' => $kept === [] ? null : json_encode($kept)]);
        $this->logActivity('document_template', 'accept', $id, 'Accepted review findings for template v' . $tpl['version']);

        $activate = (bool) $this->request->getPost('activate') && $this->previewGate($tpl) === null;
        if ($activate) {
            $this->templates->activate($id);
        }

        return redirect()->to('/documents/templates/' . $id)->with('success', 'Review accepted' . ($activate ? ' and the template is now active.' : ($this->request->getPost('activate') ? '. Preview it, then activate it.' : '.')));
    }

    /** Shape/size-limits the findings list that came from the browser. */
    private function cleanReview($raw): array
    {
        $out = [];
        foreach (is_array($raw) ? array_slice($raw, 0, 60) : [] as $f) {
            if (! is_array($f)) {
                continue;
            }
            $out[] = [
                'field'    => mb_substr(preg_replace('/[^\w.\-]/', '', (string) ($f['field'] ?? '')), 0, 50),
                'label'    => mb_substr(strip_tags((string) ($f['label'] ?? '')), 0, 80),
                'reason'   => mb_substr(strip_tags((string) ($f['reason'] ?? '')), 0, 300),
                'blocking' => ! empty($f['blocking']),
            ];
        }

        return $out;
    }

    /** Drafts (clones, replacements, edits) may only go live after a successful preview. */
    private function previewGate(array $tpl): ?string
    {
        return ($tpl['status'] === 'draft' && empty($tpl['previewed_at']))
            ? 'Preview this draft (Preview button) before activating it.'
            : null;
    }

    public function activate(int $id)
    {
        $tpl = $this->scopedTemplate($id);
        if ($tpl === null) {
            return redirect()->to('/documents/templates')->with('error', 'Template not found.');
        }
        if (\App\Modules\Document\Services\TemplatedDocument::blockingFlags($tpl) !== []) {
            return redirect()->to('/documents/templates/' . $id)->with('error', 'Review or accept the flagged fields before activating this template.');
        }

        if (($gate = $this->previewGate($tpl)) !== null) {
            return redirect()->to('/documents/templates/' . $id)->with('error', $gate);
        }

        $this->templates->activate($id);
        $this->logActivity('document_template', 'activate', $id, 'Activated template v' . $tpl['version']);

        return redirect()->to('/documents/templates')->with('success', 'v' . $tpl['version'] . ' is now the active ' . DocumentTypeRegistry::label($tpl['document_type']) . ' template.');
    }

    public function deactivate(int $id)
    {
        $tpl = $this->scopedTemplate($id);
        if ($tpl === null) {
            return redirect()->to('/documents/templates')->with('error', 'Template not found.');
        }

        $this->templates->update($id, ['status' => 'inactive']);
        $this->logActivity('document_template', 'deactivate', $id, 'Deactivated template v' . $tpl['version']);

        return redirect()->to('/documents/templates')->with('success', 'Template deactivated. No documents of this type can be generated until one is active.');
    }

    public function view(int $id)
    {
        $tpl = $this->scopedTemplate($id);
        if ($tpl === null) {
            return redirect()->to('/documents/templates')->with('error', 'Template not found.');
        }

        $html = $this->renderer->loadStored($tpl['file_path']);
        if ($html === null) {
            return redirect()->to('/documents/templates')->with('error', 'The template file is missing from storage.');
        }

        $text = match ($tpl['file_type']) {
            'html'  => $html,
            'docx'  => $this->renderer->docxText($html),
            default => '',
        };

        return view('App\Modules\Document\templates/view', [
            'title'        => 'Template — ' . $tpl['template_name'],
            'navActive'    => 'document-templates',
            'template'     => $tpl,
            'html'         => $tpl['file_type'] === 'html' ? $this->renderer->sanitize($html) : '',
            'docxText'     => $tpl['file_type'] === 'docx' ? $text : '',
            'placeholders' => $this->renderer->placeholdersIn($text),
            'flags'        => json_decode((string) $tpl['field_review'], true) ?: [],
            'blocking'     => \App\Modules\Document\Services\TemplatedDocument::blockingFlags($tpl) !== [],
            'requiredFields' => DocumentTypeRegistry::TYPES[$tpl['document_type']]['required'] ?? [],
            'usage'        => $this->templates->usageCount($id),
            'source'       => $tpl['source_template_id'] ? $this->templates->filtered([])->where('document_templates.id', $tpl['source_template_id'])->first() : null,
        ]);
    }

    /** Visual mapper: click a sample value on the PDF, pick the ERP field that replaces it. */
    public function map(int $id)
    {
        $tpl = $this->scopedTemplate($id);
        if ($tpl === null || $tpl['file_type'] !== 'pdf') {
            return redirect()->to('/documents/templates')->with('error', 'Only PDF templates have field mapping.');
        }

        return view('App\Modules\Document\templates/map', [
            'title'     => 'Map Fields — ' . $tpl['template_name'],
            'navActive' => 'document-templates',
            'template'  => $tpl,
            'fields'    => (new DocumentTypeRegistry())->fieldKeys($tpl['document_type']),
            'def'       => (new DocumentTypeRegistry())->detectionDefs()[$tpl['document_type']] ?? null,
            'map'       => json_decode((string) $tpl['field_map'], true) ?: [],
            'typeLabel' => DocumentTypeRegistry::label($tpl['document_type']),
        ]);
    }

    public function saveMap(int $id)
    {
        $tpl = $this->scopedTemplate($id);
        if ($tpl === null || $tpl['file_type'] !== 'pdf') {
            return redirect()->to('/documents/templates')->with('error', 'Template not found.');
        }

        $raw = json_decode((string) $this->request->getPost('field_map'), true);
        if (! is_array($raw)) {
            return redirect()->back()->with('error', 'Could not read the field mapping.');
        }

        $pdf   = $this->renderer->loadStored($tpl['file_path']);
        $pages = $pdf === null ? null : $this->overlay->pageCount($pdf);
        if ($pages === null) {
            return redirect()->back()->with('error', 'The template PDF could not be read.');
        }

        $dropped = 0;
        $map = $this->overlay->sanitizeMap($raw, (new DocumentTypeRegistry())->fieldKeys($tpl['document_type']), $pages, $dropped);
        $review = [];
        if ($dropped > 0) {
            $review[] = ['field' => 'mapping', 'label' => $dropped . ' field(s)', 'reason' => 'Some fields could not be matched to ERP data and were ignored.', 'blocking' => true];
        }
        if (array_intersect(DocumentTypeRegistry::identityPlaceholders($tpl['document_type']), $this->renderer->placeholdersIn($this->renderer->pdfKeysText($map))) === []) {
            $review[] = ['field' => DocumentTypeRegistry::identityPlaceholders($tpl['document_type'])[0] ?? 'identity', 'label' => '', 'reason' => 'No place for the record\'s own name/number is mapped, so documents would be identical for every record.', 'blocking' => true];
        }
        $update = ['field_map' => json_encode($map), 'field_review' => $review === [] ? null : json_encode($review), 'previewed_at' => null];
        if ($tpl['status'] !== 'active') {
            $update['status'] = 'draft'; // changed output: preview again before it can go live
        }
        $this->templates->update($id, $update);
        $this->logActivity('document_template', 'map', $id, 'Saved ' . count($map) . ' field mappings for template v' . $tpl['version']);

        return redirect()->to('/documents/templates')->with('success', count($map) . ' field(s) saved.' . ($tpl['status'] === 'active' ? '' : ' Preview it, then activate it.'));
    }

    public function download(int $id)
    {
        $tpl = $this->scopedTemplate($id);
        if ($tpl === null) {
            return redirect()->to('/documents/templates')->with('error', 'Template not found.');
        }

        $body = $this->renderer->loadStored($tpl['file_path']);
        if ($body === null) {
            return redirect()->to('/documents/templates')->with('error', 'The template file is missing from storage.');
        }

        // ?inline=1 lets the view page embed the original PDF as-is.
        if ($tpl['file_type'] === 'pdf' && $this->request->getGet('inline')) {
            return $this->response->setContentType('application/pdf')
                ->setHeader('Content-Disposition', 'inline; filename="' . url_title($tpl['template_name'], '-', true) . '.pdf"')
                ->setBody($body);
        }

        return $this->response->download($tpl['original_name'], $body);
    }

    public function delete(int $id)
    {
        $tpl = $this->scopedTemplate($id);
        if ($tpl === null) {
            return redirect()->to('/documents/templates')->with('error', 'Template not found.');
        }
        if ($tpl['status'] === 'active') {
            return redirect()->to('/documents/templates')->with('error', 'Deactivate this template before deleting it.');
        }
        // Templates that produced documents are kept (history/audit);
        // deactivating is enough to stop using them.
        if ($this->templates->usageCount($id) > 0) {
            return redirect()->to('/documents/templates')->with('error', 'This template version has generated documents and cannot be deleted. Leave it inactive instead.');
        }

        service('fileStorage')->delete($tpl['file_path']);
        $this->templates->delete($id);
        $this->logActivity('document_template', 'delete', $id, 'Deleted template v' . $tpl['version']);

        return redirect()->to('/documents/templates')->with('success', 'Template deleted.');
    }

    /** Template by id, or null when missing / outside the viewer's company. */
    private function scopedTemplate(int $id): ?array
    {
        $tpl = $this->templates->find($id);

        return ($tpl === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $tpl['company_id'])) ? null : $tpl;
    }
}
