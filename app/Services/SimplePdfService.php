<?php

namespace App\Services;

class SimplePdfService
{
    /**
     * @param  list<string>  $meta
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    public function render(string $title, array $meta, array $headers, array $rows, ?string $insight = null): string
    {
        $pageWidth = 792.0;
        $pageHeight = 612.0;
        $margin = 36.0;
        $usable = $pageWidth - ($margin * 2);
        $columnCount = max(1, count($headers));
        $widths = $this->columnWidths($headers, $rows, $usable);

        $pages = [];
        $y = $pageHeight - $margin;
        $commands = [];

        $this->writeTitle($commands, $y, $margin, $pageHeight, $title, $meta);
        $this->writeTableHeader($commands, $y, $margin, $widths, $headers);

        foreach ($rows as $row) {
            $values = [];
            for ($i = 0; $i < $columnCount; $i++) {
                $values[] = (string) ($row[$i] ?? '');
            }
            $lines = [];
            $rowHeight = 14.0;
            foreach ($values as $index => $value) {
                $wrapped = $this->wrap($value, max(8, $widths[$index] - 8));
                $lines[$index] = $wrapped;
                $rowHeight = max($rowHeight, (count($wrapped) * 10) + 6);
            }

            if ($y - $rowHeight < $margin + 20) {
                $pages[] = implode("\n", $commands);
                $commands = [];
                $y = $pageHeight - $margin;
                $this->writeTitle($commands, $y, $margin, $pageHeight, $title.' (continued)', $meta);
                $this->writeTableHeader($commands, $y, $margin, $widths, $headers);
            }

            $this->drawRect($commands, $margin, $y - $rowHeight, $usable, $rowHeight);
            $x = $margin;
            foreach ($values as $index => $value) {
                $lineY = $y - 11;
                foreach ($lines[$index] as $line) {
                    $this->text($commands, $x + 4, $lineY, $line, 8);
                    $lineY -= 10;
                }
                $x += $widths[$index];
            }
            $y -= $rowHeight;
        }

        if ($insight) {
            $insightLines = $this->wrap('Key Insight: '.$insight, $usable - 8);
            $block = (count($insightLines) * 11) + 14;
            if ($y - $block < $margin) {
                $pages[] = implode("\n", $commands);
                $commands = [];
                $y = $pageHeight - $margin;
            }
            $y -= 10;
            foreach ($insightLines as $line) {
                $this->text($commands, $margin, $y, $line, 9);
                $y -= 11;
            }
        }

        $pages[] = implode("\n", $commands);

        return $this->assemble($pages, $pageWidth, $pageHeight);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @return list<float>
     */
    private function columnWidths(array $headers, array $rows, float $usable): array
    {
        $weights = [];
        foreach ($headers as $index => $header) {
            $max = mb_strlen((string) $header);
            foreach (array_slice($rows, 0, 40) as $row) {
                $max = max($max, mb_strlen((string) ($row[$index] ?? '')));
            }
            $weights[] = max(8, min(42, $max));
        }
        $sum = array_sum($weights) ?: 1;
        $widths = [];
        foreach ($weights as $weight) {
            $widths[] = ($weight / $sum) * $usable;
        }

        return $widths;
    }

    /**
     * @param  list<string>  $commands
     * @param  list<string>  $meta
     */
    private function writeTitle(array &$commands, float &$y, float $margin, float $pageHeight, string $title, array $meta): void
    {
        unset($pageHeight);
        $this->text($commands, $margin, $y, $title, 14, true);
        $y -= 18;
        foreach ($meta as $line) {
            $this->text($commands, $margin, $y, (string) $line, 9);
            $y -= 12;
        }
        $y -= 6;
    }

    /**
     * @param  list<string>  $commands
     * @param  list<float>  $widths
     * @param  list<string>  $headers
     */
    private function writeTableHeader(array &$commands, float &$y, float $margin, array $widths, array $headers): void
    {
        $height = 18.0;
        $this->drawRect($commands, $margin, $y - $height, array_sum($widths), $height, true);
        $x = $margin;
        foreach ($headers as $index => $header) {
            $this->text($commands, $x + 4, $y - 12, (string) $header, 8, true);
            $x += $widths[$index];
        }
        $y -= $height;
    }

    /**
     * @param  list<string>  $commands
     */
    private function text(array &$commands, float $x, float $y, string $text, int $size, bool $bold = false): void
    {
        $font = $bold ? '/F2' : '/F1';
        $commands[] = sprintf(
            'BT %s %d Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET',
            $font,
            $size,
            $x,
            $y,
            $this->escape($text)
        );
    }

    /**
     * @param  list<string>  $commands
     */
    private function drawRect(array &$commands, float $x, float $y, float $width, float $height, bool $filled = false): void
    {
        if ($filled) {
            $commands[] = '0.93 0.94 0.96 rg';
            $commands[] = sprintf('%.2f %.2f %.2f %.2f re f', $x, $y, $width, $height);
            $commands[] = '0 0 0 rg';
        }
        $commands[] = '0.75 0.80 0.86 RG';
        $commands[] = sprintf('%.2f %.2f %.2f %.2f re S', $x, $y, $width, $height);
        $commands[] = '0 0 0 RG';
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, float $width): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if ($text === '') {
            return ['—'];
        }
        $max = max(8, (int) floor($width / 4.6));
        $words = explode(' ', $text);
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $next = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($next) > $max && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $next;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? ['—'] : $lines;
    }

    private function escape(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if (! is_string($converted) || $converted === '') {
            $converted = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
    }

    /**
     * @param  list<string>  $pages
     */
    private function assemble(array $pages, float $pageWidth, float $pageHeight): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $pageCount = count($pages);
        $pageIds = [];
        $nextId = 4;
        $kids = [];
        foreach ($pages as $index => $content) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageIds[$index] = [$pageId, $contentId, $content];
            $kids[] = $pageId.' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $fontBoldId = $nextId++;
        $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        foreach ($pageIds as [$pageId, $contentId, $content]) {
            $stream = $content."\n";
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 3 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>',
                $pageWidth,
                $pageHeight,
                $fontBoldId,
                $contentId
            );
            $objects[$contentId] = '<< /Length '.strlen($stream).' >> stream'."\n".$stream.'endstream';
        }

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxId + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer << /Size ".($maxId + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }
}
