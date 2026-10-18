<?php
/**
 * index.php - Dashboard (FR-7.1)
 * Uses views: v_dashboard_summary, v_stock_status, v_stock_transaction_report
 */
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';

$pdo = db();
$summary = $pdo->query('SELECT * FROM v_dashboard_summary')->fetch()
    ?: ['total_active_items' => 0, 'low_stock_items' => 0, 'transactions_this_week' => 0, 'open_alerts' => 0];

$lowItems = $pdo->query(
    "SELECT sku, item_name, quantity_on_hand, reorder_point, status
       FROM v_stock_status
      WHERE is_active = 1 AND status = 'Low'
      ORDER BY (CAST(quantity_on_hand AS SIGNED) - CAST(reorder_point AS SIGNED)) ASC, item_name
      LIMIT 5"
)->fetchAll();

$recent = $pdo->query(
    'SELECT transaction_date, transaction_type, item_name, quantity_change
       FROM v_stock_transaction_report
      ORDER BY transaction_date DESC, transaction_id DESC
      LIMIT 5'
)->fetchAll();

$byCat = $pdo->query(
    "SELECT COALESCE(category_name, 'Uncategorized') AS category,
            SUM(quantity_on_hand) AS units
       FROM v_stock_status
      WHERE is_active = 1
      GROUP BY COALESCE(category_name, 'Uncategorized')
      ORDER BY units DESC, category
      LIMIT 8"
)->fetchAll();

/** type => [label, css class] */
function txn_badge(string $type): array
{
    return match ($type) {
        'stock_in'   => ['Stock-in',  'badge-in'],
        'stock_out'  => ['Stock-out', 'badge-out'],
        default      => ['Adjust',    'badge-adj'],
    };
}

$stats = [
    ['Total active items',        $summary['total_active_items'],   'bi-box-seam',            'bg-primary-soft'],
    ['At / below reorder point',  $summary['low_stock_items'],      'bi-exclamation-triangle','bg-danger-soft'],
    ['Transactions this week',    $summary['transactions_this_week'],'bi-activity',           'bg-success-soft'],
    ['Open low-stock alerts',     $summary['open_alerts'],          'bi-bell',                'bg-warning-soft'],
];
?>
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p>Real-time overview of school-supply inventory.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($stats as [$label, $value, $icon, $tone]): ?>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon <?= e($tone) ?>"><i class="bi <?= e($icon) ?>"></i></span>
                <div>
                    <div class="stat-label"><?= e($label) ?></div>
                    <div class="stat-value"><?= number_format((int)$value) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card-ui mb-4">
    <div class="card-head">
        <h2>Stock by category</h2>
        <span class="text-muted small">On-hand units across active items</span>
    </div>
    <div class="p-3">
        <?php if (!$byCat): ?>
            <p class="text-center text-muted py-5 mb-0">No active items to chart yet.</p>
        <?php else: ?>
            <div class="chart-wrap"><canvas id="stockByCategory"></canvas></div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card-ui">
            <div class="card-head">
                <h2>Items at or below reorder point</h2>
                <?php if (is_admin()): ?><a href="<?= e(url('reports.php?tab=stock')) ?>" class="small fw-semibold text-decoration-none">View stock status</a><?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-ui">
                    <thead><tr><th>Item</th><th>SKU</th><th class="text-end">On hand</th><th class="text-end">Reorder</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$lowItems): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">All items are above their reorder point.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($lowItems as $r): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($r['item_name']) ?></td>
                            <td class="text-muted"><?= e($r['sku']) ?></td>
                            <td class="text-end"><?= number_format((int)$r['quantity_on_hand']) ?></td>
                            <td class="text-end"><?= number_format((int)$r['reorder_point']) ?></td>
                            <td><span class="badge-soft badge-low">Low</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card-ui">
            <div class="card-head">
                <h2>Recent transactions</h2>
                <?php if (is_admin()): ?><a href="<?= e(url('reports.php?tab=transactions')) ?>" class="small fw-semibold text-decoration-none">View all</a><?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-ui">
                    <thead><tr><th>Type</th><th>Item</th><th class="text-end">Qty</th></tr></thead>
                    <tbody>
                    <?php if (!$recent): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">No transactions yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recent as $t): [$lbl, $cls] = txn_badge($t['transaction_type']); $q = (int)$t['quantity_change']; ?>
                        <tr>
                            <td><span class="badge-soft <?= e($cls) ?>"><?= e($lbl) ?></span></td>
                            <td class="fw-semibold"><?= e($t['item_name']) ?></td>
                            <td class="text-end"><?= $q > 0 ? '+' : '' ?><?= number_format($q) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script id="stockByCategoryData" type="application/json"><?= json_encode(
    array_map(fn($r) => ['category' => $r['category'], 'units' => (int)$r['units']], $byCat),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
<script src="<?= e(url('assets/js/dashboard-chart.js')) ?>?v=<?= e(ASSET_VER) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>