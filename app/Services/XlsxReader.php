<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Validation\ValidationException as V;
use ZipArchive;

class XlsxReader
{
    private function xml(string $bytes): DOMDocument
    {
        if (stripos($bytes, '<!DOCTYPE') !== false || stripos($bytes, '<!ENTITY') !== false) {
            throw V::withMessages(['file' => 'XML entities are not permitted.']);
        }$d = new DOMDocument;
        $old = libxml_use_internal_errors(true);
        try {
            if (! $d->loadXML($bytes, LIBXML_NONET)) {
                throw V::withMessages(['file' => 'Invalid workbook XML.']);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }

return $d;
    }

    /** $raw = true returns every row as a plain list (up to 40 columns) with no header contract; the AI import maps columns itself. */
    public function rows(string $path, array $headers, bool $raw = false): array
    {
        if ($raw) {
            $headers = array_map(fn ($i) => 'c'.$i, range(0, 39));
        }
        $z = new ZipArchive;
        if ($z->open($path) !== true) {
            throw V::withMessages(['file' => 'Invalid XLSX workbook.']);
        }
        try {
            $size = 0;
            for ($i = 0; $i < $z->numFiles; $i++) {
                $stat = $z->statIndex($i);
                $size += $stat['size'];
                if ($size > 20 * 1024 * 1024) {
                    throw V::withMessages(['file' => 'Workbook expands beyond the safe import size.']);
                }if (str_contains(strtolower($stat['name']), 'vbaproject')) {
                    throw V::withMessages(['file' => 'Macro-enabled workbooks are not accepted.']);
                }
            }
            $strings = [];
            $raw = $z->getFromName('xl/sharedStrings.xml');
            if ($raw !== false) {
                foreach ($this->xml($raw)->getElementsByTagNameNS('*', 'si') as $si) {
                    $text = '';
                    foreach ($si->getElementsByTagNameNS('*', 't') as $t) {
                        $text .= $t->textContent;
                    }$strings[] = $text;
                }
            }
            $workbook = $z->getFromName('xl/workbook.xml');
            $relationships = $z->getFromName('xl/_rels/workbook.xml.rels');
            if ($workbook === false || $relationships === false) {
                throw V::withMessages(['file' => 'Workbook structure is missing.']);
            }$sheets = $this->xml($workbook)->getElementsByTagNameNS('*', 'sheet');
            if ($sheets->length !== 1) {
                throw V::withMessages(['file' => 'Use one data worksheet per import; export the required sheet to a separate workbook.']);
            }$sheetId = $sheets->item(0)->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            $target = null;
            foreach ($this->xml($relationships)->getElementsByTagNameNS('*', 'Relationship') as $rel) {
                if ($rel->getAttribute('Id') === $sheetId && $rel->getAttribute('TargetMode') !== 'External') {
                    $target = $rel->getAttribute('Target');
                }
            }if (! $target || str_contains($target, '..')) {
                throw V::withMessages(['file' => 'Invalid worksheet reference.']);
            }$name = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            $sheet = $z->getFromName($name);
            if ($sheet === false) {
                throw V::withMessages(['file' => 'Worksheet data is missing.']);
            }$rows = [];
            foreach ($this->xml($sheet)->getElementsByTagNameNS('*', 'row') as $row) {
                $values = array_fill(0, count($headers), '');
                foreach ($row->getElementsByTagNameNS('*', 'c') as $cell) {
                    if ($cell->getElementsByTagNameNS('*', 'f')->length) {
                        throw V::withMessages(['file' => 'Formulas are not imported. Replace formulas with checked values.']);
                    }preg_match('/^([A-Z]+)\d+$/', $cell->getAttribute('r'), $match);
                    if (! $match) {
                        throw V::withMessages(['file' => 'Invalid cell coordinate.']);
                    }$col = 0;
                    foreach (str_split($match[1]) as $ch) {
                        $col = $col * 26 + ord($ch) - 64;
                    }$col--;
                    $type = $cell->getAttribute('t');
                    $v = $cell->getElementsByTagNameNS('*', 'v')->item(0)?->textContent ?? '';
                    if ($type === 's') {
                        $v = $strings[(int) $v] ?? throw V::withMessages(['file' => 'Invalid shared string.']);
                    } elseif ($type === 'inlineStr') {
                        $v = '';
                        foreach ($cell->getElementsByTagNameNS('*', 't') as $t) {
                            $v .= $t->textContent;
                        }
                    }if ($col >= count($headers)) {
                        if ($v !== '') {
                            throw V::withMessages(['file' => 'Unexpected extra column in worksheet.']);
                        }

continue;
                    }$values[$col] = (string) $v;
                }if (collect($values)->every(fn ($v) => $v === '')) {
                    continue;
                }$rows[] = $values;
                if (count($rows) > 1001) {
                    throw V::withMessages(['file' => 'Maximum 1000 data rows per import.']);
                }
            }
            if ($raw) {
                return array_map(fn ($row) => array_values($row), $rows);
            }
            if (array_shift($rows) !== $headers) {
                throw V::withMessages(['file' => 'Workbook headers must be exactly: '.implode(',', $headers)]);
            }if (! $rows) {
                throw V::withMessages(['file' => 'The worksheet has no data rows.']);
            }

return array_map(fn ($row) => array_combine($headers, $row), $rows);
        } finally {
            $z->close();
        }
    }
}
