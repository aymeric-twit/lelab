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
                        <td style="background-color: #004c4c; padding: 24px 32px; border-bottom: 3px solid #fbb03b; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 22px; font-weight: 700;">Le lab</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 32px;">
                            <h2 style="color: #333333; font-size: 18px; font-weight: 700; margin: 0 0 16px;">Mot de passe modifié</h2>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
                                Bonjour <strong><?= htmlspecialchars($username ?? '') ?></strong>,
                            </p>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
                                Votre mot de passe a été modifié avec succès le <strong><?= htmlspecialchars($dateChangement ?? '') ?></strong>.
                            </p>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
                                Adresse IP utilisée : <code style="background: #f2f2f2; padding: 2px 6px; border-radius: 4px; font-size: 13px;"><?= htmlspecialchars($ip ?? '') ?></code>
                            </p>
                            <p style="color: #999; font-size: 12px; line-height: 1.6; margin: 24px 0 0;">
                                Si vous n'êtes pas à l'origine de ce changement, contactez immédiatement l'administrateur de la plateforme.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 16px 32px; background-color: #f8f9fa; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="color: #999; font-size: 11px; margin: 0;">Le lab &mdash; Plateforme SEO</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
