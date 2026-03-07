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
                            <h1 style="color: #ffffff; margin: 0; font-size: 22px; font-weight: 700;">Le lab — Admin</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 32px;">
                            <h2 style="color: #333333; font-size: 18px; font-weight: 700; margin: 0 0 16px;">Nouvel utilisateur inscrit</h2>
                            <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
                                Un nouvel utilisateur vient de s'inscrire sur la plateforme :
                            </p>
                            <table width="100%" cellpadding="8" cellspacing="0" style="background: #f8f9fa; border-radius: 8px; margin: 0 0 16px;">
                                <tr>
                                    <td style="color: #666; font-size: 13px; font-weight: 600; width: 120px;">Nom d'utilisateur</td>
                                    <td style="color: #333; font-size: 14px;"><?= htmlspecialchars($username ?? '') ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #666; font-size: 13px; font-weight: 600;">Email</td>
                                    <td style="color: #333; font-size: 14px;"><?= htmlspecialchars($email ?? '') ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #666; font-size: 13px; font-weight: 600;">Date</td>
                                    <td style="color: #333; font-size: 14px;"><?= htmlspecialchars($dateInscription ?? '') ?></td>
                                </tr>
                            </table>
                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="<?= htmlspecialchars($lienAdmin ?? '') ?>"
                                           style="display: inline-block; background-color: #004c4c; color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 6px; font-size: 14px; font-weight: 600;">
                                            Gérer les utilisateurs
                                        </a>
                                    </td>
                                </tr>
                            </table>
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
