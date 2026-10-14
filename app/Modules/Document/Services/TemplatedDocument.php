<?php

namespace App\Modules\Document\Services;

use App\Modules\Document\Models\DocumentTemplateModel;
use App\Modules\Document\Models\GeneratedDocumentModel;
use CodeIgniter\Files\File;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The one entry point existing ERP actions (offer letter, payslip,
 * invoice, relieving letter, ...) call to produce a company-formatted
 * document:
 *
 *     $doc = (new TemplatedDocument())->forRecord('payslip', $id);
 *     if ($doc['status'] === TemplatedDocument::OK) { return $svc->respond($this->response, $doc); }
 *
 * The template is chosen ONLY by (the record's own company_id, document
 * type) -> that company's active version. There is no fallback to any other
 * company's template. When the company has no active template the status
 * is NONE and the caller keeps its built-in output.
 *
 * Issued documents are kept: while the underlying ERP data is unchanged the
 * stored file is served again, so a later template version never rewrites
 * a document that was already generated.
 */
class TemplatedDocument
{
    public const OK       = 'ok';
    public const NONE     = 'none';      // no active template for this company + type
    public const NOT_FOUND = 'not_found';
    public const INCOMPLETE = 'incomplete'; // required ERP values missing
    public const FAILED   = 'failed';    // template unusable (unmapped / unreadable)

    public const PREVIEW_REAL   = 'PREVIEW - NOT AN ISSUED DOCUMENT';
    public const PREVIEW_SAMPLE = 'PREVIEW - SAMPLE DATA - NOT AN ISSUED DOCUMENT';

    private DocumentTypeRegistry $registry;
    private DocumentTemplateModel $templates;
    private GeneratedDocumentModel $generated;
    private TemplateRenderer $renderer;
    private PdfOverlay $overlay;

    public function __construct()
    {
        $this->registry  = new DocumentTypeRegistry();
        $this->templates = new DocumentTemplateModel();
        $this->generated = new GeneratedDocumentModel();
        $this->renderer  = new TemplateRenderer();
        $this->overlay   = new PdfOverlay();
    }

    /** True when $companyId has an active template for $type (e.g. to show/hide a button). */
    public function hasActive(string $type, int $companyId): bool
    {
        return $this->templates->activeFor($companyId, $type) !== null;
    }

    /**
     * Builds (or re-serves) the document for one ERP record.
     *
     * $testTemplate previews one specific template (admin "test" action): it must
     * belong to the record's company, skips the review block and is not archived.
     *
     * @return array{status:string, message:string, body?:string, ext?:string, title?:string}
     */
    public function forRecord(string $type, int $recordId, ?int $userId = null, ?array $testTemplate = null): array
    {
        $label = DocumentTypeRegistry::label($type);
        $ctx   = $this->registry->resolve($type, $recordId);

        if ($ctx === null) {
            return $this->result(self::NOT_FOUND, 'Record not found.');
        }

        // Unchanged data -> serve what was already issued. This comes first so a document that was issued
        // stays retrievable, byte for byte, even if its template is later deactivated or replaced.
        $stable = $ctx['data'];
        unset($stable['current_date']); // changes daily, not a data change
        $hash = sha1(json_encode($stable));
        $prev = $this->generated->where(['company_id' => $ctx['company_id'], 'document_type' => $type, 'record_id' => $recordId, 'data_hash' => $hash])
            ->orderBy('id', 'DESC')->first();
        $template = $testTemplate ?? $this->templates->activeFor($ctx['company_id'], $type);

        // The record's company decides the template; a template of any other
        // company is never used (also guards test mode).
        if ($template !== null && (int) $template['company_id'] !== (int) $ctx['company_id']) {
            return $this->result(self::FAILED, "The {$label} template does not belong to this record's company.");
        }
        // An archived document is reused only for the SAME record, the SAME data and the SAME template as it was
        // built from (same id, not changed since). A new version, a replaced file or a re-mapped template builds a
        // fresh document; the old archive row and file stay untouched. With no active template at all, the issued
        // document is still retrievable as it was.
        if ($testTemplate === null && $prev !== null) {
            $current = $template === null
                || ((int) $prev['template_id'] === (int) $template['id'] && strtotime((string) $prev['created_at']) >= strtotime((string) ($template['updated_at'] ?? '1970-01-01')));
            if ($current && ($body = $this->renderer->loadStored($prev['file_path'])) !== null) {
                return $this->result(self::OK, '', ['body' => $body, 'ext' => $prev['file_type'], 'title' => $ctx['title']]);
            }
        }

        if ($template === null) {
            return $this->result(self::NONE, "No active {$label} template is configured for this company. Ask an admin to upload one under Document Templates.", ['strict' => DocumentTypeRegistry::isStrict($type)]);
        }

        $missing = array_values(array_filter(
            DocumentTypeRegistry::TYPES[$type]['required'],
            static fn (string $k) => ! isset($ctx['data'][$k]) || $ctx['data'][$k] === ''
        ));
        if ($missing !== []) {
            return $this->result(self::INCOMPLETE, MissingDetails::documentMessage($label, $missing), ['missing_keys' => $missing, 'doc_type' => $type, 'record_id' => $recordId, 'company_id' => $ctx['company_id']]);
        }

        if ($testTemplate === null && $this->blockingFlags($template) !== []) {
            return $this->result(self::FAILED, "The active {$label} template needs review before it can be used: " . implode('; ', array_map(static fn ($f) => $f['field'] . ' — ' . $f['reason'], $this->blockingFlags($template))));
        }

        $built = $this->renderFile($template, $ctx['data'], $label, $testTemplate !== null ? self::PREVIEW_REAL : null);
        if ($built['status'] !== self::OK) {
            if (! empty($built['missing_keys'])) {
                $built['message']    = MissingDetails::documentMessage($label, $built['missing_keys']) . ($built['missing_rows'] ?? '');
                $built['doc_type']   = $type;
                $built['record_id']  = $recordId;
                $built['company_id'] = $ctx['company_id'];
            }

            return $built;
        }
        $body = $built['body'];
        $ext  = $built['ext'];

        if ($testTemplate === null) {
            $this->save($ctx, $template, $type, $recordId, $hash, $ext, $body, $userId);
        }

        return $this->result(self::OK, '', ['body' => $body, 'ext' => $ext, 'title' => ($testTemplate !== null ? 'PREVIEW - ' : '') . $ctx['title']]);
    }

