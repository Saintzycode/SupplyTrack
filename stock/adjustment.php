<?php
/**
 * adjustment.php - record a stock adjustment (admin only).
 * Adjustment quantity_change may be positive or negative but never zero;
 * the database trigger rejects inactive items, zero, and negative stock,
 * and sets quantity_after / updates on-hand / raises low-stock alerts.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();

// ---- Handle POST *before* the page renders (Post/Redirect/Get) ----
// Read the fields, validate, insert, then redirect. redirect() must run
// before any HTML output, so header.php is included *after* this block.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('danger', 'Session expired. Please try again.');
        redirect('stock/adjustment.php');
    }
    $itemId = (int)($_POST['item_id'] ?? 0);
    $dir = ($_POST['direction'] ?? 'increase') === 'decrease' ? 'decrease' : 'increase';
    $qty = trim($_POST['quantity'] ?? '');
    $date = trim($_POST['transaction_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $dest = 'stock/adjustment.php' . ($itemId > 0 ? '?' . http_build_query(['item_id' => $itemId]) : '');

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
    $qtyNum = (int)$qty;
    if (!valid_qty($qty)) {
        $errs['quantity'] = 'Quantity must be a whole number between 1 and 1,000,000.';
    } elseif ($dir === 'decrease' && $qtyNum > $onHand) {
        $errs['quantity'] = 'Insufficient stock to decrease. Available: ' . number_format($onHand) . '.';
    }
    if (!valid_date($date)) $errs['transaction_date'] = 'Enter a valid date.';
    if ($reason === '') $errs['reason'] = 'Reason is required.';
    elseif (mb_strlen($reason) > 255) $errs['reason'] = 'Reason must be 255 characters or fewer.';
    if (mb_strlen($remarks) > 255) $errs['remarks'] = 'Remarks must be 255 characters or fewer.';

    if ($errs) {
        flash('danger', implode(' ', array_values($errs)));
        redirect($dest);
    }
    $signed = $qtyNum * ($dir === 'decrease' ? -1 : 1);
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO stock_transactions
               (item_id, transaction_type, quantity_change, reason, remarks, transaction_date, created_by)
             VALUES (:item, 'adjustment', :qty, :reason, :remarks, :tdate, :by)"
        );
        $stmt->execute([
            ':item' => $itemId,
            ':qty' => $signed,
            ':reason' => $reason,
            ':remarks' => $remarks !== '' ? $remarks : null,
            ':tdate' => $date . ' 00:00:00',
            ':by' => (int)current_user()['id'],
        ]);
        $newId = (int)$pdo->lastInsertId();
        audit_log('stock_adjustment', 'stock_transactions', $newId, "Adjustment: item_id {$itemId}, change " . ($signed > 0 ? '+' : '') . $signed . ", reason: {$reason}", current_user()['id']);
        flash('success', 'Adjustment recorded. On-hand quantity updated.');
        redirect($dest);
    } catch (PDOException $e) {
        $msg = $e->getCode() === '45000' ? ($e->errorInfo[2] ?? $e->getMessage()) : 'Something went wrong. Please try again.';
        if ($e->getCode() !== '45000') error_log('adjustment insert failed: ' . $e->getMessage());
        flash('danger', $msg);
        redirect($dest);
    }
}

$pageTitle  = 'Stock adjustment';
$activePage = 'inventory';
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
    'direction'        => 'increase',
    'quantity'         => '',
    'transaction_date' => date('Y-m-d'),
    'reason'           => '',
    'remarks'          => '',
];
$errors = [];
$submitError = null;

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
        <h1>Stock adjustment</h1>
        <p>Correct the on-hand quantity of an item (increase or decrease).</p>
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
                        <label class="form-label" for="direction">Direction <span class="text-danger">*</span></label>
                        <select class="form-select" id="direction" name="direction" required>
                            <option value="increase" <?= $old['direction'] === 'increase' ? 'selected' : '' ?>>Increase (+)</option>
                            <option value="decrease" <?= $old['direction'] === 'decrease' ? 'selected' : '' ?>>Decrease (&minus;)</option>
                        </select>
                    </div>
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
                               data-future-warn="Stock will be adjusted immediately, even though this date is in the future." required>
                        <?php if (isset($errors['transaction_date'])): ?><div class="invalid-feedback"><?= e($errors['transaction_date']) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="reason">Reason <span class="text-danger">*</span></label>
                        <input type="text" maxlength="255"
                               class="form-control <?= isset($errors['reason']) ? 'is-invalid' : '' ?>"
                               id="reason" name="reason" value="<?= e($old['reason']) ?>" placeholder="physical count, damage, correction" required>
                        <?php if (isset($errors['reason'])): ?><div class="invalid-feedback"><?= e($errors['reason']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea class="form-control <?= isset($errors['remarks']) ? 'is-invalid' : '' ?>"
                                  id="remarks" name="remarks" rows="1" maxlength="255"><?= e($old['remarks']) ?></textarea>
                        <?php if (isset($errors['remarks'])): ?><div class="invalid-feedback"><?= e($errors['remarks']) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="alert alert-secondary small mt-3 mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    An increase adds stock, a decrease removes it. Adjustments never change stock to a negative
                    amount and are logged with your name and the time.
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" <?= $items ? '' : 'disabled' ?>>
                        <i class="bi bi-sliders me-1"></i>Record adjustment
                    </button>
                    <a href="<?= e(url('stock/adjustment.php')) ?>" class="btn btn-outline-secondary">Reset</a>
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
                    <dt class="col-5 text-muted">On hand</dt>
                    <dd class="col-7 fw-bold"><?= number_format((int)$panelItem['quantity_on_hand']) ?></dd>
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
            </div>
        <?php else: ?>
            <div class="card-ui p-4">
                <h2 class="h6 fw-bold mb-3">Item summary</h2>
                <p class="text-muted small mb-0">
                    Select an item to see its current on-hand quantity.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
