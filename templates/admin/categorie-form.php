<h2 class="mb-4"><?= isset($categorie) ? 'Modifier la catégorie' : 'Nouvelle catégorie' ?></h2>

<form method="POST">
    <?= \Platform\Http\Csrf::field() ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Informations</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="nom" name="nom" required
                               value="<?= htmlspecialchars($categorie['nom'] ?? '') ?>"
                               placeholder="Ex : Webperf, Contenu, Technique">
                    </div>

                    <div class="mb-3">
                        <label for="icone" class="form-label">Icône Bootstrap Icons</label>
                        <input type="text" class="form-control" id="icone" name="icone"
                               value="<?= htmlspecialchars($categorie['icone'] ?? 'bi-folder') ?>"
                               placeholder="bi-folder">
                        <small class="text-muted">
                            Classe Bootstrap Icons (ex : <code>bi-speedometer</code>, <code>bi-globe</code>, <code>bi-file-text</code>).
                            <?php $previewIcone = htmlspecialchars($categorie['icone'] ?? 'bi-folder'); ?>
                            Aperçu : <i class="bi <?= $previewIcone ?>" id="icone-preview"></i>
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="sort_order" class="form-label">Ordre d'affichage</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order"
                               value="<?= (int) ($categorie['sort_order'] ?? 100) ?>" min="0">
                        <small class="text-muted">Les catégories avec un ordre plus petit apparaissent en premier dans la sidebar.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">
            <?= isset($categorie) ? 'Enregistrer' : 'Créer' ?>
        </button>
        <a href="/admin/plugins?onglet=categories" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>

<script>
document.getElementById('icone').addEventListener('input', function() {
    const preview = document.getElementById('icone-preview');
    preview.className = 'bi ' + this.value.trim();
});
</script>
