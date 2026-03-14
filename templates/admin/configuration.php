<?php
use Platform\Enum\TypeNotification;
use Platform\Service\WebhookDispatcher;

$onglet = $onglet ?? 'cles-api';
$csrfToken = $csrfToken ?? '';

// Donnees par onglet (avec valeurs par defaut)
$clesApiGroupees = $clesApiGroupees ?? [];
$google = $google ?? [];
$smtpDb = $smtpDb ?? [];
$smtpEnv = $smtpEnv ?? [];
$smtpEffectif = $smtpEffectif ?? [];
$notifDb = $notifDb ?? [];
$sujetsDb = $sujetsDb ?? [];
$types = $types ?? [];
$securite = $securite ?? [];
$general = $general ?? [];
$webhooks = $webhooks ?? [];
$evenementsDisponibles = $evenementsDisponibles ?? WebhookDispatcher::EVENEMENTS;
$apiKeys = $apiKeys ?? [];
$utilisateurs = $utilisateurs ?? [];
$plans = $plans ?? [];
$modulesCredits = $modulesCredits ?? [];
$utilisateursPlans = $utilisateursPlans ?? [];
?>

<h2 class="mb-4"><i class="bi bi-gear me-2"></i>Configuration</h2>

<!-- Onglets -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'cles-api' ? 'active' : '' ?>" href="/admin/configuration?onglet=cles-api">
            <i class="bi bi-key me-1"></i> Cl&eacute;s d'API
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'google' ? 'active' : '' ?>" href="/admin/configuration?onglet=google">
            <i class="bi bi-google me-1"></i> Google
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'smtp' ? 'active' : '' ?>" href="/admin/configuration?onglet=smtp">
            <i class="bi bi-envelope me-1"></i> SMTP
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'notifications' ? 'active' : '' ?>" href="/admin/configuration?onglet=notifications">
            <i class="bi bi-bell me-1"></i> Notifications
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'securite' ? 'active' : '' ?>" href="/admin/configuration?onglet=securite">
            <i class="bi bi-shield-lock me-1"></i> S&eacute;curit&eacute;
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'general' ? 'active' : '' ?>" href="/admin/configuration?onglet=general">
            <i class="bi bi-sliders me-1"></i> G&eacute;n&eacute;ral
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'webhooks' ? 'active' : '' ?>" href="/admin/configuration?onglet=webhooks">
            <i class="bi bi-broadcast me-1"></i> Webhooks
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'api' ? 'active' : '' ?>" href="/admin/configuration?onglet=api">
            <i class="bi bi-braces me-1"></i> API
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $onglet === 'plans' ? 'active' : '' ?>" href="/admin/configuration?onglet=plans">
            <i class="bi bi-lightning-charge me-1"></i> Plans &amp; Cr&eacute;dits
        </a>
    </li>
</ul>

<?php if ($onglet === 'cles-api'): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ONGLET CLES D'API                          -->
<!-- ═══════════════════════════════════════════ -->
<p class="text-muted mb-3" style="font-size:0.9rem;">
    G&eacute;rez les cl&eacute;s d'API utilis&eacute;es par les plugins. Les valeurs sont stock&eacute;es dans le fichier <code>.env</code> de la plateforme.
</p>

