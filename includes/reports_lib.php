<?php
/**
 * includes/reports_lib.php
 * Single source of truth for the three reports. Used by reports.php (screen)
 * and report_export.php (PDF / Excel) so every surface shows the same
 * columns, rows, summary and totals.
 *
 * Reports: stock (Stock Status), transactions (Stock Transactions),
 *          distribution (Distribution).
 */
require_once __DIR__ . '/functions.php';

/* ------------------------------------------------------------------ */
/* Catalogue                                                          */
/* ------------------------------------------------------------------ */

/** Report catalogue: title, export filename base, whether it has a period, columns and alignment. */
function rep_reports(): array
{
    return [
        'stock' => [
            'title'   => 'Stock Status Report',
            'file'    => 'stock_status_report',
            'period'  => false,
            'columns' => ['SKU', 'Item', 'Category', 'Unit', 'On hand', 'Reorder point', 'Status'],
            'align'   => ['l', 'l', 'l', 'l', 'r', 'r', 'l'],
        ],
        'transactions' => [
            'title'   => 'Stock Transactions Report',
            'file'    => 'stock_transactions_report',
            'period'  => true,
            'columns' => ['Date', 'Type', 'SKU', 'Item', 'Quantity change', 'Balance after', 'Supplier', 'Reason', 'Remarks', 'Recorded by'],
            'align'   => ['l', 'l', 'l', 'l', 'r', 'r', 'l', 'l', 'l', 'l'],
        ],
        'distribution' => [
            'title'   => 'Distribution Report',
            'file'    => 'distribution_report',
            'period'  => true,
            'columns' => ['Date', 'School', 'SKU', 'Item', 'Unit', 'Quantity', 'Remarks', 'Recorded by'],
            'align'   => ['l', 'l', 'l', 'l', 'l', 'r', 'l', 'l'],
        ],
    ];
}

/** Shared colour tokens for the PDF and Excel exports (hex). Mirror docs/design.md. */
function rep_colors(): array
{
    return [
        'title'      => '#0F172A',
        'muted'      => '#475569',
        'headerBg'   => '#334155',
        'headerText' => '#FFFFFF',
        'rowAlt'     => '#F8FAFC',
        'rowLine'    => '#E6E8EA',
        'lowText'    => '#DC2626', 'lowBg'  => '#FEF2F2',
        'okText'     => '#059669', 'okBg'   => '#F0FDF4',
        'outText'    => '#D97706', 'outBg'  => '#FFFBEB',
        'adjText'    => '#065F46', 'adjBg'  => '#E8F5EF',
        'totalBg'    => '#F1F5F9',
        'text'       => '#0F172A',
        'neg'        => '#DC2626',
        'pos'        => '#059669',
    ];
}

function rep_txn_type_label(string $t): string
{
    return match ($t) {
        'stock_in'  => 'Stock-in',
        'stock_out' => 'Stock-out',
        default     => 'Adjustment',
    };
}

function rep_view(string $report): string
{
    return match ($report) {
        'stock'        => 'v_stock_status s',
        'transactions' => 'v_stock_transaction_report',
        default        => 'v_distribution_report',
    };
}

/* ------------------------------------------------------------------ */
/* Weekly / month presets (Asia/Manila; set in config/config.php)     */
/* ------------------------------------------------------------------ */

/** value => label for the Period selector on the period-based reports. */
function rep_period_options(): array
{
    return [
        'custom'      => 'Custom',
        'this_week'   => 'This week (Mon-Sun)',
        'last_week'   => 'Last week (Mon-Sun)',
        'last_7_days' => 'Last 7 days',
        'this_month'  => 'This month',
    ];
}

/**
 * Resolve a period preset to a [from, to] date range.
 * @return array{from:string,to:string}|null  null = custom / unknown.
 */
function rep_period_range(string $period): ?array
{
    return match ($period) {
        'this_week'   => ['from' => date('Y-m-d', strtotime('monday this week')), 'to' => date('Y-m-d', strtotime('sunday this week'))],
        'last_week'   => ['from' => date('Y-m-d', strtotime('monday last week')), 'to' => date('Y-m-d', strtotime('sunday last week'))],
        'last_7_days' => ['from' => date('Y-m-d', strtotime('-6 days')),            'to' => date('Y-m-d')],
        'this_month'  => ['from' => date('Y-m-01'),                                'to' => date('Y-m-t')],
        default       => null,
    };
}

