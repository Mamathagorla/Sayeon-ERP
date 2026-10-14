<?php

namespace App\Modules\Document\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Fills an uploaded HTML template with ERP data and renders it to PDF
 * through Dompdf (already the app's PDF engine — DOCX→PDF would need an
 * external converter this project doesn't have).
 *
 * Syntax:  {{key}}                       -> HTML-escaped value
 *          {{#items}} ... {{/items}}     -> repeated once per row of a
 *                                           list value (e.g. invoice lines);
 *                                           inside, {{description}} etc. read
 *                                           the row, falling back to the
 *                                           document-level keys.
 * Unknown/empty keys render as blank; unknownKeys() reports them.
 */
class TemplateRenderer
{
    private const KEY = '[A-Za-z0-9_]+';

    /** Every placeholder key (top level + section names) the template uses. */
    public function placeholdersIn(string $html): array
    {
        preg_match_all('/\{\{\s*#?\/?(' . self::KEY . ')\s*\}\}/', $html, $m);

        return array_values(array_unique($m[1]));
    }

    /** Keys used by the template that the data can't supply. */
    public function unknownKeys(string $html, array $data): array
    {
        // Row-level keys inside a repeating section come from each row,
        // not the document data, so only the section name is checked.
        $outer = preg_replace('/(\{\{#' . self::KEY . '\}\}).*?(\{\{\/' . self::KEY . '\}\})/s', '$1$2', $html) ?? $html;

        return array_values(array_filter(
            $this->placeholdersIn($outer),
            static fn (string $k) => ! array_key_exists($k, $data)
        ));
    }

    /**
     * Templates are static documents — drop script blocks and inline
     * event handlers so a preview in the browser can never execute code.
     */
    public function sanitize(string $html): string
    {
        $html = preg_replace('#<script\b.*?</script>#is', '', $html) ?? $html;

        return preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html) ?? $html;
    }

    /** Raw contents of a file held by the configured storage driver (local or S3). */
    public function loadStored(string $path): ?string
    {
        $storage = service('fileStorage');
        $source  = method_exists($storage, 'absolutePath') ? $storage->absolutePath($path) : $storage->url($path);
        $body    = @file_get_contents($source);

        return $body === false ? null : $body;
    }

    public function fill(string $html, array $data): string
    {
        $html = $this->sanitize($html);

        // Repeating sections first.
        $html = preg_replace_callback(
            '/\{\{#(' . self::KEY . ')\}\}(.*?)\{\{\/\1\}\}/s',
            function (array $m) use ($data): string {
                $rows = $data[$m[1]] ?? [];
                if (! is_array($rows)) {
                    return '';
                }
                $out = '';
                foreach ($rows as $row) {
                    $out .= $this->replaceScalars($m[2], is_array($row) ? $row + $data : $data);
                }

                return $out;
            },
            $html
        ) ?? $html;

        return $this->replaceScalars($html, $data);
    }

    private function replaceScalars(string $html, array $data): string
    {
        return preg_replace_callback(
            '/\{\{\s*(' . self::KEY . ')\s*\}\}/',
            static function (array $m) use ($data): string {
                $v = $data[$m[1]] ?? '';

                // Line breaks in a value (addresses ...) stay line breaks; "{{" in data is never re-read as a placeholder.
                return is_scalar($v) ? str_replace('{', '&#123;', nl2br(htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8'), false)) : '';
            },
            $html
        ) ?? $html;
    }

    // ---- HTML header / body / footer editing -----------------------------------------

    /** Removes and returns the marked (or first <name>…</name>) part from $inner. */
    private function takePart(string &$inner, string $name): string
    {
        foreach (['#<!--tpl:' . $name . '-->(.*?)<!--/tpl:' . $name . '-->#is', '#<' . $name . '(?:\s[^>]*)?>(.*?)</' . $name . '>#is'] as $pattern) {
            if (preg_match($pattern, $inner, $m)) {
                $inner = str_replace($m[0], '', $inner);

                return trim($m[1]);
            }
        }

        return '';
    }

    /**
     * Splits an HTML template into editable parts. Parts are located by the markers this
     * editor writes (<!--tpl:header-->…<!--/tpl:header-->), or for templates that were
     * simply uploaded, by their first <header> / <footer> elements; everything else
     * between <body> and </body> is the body. Head/styles are kept untouched.
     *
     * @return array{prefix:string, header:string, body:string, footer:string, suffix:string}
     */
    public function splitHtml(string $html): array
    {
        $prefix = '';
        $suffix = '';
        $inner  = $html;

        if (preg_match('#^(.*?<body[^>]*>)(.*)(</body>.*)$#is', $html, $m)) {
            [$prefix, $inner, $suffix] = [$m[1], $m[2], $m[3]];
        }

        $header = $this->takePart($inner, 'header');
        $footer = $this->takePart($inner, 'footer');
        // body marker written by a previous save
        if (preg_match('#<!--tpl:body-->(.*?)<!--/tpl:body-->#is', $inner, $m)) {
            $inner = $m[1];
        }

        return ['prefix' => $prefix, 'header' => $header, 'body' => trim($inner), 'footer' => $footer, 'suffix' => $suffix];
    }

    /** Reassembles the parts (with markers so they split back the same way). Body first, footer last. */
    public function joinHtml(array $parts): string
    {
        $prefix = $parts['prefix'] !== '' ? $parts['prefix'] : "<!doctype html><html><head><meta charset=\"utf-8\"></head><body>";
        $suffix = $parts['suffix'] !== '' ? $parts['suffix'] : "</body></html>";

        return $prefix . "
<!--tpl:header-->" . $parts['header'] . "<!--/tpl:header-->
<!--tpl:body-->"
            . $parts['body'] . "<!--/tpl:body-->
<!--tpl:footer-->" . $parts['footer'] . "<!--/tpl:footer-->
" . $suffix;
    }

    // ---- placeholder audit ----------------------------------------------------------------

    /**
     * Checks every placeholder a template uses against the data it is about to be filled with.
     * Nothing is ever silently blanked: a placeholder the document type does not provide
     * ("unknown"), one with no stored value ("empty"), and a repeating section with no rows
     * ("emptySections") are all reported so generation can be blocked.
     *
     * $text is the template's text (HTML source, DOCX text) or, for PDFs, a string made of the
     * placeholders its field map prints (see pdfKeysText()).
     *
     * @return array{unknown:string[], empty:string[], emptySections:string[], fixedTitle:bool}
     */
    public function audit(string $text, array $data): array
    {
        $out      = ['unknown' => [], 'empty' => [], 'emptySections' => []];
        $sections = [];

        $top = preg_replace_callback('/\{\{#(' . self::KEY . ')\}\}(.*?)\{\{\/\1\}\}/s', function (array $m) use (&$sections): string {
            $sections[$m[1]] = $this->placeholdersIn($m[2]);

            return '';
        }, $text) ?? $text;

        // A section used by a template must be a non-empty list in the data.
        foreach ($sections as $name => $inner) {
            $rows = $data[$name] ?? null;
            if (! is_array($rows) || $rows === []) {
                $out['emptySections'][] = $name;
                continue;
            }
            foreach ($inner as $k) {
                if (! array_key_exists($k, $rows[0]) && ! array_key_exists($k, $data)) {
                    $out['unknown'][] = $k;
                }
            }
        }

        foreach ($this->placeholdersIn($top) as $k) {
            if (! array_key_exists($k, $data) || is_array($data[$k])) {
                $out['unknown'][] = $k;
            } elseif (trim((string) $data[$k]) === '') {
                $out['empty'][] = $k;
            }
        }

        $out = array_map(static fn (array $l) => array_values(array_unique($l)), $out);

        // A fixed honorific right before the name would be inferred, not stored data.
        $out['fixedTitle'] = (bool) preg_match('/\b(?:Mr|Ms|Mrs|Miss|Dr)\.?\s*\{\{\s*(?:employee_name|salutation_name)\s*\}\}/i', $text);

        return $out;
    }

    /** The placeholders a PDF field map prints, as one string the audit can scan. */
    public function pdfKeysText(array $map): string
    {
        $text = '';
        foreach ($map as $e) {
            if (in_array($e['field'], ['__blank'], true)) {
                continue;
            }
            $text .= ($e['field'] === '__text') ? ' ' . ($e['text'] ?? '') : ' {{' . $e['field'] . '}}';
        }

        return $text;
    }

    /** Gendered words / honorifics a template prints as fixed text (they would be wrong for other people). */
    public function genderedWords(string $text): array
    {
        $text = preg_replace('/\{\{.*?\}\}/s', ' ', $text) ?? $text;
        preg_match_all('/\b(he|she|him|his|hers?|himself|herself|mr|mrs|ms|miss|mister)\b/i', strip_tags($text), $m);

        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    // ---- preview labelling ------------------------------------------------------------------

    public function markPreviewHtml(string $html, string $label): string
    {
        $banner = '<div style="background:#fff3cd;color:#b00020;border:2px solid #b00020;padding:4px 8px;margin:0 0 8px;font:bold 12px sans-serif;text-align:center">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';

        $count = 0;
        $out   = preg_replace('/(<body[^>]*>)/i', '$1' . $banner, $html, 1, $count);

        return ($out !== null && $count > 0) ? $out : $banner . $html;
    }

    public function markPreviewDocx(string $binary, string $label): string
    {
        return (string) ($this->withZip($binary, function (\ZipArchive $z) use ($label): void {
            $xml = (string) $z->getFromName('word/document.xml');
            $p   = '<w:p><w:r><w:rPr><w:b/><w:color w:val="B00020"/></w:rPr><w:t xml:space="preserve">'
                . htmlspecialchars($label, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r></w:p>';
            $xml = preg_replace('/(<w:body[^>]*>)/', '$1' . $p, $xml, 1) ?? $xml;
            $z->addFromString('word/document.xml', $xml);
        }, true) ?? $binary);
    }

    // ---- DOCX ------------------------------------------------------------

    /** Word parts that can carry text/placeholders. */
    private function docxParts(\ZipArchive $zip): array
    {
        $parts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^word/(document|header\d*|footer\d*)\.xml$#', $name)) {
                $parts[] = $name;
            }
        }

        return $parts;
    }

    /** True when $binary is a real .docx (zip with word/document.xml). */
    public function isDocx(string $binary): bool
    {
        return $this->withZip($binary, static fn (\ZipArchive $z) => $z->locateName('word/document.xml') !== false) === true;
    }

    private function withZip(string $binary, callable $fn, bool $write = false)
    {
        $tmp = WRITEPATH . 'temp' . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . '.docx';
        if (! is_dir(dirname($tmp))) {
            mkdir(dirname($tmp), 0755, true);
        }
        file_put_contents($tmp, $binary);

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmp) !== true) {
                return null;
            }
            $result = $fn($zip);
            $zip->close();

            return $write ? file_get_contents($tmp) : $result;
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }

    /** Plain text of the document (paragraph per line) — for previews and placeholder discovery. */
    public function docxText(string $binary): string
    {
        return (string) $this->withZip($binary, function (\ZipArchive $z): string {
            $text = '';
            foreach ($this->docxParts($z) as $part) {
                $xml   = (string) $z->getFromName($part);
                $xml   = preg_replace('#</w:p>#', "\n", $xml) ?? $xml;
                $xml   = preg_replace('#<w:tab/>#', "\t", $xml) ?? $xml;
                $text .= html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8') . "\n";
            }

            return trim($text);
        });
    }

    /**
     * Replaces {{key}} in body/header/footer XML. Word often splits one
     * placeholder across several runs, so any paragraph that still
     * contains "{{" after the per-run pass is collapsed into its first
     * text run (formatting of only those paragraphs is simplified).
     *
     * Repeating rows: put {{#items}} (and optionally {{/items}}) in ONE table
     * row of the Word file, with row placeholders such as {{description}},
     * {{quantity}}, {{rate}}, {{amount}}. That whole row, with its exact
     * formatting, is repeated once per item (or dropped when there are none).
     */
    public function docxFill(string $binary, array $data): ?string
    {
        return $this->withZip($binary, function (\ZipArchive $z) use ($data): void {
            foreach ($this->docxParts($z) as $part) {
                $xml = (string) $z->getFromName($part);

                $xml = preg_replace_callback('#<w:tr[ >].*?</w:tr>#s', function (array $tr) use ($data): string {
                    if (! preg_match('/\{\{\s*#(' . self::KEY . ')\s*\}\}/', strip_tags($tr[0]), $mm) || ! is_array($data[$mm[1]] ?? null)) {
                        return $tr[0];
                    }
                    $out = '';
                    foreach ($data[$mm[1]] as $row) {
                        $out .= $this->fillXmlParagraphs($tr[0], (is_array($row) ? $row : []) + $data);
                    }

                    return $out;
                }, $xml) ?? $xml;

                $z->addFromString($part, $this->fillXmlParagraphs($xml, $data));
            }
        }, true);
    }

    private function fillXmlParagraphs(string $xml, array $data): string
    {
        return preg_replace_callback('#<w:p[ >].*?</w:p>#s', function (array $p) use ($data): string {
            $para = $p[0];
            if (! str_contains(strip_tags($para), '{{')) {
                return $para;
            }
            $para = $this->replaceXmlText($para, $data);
            if (! str_contains(strip_tags($para), '{{')) {
                return $this->preserveSpaces($para);
            }

            return $this->preserveSpaces($this->fillSplitRuns($para, $data));
        }, $xml) ?? $xml;
    }

    /**
     * A placeholder Word split over several runs ("{{emp" + "loyee_name}}"). Each placeholder is replaced where it
     * STARTS (that run keeps its formatting) and its remaining characters are removed from the following runs, so
     * every other word keeps its own run, formatting, tabs and line breaks, and nothing is moved or duplicated.
     */
    private function fillSplitRuns(string $para, array $data): string
    {
        if (! preg_match_all('#<w:t(?:\s[^>]*)?>(.*?)</w:t>#s', $para, $m, PREG_OFFSET_CAPTURE)) {
            return $para;
        }
        $texts = array_column($m[1], 0);
        $all   = implode('', $texts);
        $starts = [];
        $pos = 0;
        foreach ($texts as $i => $t) {
            $starts[$i] = $pos;
            $pos += strlen($t);
        }

        // [from, to, replacement] over the concatenated text
        $edits = [];
        preg_match_all('/\{\{\s*([#\/]?)(' . self::KEY . ')\s*\}\}/', $all, $pm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($pm as $hit) {
            $v = $hit[1][0] !== '' ? '' : ($data[$hit[2][0]] ?? '');
            $edits[] = [$hit[0][1], $hit[0][1] + strlen($hit[0][0]), is_scalar($v) ? $this->xmlValue((string) $v) : ''];
        }
        if ($edits === []) {
            return $para;
        }

        $new = $texts;
        foreach (array_reverse($edits) as [$from, $to, $rep]) {
            foreach ($texts as $i => $t) {
                $s = $starts[$i];
                $e = $s + strlen($t);
                if ($e <= $from || $s >= $to) {
                    continue;
                }
                $a = max($from, $s) - $s;
                $b = min($to, $e) - $s;
                $put = $from >= $s ? $rep : '';   // the run holding the first character receives the value
                $new[$i] = substr($new[$i], 0, $a) . $put . substr($new[$i], $b);
                // offsets of later edits in this run are unaffected: edits are applied right-to-left
            }
        }

        $i = -1;

        return preg_replace_callback('#(<w:t(?:\s[^>]*)?>)(.*?)(</w:t>)#s', static function (array $x) use (&$i, $new): string {
            $i++;

            return '<w:t xml:space="preserve">' . $new[$i] . $x[3];
        }, $para) ?? $para;
    }

    /** Word trims leading/trailing spaces of a <w:t> unless told otherwise: "Dear " + name would merge into "Dearname". */
    private function preserveSpaces(string $para): string
    {
        return preg_replace('#<w:t>#', '<w:t xml:space="preserve">', $para) ?? $para;
    }

    /** XML-escaped value for a Word run; a line break in the value becomes a real Word line break. "{{" in data is never re-read. */
    private function xmlValue(string $v): string
    {
        $v = htmlspecialchars(trim($v), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $v = (string) preg_replace('/\R/', '</w:t><w:br/><w:t xml:space="preserve">', $v);

        return str_replace('{', '&#123;', $v);
    }

    /** Placeholder replace on already-XML-escaped text; values are XML-escaped. {{#x}} / {{/x}} markers vanish. */
    private function replaceXmlText(string $xml, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([#\/]?)(' . self::KEY . ')\s*\}\}/', function (array $m) use ($data): string {
            if ($m[1] !== '') {
                return '';
            }
            $v = $data[$m[2]] ?? '';

            return is_scalar($v) ? $this->xmlValue((string) $v) : '';
        }, $xml) ?? $xml;
    }

    public function toPdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
