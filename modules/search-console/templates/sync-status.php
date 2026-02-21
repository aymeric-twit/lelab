<?php
$pageTitle   = 'Synchronisations — Search Console';
$currentPage = 'sync';
ob_start();
?>

<h2 style="margin-bottom:1rem">Historique des synchronisations</h2>

<div style="margin-bottom:1rem">
    <form method="POST" action="<?= defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '' ?>/api/sync" style="display:inline">
        <button type="submit" class="btn">Lancer une synchronisation maintenant</button>
    </form>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Site</th>
                <th>Type</th>
                <th>Periode</th>
                <th>Lignes</th>
                <th>Inserees</th>
                <th>Duree</th>
                <th>Statut</th>
                <th>Erreur</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= $log['id'] ?></td>
                <td class="truncate"><?= htmlspecialchars($log['site_url'] ?? '') ?></td>
                <td><?= htmlspecialchars($log['search_type']) ?></td>
                <td><?= $log['date_from'] ?> &rarr; <?= $log['date_to'] ?></td>
                <td class="num"><?= number_format((int)$log['rows_fetched'], 0, ',', ' ') ?></td>
                <td class="num"><?= number_format((int)$log['rows_inserted'], 0, ',', ' ') ?></td>
                <td class="num"><?= $log['duration_sec'] ? number_format($log['duration_sec'], 1) . 's' : '-' ?></td>
                <td>
                    <?php if ($log['status'] === 'success'): ?>
                        <span class="badge badge-success">OK</span>
                    <?php elseif ($log['status'] === 'error'): ?>
                        <span class="badge badge-error">Erreur</span>
                    <?php else: ?>
                        <span class="badge badge-running">En cours</span>
                    <?php endif; ?>
                </td>
                <td class="truncate" style="max-width:250px"><?= htmlspecialchars($log['error_message'] ?? '') ?></td>
                <td style="white-space:nowrap"><?= $log['started_at'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr><td colspan="10" style="text-align:center;color:#999">Aucune synchronisation enregistree</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
