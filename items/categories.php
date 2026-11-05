<?php
/**
 * categories.php - item category management (admin only; AGENTS.md build step 11).
 * List categories with their item counts; add, rename, delete-unused only.
 * The table has no `is_active`, so a category is only removable while unused.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();
$me  = (int)current_user()['id'];

$errors    = [];
$old       = ['category_id' => 0, 'category_name' => '', 'description' => ''];
$openModal = '';   // 'add' | 'edit' - reopened automatically when a POST failed

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('danger', 'Session expired. Please try again.');
        redirect('items/categories.php');
    }

    $action = $_POST['action'] ?? '';

    // ---- Create / update ----
    if ($action === 'save') {
        $id   = (int)($_POST['category_id'] ?? 0);
        $name = trim((string)($_POST['category_name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $old  = ['category_id' => $id, 'category_name' => $name, 'description' => $desc];

        if ($name === '') {
            $errors['category_name'] = 'Category name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors['category_name'] = 'Category name must be 100 characters or fewer.';
        }
        if (mb_strlen($desc) > 255) {
            $errors['description'] = 'Description must be 255 characters or fewer.';
        }

        if (!$errors) {
            try {
                if ($id > 0) {
                    $prevStmt = $pdo->prepare('SELECT category_name FROM categories WHERE category_id = :id');
                    $prevStmt->execute([':id' => $id]);
                    $prev = $prevStmt->fetchColumn();
                    if ($prev === false) {
                        $errors['_form'] = 'Category not found.';
                    } else {
                        $pdo->prepare('UPDATE categories SET category_name = :n, description = :d WHERE category_id = :id')
                            ->execute([':n' => $name, ':d' => ($desc === '' ? null : $desc), ':id' => $id]);
                        audit_log('update', 'categories', $id, "Updated category: {$prev} -> {$name}", $me);
                        flash('success', 'Category updated.');
                        redirect('items/categories.php');
                    }
                } else {
                    $pdo->prepare('INSERT INTO categories (category_name, description) VALUES (:n, :d)')
                        ->execute([':n' => $name, ':d' => ($desc === '' ? null : $desc)]);
                    $newId = (int)$pdo->lastInsertId();
                    audit_log('create', 'categories', $newId, "Created category: {$name}", $me);
                    flash('success', 'Category added.');
                    redirect('items/categories.php');
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors['category_name'] = 'That category name already exists.';
                } else {
                    error_log('categories save failed: ' . $e->getMessage());
                    $errors['_form'] = 'Could not save the category. Please try again.';
                }
            }
        }

        if ($errors) $openModal = $id > 0 ? 'edit' : 'add';
    }

    // ---- Delete (only while unused) ----
    if ($action === 'delete') {
        $id = (int)($_POST['category_id'] ?? 0);
        $nameStmt = $pdo->prepare('SELECT category_name FROM categories WHERE category_id = :id');
        $nameStmt->execute([':id' => $id]);
        $name = $nameStmt->fetchColumn();

        if ($name === false) {
            flash('danger', 'Category not found.');
        } else {
            $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE category_id = :id');
            $cntStmt->execute([':id' => $id]);
            $used = (int)$cntStmt->fetchColumn();

            if ($used > 0) {
                flash('danger', "\"{$name}\" is used by {$used} item" . ($used === 1 ? '' : 's') . ' and cannot be deleted. Reassign those items first.');
            } else {
                $pdo->prepare('DELETE FROM categories WHERE category_id = :id')->execute([':id' => $id]);
                audit_log('delete', 'categories', $id, "Deleted category: {$name}", $me);
                flash('success', 'Category deleted.');
            }
        }
        redirect('items/categories.php');
    }

    if ($action !== 'save') {
        redirect('items/categories.php');
    }
}

// ---- List with item counts ----
$rows = $pdo->query(
    'SELECT c.category_id, c.category_name, c.description,
            COUNT(i.item_id) AS item_count,
            COALESCE(SUM(CASE WHEN i.is_active = 1 THEN 1 ELSE 0 END), 0) AS active_count
       FROM categories c
       LEFT JOIN items i ON i.category_id = c.category_id
      GROUP BY c.category_id, c.category_name, c.description
      ORDER BY c.category_name'
)->fetchAll();

$pageTitle  = 'Categories';
$activePage = 'categories';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Categories</h1>
        <p><?= number_format(count($rows)) ?> categor<?= count($rows) === 1 ? 'y' : 'ies' ?> &middot; used to group items.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" data-mode="add">
        <i class="bi bi-plus-lg me-1"></i>Add category
    </button>
</div>

<?php if (!empty($errors['_form'])): ?>
    <div class="alert alert-danger"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<div class="card-ui">
    <div class="table-responsive">
        <table class="table table-ui">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Description</th>
                    <th class="text-end">Items</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No categories yet. Add the first one.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php $used = (int)$r['item_count'] > 0; ?>
                    <tr>
                        <td class="fw-semibold"><?= e($r['category_name']) ?></td>
                        <td class="text-muted"><?= e($r['description'] ?? '—') ?></td>
                        <td class="text-end">
                            <span class="badge-soft <?= $used ? 'badge-normal' : 'badge-inactive' ?>"><?= number_format((int)$r['item_count']) ?></span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#catModal"
                                        data-mode="edit"
                                        data-id="<?= (int)$r['category_id'] ?>"
                                        data-name="<?= e($r['category_name']) ?>"
                                        data-desc="<?= e($r['description'] ?? '') ?>"
                                        title="Edit category" aria-label="Edit category">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="post" class="d-inline"
                                      onsubmit="return confirm('Delete category &quot;<?= e($r['category_name']) ?>&quot;?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" value="<?= (int)$r['category_id'] ?>">
                                    <button type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            title="<?= $used ? 'In use — cannot delete' : 'Delete category' ?>"
                                            aria-label="Delete category"
                                            <?= $used ? 'disabled' : '' ?>>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add / edit modal -->
<div class="modal fade" id="catModal" tabindex="-1" aria-labelledby="catModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="category_id" id="catId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="catModalLabel">Add category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="catName">Category name <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control <?= isset($errors['category_name']) ? 'is-invalid' : '' ?>"
                               id="catName" name="category_name" maxlength="100" required>
                        <?php if (isset($errors['category_name'])): ?>
                            <div class="invalid-feedback"><?= e($errors['category_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label" for="catDesc">Description</label>
                        <textarea class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                                  id="catDesc" name="description" rows="3" maxlength="255"></textarea>
                        <?php if (isset($errors['description'])): ?>
                            <div class="invalid-feedback"><?= e($errors['description']) ?></div>
                        <?php endif; ?>
                        <div class="form-text">Optional. Short note, e.g. &ldquo;Notebooks, bond paper, pad paper&rdquo;.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('catModal');
    if (!modal) return;
    var nameEl  = document.getElementById('catName');
    var descEl  = document.getElementById('catDesc');
    var idEl    = document.getElementById('catId');
    var titleEl = document.getElementById('catModalLabel');

    modal.addEventListener('show.bs.modal', function (ev) {
        var t = ev.relatedTarget;
        if (!t) return;
        if (t.getAttribute('data-mode') === 'edit') {
            titleEl.textContent = 'Edit category';
            idEl.value   = t.getAttribute('data-id') || '0';
            nameEl.value = t.getAttribute('data-name') || '';
            descEl.value = t.getAttribute('data-desc') || '';
        } else {
            titleEl.textContent = 'Add category';
            idEl.value   = '0';
            nameEl.value = '';
            descEl.value = '';
        }
    });
})();
</script>
<?php if ($openModal): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('catModal');
    if (!modal) return;
    document.getElementById('catId').value   = <?= (int)$old['category_id'] ?>;
    document.getElementById('catName').value = <?= json_encode($old['category_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    document.getElementById('catDesc').value = <?= json_encode($old['description'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    <?php if ($openModal === 'edit'): ?>document.getElementById('catModalLabel').textContent = 'Edit category';<?php endif; ?>
    new bootstrap.Modal(modal).show();
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
