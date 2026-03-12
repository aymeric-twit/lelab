<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\Platform\Http\Csrf::token()) ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - Le lab</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/platform.css?v=<?= filemtime(__DIR__ . '/../public/assets/css/platform.css') ?>" rel="stylesheet">
    <?= $headExtra ?? '' ?>
</head>
<body>
    <?php
    // Grouper les modules par catégorie pour la navbar
    $modulesParCategorie = [];
    foreach ($accessibleModules ?? [] as $mod) {
        $catKey = $mod['categorie_id'] ?? 0;
        if (!isset($modulesParCategorie[$catKey])) {
            $modulesParCategorie[$catKey] = [
                'nom'        => $mod['categorie_nom'] ?? null,
                'icone'      => $mod['categorie_icone'] ?? 'bi-folder',
                'sort_order' => $mod['categorie_sort_order'] ?? 9999,
                'modules'    => [],
            ];
        }
        $modulesParCategorie[$catKey]['modules'][] = $mod;
    }
    uksort($modulesParCategorie, function ($a, $b) use ($modulesParCategorie) {
        if ($a === 0) return 1;
        if ($b === 0) return -1;
        return $modulesParCategorie[$a]['sort_order'] <=> $modulesParCategorie[$b]['sort_order'];
    });
    ?>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top px-3">
        <a class="navbar-brand d-flex align-items-center" href="/">
            <img src="/assets/img/logo.png" alt="Le lab" class="navbar-logo">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain"
                aria-controls="navbarMain" aria-expanded="false" aria-label="Menu">
            <i class="bi bi-list"></i>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <?php foreach ($modulesParCategorie as $catId => $catData):
                    $catNom = $catId === 0 ? 'Autres' : $catData['nom'];
                    $catIcone = $catId === 0 ? 'bi-three-dots' : $catData['icone'];
                    $catContientActif = false;
                    foreach ($catData['modules'] as $mod) {
                        if (($activeModule ?? '') === $mod['slug']) { $catContientActif = true; break; }
                    }
                ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $catContientActif ? 'active' : '' ?>" href="#"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi <?= htmlspecialchars($catIcone) ?> me-1"></i>
                        <?= htmlspecialchars($catNom) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <?php foreach ($catData['modules'] as $mod):
                            $isActive = ($activeModule ?? '') === $mod['slug'];
                        ?>
                        <li>
                            <a class="dropdown-item d-flex align-items-center <?= $isActive ? 'active' : '' ?>"
                               href="/m/<?= htmlspecialchars($mod['slug']) ?>">
                                <i class="bi <?= htmlspecialchars($mod['icon'] ?? 'bi-tools') ?> me-2"></i>
                                <?= htmlspecialchars($mod['name']) ?>
                                <?php
                                $qs = $quotaSummary ?? [];
                                $modSlug = $mod['slug'];
                                if (($currentUser['role'] ?? '') !== 'admin'
                                    && isset($qs[$modSlug])
                                    && $qs[$modSlug]['quota_mode'] !== \Platform\Enum\QuotaMode::None
                                    && $qs[$modSlug]['limit'] > 0
                                ):
                                    $qUsage = $qs[$modSlug]['usage'];
                                    $qLimit = $qs[$modSlug]['limit'];
                                    $qPct = round(($qUsage / $qLimit) * 100);
                                    $qBadgeClass = $qPct >= 100 ? 'badge-quota-danger' : ($qPct >= 80 ? 'badge-quota-warn' : 'badge-quota-ok');
                                ?>
                                    <span class="badge <?= $qBadgeClass ?> ms-auto"><?= $qUsage ?>/<?= $qLimit ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php endforeach; ?>
            </ul>

            <ul class="navbar-nav align-items-lg-center">
                <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= isset($adminPage) ? 'active' : '' ?>" href="#"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-gear me-1"></i> Admin
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item <?= ($adminPage ?? '') === 'users' ? 'active' : '' ?>" href="/admin/users">
                                <i class="bi bi-people me-2"></i> Utilisateurs
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($adminPage ?? '') === 'groups' ? 'active' : '' ?>" href="/admin/groups">
                                <i class="bi bi-people-fill me-2"></i> Groupes
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($adminPage ?? '') === 'plugins' ? 'active' : '' ?>" href="/admin/plugins">
                                <i class="bi bi-puzzle me-2"></i> Plugins
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($adminPage ?? '') === 'maintenance' ? 'active' : '' ?>" href="/admin/maintenance">
                                <i class="bi bi-tools me-2"></i> Maintenance
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($adminPage ?? '') === 'emails' ? 'active' : '' ?>" href="/admin/emails">
                                <i class="bi bi-envelope me-2"></i> Emails
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item <?= ($adminPage ?? '') === 'audit' ? 'active' : '' ?>" href="/admin/audit">
                                <i class="bi bi-journal-text me-2"></i> Journal d'audit
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (!empty($moduleLangages) && count($moduleLangages) > 1): ?>
                <li class="nav-item ms-lg-2">
                    <div class="btn-group btn-group-sm" id="platformLangSelector">
                        <?php foreach ($moduleLangages as $lg): ?>
                            <button type="button" class="btn btn-outline-light btn-lang" data-lang="<?= htmlspecialchars($lg) ?>"><?= strtoupper(htmlspecialchars($lg)) ?></button>
                        <?php endforeach; ?>
                    </div>
                </li>
                <?php endif; ?>

                <li class="nav-item ms-lg-2">
                    <a href="/mon-compte" class="nav-user text-decoration-none">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($currentUser['username'] ?? '') ?>
                        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                            <span class="badge badge-admin ms-1">admin</span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item ms-lg-1">
                    <a href="/logout" class="btn btn-sm btn-logout">
                        <i class="bi bi-box-arrow-right"></i> Déconnexion
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main content -->
    <?php
    // Sidebar quota : affichée uniquement sur les pages plugin (pas dashboard, pas admin)
    $qs = $quotaSummary ?? [];
    $estAdmin = ($currentUser['role'] ?? '') === 'admin';
    $aDesQuotasActifs = false;
    foreach ($qs as $q) {
        if (($q['quota_mode'] ?? null) !== \Platform\Enum\QuotaMode::None) {
            $aDesQuotasActifs = true;
            break;
        }
    }
    $afficherSidebarQuota = !empty($activeModule) && $aDesQuotasActifs;
    ?>
    <div style="margin-top: var(--navbar-height);" <?= $afficherSidebarQuota ? 'class="d-flex"' : '' ?>>

        <?php if ($afficherSidebarQuota): ?>
        <aside class="quota-sidebar d-none d-lg-block">
            <div class="quota-sidebar-inner">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <i class="bi bi-speedometer2 me-1"></i> Mes cr&eacute;dits
                    </div>
                    <div class="card-body">
                        <?php foreach ($qs as $slug => $q):
                            if ($q['quota_mode'] === \Platform\Enum\QuotaMode::None) continue;
                            $limit = (int) $q['limit'];
                            $usage = (int) $q['usage'];
                            $nomModule = $slug;
                            foreach ($accessibleModules ?? [] as $m) {
                                if ($m['slug'] === $slug) { $nomModule = $m['name']; break; }
                            }
                            $estActif = ($slug === ($activeModule ?? ''));
                        ?>
                            <div class="dashboard-quota-item mb-2 <?= $estActif ? 'quota-item-active' : '' ?>">
                                <div class="d-flex justify-content-between align-items-center" style="font-size: 0.78rem;">
                                    <span class="text-truncate me-2"><?= htmlspecialchars($nomModule) ?></span>
                                    <?php if ($estAdmin || $limit === 0): ?>
                                        <span class="text-muted" style="font-size: 0.72rem; white-space: nowrap;">Illimit&eacute;</span>
                                    <?php else: ?>
                                        <span style="font-size: 0.72rem; white-space: nowrap;"><?= $usage ?> / <?= $limit ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$estAdmin && $limit > 0):
                                    $pct = min(100, round(($usage / $limit) * 100));
                                    $barClass = $pct >= 100 ? 'progress-bar-danger' : ($pct >= 80 ? 'progress-bar-warn' : 'progress-bar-teal');
                                ?>
                                <div class="progress mt-1" style="height: 3px;">
                                    <div class="progress-bar <?= $barClass ?>" style="width: <?= $pct ?>%"></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </aside>
        <?php endif; ?>

        <main class="<?= !empty($modeIframe) ? 'main-content-iframe' : 'main-content p-4' ?> <?= $afficherSidebarQuota ? 'flex-grow-1' : '' ?>">
            <?php
            // Bandeau d'alerte quotas (> 80%)
            if (!$estAdmin):
                $alertesQuota = [];
                foreach ($qs as $slugQ => $qData) {
                    if ($qData['quota_mode'] === \Platform\Enum\QuotaMode::None) continue;
                    $lQ = (int) $qData['limit'];
                    $uQ = (int) $qData['usage'];
                    if ($lQ <= 0) continue;
                    $pctQ = round(($uQ / $lQ) * 100);
                    if ($pctQ >= 80) {
                        $nomQ = $slugQ;
                        foreach ($accessibleModules ?? [] as $mQ) {
                            if ($mQ['slug'] === $slugQ) { $nomQ = $mQ['name']; break; }
                        }
                        $alertesQuota[] = ['nom' => $nomQ, 'usage' => $uQ, 'limit' => $lQ, 'pct' => $pctQ];
                    }
                }
                foreach ($alertesQuota as $alerte):
                    $typeAlerte = $alerte['pct'] >= 100 ? 'danger' : 'warning';
                    $iconeAlerte = $alerte['pct'] >= 100 ? 'bi-x-octagon-fill' : 'bi-exclamation-triangle-fill';
            ?>
                <div class="alert alert-<?= $typeAlerte ?> alert-dismissible fade show d-flex align-items-center py-2 px-3 mb-3" role="alert" style="font-size: 0.85rem;">
                    <i class="bi <?= $iconeAlerte ?> me-2"></i>
                    <div>
                        <?php if ($alerte['pct'] >= 100): ?>
                            <strong><?= htmlspecialchars($alerte['nom']) ?></strong> : quota &eacute;puis&eacute; (<?= $alerte['usage'] ?>/<?= $alerte['limit'] ?>).
                        <?php else: ?>
                            <strong><?= htmlspecialchars($alerte['nom']) ?></strong> : <?= $alerte['pct'] ?>% du quota utilis&eacute; (<?= $alerte['usage'] ?>/<?= $alerte['limit'] ?>).
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size: 0.65rem;"></button>
                </div>
            <?php
                endforeach;
            endif;
            ?>

            <?php
            if (isset($template)) {
                require __DIR__ . '/' . $template . '.php';
            } elseif (isset($content)) {
                echo $content;
            }
            ?>
        </main>
    </div>

    <footer class="text-center py-3" style="font-size: 0.75rem; color: #999;">
        <a href="/politique-de-confidentialite" style="color: #66b2b2; text-decoration: none;">Politique de confidentialité</a>
        <span class="mx-1">&bull;</span>
        <a href="/mentions-legales" style="color: #66b2b2; text-decoration: none;">Mentions légales</a>
    </footer>

    <?php if (!empty($flash)): ?>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 1090;">
        <?php foreach ($flash as $msg):
            $toastCfg = match($msg['type']) {
                'success' => ['icone' => 'bi-check-circle-fill', 'classe' => 'toast-success', 'titre' => 'Succès'],
                'danger'  => ['icone' => 'bi-x-circle-fill',     'classe' => 'toast-danger',  'titre' => 'Erreur'],
                'warning' => ['icone' => 'bi-exclamation-triangle-fill', 'classe' => 'toast-warning', 'titre' => 'Attention'],
                'info'    => ['icone' => 'bi-info-circle-fill',   'classe' => 'toast-info',    'titre' => 'Information'],
                default   => ['icone' => 'bi-info-circle-fill',   'classe' => 'toast-info',    'titre' => 'Notification'],
            };
        ?>
        <div class="toast <?= $toastCfg['classe'] ?>" role="alert" aria-live="assertive" aria-atomic="true"
             data-bs-autohide="true" data-bs-delay="5000">
            <div class="toast-header">
                <i class="bi <?= $toastCfg['icone'] ?> me-2 toast-icon"></i>
                <strong class="me-auto toast-title"><?= $toastCfg['titre'] ?></strong>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="toast" aria-label="Fermer"></button>
            </div>
            <div class="toast-body"><?= htmlspecialchars($msg['message']) ?></div>
            <div class="toast-progress"><div class="toast-progress-bar"></div></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <?php endif; ?>
    <script src="/assets/js/platform.js"></script>
    <?= $footExtra ?? '' ?>
</body>
</html>
