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
                        <td style="background-color: #004c4c; padding: 20px 32px; border-bottom: 3px solid #fbb03b;">
                            <table width="100%" cellpadding="0" cellspacing="0"><tr>
                                <td style="width: 40px; vertical-align: middle;">
                                    <img src="<?= htmlspecialchars($_logoUrl ?? '') ?>" alt="Le lab" width="36" height="36" style="display: block; border-radius: 6px;">
                                </td>
                                <td style="vertical-align: middle; padding-left: 12px;">
                                    <span style="color: #ffffff; font-size: 22px; font-weight: 700; font-family: 'Poppins', Arial, sans-serif;">Le lab</span>
                                </td>
                            </tr></table>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 32px;">
                            <h2 style="color: #333333; font-size: 18px; font-weight: 700; margin: 0 0 16px;">Quota bientôt atteint</h2>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
                                Bonjour <strong><?= htmlspecialchars($username ?? '') ?></strong>,
                            </p>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
                                Vous avez utilisé <strong style="color: #fbb03b;"><?= (int) ($pourcentage ?? 0) ?>%</strong> de votre quota mensuel
                                pour le module <strong><?= htmlspecialchars($nomModule ?? '') ?></strong>.
                            </p>
                            <!-- Quota bar -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">
                                <tr>
                                    <td>
                                        <div style="background: #e2e8f0; border-radius: 8px; height: 12px; overflow: hidden;">
                                            <div style="background: #fbb03b; height: 12px; width: <?= min(100, (int) ($pourcentage ?? 0)) ?>%; border-radius: 8px;"></div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 4px;">
                                        <span style="color: #666; font-size: 12px;"><?= (int) ($usage ?? 0) ?> / <?= (int) ($limite ?? 0) ?> utilisations</span>
                                    </td>
                                </tr>
                            </table>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 24px;">
                                Vos quotas seront réinitialisés le <strong><?= htmlspecialchars($dateReset ?? '') ?></strong>.
                            </p>
                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="<?= htmlspecialchars($lienPlateforme ?? '') ?>"
                                           style="display: inline-block; background-color: #004c4c; color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 6px; font-size: 14px; font-weight: 600;">
                                            Voir mon compte
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