<?php if (empty($clesApiGroupees)): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-key d-block mb-2" style="font-size: 2rem;"></i>
            Aucun plugin ne d&eacute;clare de cl&eacute; d'environnement.
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Cl&eacute;</th>
                            <th>Modules</th>
                            <th>Valeur</th>
                            <th>Statut</th>
                            <th>Requ&ecirc;tes</th>
                            <th>Cr&eacute;dits</th>
                            <th style="width: 220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clesApiGroupees as $cle => $infoCle): ?>
                            <?php
                                $estPresente = $infoCle['presente'];
                                $valeur = $infoCle['valeur'];
                                $valeurMasquee = $estPresente && strlen($valeur) > 4
                                    ? str_repeat('&bull;', 4) . htmlspecialchars(substr($valeur, -4))
                                    : ($estPresente ? '****' : '');
                                $supportsLive = in_array($cle, ['SEMRUSH_API_KEY'], true);
                                $creditsMensuels = $infoCle['credits_mensuels'];
                                $usageMois = $infoCle['usage_mois'];
                                $commentaire = $infoCle['commentaire'] ?? '';
                                $dateDebut = $infoCle['date_debut'] ?? '';
                                $prochainReset = $infoCle['prochain_reset'] ?? '';
                                $periode = $infoCle['periode'] ?? 'mensuel';
                                $labelCredits = $periode === 'hebdomadaire' ? 'sem.' : 'mois';
                            ?>
                            <tr>
                                <td>
                                    <code><?= htmlspecialchars($cle) ?></code>
                                    <?php if ($commentaire !== ''): ?>
                                        <div class="text-muted mt-1" style="font-size: 0.75rem; font-style: italic;">
                                            <?= htmlspecialchars($commentaire) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php foreach ($infoCle['modules'] as $modInfo): ?>
                                        <span class="badge bg-light text-dark border me-1 mb-1" style="font-size: 0.78rem;">
                                            <i class="bi <?= htmlspecialchars($modInfo['icon']) ?> me-1" style="color: var(--brand-teal);"></i><?= htmlspecialchars($modInfo['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </td>
                                <td class="cle-valeur-masquee"><?= $valeurMasquee ?: '<span class="text-muted">&mdash;</span>' ?></td>
                                <td>
                                    <?php if ($estPresente): ?>
                                        <span class="badge badge-active"><i class="bi bi-check-circle me-1"></i>Configur&eacute;e</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive"><i class="bi bi-exclamation-triangle me-1"></i>Manquante</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= number_format($usageMois, 0, ',', ' ') ?></strong>
                                </td>
                                <td>
                                    <?php if ($supportsLive && $estPresente): ?>
                                        <span class="credits-api-cell" data-cle="<?= htmlspecialchars($cle) ?>">
                                            <span class="spinner-border spinner-border-sm text-muted" role="status"></span>
                                        </span>
                                    <?php elseif ($creditsMensuels !== null): ?>
                                        <?php
                                            $restants = max(0, (int) $creditsMensuels - $usageMois);
                                            $pctUtilise = $creditsMensuels > 0 ? min(100, round(($usageMois / $creditsMensuels) * 100)) : 0;
                                            $barClass = $pctUtilise >= 100 ? 'bg-danger' : ($pctUtilise >= 80 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <div style="font-size: 0.85rem;">
                                            <strong><?= number_format($restants, 0, ',', ' ') ?></strong>
                                            <span class="text-muted">/ <?= number_format((int) $creditsMensuels, 0, ',', ' ') ?>/<?= $labelCredits ?></span>
                                        </div>
                                        <div class="progress mt-1" style="height: 4px; width: 80px;">
                                            <div class="progress-bar <?= $barClass ?>" style="width: <?= $pctUtilise ?>%"></div>
                                        </div>
                                        <?php if ($prochainReset !== ''): ?>
                                            <div class="text-muted mt-1" style="font-size: 0.7rem;">
                                                Reset <?= $periode === 'hebdomadaire' ? 'lun.' : 'le' ?> <?= date('j', strtotime($prochainReset)) ?>&nbsp;<?= ['', 'jan.', 'f&eacute;v.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'ao&ucirc;t', 'sept.', 'oct.', 'nov.', 'd&eacute;c.'][(int) date('n', strtotime($prochainReset))] ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <div class="cle-api-action" data-cle="<?= htmlspecialchars($cle) ?>">
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-modifier-cle" title="<?= $estPresente ? 'Modifier la cl&eacute;' : 'D&eacute;finir la cl&eacute;' ?>">
                                                <i class="bi bi-key"></i>
                                            </button>
                                            <div class="cle-api-edit d-none">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control cle-api-input"
                                                           placeholder="Nouvelle valeur"
                                                           value="">
                                                    <button type="button" class="btn btn-primary btn-sauver-cle">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary btn-annuler-cle">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="credits-config-action" data-cle="<?= htmlspecialchars($cle) ?>" data-credits="<?= (int) ($creditsMensuels ?? 0) ?>" data-commentaire="<?= htmlspecialchars($commentaire) ?>" data-date-debut="<?= htmlspecialchars($dateDebut) ?>" data-periode="<?= htmlspecialchars($periode) ?>">
                                            <button type="button" class="btn btn-sm btn-outline-secondary btn-config-credits" title="Configurer cr&eacute;dits &amp; commentaire">
                                                <i class="bi bi-gear"></i>
                                            </button>
                                            <div class="credits-config-edit d-none">
                                                <div class="d-flex flex-column gap-1" style="min-width: 240px;">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text" style="font-size: 0.75rem;">Cr&eacute;dits</span>
                                                        <input type="number" class="form-control credits-input" min="0"
                                                               placeholder="Ex: 10000"
                                                               value="<?= (int) ($creditsMensuels ?? 0) ?>">
                                                        <select class="form-select periode-input" style="max-width: 90px; font-size: 0.75rem;">
                                                            <option value="mensuel"<?= $periode === 'mensuel' ? ' selected' : '' ?>>/mois</option>
                                                            <option value="hebdomadaire"<?= $periode === 'hebdomadaire' ? ' selected' : '' ?>>/sem.</option>
                                                        </select>
                                                    </div>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text" style="font-size: 0.75rem;">D&eacute;but</span>
                                                        <input type="date" class="form-control date-debut-input"
                                                               value="<?= htmlspecialchars($dateDebut) ?>">
                                                    </div>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text" style="font-size: 0.75rem;">Note</span>
                                                        <input type="text" class="form-control commentaire-input"
                                                               placeholder="Commentaire..."
                                                               value="<?= htmlspecialchars($commentaire) ?>">
                                                    </div>
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-sm btn-primary btn-sauver-credits flex-grow-1">
                                                            <i class="bi bi-check-lg me-1"></i>OK
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-annuler-credits">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// --- Gestion des cles d'environnement ---
document.querySelectorAll('.cle-api-action').forEach(container => {
    const cle = container.dataset.cle;
    const btnModifier = container.querySelector('.btn-modifier-cle');
    const editZone = container.querySelector('.cle-api-edit');
    const input = container.querySelector('.cle-api-input');
    const btnSauver = container.querySelector('.btn-sauver-cle');
    const btnAnnuler = container.querySelector('.btn-annuler-cle');

    if (!btnModifier) return;

    btnModifier.addEventListener('click', () => {
        btnModifier.classList.add('d-none');
        editZone.classList.remove('d-none');
        input.focus();
    });

    btnAnnuler.addEventListener('click', () => {
        editZone.classList.add('d-none');
        btnModifier.classList.remove('d-none');
        input.value = '';
    });

    btnSauver.addEventListener('click', () => {
        const valeur = input.value.trim();
        if (valeur === '') return;

        const formData = new FormData();
        formData.append('cle', cle);
        formData.append('valeur', valeur);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="_csrf_token"]')?.value || '';
        formData.append('_csrf_token', csrfToken);

        btnSauver.disabled = true;

        fetch('/admin/plugins/cles-env', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                window.location.href = '/admin/configuration?onglet=cles-api';
            } else {
                alert(data.erreur || 'Erreur lors de la sauvegarde.');
                btnSauver.disabled = false;
            }
        })
        .catch(() => {
            alert('Erreur r\u00e9seau.');
            btnSauver.disabled = false;
        });
    });

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); btnSauver.click(); }
        if (e.key === 'Escape') { btnAnnuler.click(); }
    });
});

// --- Chargement AJAX des credits API ---
document.querySelectorAll('.credits-api-cell').forEach(cell => {
    const cle = cell.dataset.cle;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_csrf_token"]')?.value || '';

    const formData = new FormData();
    formData.append('cle', cle);
    formData.append('_csrf_token', csrfToken);

    fetch('/admin/plugins/api-credits', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok && data.credits !== null) {
            cell.innerHTML = '<strong style="color: var(--brand-dark);">' +
                new Intl.NumberFormat('fr-FR').format(data.credits) +
                '</strong>' +
                (data.fournisseur ? ' <small class="text-muted">(' + data.fournisseur + ')</small>' : '');
        } else {
            cell.innerHTML = '<span class="text-muted">' + (data.raison || '&mdash;') + '</span>';
        }
    })
    .catch(() => {
        cell.innerHTML = '<span class="text-muted">Erreur</span>';
    });
});

