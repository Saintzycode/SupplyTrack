<?php
/**
 * report_export.php - PDF / Excel export for the reports (admin only).
 *   ?report=stock|transactions|distribution & format=pdf|xls  (+ filters)
 * Both formats are built from includes/reports_lib.php, so the export
 * always matches the screen. Every export is written to the audit log.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/reports_lib.php';
require_once __DIR__ . '/../includes/pdf.php';

$pdo = db();

$reports = rep_reports();
$report  = $_GET['report'] ?? 'stock';
if (!isset($reports[$report])) $report = 'stock';

$format = strtolower((string)($_GET['format'] ?? 'pdf'));
if (!in_array($format, ['pdf', 'xls'], true)) $format = 'pdf';

$meta   = $reports[$report];
$filter = rep_build_filters($pdo, $report, $_GET);
$count = rep_count($pdo, $report, $filter['where'], $filter['params']);

if ($count > 5000) {
    flash('warning', 'Export is limited to 5,000 rows. Add a date range or narrower filters.');
    redirect('reports/reports.php?tab=' . $report);
}

$rows   = rep_fetch_rows($pdo, $report, $filter['where'], $filter['params'], null, 0);
$totals = rep_totals($pdo, $report, $filter['where'], $filter['params']);

$me          = current_user();
$generatedAt = date('Y-m-d H:i');
$appLine     = APP_NAME . ' ' . APP_SUBTITLE;

// Period + filters lines (period is its own line, so it is dropped from Filters).
$periodLine  = rep_period_line((bool)$meta['period'], $filter['from'], $filter['to']);
$filterLine  = $filter['summary'];
unset($filterLine['Period']);
$filtersText = rep_filters_line($filterLine);

audit_log('export', 'reports', null, "Exported {$meta['title']} as " . strtoupper($format) . " ({$count} rows)", (int)$me['id']);

/** Title-block lines shared by PDF / Excel (in order). */
function exp_title_lines(array $meta, ?string $periodLine, string $appLine, string $generatedAt, string $byName, string $filtersText, int $count, array $totals): array
{
    $lines = [$appLine, $meta['title']];
    if ($periodLine !== null) $lines[] = $periodLine;
    $lines[] = 'Generated: ' . $generatedAt . ' by ' . $byName;
    $lines[] = 'Filters: ' . $filtersText;
    $lines[] = 'Total records: ' . $count;
    foreach ($totals as $label => $value) {
        $lines[] = $label . ': ' . $value;
    }
    return $lines;
}

$totalsPairs = [];
foreach ($totals as $label => $value) $totalsPairs[] = $label . ': ' . $value;
$totalsText  = implode(' | ', $totalsPairs);

$filename = $meta['file'] . '_' . date('Ymd_His');

