<?php

namespace App\Modules\Document\Services;

use App\Modules\Document\Models\DocumentTemplateModel;
use CodeIgniter\Files\File;

/**
 * Template file operations that must never touch another template's file:
 * cloning into another company and replacing an HTML template's content.
 * Authorisation (who may clone / edit what) is the controller's job; this
 * class only guarantees that every write goes to a NEW file owned by exactly
 * one template row.
 */
class TemplateWriter
{
    private DocumentTemplateModel $templates;
    private TemplateRenderer $renderer;

    public function __construct()
    {
        $this->templates = new DocumentTemplateModel();
        $this->renderer  = new TemplateRenderer();
    }

    /** Writes bytes through the configured storage driver as a brand-new file; returns its stored path. */
    public static function storeBytes(string $bytes, string $ext, string $folder): string
    {
        $dir = WRITEPATH . 'temp';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $tmp = $dir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . '.' . $ext;
        file_put_contents($tmp, $bytes);

        try {
            return service('fileStorage')->store(new File($tmp), $folder);
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }

    /**
     * Copies $source into a separate DRAFT template owned by $targetCompanyId (own file copy,
     * next version number, source id recorded). The source row and file are not modified.
     *
     * @return int|null new template id, or null when the source file is missing
     */
    public function cloneTo(array $source, int $targetCompanyId, string $sourceCompanyName, ?int $userId): ?int
    {
        $body = $this->renderer->loadStored($source['file_path']);
        if ($body === null) {
            return null;
        }

        [$body, $flags, $adapted] = $this->adaptToCompany($source, $body);
        $path = self::storeBytes($body, $source['file_type'], "document_templates/{$targetCompanyId}");

        return (int) $this->templates->insert([
            'company_id'         => $targetCompanyId,
            'document_type'      => $source['document_type'],
            'workflow'           => $source['workflow'] ?? null,
            'template_name'      => mb_substr($source['template_name'], 0, 150),
            'version'            => $this->templates->nextVersion($targetCompanyId, $source['document_type']),
            'status'             => 'draft',
            'file_type'          => $source['file_type'],
            'field_map'          => $source['field_map'],
            'field_review'       => $flags === [] ? null : json_encode($flags),
            'source_template_id' => (int) $source['id'],
            'previewed_at'       => null,
            'file_path'          => $path,
            'original_name'      => $source['original_name'],
            'file_size'          => strlen($body),
            'notes'              => mb_substr("Cloned from {$sourceCompanyName} v{$source['version']}." . ($adapted > 0 ? " {$adapted} company detail(s) now use the target company's data automatically." : '') . " Check the header, footer, logo and address before activating.", 0, 500),
            'created_by'         => $userId,
        ]);
    }

    /**
     * Saves an editable HTML reconstruction of $source (a PDF template) as a new DRAFT HTML template for
     * $targetCompanyId. The source row and file are untouched; the source company's name / address typed into the
     * text become {{company_*}} placeholders, and anything left over is flagged like a normal clone.
     *
     * @param array $extraFlags findings from the conversion itself and from the template review
     */
    public function fromHtml(array $source, string $html, int $targetCompanyId, string $name, array $extraFlags, ?int $userId): int
    {
        [$html, $flags, $adapted] = $this->adaptToCompany(['file_type' => 'html', 'field_review' => null] + $source, $html);
        $path = self::storeBytes($html, 'html', "document_templates/{$targetCompanyId}");

        return (int) $this->templates->insert([
            'company_id'         => $targetCompanyId,
            'document_type'      => $source['document_type'],
            'workflow'           => $source['workflow'] ?? null,
            'template_name'      => mb_substr($name, 0, 150),
            'version'            => $this->templates->nextVersion($targetCompanyId, $source['document_type']),
            'status'             => 'draft',
            'file_type'          => 'html',
            'field_map'          => null,
            'field_review'       => ($all = array_merge($flags, $extraFlags)) === [] ? null : json_encode($all),
            'source_template_id' => (int) $source['id'],
            'previewed_at'       => null,
            'file_path'          => $path,
            'original_name'      => pathinfo((string) $source['original_name'], PATHINFO_FILENAME) . '.html',
            'file_size'          => strlen($html),
            'notes'              => mb_substr("Editable HTML rebuilt from the PDF template v{$source['version']}." . ($adapted > 0 ? " {$adapted} company detail(s) now use the target company's data." : '') . ' Compare with the preview, then edit header / body / footer.', 0, 500),
            'created_by'         => $userId,
        ]);
    }

    /**
     * Makes the copy independent of the source company's identity. HTML: the source company's name, address, GST/PAN/CIN
     * typed into the text become {{company_name}} / {{company_address}} / ... so the TARGET company's own data prints.
     * What can't be changed safely (a PDF's baked-in letterhead, Word headers, logos) is flagged for review instead of being
     * silently kept: a clone must not go live still showing another company's identity.
     *
     * @return array{0:string,1:array,2:int} new bytes, review flags (source's own + clone findings), number of automatic replacements
     */
    private function adaptToCompany(array $source, string $body): array
    {
        $flags   = json_decode((string) $source['field_review'], true) ?: [];
        $company = (new \App\Modules\Company\Models\CompanyModel())->find($source['company_id']) ?? [];
        $name    = (string) ($company['name'] ?? '');
        $keys    = (new DocumentTypeRegistry())->fieldKeys($source['document_type']);
        $details = [
            'company_name'    => $name,
            'company_address' => trim((string) ($company['registered_address'] ?? '')),
            'company_gst'     => trim((string) ($company['gst'] ?? '')),
            'company_pan'     => trim((string) ($company['pan'] ?? '')),
            'company_cin'     => trim((string) ($company['cin'] ?? '')),
        ];
        $count = 0;
        $brand = preg_match('/^\p{L}{4,}/u', $name, $bm) ? $bm[0] : '';

        if ($source['file_type'] === 'html') {
            // only text between tags: attributes, CSS and URLs are never rewritten
            $parts = preg_split('/(<[^>]*>)/', $body, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$body];
            foreach ($parts as $i => $part) {
                if ($i % 2 === 1 || trim($part) === '') {
                    continue;
                }
                foreach ($details as $key => $value) {
                    if ($value !== '' && in_array($key, $keys, true)) {
                        $parts[$i] = preg_replace('/' . preg_quote(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), '/') . '|' . preg_quote($value, '/') . '/iu', '{{' . $key . '}}', $parts[$i], -1, $n) ?? $parts[$i];
                        $count += $n;
                    }
                }
                // after the full name, the short brand word alone ("... employees of Sanvima") is the company too
                if ($brand !== '' && in_array('company_name', $keys, true)) {
                    $parts[$i] = preg_replace('/(?<![\w{])' . preg_quote($brand, '/') . '(?![\w}])/iu', '{{company_name}}', $parts[$i], -1, $n) ?? $parts[$i];
                    $count += $n;
                }
            }
            $body = implode('', $parts);
        }

        $text = match ($source['file_type']) {
            'html'  => strip_tags($body),
            'docx'  => $this->renderer->docxText($body),
            default => '',
        };
        $left = [];
        foreach ($details as $key => $value) {
            if ($value !== '' && $text !== '' && stripos($text, $value) !== false) {
                $left[] = $value;
            }
        }

        $who = $name !== '' ? $name : 'the source company';
        if ($source['file_type'] === 'pdf') {
            $flags[] = ['field' => 'letterhead', 'label' => $who, 'reason' => "This is a fixed-layout PDF: its letterhead, logo, address and fixed wording are part of the uploaded page and are NOT editable here (only the detected fields are replaced). Upload the target company's PDF with Replace file, or accept this review if the layout is correct for this company.", 'blocking' => true];
        } elseif ($left !== []) {
            $flags[] = ['field' => 'company_details', 'label' => $who, 'reason' => "The copy still contains the source company's details (" . implode('; ', array_map(static fn ($v) => mb_substr($v, 0, 40), $left)) . "). Replace them with the target company's details or the {{company_name}} / {{company_address}} placeholders.", 'blocking' => true];
        } elseif ($source['file_type'] === 'docx') {
            $flags[] = ['field' => 'company_details', 'label' => $who, 'reason' => "Check the Word header, footer, logo and address: they may still show {$who}. Download, edit in Word, then upload with Replace file.", 'blocking' => false];
        } elseif (preg_match('/<img\b/i', $body)) {
            $flags[] = ['field' => 'logo', 'label' => $who, 'reason' => "This template contains image(s) (probably {$who}'s logo). Replace the logo with the target company's before activating.", 'blocking' => false];
        }

        return [$body, $flags, $count];
    }

    /**
     * Replaces an HTML template's content with a new file (old file removed) and puts it
     * back to draft so it has to be previewed again. Only this template's own file is touched.
     */
    public function replaceHtml(array $tpl, string $html): void
    {
        $newPath = self::storeBytes($html, 'html', "document_templates/{$tpl['company_id']}");
        service('fileStorage')->delete($tpl['file_path']);

        $this->templates->update($tpl['id'], [
            'file_path'    => $newPath,
            'file_size'    => strlen($html),
            'status'       => 'draft',
            'previewed_at' => null,
        ]);
    }
}
