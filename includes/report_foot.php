<?php
/**
 * includes/report_foot.php - totals strip + pagination shared by the three
 * report tables in reports.php. Expects: $sumTitle, $totals, $total, $page,
 * $pages, $tab and rep_url().
 */
if (!isset($sumTitle)) return;
?>
<div class="report-summary">
    <span class="report-summary-title"><?= e($sumTitle) ?> summary</span>
    <?php foreach ($totals as $label => $value): ?>
        <span class="report-summary-item"><span class="text-muted"><?= e($label) ?>:</span> <strong><?= number_format((int)$value) ?></strong></span>
    <?php endforeach; ?>
</div>
<?php if ($pages > 1): ?>
    <div class="d-flex justify-content-between align-items-center p-3">
        <div class="small text-muted">Page <?= $page ?> of <?= $pages ?> &middot; <?= number_format($total) ?> total</div>
        <nav aria-label="Report pages"><ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e(rep_url(['tab' => $tab, 'page' => $page - 1])) ?>">Previous</a>
            </li>
            <?php
                $start = max(1, $page - 3);
                $end   = min($pages, $page + 3);
                for ($p = $start; $p <= $end; $p++):
            ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e(rep_url(['tab' => $tab, 'page' => $p])) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e(rep_url(['tab' => $tab, 'page' => $page + 1])) ?>">Next</a>
            </li>
        </ul></nav>
    </div>
<?php endif; ?>
