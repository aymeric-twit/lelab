<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Politique de confidentialité - Le lab</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/platform.css?v=1" rel="stylesheet">
    <style>
        body { background-color: #f2f2f2; font-family: 'Poppins', sans-serif; color: #333; }
        .legal-container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        .legal-header { background-color: #004c4c; padding: 24px 32px; border-bottom: 3px solid #fbb03b; border-radius: 1rem 1rem 0 0; text-align: center; }
        .legal-header h1 { color: #fff; font-size: 1.5rem; font-weight: 700; margin: 0; }
        .legal-body { background: #fff; padding: 40px; border-radius: 0 0 1rem 1rem; box-shadow: 0 2px 8px rgba(0,76,76,0.06); }
        .legal-body h2 { color: #004c4c; font-size: 1.15rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem; border-bottom: 2px solid #e8f4f4; padding-bottom: 0.5rem; }
        .legal-body h3 { color: #333; font-size: 1rem; font-weight: 600; margin-top: 1.5rem; }
        .legal-body p, .legal-body li { font-size: 0.9rem; line-height: 1.7; }
        .legal-body ul { padding-left: 1.5rem; }
        .legal-body a { color: #66b2b2; }
        .legal-footer { text-align: center; padding: 20px; }
        .legal-footer a { color: #66b2b2; text-decoration: none; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="legal-container">
        <div class="legal-header">
            <h1><i class="bi bi-shield-lock me-2"></i>Politique de confidentialité</h1>
        </div>
        <div class="legal-body">
            <p class="text-muted"><em>Dernière mise à jour : <?= date('d/m/Y') ?></em></p>

            <p>
                La présente politique de confidentialité décrit comment l'EURL Y'a pas de quoi
                (ci-après « nous », « notre » ou « Le lab ») collecte, utilise et protège vos données
                personnelles lorsque vous utilisez la plateforme <strong>Le lab</strong>, accessible à l'adresse
                <a href="https://labs.yapasdequoi.com">labs.yapasdequoi.com</a>.
            </p>

            <h2>1. Responsable du traitement</h2>
            <p>
                <strong>EURL Y'a pas de quoi</strong><br>
                Représentée par Aymeric Bouillat<br>
                58 rue de Monceau, CS 48759, 75380 Paris<br>
                SIRET : 981 817 158 00018<br>
                Contact DPO : <a href="mailto:rgpd@yapasdequoi.com">rgpd@yapasdequoi.com</a>
            </p>

            <h2>2. Données collectées</h2>
            <p>Nous collectons les données suivantes lors de votre utilisation de la plateforme :</p>
            <ul>
                <li><strong>Données d'inscription</strong> : nom d'utilisateur, adresse email, mot de passe (stocké sous forme hashée, jamais en clair)</li>
                <li><strong>Données de profil</strong> : nom de domaine (optionnel)</li>
                <li><strong>Données d'utilisation</strong> : quotas d'utilisation des modules, journaux d'audit (actions effectuées, adresse IP, horodatage)</li>
                <li><strong>Données techniques</strong> : journaux d'envoi d'emails (destinataire, sujet, statut, horodatage)</li>
            </ul>

            <h2>3. Finalités du traitement</h2>
            <p>Vos données sont traitées pour les finalités suivantes :</p>
            <ul>
                <li><strong>Exécution du contrat</strong> : création et gestion de votre compte, accès aux outils SEO, suivi des quotas d'utilisation</li>
                <li><strong>Intérêt légitime</strong> : sécurité de la plateforme (journaux d'audit, détection de fraude), amélioration du service</li>
                <li><strong>Consentement</strong> : envoi de notifications par email (vous pouvez vous désabonner à tout moment)</li>
            </ul>

            <h2>4. Base légale</h2>
            <ul>
                <li><strong>Article 6.1.b du RGPD</strong> : exécution du contrat (utilisation de la plateforme)</li>
                <li><strong>Article 6.1.f du RGPD</strong> : intérêt légitime (sécurité, logs)</li>
                <li><strong>Article 6.1.a du RGPD</strong> : consentement (notifications optionnelles)</li>
            </ul>

            <h2>5. Durée de conservation</h2>
            <ul>
                <li><strong>Données de compte</strong> : conservées jusqu'à la suppression du compte par l'utilisateur</li>
                <li><strong>Journaux d'audit</strong> : conservés pendant 1 an</li>
                <li><strong>Journaux d'emails</strong> : conservés pendant 6 mois</li>
                <li><strong>Données de session</strong> : durée de la session (cookie de session)</li>
            </ul>

            <h2>6. Cookies</h2>
            <p>
                La plateforme utilise uniquement des <strong>cookies de session</strong> strictement nécessaires au
                fonctionnement du service (authentification, protection CSRF). Aucun cookie de tracking, publicitaire
                ou analytique n'est utilisé.
            </p>
            <p>
                Ces cookies étant essentiels au fonctionnement du service, ils ne nécessitent pas votre consentement
                préalable (article 82 de la loi Informatique et Libertés).
            </p>

            <h2>7. Absence de tracking</h2>
            <p>
                Nous n'utilisons <strong>aucun outil de suivi</strong> (pas de Google Analytics, pas de pixels de tracking,
                pas de scripts tiers). Votre activité sur la plateforme n'est pas tracée à des fins publicitaires
                ou de profilage.
            </p>

            <h2>8. Partage des données</h2>
            <p>
                Vos données personnelles ne sont partagées avec aucun tiers, à l'exception de notre hébergeur
                technique qui assure le fonctionnement de la plateforme :
            </p>
            <ul>
                <li><strong>Gandi SAS</strong> — 63-65 Boulevard Masséna, 75013 Paris — hébergement web et base de données</li>
            </ul>
            <p>Aucun transfert de données en dehors de l'Union Européenne n'est effectué.</p>

            <h2>9. Sécurité</h2>
            <p>Nous mettons en œuvre les mesures suivantes pour protéger vos données :</p>
            <ul>
                <li>Mots de passe hashés (bcrypt)</li>
                <li>Protection CSRF sur tous les formulaires</li>
                <li>Connexion HTTPS obligatoire</li>
                <li>Limitation de tentatives de connexion (rate limiting)</li>
                <li>Journalisation des accès et actions sensibles</li>
            </ul>

            <h2>10. Vos droits</h2>
            <p>Conformément au RGPD, vous disposez des droits suivants :</p>
            <ul>
                <li><strong>Droit d'accès</strong> : obtenir une copie de vos données personnelles</li>
                <li><strong>Droit de rectification</strong> : modifier vos données depuis votre page <a href="/mon-compte">Mon compte</a></li>
                <li><strong>Droit de suppression</strong> : supprimer votre compte et toutes vos données depuis votre page <a href="/mon-compte">Mon compte</a></li>
                <li><strong>Droit à la portabilité</strong> : recevoir vos données dans un format structuré</li>
                <li><strong>Droit d'opposition</strong> : vous opposer au traitement de vos données</li>
                <li><strong>Droit à la limitation</strong> : limiter le traitement de vos données</li>
                <li><strong>Droit de retrait du consentement</strong> : vous désabonner des notifications via le lien dans chaque email</li>
            </ul>
            <p>
                Pour exercer ces droits, contactez-nous à <a href="mailto:rgpd@yapasdequoi.com">rgpd@yapasdequoi.com</a>.
                Nous nous engageons à répondre dans un délai de 30 jours.
            </p>

            <h2>11. Suppression de compte</h2>
            <p>
                Vous pouvez supprimer votre compte à tout moment depuis la page
                <a href="/mon-compte">Mon compte</a>. La suppression entraîne :
            </p>
            <ul>
                <li>La désactivation immédiate de votre accès</li>
                <li>La suppression de vos tokens d'authentification, quotas et droits d'accès</li>
                <li>L'envoi d'un email de confirmation de suppression</li>
            </ul>

            <h2>12. Réclamation</h2>
            <p>
                Si vous estimez que vos droits ne sont pas respectés, vous pouvez introduire une réclamation
                auprès de la <strong>CNIL</strong> (Commission Nationale de l'Informatique et des Libertés) :
            </p>
            <p>
                CNIL — 3 Place de Fontenoy, TSA 80715, 75334 Paris Cedex 07<br>
                <a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>
            </p>

            <h2>13. Modifications</h2>
            <p>
                Nous nous réservons le droit de modifier cette politique de confidentialité à tout moment.
                En cas de modification substantielle, les utilisateurs seront informés par email.
                La date de dernière mise à jour est indiquée en haut de cette page.
            </p>
        </div>

        <div class="legal-footer">
            <a href="/mentions-legales">Mentions légales</a>
            <span class="mx-2 text-muted">&bull;</span>
            <a href="/login">Connexion</a>
            <span class="mx-2 text-muted">&bull;</span>
            <a href="/">Accueil</a>
        </div>
    </div>
</body>
</html>
