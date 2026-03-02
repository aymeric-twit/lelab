<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'SEO Platform') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/platform.css?v=<?= filemtime(__DIR__ . '/../public/assets/css/platform.css') ?>" rel="stylesheet">
    <script>if (window.self !== window.top) { window.top.location.href = '/login'; }</script>
</head>
<body>
    <div class="login-wrapper d-flex align-items-center justify-content-center">
        <div class="card login-card shadow-sm" style="width: 100%; max-width: 420px;">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h4 class="login-title"><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'SEO Platform') ?></h4>
                    <p class="text-muted small">Connectez-vous pour continuer</p>
                </div>

                <?php foreach ($flash ?? [] as $msg): ?>
                    <div class="alert alert-<?= htmlspecialchars($msg['type']) ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($msg['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>

                <form method="POST" action="/login">
                    <?= \Platform\Http\Csrf::field() ?>
                    <div class="mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur</label>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
