<?php
/**
 * stock_in.php - record a stock-in transaction (AGENTS.md build step 3).
 */
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();

// ---- Handle POST *before* the page renders (Post/Redirect/Get) ----
// Read the fields, validate, insert, then redirect. redirect() must run
// before any HTML output, so header.php is included *after* this block.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('danger', 'Session expired. Please try again.');
        redirect('stock/stock_in.php');
    }
    $itemId = (int)($_POST['item_id'] ?? 0);
    $qty = trim($_POST['quantity'] ?? '');
    $date = trim($_POST['transaction_date'] ?? '');
    $supId = (int)($_POST['supplier_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    $dest = 'stock/stock_in.php' . ($itemId > 0 ? '?' . http_build_query(['item_id' => $itemId]) : '');

    $errs = [];
    $valid = $pdo->prepare('SELECT COUNT(*) FROM items WHERE item_id = :id AND is_active = 1');
    $valid->execute([':id' => $itemId]);
    if ((int)$valid->fetchColumn() === 0) $errs['item_id'] = 'Select an item.';

    $vSup = $pdo->prepare('SELECT COUNT(*) FROM suppliers WHERE supplier_id = :id AND is_active = 1');
    $vSup->execute([':id' => $supId]);
    $supOk = (int)$vSup->fetchColumn() > 0;
    if ($supId <= 0) $errs['supplier_id'] = 'Select a supplier.';
    elseif (!$supOk) $errs['supplier_id'] = 'Invalid supplier.';

    if ($qty === '' || !valid_qty($qty)) {
        $errs['quantity'] = 'Quantity must be a whole number between 1 and 1,000,000.';
    }
    if (!valid_date($date)) $errs['transaction_date'] = 'Enter a valid date.';
    if (mb_strlen($remarks) > 255) $errs['remarks'] = 'Remarks must be 255 characters or fewer.';

    if ($errs) {
        flash('danger', implode(' ', array_values($errs)));
        redirect($dest);
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO stock_transactions
               (item_id, transaction_type, quantity_change, supplier_id, reason, remarks, transaction_date, created_by)
             VALUES (:item, 'stock_in', :qty, :sup, NULL, :remarks, :tdate, :by)"
        );
        $stmt->execute([
            ':item' => $itemId,
            ':qty' => (int)$qty,
            ':sup' => $supId,
            ':remarks' => $remarks !== '' ? $remarks : null,
            ':tdate' => $date . ' 00:00:00',
            ':by' => (int)current_user()['id'],
        ]);
        $newId = (int)$pdo->lastInsertId();
        audit_log('stock_in', 'stock_transactions', $newId, "Stock-in: item_id {$itemId}, qty {$qty}", current_user()['id']);
        flash('success', 'Stock-in recorded. On-hand quantity updated.');
        redirect($dest);
    } catch (PDOException $e) {
        $msg = $e->getCode() === '45000' ? ($e->errorInfo[2] ?? $e->getMessage()) : 'Something went wrong. Please try again.';
        if ($e->getCode() !== '45000') error_log('stock_in insert failed: ' . $e->getMessage());
        flash('danger', $msg);
        redirect($dest);
    }
}

$pageTitle  = 'Stock-in';
$activePage = 'inventory';
require_once __DIR__ . '/../includes/header.php';

// ---- Lookups ----
$items = $pdo->query(
    'SELECT item_id, sku, item_name, unit_of_measure, quantity_on_hand, reorder_point, unit_cost, is_active
       FROM items
      WHERE is_active = 1
      ORDER BY item_name'
)->fetchAll();
$suppliers = $pdo->query(
    'SELECT supplier_id, company_name FROM suppliers WHERE is_active = 1 ORDER BY company_name'
)->fetchAll();

// ---- Selected item (GET or after a failed submit keeps it sticky) ----
$selectedItemId = (int)($_GET['item_id'] ?? 0);
if (isset($_POST['item_id'])) {
    $selectedItemId = (int)$_POST['item_id'];
}

