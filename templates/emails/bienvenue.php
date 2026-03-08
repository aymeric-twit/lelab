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
                                    <img src="<?= htmlspecialchars($_logoUrl ?? '') ?>" alt="Le lab" width="130" style="display: block;">
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
                            <h2 style="color: #333333; font-size: 18px; font-weight: 700; margin: 0 0 16px;">Bienvenue sur Le lab !</h2>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
                                Bonjour <strong><?= htmlspecialchars($username ?? '') ?></strong>,
                            </p>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
                                Votre compte a bien été activé. Vous pouvez dès maintenant accéder à tous les outils SEO de la plateforme.
                            </p>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 24px;">
                                Explorez les modules disponibles et commencez à optimiser votre référencement !
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
