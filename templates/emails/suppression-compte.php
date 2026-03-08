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
                            <h2 style="color: #333333; font-size: 18px; font-weight: 700; margin: 0 0 16px;">Compte supprimé</h2>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
                                Bonjour <strong><?= htmlspecialchars($username ?? '') ?></strong>,
                            </p>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
                                Votre compte a bien été supprimé le <strong><?= htmlspecialchars($dateEffective ?? '') ?></strong>.
                            </p>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
                                Toutes vos données personnelles ont été effacées conformément à notre politique de confidentialité.
                            </p>
                            <p style="color: #999; font-size: 12px; line-height: 1.6; margin: 24px 0 0;">
                                Si vous n'êtes pas à l'origine de cette suppression, contactez immédiatement l'administrateur.
                            </p>
                        </td>
                    </tr>
                    <?php require __DIR__ . '/_footer.php'; ?>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
