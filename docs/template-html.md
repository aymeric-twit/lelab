# Template HTML — Plugin embedded

## Structure standard

```php
<?php
// Chargement des donnees, traitement du formulaire
$donnees = traiterFormulaire();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Plugin — Sous-titre</title>
    <!-- CDN (pour standalone, ignore par extractParts) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- CSS local (extrait et reecrit par extractParts) -->
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<!-- Navbar (supprimee automatiquement en mode embedded) -->
<nav class="navbar mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1">
            Mon Plugin
            <span class="d-block d-sm-inline ms-sm-2">Sous-titre</span>
        </span>
    </div>
</nav>

<div class="container">
    <!-- Formulaire — le CSRF est injecte automatiquement -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="query" class="form-label">Requete</label>
                    <input type="text" class="form-control" id="query" name="query" required>
                </div>
                <button type="submit" class="btn btn-primary">Lancer</button>
            </form>
        </div>
    </div>

    <!-- Resultats -->
    <?php if (!empty($donnees)): ?>
    <div class="card">
        <div class="card-header"><h6 class="mb-0">Resultats</h6></div>
        <div class="card-body">
            <!-- ... -->
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="app.js"></script>
</body>
</html>
```

## Redirections en mode embedded

```php
if (defined('PLATFORM_EMBEDDED')) {
    $baseUrl = '/m/' . $slug;
    header('Location: ' . $baseUrl . '/results.php?id=' . urlencode($id));
} else {
    header('Location: results.php?id=' . urlencode($id));
}
exit;
```

Les formulaires avec `action=""` (vide) fonctionnent dans les deux modes car l'URL courante
est deja `/m/{slug}/index.php` en embedded.
