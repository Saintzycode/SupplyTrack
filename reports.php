<?php
/**
 * reports.php - Stock Status / Stock Transactions / Distribution (admin only).
 * Screen and exports share includes/reports_lib.php so they always agree.
 */
require_once __DIR__ . '/includes/auth.php';
require_admin();

$pageTitle  = 'Reports';
$activePage = 'reports';
require_once __DIR__ . '/includes/header.php';

$pdo = db();
require_once __DIR__ . '/includes/reports_lib.php';

$tab = $_GET['tab'] ?? 'stock';
if (!in_array($tab, ['stock', 'transactions', 'distribution'], true)) {
    $tab = 'stock';
}

$perPage = 15;
$page    = max(1, (int)($_GET['page'] ?? 1));

// Lookup data for the filter dropdowns
$itemsList   = $pdo->query('SELECT item_id, sku, item_name FROM items ORDER BY item_name')->fetchAll();
$schoolsList = $pdo->query('SELECT school_id, school_name FROM schools ORDER BY school_name')->fetchAll();
$categories  = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();

// Period presets (Stock Transactions + Distribution only)
$periodOpts = rep_period_options();
$period = (string)($_GET['period'] ?? 'custom');
if (!array_key_exists($period, $periodOpts)) $period = 'custom';
$periodLocked = $period !== 'custom';

// URL builder that keeps current filters + applies overrides
function rep_url(array $override): string {
    return url('reports.php?' . http_build_query(array_merge($_GET, $override)));
}

/* ------------------------------------------------------------------ */
/* Filters (shared with the exports)                                  */
/* ------------------------------------------------------------------ */
$stockF = rep_build_filters($pdo, 'stock', $_GET);
$txF    = rep_build_filters($pdo, 'transactions', $_GET);
$distF  = rep_build_filters($pdo, 'distribution', $_GET);

$stockQ      = trim($_GET['stock_q'] ?? '');
$stockStatus = trim($_GET['stock_status'] ?? '');
$stockCat    = (int)($_GET['stock_cat'] ?? 0);
$txItem      = (int)($_GET['tx_item'] ?? 0);
$txType      = trim($_GET['tx_type'] ?? '');
if (!in_array($txType, ['', 'stock_in', 'stock_out', 'adjustment'], true)) $txType = '';
$distSchool  = (int)($_GET['dist_school'] ?? 0);
$distItem    = (int)($_GET['dist_item'] ?? 0);

$txFrom   = $txF['from'];
$txTo     = $txF['to'];
$distFrom = $distF['from'];
$distTo   = $distF['to'];

/* ------------------------------------------------------------------ */
/* Rows + totals for the active tab                                   */
/* ------------------------------------------------------------------ */
$rows = [];
$total = 0;
$pages = 1;
$totals = [];
$where = [];
$params = [];

if ($tab === 'stock') {
    $where = $stockF['where']; $params = $stockF['params'];
} elseif ($tab === 'transactions') {
    $where = $txF['where']; $params = $txF['params'];
} else {
    $where = $distF['where']; $params = $distF['params'];
}

$total = rep_count($pdo, $tab, $where, $params);
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) $page = $pages;
$rows   = rep_fetch_rows($pdo, $tab, $where, $params, $perPage, ($page - 1) * $perPage);
$totals = rep_totals($pdo, $tab, $where, $params);

/* ------------------------------------------------------------------ */
/* Export links (same filters + effective period dates)               */
/* ------------------------------------------------------------------ */
$exportParams = $_GET;
unset($exportParams['page']);
$exportParams['report'] = $tab;
if ($tab === 'transactions') { $exportParams['period'] = $period; $exportParams['tx_from'] = $txFrom; $exportParams['tx_to'] = $txTo; }
if ($tab === 'distribution') { $exportParams['period'] = $period; $exportParams['dist_from'] = $distFrom; $exportParams['dist_to'] = $distTo; }

$pdfUrl = url('report_export.php?' . http_build_query(array_merge($exportParams, ['format' => 'pdf'])));
$xlsUrl = url('report_export.php?' . http_build_query(array_merge($exportParams, ['format' => 'xls'])));