// --- Gestion config credits API ---
document.querySelectorAll('.credits-config-action').forEach(container => {
    const cle = container.dataset.cle;
    const btnConfig = container.querySelector('.btn-config-credits');
    const editZone = container.querySelector('.credits-config-edit');
    const creditsInput = container.querySelector('.credits-input');
    const periodeInput = container.querySelector('.periode-input');
    const dateDebutInput = container.querySelector('.date-debut-input');
    const commentaireInput = container.querySelector('.commentaire-input');
    const btnSauver = container.querySelector('.btn-sauver-credits');
    const btnAnnuler = container.querySelector('.btn-annuler-credits');

    if (!btnConfig) return;

    btnConfig.addEventListener('click', () => {
        btnConfig.classList.add('d-none');
        editZone.classList.remove('d-none');
        creditsInput.focus();
    });

    btnAnnuler.addEventListener('click', () => {
        editZone.classList.add('d-none');
        btnConfig.classList.remove('d-none');
    });

    btnSauver.addEventListener('click', () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="_csrf_token"]')?.value || '';

        const formData = new FormData();
        formData.append('cle', cle);
        formData.append('credits_mensuels', creditsInput.value);
        formData.append('periode', periodeInput.value);
        formData.append('date_debut', dateDebutInput.value);
        formData.append('commentaire', commentaireInput.value);
        formData.append('_csrf_token', csrfToken);

        btnSauver.disabled = true;

        fetch('/admin/plugins/api-credits-config', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                window.location.href = '/admin/configuration?onglet=cles-api';
            } else {
                alert(data.erreur || 'Erreur.');
                btnSauver.disabled = false;
            }
        })
        .catch(() => {
            alert('Erreur r\u00e9seau.');
            btnSauver.disabled = false;
        });
    });

    creditsInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); btnSauver.click(); }
        if (e.key === 'Escape') { btnAnnuler.click(); }
    });
    dateDebutInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); btnSauver.click(); }
        if (e.key === 'Escape') { btnAnnuler.click(); }
    });
    commentaireInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); btnSauver.click(); }
        if (e.key === 'Escape') { btnAnnuler.click(); }
    });
});
</script>

<?php elseif ($onglet === 'google'): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ONGLET GOOGLE                              -->
<!-- ═══════════════════════════════════════════ -->
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3"><i class="bi bi-google me-2"></i>Identifiants Google OAuth</h5>
                <form method="POST" action="/admin/configuration/google">
                    <?= \Platform\Http\Csrf::field() ?>

                    <div class="mb-3">
                        <label for="google-client-id" class="form-label">GOOGLE_CLIENT_ID</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="google-client-id" name="GOOGLE_CLIENT_ID"
                                   value="<?= htmlspecialchars($google['GOOGLE_CLIENT_ID'] ?? '') ?>"
                                   placeholder="123456789.apps.googleusercontent.com">
                            <span class="input-group-text">
                                <?php if (!empty($google['GOOGLE_CLIENT_ID'])): ?>
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-danger"></i>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="google-client-secret" class="form-label">GOOGLE_CLIENT_SECRET</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="google-client-secret" name="GOOGLE_CLIENT_SECRET"
                                   placeholder="<?= !empty($google['GOOGLE_CLIENT_SECRET']) ? '••••••• (d&eacute;fini)' : 'Non configur&eacute;' ?>">
                            <span class="input-group-text">
                                <?php if (!empty($google['GOOGLE_CLIENT_SECRET'])): ?>
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-danger"></i>
                                <?php endif; ?>
                            </span>
                        </div>
                        <small class="text-muted">Laisser vide pour conserver la valeur actuelle</small>
                    </div>

                    <div class="mb-3">
                        <label for="google-redirect-uri" class="form-label">GOOGLE_REDIRECT_URI</label>
                        <div class="input-group">
                            <input type="url" class="form-control" id="google-redirect-uri" name="GOOGLE_REDIRECT_URI"
                                   value="<?= htmlspecialchars($google['GOOGLE_REDIRECT_URI'] ?? '') ?>"
                                   placeholder="https://votre-domaine.com/auth/google/callback">
                            <span class="input-group-text">
                                <?php if (!empty($google['GOOGLE_REDIRECT_URI'])): ?>
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-danger"></i>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3"><i class="bi bi-database me-1"></i> Base de donn&eacute;es GSC (optionnel)</h6>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="gsc-db-host" class="form-label">GSC_DB_HOST</label>
                            <input type="text" class="form-control" id="gsc-db-host" name="GSC_DB_HOST"
                                   value="<?= htmlspecialchars($google['GSC_DB_HOST'] ?? '') ?>"
                                   placeholder="localhost">
                        </div>
                        <div class="col-md-4">
                            <label for="gsc-db-name" class="form-label">GSC_DB_NAME</label>
                            <input type="text" class="form-control" id="gsc-db-name" name="GSC_DB_NAME"
                                   value="<?= htmlspecialchars($google['GSC_DB_NAME'] ?? '') ?>"
                                   placeholder="gsc_data">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="gsc-db-user" class="form-label">GSC_DB_USER</label>
                            <input type="text" class="form-control" id="gsc-db-user" name="GSC_DB_USER"
                                   value="<?= htmlspecialchars($google['GSC_DB_USER'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="gsc-db-password" class="form-label">GSC_DB_PASSWORD</label>
                            <input type="password" class="form-control" id="gsc-db-password" name="GSC_DB_PASSWORD"
                                   placeholder="<?= !empty($google['GSC_DB_PASSWORD']) ? '••••••• (d&eacute;fini)' : '' ?>">
                            <small class="text-muted">Laisser vide pour conserver la valeur actuelle</small>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-info-circle me-1"></i> Configuration Google Cloud</h6>
                <p class="small text-muted mb-2">Pour obtenir les identifiants OAuth :</p>
                <ol class="small text-muted mb-2">
                    <li>Allez dans la <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Console Google Cloud</a></li>
                    <li>Cr&eacute;ez un projet ou s&eacute;lectionnez un projet existant</li>
                    <li>Allez dans <strong>Identifiants</strong> &rarr; <strong>Cr&eacute;er des identifiants</strong> &rarr; <strong>ID client OAuth</strong></li>
                    <li>Type d'application : <strong>Application Web</strong></li>
                    <li>Ajoutez l'URI de redirection autoris&eacute;e</li>
                </ol>
                <p class="small text-muted mb-0">
                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                    Activez les API n&eacute;cessaires : Search Console API, Google Analytics API, etc.
                </p>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-check2-all me-1"></i> Statut de la configuration</h6>
                <?php
                    $googleComplet = !empty($google['GOOGLE_CLIENT_ID']) && !empty($google['GOOGLE_CLIENT_SECRET']) && !empty($google['GOOGLE_REDIRECT_URI']);
                    $gscComplet = !empty($google['GSC_DB_HOST']) && !empty($google['GSC_DB_NAME']) && !empty($google['GSC_DB_USER']);
                ?>
                <div class="d-flex align-items-center mb-2">
                    <i class="bi <?= $googleComplet ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?> me-2"></i>
                    <span class="small">OAuth Google : <?= $googleComplet ? 'Configur&eacute;' : 'Incomplet' ?></span>
                </div>
                <div class="d-flex align-items-center">
                    <i class="bi <?= $gscComplet ? 'bi-check-circle-fill text-success' : 'bi-dash-circle text-muted' ?> me-2"></i>
                    <span class="small">Base GSC : <?= $gscComplet ? 'Configur&eacute;e' : 'Non configur&eacute;e' ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($onglet === 'smtp'): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ONGLET SMTP                                -->
