<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'SEO Platform') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/platform.css" rel="stylesheet">
    <?= $headExtra ?? '' ?>
</head>
<body>
    <!-- Topbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom fixed-top px-3">
        <button class="btn btn-sm btn-outline-secondary me-3 d-lg-none" id="sidebar-toggle" type="button">
            <i class="bi bi-list"></i>
        </button>
        <a class="navbar-brand fw-bold" href="/"><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'SEO Platform') ?></a>
        <div class="ms-auto d-flex align-items-center">
            <span class="text-muted me-3">
                <i class="bi bi-person-circle me-1"></i>
                <?= htmlspecialchars($currentUser['username'] ?? '') ?>
                <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                    <span class="badge bg-primary ms-1">admin</span>
                <?php endif; ?>
            </span>
            <a href="/logout" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-box-arrow-right"></i> Deconnexion
            </a>
        </div>
    </nav>

    <div class="d-flex" style="margin-top: 56px;">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar bg-white border-end">
            <div class="p-3">
                <h6 class="text-uppercase text-muted small fw-bold mb-3">Outils</h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeModule ?? '') === '' ? 'active' : '' ?>" href="/">
                            <i class="bi bi-grid me-2"></i> Dashboard
                        </a>
                    </li>
                    <?php foreach ($accessibleModules ?? [] as $mod): ?>
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
                                    && $qs[$modSlug]['quota_mode'] !== 'none'
                                    && $qs[$modSlug]['limit'] > 0
                                ):
                                    $qUsage = $qs[$modSlug]['usage'];
                                    $qLimit = $qs[$modSlug]['limit'];
                                    $qPct = round(($qUsage / $qLimit) * 100);
                                    $qBadgeClass = $qPct >= 100 ? 'bg-danger' : ($qPct >= 80 ? 'bg-warning text-dark' : 'bg-secondary');
                                ?>
                                    <span class="badge <?= $qBadgeClass ?> ms-auto"><?= $qUsage ?>/<?= $qLimit ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                    <h6 class="text-uppercase text-muted small fw-bold mb-3 mt-4">Administration</h6>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= ($adminPage ?? '') === 'users' ? 'active' : '' ?>" href="/admin/users">
                                <i class="bi bi-people me-2"></i> Utilisateurs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($adminPage ?? '') === 'access' ? 'active' : '' ?>" href="/admin/access">
                                <i class="bi bi-key me-2"></i> Acces
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($adminPage ?? '') === 'quotas' ? 'active' : '' ?>" href="/admin/quotas">
                                <i class="bi bi-speedometer me-2"></i> Quotas
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </nav>

        <!-- Main content -->
        <main class="main-content flex-grow-1 p-4">
            <?php foreach ($flash ?? [] as $msg): ?>
                <div class="alert alert-<?= htmlspecialchars($msg['type']) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($msg['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>

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
    <script src="/assets/js/platform.js"></script>
    <?= $footExtra ?? '' ?>
</body>
</html>