/* ================================================================== */
/* Excel (.xls = HTML table; no libraries)                            */
/* ================================================================== */
if ($format === 'xls') {
    $colCount = count($meta['columns']);
    $weights  = rep_col_weights($report);
    $totalW   = array_sum($weights);

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: no-store');

    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="utf-8"></head><body>';
    echo '<table border="0" cellspacing="0" cellpadding="4" style="font-family:Calibri,Arial,sans-serif;font-size:11pt;">';
    echo '<colgroup>';
    foreach ($weights as $wt) {
        echo '<col style="width:' . (int)round($wt / $totalW * 1100) . 'px">';
    }
    echo '</colgroup>';

    // Title block
    foreach (exp_title_lines($meta, $periodLine, $appLine, $generatedAt, $me['name'], $filtersText, $count, $totals) as $i => $line) {
        echo '<tr><td colspan="' . $colCount . '" style="' . exp_title_style($i, $line) . '">'
            . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
    echo '<tr><td colspan="' . $colCount . '"></td></tr>';

    // Header row
    echo '<tr>';
    foreach ($meta['columns'] as $col) {
        echo '<td style="background-color:#334155;color:#FFFFFF;font-weight:bold;border:1px solid #E6E8EA;">'
            . htmlspecialchars($col, ENT_QUOTES, 'UTF-8') . '</td>';
    }
    echo '</tr>';

    // Data rows
    echo exp_xls_rows($report, $rows, $colCount, $meta);

    // Totals row
    echo '<tr><td colspan="' . $colCount . '" style="background-color:#F1F5F9;color:#0F172A;font-weight:bold;border:1px solid #E6E8EA;">Totals &mdash; '
        . htmlspecialchars($totalsText, ENT_QUOTES, 'UTF-8') . '</td></tr>';

    echo '</table></body></html>';
    exit;
}

/* ================================================================== */
/* PDF (own SimplePdf)                                                */
/* ================================================================== */
exp_render_pdf($meta, $report, $rows, $totals, $periodLine, $appLine, $generatedAt, $me['name'], $filtersText, $count, $filename);
exit;

/* ================================================================== */
/* Helpers                                                            */
/* ================================================================== */

/** Relative column widths per report (used by Excel + PDF). */
function rep_col_weights(string $report): array
{
    return match ($report) {
        'stock'        => [1.1, 2.6, 1.7, 0.9, 1.0, 1.2, 1.0],
        'transactions' => [1.5, 1.1, 1.2, 2.2, 1.3, 1.2, 1.6, 1.8, 1.8, 1.4],
        default        => [1.2, 2.2, 1.2, 2.2, 0.9, 1.1, 2.0, 1.4],
    };
}

function exp_title_style(int $i, string $line): string
{
    if ($i === 0) return 'color:#334155;font-size:16pt;font-weight:bold;';
    if ($i === 1) return 'color:#0F172A;font-size:13pt;font-weight:bold;';
    return 'color:#475569;';
}

/** Data rows for Excel, with per-cell colours and numeric cells kept numeric. */
function exp_xls_rows(string $report, array $rows, int $colCount, array $meta): string
{
    $c = rep_colors();
    $out = '';
    $i = 0;
    foreach ($rows as $r) {
        $bg = ($i % 2 === 1) ? $c['rowAlt'] : '#FFFFFF';
        $out .= '<tr>';
        foreach (rep_row_cells($report, $r) as $cell) {
            $style = 'background-color:' . $bg . ';border-bottom:1px solid ' . $c['rowLine'] . ';';
            if (!empty($cell['bold']))  $style .= 'font-weight:bold;';
            if (!empty($cell['color'])) $style .= 'color:' . $cell['color'] . ';';
            if (!empty($cell['bg']))    $style .= 'background-color:' . $cell['bg'] . ';';

            $text = (string)$cell['t'];
            if ($cell['align'] === 'r' && $text !== '' && is_numeric($text)) {
                // real number so Excel can sum it
                $out .= '<td style="' . $style . 'text-align:right;">' . $text . '</td>';
            } else {
                // force text for risky values so Excel does not run them as formulas
                if ($text !== '' && str_contains('=+-@', $text[0]) && !is_numeric($text)) {
                    $style .= "mso-number-format:'\\@';";
                }
                $out .= '<td style="' . $style . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</td>';
            }
        }
        $out .= '</tr>';
        $i++;
    }
    return $out;
}

/** Draw the whole report as an A4-landscape PDF and stream it. */
function exp_render_pdf(array $meta, string $report, array $rows, array $totals, ?string $periodLine, string $appLine, string $generatedAt, string $byName, string $filtersText, int $count, string $filename): void
{
    $c = rep_colors();
    $w = 841.89;
    $h = 595.28;
    $margin = 32.0;
    $usable = $w - 2 * $margin;

    $pdf = new SimplePdf($w, $h);
    $pdf->setFooter($appLine . ' | Generated by ' . $byName, 'Page {page} of {total}', $c['muted']);

    // Header block (page 1)
    $y = 46.0;
    $pdf->text($margin, $y, $meta['title'], 20, $c['title'], true);
    $y += 20;
    $pdf->text($margin, $y, $appLine, 11, $c['muted']);
    $y += 16;
    if ($periodLine !== null) {
        $pdf->text($margin, $y, $periodLine, 10, $c['muted']);
        $y += 14;
    }
    $pdf->text($margin, $y, 'Generated: ' . $generatedAt . ' by ' . $byName, 10, $c['muted']);
    $y += 14;
    $pdf->text($margin, $y, 'Filters: ' . $filtersText, 10, $c['muted']);
    $y += 14;
    $pdf->text($margin, $y, 'Total records: ' . $count, 10, $c['muted']);
    $y += 18;

    // Summary block
    if ($totals) {
        $lines = [];
        foreach ($totals as $label => $value) $lines[] = $label . ': ' . $value;
        $blockH = 8 + count($lines) * 14;
        $pdf->rectFill($margin, $y, $usable, $blockH, $c['totalBg']);
        $ty = $y + 6;
        foreach ($lines as $line) {
            $pdf->text($margin + 8, $ty, $line, 10, $c['text'], true);
            $ty += 14;
        }
        $y += $blockH + 16;
    }

    // Column geometry
    $columns = $meta['columns'];
    $weights = rep_col_weights($report);
    $sumW = array_sum($weights);
    $colX = [];
    $colW = [];
    $x = $margin;
    foreach ($weights as $i => $wt) {
        $cw = $usable * ($wt / $sumW);
        $colX[$i] = $x;
        $colW[$i] = $cw;
        $x += $cw;
    }

    $headerH = 20.0;
    $rowH    = 16.0;
    $fontSize = 8.5;
    $pad = 4.0;
    $maxY = $h - 40.0; // leave room for the footer

    // Table header (page 1)
    $pdf->rectFill($margin, $y, $usable, $headerH, $c['headerBg']);
    foreach ($columns as $i => $label) {
        $label = $pdf->ellipsize($label, $colW[$i] - 2 * $pad, 8.5, true);
        $pdf->text($colX[$i] + $pad, $y + 14, $label, 8.5, $c['headerText'], true);
    }
    $y += $headerH;

    $rowIndex = 0;
    if (!$rows) {
        $pdf->text($margin + $pad, $y + 14, 'No records match the selected filters.', 11, $c['muted'], false);
        $y += $rowH;
    }

    foreach ($rows as $r) {
        if ($y + $rowH > $maxY) {
            $pdf->addPage();
            $y = $margin;
            $pdf->rectFill($margin, $y, $usable, $headerH, $c['headerBg']);
            foreach ($columns as $i => $label) {
                $label = $pdf->ellipsize($label, $colW[$i] - 2 * $pad, 8.5, true);
                $pdf->text($colX[$i] + $pad, $y + 14, $label, 8.5, $c['headerText'], true);
            }
            $y += $headerH;
        }

        $bg = ($rowIndex % 2 === 1) ? $c['rowAlt'] : '#FFFFFF';
        $pdf->rectFill($margin, $y, $usable, $rowH, $bg);

        $cells = rep_row_cells($report, $r);
        foreach ($cells as $i => $cell) {
            if (!empty($cell['bg'])) {
                $pdf->rectFill($colX[$i], $y, $colW[$i], $rowH, $cell['bg']);
            }
            $text = $pdf->ellipsize((string)$cell['t'], $colW[$i] - 2 * $pad, $fontSize, !empty($cell['bold']));
            $tx = $colX[$i] + $pad;
            if (($cell['align'] ?? 'l') === 'r') {
                $tx = $colX[$i] + $colW[$i] - $pad - $pdf->textWidth($text, $fontSize, !empty($cell['bold']));
            }
            $pdf->text($tx, $y + 11, $text, $fontSize, $cell['color'] ?? $c['text'], !empty($cell['bold']));
        }

        $pdf->line($margin, $y + $rowH, $margin + $usable, $y + $rowH, $c['rowLine'], 0.5);
        $y += $rowH;
        $rowIndex++;
    }

    // Totals row
    if ($y + 22 > $maxY) {
        $pdf->addPage();
        $y = $margin;
    }
    $totalsLines = [];
    foreach ($totals as $label => $value) $totalsLines[] = $label . ': ' . $value;
    $pdf->rectFill($margin, $y, $usable, 22, $c['totalBg']);
    $pdf->text($margin + 8, $y + 15, 'Totals  —  ' . implode('   |   ', $totalsLines), 10, $c['text'], true);

    $bytes = $pdf->output();

    if (ob_get_level() > 0) {
        while (ob_get_level() > 0) ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: no-store');
    echo $bytes;
}