/** "Period: YYYY-MM-DD to YYYY-MM-DD" (or a sensible fallback). Null for reports without a period. */
function rep_period_line(bool $hasPeriod, string $from, string $to): ?string
{
    if (!$hasPeriod) return null;
    if ($from !== '' && $to !== '') return 'Period: ' . $from . ' to ' . $to;
    if ($from !== '')               return 'Period: from ' . $from;
    if ($to !== '')                 return 'Period: until ' . $to;
    return 'Period: All dates';
}

/* ------------------------------------------------------------------ */
/* Filters                                                            */
/* ------------------------------------------------------------------ */

/**
 * Build the WHERE clause, bound params, filter summary and the effective
 * period range.
 * @return array{where:string,params:array,summary:array,from:string,to:string}
 */
function rep_build_filters(PDO $pdo, string $report, array $get): array
{
    $where = [];
    $params = [];
    $summary = [];
    $from = '';
    $to   = '';

    if ($report === 'stock') {
        // Stock Status is a snapshot of active items only.
        $where[] = 's.is_active = 1';

        $q      = trim($get['stock_q'] ?? '');
        $status = trim($get['stock_status'] ?? '');
        $cat    = (int)($get['stock_cat'] ?? 0);

        if ($q !== '') {
            $where[] = '(s.sku LIKE :sq1 OR s.item_name LIKE :sq2 OR s.category_name LIKE :sq3)';
            $like = '%' . $q . '%';
            $params[':sq1'] = $like;
            $params[':sq2'] = $like;
            $params[':sq3'] = $like;
            $summary['Search'] = $q;
        }
        if ($status === 'Normal' || $status === 'Low') {
            $where[] = 's.status = :ss';
            $params[':ss'] = $status;
            $summary['Status'] = $status;
        }
        if ($cat > 0) {
            $where[] = 's.item_id IN (SELECT item_id FROM items WHERE category_id = :sc)';
            $params[':sc'] = $cat;
            $nm = $pdo->prepare('SELECT category_name FROM categories WHERE category_id = :c');
            $nm->execute([':c' => $cat]);
            $summary['Category'] = (string)$nm->fetchColumn();
        }
    } elseif ($report === 'transactions') {
        [$from, $to] = rep_resolve_range($get, 'tx_from', 'tx_to');
        $item = (int)($get['tx_item'] ?? 0);
        $type = trim($get['tx_type'] ?? '');
        if (!in_array($type, ['', 'stock_in', 'stock_out', 'adjustment'], true)) $type = '';

        if ($from !== '') { $where[] = 'transaction_date >= :tfrom'; $params[':tfrom'] = $from . ' 00:00:00'; }
        if ($to !== '')   { $where[] = 'transaction_date <= :tto';   $params[':tto']   = $to . ' 23:59:59'; }
        if ($item > 0) {
            $where[] = 'item_id = :titem';
            $params[':titem'] = $item;
            $nm = $pdo->prepare('SELECT sku, item_name FROM items WHERE item_id = :i');
            $nm->execute([':i' => $item]);
            if ($r = $nm->fetch(PDO::FETCH_ASSOC)) $summary['Item'] = $r['sku'] . ' - ' . $r['item_name'];
        }
        if ($type !== '') { $where[] = 'transaction_type = :ttype'; $params[':ttype'] = $type; $summary['Type'] = rep_txn_type_label($type); }
    } else { // distribution
        [$from, $to] = rep_resolve_range($get, 'dist_from', 'dist_to');
        $school = (int)($get['dist_school'] ?? 0);
        $item   = (int)($get['dist_item'] ?? 0);

        if ($from !== '') { $where[] = 'distribution_date >= :dfrom'; $params[':dfrom'] = $from . ' 00:00:00'; }
        if ($to !== '')   { $where[] = 'distribution_date <= :dto';   $params[':dto']   = $to . ' 23:59:59'; }
        if ($school > 0) {
            $where[] = 'school_id = :dschool';
            $params[':dschool'] = $school;
            $nm = $pdo->prepare('SELECT school_name FROM schools WHERE school_id = :i');
            $nm->execute([':i' => $school]);
            $summary['School'] = (string)$nm->fetchColumn();
        }
        if ($item > 0) {
            $where[] = 'item_id = :ditem';
            $params[':ditem'] = $item;
            $nm = $pdo->prepare('SELECT sku, item_name FROM items WHERE item_id = :i');
            $nm->execute([':i' => $item]);
            if ($r = $nm->fetch(PDO::FETCH_ASSOC)) $summary['Item'] = $r['sku'] . ' - ' . $r['item_name'];
        }
    }

    // Period summary + range keys (used by the report header / export links).
    if ($from !== '' || $to !== '') {
        $summary = ['Period' => ($from !== '' ? $from : '...') . ' to ' . ($to !== '' ? $to : '...')] + $summary;
    }

    return [
        'where'   => $where ? 'WHERE ' . implode(' AND ', $where) : '',
        'params'  => $params,
        'summary' => $summary,
        'from'    => $from,
        'to'      => $to,
    ];
}

