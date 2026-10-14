<?php

namespace App\Modules\Document\Services;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Fills a company's approved PDF template without altering its design:
 * every page of the original is imported as-is (logo, columns, layout),
 * then for each mapped field the old sample value is covered with a
 * white box and the real value is printed in its place.
 *
 * Field map entries (all in PDF points, origin top-left):
 *   page, x, y, w, h, base (baseline y), field, size, align (L|C|R), bold, serif
 *
 * Uses the bundled FPDI/FPDF copies in app/ThirdParty (no Composer step).
 */
class PdfOverlay
{
    public const ALIGNS = ['L', 'C', 'R'];

    /** Special "fields": cover the sample value and leave it blank / print a text pattern. */
    public const BLANK = '__blank';
    public const TEXT  = '__text';

    /** Extra room (pt) a replacement value may grow beyond the sample's width before shrinking. */
    private const GROW = 40;
    private const MAX_LINES = 4;

    private static bool $booted = false;

    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        require_once APPPATH . 'ThirdParty/Fpdf/fpdf.php';
        spl_autoload_register(static function (string $class): void {
            $prefix = 'setasign\\Fpdi\\';
            if (strpos($class, $prefix) === 0) {
                $file = APPPATH . 'ThirdParty/Fpdi/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        self::$booted = true;
    }

    /** Page count of a PDF, or null when it can't be imported. */
    public function pageCount(string $pdf): ?int
    {
        self::boot();
        try {
            return (new Fpdi())->setSourceFile(StreamReader::createByString($pdf));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Validates/normalises a field map coming from the mapper UI.
     * Unknown fields, bad numbers and out-of-range pages are dropped.
     *
     * @param string[] $allowedFields placeholder keys that may be mapped
     */
    public function sanitizeMap(array $raw, array $allowedFields, int $pages, ?int &$dropped = null): array
    {
        $out = [];
        $dropped = 0;
        foreach (array_slice($raw, 0, 300) as $e) {
            if (! is_array($e) || ! in_array($e['field'] ?? '', array_merge($allowedFields, [self::BLANK, self::TEXT]), true)) {
                $dropped++;
                continue;
            }
            $pattern = mb_substr(trim(strip_tags((string) ($e['text'] ?? ''))), 0, 400);
            if ($e['field'] === self::TEXT && $pattern === '') {
                $dropped++;
                continue;
            }
            $page = (int) ($e['page'] ?? 0);
            foreach (['x', 'y', 'w', 'h', 'base', 'size'] as $k) {
                if (! isset($e[$k]) || ! is_numeric($e[$k])) {
                    $dropped++;
                    continue 2;
                }
            }
            if ($page < 1 || $page > $pages || $e['w'] <= 0 || $e['h'] <= 0 || $e['size'] < 3 || $e['size'] > 96 || $e['x'] < -50 || $e['x'] > 3000 || $e['y'] < -50 || $e['y'] > 3000) {
                $dropped++;
                continue;
            }
            $out[] = [
                'page'  => $page,
                'x'     => round((float) $e['x'], 2), 'y' => round((float) $e['y'], 2),
                'w'     => round((float) $e['w'], 2), 'h' => round((float) $e['h'], 2),
                'base'  => round((float) $e['base'], 2),
                'maxw'  => isset($e['maxw']) && is_numeric($e['maxw']) && $e['maxw'] > 0 && $e['maxw'] < 3000 ? round((float) $e['maxw'], 1) : null,
                'field' => $e['field'],
                'text'  => $e['field'] === self::TEXT ? $pattern : '',
                'size'  => round((float) $e['size'], 1),
                'align' => in_array($e['align'] ?? 'L', self::ALIGNS, true) ? ($e['align'] ?? 'L') : 'L',
                'bold'  => ! empty($e['bold']),
                'serif' => ! empty($e['serif']),
                'sample' => mb_substr(strip_tags((string) ($e['sample'] ?? '')), 0, 60),
            ];
        }

        return $out;
    }

    /** Original pages + replaced values -> PDF bytes. Throws on an unreadable template. */
    public function fill(string $pdf, array $map, array $data, ?string $previewLabel = null): string
    {
        self::boot();

        // Points (not mm) so the mapper's PDF-point coordinates apply directly.
        $doc   = new Fpdi('P', 'pt');
        $pages = $doc->setSourceFile(StreamReader::createByString($pdf));
        $doc->SetAutoPageBreak(false);
        $doc->SetMargins(0, 0, 0);

        for ($p = 1; $p <= $pages; $p++) {
            $tpl  = $doc->importPage($p);
            $size = $doc->getTemplateSize($tpl);
            $doc->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $doc->useTemplate($tpl);

            if ($previewLabel !== null) {
                // Unmistakable on every page of a preview; issued documents never get this.
                $doc->SetFont('Helvetica', 'B', 9);
                $doc->SetFillColor(255, 255, 255);
                $doc->Rect(14, 3, 8 + $doc->GetStringWidth($this->encode($previewLabel)), 14, 'F');
                $doc->SetTextColor(176, 0, 32);
                $doc->Text(18, 13, $this->encode($previewLabel));
            }

            $onPage = $this->withoutOverlaps(array_values(array_filter($map, static fn ($e) => (int) $e['page'] === $p)));
            foreach ($onPage as $e) {
                $this->coverSample($doc, $e);
            }
            foreach ($onPage as $e) {
                $this->printValue($doc, $e, $this->encode($this->valueFor($e, $data)));
            }
        }

        return $doc->Output('S');
    }

    private function valueFor(array $e, array $data): string
    {
        if ($e['field'] === self::BLANK) {
            return '';
        }
        if ($e['field'] === self::TEXT) {
            return (string) preg_replace_callback('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', static fn (array $m) => is_scalar($data[$m[1]] ?? null) ? (string) $data[$m[1]] : '', (string) ($e['text'] ?? ''));
        }

        return is_scalar($data[$e['field']] ?? null) ? (string) $data[$e['field']] : '';
    }

    /** Covers the template's sample value (slightly padded). */
    private function coverSample(Fpdi $doc, array $e): void
    {
        $doc->SetFillColor(255, 255, 255);
        $doc->Rect($e['x'] - 0.3, $e['y'] + 0.3, $e['w'] + 0.6, $e['h'] + 0.5, 'F');
    }

    /**
     * Two entries over the same spot would cover each other's text and print twice (merged/overlapping words).
     * Keep one per spot: the first, except that a real field wins over a mere "blank this out" entry.
     */
    private function withoutOverlaps(array $entries): array
    {
        $kept = [];
        foreach ($entries as $e) {
            $dup = null;
            foreach ($kept as $i => $k) {
                $w = min($e['x'] + $e['w'], $k['x'] + $k['w']) - max($e['x'], $k['x']);
                $h = min($e['y'] + $e['h'], $k['y'] + $k['h']) - max($e['y'], $k['y']);
                if ($w > 0 && $h > 0 && ($w * $h) / max(1.0, min($e['w'] * $e['h'], $k['w'] * $k['h'])) > 0.5) {
                    $dup = $i;
                    break;
                }
            }
            if ($dup === null) {
                $kept[] = $e;
            } elseif ($kept[$dup]['field'] === self::BLANK && $e['field'] !== self::BLANK) {
                $kept[$dup] = $e;
            }
        }

        return $kept;
    }

    /**
     * Prints the real value where the template's sample value was. It keeps the original font size when it fits;
     * a long value is first shrunk a little (never below 85% of the original, so it still matches the page), then
     * wrapped onto following lines at that size instead of shrinking to unreadable or running off the page / over
     * the neighbouring text.
     */
    private function printValue(Fpdi $doc, array $e, string $text): void
    {
        if ($text === '') {
            return;
        }

        $family = ! empty($e['serif']) ? 'Times' : 'Helvetica';
        $style  = ! empty($e['bold']) ? 'B' : '';
        $orig   = (float) $e['size'];
        $size   = $orig;
        $limit  = ! empty($e['maxw']) ? max((float) $e['maxw'], (float) $e['w']) : (float) $e['w'] + self::GROW;
        $text   = trim((string) preg_replace('/[ 	]+/', ' ', $text));
        $floor  = max(5.0, round($orig * 0.85 * 2) / 2);

        $doc->SetFont($family, $style, $size);
        while ($size > $floor && $doc->GetStringWidth($text) > $limit) {
            $size -= 0.5;
            $doc->SetFont($family, $style, $size);
        }

        $lines = $this->wrap($doc, $text, $limit);
        if (count($lines) > self::MAX_LINES) {
            // Extremely long: fit it in the allowed lines rather than run down the page.
            $lines = array_slice($lines, 0, self::MAX_LINES);
            $lines[self::MAX_LINES - 1] = rtrim($lines[self::MAX_LINES - 1]) . '...';
        }

        $doc->SetTextColor(0, 0, 0);
        foreach ($lines as $i => $line) {
            $width = $doc->GetStringWidth($line);
            $x     = match ($e['align']) {
                'R'     => $e['x'] + $e['w'] - $width,
                'C'     => $e['x'] + ($e['w'] - $width) / 2,
                default => $e['x'],
            };
            $doc->Text($x, $e['base'] + $i * $size * 1.25, $line);
        }
    }

    /** Greedy word wrap using the font currently set on $doc; a single over-long word is hard-split. */
    private function wrap(Fpdi $doc, string $text, float $limit): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $text) ?: [$text] as $para) {
            $cur = '';
            foreach (preg_split('/ /', $para) ?: [] as $word) {
                $try = $cur === '' ? $word : $cur . ' ' . $word;
                if ($doc->GetStringWidth($try) <= $limit || $cur === '' && $word === '') {
                    $cur = $try;
                    continue;
                }
                if ($cur !== '') {
                    $lines[] = $cur;
                }
                while ($doc->GetStringWidth($word) > $limit && strlen($word) > 1) {
                    $n = strlen($word);
                    while ($n > 1 && $doc->GetStringWidth(substr($word, 0, $n)) > $limit) {
                        $n--;
                    }
                    $lines[] = substr($word, 0, $n);
                    $word    = substr($word, $n);
                }
                $cur = $word;
            }
            $lines[] = $cur;
        }

        return $lines === [] ? [''] : $lines;
    }

    /** FPDF core fonts are Latin-1: map the rupee sign, drop what can't be shown. */
    private function encode(string $text): string
    {
        $text = str_replace('₹', 'Rs.', $text);
        $out  = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);

        return $out === false ? '' : $out;
    }
}
