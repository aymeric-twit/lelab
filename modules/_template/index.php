<div class="container-fluid py-4">
    <h2 class="mb-4">{{NAME}}</h2>
    <p class="text-muted">{{DESCRIPTION}}</p>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" id="module-form">
                <div class="mb-3">
                    <label for="input-query" class="form-label">Votre requete</label>
                    <input type="text" class="form-control" id="input-query" name="query" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-play-fill me-1"></i> Lancer
                </button>
            </form>
        </div>
    </div>

    <div id="results" class="mt-4" style="display:none;">
        <div class="card shadow-sm">
            <div class="card-header">Resultats</div>
            <div class="card-body" id="results-content"></div>
        </div>
    </div>
</div>

<script>
// MODULE_BASE_URL est injecte automatiquement par la plateforme (ex: '/m/{{SLUG}}')
// Utilisez-le pour les appels AJAX vers les sous-routes du module.
//
// Pour le mode quota api_call, appelez \Platform\Module\Quota::track('{{SLUG}}')
// dans votre endpoint AJAX apres chaque operation reussie.
//
// Exemple d'appel AJAX :
// fetch(window.MODULE_BASE_URL + '/process.php', {
//     method: 'POST',
//     headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '...' },
//     body: formData
// });
</script>