// ---- Form state ----
$old = [
    'item_id'          => $selectedItemId > 0 ? $selectedItemId : 0,
    'quantity'         => '',
    'transaction_date' => date('Y-m-d'),
    'supplier_id'      => 0,
    'remarks'          => '',
];
$errors = [];
$submitError = null;

// (All POST handling is at the top of this file, before header.php is
// included — see the Post/Redirect/Get block above.)

// ---- Side-panel data for the selected item ----
$panelItem     = null;
$recentIns     = [];
if ($selectedItemId > 0) {
    $ps = $pdo->prepare(
        'SELECT i.item_id, i.sku, i.item_name, i.unit_of_measure, i.quantity_on_hand,
                i.reorder_point, i.unit_cost, i.is_active, c.category_name
           FROM items i
           LEFT JOIN categories c ON c.category_id = i.category_id
          WHERE i.item_id = :id'
    );
    $ps->execute([':id' => $selectedItemId]);
    $panelItem = $ps->fetch();

    if ($panelItem) {
        $ri = $pdo->prepare(
            'SELECT st.transaction_date, st.quantity_change, st.quantity_after, st.remarks,
                    s.company_name
               FROM stock_transactions st
               LEFT JOIN suppliers s ON s.supplier_id = st.supplier_id
              WHERE st.item_id = :id AND st.transaction_type = "stock_in"
              ORDER BY st.transaction_date DESC, st.transaction_id DESC
              LIMIT 5'
        );
        $ri->execute([':id' => $selectedItemId]);
        $recentIns = $ri->fetchAll();
    }
}
?>
<div class="page-head">
    <div>
        <h1>Stock-in</h1>
        <p>Record incoming stock from a supplier.</p>
    </div>
    <a href="<?= e(url('items/inventory.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to inventory</a>
</div>

<?php if ($submitError): ?>
    <div class="alert alert-danger"><?= e($submitError) ?></div>
