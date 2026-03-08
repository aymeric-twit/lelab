<?php
use Platform\Enum\TypeNotification;

$onglet = $onglet ?? 'smtp';
$smtpDb = $smtpDb ?? [];
$smtpEnv = $smtpEnv ?? [];
$smtpEffectif = $smtpEffectif ?? [];
$notifDb = $notifDb ?? [];
$sujetsDb = $sujetsDb ?? [];
$types = $types ?? TypeNotification::cases();
$historique = $historique ?? [];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'parPage' => 30, 'totalPages' => 1];
$filtres = $filtres ?? ['date_debut' => '', 'date_fin' => '', 'type' => '', 'statut' => '', 'destinataire' => ''];
$stats = $stats ?? ['envoye' => 0, 'echec' => 0];
$csrfToken = $csrfToken ?? '';

$filtreParams = array_filter($filtres, fn($v) => $v !== '');
$filtreQueryString = $filtreParams ? '&' . http_build_query($filtreParams) : '';
?>

<h2 class="mb-4"><i class="bi bi-envelope me-2"></i>Gestion des emails</h2>

<!-- Onglets -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'smtp' ? 'active' : '' ?>" href="/admin/emails?onglet=smtp">
            <i class="bi bi-gear me-1"></i> Configuration SMTP
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'notifications' ? 'active' : '' ?>" href="/admin/emails?onglet=notifications">
            <i class="bi bi-bell me-1"></i> Notifications
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'templates' ? 'active' : '' ?>" href="/admin/emails?onglet=templates">
            <i class="bi bi-file-earmark-code me-1"></i> Aperçu templates
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'historique' ? 'active' : '' ?>" href="/admin/emails?onglet=historique">
            <i class="bi bi-clock-history me-1"></i> Historique
        </a>
    </li>
</ul>

