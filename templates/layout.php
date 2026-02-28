<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'SEO Platform') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/platform.css?v=<?= filemtime(__DIR__ . '/../public/assets/css/platform.css') ?>" rel="stylesheet">
    <?= $headExtra ?? '' ?>
</head>
<body>
    <!-- Topbar -->
    <nav class="navbar fixed-top px-3">
        <div class="d-flex align-items-center">
            <button class="btn btn-sm btn-sidebar-toggle me-3 d-lg-none" id="sidebar-toggle" type="button">
                <i class="bi bi-list"></i>
            </button>
            <a class="navbar-brand" href="/"><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'SEO Platform') ?></a>
        </div>
        <div class="d-flex align-items-center">
            <?php if (!empty($moduleLangages) && count($moduleLangages) > 1): ?>
                <div class="btn-group btn-group-sm me-3" id="platformLangSelector">
                    <?php foreach ($moduleLangages as $lg): ?>
                        <button type="button" class="btn btn-outline-light btn-lang" data-lang="<?= htmlspecialchars($lg) ?>"><?= strtoupper(htmlspecialchars($lg)) ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <span class="nav-user me-3">
                <i class="bi bi-person-circle me-1"></i>
                <?= htmlspecialchars($currentUser['username'] ?? '') ?>
                <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                    <span class="badge badge-admin ms-1">admin</span>
                <?php endif; ?>
            </span>
            <a href="/logout" class="btn btn-sm btn-logout">
                <i class="bi bi-box-arrow-right"></i> Déconnexion
            </a>
        </div>
    </nav>

    <div class="d-flex" style="margin-top: 56px;">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="p-3">
                <h6 class="sidebar-heading mb-3 mt-1">Outils</h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeModule ?? '') === '' && !isset($adminPage) ? 'active' : '' ?>" href="/">
                            <i class="bi bi-grid me-2"></i> Dashboard
                        </a>
                    </li>
                </ul>

                <?php
                // Grouper les modules par catégorie
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

                // Trier : "Non classé" (key 0) en dernier, puis par sort_order catégorie
                uksort($modulesParCategorie, function ($a, $b) use ($modulesParCategorie) {
                    if ($a === 0) return 1;
                    if ($b === 0) return -1;
                    return $modulesParCategorie[$a]['sort_order'] <=> $modulesParCategorie[$b]['sort_order'];
                });

                $catIndex = 0;
                foreach ($modulesParCategorie as $catId => $catData):
                    $catNom = $catId === 0 ? 'Non classé' : $catData['nom'];
                    $catIcone = $catId === 0 ? 'bi-folder' : $catData['icone'];
                    $collapseId = 'sidebar-cat-' . $catIndex;

                    // Déplier si la catégorie contient le module actif
                    $contientActif = false;
                    foreach ($catData['modules'] as $mod) {
                        if (($activeModule ?? '') === $mod['slug']) {
                            $contientActif = true;
                            break;
                        }
                    }
                    $showClass = $contientActif ? 'show' : 'show';
                ?>
                <h6 class="sidebar-heading sidebar-category mt-3 mb-1"
                    data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"
                    aria-expanded="true" aria-controls="<?= $collapseId ?>"
                    role="button" style="cursor: pointer;">
                    <i class="bi <?= htmlspecialchars($catIcone) ?> me-1"></i>
                    <?= htmlspecialchars($catNom) ?>
                    <i class="bi bi-chevron-down ms-auto sidebar-chevron" style="font-size: 0.65rem;"></i>
                </h6>
                <div class="collapse <?= $showClass ?>" id="<?= $collapseId ?>">
                    <ul class="nav flex-column">
                        <?php foreach ($catData['modules'] as $mod): ?>
                            <li class="nav-item">
                                <a class="nav-link d-flex align-items-center <?= ($activeModule ?? '') === $mod['slug'] ? 'active' : '' ?>"
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
                </div>
                <?php $catIndex++; endforeach; ?>

                <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                    <h6 class="sidebar-heading mb-3 mt-4">Administration</h6>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= ($adminPage ?? '') === 'users' ? 'active' : '' ?>" href="/admin/users">
                                <i class="bi bi-people me-2"></i> Utilisateurs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($adminPage ?? '') === 'plugins' ? 'active' : '' ?>" href="/admin/plugins">
                                <i class="bi bi-puzzle me-2"></i> Plugins
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </nav>

        <!-- Main content -->
        <main class="<?= !empty($modeIframe) ? 'main-content-iframe flex-grow-1' : 'main-content flex-grow-1 p-4' ?>">
            <?php if (empty($modeIframe)): ?>
                <?php foreach ($flash ?? [] as $msg): ?>
                    <div class="alert alert-<?= htmlspecialchars($msg['type']) ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($msg['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php
            if (isset($template)) {
                require __DIR__ . '/' . $template . '.php';
            } elseif (isset($content)) {
                echo $content;
            }
            ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <?php endif; ?>
    <script src="/assets/js/platform.js"></script>
    <?= $footExtra ?? '' ?>
</body>
</html>
