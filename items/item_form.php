<?php
/**
 * item_form.php - add / edit item (AGENTS.md build step 2; admin only).
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle  = 'Add item';
$activePage = 'inventory';

$pdo = db();

/** Build a short SKU prefix from the item name, e.g. "Notebook 80 Leaves" -> "NOT". */
function sku_prefix(string $itemName): string
{
    $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $itemName));
    $prefix = substr($prefix, 0, 3);
    return $prefix !== '' ? $prefix : 'ITM';
}

// ---- Load list data needed for the form ----
$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();
$suppliers  = $pdo->query('SELECT supplier_id, company_name FROM suppliers WHERE is_active = 1 ORDER BY company_name')->fetchAll();
$activeSupplierIds = array_map('intval', array_column($suppliers, 'supplier_id'));

// ---- Determine edit mode ----
$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (isset($_POST['item_id'])) {
    $itemId = (int)$_POST['item_id'];
}
$editing = $itemId > 0;

$item = null;
if ($editing) {
    $stmt = $pdo->prepare('SELECT * FROM items WHERE item_id = :id');
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch();
    if (!$item) {
        flash('danger', 'Item not found.');
        redirect('items/inventory.php');
    }
    $pageTitle = 'Edit item';
}

// ---- Handle "activate / deactivate" (soft delete toggle) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    if (!csrf_verify()) {
        flash('danger', 'Session expired. Try again.');
        redirect($editing ? "items/item_form.php?id=$itemId" : 'items/inventory.php');
    }
    $row = $pdo->prepare('SELECT item_name, is_active FROM items WHERE item_id = :id');
    $row->execute([':id' => $itemId]);
    $r = $row->fetch();
    if ($r) {
        $newState = (int)$r['is_active'] === 1 ? 0 : 1;
        $pdo->prepare('UPDATE items SET is_active = :s, updated_at = NOW() WHERE item_id = :id')
            ->execute([':s' => $newState, ':id' => $itemId]);
        audit_log($newState === 0 ? 'deactivate' : 'reactivate', 'items', $itemId,
            ($newState === 0 ? 'Deactivated' : 'Reactivated') . " item: {$r['item_name']}", current_user()['id']);
        flash('success', $newState === 0 ? 'Item deactivated.' : 'Item reactivated.');
    }
    redirect('items/inventory.php');
}

// ---- Sticky form values ----
$old = [
    'sku'               => $item['sku'] ?? '',
    'item_name'         => $item['item_name'] ?? '',
    'description'       => $item['description'] ?? '',
    'category_id'       => $item['category_id'] ?? 0,
    'unit_of_measure'   => $item['unit_of_measure'] ?? '',
    'reorder_point'     => $item['reorder_point'] ?? 0,
    'unit_cost'         => $item['unit_cost'] ?? '0.00',
];
$selectedSuppliers = [];
if ($item) {
    $ss = $pdo->prepare('SELECT supplier_id FROM item_suppliers WHERE item_id = :id');
    $ss->execute([':id' => $itemId]);
    $selectedSuppliers = array_map('intval', array_column($ss->fetchAll(), 'supplier_id'));
}