<?php if ($onglet === 'smtp'): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ONGLET SMTP                                -->
<!-- ═══════════════════════════════════════════ -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Paramètres SMTP</h5>
                <form method="POST" action="/admin/emails/smtp">
                    <?= \Platform\Http\Csrf::field() ?>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="smtp-host" class="form-label">Hôte SMTP</label>
                            <input type="text" class="form-control" id="smtp-host" name="host"
                                   value="<?= htmlspecialchars($smtpEffectif['host'] ?? '') ?>">
                            <small class="text-muted">Valeur .env : <?= htmlspecialchars($smtpEnv['host'] ?? 'non définie') ?></small>
                        </div>
                        <div class="col-md-4">
                            <label for="smtp-port" class="form-label">Port</label>
                            <input type="number" class="form-control" id="smtp-port" name="port"
                                   value="<?= (int) ($smtpEffectif['port'] ?? 587) ?>">
                            <small class="text-muted">Valeur .env : <?= (int) ($smtpEnv['port'] ?? 587) ?></small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="smtp-username" class="form-label">Nom d'utilisateur</label>
                            <input type="text" class="form-control" id="smtp-username" name="username"
                                   value="<?= htmlspecialchars($smtpEffectif['username'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="smtp-password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="smtp-password" name="password"
                                   placeholder="<?= !empty($smtpDb['password']) ? '••••••• (défini)' : 'Non configuré' ?>">
                            <small class="text-muted">Laisser vide pour conserver l'actuel</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="smtp-encryption" class="form-label">Chiffrement</label>
                        <select class="form-select" id="smtp-encryption" name="encryption">
                            <option value="tls" <?= ($smtpEffectif['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($smtpEffectif['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= ($smtpEffectif['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Aucun</option>
                        </select>
                    </div>

                    <hr>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="smtp-from" class="form-label">Adresse d'expédition</label>
                            <input type="email" class="form-control" id="smtp-from" name="from"
                                   value="<?= htmlspecialchars($smtpEffectif['from'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="smtp-from-name" class="form-label">Nom d'expéditeur</label>
                            <input type="text" class="form-control" id="smtp-from-name" name="from_name"
                                   value="<?= htmlspecialchars($smtpEffectif['from_name'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Enregistrer
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btn-test-email">
                            <i class="bi bi-send me-1"></i>Envoyer un email de test
                        </button>
                    </div>
                </form>

                <div id="test-email-result" class="mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-info-circle me-1"></i> Priorité de configuration</h6>
                <p class="small text-muted mb-2">Les valeurs sont résolues dans cet ordre :</p>
                <ol class="small text-muted mb-0">
                    <li><strong>Base de données</strong> — valeurs saisies ici</li>
                    <li><strong>Fichier .env</strong> — variables d'environnement</li>
                    <li><strong>Défauts</strong> — valeurs par défaut du code</li>
                </ol>
            </div>
        </div>

        <!-- Card Gmail -->
        <div class="card mt-3">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-google me-1"></i> Configuration Gmail</h6>
                <p class="small text-muted mb-2">Pour utiliser Gmail avec un mot de passe d'application :</p>
                <ol class="small text-muted mb-2">
                    <li>Activez la <strong>validation en 2 étapes</strong> sur votre compte Google</li>
                    <li>Créez un <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">mot de passe d'application</a></li>
                    <li>Cliquez sur le bouton ci-dessous pour pré-remplir les champs</li>
                </ol>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-prefill-gmail">
                    <i class="bi bi-lightning me-1"></i>Pré-remplir Gmail
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-prefill-gmail')?.addEventListener('click', function() {
    document.getElementById('smtp-host').value = 'smtp.gmail.com';
    document.getElementById('smtp-port').value = '587';
    document.getElementById('smtp-encryption').value = 'tls';
    // Username et mot de passe doivent être remplis par l'utilisateur
});

document.getElementById('btn-test-email')?.addEventListener('click', function() {
    const btn = this;
    const resultDiv = document.getElementById('test-email-result');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Envoi...';
    resultDiv.style.display = 'none';

    fetch('/admin/emails/test', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '<?= htmlspecialchars($csrfToken) ?>',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        resultDiv.style.display = 'block';
        resultDiv.className = 'mt-3 alert alert-' + (data.ok ? 'success' : 'danger');
        resultDiv.textContent = data.message;
    })
    .catch(() => {
        resultDiv.style.display = 'block';
        resultDiv.className = 'mt-3 alert alert-danger';
        resultDiv.textContent = 'Erreur de connexion.';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i>Envoyer un email de test';
    });
});
</script>

<?php elseif ($onglet === 'notifications'): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ONGLET NOTIFICATIONS                       -->
<!-- ═══════════════════════════════════════════ -->
<form method="POST" action="/admin/emails/notifications">
    <?= \Platform\Http\Csrf::field() ?>

    <!-- Paramètres généraux -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Paramètres généraux</h5>
            <div class="row">
                <div class="col-md-8">
                    <label for="admin-email" class="form-label">Email(s) admin pour notifications</label>
                    <input type="text" class="form-control" id="admin-email" name="admin_email"
                           value="<?= htmlspecialchars($notifDb['admin_email'] ?? '') ?>"
                           placeholder="admin@example.com, admin2@example.com">
                    <small class="text-muted">Séparer par des virgules. Si vide, les admins actifs de la plateforme seront notifiés.</small>
                </div>
                <div class="col-md-4">
                    <label for="quota-seuil" class="form-label">Seuil alerte quota</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="quota-seuil" name="quota_seuil_alerte"
                               value="<?= (int) ($notifDb['quota_seuil_alerte'] ?? 80) ?>" min="50" max="99">
                        <span class="input-group-text">%</span>
                    </div>
                    <small class="text-muted">Envoie une alerte à ce seuil d'utilisation.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Types de notifications -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3">Types de notifications</h5>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Actif</th>
                            <th>Notification</th>
                            <th>Sujet personnalisé</th>
                            <th style="width: 80px;">Aperçu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($types as $type): ?>
                        <?php $actif = ($notifDb[$type->value . '_active'] ?? '1') !== '0'; ?>
                        <tr>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center mb-0">
                                    <input class="form-check-input" type="checkbox"
                                           name="<?= $type->value ?>_active" value="1"
                                           <?= $actif ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <td>
                                <i class="bi <?= $type->icone() ?> me-1"></i>
                                <strong><?= htmlspecialchars($type->label()) ?></strong>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm" name="sujet_<?= $type->value ?>"
                                       value="<?= htmlspecialchars($sujetsDb[$type->value] ?? '') ?>"
                                       placeholder="<?= htmlspecialchars($type->sujetParDefaut()) ?>">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-secondary btn-sm btn-apercu"
                                        data-type="<?= $type->value ?>" title="Aperçu">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Enregistrer
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Modal aperçu -->
<div class="modal fade" id="modalApercu" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalApercuTitle">Aperçu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="iframeApercu" style="width:100%; height:500px; border:none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-apercu').forEach(btn => {
    btn.addEventListener('click', function() {
        const type = this.dataset.type;
        const modal = new bootstrap.Modal(document.getElementById('modalApercu'));
        const iframe = document.getElementById('iframeApercu');
        const title = document.getElementById('modalApercuTitle');

        fetch('/admin/emails/apercu/' + type, {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                title.textContent = 'Aperçu — ' + data.label;
                iframe.srcdoc = data.html;
                modal.show();
            }
        });
    });
});
</script>