<?php endif; ?>
<?php if (!empty($errors['_form'])): ?>
    <div class="alert alert-danger"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card-ui p-4">
            <form method="post" onsubmit="if(this.dataset.submitted){return false} this.dataset.submitted=1; this.querySelectorAll('button[type=submit]').forEach(function(b){b.disabled=true; b.innerHTML='Saving…';});">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="item_id">Item <span class="text-danger">*</span></label>
                    <select class="form-select <?= isset($errors['item_id']) ? 'is-invalid' : '' ?>"
                            id="item_id" name="item_id" required>
                            <option value="">Select an item</option>
                        <?php foreach ($items as $it): ?>
                            <option value="<?= (int)$it['item_id'] ?>" <?= $old['item_id'] === (int)$it['item_id'] ? 'selected' : '' ?>>
                                <?= e($it['sku'] . ' - ' . $it['item_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['item_id'])): ?><div class="invalid-feedback"><?= e($errors['item_id']) ?></div><?php endif; ?>
                    <?php if (!$items): ?>
                        <div class="form-text text-muted">No active items. Add items in Inventory first.</div>
                    <?php endif; ?>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="quantity">Quantity <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="1"
                               class="form-control <?= isset($errors['quantity']) ? 'is-invalid' : '' ?>"
                               id="quantity" name="quantity" value="<?= e($old['quantity']) ?>" required>
                        <?php if (isset($errors['quantity'])): ?><div class="invalid-feedback"><?= e($errors['quantity']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="transaction_date">Date <span class="text-danger">*</span></label>
                        <input type="date"
                               class="form-control <?= isset($errors['transaction_date']) ? 'is-invalid' : '' ?>"
                               id="transaction_date" name="transaction_date" value="<?= e($old['transaction_date']) ?>"
                               data-future-warn="Stock will be recorded immediately, even though this date is in the future." required>
                        <?php if (isset($errors['transaction_date'])): ?><div class="invalid-feedback"><?= e($errors['transaction_date']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="supplier_id">Supplier <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['supplier_id']) ? 'is-invalid' : '' ?>"
                                id="supplier_id" name="supplier_id" required>
                            <option value="">Select a supplier</option>
                            <?php foreach ($suppliers as $sp): ?>
                                <option value="<?= (int)$sp['supplier_id'] ?>" <?= $old['supplier_id'] === (int)$sp['supplier_id'] ? 'selected' : '' ?>>
                                    <?= e($sp['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['supplier_id'])): ?><div class="invalid-feedback"><?= e($errors['supplier_id']) ?></div><?php endif; ?>
                        <?php if (!$suppliers): ?>
                            <div class="form-text text-muted">No active suppliers. Add suppliers first.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-3 mt-3">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea class="form-control <?= isset($errors['remarks']) ? 'is-invalid' : '' ?>"
                              id="remarks" name="remarks" rows="2" maxlength="255"><?= e($old['remarks']) ?></textarea>
                    <?php if (isset($errors['remarks'])): ?><div class="invalid-feedback"><?= e($errors['remarks']) ?></div><?php endif; ?>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" <?= ($items && $suppliers) ? '' : 'disabled' ?>>
                        <i class="bi bi-plus-lg me-1"></i>Record stock-in
                    </button>
                    <a href="<?= e(url('stock/stock_in.php')) ?>" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Side panel -->
    <div class="col-12 col-xl-4">
        <?php if ($panelItem): ?>
            <div class="card-ui p-4 mb-3">
                <h2 class="h6 fw-bold mb-3">Item summary</h2>
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">SKU</dt>
                    <dd class="col-7 fw-semibold"><?= e($panelItem['sku']) ?></dd>
                    <dt class="col-5 text-muted">Name</dt>
                    <dd class="col-7"><?= e($panelItem['item_name']) ?></dd>
                    <dt class="col-5 text-muted">Category</dt>
                    <dd class="col-7"><?= e($panelItem['category_name'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Unit</dt>
                    <dd class="col-7"><?= e($panelItem['unit_of_measure']) ?></dd>
                    <dt class="col-5 text-muted">On hand</dt>
                    <dd class="col-7 fw-bold <?= (int)$panelItem['quantity_on_hand'] <= (int)$panelItem['reorder_point'] ? 'text-danger' : '' ?>">
                        <?= number_format((int)$panelItem['quantity_on_hand']) ?>
                    </dd>
                    <dt class="col-5 text-muted">Reorder</dt>
                    <dd class="col-7"><?= number_format((int)$panelItem['reorder_point']) ?></dd>
                    <dt class="col-5 text-muted">Unit cost</dt>
                    <dd class="col-7"><?= money($panelItem['unit_cost']) ?></dd>
                    <dt class="col-5 text-muted">Status</dt>
                    <dd class="col-7">
                        <?php if ((int)$panelItem['is_active'] !== 1): ?>
                            <span class="badge-soft badge-inactive">Inactive</span>
                        <?php elseif ((int)$panelItem['quantity_on_hand'] <= (int)$panelItem['reorder_point']): ?>
                            <span class="badge-soft badge-low">Low</span>
                        <?php else: ?>
                            <span class="badge-soft badge-normal">Normal</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>

            <div class="card-ui p-4">
                <h2 class="h6 fw-bold mb-3">Recent stock-ins</h2>
                <?php if (!$recentIns): ?>
                    <p class="text-muted small mb-0">No stock-ins recorded yet for this item.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($recentIns as $r): ?>
                            <li class="list-group-item px-0 py-2">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold">+<?= number_format((int)$r['quantity_change']) ?></span>
                                    <span class="text-muted"><?= e(fmt_date($r['transaction_date'])) ?></span>
                                </div>
                                <div class="text-muted">
                                    <?= e($r['company_name'] ?? 'no supplier') ?>
                                    <?php if (!empty($r['remarks'])): ?>
                                        &middot; <?= e($r['remarks']) ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card-ui p-4">
                <h2 class="h6 fw-bold mb-3">Item summary</h2>
                <p class="text-muted small mb-2">Open an item to see its details and recent stock-ins.</p>
                <p class="text-muted small mb-0">
                    Tip: visit <code>stock/stock_in.php?item_id=1</code> to pre-select an item.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
