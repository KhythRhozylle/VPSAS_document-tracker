<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpreadsheetExportService
{
    /**
     * @param list<string> $headers
     * @param list<list<string|int|float|null>> $rows
     */
    public function createCsvResponse(array $headers, array $rows, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    /**
     * @param list<string> $headers
     * @param list<list<string|int|float|null>> $rows
     */
    public function createExcelResponse(array $headers, array $rows, string $filename): Response
    {
        if (!str_ends_with(strtolower($filename), '.xlsx')) {
            $filename = preg_replace('/\.(xls|xlsx)$/i', '', $filename).'.xlsx';
        }

        $content = $this->buildXlsx($headers, $rows);
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Content-Length', (string) strlen($content));

        return $response;
    }

    /**
     * @param list<string> $headers
     * @param list<list<string|int|float|null>> $rows
     */
    private function buildXlsx(array $headers, array $rows): string
    {
        $sheetRows = array_merge([$headers], $rows);
        $sheetXml = $this->buildWorksheetXml($sheetRows);
        $sharedStrings = $this->collectSharedStrings($sheetRows);

        $files = [
            '[Content_Types].xml' => $this->getContentTypesXml(),
            '_rels/.rels' => $this->getRootRelsXml(),
            'docProps/core.xml' => $this->getCorePropsXml(),
            'docProps/app.xml' => $this->getAppPropsXml(),
            'xl/workbook.xml' => $this->getWorkbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->getWorkbookRelsXml(),
            'xl/styles.xml' => $this->getStylesXml(),
            'xl/sharedStrings.xml' => $this->buildSharedStringsXml($sharedStrings),
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ];

        if (class_exists(\ZipArchive::class)) {
            return $this->buildXlsxWithZipArchive($files);
        }

        return $this->buildZipArchive($files);
    }

    /**
     * @param array<string, string> $files
     */
    private function buildXlsxWithZipArchive(array $files): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tmpFile === false) {
            throw new \RuntimeException('Unable to create temporary export file.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpFile);
            throw new \RuntimeException('Unable to create Excel export archive.');
        }

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        $content = file_get_contents($tmpFile);
        @unlink($tmpFile);

        if ($content === false) {
            throw new \RuntimeException('Unable to read Excel export file.');
        }

        return $content;
    }

    /**
     * @param array<string, string> $files
     */
    private function buildZipArchive(array $files): string
    {
        $localHeaders = '';
        $centralHeaders = '';
        $offset = 0;

        foreach ($files as $name => $content) {
            $nameLength = strlen($name);
            $contentLength = strlen($content);
            $crc = crc32($content);

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $contentLength,
                $contentLength,
                $nameLength,
                0,
            );

            $localHeaders .= $localHeader.$name.$content;

            $centralHeader = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $contentLength,
                $contentLength,
                $nameLength,
                0,
                0,
                0,
                0,
                0,
                $offset,
            );

            $centralHeaders .= $centralHeader.$name;
            $offset += strlen($localHeader) + $nameLength + $contentLength;
        }

        $centralDirectorySize = strlen($centralHeaders);
        $endRecord = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            count($files),
            count($files),
            $centralDirectorySize,
            $offset,
            0,
        );

        return $localHeaders.$centralHeaders.$endRecord;
    }

    /**
     * @param list<list<string|int|float|null>> $rows
     *
     * @return list<string>
     */
    private function collectSharedStrings(array $rows): array
    {
        $strings = [];
        $index = [];

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $value = (string) ($cell ?? '');
                if (!array_key_exists($value, $index)) {
                    $index[$value] = count($strings);
                    $strings[] = $value;
                }
            }
        }

        return $strings;
    }

    /**
     * @param list<list<string|int|float|null>> $rows
     */
    private function buildWorksheetXml(array $rows): string
    {
        $sharedStrings = $this->collectSharedStrings($rows);
        $stringIndex = array_flip($sharedStrings);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $xml .= '<row r="'.$rowNumber.'">';
            foreach ($row as $columnIndex => $cell) {
                $columnLetter = $this->columnLetter($columnIndex + 1);
                $cellReference = $columnLetter.$rowNumber;
                $value = (string) ($cell ?? '');
                $sharedIndex = $stringIndex[$value];
                $xml .= '<c r="'.$cellReference.'" t="s"><v>'.$sharedIndex.'</v></c>';
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    /**
     * @param list<string> $sharedStrings
     */
    private function buildSharedStringsXml(array $sharedStrings): string
    {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($sharedStrings).'" uniqueCount="'.count($sharedStrings).'">';

        foreach ($sharedStrings as $string) {
            $xml .= '<si><t>'.$escape($string).'</t></si>';
        }

        $xml .= '</sst>';

        return $xml;
    }

    private function columnLetter(int $columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $columnNumber = intdiv($columnNumber - 1, 26);
        }

        return $letter;
    }

    private function getContentTypesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML;
    }

    private function getRootRelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;
    }

    private function getWorkbookXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Activity Logs" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML;
    }

    private function getWorkbookRelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private function getStylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
    <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
    <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styleSheet>
XML;
    }

    private function getCorePropsXml(): string
    {
        $created = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            .'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            .'xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>VPSAS Document Tracker</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:created>'
            .'</cp:coreProperties>';
    }

    private function getAppPropsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
    <Application>VPSAS Document Tracker</Application>
</Properties>
XML;
    }
}
