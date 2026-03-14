<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\Platform\Http\Csrf::token()) ?>">
    <title>Bienvenue - Le lab</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/platform.css?v=<?= filemtime(__DIR__ . '/../public/assets/css/platform.css') ?>" rel="stylesheet">
    <style>
        body { background: var(--brand-linen); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .onboarding-card { max-width: 640px; width: 100%; }
        .step { display: none; }
        .step.active { display: block; }
        .plan-option { cursor: pointer; transition: border-color 0.2s, box-shadow 0.2s; }
        .plan-option:hover, .plan-option.selected { border-color: var(--brand-teal); box-shadow: 0 0 0 2px rgba(102,178,178,0.3); }
        .plan-option.selected { background: var(--brand-teal-light); }
        .progress-steps { display: flex; gap: 8px; justify-content: center; margin-bottom: 2rem; }
        .progress-step { width: 40px; height: 4px; border-radius: 2px; background: #ddd; transition: background 0.3s; }
        .progress-step.active { background: var(--brand-dark); }
        .progress-step.done { background: var(--brand-teal); }
    </style>
</head>
<body>
    <div class="onboarding-card">
        <div class="card" style="border-radius: 1rem; box-shadow: rgba(0, 76, 76, 0.08) 0px 4px 16px;">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <img src="/assets/img/logo.png" alt="Le lab" style="height: 48px;" class="mb-3">
                    <h3 style="color: var(--brand-dark); font-weight: 700;">Bienvenue, <?= htmlspecialchars($currentUser['username'] ?? '') ?> !</h3>
                    <p class="text-muted">Configurons votre espace en quelques étapes.</p>
                </div>

                <div class="progress-steps" id="progressSteps">
                    <div class="progress-step active" data-step="1"></div>
                    <div class="progress-step" data-step="2"></div>
                    <div class="progress-step" data-step="3"></div>
                </div>

                <form method="POST" action="/onboarding" id="onboardingForm">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Platform\Http\Csrf::token()) ?>">

                    <!-- Étape 1 : Domaine -->
                    <div class="step active" id="step1">
                        <h5 style="color: var(--brand-dark); font-weight: 600;" class="mb-3">
                            <i class="bi bi-globe me-2"></i>Votre domaine principal
                        </h5>
                        <p class="text-muted" style="font-size: 0.9rem;">
                            Ce domaine sera pré-rempli dans les outils SEO pour vous faire gagner du temps.
                        </p>
                        <div class="mb-4">
                            <input type="text" name="domaine" id="domaineInput" class="form-control form-control-lg"
                                   placeholder="exemple.com" value="<?= htmlspecialchars($currentUser['domaine'] ?? '') ?>">
                            <div class="form-text">Vous pourrez le modifier plus tard dans Mon compte.</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="/" class="btn btn-link text-muted">Passer</a>
                            <button type="button" class="btn btn-primary" onclick="allerEtape(2)">
                                Continuer <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Étape 2 : Plan (optionnel) -->
                    <div class="step" id="step2">
                        <h5 style="color: var(--brand-dark); font-weight: 600;" class="mb-3">
                            <i class="bi bi-rocket-takeoff me-2"></i>Choisir un plan
                        </h5>
                        <input type="hidden" name="plan_id" id="planIdInput" value="">

                        <?php if (!empty($plans)): ?>
                        <div class="row g-3 mb-4">
                            <?php foreach ($plans as $plan): ?>
                            <div class="col-md-4">
                                <div class="card plan-option p-3 h-100" data-plan-id="<?= (int) $plan['id'] ?>"
                                     onclick="choisirPlan(<?= (int) $plan['id'] ?>, this)">
                                    <h6 class="mb-1" style="font-weight: 700; color: var(--brand-dark);">
                                        <?= htmlspecialchars($plan['nom']) ?>
                                    </h6>
                                    <div class="mb-2" style="font-size: 1.25rem; font-weight: 700; color: var(--brand-teal);">
                                        <?php if ((float) $plan['prix_mensuel'] > 0): ?>
                                            <?= number_format((float) $plan['prix_mensuel'], 0, ',', ' ') ?>&euro;/mois
                                        <?php else: ?>
                                            Gratuit
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted mb-0" style="font-size: 0.8rem;">
                                        <?= htmlspecialchars($plan['description'] ?? '') ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-muted mb-4">Les plans seront bientôt disponibles.</p>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="allerEtape(1)">
                                <i class="bi bi-arrow-left me-1"></i> Retour
                            </button>
                            <button type="button" class="btn btn-primary" onclick="allerEtape(3)">
                                Continuer <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Étape 3 : Découvrir les outils -->
                    <div class="step" id="step3">
                        <h5 style="color: var(--brand-dark); font-weight: 600;" class="mb-3">
                            <i class="bi bi-tools me-2"></i>Vos outils disponibles
                        </h5>
                        <p class="text-muted" style="font-size: 0.9rem;">
                            Voici les modules SEO auxquels vous avez accès :
                        </p>

                        <div class="row g-2 mb-4" style="max-height: 280px; overflow-y: auto;">
                            <?php foreach ($modules as $mod): ?>
                            <div class="col-6">
                                <div class="d-flex align-items-center gap-2 p-2 rounded" style="background: var(--brand-teal-light);">
                                    <i class="bi <?= htmlspecialchars($mod['icon'] ?? 'bi-tools') ?>" style="color: var(--brand-dark); font-size: 1.2rem;"></i>
                                    <span style="font-size: 0.85rem; font-weight: 500;"><?= htmlspecialchars($mod['name']) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($modules)): ?>
                            <div class="col-12">
                                <p class="text-muted">Aucun module disponible pour l'instant. Contactez l'administrateur.</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="allerEtape(2)">
                                <i class="bi bi-arrow-left me-1"></i> Retour
                            </button>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-lg me-1"></i> C'est parti !
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    var etapeActuelle = 1;

    function allerEtape(n) {
        document.getElementById('step' + etapeActuelle).classList.remove('active');
        document.getElementById('step' + n).classList.add('active');

        document.querySelectorAll('.progress-step').forEach(function(el) {
            var step = parseInt(el.dataset.step);
            el.classList.remove('active', 'done');
            if (step === n) el.classList.add('active');
            else if (step < n) el.classList.add('done');
        });

        etapeActuelle = n;
    }

    function choisirPlan(planId, el) {
        document.querySelectorAll('.plan-option').forEach(function(c) { c.classList.remove('selected'); });
        el.classList.add('selected');
        document.getElementById('planIdInput').value = planId;
    }
    </script>
</body>
</html>
