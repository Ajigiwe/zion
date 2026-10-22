<?php
/**
 * Telegram intake - minimal .xlsx reader.
 *
 * An .xlsx is a zip of XML, which is all this needs: shared strings, the
 * first worksheet, inline strings and numbers. No composer dependency, which
 * matters on shared hosting. Anything unexpected throws with a message the
 * Telegram chat can show the client ("re-save it as CSV").
 *
 * First worksheet only - multi-sheet workbooks are refused loudly rather
 * than silently importing the wrong tab.
 */

declare(strict_types=1);

const INTAKE_XLSX_MAX_ROWS = 5000;

/** 'B7' -> 1 (zero-based column index from a cell reference). */
function intake_xlsx_col_index(string $ref): int
{
    $letters = rtrim(strtoupper($ref), '0123456789');
    $n = 0;
    for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }
    return max(0, $n - 1);
}

/**
 * Convert the first worksheet of an .xlsx file to CSV text.
 *
 * @throws RuntimeException when the file is not a readable .xlsx
 */
function intake_xlsx_to_csv(string $xlsxPath): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('The server is missing the ZIP extension needed to read .xlsx files.');
    }
    $zip = new ZipArchive();
    if ($zip->open($xlsxPath) !== true) {
        throw new RuntimeException('That file could not be opened as .xlsx. Re-save it from Excel and send again.');
    }

    $shared = [];
    $ssIdx  = $zip->locateName('xl/sharedStrings.xml');
    if ($ssIdx !== false) {
        $doc = new DOMDocument();
        if (@$doc->loadXML((string) $zip->getFromIndex($ssIdx), LIBXML_NONET) !== false) {
            foreach ($doc->getElementsByTagName('si') as $si) {
                $text = '';
                foreach ($si->getElementsByTagName('t') as $t) {
                    $text .= $t->textContent;   // rich text runs concatenate
                }
                $shared[] = $text;
            }
        }
    }

    $sheetIdx = $zip->locateName('xl/worksheets/sheet1.xml');
    if ($sheetIdx === false) {
        $zip->close();
        throw new RuntimeException('The first worksheet was not found. Save the file again (or export it as CSV).');
    }
    $sheetXml = (string) $zip->getFromIndex($sheetIdx);
    $zip->close();

    $doc = new DOMDocument();
    if (@$doc->loadXML($sheetXml, LIBXML_NONET) === false) {
        throw new RuntimeException('The worksheet XML could not be read. Save the file again as .xlsx or CSV.');
    }

    $rows = [];
    foreach ($doc->getElementsByTagName('row') as $rowEl) {
        $cells  = [];
        $maxCol = -1;
        foreach ($rowEl->getElementsByTagName('c') as $cell) {
            $ref  = $cell->getAttribute('r');
            $col  = $ref !== '' ? intake_xlsx_col_index($ref) : $maxCol + 1;
            $type = $cell->getAttribute('t');
            $value = '';
            if ($type === 'inlineStr') {
                foreach ($cell->getElementsByTagName('t') as $t) {
                    $value .= $t->textContent;
                }
            } else {
                $v = '';
                foreach ($cell->getElementsByTagName('v') as $ve) {
                    $v = $ve->textContent;
                    break;
                }
                if ($type === 's') {
                    $value = $shared[(int) $v] ?? '';
                } elseif ($type === 'b') {
                    $value = $v === '1' ? 'TRUE' : 'FALSE';
                } else {
                    $value = $v;
                }
            }
            $cells[$col] = $value;
            if ($col > $maxCol) {
                $maxCol = $col;
            }
        }
        $line = [];
        for ($i = 0; $i <= $maxCol; $i++) {
            $line[] = $cells[$i] ?? '';
        }
        $rows[] = $line;
        if (count($rows) >= INTAKE_XLSX_MAX_ROWS) {
            break;
        }
    }

    $fh = fopen('php://temp', 'r+');
    if ($fh === false) {
        throw new RuntimeException('Out of memory while converting the sheet.');
    }
    foreach ($rows as $line) {
        fputcsv($fh, $line);
    }
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);

    return $csv === false ? '' : $csv;
}