<?php elseif ($onglet === 'templates'): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ONGLET TEMPLATES                           -->
<!-- ═══════════════════════════════════════════ -->
<div class="row g-3">
    <?php foreach ($types as $type): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <h6 class="card-title">
                    <i class="bi <?= $type->icone() ?> me-1"></i>
                    <?= htmlspecialchars($type->label()) ?>
                </h6>
                <p class="small text-muted mb-2">Template : <code><?= $type->template() ?>.php</code></p>
                <p class="small text-muted flex-grow-1">Sujet : <?= htmlspecialchars($type->sujetParDefaut()) ?></p>
                <button type="button" class="btn btn-outline-primary btn-sm btn-apercu" data-type="<?= $type->value ?>">
                    <i class="bi bi-eye me-1"></i>Voir l'aperçu
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal aperçu (même que notifications) -->
<div class="modal fade" id="modalApercu" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalApercuTitle">Aperçu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="iframeApercu" style="width:100%; height:500px; border:none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-apercu').forEach(btn => {
    btn.addEventListener('click', function() {
        const type = this.dataset.type;
        const modal = new bootstrap.Modal(document.getElementById('modalApercu'));
        const iframe = document.getElementById('iframeApercu');
        const title = document.getElementById('modalApercuTitle');

        fetch('/admin/emails/apercu/' + type, {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                title.textContent = 'Aperçu — ' + data.label;
                iframe.srcdoc = data.html;
                modal.show();
            }
        });
    });
});
</script>

<?php elseif ($onglet === 'historique'): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ONGLET HISTORIQUE                           -->
<!-- ═══════════════════════════════════════════ -->

<!-- Stats rapides -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="h4 mb-0 text-success"><?= $stats['envoye'] ?></div>
                <small class="text-muted">Envoyés (30j)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="h4 mb-0 text-danger"><?= $stats['echec'] ?></div>
                <small class="text-muted">Échecs (30j)</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="/admin/emails" class="mb-3">
    <input type="hidden" name="onglet" value="historique">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label for="filtre-date-debut" class="form-label small mb-1">Du</label>
            <input type="date" class="form-control form-control-sm" id="filtre-date-debut" name="date_debut"
                   value="<?= htmlspecialchars($filtres['date_debut']) ?>">
        </div>
        <div class="col-md-2">
            <label for="filtre-date-fin" class="form-label small mb-1">Au</label>
            <input type="date" class="form-control form-control-sm" id="filtre-date-fin" name="date_fin"
                   value="<?= htmlspecialchars($filtres['date_fin']) ?>">
        </div>
        <div class="col-md-2">
            <label for="filtre-type" class="form-label small mb-1">Type</label>
            <select class="form-select form-select-sm" id="filtre-type" name="type">
                <option value="">Tous</option>
                <?php foreach ($types as $t): ?>
                <option value="<?= $t->value ?>" <?= ($filtres['type'] ?? '') === $t->value ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t->label()) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="filtre-statut" class="form-label small mb-1">Statut</label>
            <select class="form-select form-select-sm" id="filtre-statut" name="statut">
                <option value="">Tous</option>
                <option value="envoye" <?= ($filtres['statut'] ?? '') === 'envoye' ? 'selected' : '' ?>>Envoyé</option>
                <option value="echec" <?= ($filtres['statut'] ?? '') === 'echec' ? 'selected' : '' ?>>Échec</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="filtre-dest" class="form-label small mb-1">Destinataire</label>
            <input type="text" class="form-control form-control-sm" id="filtre-dest" name="destinataire"
                   value="<?= htmlspecialchars($filtres['destinataire'] ?? '') ?>" placeholder="Email...">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-funnel me-1"></i>Filtrer
            </button>
            <?php if ($filtreParams): ?>
            <a href="/admin/emails?onglet=historique" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-lg"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-2">
    <p class="text-muted small mb-0">
        <strong><?= $pagination['total'] ?></strong> email(s)
    </p>
    <?php if (!empty($historique)): ?>
    <a href="/admin/emails/historique/export?<?= http_build_query($filtreParams) ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
    </a>
    <?php endif; ?>