/** Resolve the effective from/to for a period report (preset wins over manual dates). */
function rep_resolve_range(array $get, string $fromKey, string $toKey): array
{
    $period = (string)($get['period'] ?? 'custom');
    $rng = rep_period_range($period);
    if ($rng !== null) return [$rng['from'], $rng['to']];
    return [trim($get[$fromKey] ?? ''), trim($get[$toKey] ?? '')];
}

/* ------------------------------------------------------------------ */
/* Queries                                                            */
/* ------------------------------------------------------------------ */

function rep_count(PDO $pdo, string $report, string $whereSql, array $params): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM ' . rep_view($report) . ' ' . $whereSql);
    $st->execute($params);
    return (int)$st->fetchColumn();
}

/** Fetch rows (all when $limit is null, otherwise a page). */
function rep_fetch_rows(PDO $pdo, string $report, string $whereSql, array $params, ?int $limit, int $offset = 0): array
{
    $select = [
        'stock'        => 'SELECT s.item_id, s.sku, s.item_name, s.category_name, s.unit_of_measure,
                                  s.quantity_on_hand, s.reorder_point, s.status, s.is_active
                             FROM v_stock_status s',
        'transactions' => 'SELECT transaction_id, transaction_date, transaction_type, item_id, sku, item_name,
                                  quantity_change, quantity_after, supplier_name, reason, remarks, recorded_by_name
                             FROM v_stock_transaction_report',
        'distribution' => 'SELECT distribution_id, distribution_date, school_id, school_name, item_id, sku,
                                  item_name, unit_of_measure, quantity, remarks, recorded_by, recorded_by_name
                             FROM v_distribution_report',
    ];
    $order = [
        'stock'        => "ORDER BY s.status = 'Low' DESC, s.item_name ASC",
        'transactions' => 'ORDER BY transaction_date DESC, transaction_id DESC',
        'distribution' => 'ORDER BY distribution_date DESC, distribution_id DESC',
    ];

    $sql = $select[$report] . ' ' . $whereSql . ' ' . $order[$report];
    if ($limit !== null) $sql .= ' LIMIT :lim OFFSET :off';

    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) $st->bindValue($k, $v);
    if ($limit !== null) {
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
    }
    $st->execute();
    return $st->fetchAll();
}

/* ------------------------------------------------------------------ */
/* Totals / summary (plain integers - formatted per surface)          */
/* ------------------------------------------------------------------ */

/** Ordered totals for the summary block and the totals row. Values are raw ints. */
function rep_totals(PDO $pdo, string $report, string $whereSql, array $params): array
{
    if ($report === 'stock') {
        $st = $pdo->prepare(
            "SELECT COUNT(*) AS c,
                    COALESCE(SUM(CASE WHEN s.status = 'Low' THEN 1 ELSE 0 END), 0) AS low
               FROM v_stock_status s $whereSql"
        );
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return [
            'Total items'     => (int)$r['c'],
            'Low-stock items' => (int)$r['low'],
        ];
    }

    if ($report === 'transactions') {
        $st = $pdo->prepare(
            "SELECT COUNT(*) AS c,
                    COALESCE(SUM(CASE WHEN transaction_type = 'stock_in'  THEN quantity_change ELSE 0 END), 0) AS in_q,
                    COALESCE(SUM(CASE WHEN transaction_type = 'stock_out' THEN -quantity_change ELSE 0 END), 0) AS out_q,
                    COALESCE(SUM(quantity_change), 0) AS net
               FROM v_stock_transaction_report $whereSql"
        );
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return [
            'Total stock-in quantity'  => (int)$r['in_q'],
            'Total stock-out quantity' => (int)$r['out_q'],
            'Net change'               => (int)$r['net'],
            'Transactions'             => (int)$r['c'],
        ];
    }

    $st = $pdo->prepare(
        "SELECT COUNT(DISTINCT distribution_id) AS c,
                COUNT(DISTINCT school_id) AS schools,
                COALESCE(SUM(quantity), 0) AS q
           FROM v_distribution_report $whereSql"
    );
    $st->execute($params);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return [
        'Distributions'             => (int)$r['c'],
        'Schools served'            => (int)$r['schools'],
        'Total quantity distributed'=> (int)$r['q'],
    ];
}

