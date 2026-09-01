<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

class XlsxService
{
    public function export(array $headers, iterable $rows, string $filename): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('Excel export requires the PHP Zip extension.');
        }

        $base = storage_path('app/temp-xlsx');
        if (! is_dir($base)) {
            mkdir($base, 0775, true);
        }

        $columnCount = max(1, count($headers));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetFormatPr defaultRowHeight="20" customHeight="1"/>';
        $xml .= $this->columnsXml($headers);
        $xml .= '<sheetData>';
        $rowNumber = 1;
        $xml .= $this->rowXml($rowNumber++, $headers, true, $columnCount);
        foreach ($rows as $row) {
            $values = is_array($row) ? array_values($row) : array_values((array) $row);
            if (count($values) < $columnCount) {
                $values = array_pad($values, $columnCount, '');
            }
            $xml .= $this->rowXml($rowNumber++, $values, false, $columnCount);
        }
        $xml .= '</sheetData></worksheet>';

        $zipPath = $base.'/'.Str::uuid().'.xlsx';
        $zip = new \ZipArchive();
        $opened = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new RuntimeException('Unable to create the Excel file.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        if (! is_file($zipPath) || filesize($zipPath) < 4) {
            throw new RuntimeException('The Excel file could not be written.');
        }

        return $zipPath;
    }

    /**
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    public function read(string $path): array
    {
        if (! is_file($path) || filesize($path) < 4) {
            throw new RuntimeException('The Excel file could not be read.');
        }

        $handle = fopen($path, 'rb');
        $magic = $handle ? (string) fread($handle, 8) : '';
        if ($handle) {
            fclose($handle);
        }

        if (str_starts_with($magic, 'PK')) {
            return $this->readXlsx($path);
        }

        $contents = (string) file_get_contents($path);
        if (str_contains($contents, '<Workbook') || str_contains($contents, '<ss:Workbook')) {
            return $this->readSpreadsheetMl($contents);
        }

        throw new RuntimeException('Only .xlsx Excel files can be read. Save the file as .xlsx and try again.');
    }

    /**
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private function readXlsx(string $path): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('Excel import requires the PHP Zip extension.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open the Excel file.');
        }

        $sharedXml = $this->zipEntry($zip, 'xl/sharedStrings.xml');
        $shared = $this->parseSharedStrings($sharedXml);
        $sheet = $this->firstWorksheetXml($zip);
        $zip->close();

        if (! is_string($sheet) || $sheet === '') {
            throw new RuntimeException('The Excel worksheet could not be read.');
        }

        return $this->parseSheetXml($sheet, $shared);
    }

    private function zipEntry(\ZipArchive $zip, string $preferred): string|false
    {
        $direct = $zip->getFromName($preferred);
        if ($direct !== false && $direct !== '') {
            return $direct;
        }

        $needle = strtolower(basename($preferred));
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if (strtolower(basename($name)) === $needle) {
                $contents = $zip->getFromIndex($i);
                if ($contents !== false && $contents !== '') {
                    return $contents;
                }
            }
        }

        return false;
    }

    private function firstWorksheetXml(\ZipArchive $zip): ?string
    {
        $rels = (string) ($this->zipEntry($zip, 'xl/_rels/workbook.xml.rels') ?: '');
        if ($rels !== '' && preg_match_all('/Target="([^"]+)"/i', $rels, $matches)) {
            foreach ($matches[1] as $target) {
                $normalized = str_replace('\\', '/', ltrim((string) $target, '/'));
                if (! str_contains(strtolower($normalized), 'worksheet')) {
                    continue;
                }
                if (! str_starts_with($normalized, 'xl/')) {
                    $normalized = 'xl/'.$normalized;
                }
                $xml = $this->zipEntry($zip, $normalized);
                if (is_string($xml) && $xml !== '') {
                    return $xml;
                }
            }
        }

        $sheet = null;
        for ($i = 1; $i <= 8; $i++) {
            $xml = $this->zipEntry($zip, 'xl/worksheets/sheet'.$i.'.xml');
            if (is_string($xml) && $xml !== '') {
                $sheet = $xml;
                break;
            }
        }

        return $sheet;
    }

    /**
     * @return list<string>
     */
    private function parseSharedStrings(string|false $xml): array
    {
        if (! is_string($xml) || $xml === '') {
            return [];
        }

        $values = [];
        $xml = $this->normalizeSpreadsheetXml($xml);
        $document = @simplexml_load_string($xml);
        if ($document === false) {
            return [];
        }

        foreach ($document->si ?? [] as $item) {
            $text = '';
            foreach ($item->xpath('.//t') ?: [] as $node) {
                $text .= (string) $node;
            }
            $values[] = $text;
        }

        return $values;
    }

    /**
     * @param  list<string>  $shared
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private function parseSheetXml(string $xml, array $shared): array
    {
        $xml = $this->normalizeSpreadsheetXml($xml);
        $document = @simplexml_load_string($xml);
        if ($document === false) {
            throw new RuntimeException('The Excel worksheet could not be parsed.');
        }

        $rows = [];
        foreach ($document->xpath('//sheetData/row') ?: [] as $row) {
            $cells = [];
            $fallbackIndex = 0;
            foreach ($row->c ?? [] as $cell) {
                $ref = (string) $cell['r'];
                $index = $ref !== '' ? $this->columnIndexFromRef($ref) : $fallbackIndex;
                $fallbackIndex = $index + 1;
                $type = (string) $cell['t'];
                $value = '';
                if ($type === 's') {
                    $value = $shared[(int) $cell->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $text = '';
                    foreach ($cell->xpath('.//t') ?: [] as $node) {
                        $text .= (string) $node;
                    }
                    $value = $text !== '' ? $text : (string) ($cell->is->t ?? $cell->t ?? '');
                } elseif ($type === 'str' || $type === 'b' || $type === 'e') {
                    $value = (string) ($cell->v ?? '');
                } else {
                    $value = (string) ($cell->v ?? '');
                }
                $cells[$index] = $value;
            }
            if ($cells === []) {
                continue;
            }
            $width = max(array_keys($cells)) + 1;
            $values = array_fill(0, $width, '');
            foreach ($cells as $index => $value) {
                $values[$index] = $value;
            }
            if (implode('', $values) === '') {
                continue;
            }
            $rows[] = $values;
        }

        if ($rows === []) {
            return [[], []];
        }

        $headers = array_map(static fn ($value) => trim((string) $value), array_shift($rows));

        return [$headers, $rows];
    }

    private function normalizeSpreadsheetXml(string $xml): string
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?: $xml;
        $xml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xml) ?: $xml;

        return $xml;
    }

    /**
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private function readSpreadsheetMl(string $xml): array
    {
        $document = @simplexml_load_string($xml);
        if ($document === false) {
            throw new RuntimeException('The Excel file could not be parsed.');
        }

        $rows = [];
        $document->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
        foreach ($document->xpath('//ss:Worksheet[1]//ss:Row') ?: $document->xpath('//Row') ?: [] as $row) {
            $values = [];
            foreach ($row->xpath('./ss:Cell') ?: $row->xpath('./Cell') ?: [] as $cell) {
                $data = $cell->xpath('./ss:Data') ?: $cell->xpath('./Data') ?: [];
                $values[] = trim((string) ($data[0] ?? ''));
            }
            if (implode('', $values) === '') {
                continue;
            }
            $rows[] = $values;
        }

        if ($rows === []) {
            return [[], []];
        }

        $headers = array_map(static fn ($value) => trim((string) $value), array_shift($rows));

        return [$headers, $rows];
    }

    private function columnIndexFromRef(string $ref): int
    {
        preg_match('/^([A-Z]+)/i', $ref, $matches);
        $letters = strtoupper($matches[1] ?? 'A');
        $number = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $number = ($number * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $number - 1);
    }

    private function rowXml(int $rowNumber, array $values, bool $header, int $columnCount = 0): string
    {
        $style = $header ? '1' : '2';
        $height = $header ? '24' : '20';
        $xml = '<row r="'.$rowNumber.'" ht="'.$height.'" customHeight="1">';
        $count = $columnCount > 0 ? $columnCount : count($values);
        for ($index = 0; $index < $count; $index++) {
            $cellRef = $this->columnName($index + 1).$rowNumber;
            $text = htmlspecialchars((string) ($values[$index] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xml .= '<c r="'.$cellRef.'" s="'.$style.'" t="inlineStr"><is><t>'.$text.'</t></is></c>';
        }

        return $xml.'</row>';
    }

    /**
     * @param  list<string>  $headers
     */
    private function columnsXml(array $headers): string
    {
        $xml = '<cols>';
        foreach (array_values($headers) as $index => $header) {
            $width = $this->columnWidth((string) $header);
            $col = $index + 1;
            $xml .= '<col min="'.$col.'" max="'.$col.'" width="'.$width.'" customWidth="1"/>';
        }

        return $xml.'</cols>';
    }

    private function columnWidth(string $header): string
    {
        $length = max(1, mb_strlen($header));
        $width = max(16, $length + 10);
        if (strcasecmp($header, 'Remarks') === 0) {
            $width = max(42, $width * 2);
        } elseif (preg_match('/gateway|allocation|address|username|database|storage|channel allocation|total channel/i', $header)) {
            $width = max(26, $width + 4);
        } elseif (preg_match('/^id$/i', $header)) {
            $width = 12;
        }

        return number_format($width, 2, '.', '');
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            .'</fonts>'
            .'<fills count="2">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border>'
            .'<left style="thin"><color rgb="FF000000"/></left>'
            .'<right style="thin"><color rgb="FF000000"/></right>'
            .'<top style="thin"><color rgb="FF000000"/></top>'
            .'<bottom style="thin"><color rgb="FF000000"/></bottom>'
            .'<diagonal/>'
            .'</border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">'
            .'<alignment horizontal="center" vertical="center" wrapText="1"/>'
            .'</xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">'
            .'<alignment horizontal="center" vertical="center" wrapText="1"/>'
            .'</xf>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }
}
