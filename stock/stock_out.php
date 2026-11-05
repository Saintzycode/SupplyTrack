<?php
/**
 * stock_out.php - record a stock-out transaction (AGENTS.md build step 4).
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
        redirect('stock/stock_out.php');
    }
    $itemId = (int)($_POST['item_id'] ?? 0);
    $qty = trim($_POST['quantity'] ?? '');
    $date = trim($_POST['transaction_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $dest = 'stock/stock_out.php' . ($itemId > 0 ? '?' . http_build_query(['item_id' => $itemId]) : '');

    $errs = [];
    $valid = $pdo->prepare('SELECT COUNT(*) FROM items WHERE item_id = :id AND is_active = 1');
    $valid->execute([':id' => $itemId]);
    $itemOk = (int)$valid->fetchColumn() > 0;
    if (!$itemOk) $errs['item_id'] = 'Select an item.';
    $onHand = 0;
    if ($itemOk) {
        $st = $pdo->prepare('SELECT quantity_on_hand FROM items WHERE item_id = :id');
        $st->execute([':id' => $itemId]);
        $onHand = (int)$st->fetchColumn();
    }
    if (!valid_qty($qty)) {
        $errs['quantity'] = 'Quantity must be a whole number between 1 and 1,000,000.';
    } elseif ((int)$qty > $onHand) {
        $errs['quantity'] = 'Insufficient stock. Available: ' . number_format($onHand) . '.';
    }
    if (!valid_date($date)) $errs['transaction_date'] = 'Enter a valid date.';
    if ($reason === '') $errs['reason'] = 'Reason is required.';
    elseif (mb_strlen($reason) > 255) $errs['reason'] = 'Reason must be 255 characters or fewer.';
    if (mb_strlen($remarks) > 255) $errs['remarks'] = 'Remarks must be 255 characters or fewer.';

    if ($errs) {
        flash('danger', implode(' ', array_values($errs)));
        redirect($dest);
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO stock_transactions
               (item_id, transaction_type, quantity_change, reason, remarks, transaction_date, created_by)
             VALUES (:item, 'stock_out', :qty, :reason, :remarks, :tdate, :by)"
        );
        $stmt->execute([
            ':item' => $itemId,
            ':qty' => -1 * (int)$qty,
            ':reason' => $reason,
            ':remarks' => $remarks !== '' ? $remarks : null,
            ':tdate' => $date . ' 00:00:00',
            ':by' => (int)current_user()['id'],
        ]);
        $newId = (int)$pdo->lastInsertId();
        audit_log('stock_out', 'stock_transactions', $newId, "Stock-out: item_id {$itemId}, qty {$qty}, reason: {$reason}", current_user()['id']);
        flash('success', 'Stock-out recorded. On-hand quantity updated.');
        redirect($dest);
    } catch (PDOException $e) {
        $msg = $e->getCode() === '45000' ? ($e->errorInfo[2] ?? $e->getMessage()) : 'Something went wrong. Please try again.';
        if ($e->getCode() !== '45000') error_log('stock_out insert failed: ' . $e->getMessage());
        flash('danger', $msg);
        redirect($dest);
    }
}

$pageTitle  = 'Stock-out';
$activePage = 'inventory';
// Declare error vars before any template use (avoids "Use of unassigned variable" warnings)
$submitError = null;
$errors = [];

require_once __DIR__ . '/../includes/header.php';

$items = $pdo->query(
    'SELECT item_id, sku, item_name, unit_of_measure, quantity_on_hand, reorder_point, is_active
       FROM items
      WHERE is_active = 1
      ORDER BY item_name'
)->fetchAll();

$selectedItemId = (int)($_GET['item_id'] ?? 0);
if (isset($_POST['item_id'])) {
    $selectedItemId = (int)$_POST['item_id'];
}

$old = [
    'item_id'          => $selectedItemId > 0 ? $selectedItemId : 0,
    'quantity'         => '',
    'transaction_date' => date('Y-m-d'),
    'reason'           => '',
    'remarks'          => '',
];

// (All POST handling is at the top of this file, before header.php is
// included — see the Post/Redirect/Get block above.)

// ---- Side panel ----
$panelItem = null;
if ($selectedItemId > 0) {
    $ps = $pdo->prepare(
        'SELECT i.item_id, i.sku, i.item_name, i.unit_of_measure, i.quantity_on_hand,
                i.reorder_point, i.is_active, c.category_name
           FROM items i LEFT JOIN categories c ON c.category_id = i.category_id
          WHERE i.item_id = :id'
    );
    $ps->execute([':id' => $selectedItemId]);
    $panelItem = $ps->fetch();
}
?>
<div class="page-head">
    <div>
        <h1>Stock-out</h1>
        <p>Record outgoing stock (manual removal or damage).</p>
    </div>
    <a href="<?= e(url('items/inventory.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to inventory</a>
</div>

<?php if (!empty($submitError)): ?>
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
                        <label class="form-label" for="reason">Reason <span class="text-danger">*</span></label>
                        <input type="text" maxlength="255"
                               class="form-control <?= isset($errors['reason']) ? 'is-invalid' : '' ?>"
                               id="reason" name="reason" value="<?= e($old['reason']) ?>" placeholder="damage, loss, adjustment" required>
                        <?php if (isset($errors['reason'])): ?><div class="invalid-feedback"><?= e($errors['reason']) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="mb-3 mt-3">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea class="form-control <?= isset($errors['remarks']) ? 'is-invalid' : '' ?>"
                              id="remarks" name="remarks" rows="2" maxlength="255"><?= e($old['remarks']) ?></textarea>
                    <?php if (isset($errors['remarks'])): ?><div class="invalid-feedback"><?= e($errors['remarks']) ?></div><?php endif; ?>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" <?= $items ? '' : 'disabled' ?>>
                        <i class="bi bi-dash-lg me-1"></i>Record stock-out
                    </button>
                    <a href="<?= e(url('stock/stock_out.php')) ?>" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <?php if ($panelItem): ?>
            <div class="card-ui p-4">
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
                    <dt class="col-5 text-muted">Available</dt>
                    <dd class="col-7 fw-bold <?= (int)$panelItem['quantity_on_hand'] <= 0 ? 'text-danger' : '' ?>">
                        <?= number_format((int)$panelItem['quantity_on_hand']) ?>
                    </dd>
                    <dt class="col-5 text-muted">Reorder</dt>
                    <dd class="col-7"><?= number_format((int)$panelItem['reorder_point']) ?></dd>
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
                <?php if ((int)$panelItem['quantity_on_hand'] <= 0): ?>
                    <div class="alert alert-warning small mt-3 mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>No stock available for this item.
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card-ui p-4">
                <h2 class="h6 fw-bold mb-3">Item summary</h2>
                <p class="text-muted small mb-0">
                    Select an item to see its available quantity.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