    /**
     * Template file + data -> document bytes (or a failure result).
     *
     * Every placeholder the template uses is audited against $data first: an unknown, empty or
     * unmapped placeholder blocks generation instead of being printed blank or stale.
     * $previewLabel non-null marks the output as a preview (visible banner on every format).
     */
    /** " The template has {{Ms}}, {{1/11/26}} ..." when it contains braces that are not data fields (typed values instead of field names). */
    private function unknownHint(string $text, array $data): string
    {
        preg_match_all('/\{\{\s*([^{}#\/][^{}]*?)\s*\}\}/', $text, $m);
        $bad = array_values(array_unique(array_filter($m[1], static fn ($k) => ! array_key_exists($k, $data))));

        return $bad === [] ? '' : ' It contains {{' . implode('}}, {{', array_slice($bad, 0, 5)) . '}}, which are not data fields: a placeholder must be a field name such as {{salutation_name}} or {{joining_date}}, not a typed value.';
    }

    private function renderFile(array $template, array $data, string $label, ?string $previewLabel = null): array
    {
        $source = $this->renderer->loadStored($template['file_path']);
        if ($source === null) {
            return $this->result(self::FAILED, "The {$label} template file is missing from storage. Please re-upload it.");
        }

        $map  = $template['file_type'] === 'pdf' ? (json_decode((string) $template['field_map'], true) ?: []) : [];
        $text = match ($template['file_type']) {
            'docx'  => $this->renderer->docxText($source),
            'pdf'   => $this->renderer->pdfKeysText($map),
            default => $source,
        };
        if ($template['file_type'] === 'pdf' && $map === []) {
            return $this->result(self::FAILED, "The {$label} template has no mapped fields yet. Open Review fields under Document Templates.");
        }

        $identity = DocumentTypeRegistry::identityPlaceholders($template['document_type']);
        if ($identity !== [] && array_intersect($identity, $this->renderer->placeholdersIn($text)) === []) {
            return $this->result(self::FAILED, "Cannot generate the {$label}: this template has no place for the record's {$identity[0]}, so every document would come out the same. Open Document Templates and re-upload it or use Review fields." . $this->unknownHint($text, $data));
        }

        $audit = $this->renderer->audit($text, $data);
        if ($audit['fixedTitle']) {
            return $this->result(self::FAILED, "Cannot generate the {$label}: the template prints a fixed title (Mr./Ms./Dr.) before the name. Titles are never guessed; use {{salutation_name}} (name, plus a title only if one is stored). Re-run Detect automatically or edit the template.");
        }
        if ($audit['unknown'] !== []) {
            return $this->result(self::FAILED, "Cannot generate the {$label}: the template uses " . implode(', ', array_map(static fn ($k) => '{{' . $k . '}}', $audit['unknown'])) . ', which this document type does not provide. Ask an admin to correct the template.');
        }
        if ($audit['empty'] !== [] || $audit['emptySections'] !== []) {
            $what = array_merge(array_map(static fn ($k) => str_replace('_', ' ', $k), $audit['empty']), array_map(static fn ($k) => $k . ' (no rows)', $audit['emptySections']));

            $rows = $audit['emptySections'] === [] ? '' : ' Also missing rows for: ' . implode(', ', $audit['emptySections']) . '.';
            if ($audit['empty'] === []) {
                return $this->result(self::INCOMPLETE, "Cannot generate the {$label}: no rows for " . implode(', ', $audit['emptySections']) . ' (used by the template). Complete the record first.', ['missing' => $what]);
            }

            return $this->result(self::INCOMPLETE, '', ['missing' => $what, 'missing_keys' => $audit['empty'], 'missing_rows' => $rows]);
        }

        try {
            switch ($template['file_type']) {
                case 'docx':
                    $body = $this->renderer->docxFill($source, $data);
                    if ($body !== null && $previewLabel !== null) {
                        $body = $this->renderer->markPreviewDocx($body, $previewLabel);
                    }
                    $ext = 'docx';
                    break;
                case 'pdf':
                    $body = $this->overlay->fill($source, $map, $data, $previewLabel);
                    $ext  = 'pdf';
                    break;
                default:
                    $html = $this->renderer->fill($source, $data);
                    $body = $this->renderer->toPdf($previewLabel !== null ? $this->renderer->markPreviewHtml($html, $previewLabel) : $html);
                    $ext  = 'pdf';
            }
        } catch (\Throwable $e) {
            log_message('error', 'Template render failed: ' . $e->getMessage());

            return $this->result(self::FAILED, "The {$label} template could not be processed.");
        }

        if ($body === null || $body === '') {
            return $this->result(self::FAILED, "The {$label} template could not be processed.");
        }

        return $this->result(self::OK, '', ['body' => $body, 'ext' => $ext]);
    }

