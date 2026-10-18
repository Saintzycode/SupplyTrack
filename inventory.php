<?php
/**
 * inventory.php - item list (AGENTS.md build step 1)
 * Search (?q=), category filter, status filter (Normal/Low/Inactive), pagination.
 */
$pageTitle  = 'Inventory';
$activePage = 'inventory';
require_once __DIR__ . '/includes/header.php';

$pdo = db();

// ---- Filters (server-validated) ----
$q       = trim($_GET['q'] ?? '');
$cat     = (int)($_GET['category_id'] ?? 0);
$status  = $_GET['status'] ?? '';
if (!in_array($status, ['', 'Normal', 'Low', 'Inactive'], true)) {
    $status = '';
}

// ---- Build WHERE clause with bound params ----
$wheres = [];
$params = [];
if ($q !== '') {
    $wheres[] = "(i.sku LIKE :q1 OR i.item_name LIKE :q2 OR i.description LIKE :q3 OR c.category_name LIKE :q4)";
    $like = '%' . $q . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
    $params[':q4'] = $like;
}
if ($cat > 0) {
    $wheres[] = 'i.category_id = :cat';
    $params[':cat'] = $cat;
}
if ($status === 'Normal' || $status === 'Low') {
    $wheres[] = 'i.is_active = 1';
    if ($status === 'Normal') {
        $wheres[] = 'i.quantity_on_hand > i.reorder_point';
    } else {
        $wheres[] = 'i.quantity_on_hand <= i.reorder_point';
    }
} elseif ($status === 'Inactive') {
    $wheres[] = 'i.is_active = 0';
}
$whereSql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

// ---- Total for pagination ----
$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
       FROM items i
       LEFT JOIN categories c ON c.category_id = i.category_id
       $whereSql"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// ---- Pagination ----
$perPage = 15;
$page    = max(1, (int)($_GET['page'] ?? 1));
$pages   = max(1, (int)ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;

// ---- Fetch page ----
$listStmt = $pdo->prepare(
    "SELECT i.item_id, i.sku, i.item_name, i.unit_of_measure,
            i.quantity_on_hand, i.reorder_point, i.unit_cost, i.is_active,
            c.category_name
       FROM items i
       LEFT JOIN categories c ON c.category_id = i.category_id
       $whereSql
       ORDER BY i.is_active DESC, i.item_name ASC
       LIMIT :lim OFFSET :off"
);
foreach ($params as $k => $v) {
    $listStmt->bindValue($k, $v);
}
$listStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':off', $offset, PDO::PARAM_INT);
$listStmt->execute();
$rows = $listStmt->fetchAll();

// ---- Categories for the filter dropdown ----
$catOpts = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();

// ---- Helper: URL for a page number, keeping filters ----
function inv_page_url(int $p, string $q, int $cat, string $status): string
{
    $qs = http_build_query(array_filter([
        'page'        => $p,
        'q'           => $q !== '' ? $q : null,
        'category_id' => $cat > 0 ? $cat : null,
        'status'      => $status !== '' ? $status : null,
    ], function ($v) { return $v !== null; }));
    return url('inventory.php' . ($qs !== '' ? '?' . $qs : ''));
}
?>
<div class="page-head">
    <div>
        <h1>Inventory</h1>
        <p><?= number_format($total) ?> item<?= $total === 1 ? '' : 's' ?> found.</p>
    </div>
    <?php if (is_admin()): ?>
        <a href="<?= e(url('item_form.php')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add item</a>
    <?php endif; ?>
</div>