<!-- ═══════════════════════════════════════════ -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Param&egrave;tres SMTP</h5>
                <form method="POST" action="/admin/configuration/smtp">
                    <?= \Platform\Http\Csrf::field() ?>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="smtp-host" class="form-label">H&ocirc;te SMTP</label>
                            <input type="text" class="form-control" id="smtp-host" name="host"
                                   value="<?= htmlspecialchars($smtpEffectif['host'] ?? '') ?>">
                            <small class="text-muted">Valeur .env : <?= htmlspecialchars($smtpEnv['host'] ?? 'non d&eacute;finie') ?></small>
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
                                   placeholder="<?= !empty($smtpDb['password']) ? '••••••• (d&eacute;fini)' : 'Non configur&eacute;' ?>">
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
                            <label for="smtp-from" class="form-label">Adresse d'exp&eacute;dition</label>
                            <input type="email" class="form-control" id="smtp-from" name="from"
                                   value="<?= htmlspecialchars($smtpEffectif['from'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="smtp-from-name" class="form-label">Nom d'exp&eacute;diteur</label>
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
                <h6 class="card-title"><i class="bi bi-info-circle me-1"></i> Priorit&eacute; de configuration</h6>
                <p class="small text-muted mb-2">Les valeurs sont r&eacute;solues dans cet ordre :</p>
                <ol class="small text-muted mb-0">
                    <li><strong>Base de donn&eacute;es</strong> &mdash; valeurs saisies ici</li>
                    <li><strong>Fichier .env</strong> &mdash; variables d'environnement</li>
                    <li><strong>D&eacute;fauts</strong> &mdash; valeurs par d&eacute;faut du code</li>
                </ol>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-google me-1"></i> Configuration Gmail</h6>
                <p class="small text-muted mb-2">Pour utiliser Gmail avec un mot de passe d'application :</p>
                <ol class="small text-muted mb-2">
                    <li>Activez la <strong>validation en 2 &eacute;tapes</strong> sur votre compte Google</li>
                    <li>Cr&eacute;ez un <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">mot de passe d'application</a></li>
                    <li>Cliquez sur le bouton ci-dessous pour pr&eacute;-remplir les champs</li>
                </ol>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-prefill-gmail">
                    <i class="bi bi-lightning me-1"></i>Pr&eacute;-remplir Gmail
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
});

document.getElementById('btn-test-email')?.addEventListener('click', function() {
    const btn = this;
    const resultDiv = document.getElementById('test-email-result');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Envoi...';
    resultDiv.style.display = 'none';

    fetch('/admin/configuration/smtp/test', {
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
<form method="POST" action="/admin/configuration/notifications">
    <?= \Platform\Http\Csrf::field() ?>

    <!-- Parametres generaux -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Param&egrave;tres g&eacute;n&eacute;raux</h5>
            <div class="row">
                <div class="col-md-8">
                    <label for="admin-email" class="form-label">Email(s) admin pour notifications</label>
                    <input type="text" class="form-control" id="admin-email" name="admin_email"
                           value="<?= htmlspecialchars($notifDb['admin_email'] ?? '') ?>"
                           placeholder="admin@example.com, admin2@example.com">
                    <small class="text-muted">S&eacute;parer par des virgules. Si vide, les admins actifs de la plateforme seront notifi&eacute;s.</small>
                </div>
                <div class="col-md-4">
                    <label for="quota-seuil" class="form-label">Seuil alerte quota</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="quota-seuil" name="quota_seuil_alerte"
                               value="<?= (int) ($notifDb['quota_seuil_alerte'] ?? 80) ?>" min="50" max="99">
                        <span class="input-group-text">%</span>
                    </div>
                    <small class="text-muted">Envoie une alerte &agrave; ce seuil d'utilisation.</small>
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
                            <th>Sujet personnalis&eacute;</th>
                            <th style="width: 80px;">Aper&ccedil;u</th>
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
                                        data-type="<?= $type->value ?>" title="Aper&ccedil;u">
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

<!-- Modal apercu -->
<div class="modal fade" id="modalApercu" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalApercuTitle">Aper&ccedil;u</h5>
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
                title.textContent = 'Aper\u00e7u \u2014 ' + data.label;
                iframe.srcdoc = data.html;
                modal.show();
            }
        });
    });
});
</script>

