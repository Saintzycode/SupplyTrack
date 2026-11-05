<?php
/**
 * schools.php - school list (AGENTS.md build step 6; admin only).
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle  = 'Schools';
$activePage = 'schools';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();

$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
if (!in_array($status, ['', 'active', 'inactive'], true)) $status = '';

$wheres = []; $params = [];
if ($q !== '') {
    $wheres[] = '(school_name LIKE :q1 OR contact_person LIKE :q2 OR email LIKE :q3 OR phone LIKE :q4)';
    $like = '%' . $q . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
    $params[':q4'] = $like;
}
if ($status === 'active')   $wheres[] = 'is_active = 1';
if ($status === 'inactive') $wheres[] = 'is_active = 0';
$whereSql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM schools $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $perPage;

$listStmt = $pdo->prepare(
    "SELECT school_id, school_name, contact_person, phone, email, is_active
       FROM schools
       $whereSql
       ORDER BY is_active DESC, school_name ASC
       LIMIT :lim OFFSET :off"
);
foreach ($params as $k => $v) $listStmt->bindValue($k, $v);
$listStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':off', $offset, PDO::PARAM_INT);
$listStmt->execute();
$rows = $listStmt->fetchAll();

function school_page_url(int $p, string $q, string $status): string {
    $qs = http_build_query(array_filter([
        'page'   => $p,
        'q'      => $q !== '' ? $q : null,
        'status' => $status !== '' ? $status : null,
    ], function ($v) { return $v !== null; }));
    return url('partners/schools.php' . ($qs !== '' ? '?' . $qs : ''));
}
?>
<div class="page-head">
    <div>
        <h1>Schools</h1>
        <p><?= number_format($total) ?> school<?= $total === 1 ? '' : 's' ?>.</p>
    </div>
    <a href="<?= e(url('partners/school_form.php')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add school</a>
</div>

<form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('partners/schools.php')) ?>">
    <div class="col-12 col-md-5">
        <label class="form-label small text-muted mb-0" for="q">Search</label>
        <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="school name, contact, email, phone">
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small text-muted mb-0" for="status">Status</label>
        <select class="form-select" id="status" name="status">
            <option value="" <?= $status === '' ? 'selected' : '' ?>>All</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
    <div class="col-6 col-md-2 d-grid">
        <button type="submit" class="btn btn-outline-secondary">Apply</button>
    </div>
    <?php if ($q !== '' || $status !== ''): ?>
        <div class="col-12">
            <a href="<?= e(url('partners/schools.php')) ?>" class="small text-decoration-none">Clear filters</a>
        </div>
    <?php endif; ?>
</form>

<div class="card-ui">
    <div class="table-responsive">
        <table class="table table-ui">
            <thead>
                <tr>
                    <th>School name</th>
                    <th>Contact person</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <?= ($q !== '' || $status !== '') ? 'No schools match your filters.' : 'No schools yet. Add the first one.' ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($r['school_name']) ?></td>
                        <td><?= e($r['contact_person'] ?? '—') ?></td>
                        <td><?= e($r['phone'] ?? '—') ?></td>
                        <td class="text-muted"><?= e($r['email'] ?? '—') ?></td>
                        <td>
                            <?php if ((int)$r['is_active'] === 1): ?>
                                <span class="badge-soft badge-normal">Active</span>
                            <?php else: ?>
                                <span class="badge-soft badge-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= e(url('partners/school_form.php?id=' . (int)$r['school_id'])) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3">
            <div class="small text-muted">Page <?= $page ?> of <?= $pages ?> &middot; <?= number_format($total) ?> total</div>
            <nav aria-label="Schools pages">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(school_page_url($page - 1, $q, $status)) ?>">Previous</a></li>
                    <?php
                        $start = max(1, $page - 3); $end = min($pages, $page + 3);
                        for ($p = $start; $p <= $end; $p++):
                    ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(school_page_url($p, $q, $status)) ?>"><?= $p ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(school_page_url($page + 1, $q, $status)) ?>">Next</a></li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
