<?php

/**
 * Minimal XLSX writer — no external libraries needed.
 * Uses PHP's ZipArchive to build a valid .xlsx file.
 * Falls back to CSV if ZipArchive is unavailable.
 */
class XlsxWriter
{
    private array $headers = [];
    private array $rows    = [];
    private string $sheetName = 'Dados';

    public function setSheetName(string $name): self
    {
        $this->sheetName = $name;
        return $this;
    }

    public function setHeaders(array $headers): self
    {
        $this->headers = array_values($headers);
        return $this;
    }

    public function addRow(array $row): self
    {
        $this->rows[] = array_values($row);
        return $this;
    }

    public function addRows(array $rows): self
    {
        foreach ($rows as $row) {
            $this->addRow($row);
        }
        return $this;
    }

    public function download(string $filename): never
    {
        if (!class_exists('ZipArchive')) {
            $this->downloadCsv(str_replace('.xlsx', '.csv', $filename));
        }

        $data = $this->buildXlsx();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        echo $data;
        exit;
    }

    // ── XLSX builder ─────────────────────────────────────────────────────────

    private function buildXlsx(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gn_xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels',          $this->rootRels());
        $zip->addFromString('xl/workbook.xml',       $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml',         $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet());

        $zip->close();

        $content = file_get_contents($tmp);
        unlink($tmp);

        return $content;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"              ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"     ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"                ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function workbook(): string
    {
        $name = htmlspecialchars($this->sheetName, ENT_XML1, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="' . $name . '" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/><family val="2"/></font>
    <font><sz val="11"/><b/><name val="Calibri"/><family val="2"/><color rgb="FFFFFFFF"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1A3A6B"/><bgColor indexed="64"/></patternFill></fill>
  </fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
  </cellXfs>
</styleSheet>';
    }

    private function sheet(): string
    {
        $xml   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml  .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml  .= '<sheetData>';

        $rowNum = 1;

        if (!empty($this->headers)) {
            $xml .= '<row r="' . $rowNum . '" ht="18" customHeight="1">';
            foreach ($this->headers as $col => $val) {
                $xml .= '<c r="' . $this->col($col) . $rowNum . '" t="inlineStr" s="1">';
                $xml .= '<is><t>' . $this->x($val) . '</t></is></c>';
            }
            $xml .= '</row>';
            $rowNum++;
        }

        foreach ($this->rows as $row) {
            $xml .= '<row r="' . $rowNum . '">';
            foreach ($row as $col => $val) {
                $xml .= '<c r="' . $this->col($col) . $rowNum . '" t="inlineStr">';
                $xml .= '<is><t>' . $this->x((string) ($val ?? '')) . '</t></is></c>';
            }
            $xml .= '</row>';
            $rowNum++;
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function col(int $n): string
    {
        $n++;
        $s = '';
        while ($n > 0) {
            $n--;
            $s  = chr(65 + ($n % 26)) . $s;
            $n  = intdiv($n, 26);
        }
        return $s;
    }

    private function x(string $val): string
    {
        return htmlspecialchars($val, ENT_XML1, 'UTF-8');
    }

    // ── CSV fallback ─────────────────────────────────────────────────────────

    private function downloadCsv(string $filename): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Cache-Control: max-age=0');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // BOM for Excel UTF-8
        if ($this->headers) {
            fputcsv($out, $this->headers, ';');
        }
        foreach ($this->rows as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }
}
