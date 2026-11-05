<?php
/**
 * supplier_form.php - add / edit supplier (admin only).
 * Includes the supplier profile (linked items) in edit mode.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle  = 'Add supplier';
$activePage = 'suppliers';

$pdo = db();

$supplierId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (isset($_POST['supplier_id'])) {
    $supplierId = (int)$_POST['supplier_id'];
}
$editing = $supplierId > 0;

$supplier = null;
if ($editing) {
    $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE supplier_id = :id');
    $stmt->execute([':id' => $supplierId]);
    $supplier = $stmt->fetch();
    if (!$supplier) {
        flash('danger', 'Supplier not found.');
        redirect('partners/suppliers.php');
    }
    $pageTitle = 'Edit supplier';
}

$old = [
    'company_name'   => $supplier['company_name'] ?? '',
    'contact_person' => $supplier['contact_person'] ?? '',
    'phone'          => $supplier['phone'] ?? '',
    'email'          => $supplier['email'] ?? '',
    'address'        => $supplier['address'] ?? '',
    'payment_terms'  => $supplier['payment_terms'] ?? '',
];
$errors = [];
$notice = null;

// ---- Soft delete / reactivate ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    if (!csrf_verify()) {
        flash('danger', 'Session expired. Try again.');
        redirect($editing ? 'partners/supplier_form.php?id=' . $supplierId : 'partners/suppliers.php');
    }
    $row = $pdo->prepare('SELECT company_name, is_active FROM suppliers WHERE supplier_id = :id');
    $row->execute([':id' => $supplierId]);
    $r = $row->fetch();
    if ($r) {
        $newState = (int)$r['is_active'] === 1 ? 0 : 1;
        $pdo->prepare('UPDATE suppliers SET is_active = :s, updated_at = NOW() WHERE supplier_id = :id')
            ->execute([':s' => $newState, ':id' => $supplierId]);
        audit_log($newState === 0 ? 'deactivate' : 'reactivate', 'suppliers', $supplierId,
            ($newState === 0 ? 'Deactivated' : 'Reactivated') . " supplier: {$r['company_name']}",
            current_user()['id']);
        flash('success', $newState === 0 ? 'Supplier deactivated.' : 'Supplier reactivated.');
    }
    redirect('partners/suppliers.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_active'])) {
    if (!csrf_verify()) {
        $errors['_form'] = 'Session expired. Please try again.';
    } else {
        $old['company_name']   = trim($_POST['company_name'] ?? '');
        $old['contact_person'] = trim($_POST['contact_person'] ?? '');
        $old['phone']          = trim($_POST['phone'] ?? '');
        $old['email']          = trim($_POST['email'] ?? '');
        $old['address']        = trim($_POST['address'] ?? '');
        $old['payment_terms']  = trim($_POST['payment_terms'] ?? '');

        if ($old['company_name'] === '') {
            $errors['company_name'] = 'Company name is required.';
        } elseif (mb_strlen($old['company_name']) > 150) {
            $errors['company_name'] = 'Company name must be 150 characters or fewer.';
        }
        if ($old['contact_person'] !== '' && mb_strlen($old['contact_person']) > 100) {
            $errors['contact_person'] = 'Contact person must be 100 characters or fewer.';
        }
        if ($old['phone'] !== '' && mb_strlen($old['phone']) > 30) {
            $errors['phone'] = 'Phone must be 30 characters or fewer.';
        }
        if ($old['email'] !== '') {
            if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Enter a valid email address.';
            } elseif (mb_strlen($old['email']) > 150) {
                $errors['email'] = 'Email must be 150 characters or fewer.';
            }
        }
        if ($old['address'] !== '' && mb_strlen($old['address']) > 255) {
            $errors['address'] = 'Address must be 255 characters or fewer.';
        }
        if ($old['payment_terms'] !== '' && mb_strlen($old['payment_terms']) > 100) {
            $errors['payment_terms'] = 'Payment terms must be 100 characters or fewer.';
        }

        if (!$errors) {
            try {
                if ($editing) {
                    $upd = $pdo->prepare(
                        'UPDATE suppliers
                            SET company_name = :c, contact_person = :cp, phone = :ph, email = :em,
                                address = :ad, payment_terms = :pt, updated_at = NOW()
                          WHERE supplier_id = :id'
                    );
                    $upd->execute([
                        ':c'  => $old['company_name'],
                        ':cp' => $old['contact_person'] !== '' ? $old['contact_person'] : null,
                        ':ph' => $old['phone'] !== '' ? $old['phone'] : null,
                        ':em' => $old['email'] !== '' ? $old['email'] : null,
                        ':ad' => $old['address'] !== '' ? $old['address'] : null,
                        ':pt' => $old['payment_terms'] !== '' ? $old['payment_terms'] : null,
                        ':id' => $supplierId,
                    ]);
                } else {
                    $ins = $pdo->prepare(
                        "INSERT INTO suppliers (company_name, contact_person, phone, email, address, payment_terms, is_active)
                         VALUES (:c, :cp, :ph, :em, :ad, :pt, 1)"
                    );
                    $ins->execute([
                        ':c'  => $old['company_name'],
                        ':cp' => $old['contact_person'] !== '' ? $old['contact_person'] : null,
                        ':ph' => $old['phone'] !== '' ? $old['phone'] : null,
                        ':em' => $old['email'] !== '' ? $old['email'] : null,
                        ':ad' => $old['address'] !== '' ? $old['address'] : null,
                        ':pt' => $old['payment_terms'] !== '' ? $old['payment_terms'] : null,
                    ]);
                    $supplierId = (int)$pdo->lastInsertId();
                }
                audit_log($editing ? 'update' : 'create', 'suppliers', $supplierId,
                    ($editing ? 'Updated' : 'Created') . " supplier: {$old['company_name']}",
                    current_user()['id']);
                flash('success', $editing ? 'Supplier updated.' : 'Supplier created.');
                redirect('partners/suppliers.php');
            } catch (PDOException $e) {
                error_log('supplier_form save failed: ' . $e->getMessage());
                $errors['_form'] = 'Something went wrong while saving. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

// ---- Supplier profile (linked items) in edit mode ----
$linkedItems = [];
if ($editing && $supplier) {
    $li = $pdo->prepare(
        'SELECT i.item_id, i.sku, i.item_name, i.unit_of_measure, i.quantity_on_hand, i.is_active, c.category_name
           FROM item_suppliers its
           JOIN items i ON i.item_id = its.item_id
           LEFT JOIN categories c ON c.category_id = i.category_id
          WHERE its.supplier_id = :id
          ORDER BY i.item_name'
    );
    $li->execute([':id' => $supplierId]);
    $linkedItems = $li->fetchAll();
}
?>
<div class="page-head">
    <div>
        <h1><?= $editing ? 'Edit supplier' : 'Add supplier' ?></h1>
        <p><?= $editing ? 'Update supplier details and availability.' : 'Create a new supplier.' ?></p>
    </div>
    <a href="<?= e(url('partners/suppliers.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to suppliers</a>
</div>

<?php if (!empty($errors['_form'])): ?>
    <div class="alert alert-danger"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="supplier_id" value="<?= (int)$supplierId ?>">

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card-ui p-4">
                <h2 class="h6 fw-bold mb-3">Supplier details</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="company_name">Company name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['company_name']) ? 'is-invalid' : '' ?>"
                               id="company_name" name="company_name" value="<?= e($old['company_name']) ?>" maxlength="150" required>
                        <?php if (isset($errors['company_name'])): ?><div class="invalid-feedback"><?= e($errors['company_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="contact_person">Contact person</label>
                        <input type="text" class="form-control <?= isset($errors['contact_person']) ? 'is-invalid' : '' ?>"
                               id="contact_person" name="contact_person" value="<?= e($old['contact_person']) ?>" maxlength="100">
                        <?php if (isset($errors['contact_person'])): ?><div class="invalid-feedback"><?= e($errors['contact_person']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="phone">Phone</label>
                        <input type="text" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                               id="phone" name="phone" value="<?= e($old['phone']) ?>" maxlength="30">
                        <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               id="email" name="email" value="<?= e($old['email']) ?>" maxlength="150">
                        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="payment_terms">Payment terms</label>
                        <input type="text" class="form-control <?= isset($errors['payment_terms']) ? 'is-invalid' : '' ?>"
                               id="payment_terms" name="payment_terms" value="<?= e($old['payment_terms']) ?>" maxlength="100" placeholder="Net 30, COD">
                        <?php if (isset($errors['payment_terms'])): ?><div class="invalid-feedback"><?= e($errors['payment_terms']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="address">Address</label>
                        <textarea class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>"
                                  id="address" name="address" rows="2" maxlength="255"><?= e($old['address']) ?></textarea>
                        <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?= e($errors['address']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($editing): ?>
                <div class="card-ui p-4 mt-4">
                    <h2 class="h6 fw-bold mb-3">Supplier profile — linked items</h2>
                    <?php if (!$linkedItems): ?>
                        <p class="text-muted small mb-0">No items linked to this supplier. Link items from Inventory → item Edit → Preferred suppliers.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-ui mb-0">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Item</th>
                                        <th>Category</th>
                                        <th>Unit</th>
                                        <th class="text-end">On hand</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($linkedItems as $li): ?>
                                        <tr>
                                            <td class="text-muted"><?= e($li['sku']) ?></td>
                                            <td class="fw-semibold"><?= e($li['item_name']) ?></td>
                                            <td><?= e($li['category_name'] ?? '—') ?></td>
                                            <td><?= e($li['unit_of_measure']) ?></td>
                                            <td class="text-end"><?= number_format((int)$li['quantity_on_hand']) ?></td>
                                            <td>
                                                <?php if ((int)$li['is_active'] !== 1): ?>
                                                    <span class="badge-soft badge-inactive">Inactive</span>
                                                <?php elseif ((int)$li['quantity_on_hand'] <= 0): ?>
                                                    <span class="badge-soft badge-low">Low</span>
                                                <?php else: ?>
                                                    <span class="badge-soft badge-normal">Normal</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card-ui p-4">
                <h2 class="h6 fw-bold mb-3">Availability</h2>
                <?php if ($editing): ?>
                    <p class="mb-2">Status: <strong><?= (int)$supplier['is_active'] === 1 ? 'Active' : 'Inactive' ?></strong></p>
                    <p class="text-muted small mb-3">
                        <?= (int)$supplier['is_active'] === 1
                            ? 'Supplier is available for new stock-in transactions.'
                            : 'Supplier is excluded from new stock-in transactions.' ?>
                    </p>
                    <button type="submit" name="toggle_active" value="1" onclick="return confirm('<?= e((int)$supplier['is_active'] === 1 ? 'Do you want to deactivate this supplier?' : 'Do you want to reactivate this supplier?') ?>');"
                            class="btn btn-sm <?= (int)$supplier['is_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                        <?= (int)$supplier['is_active'] === 1 ? 'Deactivate supplier' : 'Reactivate supplier' ?>
                    </button>
                <?php else: ?>
                    <p class="text-muted small mb-0">New suppliers start active. Link items to them from Inventory → item Edit → Preferred suppliers.</p>
                <?php endif; ?>
            </div>
            <div class="d-grid gap-2 mt-3">
                <button type="submit" class="btn btn-primary py-2"><i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save changes' : 'Create supplier' ?></button>
                <a href="<?= e(url('partners/suppliers.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
