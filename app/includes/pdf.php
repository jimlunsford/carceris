<?php

declare(strict_types=1);

function carceris_pdf_clean_text(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? $text;
    $text = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", "    "], $text);
    $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

    if ($converted === false) {
        $converted = preg_replace('/[^\x20-\x7E\n]/', '', $text) ?? $text;
    }

    return (string) $converted;
}

function carceris_pdf_escape_text(string $text): string
{
    return str_replace(
        ['\\', '(', ')'],
        ['\\\\', '\\(', '\\)'],
        carceris_pdf_clean_text($text)
    );
}

function carceris_pdf_wrap_text(string $text, int $maxChars): array
{
    $text = carceris_pdf_clean_text($text);
    $paragraphs = explode("\n", $text);
    $lines = [];

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);

        if ($paragraph === '') {
            $lines[] = '';
            continue;
        }

        $words = preg_split('/\s+/', $paragraph) ?: [];
        $line = '';

        foreach ($words as $word) {
            if ($line === '') {
                $line = $word;
                continue;
            }

            if (strlen($line . ' ' . $word) <= $maxChars) {
                $line .= ' ' . $word;
                continue;
            }

            $lines[] = $line;
            $line = $word;
        }

        if ($line !== '') {
            $lines[] = $line;
        }
    }

    return $lines;
}

function carceris_pdf_wrap_text_for_width(string $text, float $width, int $fontSize): array
{
    $averageCharacterWidth = max(3.6, $fontSize * 0.46);
    $maxChars = max(8, (int) floor($width / $averageCharacterWidth));

    return carceris_pdf_wrap_text($text, $maxChars);
}

function carceris_pdf_new_document(): array
{
    return [
        'pages' => [],
        'current' => [],
        'y' => 756.0,
        'page_width' => 612.0,
        'page_height' => 792.0,
        'margin_left' => 30.0,
        'margin_right' => 30.0,
        'margin_bottom' => 40.0,
        'margin_top' => 756.0,
    ];
}

function carceris_pdf_add_page(array &$pdf): void
{
    if ($pdf['current']) {
        $pdf['pages'][] = $pdf['current'];
    }

    $pdf['current'] = [];
    $pdf['y'] = $pdf['margin_top'];
}

function carceris_pdf_finish_pages(array &$pdf): void
{
    if ($pdf['current']) {
        $pdf['pages'][] = $pdf['current'];
        $pdf['current'] = [];
    }

    if (!$pdf['pages']) {
        $pdf['pages'][] = [];
    }
}

function carceris_pdf_number(float|int $value): string
{
    $formatted = number_format((float) $value, 2, '.', '');

    return rtrim(rtrim($formatted, '0'), '.');
}

function carceris_pdf_write_text_at(array &$pdf, string $text, float $x, float $y, int $fontSize = 10, bool $bold = false): void
{
    if (!$pdf['current']) {
        carceris_pdf_add_page($pdf);
    }

    $font = $bold ? 'F2' : 'F1';
    $escaped = carceris_pdf_escape_text($text);

    $pdf['current'][] = sprintf(
        '0 g 0 G BT /%s %d Tf %s %s Td (%s) Tj ET',
        $font,
        $fontSize,
        carceris_pdf_number($x),
        carceris_pdf_number($y),
        $escaped
    );
}

function carceris_pdf_write_line(array &$pdf, string $text, int $fontSize = 10, bool $bold = false, int $indent = 0, int $leading = 14): void
{
    if (!$pdf['current']) {
        carceris_pdf_add_page($pdf);
    }

    if ($pdf['y'] < $pdf['margin_bottom']) {
        carceris_pdf_add_page($pdf);
    }

    $x = $pdf['margin_left'] + $indent;
    $y = $pdf['y'];

    carceris_pdf_write_text_at($pdf, $text, $x, $y, $fontSize, $bold);

    $pdf['y'] -= $leading;
}

function carceris_pdf_write_wrapped(array &$pdf, string $text, int $fontSize = 10, bool $bold = false, int $indent = 0, int $leading = 14, int $maxChars = 92): void
{
    foreach (carceris_pdf_wrap_text($text, $maxChars) as $line) {
        carceris_pdf_write_line($pdf, $line, $fontSize, $bold, $indent, $leading);
    }
}

function carceris_pdf_draw_line(array &$pdf, float $x1, float $y1, float $x2, float $y2, float $width = 0.5): void
{
    if (!$pdf['current']) {
        carceris_pdf_add_page($pdf);
    }

    $pdf['current'][] = sprintf(
        '%s w %s %s m %s %s l S',
        carceris_pdf_number($width),
        carceris_pdf_number($x1),
        carceris_pdf_number($y1),
        carceris_pdf_number($x2),
        carceris_pdf_number($y2)
    );
}

