<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use Platform\Enum\TypeNotification;
use Platform\Http\Csrf;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Module\ApiCreditsTracker;
use Platform\Module\Quota;
use Platform\Repository\SettingsRepository;
use Platform\Service\AuditLogger;
use Platform\Service\EmailTemplate;
use Platform\Service\Mailer;
use Platform\Service\PluginApiCreditsService;
use Platform\Service\PluginEnvService;
use Platform\Service\WebhookDispatcher;
use Platform\User\AccessControl;
use Platform\Validation\Validator;
use Platform\View\Flash;
use Platform\View\Layout;

class AdminConfigController
{
    /**
     * GET /admin/configuration — Page de configuration centralisee avec onglets.
     */
    public function index(Request $req): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $db = Connection::get();
        $settings = new SettingsRepository($db);

        $onglet = $req->get('onglet', 'cles-api');

        // Donnees communes
        $donnees = [
            'template'          => 'admin/configuration',
            'pageTitle'         => 'Configuration',
            'currentUser'       => $user,
            'accessibleModules' => $ac->getAccessibleModules($user['id']),
            'adminPage'         => 'configuration',
            'onglet'            => $onglet,
            'csrfToken'         => Csrf::token(),
        ];

        // Chargement conditionnel par onglet
        if ($onglet === 'cles-api') {
            $donnees = array_merge($donnees, $this->chargerDonneesClesApi($db, $settings));
        } elseif ($onglet === 'google') {
            $donnees = array_merge($donnees, $this->chargerDonneesGoogle());
        } elseif ($onglet === 'smtp') {
            $donnees = array_merge($donnees, $this->chargerDonneesSmtp($settings));
        } elseif ($onglet === 'notifications') {
            $donnees = array_merge($donnees, $this->chargerDonneesNotifications($settings));
        } elseif ($onglet === 'securite') {
            $donnees['securite'] = $settings->obtenirGroupe('securite');
        } elseif ($onglet === 'general') {
            $donnees['general'] = $settings->obtenirGroupe('general');
        } elseif ($onglet === 'webhooks') {
            try {
                $dispatcher = new WebhookDispatcher($db);
                $donnees['webhooks'] = $dispatcher->listerTous();
            } catch (\PDOException) {
                $donnees['webhooks'] = [];
            }
            $donnees['evenementsDisponibles'] = WebhookDispatcher::EVENEMENTS;
        } elseif ($onglet === 'api') {
            $donnees = array_merge($donnees, $this->chargerDonneesApiKeys($db));
        }

