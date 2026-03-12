<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f2f2f2; font-family: 'Poppins', Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f2f2f2; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="520" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,76,76,0.06);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #004c4c; padding: 14px 24px; border-bottom: 3px solid #fbb03b;">
                            <table width="100%" cellpadding="0" cellspacing="0"><tr>
                                <td style="width: 140px;">
                                    <img src="<?= htmlspecialchars($_logoUrl ?? '') ?>" alt="Le lab" width="160" style="display: block;">
                                </td>
                                <td style="text-align: center; color: #ffffff; font-family: 'Poppins', Arial, sans-serif; font-size: 22px; font-weight: 700; letter-spacing: 0.02em;">
                                    Le lab
                                </td>
                                <td style="width: 140px;"></td>
                            </tr></table>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 32px;">
                            <h2 style="color: #333333; font-size: 18px; font-weight: 700; margin: 0 0 16px;">Quotas réinitialisés</h2>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
                                Bonjour <strong><?= htmlspecialchars($username ?? '') ?></strong>,
                            </p>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
                                Vos quotas mensuels ont été réinitialisés. Voici le résumé de votre activité en <strong><?= htmlspecialchars($moisPrecedent ?? '') ?></strong> :
                            </p>
                            <!-- Modules summary table -->
                            <?php if (!empty($resumeModules)): ?>
                            <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse; margin: 0 0 24px;">
                                <thead>
                                    <tr>
                                        <th style="text-align: left; color: #666; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 8px; border-bottom: 2px solid #e2e8f0;">Module</th>
                                        <th style="text-align: center; color: #666; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 8px; border-bottom: 2px solid #e2e8f0;">Utilisé</th>
                                        <th style="text-align: center; color: #666; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 8px; border-bottom: 2px solid #e2e8f0;">Limite</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resumeModules as $module): ?>
                                    <?php
                                        $pourcent = $module['limite'] > 0 ? round(($module['usage'] / $module['limite']) * 100) : 0;
                                        $couleur = $pourcent >= 100 ? '#dc3545' : ($pourcent >= 80 ? '#fbb03b' : '#198754');
                                    ?>
                                    <tr>
                                        <td style="color: #333; font-size: 13px; padding: 8px; border-bottom: 1px solid #f1f5f9;"><?= htmlspecialchars($module['nom'] ?? '') ?></td>
                                        <td style="text-align: center; font-size: 13px; padding: 8px; border-bottom: 1px solid #f1f5f9; color: <?= $couleur ?>; font-weight: 600;"><?= (int) ($module['usage'] ?? 0) ?></td>
                                        <td style="text-align: center; color: #666; font-size: 13px; padding: 8px; border-bottom: 1px solid #f1f5f9;"><?= (int) ($module['limite'] ?? 0) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 24px;">
                                Tous vos compteurs sont maintenant remis à zéro. Bon mois !
                            </p>
                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="<?= htmlspecialchars($lienPlateforme ?? '') ?>"
                                           style="display: inline-block; background-color: #004c4c; color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 6px; font-size: 14px; font-weight: 600;">
                                            Accéder à la plateforme
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <?php require __DIR__ . '/_footer.php'; ?>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