$errors = [];
$saved = false;
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_active'])) {
    if (!csrf_verify()) {
        $errors['_form'] = 'Session expired. Please try again.';
    } else {
        $old['sku']             = trim($_POST['sku'] ?? '');
        $old['item_name']       = trim($_POST['item_name'] ?? '');
        $old['description']     = trim($_POST['description'] ?? '');
        $old['category_id']     = (int)($_POST['category_id'] ?? 0);
        $old['unit_of_measure'] = trim($_POST['unit_of_measure'] ?? '');
        $old['reorder_point']   = trim($_POST['reorder_point'] ?? '0');
        $old['unit_cost']       = trim($_POST['unit_cost'] ?? '0');

        $chosenSuppliers = array_values(array_intersect(
            array_map('intval', (array)($_POST['suppliers'] ?? [])),
            $activeSupplierIds
        ));

        // ---- Validation (AGENTS rule 11) ----
        // SKU is optional on create: leave it blank and the system generates one.
        if ($old['sku'] === '') {
            if ($editing) {
                $errors['sku'] = 'SKU is required.';
            }
        } elseif (mb_strlen($old['sku']) > 50) {
            $errors['sku'] = 'SKU must be 50 characters or fewer.';
        }
        if ($old['item_name'] === '') {
            $errors['item_name'] = 'Item name is required.';
        } elseif (mb_strlen($old['item_name']) > 150) {
            $errors['item_name'] = 'Item name must be 150 characters or fewer.';
        }
        // duplicate SKU precheck (skip when blank on create -> it will be generated)
        if ($old['sku'] !== '' && !isset($errors['sku'])) {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM items WHERE sku = :s AND item_id <> :id');
            $dup->execute([':s' => $old['sku'], ':id' => $itemId]);
            if ((int)$dup->fetchColumn() > 0) {
                $errors['sku'] = 'SKU already exists.';
            }
        }
        // category exists
        if ($old['category_id'] <= 0) {
            $errors['category_id'] = 'Select a category.';
        } else {
            $cc = $pdo->prepare('SELECT 1 FROM categories WHERE category_id = :c');
            $cc->execute([':c' => $old['category_id']]);
            if (!$cc->fetchColumn()) {
                $errors['category_id'] = 'Invalid category.';
            }
        }
        if ($old['unit_of_measure'] === '') {
            $errors['unit_of_measure'] = 'Unit of measure is required.';
        } elseif (mb_strlen($old['unit_of_measure']) > 30) {
            $errors['unit_of_measure'] = 'Unit must be 30 characters or fewer.';
        }
        if (!ctype_digit($old['reorder_point']) && !preg_match('/^\d+$/', $old['reorder_point'])) {
            $errors['reorder_point'] = 'Reorder point must be a whole number.';
        } elseif ((int)$old['reorder_point'] < 0) {
            $errors['reorder_point'] = 'Reorder point cannot be negative.';
        }
        $costVal = filter_var(str_replace(',', '', $old['unit_cost']), FILTER_VALIDATE_FLOAT);
        if ($costVal === false || $costVal < 0) {
            $errors['unit_cost'] = 'Unit cost must be a non-negative number.';
        }

        if (!$errors) {
            $generatedSku = null;
            try {
                $pdo->beginTransaction();
                $reorder = (int)$old['reorder_point'];
                $cost    = (float)str_replace(',', '', $old['unit_cost']);
                if ($editing) {
                    $upd = $pdo->prepare(
                        'UPDATE items
                            SET sku = :sku, item_name = :name, description = :desc,
                                category_id = :cat, unit_of_measure = :unit,
                                reorder_point = :reorder, unit_cost = :cost, updated_at = NOW()
                          WHERE item_id = :id'
                    );
                    $upd->execute([
                        ':sku'    => $old['sku'],
                        ':name'   => $old['item_name'],
                        ':desc'   => $old['description'] !== '' ? $old['description'] : null,
                        ':cat'    => $old['category_id'],
                        ':unit'   => $old['unit_of_measure'],
                        ':reorder'=> $reorder,
                        ':cost'   => $cost,
                        ':id'     => $itemId,
                    ]);
                } else {
                    // Blank SKU -> insert with a temporary unique placeholder, then replace it
                    // with PREFIX-#### derived from the new item_id (unique + race-safe).
                    $ins = $pdo->prepare(
                        "INSERT INTO items (sku, item_name, description, category_id, unit_of_measure,
                                           reorder_point, unit_cost, quantity_on_hand, is_active)
                         VALUES (:sku, :name, :desc, :cat, :unit, :reorder, :cost, 0, 1)"
                    );
                    $ins->execute([
                        ':sku'    => $old['sku'] !== '' ? $old['sku'] : ('TMP-' . bin2hex(random_bytes(8))),
                        ':name'   => $old['item_name'],
                        ':desc'   => $old['description'] !== '' ? $old['description'] : null,
                        ':cat'    => $old['category_id'],
                        ':unit'   => $old['unit_of_measure'],
                        ':reorder'=> $reorder,
                        ':cost'   => $cost,
                    ]);
                    $itemId = (int)$pdo->lastInsertId();

                    if ($old['sku'] === '') {
                        $generated = sku_prefix($old['item_name']) . '-' . str_pad((string)$itemId, 4, '0', STR_PAD_LEFT);
                        $pdo->prepare('UPDATE items SET sku = :s WHERE item_id = :id')
                            ->execute([':s' => $generated, ':id' => $itemId]);
                        $old['sku'] = $generated;
                        $generatedSku = $generated;
                    }

                    // New items start at 0 on hand: open a low-stock alert when a
                    // reorder point is set, so the alerts list matches the dashboard.
                    if ($reorder > 0) {
                        $pdo->prepare('INSERT INTO low_stock_alerts (item_id, quantity_at_alert, reorder_point) VALUES (:i, 0, :r)')
                            ->execute([':i' => $itemId, ':r' => $reorder]);
                    }
                }
                // refresh preferred suppliers (many-to-many)
                $pdo->prepare('DELETE FROM item_suppliers WHERE item_id = :id')->execute([':id' => $itemId]);
                foreach ($chosenSuppliers as $sid) {
                    $pdo->prepare('INSERT INTO item_suppliers (item_id, supplier_id) VALUES (:i, :s)')
                        ->execute([':i' => $itemId, ':s' => $sid]);
                }
                $pdo->commit();

                audit_log($editing ? 'update' : 'create', 'items', $itemId,
                    ($editing ? 'Updated' : 'Created') . " item: {$old['item_name']} (SKU {$old['sku']})",
                    current_user()['id']);
                if ($editing) {
                    flash('success', 'Item updated.');
                } elseif ($generatedSku !== null) {
                    flash('success', "Item created with SKU {$generatedSku}.");
                } else {
                    flash('success', 'Item created.');
                }
                redirect('items/inventory.php');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                if ($e->getCode() === '23000') {
                    $errors['sku'] = 'SKU already exists.';
                } else {
                    error_log('item_form save failed: ' . $e->getMessage());
                    $errors['_form'] = 'Something went wrong while saving. Please try again.';
                }
            }
        }
    }
}