<?php elseif ($onglet === 'securite'): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ONGLET SECURITE                            -->
<!-- ═══════════════════════════════════════════ -->
<div class="row">
    <div class="col-lg-8">
        <form method="POST" action="/admin/configuration/securite">
            <?= \Platform\Http\Csrf::field() ?>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="bi bi-shield-lock me-2"></i>Authentification</h5>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="login-max-tentatives" class="form-label">Tentatives de connexion max</label>
                            <input type="number" class="form-control" id="login-max-tentatives" name="login_max_tentatives"
                                   value="<?= (int) ($securite['login_max_tentatives'] ?? 5) ?>" min="1" max="20">
                            <small class="text-muted">Nombre maximum de tentatives avant blocage temporaire.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="login-fenetre" class="form-label">Fen&ecirc;tre de blocage (secondes)</label>
                            <input type="number" class="form-control" id="login-fenetre" name="login_fenetre"
                                   value="<?= (int) ($securite['login_fenetre'] ?? 900) ?>" min="60" max="7200">
                            <small class="text-muted">Dur&eacute;e du blocage apr&egrave;s d&eacute;passement des tentatives (ex: 900 = 15 min).</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="mot-de-passe-min" class="form-label">Longueur minimale du mot de passe</label>
                            <input type="number" class="form-control" id="mot-de-passe-min" name="mot_de_passe_min"
                                   value="<?= (int) ($securite['mot_de_passe_min'] ?? 8) ?>" min="6" max="32">
                            <small class="text-muted">Nombre minimum de caract&egrave;res pour les mots de passe.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Double authentification (2FA)</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="forcer-2fa" name="forcer_2fa" value="1"
                                       <?= ($securite['forcer_2fa'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="forcer-2fa">
                                    Forcer la 2FA pour tous les utilisateurs
                                </label>
                            </div>
                            <small class="text-muted">Si activ&eacute;, chaque utilisateur devra configurer la 2FA &agrave; sa prochaine connexion.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="bi bi-clock-history me-2"></i>Sessions</h5>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="session-duree" class="form-label">Dur&eacute;e de session (minutes)</label>
                            <input type="number" class="form-control" id="session-duree" name="session_duree"
                                   value="<?= (int) ($securite['session_duree'] ?? 120) ?>" min="5" max="1440">
                            <small class="text-muted">Dur&eacute;e d'inactivit&eacute; avant d&eacute;connexion automatique (ex: 120 = 2h).</small>
                        </div>
                        <div class="col-md-6">
                            <label for="session-regeneration" class="form-label">R&eacute;g&eacute;n&eacute;ration de session (secondes)</label>
                            <input type="number" class="form-control" id="session-regeneration" name="session_regeneration"
                                   value="<?= (int) ($securite['session_regeneration'] ?? 300) ?>" min="60" max="3600">
                            <small class="text-muted">Intervalle de r&eacute;g&eacute;n&eacute;ration de l'ID de session pour pr&eacute;venir le hijacking.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="bi bi-person-plus me-2"></i>Inscription</h5>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="inscription-active" name="inscription_active" value="1"
                               <?= ($securite['inscription_active'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="inscription-active">
                            Autoriser les nouvelles inscriptions
                        </label>
                    </div>
                    <small class="text-muted">Si d&eacute;sactiv&eacute;, seuls les administrateurs pourront cr&eacute;er de nouveaux comptes.</small>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>Enregistrer
            </button>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-info-circle me-1"></i> Recommandations</h6>
                <ul class="small text-muted mb-0">
                    <li class="mb-1"><strong>Tentatives</strong> : 5 tentatives max est un bon compromis s&eacute;curit&eacute;/ergonomie.</li>
                    <li class="mb-1"><strong>Fen&ecirc;tre</strong> : 15 minutes (900s) est la valeur recommand&eacute;e par l'OWASP.</li>
                    <li class="mb-1"><strong>Session</strong> : 2 heures max pour les applications sensibles.</li>
                    <li class="mb-1"><strong>2FA</strong> : fortement recommand&eacute; pour les comptes administrateurs.</li>
                    <li class="mb-1"><strong>Mot de passe</strong> : 8 caract&egrave;res minimum, 12+ recommand&eacute;.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php elseif ($onglet === 'general'): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ONGLET GENERAL                             -->
<!-- ═══════════════════════════════════════════ -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3"><i class="bi bi-sliders me-2"></i>Param&egrave;tres g&eacute;n&eacute;raux</h5>
                <form method="POST" action="/admin/configuration/general">
                    <?= \Platform\Http\Csrf::field() ?>

                    <div class="mb-3">
                        <label for="app-name" class="form-label">Nom de l'application</label>
                        <input type="text" class="form-control" id="app-name" name="app_name"
                               value="<?= htmlspecialchars($general['app_name'] ?? '') ?>"
                               placeholder="Ma plateforme SEO">
                    </div>

                    <div class="mb-3">
                        <label for="app-url" class="form-label">URL de l'application</label>
                        <input type="url" class="form-control" id="app-url" name="app_url"
                               value="<?= htmlspecialchars($general['app_url'] ?? '') ?>"
                               placeholder="https://seo.example.com">
                        <small class="text-muted">Utilis&eacute;e pour les liens dans les emails et les webhooks.</small>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="timezone" class="form-label">Fuseau horaire</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <?php
                                $fuseaux = [
                                    'Europe/Paris'      => 'Europe/Paris (UTC+1/+2)',
                                    'Europe/London'     => 'Europe/London (UTC+0/+1)',
                                    'Europe/Berlin'     => 'Europe/Berlin (UTC+1/+2)',
                                    'Europe/Madrid'     => 'Europe/Madrid (UTC+1/+2)',
                                    'Europe/Rome'       => 'Europe/Rome (UTC+1/+2)',
                                    'Europe/Brussels'   => 'Europe/Brussels (UTC+1/+2)',
                                    'Europe/Zurich'     => 'Europe/Zurich (UTC+1/+2)',
                                    'America/New_York'  => 'America/New_York (UTC-5/-4)',
                                    'America/Chicago'   => 'America/Chicago (UTC-6/-5)',
                                    'America/Los_Angeles' => 'America/Los_Angeles (UTC-8/-7)',
                                    'America/Montreal'  => 'America/Montreal (UTC-5/-4)',
                                    'Asia/Tokyo'        => 'Asia/Tokyo (UTC+9)',
                                    'Asia/Shanghai'     => 'Asia/Shanghai (UTC+8)',
                                    'Asia/Dubai'        => 'Asia/Dubai (UTC+4)',
                                    'Australia/Sydney'  => 'Australia/Sydney (UTC+10/+11)',
                                    'Pacific/Noumea'    => 'Pacific/Noumea (UTC+11)',
                                    'Indian/Reunion'    => 'Indian/Reunion (UTC+4)',
                                    'UTC'               => 'UTC',
                                ];
                                $fuseauActuel = $general['timezone'] ?? 'Europe/Paris';
                                foreach ($fuseaux as $valeur => $label): ?>
                                <option value="<?= $valeur ?>" <?= $fuseauActuel === $valeur ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="langue" class="form-label">Langue par d&eacute;faut</label>
                            <select class="form-select" id="langue" name="langue">
                                <?php $langueActuelle = $general['langue'] ?? 'fr'; ?>
                                <option value="fr" <?= $langueActuelle === 'fr' ? 'selected' : '' ?>>Fran&ccedil;ais</option>
                                <option value="en" <?= $langueActuelle === 'en' ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="footer-text" class="form-label">Texte de pied de page</label>
                        <textarea class="form-control" id="footer-text" name="footer_text" rows="3"
                                  placeholder="&copy; 2026 Ma plateforme SEO"><?= htmlspecialchars($general['footer_text'] ?? '') ?></textarea>
                        <small class="text-muted">Affich&eacute; en bas de chaque page. Supporte le HTML basique.</small>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-info-circle me-1"></i> Informations syst&egrave;me</h6>
                <table class="table table-sm mb-0" style="font-size: 0.85rem;">
                    <tr><td class="text-muted">PHP</td><td><code><?= PHP_VERSION ?></code></td></tr>
                    <tr><td class="text-muted">Serveur</td><td><code><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') ?></code></td></tr>
                    <tr><td class="text-muted">OS</td><td><code><?= PHP_OS ?></code></td></tr>
                    <tr><td class="text-muted">Date serveur</td><td><code><?= date('Y-m-d H:i:s') ?></code></td></tr>
                    <tr><td class="text-muted">Timezone PHP</td><td><code><?= date_default_timezone_get() ?></code></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php elseif ($onglet === 'webhooks'): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ONGLET WEBHOOKS                            -->
<!-- ═══════════════════════════════════════════ -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0" style="font-size:0.9rem;">
        Configurez des webhooks pour recevoir des notifications en temps r&eacute;el sur les &eacute;v&eacute;nements de la plateforme.
    </p>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#formAjouterWebhook">
        <i class="bi bi-plus-lg me-1"></i> Ajouter un webhook
    </button>
</div>

<!-- Formulaire d'ajout (collapse) -->
<div class="collapse mb-4" id="formAjouterWebhook">
    <div class="card">
        <div class="card-body">
            <h6 class="card-title mb-3">Nouveau webhook</h6>
            <form method="POST" action="/admin/configuration/webhooks">
                <?= \Platform\Http\Csrf::field() ?>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="wh-nom" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="wh-nom" name="nom" required placeholder="Ex: Slack notifications">
                    </div>
                    <div class="col-md-5">
                        <label for="wh-url" class="form-label">URL</label>
                        <input type="url" class="form-control" id="wh-url" name="url" required placeholder="https://hooks.slack.com/...">
                    </div>
                    <div class="col-md-3">
                        <label for="wh-secret" class="form-label">Secret (optionnel)</label>
                        <input type="text" class="form-control" id="wh-secret" name="secret" placeholder="HMAC SHA-256">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">&Eacute;v&eacute;nements</label>
                    <div class="row">
                        <?php foreach ($evenementsDisponibles as $evt): ?>
                        <div class="col-md-3 col-sm-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="evenements[]" value="<?= htmlspecialchars($evt) ?>" id="wh-evt-<?= htmlspecialchars($evt) ?>">
                                <label class="form-check-label small" for="wh-evt-<?= htmlspecialchars($evt) ?>"><?= htmlspecialchars($evt) ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="actif" value="1" id="wh-actif" checked>
                    <label class="form-check-label" for="wh-actif">Actif</label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Cr&eacute;er
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Liste des webhooks -->
<?php if (empty($webhooks)): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-broadcast d-block mb-2" style="font-size: 2rem;"></i>
            Aucun webhook configur&eacute;.
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>URL</th>
                            <th>&Eacute;v&eacute;nements</th>
                            <th>Statut</th>
                            <th>Dernier envoi</th>
                            <th style="width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($webhooks as $wh): ?>
                        <?php $evts = json_decode($wh['evenements'] ?? '[]', true) ?: []; ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($wh['nom']) ?></strong>
                            </td>
                            <td style="max-width: 200px;" class="text-truncate">
                                <code style="font-size: 0.8rem;"><?= htmlspecialchars($wh['url']) ?></code>
                            </td>
                            <td>
                                <?php foreach ($evts as $evt): ?>
                                    <span class="badge bg-light text-dark border mb-1" style="font-size: 0.7rem;"><?= htmlspecialchars($evt) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?php if ($wh['actif']): ?>
                                    <span class="badge badge-active"><i class="bi bi-check-circle me-1"></i>Actif</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive"><i class="bi bi-pause-circle me-1"></i>Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.82rem;">
                                <?php if (!empty($wh['dernier_envoi'])): ?>
                                    <?= htmlspecialchars($wh['dernier_envoi']) ?>
                                    <?php if (isset($wh['dernier_statut'])): ?>
                                        <span class="badge <?= $wh['dernier_statut'] >= 200 && $wh['dernier_statut'] < 300 ? 'bg-success' : 'bg-danger' ?>" style="font-size: 0.65rem;">
                                            <?= (int) $wh['dernier_statut'] ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-editer-webhook"
                                            data-id="<?= (int) $wh['id'] ?>"
                                            data-nom="<?= htmlspecialchars($wh['nom']) ?>"
                                            data-url="<?= htmlspecialchars($wh['url']) ?>"
                                            data-secret=""
                                            data-evenements='<?= htmlspecialchars(json_encode($evts)) ?>'
                                            data-actif="<?= $wh['actif'] ? '1' : '0' ?>"
                                            title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="/admin/configuration/webhooks/<?= (int) $wh['id'] ?>/tester" class="d-inline">
                                        <?= \Platform\Http\Csrf::field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-primary" title="Tester">
                                            <i class="bi bi-send"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="/admin/configuration/webhooks/<?= (int) $wh['id'] ?>/supprimer" class="d-inline"
                                          onsubmit="return confirm('Supprimer ce webhook ?');">
                                        <?= \Platform\Http\Csrf::field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
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
    </div>
<?php endif; ?>

<!-- Modal edition webhook -->
<div class="modal fade" id="modalEditerWebhook" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formEditerWebhook" action="">
                <?= \Platform\Http\Csrf::field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le webhook</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-5">
                            <label for="edit-wh-nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="edit-wh-nom" name="nom" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-wh-url" class="form-label">URL</label>
                            <input type="url" class="form-control" id="edit-wh-url" name="url" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit-wh-secret" class="form-label">Secret</label>
                            <input type="text" class="form-control" id="edit-wh-secret" name="secret" placeholder="Laisser vide pour conserver">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">&Eacute;v&eacute;nements</label>
                        <div class="row">
                            <?php foreach ($evenementsDisponibles as $evt): ?>
                            <div class="col-md-3 col-sm-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input edit-wh-evt" type="checkbox" name="evenements[]" value="<?= htmlspecialchars($evt) ?>" id="edit-wh-evt-<?= htmlspecialchars($evt) ?>">
                                    <label class="form-check-label small" for="edit-wh-evt-<?= htmlspecialchars($evt) ?>"><?= htmlspecialchars($evt) ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="actif" value="1" id="edit-wh-actif">
                        <label class="form-check-label" for="edit-wh-actif">Actif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-editer-webhook').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const form = document.getElementById('formEditerWebhook');
        form.action = '/admin/configuration/webhooks/' + id + '/editer';

        document.getElementById('edit-wh-nom').value = this.dataset.nom;
        document.getElementById('edit-wh-url').value = this.dataset.url;
        document.getElementById('edit-wh-secret').value = '';
        document.getElementById('edit-wh-actif').checked = this.dataset.actif === '1';

        // Cocher les evenements
        const evts = JSON.parse(this.dataset.evenements || '[]');
        document.querySelectorAll('.edit-wh-evt').forEach(cb => {
            cb.checked = evts.includes(cb.value);
        });

        const modal = new bootstrap.Modal(document.getElementById('modalEditerWebhook'));
        modal.show();
    });
});
</script>