function txn_badge_class(string $t): string
{
    return match ($t) {
        'stock_in'  => 'badge-in',
        'stock_out' => 'badge-out',
        default     => 'badge-adj',
    };
}
?>
<div class="page-head">
    <div>
        <h1>Reports</h1>
        <p>Stock status, stock transactions, and distribution reports.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e($pdfUrl) ?>" class="btn btn-outline-danger"><i class="bi bi-filetype-pdf me-1"></i>Export PDF</a>
        <a href="<?= e($xlsUrl) ?>" class="btn btn-outline-success"><i class="bi bi-filetype-xls me-1"></i>Export Excel</a>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <?php
    $tabs = [
        'stock'        => ['Stock status', 'bi-box-seam'],
        'transactions' => ['Stock transactions', 'bi-activity'],
        'distribution' => ['Distribution', 'bi-send'],
    ];
    foreach ($tabs as $key => [$label, $icon]): ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="<?= e(rep_url(['tab' => $key, 'page' => 1])) ?>">
                <i class="bi <?= e($icon) ?> me-1"></i><?= e($label) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'stock'): ?>
    <form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('reports.php')) ?>">
        <input type="hidden" name="tab" value="stock">
        <div class="col-12 col-md-3">
            <label class="form-label small text-muted mb-0" for="stock_q">Search</label>
            <input type="text" class="form-control" id="stock_q" name="stock_q" value="<?= e($stockQ) ?>" placeholder="sku, item, category">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-0" for="stock_status">Status</label>
            <select class="form-select" id="stock_status" name="stock_status">
                <option value="" <?= $stockStatus === '' ? 'selected' : '' ?>>All</option>
                <option value="Normal" <?= $stockStatus === 'Normal' ? 'selected' : '' ?>>Normal</option>
                <option value="Low" <?= $stockStatus === 'Low' ? 'selected' : '' ?>>Low</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-0" for="stock_cat">Category</label>
            <select class="form-select" id="stock_cat" name="stock_cat">
                <option value="0">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['category_id'] ?>" <?= $stockCat === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-secondary">Apply</button>
        </div>
        <?php if ($stockQ !== '' || $stockStatus !== '' || $stockCat > 0): ?>
            <div class="col-12"><a href="<?= e(url('reports.php?tab=stock')) ?>" class="small text-decoration-none">Clear filters</a></div>
        <?php endif; ?>
    </form>

    <div class="card-ui">
        <div class="table-responsive">
            <table class="table table-ui">
                <thead>
                    <tr>
                        <th>SKU</th><th>Item</th><th>Category</th><th>Unit</th>
                        <th class="text-end">On hand</th><th class="text-end">Reorder point</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No items match the filters.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                            $low = (int)$r['quantity_on_hand'] <= (int)$r['reorder_point'];
                            $rowCls = $low ? 'row-low' : 'row-normal';
                        ?>
                        <tr class="<?= e($rowCls) ?>">
                            <td class="text-muted"><?= e($r['sku']) ?></td>
                            <td class="fw-semibold"><?= e($r['item_name']) ?></td>
                            <td><?= e($r['category_name'] ?? '—') ?></td>
                            <td><?= e($r['unit_of_measure']) ?></td>
                            <td class="text-end fw-semibold <?= $low ? 'text-danger' : '' ?>"><?= number_format((int)$r['quantity_on_hand']) ?></td>
                            <td class="text-end text-muted"><?= number_format((int)$r['reorder_point']) ?></td>
                            <td><span class="badge-soft <?= $low ? 'badge-low' : 'badge-normal' ?>"><?= $low ? 'Low' : 'Normal' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php $sumTitle = 'Stock status'; require __DIR__ . '/includes/report_foot.php'; ?>
    </div>

