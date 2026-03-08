<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préférences de notification - Le lab</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/platform.css?v=1" rel="stylesheet">
</head>
<body>
    <div class="login-wrapper d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="card shadow-sm" style="width: 100%; max-width: 540px; border-radius: 1rem;">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img src="/assets/img/logo-login.png" alt="Le lab" class="login-logo mb-2" style="max-height: 48px;">
                    <h4 style="color: #004c4c; font-weight: 700;">Préférences de notification</h4>
                </div>

                <?php if (!empty($erreur)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($erreur) ?>
                    </div>
                <?php elseif (!empty($succes)): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($succes) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($user) && !empty($types)): ?>
                    <p class="text-muted small mb-3">
                        Bonjour <strong><?= htmlspecialchars($user['username']) ?></strong>,
                        choisissez les notifications que vous souhaitez recevoir par email.
                    </p>

                    <form method="POST" action="/desabonnement">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        <div class="list-group mb-3">
                            <?php foreach ($types as $type): ?>
                                <?php
                                    $estActif = !isset($preferences[$type->value]) || $preferences[$type->value];
                                    $inputId = 'notif_' . $type->value;
                                ?>
                                <label class="list-group-item d-flex align-items-center" for="<?= $inputId ?>" style="cursor: pointer;">
                                    <div class="form-check form-switch me-3 mb-0">
                                        <input class="form-check-input" type="checkbox" id="<?= $inputId ?>"
                                               name="<?= $inputId ?>" <?= $estActif ? 'checked' : '' ?>>
                                    </div>
                                    <div>
                                        <i class="bi <?= $type->icone() ?> me-1" style="color: #66b2b2;"></i>
                                        <strong style="font-size: 0.9rem;"><?= htmlspecialchars($type->label()) ?></strong>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" style="background-color: #004c4c; border-color: #004c4c;">
                                <i class="bi bi-check-lg me-1"></i>Enregistrer
                            </button>
                        </div>
                    </form>

                    <hr class="my-3">
                    <form method="POST" action="/desabonnement/tout">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                                onclick="return confirm('Êtes-vous sûr de vouloir vous désabonner de toutes les notifications ?')">
                            <i class="bi bi-bell-slash me-1"></i>Se désabonner de tout
                        </button>
                    </form>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <small class="text-muted">
                        <a href="/politique-de-confidentialite" style="color: #66b2b2;">Politique de confidentialité</a>
                        &bull;
                        <a href="/mentions-legales" style="color: #66b2b2;">Mentions légales</a>
                    </small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