function carceris_pdf_draw_rect(array &$pdf, float $x, float $y, float $width, float $height, ?float $fillGray = null, bool $stroke = true): void
{
    if (!$pdf['current']) {
        carceris_pdf_add_page($pdf);
    }

    if ($fillGray !== null) {
        $pdf['current'][] = sprintf(
            '%s g %s %s %s %s re f 0 g 0 G',
            carceris_pdf_number($fillGray),
            carceris_pdf_number($x),
            carceris_pdf_number($y),
            carceris_pdf_number($width),
            carceris_pdf_number($height)
        );
    }

    if ($stroke) {
        $pdf['current'][] = sprintf(
            '0.5 w %s %s %s %s re S',
            carceris_pdf_number($x),
            carceris_pdf_number($y),
            carceris_pdf_number($width),
            carceris_pdf_number($height)
        );
    }
}

function carceris_pdf_write_rule(array &$pdf): void
{
    if (!$pdf['current']) {
        carceris_pdf_add_page($pdf);
    }

    if ($pdf['y'] < $pdf['margin_bottom']) {
        carceris_pdf_add_page($pdf);
    }

    $y = $pdf['y'] + 4;
    $x1 = $pdf['margin_left'];
    $x2 = $pdf['page_width'] - $pdf['margin_right'];

    carceris_pdf_draw_line($pdf, $x1, $y, $x2, $y, 1.5);
    $pdf['y'] -= 12;
}

function carceris_pdf_build(array $pdf): string
{
    carceris_pdf_finish_pages($pdf);

    $objects = [];
    $pages = $pdf['pages'];
    $pageCount = count($pages);

    $catalogId = 1;
    $pagesId = 2;
    $fontRegularId = 3;
    $fontBoldId = 4;
    $firstPageId = 5;

    $objects[$catalogId] = '<< /Type /Catalog /Pages ' . $pagesId . ' 0 R >>';

    $kids = [];
    $nextId = $firstPageId;

    foreach ($pages as $commands) {
        $pageId = $nextId++;
        $contentId = $nextId++;
        $kids[] = $pageId . ' 0 R';

        $content = implode("\n", $commands) . "\n";
        $objects[$contentId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";

        $objects[$pageId] = '<< /Type /Page /Parent ' . $pagesId . ' 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 ' . $fontRegularId . ' 0 R /F2 ' . $fontBoldId . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
    }

    $objects[$pagesId] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
    $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

    ksort($objects);

    $output = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($output);
        $output .= $id . " 0 obj\n" . $body . "\nendobj\n";
    }

    $xrefOffset = strlen($output);
    $maxId = max(array_keys($objects));

    $output .= "xref\n";
    $output .= "0 " . ($maxId + 1) . "\n";
    $output .= "0000000000 65535 f \n";

    for ($i = 1; $i <= $maxId; $i++) {
        $output .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }

    $output .= "trailer\n";
    $output .= "<< /Size " . ($maxId + 1) . " /Root " . $catalogId . " 0 R >>\n";
    $output .= "startxref\n";
    $output .= $xrefOffset . "\n";
    $output .= "%%EOF\n";

    return $output;
}

function carceris_pdf_table_columns(): array
{
    return [
        ['key' => 'time', 'label' => 'Time', 'width' => 46.0],
        ['key' => 'category', 'label' => 'Category', 'width' => 62.0],
        ['key' => 'priority', 'label' => 'Priority', 'width' => 48.0],
        ['key' => 'location', 'label' => 'Location', 'width' => 62.0],
        ['key' => 'inmate', 'label' => 'Inmate', 'width' => 64.0],
        ['key' => 'entry', 'label' => 'Entry', 'width' => 188.0],
        ['key' => 'officer', 'label' => 'Officer', 'width' => 82.0],
    ];
}

