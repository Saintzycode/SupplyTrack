<?php
/**
 * distribution.php - record a distribution to a school (AGENTS.md build step 7).
 */
$pageTitle  = 'Distribution';
$activePage = 'distribution';
require_once __DIR__ . '/includes/header.php';

$pdo = db();

$schools = $pdo->query(
    'SELECT school_id, school_name, is_active FROM schools WHERE is_active = 1 ORDER BY school_name'
)->fetchAll();
$items = $pdo->query(
    'SELECT item_id, sku, item_name, unit_of_measure, quantity_on_hand, is_active
       FROM items WHERE is_active = 1 ORDER BY item_name'
)->fetchAll();
$activeItemIds = array_map('intval', array_column($items, 'item_id'));
$stockMap = [];
foreach ($items as $it) {
    $stockMap[(int)$it['item_id']] = (int)$it['quantity_on_hand'];
}

// ---- Render previous submitted rows (or one blank row on first load) ----
function posted_lines(): array
{
    $ids  = $_POST['items'] ?? [];
    $qtys = $_POST['quantities'] ?? [];
    $lines = [];
    foreach (array_keys($ids) as $i) {
        $lines[] = [
            'item_id'  => (int)$ids[$i],
            'quantity' => trim((string)($qtys[$i] ?? '')),
        ];
    }
    if (!$lines) {
        $lines[] = ['item_id' => 0, 'quantity' => ''];
    }
    return $lines;
}

$lines = (($_SERVER['REQUEST_METHOD'] === 'POST') ? posted_lines() : [['item_id' => 0, 'quantity' => '']]);

$oldSchool   = (int)($_GET['school_id'] ?? 0);
$oldDate     = date('Y-m-d');
$oldRemarks  = '';
$errors      = [];
$lineErrors  = [];
$submitError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors['_form'] = 'Session expired. Please try again.';
    } else {
        $oldSchool  = (int)($_POST['school_id'] ?? 0);
        $oldDate    = trim($_POST['distribution_date'] ?? '');
        $oldRemarks = trim($_POST['remarks'] ?? '');
        $lines      = posted_lines();

        // validate school
        $validSchoolIds = array_map('intval', array_column($schools, 'school_id'));
        if (!in_array($oldSchool, $validSchoolIds, true)) {
            $errors['school_id'] = 'Select a school.';
        }

        // validate date
        $dt = DateTime::createFromFormat('Y-m-d', $oldDate);
        if ($dt === false || $dt->format('Y-m-d') !== $oldDate) {
            $errors['distribution_date'] = 'Enter a valid date.';
        }

        if ($oldRemarks !== '' && mb_strlen($oldRemarks) > 255) {
            $errors['remarks'] = 'Remarks must be 255 characters or fewer.';
        }

        // validate lines
        $seen = [];
        foreach ($lines as $idx => $ln) {
            $le = [];
            if (!in_array($ln['item_id'], $activeItemIds, true)) {
                $le['item_id'] = 'Select an item.';
            } elseif (in_array($ln['item_id'], $seen, true)) {
                $le['item_id'] = 'Duplicate item in this distribution.';
            }
            if ($le === [] && $ln['item_id'] > 0) {
                $seen[] = $ln['item_id'];
            }
            if ($ln['quantity'] === '' || !ctype_digit($ln['quantity']) || (int)$ln['quantity'] <= 0) {
                $le['quantity'] = 'Quantity must be a whole number greater than 0.';
            }
            // sufficient-stock pre-check
            if (!isset($le['quantity']) && isset($ln['item_id']) && in_array($ln['item_id'], $activeItemIds, true)) {
                $avail = $stockMap[$ln['item_id']] ?? 0;
                if ((int)$ln['quantity'] > $avail) {
                    $le['quantity'] = 'Insufficient stock. Available: ' . number_format($avail) . '.';
                }
            }
            if ($le) {
                $lineErrors[$idx] = $le;
            }
        }
        // require at least one fully-filled line
        $hasValidLine = false;
        foreach ($lines as $idx => $ln) {
            if (empty($lineErrors[$idx]) && $ln['item_id'] > 0 && $ln['quantity'] !== '') {
                $hasValidLine = true;
            }
        }
        // remove entirely blank rows from consideration? if all blank, error
        $allBlank = true;
        foreach ($lines as $ln) {
            if ($ln['item_id'] > 0 || $ln['quantity'] !== '') { $allBlank = false; break; }
        }
        if ($allBlank) {
            $errors['lines'] = 'Add at least one item line.';
        }
        // prune blank trailing rows before saving
        $saveLines = [];
        foreach ($lines as $idx => $ln) {
            if ($ln['item_id'] === 0 && $ln['quantity'] === '') continue;
            $saveLines[] = $ln;
        }
        if ($allBlank) $saveLines = [];

        if (!$errors && !$lineErrors && $saveLines) {
            try {
                $pdo->beginTransaction();
                $insDist = $pdo->prepare(
                    "INSERT INTO distributions (school_id, distribution_date, remarks, recorded_by)
                     VALUES (:s, :d, :r, :by)"
                );
                $insDist->execute([
                    ':s'  => $oldSchool,
                    ':d'  => $oldDate . ' 00:00:00',
                    ':r'  => $oldRemarks !== '' ? $oldRemarks : null,
                    ':by' => (int)current_user()['id'],
                ]);
                $distId = (int)$pdo->lastInsertId();

                $insLine = $pdo->prepare(
                    "INSERT INTO distribution_items (distribution_id, item_id, quantity)
                     VALUES (:d, :i, :q)"
                );
                foreach ($saveLines as $ln) {
                    $insLine->execute([
                        ':d' => $distId,
                        ':i' => (int)$ln['item_id'],
                        ':q' => (int)$ln['quantity'],
                    ]);
                }
                $pdo->commit();

                audit_log('create', 'distributions', $distId,
                    'Distribution to school ' . $oldSchool . ': ' . count($saveLines) . ' item(s)', current_user()['id']);
                flash('success', 'Distribution recorded. Stock reduced for ' . count($saveLines) . ' item(s).');
                redirect('distribution.php');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                if ($e->getCode() === '45000') {
                    $submitError = $e->errorInfo[2] ?? $e->getMessage();
                } else {
                    error_log('distribution insert failed: ' . $e->getMessage());
                    $submitError = 'Something went wrong while recording the distribution. Please try again.';
                }
            }
        }
    }
}
?>
<div class="page-head">
    <div>
        <h1>Distribution</h1>
        <p>Record supplies distributed to a school.</p>
    </div>
