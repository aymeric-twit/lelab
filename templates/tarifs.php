<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarifs - Le lab</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/platform.css?v=<?= filemtime(__DIR__ . '/../public/assets/css/platform.css') ?>" rel="stylesheet">
    <style>
        .tarifs-hero {
            background: var(--brand-dark);
            color: #fff;
            padding: 3rem 0 2rem;
            border-bottom: 2.5px solid var(--brand-gold);
        }
        .tarifs-hero h1 { font-weight: 700; }
        .tarifs-hero p { color: rgba(255,255,255,0.8); }

        .plan-card {
            background: #fff;
            border-radius: 1rem;
            border: 1px solid rgb(226, 232, 240);
            box-shadow: rgba(0, 76, 76, 0.06) 0px 2px 8px, rgba(0, 0, 0, 0.04) 0px 1px 3px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            overflow: hidden;
        }
        .plan-card:hover {
            transform: translateY(-4px);
            box-shadow: rgba(0, 76, 76, 0.12) 0px 8px 24px, rgba(0, 0, 0, 0.06) 0px 2px 8px;
        }
        .plan-card.plan-recommande {
            border: 2px solid var(--brand-teal);
            position: relative;
        }
        .plan-badge-recommande {
            position: absolute;
            top: -1px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--brand-teal);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.25rem 1rem;
            border-radius: 0 0 6px 6px;
        }
        .plan-card-header {
            padding: 2rem 1.5rem 1rem;
            text-align: center;
        }
        .plan-nom {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--brand-anthracite);
        }
        .plan-prix {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--brand-dark);
            line-height: 1.2;
        }
        .plan-prix .devise { font-size: 1rem; vertical-align: super; }
        .plan-prix .periode { font-size: 0.875rem; font-weight: 400; color: #666; }
        .plan-credits {
            display: inline-block;
            background: var(--brand-teal-light);
            color: var(--brand-dark);
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            margin-top: 0.5rem;
        }
        .plan-card-body {
            padding: 1rem 1.5rem 1.5rem;
        }
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .plan-features li {
            padding: 0.4rem 0;
            font-size: 0.9rem;
            color: var(--brand-anthracite);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .plan-features li i {
            color: var(--brand-teal);
            font-size: 0.875rem;
        }
        .plan-card-footer {
            padding: 0 1.5rem 1.5rem;
        }

        .table-comparaison thead th {
            background: rgb(241, 245, 249);
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--brand-dark);
        }
        .table-comparaison td, .table-comparaison th {
            vertical-align: middle;
            font-size: 0.875rem;
        }

        body { background: var(--brand-linen); }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="tarifs-hero">
        <div class="container text-center">
            <h1>Nos tarifs</h1>
            <p class="lead mb-0">Choisissez le plan adapte a vos besoins SEO</p>
        </div>
    </div>

    <!-- Grille tarifaire -->
    <div class="container py-5">
        <?php if (empty($plans)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle me-2"></i>
                Aucun plan n'est encore configure. Revenez bientot !
            </div>
        <?php else: ?>
            <div class="row g-4 justify-content-center mb-5">
                <?php foreach ($plans as $index => $plan): ?>
                    <?php
                        $estRecommande = ($plan['slug'] ?? '') === 'pro';
                        $prix = $plan['prix_mensuel'] ?? 0;
                        $creditsMensuels = $plan['credits_mensuels'] ?? 0;
                        $description = $plan['description'] ?? '';
                        $limites = json_decode($plan['limites'] ?? '{}', true);
                        $modulesInclus = json_decode($plan['modules_inclus'] ?? '[]', true);
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="plan-card h-100 d-flex flex-column <?= $estRecommande ? 'plan-recommande' : '' ?>">
                            <?php if ($estRecommande): ?>
                                <span class="plan-badge-recommande">Recommande</span>
                            <?php endif; ?>

                            <div class="plan-card-header">
                                <div class="plan-nom"><?= htmlspecialchars($plan['nom']) ?></div>
                                <div class="plan-prix mt-2">
                                    <?php if ((float) $prix > 0): ?>
                                        <span class="devise">&euro;</span><?= number_format((float) $prix, 0, ',', ' ') ?>
                                        <span class="periode">/ mois</span>
                                    <?php else: ?>
                                        Gratuit
                                    <?php endif; ?>
                                </div>
                                <div class="plan-credits">
                                    <?php if ((int) $creditsMensuels === 0): ?>
                                        <i class="bi bi-infinity me-1"></i> Credits illimites
                                    <?php else: ?>
                                        <i class="bi bi-lightning-charge me-1"></i> <?= number_format((int) $creditsMensuels, 0, ',', ' ') ?> credits / mois
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="plan-card-body flex-grow-1">
                                <?php if (!empty($description)): ?>
                                    <p class="text-muted small mb-3"><?= htmlspecialchars($description) ?></p>
                                <?php endif; ?>

                                <ul class="plan-features">
                                    <?php if (is_array($modulesInclus) && in_array('*', $modulesInclus, true)): ?>
                                        <li><i class="bi bi-check-circle-fill"></i> Acces a tous les modules</li>
                                    <?php elseif (is_array($modulesInclus) && !empty($modulesInclus)): ?>
                                        <li><i class="bi bi-check-circle-fill"></i> <?= count($modulesInclus) ?> module(s) inclus</li>
                                    <?php else: ?>
                                        <li><i class="bi bi-check-circle-fill"></i> Modules de base</li>
                                    <?php endif; ?>

                                    <?php if (isset($limites['max_modules']) && $limites['max_modules'] !== null): ?>
                                        <li><i class="bi bi-check-circle-fill"></i> Jusqu'a <?= (int) $limites['max_modules'] ?> modules</li>
                                    <?php else: ?>
                                        <li><i class="bi bi-check-circle-fill"></i> Modules illimites</li>
                                    <?php endif; ?>

                                    <?php if ((float) $prix >= 99): ?>
                                        <li><i class="bi bi-check-circle-fill"></i> Support prioritaire</li>
                                        <li><i class="bi bi-check-circle-fill"></i> Acces API complet</li>
                                    <?php elseif ((float) $prix > 0): ?>
                                        <li><i class="bi bi-check-circle-fill"></i> Support email</li>
                                        <li><i class="bi bi-check-circle-fill"></i> Acces API</li>
                                    <?php else: ?>
                                        <li><i class="bi bi-check-circle-fill"></i> Support communautaire</li>
                                    <?php endif; ?>

                                    <li><i class="bi bi-check-circle-fill"></i> Historique des analyses</li>
                                    <li><i class="bi bi-check-circle-fill"></i> Export CSV</li>
                                </ul>
                            </div>

                            <div class="plan-card-footer">
                                <a href="/inscription" class="btn <?= $estRecommande ? 'btn-primary' : 'btn-outline-dark' ?> w-100">
                                    <?= (float) $prix > 0 ? 'Commencer' : 'S\'inscrire gratuitement' ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Tableau comparatif modules x plans -->
            <?php if (!empty($modules)): ?>
                <div class="card" style="border-radius: 1rem; border: 1px solid rgb(226, 232, 240); box-shadow: rgba(0, 76, 76, 0.06) 0px 2px 8px;">
                    <div class="card-header" style="background: linear-gradient(135deg, rgba(102, 178, 178, 0.04) 0%, transparent 60%); border-radius: 1rem 1rem 0 0;">
                        <h5 class="mb-0 fw-bold" style="color: var(--brand-anthracite);">
                            <i class="bi bi-table me-2" style="color: var(--brand-teal);"></i>
                            Comparatif par module
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-comparaison mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Module</th>
                                        <th class="text-center">Credits / analyse</th>
                                        <?php foreach ($plans as $plan): ?>
                                            <th class="text-center"><?= htmlspecialchars($plan['nom']) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($modules as $mod): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <?php if (!empty($mod['icon'])): ?>
                                                    <i class="<?= htmlspecialchars($mod['icon']) ?> me-1" style="color: var(--brand-teal);"></i>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($mod['name']) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark"><?= (int) $mod['credits_par_analyse'] ?></span>
                                            </td>
                                            <?php foreach ($plans as $plan): ?>
                                                <?php
                                                    $creditsPlan = (int) ($plan['credits_mensuels'] ?? 0);
                                                    $poidsModule = (int) $mod['credits_par_analyse'];
                                                    if ($creditsPlan === 0) {
                                                        $maxAnalyses = '&infin;';
                                                    } elseif ($poidsModule > 0) {
                                                        $maxAnalyses = (string) intdiv($creditsPlan, $poidsModule);
                                                    } else {
                                                        $maxAnalyses = '&infin;';
                                                    }
                                                ?>
                                                <td class="text-center fw-semibold">
                                                    <?= $maxAnalyses ?> <span class="text-muted fw-normal" style="font-size: 0.75rem;">analyses</span>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Lien retour -->
        <div class="text-center mt-4">
            <a href="/login" class="text-decoration-none" style="color: var(--brand-teal);">
                <i class="bi bi-arrow-left me-1"></i> Retour a la connexion
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