</div>

<!-- Tableau -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th style="width: 150px;">Date</th>
                        <th>Destinataire</th>
                        <th>Type</th>
                        <th>Sujet</th>
                        <th style="width: 80px;">Statut</th>
                        <th>Erreur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historique)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox d-block mb-2" style="font-size: 2rem;"></i>
                            Aucun email envoyé.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($historique as $log):
                        $typeNotif = TypeNotification::tryFrom($log['type_email'] ?? '');
                    ?>
                    <tr>
                        <td style="font-size: 0.82rem; color: var(--text-muted);">
                            <?= htmlspecialchars($log['created_at']) ?>
                        </td>
                        <td style="font-size: 0.88rem;">
                            <?= htmlspecialchars($log['destinataire']) ?>
                        </td>
                        <td style="font-size: 0.88rem;">
                            <?php if ($typeNotif): ?>
                            <i class="bi <?= $typeNotif->icone() ?> me-1"></i>
                            <?= htmlspecialchars($typeNotif->label()) ?>
                            <?php else: ?>
                            <span class="text-muted"><?= htmlspecialchars($log['type_email'] ?? '-') ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 0.82rem; max-width: 200px;" class="text-truncate">
                            <?= htmlspecialchars($log['sujet']) ?>
                        </td>
                        <td>
                            <?php if ($log['statut'] === 'envoye'): ?>
                            <span class="badge bg-success">Envoyé</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Échec</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 0.82rem; max-width: 200px; color: var(--bs-danger);">
                            <?= htmlspecialchars($log['erreur'] ?? '') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($pagination['totalPages'] > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php if ($pagination['page'] > 1): ?>
        <li class="page-item">
            <a class="page-link" href="/admin/emails?onglet=historique&page=<?= $pagination['page'] - 1 ?><?= $filtreQueryString ?>">Précédent</a>
        </li>
        <?php endif; ?>

        <?php
        $debut = max(1, $pagination['page'] - 3);
        $fin = min($pagination['totalPages'], $pagination['page'] + 3);
        ?>
        <?php if ($debut > 1): ?>
        <li class="page-item"><a class="page-link" href="/admin/emails?onglet=historique&page=1<?= $filtreQueryString ?>">1</a></li>
        <?php if ($debut > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $debut; $p <= $fin; $p++): ?>
        <li class="page-item <?= $p === $pagination['page'] ? 'active' : '' ?>">
            <a class="page-link" href="/admin/emails?onglet=historique&page=<?= $p ?><?= $filtreQueryString ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>

        <?php if ($fin < $pagination['totalPages']): ?>
        <?php if ($fin < $pagination['totalPages'] - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <li class="page-item"><a class="page-link" href="/admin/emails?onglet=historique&page=<?= $pagination['totalPages'] ?><?= $filtreQueryString ?>"><?= $pagination['totalPages'] ?></a></li>
        <?php endif; ?>

        <?php if ($pagination['page'] < $pagination['totalPages']): ?>
        <li class="page-item">
            <a class="page-link" href="/admin/emails?onglet=historique&page=<?= $pagination['page'] + 1 ?><?= $filtreQueryString ?>">Suivant</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>
