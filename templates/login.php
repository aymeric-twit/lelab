<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Le lab</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/platform.css?v=<?= filemtime(__DIR__ . '/../public/assets/css/platform.css') ?>" rel="stylesheet">
    <script>if (window.self !== window.top) { window.top.location.href = '/login'; }</script>
</head>
<body>
    <div class="login-wrapper d-flex flex-column align-items-center justify-content-center">
        <div class="card login-card shadow-sm" style="width: 100%; max-width: 420px;">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img src="/assets/img/logo-login.png" alt="Le lab" class="login-logo mb-2">
                    <p class="text-muted small">Connectez-vous pour continuer</p>
                </div>

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
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="se_souvenir" value="1" id="se_souvenir">
                            <label class="form-check-label small" for="se_souvenir">Se souvenir de moi</label>
                        </div>
                        <a href="/mot-de-passe-oublie" class="small text-decoration-none" style="color: var(--brand-teal);">Mot de passe oublié ?</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                </form>

                <?php if (!empty($inscriptionActive)): ?>
                <div class="text-center mt-3">
                    <span class="small text-muted">Pas encore de compte ?</span>
                    <a href="/inscription" class="small text-decoration-none fw-semibold" style="color: var(--brand-dark);">Créer un compte</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-center mt-3" style="font-size: 0.75rem;">
            <a href="/politique-de-confidentialite" style="color: #66b2b2; text-decoration: none;">Politique de confidentialité</a>
            <span class="mx-1 text-muted">&bull;</span>
            <a href="/mentions-legales" style="color: #66b2b2; text-decoration: none;">Mentions légales</a>
        </div>
    </div>
    <?php if (!empty($flash)): ?>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 1090;">
        <?php foreach ($flash as $msg):
            $toastCfg = match($msg['type']) {
                'success' => ['icone' => 'bi-check-circle-fill', 'classe' => 'toast-success', 'titre' => 'Succès'],
                'danger'  => ['icone' => 'bi-x-circle-fill',     'classe' => 'toast-danger',  'titre' => 'Erreur'],
                'warning' => ['icone' => 'bi-exclamation-triangle-fill', 'classe' => 'toast-warning', 'titre' => 'Attention'],
                default   => ['icone' => 'bi-info-circle-fill',   'classe' => 'toast-info',    'titre' => 'Information'],
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
    <script>document.querySelectorAll('#toastContainer .toast').forEach(function(el){new bootstrap.Toast(el).show();});</script>
</body>
</html>