function carceris_pdf_entry_row_cells(array $entry): array
{
    $priority = ucfirst((string) ($entry['priority'] ?? 'normal'));
    $status = entry_status_label($entry);

    if ($status !== 'Active') {
        $priority .= "\n" . $status;
    }

    $entryText = trim((string) ($entry['entry_text'] ?? ''));

    $lateNote = entry_late_note($entry);

    if ($lateNote !== '') {
        $entryText .= "\n" . $lateNote;
    }

    $statusNote = entry_status_note($entry);

    if ($statusNote !== '') {
        $entryText .= "\n" . $statusNote;
    }

    return [
        'time' => format_log_time($entry['event_time']),
        'category' => (string) ($entry['category'] ?? ''),
        'priority' => $priority,
        'location' => (string) ($entry['location'] ?? ''),
        'inmate' => (string) ($entry['inmate_name'] ?? ''),
        'entry' => $entryText,
        'officer' => (string) ($entry['created_by_name'] ?? 'Unknown'),
    ];
}

function carceris_pdf_write_table_header(array &$pdf): void
{
    $columns = carceris_pdf_table_columns();
    $x = $pdf['margin_left'];
    $top = $pdf['y'];
    $height = 18.0;

    carceris_pdf_draw_rect(
        $pdf,
        $pdf['margin_left'],
        $top - $height,
        $pdf['page_width'] - $pdf['margin_left'] - $pdf['margin_right'],
        $height,
        0.93,
        true
    );

    foreach ($columns as $column) {
        carceris_pdf_draw_rect($pdf, $x, $top - $height, $column['width'], $height, null, true);
        carceris_pdf_write_text_at($pdf, $column['label'], $x + 3, $top - 12, 8, true);
        $x += $column['width'];
    }

    $pdf['y'] -= $height;
}

function carceris_pdf_calculate_entry_row_height(array $cells): float
{
    $columns = carceris_pdf_table_columns();
    $maxLines = 1;

    foreach ($columns as $column) {
        $lines = carceris_pdf_wrap_text_for_width(
            (string) ($cells[$column['key']] ?? ''),
            $column['width'] - 6,
            9
        );

        $maxLines = max($maxLines, count($lines));
    }

    return max(22.0, ($maxLines * 11.0) + 8.0);
}

function carceris_pdf_write_entry_table_row(array &$pdf, array $entry): void
{
    $columns = carceris_pdf_table_columns();
    $cells = carceris_pdf_entry_row_cells($entry);
    $rowHeight = carceris_pdf_calculate_entry_row_height($cells);

    if (($pdf['y'] - $rowHeight) < $pdf['margin_bottom']) {
        carceris_pdf_add_page($pdf);
        carceris_pdf_write_line($pdf, carceris_report_title([]) . ' (continued)', 12, true, 0, 18);
        carceris_pdf_write_table_header($pdf);
    }

    $x = $pdf['margin_left'];
    $top = $pdf['y'];

    foreach ($columns as $column) {
        carceris_pdf_draw_rect($pdf, $x, $top - $rowHeight, $column['width'], $rowHeight, null, true);

        $lines = carceris_pdf_wrap_text_for_width(
            (string) ($cells[$column['key']] ?? ''),
            $column['width'] - 6,
            9
        );

        $textY = $top - 11;

        foreach ($lines as $line) {
            if ($textY < ($top - $rowHeight + 6)) {
                break;
            }

            carceris_pdf_write_text_at($pdf, $line, $x + 3, $textY, 9, $column['key'] === 'priority' && str_contains((string) ($cells[$column['key']] ?? ''), 'Voided'));
            $textY -= 11;
        }

        $x += $column['width'];
    }

    $pdf['y'] -= $rowHeight;
}

function carceris_render_log_report_pdf(array $logDay, array $entries): string
{
    $pdf = carceris_pdf_new_document();
    carceris_pdf_add_page($pdf);

    $title = carceris_report_title($logDay);

    carceris_pdf_write_line($pdf, $title, 22, true, 0, 28);
    carceris_pdf_write_rule($pdf);

    carceris_pdf_write_line($pdf, 'System: ' . carceris_header_brand_name(), 10, false, 0, 14);
    carceris_pdf_write_line($pdf, 'Operational Period: ' . $logDay['log_label'], 10, false, 0, 14);
    carceris_pdf_write_line($pdf, 'Status: ' . ucfirst((string) ($logDay['status'] ?? '')), 10, false, 0, 14);
    carceris_pdf_write_line($pdf, 'Printed: ' . carceris_format_datetime(new DateTimeImmutable('now')), 10, false, 0, 18);

    if (!$entries) {
        carceris_pdf_write_wrapped($pdf, 'No log entries were recorded for this operational period.', 11, false, 0, 15, 88);
        return carceris_pdf_build($pdf);
    }

    carceris_pdf_write_table_header($pdf);

    foreach ($entries as $entry) {
        carceris_pdf_write_entry_table_row($pdf, $entry);
    }

    return carceris_pdf_build($pdf);
}