    /**
     * Admin preview of ONE specific template (draft, inactive or active): rendered
     * with a complete real record of the template's own company when there is one,
     * otherwise with obvious sample values. Never archived.
     */
    public function previewTemplate(array $template): array
    {
        $type  = $template['document_type'];
        $label = DocumentTypeRegistry::label($type);

        foreach (array_slice($this->registry->records($type, (int) $template['company_id']), 0, 40) as $record) {
            $doc = $this->forRecord($type, (int) $record['id'], null, $template);
            if ($doc['status'] === self::OK) {
                return $doc;
            }
        }

        // No complete record yet: sample EMPLOYEE data, but the template's own company's real details.
        $sample = $this->registry->sampleData($type);
        foreach ($this->registry->companyDetails((int) $template['company_id']) as $k => $v) {
            if (array_key_exists($k, $sample) && (string) $v !== '') {
                $sample[$k] = $v;
            }
        }
        $built = $this->renderFile($template, $sample, $label, self::PREVIEW_SAMPLE);
        if ($built['status'] === self::OK) {
            $built['title'] = 'PREVIEW - ' . $label . ' (sample data)';
        }

        return $built;
    }

    /**
     * Flashes the structured "complete the missing details" guidance for an INCOMPLETE result
     * (no-op for any other result). Returns true when guidance was flashed.
     * $returnUrl is where the "Return & continue" bar leads once the details are
     * filled in; by default the page that raised it, so callers whose own URL is a
     * POST-only action must pass a page the user can open.
     */
    public static function flashMissing(array $doc, ?string $returnUrl = null): bool
    {
        if (empty($doc['missing_keys']) || empty($doc['doc_type'])) {
            return false;
        }
        MissingDetails::flash(
            DocumentTypeRegistry::label($doc['doc_type']),
            $doc['missing_keys'],
            MissingDetails::documentTargets($doc['doc_type'], (int) $doc['record_id'], (int) $doc['company_id']),
            $returnUrl
        );

        return true;
    }

    /** Sends a successful forRecord() result to the browser. */
    public function respond(ResponseInterface $response, array $doc, bool $inline = false): ResponseInterface
    {
        $isDocx = $doc['ext'] === 'docx';
        $name   = url_title($doc['title'], '-', true) . '.' . $doc['ext'];

        return $response
            ->setContentType($isDocx ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/pdf')
            ->setHeader('Content-Disposition', (($inline && ! $isDocx) ? 'inline' : 'attachment') . '; filename="' . $name . '"')
            ->setBody($doc['body']);
    }

    /** Unresolved automatic-detection findings that must be reviewed before use. */
    public static function blockingFlags(array $template): array
    {
        $flags = json_decode((string) ($template['field_review'] ?? ''), true) ?: [];

        return array_values(array_filter($flags, static fn ($f) => ! empty($f['blocking'])));
    }

    private function save(array $ctx, array $template, string $type, int $recordId, string $hash, string $ext, string $body, ?int $userId): void
    {
        $tmpDir = WRITEPATH . 'temp';
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $tmp = $tmpDir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . '.' . $ext;
        file_put_contents($tmp, $body);

        try {
            $path = service('fileStorage')->store(new File($tmp), "generated_documents/{$ctx['company_id']}");
            $this->generated->insert([
                'company_id'       => $ctx['company_id'],
                'template_id'      => $template['id'],
                'template_version' => $template['version'],
                'document_type'    => $type,
                'file_type'        => $ext,
                'record_id'        => $recordId,
                'data_hash'        => $hash,
                'data_snapshot'    => json_encode($ctx['data'], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                'title'            => mb_substr($ctx['title'], 0, 200),
                'file_path'        => $path,
                'file_size'        => strlen($body),
                'generated_by'     => $userId,
            ]);
        } catch (\Throwable $e) {
            // The document itself is still returned; only the archive copy failed.
            log_message('error', 'Could not archive generated document: ' . $e->getMessage());
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }

    private function result(string $status, string $message, array $extra = []): array
    {
        return ['status' => $status, 'message' => $message] + $extra;
    }
}