/** Human-readable filter line: "Key: value | Key: value" or "None (all records)". */
function rep_filters_line(array $summary): string
{
    if (!$summary) return 'None (all records)';
    $parts = [];
    foreach ($summary as $k => $v) $parts[] = $k . ': ' . $v;
    return implode(' | ', $parts);
}

/* ------------------------------------------------------------------ */
/* Row + summary cells (structured, colour-aware)                     */
/* ------------------------------------------------------------------ */

/** Structured row: list of cells ['t'=>string,'align'=>'l|r','bold'=>bool,'color'=>?hex,'bg'=>?hex]. */
function rep_row_cells(string $report, array $r): array
{
    $c = rep_colors();
    $num = fn($v): array => ['t' => (string)(int)$v, 'align' => 'r', 'bold' => false, 'color' => null, 'bg' => null];
    $txt = fn($v): array => ['t' => ($v === null ? '' : (string)$v), 'align' => 'l', 'bold' => false, 'color' => null, 'bg' => null];

    if ($report === 'stock') {
        $low = (int)$r['quantity_on_hand'] <= (int)$r['reorder_point'];
        $status = $low ? 'Low' : 'Normal';
        return [
            $txt($r['sku']),
            ['t' => (string)$r['item_name'], 'align' => 'l', 'bold' => true,  'color' => null, 'bg' => null],
            $txt($r['category_name']),
            $txt($r['unit_of_measure']),
            ['t' => (string)(int)$r['quantity_on_hand'], 'align' => 'r', 'bold' => true,
             'color' => $low ? $c['lowText'] : null, 'bg' => null],
            $num($r['reorder_point']),
            ['t' => $status, 'align' => 'l', 'bold' => true,
             'color' => $low ? $c['lowText'] : $c['okText'], 'bg' => $low ? $c['lowBg'] : $c['okBg']],
        ];
    }

    if ($report === 'transactions') {
        $q = (int)$r['quantity_change'];
        [$typeColor, $typeBg] = match ($r['transaction_type']) {
            'stock_in'  => [$c['okText'],  $c['okBg']],
            'stock_out' => [$c['outText'], $c['outBg']],
            default     => [$c['adjText'], $c['adjBg']],
        };
        return [
            $txt(date('Y-m-d H:i', strtotime((string)$r['transaction_date']))),
            ['t' => rep_txn_type_label($r['transaction_type']), 'align' => 'l', 'bold' => true, 'color' => $typeColor, 'bg' => $typeBg],
            $txt($r['sku']),
            ['t' => (string)$r['item_name'], 'align' => 'l', 'bold' => true, 'color' => null, 'bg' => null],
            ['t' => (string)$q, 'align' => 'r', 'bold' => true,
             'color' => $q > 0 ? $c['pos'] : ($q < 0 ? $c['neg'] : null), 'bg' => null],
            $num($r['quantity_after']),
            $txt($r['supplier_name']),
            $txt($r['reason']),
            $txt($r['remarks']),
            $txt($r['recorded_by_name']),
        ];
    }

    return [
        $txt(substr((string)$r['distribution_date'], 0, 10)),
        ['t' => (string)$r['school_name'], 'align' => 'l', 'bold' => true, 'color' => null, 'bg' => null],
        $txt($r['sku']),
        $txt($r['item_name']),
        $txt($r['unit_of_measure']),
        ['t' => (string)(int)$r['quantity'], 'align' => 'r', 'bold' => true, 'color' => null, 'bg' => null],
        $txt($r['remarks']),
        $txt($r['recorded_by_name']),
    ];
}