</div>

<?php if ($submitError): ?>
    <div class="alert alert-danger"><?= e($submitError) ?></div>
<?php endif; ?>
<?php if (!empty($errors['_form'])): ?>
    <div class="alert alert-danger"><?= e($errors['_form']) ?></div>
<?php endif; ?>
<?php if (isset($errors['lines'])): ?>
    <div class="alert alert-danger"><?= e($errors['lines']) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card-ui p-4">
            <form method="post" novalidate id="distForm">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="school_id">School <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['school_id']) ? 'is-invalid' : '' ?>"
                                id="school_id" name="school_id" required>
                            <option value="0">Select a school</option>
                            <?php foreach ($schools as $sc): ?>
                                <option value="<?= (int)$sc['school_id'] ?>" <?= $oldSchool === (int)$sc['school_id'] ? 'selected' : '' ?>>
                                    <?= e($sc['school_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['school_id'])): ?><div class="invalid-feedback"><?= e($errors['school_id']) ?></div><?php endif; ?>
                        <?php if (!$schools): ?>
                            <div class="form-text text-muted">No active schools. Add one first.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="distribution_date">Date <span class="text-danger">*</span></label>
                        <input type="date"
                               class="form-control <?= isset($errors['distribution_date']) ? 'is-invalid' : '' ?>"
                               id="distribution_date" name="distribution_date" value="<?= e($oldDate) ?>"
                               data-future-warn="Stock will be deducted immediately, even though this date is in the future." required>
                        <?php if (isset($errors['distribution_date'])): ?><div class="invalid-feedback"><?= e($errors['distribution_date']) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">Item lines <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="addLineBtn">
                            <i class="bi bi-plus-lg me-1"></i>Add line
                        </button>
                    </div>
                    <div id="lines">
                        <?php foreach ($lines as $idx => $ln): ?>
                            <?php $le = $lineErrors[$idx] ?? []; ?>
                            <div class="line-row row g-2 align-items-start mb-2">
                                <div class="col-12 col-md-7">
                                    <select class="form-select item-select <?= isset($le['item_id']) ? 'is-invalid' : '' ?>"
                                            name="items[]" required data-idx="<?= $idx ?>">
                                        <option value="0" data-qty="">Select item</option>
                                        <?php foreach ($items as $it): ?>
                                            <option value="<?= (int)$it['item_id'] ?>"
                                                    data-qty="<?= (int)$it['quantity_on_hand'] ?>"
                                                    <?= (int)$ln['item_id'] === (int)$it['item_id'] ? 'selected' : '' ?>>
                                                <?= e($it['sku'] . ' - ' . $it['item_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text avail-text small text-muted">Available: —</div>
                                    <?php if (isset($le['item_id'])): ?><div class="invalid-feedback d-block"><?= e($le['item_id']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-8 col-md-3">
                                    <input type="number" min="1" step="1"
                                           class="form-control <?= isset($le['quantity']) ? 'is-invalid' : '' ?>"
                                           name="quantities[]" value="<?= e($ln['quantity']) ?>" placeholder="Qty" required>
                                    <?php if (isset($le['quantity'])): ?><div class="invalid-feedback"><?= e($le['quantity']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-4 col-md-2 d-grid">
                                    <button type="button" class="btn btn-outline-danger remove-line">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <template id="lineTemplate">
                        <div class="line-row row g-2 align-items-start mb-2">
                            <div class="col-12 col-md-7">
                                <select class="form-select item-select" name="items[]" required>
                                    <option value="0" data-qty="">Select item</option>
                                    <?php foreach ($items as $it): ?>
                                        <option value="<?= (int)$it['item_id'] ?>" data-qty="<?= (int)$it['quantity_on_hand'] ?>">
                                            <?= e($it['sku'] . ' - ' . $it['item_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text avail-text small text-muted">Available: —</div>
                            </div>
                            <div class="col-8 col-md-3">
                                <input type="number" min="1" step="1" class="form-control" name="quantities[]" placeholder="Qty" required>
                            </div>
                            <div class="col-4 col-md-2 d-grid">
                                <button type="button" class="btn btn-outline-danger remove-line"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mb-3 mt-3">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea class="form-control <?= isset($errors['remarks']) ? 'is-invalid' : '' ?>"
                              id="remarks" name="remarks" rows="2" maxlength="255"><?= e($oldRemarks) ?></textarea>
                    <?php if (isset($errors['remarks'])): ?><div class="invalid-feedback"><?= e($errors['remarks']) ?></div><?php endif; ?>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" <?= ($schools && $items) ? '' : 'disabled' ?>>
                        <i class="bi bi-send me-1"></i>Record distribution
                    </button>
                    <a href="<?= e(url('distribution.php')) ?>" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card-ui p-4">
            <h2 class="h6 fw-bold mb-3">How it works</h2>
            <ul class="small text-muted ps-3 mb-0">
                <li>Each line counts as a stock-out and reduces on-hand quantity.</li>
                <li>Blocked if any line exceeds available stock.</li>
                <li>All lines are saved in one transaction; any failure rolls back everything.</li>
                <li>Inactive items cannot be distributed.</li>
            </ul>
        </div>
        <div class="card-ui p-4 mt-3">
            <h2 class="h6 fw-bold mb-3">Tips</h2>
            <p class="text-muted small mb-0">
                Use Add line for multiple items. Remove a line with the trash button. Available stock is shown per line.
            </p>
        </div>
    </div>
</div>

<script>
(function(){
    const lines = document.getElementById('lines');
    const tmpl = document.getElementById('lineTemplate');
    const addBtn = document.getElementById('addLineBtn');
    const stock = <?= json_encode($stockMap, JSON_UNESCAPED_SLASHES) ?>;

    function updateAvail(sel){
        const id = parseInt(sel.value, 10) || 0;
        const avail = (id && stock[id] !== undefined) ? stock[id] : null;
        const text = sel.parentElement.querySelector('.avail-text');
        if (text) {
            text.textContent = avail === null ? 'Available: —' : 'Available: ' + avail.toLocaleString();
            text.classList.toggle('text-danger', avail !== null && avail <= 0);
        }
    }

    function wireRow(row){
        const sel = row.querySelector('.item-select');
        if (sel) {
            sel.addEventListener('change', function(){ updateAvail(sel); });
            updateAvail(sel);
        }
        const rm = row.querySelector('.remove-line');
        if (rm) {
            rm.addEventListener('click', function(){
                if (lines.querySelectorAll('.line-row').length <= 1) return;
                row.remove();
            });
        }
    }

    lines.querySelectorAll('.line-row').forEach(wireRow);

    addBtn.addEventListener('click', function(){
        const node = tmpl.content.cloneNode(true);
        const row = node.querySelector('.line-row');
        lines.appendChild(row);
        wireRow(row);
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
