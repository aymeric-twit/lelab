<h2 class="mb-1">Quotas mensuels</h2>

<?php
$creditsActifs = false;
try { new \Platform\Service\CreditService(); $creditsActifs = true; } catch (\PDOException) {}
?>
<?php if ($creditsActifs): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-4" style="font-size: 0.85rem;">
    <i class="bi bi-lightning-charge-fill"></i>
    <div>
        <strong>Syst&egrave;me de cr&eacute;dits universels actif.</strong>
        Les quotas par module ci-dessous ne sont plus utilis&eacute;s pour le contr&ocirc;le d'acc&egrave;s.
        G&eacute;rez les cr&eacute;dits dans <a href="/admin/configuration?onglet=plans">Configuration &rarr; Plans &amp; Cr&eacute;dits</a>.
    </div>
</div>
<?php endif; ?>

<p class="text-muted mb-4" style="font-size:0.9rem;">Définissez les limites d'utilisation par utilisateur et par module. Laissez vide pour utiliser la valeur par défaut du module. <code style="background:var(--brand-teal-light);color:var(--brand-dark);padding:0.15em 0.35em;border-radius:0.25rem;">0</code> = illimité.</p>

<form method="POST" action="/admin/quotas">
    <?= \Platform\Http\Csrf::field() ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <?php foreach ($modules as $mod): ?>
                                <th class="text-center" style="min-width: 120px;">
                                    <i class="bi <?= htmlspecialchars($mod['icon']) ?> d-block mb-1"></i>
                                    <?= htmlspecialchars($mod['name']) ?>
                                    <div class="small" style="color: var(--text-muted); font-weight:400;">
                                        <?= htmlspecialchars($mod['quota_mode']) ?>
                                        <?php if ((int) $mod['default_quota'] > 0): ?>
                                            <br>défaut: <?= (int) $mod['default_quota'] ?>
                                        <?php else: ?>
                                            <br>défaut: illimité
                                        <?php endif; ?>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($u['username']) ?></strong>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge badge-role-admin ms-1">admin</span>
                                    <div class="small" style="color:var(--text-muted);">Exempt des quotas</div>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($modules as $mod): ?>
                                <td class="text-center">
                                    <?php if ($mod['quota_mode'] === 'none'): ?>
                                        <span style="color:var(--text-muted);" class="small">&mdash;</span>
                                    <?php else: ?>
                                        <input type="number"
                                               class="form-control form-control-sm text-center"
                                               name="quotas[<?= $u['id'] ?>][<?= $mod['id'] ?>]"
                                               value="<?= isset($matrix[$u['id']][$mod['id']]) ? (int) $matrix[$u['id']][$mod['id']] : '' ?>"
                                               min="0"
                                               placeholder="<?= (int) $mod['default_quota'] ?>"
                                               style="width: 90px; margin: 0 auto;">
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary mt-3">
        <i class="bi bi-check-lg me-1"></i> Enregistrer les quotas
    </button>
</form>
