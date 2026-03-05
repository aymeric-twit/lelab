<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification email - Le lab</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/platform.css?v=<?= filemtime(__DIR__ . '/../public/assets/css/platform.css') ?>" rel="stylesheet">
</head>
<body>
    <div class="login-wrapper d-flex align-items-center justify-content-center">
        <div class="card login-card shadow-sm" style="width: 100%; max-width: 420px;">
            <div class="card-body p-4 text-center">
                <div class="mb-4">
                    <img src="/assets/img/logo-login.png" alt="Le lab" class="login-logo mb-2">
                </div>

                <?php foreach ($flash ?? [] as $msg): ?>
                    <div class="alert alert-<?= htmlspecialchars($msg['type']) ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($msg['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>

                <?php if (!($succes ?? false)): ?>
                    <div class="mb-3">
                        <i class="bi bi-x-circle text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-2">Vérification échouée</h5>
                    <p class="text-muted small"><?= htmlspecialchars($message ?? 'Lien invalide ou expiré.') ?></p>
                <?php endif; ?>

                <a href="/login" class="btn btn-primary mt-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Se connecter
                </a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