        Layout::render('layout', $donnees);
    }

    // -----------------------------------------------
    // Chargement des donnees par onglet
    // -----------------------------------------------

    /**
     * Charge les donnees pour l'onglet cles-api (meme logique que AdminPluginController::index).
     *
     * @return array<string, mixed>
     */
    private function chargerDonneesClesApi(\PDO $db, SettingsRepository $settings): array
    {
        $modules = $db->query(
            'SELECT m.*, c.nom AS categorie_nom
             FROM modules m
             LEFT JOIN categories c ON c.id = m.categorie_id
             WHERE m.desinstalle_le IS NULL
             ORDER BY m.sort_order'
        )->fetchAll();

        // Enrichir chaque module avec le statut des cles d'environnement
        foreach ($modules as &$mod) {
            $clesEnv = !empty($mod['cles_env']) ? json_decode($mod['cles_env'], true) : [];
            $mod['_cles_env_liste'] = is_array($clesEnv) ? $clesEnv : [];
            $mod['_api_doc_url'] = '';
            $cheminSource = $mod['chemin_source'] ?? '';
            if ($cheminSource !== '') {
                $cheminModuleJson = $cheminSource . '/module.json';
                if (file_exists($cheminModuleJson)) {
                    $moduleJsonData = json_decode(file_get_contents($cheminModuleJson), true);
                    $mod['_api_doc_url'] = $moduleJsonData['api_doc_url'] ?? '';
                }
            }
        }
        unset($mod);

        // Charger l'usage depuis api_credits_usage
        $usageApiCredits = [];
        try {
            $stmtApiUsage = $db->query('SELECT cle_api, periode_id, usage_count FROM api_credits_usage');
            foreach ($stmtApiUsage->fetchAll() as $row) {
                $usageApiCredits[$row['cle_api']][$row['periode_id']] = (int) $row['usage_count'];
            }
        } catch (\PDOException) {
        }

        // Fallback : charger module_usage
        $moisCourant = date('Ym');
        $moisPrecedent = date('Ym', strtotime('first day of last month'));
        $usageParModulePeriode = [];
        try {
            $stmtUsage = $db->prepare(
                'SELECT module_id, year_month, SUM(usage_count) AS total FROM module_usage WHERE year_month IN (?, ?) GROUP BY module_id, year_month'
            );
            $stmtUsage->execute([$moisCourant, $moisPrecedent]);
            foreach ($stmtUsage->fetchAll() as $row) {
                $usageParModulePeriode[(int) $row['module_id']][$row['year_month']] = (int) $row['total'];
            }
        } catch (\PDOException) {
        }

        // Charger la config credits manuels depuis settings
        $creditsConfig = [];
        try {
            $creditsConfigBrut = $settings->obtenirGroupe('api_credits');
            foreach ($creditsConfigBrut as $cleApi => $jsonVal) {
                $decoded = json_decode($jsonVal, true);
                if (is_array($decoded)) {
                    $creditsConfig[$cleApi] = $decoded;
                }
            }
        } catch (\PDOException) {
        }

        $clesApiGroupees = [];
        foreach ($modules as $mod) {
            if (empty($mod['_cles_env_liste'])) {
                continue;
            }
            foreach ($mod['_cles_env_liste'] as $cle) {
                if (!isset($clesApiGroupees[$cle])) {
                    $valeur = array_key_exists($cle, $_ENV) ? (string) $_ENV[$cle] : '';
                    if ($valeur === '') {
                        $envGetenv = getenv($cle);
                        $valeur = $envGetenv !== false ? $envGetenv : '';
                    }
                    $dateDebut = $creditsConfig[$cle]['date_debut'] ?? null;
                    $jourDebut = $dateDebut !== null ? (int) date('j', strtotime($dateDebut)) : 1;
                    $periode = $creditsConfig[$cle]['periode'] ?? 'mensuel';
                    $periodeActive = Quota::currentPeriod($jourDebut);
                    $prochainReset = $periode === 'hebdomadaire'
                        ? PluginApiCreditsService::calculerProchainResetHebdo()
                        : PluginApiCreditsService::calculerProchainReset($jourDebut);

                    $clesApiGroupees[$cle] = [
                        'cle'              => $cle,
                        'valeur'           => $valeur,
                        'presente'         => $valeur !== '',
                        'modules'          => [],
                        'usage_mois'       => 0,
                        'credits_mensuels' => $creditsConfig[$cle]['credits_mensuels'] ?? null,
                        'date_debut'       => $dateDebut,
                        'jour_debut'       => $jourDebut,
                        'periode'          => $periode,
                        'periode_active'   => $periodeActive,
                        'prochain_reset'   => $prochainReset,
                        'commentaire'      => $creditsConfig[$cle]['commentaire'] ?? '',
                        'api_doc_url'      => $mod['_api_doc_url'] ?? '',
                    ];
                }
                $moduleId = (int) $mod['id'];
                $clesApiGroupees[$cle]['modules'][] = [
                    'id'   => $moduleId,
                    'name' => $mod['name'],
                    'icon' => $mod['icon'] ?? 'bi-tools',
                    'slug' => $mod['slug'],
                ];
                if (empty($clesApiGroupees[$cle]['api_doc_url']) && !empty($mod['_api_doc_url'])) {
                    $clesApiGroupees[$cle]['api_doc_url'] = $mod['_api_doc_url'];
                }
            }
        }

        // Calculer usage_mois pour chaque cle API
        foreach ($clesApiGroupees as $cle => &$infoCle) {
            $periodeId = $infoCle['periode'] === 'hebdomadaire'
                ? ApiCreditsTracker::currentWeekPeriod()
                : Quota::currentPeriod($infoCle['jour_debut']);

            if (isset($usageApiCredits[$cle][$periodeId])) {
                $infoCle['usage_mois'] = $usageApiCredits[$cle][$periodeId];
            } else {
                $periodeActive = Quota::currentPeriod($infoCle['jour_debut']);
                foreach ($infoCle['modules'] as $modInfo) {
                    $infoCle['usage_mois'] += $usageParModulePeriode[$modInfo['id']][$periodeActive] ?? 0;
                }
            }
        }
        unset($infoCle);

        return ['clesApiGroupees' => $clesApiGroupees];
    }

    /**
     * Charge les valeurs Google depuis l'environnement.
     *
     * @return array<string, mixed>
     */
    private function chargerDonneesGoogle(): array
    {
        $cles = [
            'GOOGLE_CLIENT_ID',
            'GOOGLE_CLIENT_SECRET',
            'GOOGLE_REDIRECT_URI',
            'GSC_DB_HOST',
            'GSC_DB_NAME',
            'GSC_DB_USER',
            'GSC_DB_PASSWORD',
        ];

        $envService = new PluginEnvService(Connection::get());
        $google = [];
        foreach ($cles as $cle) {
            $google[$cle] = $envService->resoudreCle($cle);
        }

        return ['google' => $google];
    }

    /**
     * Charge les donnees SMTP.
     *
     * @return array<string, mixed>
     */
    private function chargerDonneesSmtp(SettingsRepository $settings): array
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';

        return [
            'smtpDb'       => $settings->obtenirGroupe('smtp'),
            'smtpEnv'      => $config['email'] ?? [],
            'smtpEffectif' => Mailer::configEffective(),
        ];
    }

    /**
     * Charge les donnees de notifications.
     *
     * @return array<string, mixed>
     */
    private function chargerDonneesNotifications(SettingsRepository $settings): array
    {
        return [
            'notifDb'  => $settings->obtenirGroupe('notifications'),
            'sujetsDb' => $settings->obtenirGroupe('email_sujets'),
            'types'    => TypeNotification::cases(),
        ];
    }

    /**
     * Charge les cles API de la plateforme (table api_keys).
     *
     * @return array<string, mixed>
     */
    private function chargerDonneesApiKeys(\PDO $db): array
    {
        $apiKeys = [];
        try {
            $apiKeys = $db->query(
                'SELECT ak.*, u.username FROM api_keys ak
                 LEFT JOIN users u ON u.id = ak.user_id
                 ORDER BY ak.created_at DESC'
            )->fetchAll();
        } catch (\PDOException) {
        }

        $utilisateurs = [];
        try {
            $utilisateurs = $db->query('SELECT id, username, email FROM users WHERE active = 1 AND deleted_at IS NULL ORDER BY username')->fetchAll();
        } catch (\PDOException) {
        }

        return [
            'apiKeys'      => $apiKeys,
            'utilisateurs' => $utilisateurs,
        ];
    }

    // -----------------------------------------------
    // Actions de sauvegarde
    // -----------------------------------------------

    /**
     * POST /admin/configuration/google — Sauvegarde les cles Google dans le .env.
     */
    public function sauvegarderGoogle(Request $req): void
    {
        Csrf::validateOrAbort();

        $envService = new PluginEnvService(Connection::get());

        $cles = [
            'GOOGLE_CLIENT_ID',
            'GOOGLE_CLIENT_SECRET',
            'GOOGLE_REDIRECT_URI',
            'GSC_DB_HOST',
            'GSC_DB_NAME',
            'GSC_DB_USER',
            'GSC_DB_PASSWORD',
        ];

        $envPath = dirname(__DIR__, 2) . '/.env';

        foreach ($cles as $cle) {
            $valeur = trim($req->post($cle, ''));
            if ($valeur !== '') {
                $this->ecrireDansEnv($envPath, $cle, $valeur);
                $_ENV[$cle] = $valeur;
                putenv("{$cle}={$valeur}");
            }
        }

        AuditLogger::instance()->log(
            AuditAction::EmailConfigUpdate,
            $req->ipAnonymisee(),
            Auth::id(),
            'settings',
            null,
            ['action' => 'google_config_update']
        );

        Flash::success('Configuration Google enregistree.');
        Response::redirect('/admin/configuration?onglet=google');
    }

    /**
     * POST /admin/configuration/smtp — Sauvegarde la configuration SMTP.
     */
    public function sauvegarderSmtp(Request $req): void
    {
        Csrf::validateOrAbort();

        $settings = new SettingsRepository(Connection::get());

        $donnees = [
            'host'       => trim($req->post('host', '')),
            'port'       => trim($req->post('port', '')),
            'username'   => trim($req->post('username', '')),
            'encryption' => trim($req->post('encryption', '')),
            'from'       => trim($req->post('from', '')),
            'from_name'  => trim($req->post('from_name', '')),
        ];

        $password = $req->post('password', '');
        if ($password !== '') {
            $donnees['password'] = $password;
        }

        $validator = new Validator();
        $regles = [
            'host' => 'requis',
            'port' => 'requis',
            'from' => 'requis|email',
        ];

        if (!$validator->valider($donnees, $regles)) {
            Flash::error($validator->premiereErreur());
            Response::redirect('/admin/configuration?onglet=smtp');
        }

        if (!in_array($donnees['encryption'], ['tls', 'ssl', 'none', ''], true)) {
            Flash::error('Chiffrement invalide.');
            Response::redirect('/admin/configuration?onglet=smtp');
        }

        $settings->definirGroupe('smtp', $donnees);
        Mailer::resetInstance();

        AuditLogger::instance()->log(AuditAction::EmailConfigUpdate, $req->ipAnonymisee(), Auth::id(), 'settings');

        Flash::success('Configuration SMTP enregistree.');
        Response::redirect('/admin/configuration?onglet=smtp');
    }

    /**
     * POST /admin/configuration/smtp/test — Envoie un email de test (AJAX).
     */
    public function envoyerTestSmtp(Request $req): void
    {
        $user = Auth::user();
        $destinataire = $user['email'] ?? '';

        if (empty($destinataire)) {
            Response::json(['ok' => false, 'message' => 'Aucune adresse email configuree sur votre compte.'], 400);
        }

        Mailer::resetInstance();

        $contenu = EmailTemplate::rendre('verification-email', [
            'username'   => $user['username'],
            'lien'       => '#',
            'expiration' => 24,
        ]);

        $succes = Mailer::instance()->envoyer(
            $destinataire,
            'Test email — Le lab',
            $contenu,
            'test',
        );

        if ($succes) {
            Response::json(['ok' => true, 'message' => "Email de test envoye a {$destinataire}."]);
        } else {
            Response::json(['ok' => false, 'message' => 'Echec de l\'envoi. Verifiez les parametres SMTP et les logs.'], 500);
        }
    }

    /**
     * POST /admin/configuration/notifications — Sauvegarde la config des notifications.
     */
    public function sauvegarderNotifications(Request $req): void
    {
        Csrf::validateOrAbort();

        $settings = new SettingsRepository(Connection::get());

        $adminEmail = trim($req->post('admin_email', ''));
        $seuilQuota = (int) $req->post('quota_seuil_alerte', '80');

        if ($seuilQuota < 50 || $seuilQuota > 99) {
            Flash::error('Le seuil d\'alerte quota doit etre entre 50 et 99%.');
            Response::redirect('/admin/configuration?onglet=notifications');
        }

        $settings->definir('notifications', 'admin_email', $adminEmail);
        $settings->definir('notifications', 'quota_seuil_alerte', (string) $seuilQuota);

        foreach (TypeNotification::cases() as $type) {
            $actif = $req->post($type->value . '_active') !== null ? '1' : '0';
            $settings->definir('notifications', $type->value . '_active', $actif);
        }

        foreach (TypeNotification::cases() as $type) {
            $sujet = trim($req->post('sujet_' . $type->value, ''));
            if ($sujet !== '') {
                $settings->definir('email_sujets', $type->value, mb_substr($sujet, 0, 200));
            } else {
                $settings->supprimer('email_sujets', $type->value);
            }
        }

        AuditLogger::instance()->log(AuditAction::EmailConfigUpdate, $req->ipAnonymisee(), Auth::id(), 'settings');

        Flash::success('Configuration des notifications enregistree.');
        Response::redirect('/admin/configuration?onglet=notifications');
    }

    /**
     * POST /admin/configuration/securite — Sauvegarde les parametres de securite.
     */
    public function sauvegarderSecurite(Request $req): void
    {
        Csrf::validateOrAbort();

        $settings = new SettingsRepository(Connection::get());

        $donnees = [
            'login_max_tentatives'  => (string) max(1, (int) $req->post('login_max_tentatives', '5')),
            'login_fenetre'         => (string) max(60, (int) $req->post('login_fenetre', '900')),
            'session_duree'         => (string) max(5, (int) $req->post('session_duree', '120')),
            'session_regeneration'  => (string) max(60, (int) $req->post('session_regeneration', '300')),
            'forcer_2fa'            => $req->post('forcer_2fa') !== null ? '1' : '0',
            'mot_de_passe_min'      => (string) max(6, (int) $req->post('mot_de_passe_min', '8')),
            'inscription_active'    => $req->post('inscription_active') !== null ? '1' : '0',
        ];

        $settings->definirGroupe('securite', $donnees);

        AuditLogger::instance()->log(AuditAction::EmailConfigUpdate, $req->ipAnonymisee(), Auth::id(), 'settings', null, ['action' => 'securite_update']);

        Flash::success('Parametres de securite enregistres.');
        Response::redirect('/admin/configuration?onglet=securite');
    }

    /**
     * POST /admin/configuration/general — Sauvegarde les parametres generaux.
     */
    public function sauvegarderGeneral(Request $req): void
    {
        Csrf::validateOrAbort();

        $settings = new SettingsRepository(Connection::get());

        $validator = new Validator();
        $donneesPost = [
            'app_name' => trim($req->post('app_name', '')),
            'app_url'  => trim($req->post('app_url', '')),
        ];

        if (!$validator->valider($donneesPost, ['app_name' => 'requis|min:2|max:100'])) {
            Flash::error($validator->premiereErreur());
            Response::redirect('/admin/configuration?onglet=general');
        }

        $donnees = [
            'app_name'    => trim($req->post('app_name', '')),
            'app_url'     => trim($req->post('app_url', '')),
            'timezone'    => trim($req->post('timezone', 'Europe/Paris')),
            'langue'      => trim($req->post('langue', 'fr')),
            'footer_text' => trim($req->post('footer_text', '')),
        ];

        $settings->definirGroupe('general', $donnees);

        AuditLogger::instance()->log(AuditAction::EmailConfigUpdate, $req->ipAnonymisee(), Auth::id(), 'settings', null, ['action' => 'general_update']);

        Flash::success('Parametres generaux enregistres.');
        Response::redirect('/admin/configuration?onglet=general');
    }

    // -----------------------------------------------
    // Webhooks
    // -----------------------------------------------

    /**
     * POST /admin/configuration/webhooks — Cree un nouveau webhook.
     */
    public function creerWebhook(Request $req): void
    {
        Csrf::validateOrAbort();

        $validator = new Validator();
        $donnees = [
            'nom' => trim($req->post('nom', '')),
            'url' => trim($req->post('url', '')),
        ];

        if (!$validator->valider($donnees, ['nom' => 'requis|min:2|max:100', 'url' => 'requis|url'])) {
            Flash::error($validator->premiereErreur());
            Response::redirect('/admin/configuration?onglet=webhooks');
        }

        $evenements = $req->post('evenements', []);
        if (!is_array($evenements)) {
            $evenements = [];
        }

        $dispatcher = new WebhookDispatcher(Connection::get());
        $dispatcher->creer([
            'nom'        => $donnees['nom'],
            'url'        => $donnees['url'],
            'secret'     => trim($req->post('secret', '')),
            'evenements' => $evenements,
            'actif'      => $req->post('actif') !== null ? 1 : 0,
            'created_by' => Auth::id(),
        ]);

        Flash::success('Webhook cree.');
        Response::redirect('/admin/configuration?onglet=webhooks');
    }

    /**
     * POST /admin/configuration/webhooks/{id}/editer — Met a jour un webhook.
     */
    public function editerWebhook(Request $req, array $params): void
    {
        Csrf::validateOrAbort();

        $id = (int) ($params['id'] ?? 0);
        $dispatcher = new WebhookDispatcher(Connection::get());

        $webhook = $dispatcher->parId($id);
        if (!$webhook) {
            Response::abort(404);
        }

        $evenements = $req->post('evenements', []);
        if (!is_array($evenements)) {
            $evenements = [];
        }

        $donnees = [
            'nom'        => trim($req->post('nom', '')),
            'url'        => trim($req->post('url', '')),
            'evenements' => $evenements,
            'actif'      => $req->post('actif') !== null ? 1 : 0,
        ];

        $secret = trim($req->post('secret', ''));
        if ($secret !== '') {
            $donnees['secret'] = $secret;
        }

        $dispatcher->mettreAJour($id, $donnees);

        Flash::success('Webhook mis a jour.');
        Response::redirect('/admin/configuration?onglet=webhooks');
    }

    /**
     * POST /admin/configuration/webhooks/{id}/supprimer — Supprime un webhook.
     */
    public function supprimerWebhook(Request $req, array $params): void
    {
        Csrf::validateOrAbort();

        $id = (int) ($params['id'] ?? 0);
        $dispatcher = new WebhookDispatcher(Connection::get());
        $dispatcher->supprimer($id);

        Flash::success('Webhook supprime.');
        Response::redirect('/admin/configuration?onglet=webhooks');
    }

    /**
     * POST /admin/configuration/webhooks/{id}/tester — Envoie un evenement test.ping.
     */
    public function testerWebhook(Request $req, array $params): void
    {
        Csrf::validateOrAbort();

        $id = (int) ($params['id'] ?? 0);
        $dispatcher = new WebhookDispatcher(Connection::get());

        $webhook = $dispatcher->parId($id);
        if (!$webhook) {
            Response::abort(404);
        }

        $dispatcher->declencher('test.ping', [
            'webhook_id' => $id,
            'timestamp'  => date('c'),
            'message'    => 'Ceci est un test depuis la plateforme SEO.',
        ]);

        Flash::success('Evenement test.ping envoye au webhook "' . htmlspecialchars($webhook['nom']) . '".');
        Response::redirect('/admin/configuration?onglet=webhooks');
    }

    // -----------------------------------------------
    // Cles API de la plateforme
    // -----------------------------------------------

    /**
     * POST /admin/configuration/api-keys — Cree une cle API pour la plateforme.
     */
    public function creerApiKey(Request $req): void
    {
        Csrf::validateOrAbort();

        $db = Connection::get();

        $nom = trim($req->post('nom', ''));
        $userId = $req->post('user_id', '') !== '' ? (int) $req->post('user_id', '') : Auth::id();
        $expiration = trim($req->post('expiration', ''));

        if ($nom === '') {
            Flash::error('Le nom de la cle est requis.');
            Response::redirect('/admin/configuration?onglet=api');
        }

        // Generer une cle aleatoire de 48 caracteres avec prefixe sk_live_
        $octetsAleatoires = bin2hex(random_bytes(24));
        $cleComplete = 'sk_live_' . $octetsAleatoires;
        $hash = hash('sha256', $cleComplete);
        $prefixe = substr($cleComplete, 0, 12);

        $dateExpiration = $expiration !== '' ? $expiration : null;

        try {
            $stmt = $db->prepare(
                'INSERT INTO api_keys (nom, prefixe, cle_hash, user_id, date_expiration, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$nom, $prefixe, $hash, $userId, $dateExpiration]);

            AuditLogger::instance()->log(
                AuditAction::EmailConfigUpdate,
                $req->ipAnonymisee(),
                Auth::id(),
                'api_keys',
                (int) $db->lastInsertId(),
                ['action' => 'api_key_created', 'nom' => $nom]
            );

            // Afficher la cle une seule fois
            Flash::success("Cle API creee avec succes. Copiez-la maintenant, elle ne sera plus affichee : <code>{$cleComplete}</code>");
        } catch (\PDOException $e) {
            Flash::error('Erreur lors de la creation de la cle : ' . $e->getMessage());
        }

        Response::redirect('/admin/configuration?onglet=api');
    }

    /**
     * POST /admin/configuration/api-keys/{id}/revoquer — Revoque une cle API.
     */
    public function revoquerApiKey(Request $req, array $params): void
    {
        Csrf::validateOrAbort();

        $id = (int) ($params['id'] ?? 0);
        $db = Connection::get();

        try {
            $stmt = $db->prepare('SELECT nom FROM api_keys WHERE id = ?');
            $stmt->execute([$id]);
            $cle = $stmt->fetch();

            if (!$cle) {
                Flash::error('Cle introuvable.');
                Response::redirect('/admin/configuration?onglet=api');
            }

            $db->prepare('DELETE FROM api_keys WHERE id = ?')->execute([$id]);

            AuditLogger::instance()->log(
                AuditAction::EmailConfigUpdate,
                $req->ipAnonymisee(),
                Auth::id(),
                'api_keys',
                $id,
                ['action' => 'api_key_revoked', 'nom' => $cle['nom']]
            );

            Flash::success('Cle API "' . htmlspecialchars($cle['nom']) . '" revoquee.');
        } catch (\PDOException $e) {
            Flash::error('Erreur lors de la revocation : ' . $e->getMessage());
        }

        Response::redirect('/admin/configuration?onglet=api');
    }

    // -----------------------------------------------
    // Utilitaires
    // -----------------------------------------------

    /**
     * Ecrit ou met a jour une cle dans le fichier .env.
     */
    private function ecrireDansEnv(string $cheminEnv, string $cle, string $valeur): void
    {
        $contenu = file_exists($cheminEnv) ? file_get_contents($cheminEnv) : '';
        $lignes = explode("\n", $contenu);
        $trouve = false;
        $valeurEchappee = '"' . str_replace(['\\', '"', "\n", "\r", "\0"], ['\\\\', '\\"', '\\n', '\\r', ''], $valeur) . '"';

        foreach ($lignes as &$ligne) {
            if (preg_match('/^' . preg_quote($cle, '/') . '\s*=/', $ligne)) {
                $ligne = "{$cle}={$valeurEchappee}";
                $trouve = true;
                break;
            }
        }
        unset($ligne);

        if (!$trouve) {
            $lignes[] = "{$cle}={$valeurEchappee}";
        }

        file_put_contents($cheminEnv, implode("\n", $lignes));
    }
}
