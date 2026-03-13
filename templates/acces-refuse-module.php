<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm text-center" style="border-radius: 1rem;">
                <div class="card-body p-4">
                    <div class="mb-3" style="font-size: 3rem; color: var(--brand-gold);">
                        <i class="bi <?= htmlspecialchars($moduleIcone ?? 'bi-shield-x') ?>"></i>
                    </div>
                    <h3 class="fw-bold mb-2" style="color: var(--brand-dark);">Acc&egrave;s non autoris&eacute;</h3>
                    <p class="text-muted mb-4">
                        Vous n'avez pas acc&egrave;s au module
                        &laquo;&nbsp;<strong><?= htmlspecialchars($moduleNom ?? '') ?></strong>&nbsp;&raquo;.
                        <br>Vous pouvez demander l'acc&egrave;s &agrave; l'administrateur.
                    </p>

                    <?php if (!empty($demandeEnvoyee)): ?>
                        <div class="alert alert-success py-2" style="font-size: 0.9rem;">
                            <i class="bi bi-check-circle me-1"></i> Votre demande a &eacute;t&eacute; envoy&eacute;e.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="/m/<?= htmlspecialchars($moduleSlug ?? '') ?>/demander-acces" class="text-start">
                            <div class="mb-2">
                                <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">Utilisateur</label>
                                <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($username ?? '') ?>" readonly>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">Email</label>
                                <input type="email" class="form-control form-control-sm" value="<?= htmlspecialchars($email ?? '') ?>" readonly>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">Module demand&eacute;</label>
                                <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($moduleNom ?? '') ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="messageDemande" class="form-label" style="font-size: 0.85rem; font-weight: 600;">Message <span class="text-muted fw-normal">(optionnel)</span></label>
                                <textarea id="messageDemande" name="message" class="form-control form-control-sm" rows="3" placeholder="Expliquez pourquoi vous avez besoin de cet outil..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-send me-1"></i> Demander l'acc&egrave;s
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="mt-3">
                        <a href="/" class="text-decoration-none" style="font-size: 0.85rem;">
                            <i class="bi bi-arrow-left me-1"></i>Retour au dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
