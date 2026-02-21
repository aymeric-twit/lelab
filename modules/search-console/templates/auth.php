<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Search Console</title>
    <link rel="stylesheet" href="/module-assets/search-console/public/assets/css/app.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-card">
            <h2>Search Console Dashboard</h2>

            <?php if (!empty($hasToken)): ?>
                <p>Vous etes connecte a Google Search Console.</p>
                <a href="<?= defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '' ?>/" class="btn btn-success">Acceder au dashboard</a>
                <br><br>
                <a href="<?= defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '' ?>/auth/logout" class="btn btn-danger">Deconnexion</a>
            <?php else: ?>
                <p>Connectez-vous avec votre compte Google pour acceder aux donnees Search Console.</p>
                <a href="<?= defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '' ?>/auth/login" class="btn">Se connecter avec Google</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