<!-- Filter bar -->
<form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('inventory.php')) ?>">
    <div class="col-12 col-md-4">
        <label class="form-label small text-muted mb-0" for="q">Search</label>
        <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="SKU, name, description, category">
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small text-muted mb-0" for="category_id">Category</label>
        <select class="form-select" id="category_id" name="category_id">
            <option value="0">All categories</option>
            <?php foreach ($catOpts as $c): ?>
                <option value="<?= (int)$c['category_id'] ?>" <?= $cat === (int)$c['category_id'] ? 'selected' : '' ?>>
                    <?= e($c['category_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small text-muted mb-0" for="status">Status</label>
        <select class="form-select" id="status" name="status">
            <option value="" <?= $status === '' ? 'selected' : '' ?>>All</option>
            <option value="Normal" <?= $status === 'Normal' ? 'selected' : '' ?>>Normal</option>
            <option value="Low" <?= $status === 'Low' ? 'selected' : '' ?>>Low</option>
            <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
    <div class="col-12 col-md-2 d-grid">
        <button type="submit" class="btn btn-outline-secondary">Apply</button>
    </div>
    <?php if ($q !== '' || $cat > 0 || $status !== ''): ?>
        <div class="col-12">
            <a href="<?= e(url('inventory.php')) ?>" class="small text-decoration-none">Clear filters</a>
        </div>
    <?php endif; ?>
</form>

<div class="card-ui">
    <div class="table-responsive">
        <table class="table table-ui">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th class="text-end">On hand</th>
                    <th class="text-end">Reorder</th>
                    <th class="text-end">Unit cost</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <?php if ($q !== '' || $cat > 0 || $status !== ''): ?>
                                No items match your filters.
                            <?php else: ?>
                                No items yet.
                                <?php if (is_admin()): ?>
                                    <a href="<?= e(url('item_form.php')) ?>" class="ms-1">Add the first item</a>.
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                        $active = (int)$r['is_active'] === 1;
                        if (!$active) {
                            $stLabel = 'Inactive'; $stClass = 'badge-inactive';
                        } elseif ((int)$r['quantity_on_hand'] <= (int)$r['reorder_point']) {
                            $stLabel = 'Low'; $stClass = 'badge-low';
                        } else {
                            $stLabel = 'Normal'; $stClass = 'badge-normal';
                        }
                    ?>
                    <tr>
                        <td class="fw-semibold">
                            <?= e($r['item_name']) ?>
                            <?php if (!$active): ?>
                                <span class="text-muted small d-block">inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= e($r['sku']) ?></td>
                        <td><?= e($r['category_name'] ?? '—') ?></td>
                        <td><?= e($r['unit_of_measure']) ?></td>
                        <td class="text-end fw-semibold
                            <?= $active && (int)$r['quantity_on_hand'] <= (int)$r['reorder_point'] ? 'text-danger' : '' ?>">
                            <?= number_format((int)$r['quantity_on_hand']) ?>
                        </td>
                        <td class="text-end text-muted"><?= number_format((int)$r['reorder_point']) ?></td>
                        <td class="text-end"><?= money($r['unit_cost']) ?></td>
                        <td><span class="badge-soft <?= e($stClass) ?>"><?= e($stLabel) ?></span></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Row actions">
                                <?php if ($active): ?>
                                    <a href="<?= e(url('stock_in.php?item_id=' . (int)$r['item_id'])) ?>"
                                       class="btn btn-outline-success" title="Record stock-in" aria-label="Record stock-in">
                                        <i class="bi bi-arrow-down-circle"></i>
                                    </a>
                                    <a href="<?= e(url('stock_out.php?item_id=' . (int)$r['item_id'])) ?>"
                                       class="btn btn-outline-warning" title="Record stock-out" aria-label="Record stock-out">
                                        <i class="bi bi-arrow-up-circle"></i>
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-secondary" disabled title="Item is inactive" aria-label="Record stock-in">
                                        <i class="bi bi-arrow-down-circle"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" disabled title="Item is inactive" aria-label="Record stock-out">
                                        <i class="bi bi-arrow-up-circle"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if (is_admin()): ?>
                                    <a href="<?= e(url('item_form.php?id=' . (int)$r['item_id'])) ?>"
                                       class="btn btn-outline-secondary" title="Edit item" aria-label="Edit item">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3">
            <div class="small text-muted">
                Page <?= $page ?> of <?= $pages ?> &middot; <?= number_format($total) ?> total
            </div>
            <nav aria-label="Inventory pages">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(inv_page_url($page - 1, $q, $cat, $status)) ?>">Previous</a>
                    </li>
                    <?php
                        // Window of up to 7 page numbers around the current page
                        $start = max(1, $page - 3);
                        $end   = min($pages, $page + 3);
                        for ($p = $start; $p <= $end; $p++):
                    ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e(inv_page_url($p, $q, $cat, $status)) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(inv_page_url($page + 1, $q, $cat, $status)) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