<?php elseif ($onglet === 'api'): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ONGLET API (CLES PLATEFORME)               -->
<!-- ═══════════════════════════════════════════ -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0" style="font-size:0.9rem;">
        G&eacute;rez les cl&eacute;s d'acc&egrave;s &agrave; l'API de la plateforme. Chaque cl&eacute; permet d'authentifier les requ&ecirc;tes API.
    </p>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#formCreerApiKey">
        <i class="bi bi-plus-lg me-1"></i> Cr&eacute;er une cl&eacute;
    </button>
</div>

<!-- Formulaire de creation (collapse) -->
<div class="collapse mb-4" id="formCreerApiKey">
    <div class="card">
        <div class="card-body">
            <h6 class="card-title mb-3">Nouvelle cl&eacute; API</h6>
            <form method="POST" action="/admin/configuration/api-keys">
                <?= \Platform\Http\Csrf::field() ?>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="ak-nom" class="form-label">Nom de la cl&eacute;</label>
                        <input type="text" class="form-control" id="ak-nom" name="nom" required
                               placeholder="Ex: Integration Zapier">
                    </div>
                    <div class="col-md-4">
                        <label for="ak-user" class="form-label">Utilisateur associ&eacute;</label>
                        <select class="form-select" id="ak-user" name="user_id">
                            <?php foreach ($utilisateurs as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="ak-expiration" class="form-label">Date d'expiration (optionnel)</label>
                        <input type="date" class="form-control" id="ak-expiration" name="expiration"
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-key me-1"></i>G&eacute;n&eacute;rer la cl&eacute;
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Liste des cles -->
<?php if (empty($apiKeys)): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-braces d-block mb-2" style="font-size: 2rem;"></i>
            Aucune cl&eacute; API cr&eacute;&eacute;e.
        </div>
    </div>
<?php else: ?>
    <div class="card mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Pr&eacute;fixe</th>
                            <th>Nom</th>
                            <th>Utilisateur</th>
                            <th>Cr&eacute;&eacute;e le</th>
                            <th>Derni&egrave;re utilisation</th>
                            <th>Expiration</th>
                            <th style="width: 100px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apiKeys as $ak): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($ak['prefixe'] ?? 'sk_live_...') ?></code></td>
                            <td><strong><?= htmlspecialchars($ak['nom'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($ak['username'] ?? '-') ?></td>
                            <td style="font-size: 0.85rem;"><?= htmlspecialchars($ak['created_at'] ?? '') ?></td>
                            <td style="font-size: 0.85rem;">
                                <?= !empty($ak['derniere_utilisation']) ? htmlspecialchars($ak['derniere_utilisation']) : '<span class="text-muted">Jamais</span>' ?>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?php if (!empty($ak['date_expiration'])): ?>
                                    <?php
                                        $expire = strtotime($ak['date_expiration']) < time();
                                    ?>
                                    <span class="<?= $expire ? 'text-danger' : '' ?>"><?= htmlspecialchars($ak['date_expiration']) ?></span>
                                    <?php if ($expire): ?>
                                        <span class="badge bg-danger" style="font-size: 0.65rem;">Expir&eacute;e</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Aucune</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="/admin/configuration/api-keys/<?= (int) $ak['id'] ?>/revoquer"
                                      onsubmit="return confirm('R&eacute;voquer cette cl&eacute; API ? Cette action est irr&eacute;versible.');">
                                    <?= \Platform\Http\Csrf::field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="R&eacute;voquer">
                                        <i class="bi bi-x-circle me-1"></i>R&eacute;voquer
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Documentation rapide de l'API -->
<div class="card">
    <div class="card-body">
        <h6 class="card-title mb-3"><i class="bi bi-book me-2"></i>Documentation rapide de l'API</h6>
        <p class="small text-muted mb-3">
            Authentification via header <code>Authorization: Bearer sk_live_...</code> ou param&egrave;tre <code>?api_key=sk_live_...</code>
        </p>
        <div class="table-responsive">
            <table class="table table-sm" style="font-size: 0.85rem;">
                <thead>
                    <tr>
                        <th style="width: 80px;">M&eacute;thode</th>
                        <th>Endpoint</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge bg-success">GET</span></td>
                        <td><code>/api/v1/modules</code></td>
                        <td>Liste des modules accessibles</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-success">GET</span></td>
                        <td><code>/api/v1/modules/{slug}</code></td>
                        <td>D&eacute;tails d'un module</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-success">GET</span></td>
                        <td><code>/api/v1/utilisateurs</code></td>
                        <td>Liste des utilisateurs (admin)</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-success">GET</span></td>
                        <td><code>/api/v1/quotas</code></td>
                        <td>Quotas de l'utilisateur courant</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-primary">POST</span></td>
                        <td><code>/api/v1/webhooks/test</code></td>
                        <td>D&eacute;clenche un &eacute;v&eacute;nement de test</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-success">GET</span></td>
                        <td><code>/api/v1/audit</code></td>
                        <td>Journal d'audit (admin)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($onglet === 'plans'): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ONGLET PLANS & CREDITS                     -->
<!-- ═══════════════════════════════════════════ -->

<div class="row g-4">
    <!-- Colonne gauche : Poids des modules -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-speedometer2 me-1"></i> Poids en cr&eacute;dits par module
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Chaque analyse consomme un nombre de cr&eacute;dits selon le co&ucirc;t r&eacute;el du module.
                    <strong>0 = gratuit</strong> (pas de cr&eacute;dits d&eacute;duits).
                </p>
                <form method="POST" action="/admin/configuration/plans/module-credits">
                    <?= \Platform\Http\Csrf::field() ?>
                    <table class="table table-sm align-middle mb-3">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th style="width: 80px;">Mode</th>
                                <th style="width: 120px;">Cr&eacute;dits/analyse</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modulesCredits as $mod): ?>
                            <tr>
                                <td>
                                    <i class="bi <?= htmlspecialchars($mod['icon'] ?? 'bi-tools') ?> me-1" style="color: var(--brand-teal);"></i>
                                    <?= htmlspecialchars($mod['name']) ?>
                                    <span class="text-muted" style="font-size: 0.7rem;">(<?= htmlspecialchars($mod['slug']) ?>)</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark" style="font-size: 0.7rem;"><?= htmlspecialchars($mod['quota_mode']) ?></span>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm text-center" name="credits[<?= (int) $mod['id'] ?>]"
                                           value="<?= (int) $mod['credits_par_analyse'] ?>" min="0" max="50" style="width: 70px;">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($modulesCredits)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Aucun module install&eacute;.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Enregistrer les poids
                    </button>
                </form>
            </div>
        </div>

        <!-- Info : comment ça marche -->
        <div class="card mt-3">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-info-circle me-1"></i> Comment fonctionnent les cr&eacute;dits ?</h6>
                <ul class="small text-muted mb-0">
                    <li>Chaque plan donne un pool de <strong>cr&eacute;dits mensuels</strong></li>
                    <li>Chaque analyse d&eacute;duit N cr&eacute;dits selon le poids du module</li>
                    <li>Un module &agrave; <strong>0 cr&eacute;dit</strong> est toujours gratuit</li>
                    <li>Un plan &agrave; <strong>0 cr&eacute;dit</strong> = illimit&eacute;</li>
                    <li>Les cr&eacute;dits se r&eacute;initialisent chaque mois (date d'inscription de l'utilisateur)</li>
                    <li>Les admins sont toujours exempt&eacute;s</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Colonne droite : Plans + Utilisateurs -->
    <div class="col-lg-6">
        <!-- Plans existants -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bookmark-star me-1"></i> Plans d'abonnement</span>
                <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#formNouveauPlan">
                    <i class="bi bi-plus-lg me-1"></i>Nouveau plan
                </button>
            </div>

            <!-- Formulaire nouveau plan (collapse) -->
            <div class="collapse" id="formNouveauPlan">
                <div class="card-body border-bottom" style="background: var(--brand-teal-light);">
                    <form method="POST" action="/admin/configuration/plans/creer">
                        <?= \Platform\Http\Csrf::field() ?>
                        <div class="row g-2 mb-2">
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" name="slug" placeholder="slug (ex: starter)" required>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" name="nom" placeholder="Nom du plan" required>
                            </div>
                            <div class="col-4">
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control" name="credits_mensuels" placeholder="Cr&eacute;dits" value="100" min="0">
                                    <span class="input-group-text">cr./mois</span>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-4">
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" class="form-control" name="prix_mensuel" placeholder="Prix">
                                    <span class="input-group-text">&euro;/mois</span>
                                </div>
                            </div>
                            <div class="col-5">
                                <input type="text" class="form-control form-control-sm" name="description" placeholder="Description">
                            </div>
                            <div class="col-3">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Cr&eacute;er</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Cr&eacute;dits/mois</th>
                            <th>Prix</th>
                            <th>Actif</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $plan): ?>
                        <tr>
                            <form method="POST" action="/admin/configuration/plans/<?= (int) $plan['id'] ?>/editer">
                                <?= \Platform\Http\Csrf::field() ?>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="nom" value="<?= htmlspecialchars($plan['nom']) ?>" style="width: 120px;">
                                    <span class="text-muted" style="font-size: 0.65rem;"><?= htmlspecialchars($plan['slug']) ?></span>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm text-center" name="credits_mensuels"
                                           value="<?= (int) ($plan['credits_mensuels'] ?? 0) ?>" min="0" style="width: 80px;">
                                    <span class="text-muted" style="font-size: 0.65rem;">0 = illimit&eacute;</span>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm" style="width: 100px;">
                                        <input type="number" step="0.01" class="form-control" name="prix_mensuel"
                                               value="<?= htmlspecialchars($plan['prix_mensuel'] ?? '') ?>">
                                        <span class="input-group-text">&euro;</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center mb-0">
                                        <input class="form-check-input" type="checkbox" name="actif" value="1"
                                               <?= ($plan['actif'] ?? 1) ? 'checked' : '' ?>>
                                    </div>
                                </td>
                                <td>
                                    <button type="submit" class="btn btn-outline-primary btn-sm" title="Sauvegarder">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($plans)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Aucun plan. Jouez la migration 037.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Assigner un plan à un utilisateur -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-person-check me-1"></i> Utilisateurs &amp; Plans
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Plan actuel</th>
                            <th>Cr&eacute;dits</th>
                            <th>Changer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateursPlans as $u):
                            $uCreditsUtilises = (int) ($u['credits_utilises'] ?? 0);
                            $uCreditsLimite = (int) ($u['credits_limite'] ?? 0);
                            $uIllimite = $uCreditsLimite === 0;
                            $uPct = $uIllimite ? 0 : ($uCreditsLimite > 0 ? min(100, round(($uCreditsUtilises / $uCreditsLimite) * 100)) : 0);
                            $uBarClass = $uPct >= 100 ? 'bg-danger' : ($uPct >= 80 ? 'bg-warning' : '');
                        ?>
                        <tr>
                            <td>
                                <strong style="font-size: 0.85rem;"><?= htmlspecialchars($u['username']) ?></strong>
                                <?php if (!empty($u['email'])): ?>
                                <br><span class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($u['email']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($u['plan_nom'])): ?>
                                    <span class="badge" style="background: var(--brand-teal); color: #fff;"><?= htmlspecialchars($u['plan_nom']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.8rem;">Aucun</span>
                                <?php endif; ?>
                            </td>
                            <td style="min-width: 120px;">
                                <?php if ($uIllimite): ?>
                                    <span class="text-success" style="font-size: 0.8rem;"><i class="bi bi-infinity"></i> Illimit&eacute;</span>
                                <?php elseif ($uCreditsLimite > 0): ?>
                                    <div style="font-size: 0.75rem;"><?= $uCreditsUtilises ?>/<?= $uCreditsLimite ?></div>
                                    <div class="progress" style="height: 4px;">
                                        <div class="progress-bar <?= $uBarClass ?>" style="width: <?= $uPct ?>%; <?= $uBarClass === '' ? 'background: var(--brand-teal);' : '' ?>"></div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.75rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="/admin/configuration/plans/assigner" class="d-flex gap-1">
                                    <?= \Platform\Http\Csrf::field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <select name="plan_id" class="form-select form-select-sm" style="width: 110px; font-size: 0.75rem;">
                                        <option value="">Aucun</option>
                                        <?php foreach ($plans as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>" <?= ((int) ($u['plan_id'] ?? 0)) === (int) $p['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nom']) ?> (<?= (int) ($p['credits_mensuels'] ?? 0) ?>cr.)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-outline-primary btn-sm" title="Assigner">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($utilisateursPlans)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Aucun utilisateur actif.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