<?php elseif ($tab === 'transactions'): ?>
    <form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('reports.php')) ?>">
        <input type="hidden" name="tab" value="transactions">
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label small text-muted mb-0" for="period">Period</label>
            <select class="form-select" id="period" name="period" data-period-select>
                <?php foreach ($periodOpts as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $period === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-0" for="tx_from">From</label>
            <input type="date" class="form-control" id="tx_from" name="tx_from" value="<?= e($txFrom) ?>" <?= $periodLocked ? 'readonly' : '' ?>>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-0" for="tx_to">To</label>
            <input type="date" class="form-control" id="tx_to" name="tx_to" value="<?= e($txTo) ?>" <?= $periodLocked ? 'readonly' : '' ?>>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label small text-muted mb-0" for="tx_item">Item</label>
            <select class="form-select" id="tx_item" name="tx_item">
                <option value="0">All items</option>
                <?php foreach ($itemsList as $it): ?>
                    <option value="<?= (int)$it['item_id'] ?>" <?= $txItem === (int)$it['item_id'] ? 'selected' : '' ?>><?= e($it['sku'] . ' - ' . $it['item_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-0" for="tx_type">Type</label>
            <select class="form-select" id="tx_type" name="tx_type">
                <option value="" <?= $txType === '' ? 'selected' : '' ?>>All types</option>
                <option value="stock_in" <?= $txType === 'stock_in' ? 'selected' : '' ?>>Stock-in</option>
                <option value="stock_out" <?= $txType === 'stock_out' ? 'selected' : '' ?>>Stock-out</option>
                <option value="adjustment" <?= $txType === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
            </select>
        </div>
        <div class="col-6 col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-secondary">Apply</button>
        </div>
        <?php if ($period !== 'custom' || $txFrom !== '' || $txTo !== '' || $txItem > 0 || $txType !== ''): ?>
            <div class="col-12"><a href="<?= e(url('reports.php?tab=transactions')) ?>" class="small text-decoration-none">Clear filters</a></div>
        <?php endif; ?>
    </form>

    <div class="card-ui">
        <div class="table-responsive">
            <table class="table table-ui">
                <thead>
                    <tr>
                        <th>Date</th><th>Type</th><th>SKU</th><th>Item</th>
                        <th class="text-end">Quantity change</th><th class="text-end">Balance after</th>
                        <th>Supplier</th><th>Reason</th><th>Remarks</th><th>Recorded by</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No transactions match the filters.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                            $q = (int)$r['quantity_change'];
                            $rowCls = match ($r['transaction_type']) {
                                'stock_in'  => 'row-in',
                                'stock_out' => 'row-out',
                                default     => 'row-adj',
                            };
                        ?>
                        <tr class="<?= e($rowCls) ?>">
                            <td class="text-muted small"><?= e(date('Y-m-d H:i', strtotime((string)$r['transaction_date']))) ?></td>
                            <td><span class="badge-soft <?= e(txn_badge_class($r['transaction_type'])) ?>"><?= e(rep_txn_type_label($r['transaction_type'])) ?></span></td>
                            <td class="text-muted"><?= e($r['sku']) ?></td>
                            <td class="fw-semibold"><?= e($r['item_name']) ?></td>
                            <td class="text-end fw-semibold <?= $q > 0 ? 'text-success' : ($q < 0 ? 'text-danger' : '') ?>"><?= $q > 0 ? '+' : '' ?><?= number_format($q) ?></td>
                            <td class="text-end text-muted"><?= number_format((int)$r['quantity_after']) ?></td>
                            <td><?= e($r['supplier_name'] ?? '—') ?></td>
                            <td class="small"><?= e(($r['reason'] ?? '') !== '' ? $r['reason'] : '—') ?></td>
                            <td class="small text-muted"><?= e(($r['remarks'] ?? '') !== '' ? $r['remarks'] : '—') ?></td>
                            <td class="text-muted small"><?= e($r['recorded_by_name'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php $sumTitle = 'Stock transactions'; require __DIR__ . '/includes/report_foot.php'; ?>
    </div>

<?php else: ?>
    <form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('reports.php')) ?>">
        <input type="hidden" name="tab" value="distribution">
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label small text-muted mb-0" for="period">Period</label>
            <select class="form-select" id="period" name="period" data-period-select>
                <?php foreach ($periodOpts as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $period === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-0" for="dist_from">From</label>
            <input type="date" class="form-control" id="dist_from" name="dist_from" value="<?= e($distFrom) ?>" <?= $periodLocked ? 'readonly' : '' ?>>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-0" for="dist_to">To</label>
            <input type="date" class="form-control" id="dist_to" name="dist_to" value="<?= e($distTo) ?>" <?= $periodLocked ? 'readonly' : '' ?>>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label small text-muted mb-0" for="dist_school">School</label>
            <select class="form-select" id="dist_school" name="dist_school">
                <option value="0">All schools</option>
                <?php foreach ($schoolsList as $sc): ?>
                    <option value="<?= (int)$sc['school_id'] ?>" <?= $distSchool === (int)$sc['school_id'] ? 'selected' : '' ?>><?= e($sc['school_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label small text-muted mb-0" for="dist_item">Item</label>
            <select class="form-select" id="dist_item" name="dist_item">
                <option value="0">All items</option>
                <?php foreach ($itemsList as $it): ?>
                    <option value="<?= (int)$it['item_id'] ?>" <?= $distItem === (int)$it['item_id'] ? 'selected' : '' ?>><?= e($it['sku'] . ' - ' . $it['item_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-secondary">Apply</button>
        </div>
        <?php if ($period !== 'custom' || $distFrom !== '' || $distTo !== '' || $distSchool > 0 || $distItem > 0): ?>
            <div class="col-12"><a href="<?= e(url('reports.php?tab=distribution')) ?>" class="small text-decoration-none">Clear filters</a></div>
        <?php endif; ?>
    </form>

    <div class="card-ui">
        <div class="table-responsive">
            <table class="table table-ui">
                <thead>
                    <tr>
                        <th>Date</th><th>School</th><th>SKU</th><th>Item</th>
                        <th>Unit</th><th class="text-end">Quantity</th><th>Remarks</th><th>Recorded by</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No distributions match the filters.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="text-muted small"><?= e(substr((string)$r['distribution_date'], 0, 10)) ?></td>
                            <td class="fw-semibold"><?= e($r['school_name']) ?></td>
                            <td class="text-muted"><?= e($r['sku']) ?></td>
                            <td><?= e($r['item_name']) ?></td>
                            <td><?= e($r['unit_of_measure']) ?></td>
                            <td class="text-end fw-semibold"><?= number_format((int)$r['quantity']) ?></td>
                            <td class="small text-muted"><?= e(($r['remarks'] ?? '') !== '' ? $r['remarks'] : '—') ?></td>
                            <td class="text-muted small"><?= e($r['recorded_by_name'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php $sumTitle = 'Distribution'; require __DIR__ . '/includes/report_foot.php'; ?>
    </div>
<?php endif; ?>

<script>
(function () {
    document.querySelectorAll('[data-period-select]').forEach(function (sel) {
        sel.addEventListener('change', function () { sel.form.submit(); });
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
