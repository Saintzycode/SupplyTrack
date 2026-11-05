<?php
/**
 * school_form.php - add / edit school (admin only).
 * Profile lists this school's distribution history (from v_distribution_report).
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle  = 'Add school';
$activePage = 'schools';

$pdo = db();

$schoolId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (isset($_POST['school_id'])) {
    $schoolId = (int)$_POST['school_id'];
}
$editing = $schoolId > 0;

$school = null;
if ($editing) {
    $stmt = $pdo->prepare('SELECT * FROM schools WHERE school_id = :id');
    $stmt->execute([':id' => $schoolId]);
    $school = $stmt->fetch();
    if (!$school) {
        flash('danger', 'School not found.');
        redirect('partners/schools.php');
    }
    $pageTitle = 'Edit school';
}

$old = [
    'school_name'    => $school['school_name'] ?? '',
    'contact_person' => $school['contact_person'] ?? '',
    'phone'          => $school['phone'] ?? '',
    'email'          => $school['email'] ?? '',
    'address'        => $school['address'] ?? '',
];
$errors = [];

// ---- soft delete / reactivate ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    if (!csrf_verify()) {
        flash('danger', 'Session expired. Try again.');
        redirect($editing ? 'partners/school_form.php?id=' . $schoolId : 'partners/schools.php');
    }
    $row = $pdo->prepare('SELECT school_name, is_active FROM schools WHERE school_id = :id');
    $row->execute([':id' => $schoolId]);
    $r = $row->fetch();
    if ($r) {
        $newState = (int)$r['is_active'] === 1 ? 0 : 1;
        $pdo->prepare('UPDATE schools SET is_active = :s, updated_at = NOW() WHERE school_id = :id')
            ->execute([':s' => $newState, ':id' => $schoolId]);
        audit_log($newState === 0 ? 'deactivate' : 'reactivate', 'schools', $schoolId,
            ($newState === 0 ? 'Deactivated' : 'Reactivated') . " school: {$r['school_name']}",
            current_user()['id']);
        flash('success', $newState === 0 ? 'School deactivated.' : 'School reactivated.');
    }
    redirect('partners/schools.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_active'])) {
    if (!csrf_verify()) {
        $errors['_form'] = 'Session expired. Please try again.';
    } else {
        $old['school_name']    = trim($_POST['school_name'] ?? '');
        $old['contact_person'] = trim($_POST['contact_person'] ?? '');
        $old['phone']          = trim($_POST['phone'] ?? '');
        $old['email']          = trim($_POST['email'] ?? '');
        $old['address']        = trim($_POST['address'] ?? '');

        if ($old['school_name'] === '') {
            $errors['school_name'] = 'School name is required.';
        } elseif (mb_strlen($old['school_name']) > 150) {
            $errors['school_name'] = 'School name must be 150 characters or fewer.';
        }
        // duplicate name pre-check
        if (!isset($errors['school_name'])) {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM schools WHERE school_name = :n AND school_id <> :id');
            $dup->execute([':n' => $old['school_name'], ':id' => $schoolId]);
            if ((int)$dup->fetchColumn() > 0) {
                $errors['school_name'] = 'School name already exists.';
            }
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
            } elseif (mb_strlen($old['email']) > 100) {
                $errors['email'] = 'Email must be 100 characters or fewer.';
            }
        }
        if ($old['address'] !== '' && mb_strlen($old['address']) > 255) {
            $errors['address'] = 'Address must be 255 characters or fewer.';
        }

        if (!$errors) {
            try {
                if ($editing) {
                    $upd = $pdo->prepare(
                        'UPDATE schools
                            SET school_name = :n, contact_person = :cp, phone = :ph,
                                email = :em, address = :ad, updated_at = NOW()
                          WHERE school_id = :id'
                    );
                    $upd->execute([
                        ':n'  => $old['school_name'],
                        ':cp' => $old['contact_person'] !== '' ? $old['contact_person'] : null,
                        ':ph' => $old['phone'] !== '' ? $old['phone'] : null,
                        ':em' => $old['email'] !== '' ? $old['email'] : null,
                        ':ad' => $old['address'] !== '' ? $old['address'] : null,
                        ':id' => $schoolId,
                    ]);
                } else {
                    $ins = $pdo->prepare(
                        "INSERT INTO schools (school_name, contact_person, phone, email, address, is_active)
                         VALUES (:n, :cp, :ph, :em, :ad, 1)"
                    );
                    $ins->execute([
                        ':n'  => $old['school_name'],
                        ':cp' => $old['contact_person'] !== '' ? $old['contact_person'] : null,
                        ':ph' => $old['phone'] !== '' ? $old['phone'] : null,
                        ':em' => $old['email'] !== '' ? $old['email'] : null,
                        ':ad' => $old['address'] !== '' ? $old['address'] : null,
                    ]);
                    $schoolId = (int)$pdo->lastInsertId();
                }
                audit_log($editing ? 'update' : 'create', 'schools', $schoolId,
                    ($editing ? 'Updated' : 'Created') . " school: {$old['school_name']}",
                    current_user()['id']);
                flash('success', $editing ? 'School updated.' : 'School created.');
                redirect('partners/schools.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors['school_name'] = 'School name already exists.';
                } else {
                    error_log('school_form save failed: ' . $e->getMessage());
                    $errors['_form'] = 'Something went wrong while saving. Please try again.';
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

// ---- Profile: distribution history grouped by distribution ----
$distributions = [];
if ($editing && $school) {
    $dr = $pdo->prepare(
        'SELECT distribution_id, distribution_date, item_id, sku, item_name, unit_of_measure,
                quantity, remarks, recorded_by_name
           FROM v_distribution_report
          WHERE school_id = :id
          ORDER BY distribution_date DESC, distribution_id DESC, item_id'
    );
    $dr->execute([':id' => $schoolId]);
    // group rows by distribution_id
    foreach ($dr->fetchAll() as $row) {
        $did = (int)$row['distribution_id'];
        if (!isset($distributions[$did])) {
            $distributions[$did] = [
                'date'        => $row['distribution_date'],
                'remarks'     => $row['remarks'],
                'recorded_by' => $row['recorded_by_name'],
                'items'       => [],
            ];
        }
        $distributions[$did]['items'][] = $row;
    }
}
?>
<div class="page-head">
    <div>
        <h1><?= $editing ? 'Edit school' : 'Add school' ?></h1>
        <p><?= $editing ? 'Update school details and availability.' : 'Create a new school / customer.' ?></p>
    </div>
    <a href="<?= e(url('partners/schools.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to schools</a>
</div>

<?php if (!empty($errors['_form'])): ?>
    <div class="alert alert-danger"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="school_id" value="<?= (int)$schoolId ?>">

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card-ui p-4">
                <h2 class="h6 fw-bold mb-3">School details</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="school_name">School name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['school_name']) ? 'is-invalid' : '' ?>"
                               id="school_name" name="school_name" value="<?= e($old['school_name']) ?>" maxlength="150" required>
                        <?php if (isset($errors['school_name'])): ?><div class="invalid-feedback"><?= e($errors['school_name']) ?></div><?php endif; ?>
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
                               id="email" name="email" value="<?= e($old['email']) ?>" maxlength="100">
                        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="address">Address</label>
                        <textarea class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>"
                                  id="address" name="address" rows="2" maxlength="255"><?= e($old['address']) ?></textarea>
                        <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?= e($errors['address']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($editing && $distributions): ?>
                <div class="card-ui p-4 mt-4">
                    <h2 class="h6 fw-bold mb-3">Distribution history</h2>
                    <div class="accordion" id="distAccordion">
                        <?php $i = 0; foreach ($distributions as $did => $d): ?>
                            <?php $i++; ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="h<?= $did ?>">
                                    <button class="accordion-button <?= $i === 1 ? '' : 'collapsed' ?>" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#c<?= $did ?>" aria-expanded="<?= $i === 1 ? 'true' : 'false' ?>">
                                        <?= e(fmt_date($d['date'])) ?>
                                        &middot; <?= count($d['items']) ?> item<?= count($d['items']) === 1 ? '' : 's' ?>
                                        &middot; by <?= e($d['recorded_by'] ?? '—') ?>
                                    </button>
                                </h2>
                                <div id="c<?= $did ?>" class="accordion-collapse collapse <?= $i === 1 ? 'show' : '' ?>" data-bs-parent="#distAccordion">
                                    <div class="accordion-body p-0">
                                        <table class="table table-ui mb-0">
                                            <thead>
                                                <tr>
                                                    <th>SKU</th><th>Item</th><th class="text-end">Qty</th><th>Unit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($d['items'] as $it): ?>
                                                    <tr>
                                                        <td class="text-muted"><?= e($it['sku']) ?></td>
                                                        <td class="fw-semibold"><?= e($it['item_name']) ?></td>
                                                        <td class="text-end"><?= number_format((int)$it['quantity']) ?></td>
                                                        <td><?= e($it['unit_of_measure']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (!empty($d['remarks'])): ?>
                                                    <tr>
                                                        <td colspan="4" class="text-muted small">Remarks: <?= e($d['remarks']) ?></td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($editing): ?>
                <div class="card-ui p-4 mt-4">
                    <h2 class="h6 fw-bold mb-2">Distribution history</h2>
                    <p class="text-muted small mb-0">No distributions recorded for this school yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card-ui p-4">
                <h2 class="h6 fw-bold mb-3">Availability</h2>
                <?php if ($editing): ?>
                    <p class="mb-2">Status: <strong><?= (int)$school['is_active'] === 1 ? 'Active' : 'Inactive' ?></strong></p>
                    <p class="text-muted small mb-3">
                        <?= (int)$school['is_active'] === 1
                            ? 'School is available for new distributions.'
                            : 'School is excluded from new distributions.' ?>
                    </p>
                    <button type="submit" name="toggle_active" value="1" onclick="return confirm('<?= e((int)$school['is_active'] === 1 ? 'Do you want to deactivate this school?' : 'Do you want to reactivate this school?') ?>');"
                            class="btn btn-sm <?= (int)$school['is_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                        <?= (int)$school['is_active'] === 1 ? 'Deactivate school' : 'Reactivate school' ?>
                    </button>
                <?php else: ?>
                    <p class="text-muted small mb-0">New schools start active.</p>
                <?php endif; ?>
            </div>
            <div class="d-grid gap-2 mt-3">
                <button type="submit" class="btn btn-primary py-2"><i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save changes' : 'Create school' ?></button>
                <a href="<?= e(url('partners/schools.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
