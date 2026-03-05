<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $codeErreur ?> - Le lab</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/platform.css?v=<?= @filemtime(__DIR__ . '/../public/assets/css/platform.css') ?: '' ?>" rel="stylesheet">
</head>
<body>
    <div class="login-wrapper d-flex align-items-center justify-content-center">
        <div class="card error-card error-card-<?= $codeErreur ?> shadow-sm text-center p-4" style="width: 100%; max-width: 480px; border-radius: 1rem;">
            <div class="card-body">
                <div class="error-code"><?= $codeErreur ?></div>
                <div class="error-icon mb-3" style="color: <?= $couleurAccent ?? 'var(--brand-teal)' ?>;">
                    <i class="bi <?= $iconeErreur ?>"></i>
                </div>
                <h3 class="fw-bold mb-2"><?= htmlspecialchars($titreErreur) ?></h3>
                <p class="text-muted mb-4"><?= htmlspecialchars($messageErreur) ?></p>

                <div class="d-flex justify-content-center gap-2">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="/" class="btn btn-primary">
                            <i class="bi bi-house me-1"></i>Retour au dashboard
                        </a>
                    <?php else: ?>
                        <a href="/login" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Se connecter
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