// Refresh selected suppliers from POST when validating (keep typed choices)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['suppliers'])) {
    $selectedSuppliers = array_values(array_intersect(
        array_map('intval', (array)$_POST['suppliers']),
        $activeSupplierIds
    ));
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1><?= $editing ? 'Edit item' : 'Add item' ?></h1>
        <p><?= $editing ? 'Update details, preferred suppliers, or availability.' : 'Create a new inventory item.' ?></p>
    </div>
    <a href="<?= e(url('items/inventory.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to inventory</a>
</div>

<?php if (!empty($errors['_form'])): ?>
    <div class="alert alert-danger"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card-ui p-4">
                <h2 class="h6 fw-bold mb-3">Item details</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="sku">SKU <?= $editing ? '<span class="text-danger">*</span>' : '' ?></label>
                        <input type="text" class="form-control <?= isset($errors['sku']) ? 'is-invalid' : '' ?>"
                               id="sku" name="sku" value="<?= e($old['sku']) ?>" maxlength="50" <?= $editing ? 'required' : '' ?>
                               placeholder="<?= $editing ? '' : 'Leave blank to auto-generate' ?>">
                        <?php if (isset($errors['sku'])): ?><div class="invalid-feedback"><?= e($errors['sku']) ?></div><?php endif; ?>
                        <?php if (!$editing): ?><div class="form-text">Optional. Leave blank to auto-generate from the item name, e.g. <code>NOT-0007</code>.</div><?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="item_name">Item name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['item_name']) ? 'is-invalid' : '' ?>"
                               id="item_name" name="item_name" value="<?= e($old['item_name']) ?>" maxlength="150" required>
                        <?php if (isset($errors['item_name'])): ?><div class="invalid-feedback"><?= e($errors['item_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" maxlength="500"><?= e($old['description']) ?></textarea>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="category_id">Category <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" id="category_id" name="category_id" required>
                            <option value="0">Select</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['category_id'] ?>" <?= (int)$old['category_id'] === (int)$c['category_id'] ? 'selected' : '' ?>>
                                    <?= e($c['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['category_id'])): ?><div class="invalid-feedback"><?= e($errors['category_id']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="unit_of_measure">Unit of measure <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['unit_of_measure']) ? 'is-invalid' : '' ?>"
                               id="unit_of_measure" name="unit_of_measure" value="<?= e($old['unit_of_measure']) ?>" maxlength="30" placeholder="box, pc, ream" required>
                        <?php if (isset($errors['unit_of_measure'])): ?><div class="invalid-feedback"><?= e($errors['unit_of_measure']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="reorder_point">Reorder point</label>
                        <input type="number" min="0" step="1" class="form-control <?= isset($errors['reorder_point']) ? 'is-invalid' : '' ?>"
                               id="reorder_point" name="reorder_point" value="<?= e($old['reorder_point']) ?>">
                        <?php if (isset($errors['reorder_point'])): ?><div class="invalid-feedback"><?= e($errors['reorder_point']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="unit_cost">Unit cost (<?= e(CURRENCY) ?>)</label>
                        <input type="number" min="0" step="0.01" class="form-control <?= isset($errors['unit_cost']) ? 'is-invalid' : '' ?>"
                               id="unit_cost" name="unit_cost" value="<?= e($old['unit_cost']) ?>">
                        <?php if (isset($errors['unit_cost'])): ?><div class="invalid-feedback"><?= e($errors['unit_cost']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card-ui p-4 mt-4">
                <h2 class="h6 fw-bold mb-3">Preferred suppliers</h2>
                <?php if (!$suppliers): ?>
                    <p class="text-muted small mb-0">No active suppliers yet. Add suppliers first.</p>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($suppliers as $s): ?>
                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sup_<?= (int)$s['supplier_id'] ?>"
                                           name="suppliers[]" value="<?= (int)$s['supplier_id'] ?>"
                                           <?= in_array((int)$s['supplier_id'], $selectedSuppliers, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sup_<?= (int)$s['supplier_id'] ?>">
                                        <?= e($s['company_name']) ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card-ui p-4">
                <h2 class="h6 fw-bold mb-3">Availability</h2>
                <?php if ($editing): ?>
                    <p class="mb-2">Status: <strong><?= (int)$item['is_active'] === 1 ? 'Active' : 'Inactive' ?></strong></p>
                    <p class="text-muted small mb-3">On hand: <?= number_format((int)$item['quantity_on_hand']) ?> (read-only, changed by stock transactions)</p>
                    <button type="submit" name="toggle_active" value="1" onclick="return confirm('<?= e((int)$item['is_active'] === 1 ? 'Do you want to deactivate this item?' : 'Do you want to reactivate this item?') ?>');" class="btn btn-sm <?= (int)$item['is_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                        <?= (int)$item['is_active'] === 1 ? 'Deactivate item' : 'Reactivate item' ?>
                    </button>
                <?php else: ?>
                    <p class="text-muted small mb-0">New items start active with 0 on hand. Use Stock-in to add quantities.</p>
                <?php endif; ?>
            </div>
            <div class="d-grid gap-2 mt-3">
                <button type="submit" class="btn btn-primary py-2"><i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save changes' : 'Create item' ?></button>
                <a href="<?= e(url('items/inventory.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
