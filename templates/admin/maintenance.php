<h2 class="mb-4">Maintenance</h2>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i> D&eacute;pendances des plugins</h5>
        <?php if (!empty($etats)): ?>
            <form method="POST" action="/admin/maintenance/dependances" class="d-inline">
                <?= \Platform\Http\Csrf::field() ?>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-arrow-repeat me-1"></i> Installer toutes les d&eacute;pendances
                </button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($etats)): ?>
            <p class="text-muted mb-0">Aucun plugin actif avec des d&eacute;pendances Composer ou npm.</p>
        <?php else: ?>
            <p class="text-muted mb-3" style="font-size:0.9rem;">
                &Eacute;tat des d&eacute;pendances pour chaque plugin actif. Utilisez le bouton ci-dessus pour r&eacute;installer toutes les d&eacute;pendances en une fois (utile apr&egrave;s une migration de serveur).
            </p>
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Plugin</th>
                        <th class="text-center" style="width:140px;">Composer</th>
                        <th class="text-center" style="width:140px;">npm</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($etats as $etat): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($etat['name']) ?></strong>
                                <span class="text-muted ms-1" style="font-size:0.85rem;"><?= htmlspecialchars($etat['slug']) ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($etat['composer'] === null): ?>
                                    <span class="badge bg-secondary">n/a</span>
                                <?php elseif ($etat['composer'] === 'ok'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>OK</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-lg me-1"></i>Manquant</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($etat['npm'] === null): ?>
                                    <span class="badge bg-secondary">n/a</span>
                                <?php elseif ($etat['npm'] === 'ok'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>OK</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-lg me-1"></i>Manquant</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-trash3 me-2"></i> Purge des donn&eacute;es d'usage</h5>
        <form method="POST" action="/admin/maintenance/purge-usage" class="d-inline">
            <?= \Platform\Http\Csrf::field() ?>
            <button type="submit" class="btn btn-outline-danger btn-sm"
                    onclick="return confirm('Supprimer les données d\'usage de plus de 12 mois ?')">
                <i class="bi bi-trash me-1"></i> Purger (&gt; 12 mois)
            </button>
        </form>
    </div>
    <div class="card-body">
        <p class="text-muted mb-0" style="font-size:0.9rem;">
            Supprime les lignes de <code>module_usage</code> ant&eacute;rieures &agrave; 12 mois.
            Les donn&eacute;es du mois en cours et des 11 mois pr&eacute;c&eacute;dents sont conserv&eacute;es.
        </p>
    </div>
</div>
