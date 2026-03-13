<?php
use Platform\Http\Csrf;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-database-gear me-2"></i>Migrations</h2>
</div>

<?php if ($resultats !== null): ?>
<div class="card mb-4">
    <div class="card-header">
        <strong>Résultat</strong> —
        <span class="text-success"><?= $resultats['ok'] ?> appliquée(s)</span>,
        <span class="text-muted"><?= $resultats['skip'] ?> ignorée(s)</span><?php if ($resultats['err'] > 0): ?>,
        <span class="text-danger"><?= $resultats['err'] ?> erreur(s)</span><?php endif; ?>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">Statut</th>
                    <th>Migration</th>
                    <th>Détails</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultats['details'] as $d): ?>
                <tr>
                    <td>
                        <?php if ($d['statut'] === 'ok'): ?>
                            <span class="badge bg-success">OK</span>
                        <?php elseif ($d['statut'] === 'skip'): ?>
                            <span class="badge bg-secondary">SKIP</span>
                        <?php else: ?>
                            <span class="badge bg-danger">FAIL</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 0.88rem;"><?= htmlspecialchars($d['nom']) ?></td>
                    <td style="font-size: 0.82rem; color: var(--text-muted);">
                        <?= isset($d['message']) ? htmlspecialchars($d['message']) : '' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <p class="mb-3">
            Ce script exécute toutes les migrations en attente. Les migrations déjà appliquées sont automatiquement ignorées.
        </p>
        <form method="POST" action="/admin/migrations" onsubmit="return confirm('Exécuter toutes les migrations en attente ?')">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-play-fill me-1"></i>Exécuter les migrations
            </button>
        </form>
    </div>
</div>
