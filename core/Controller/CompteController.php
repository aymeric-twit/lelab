<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Auth\PasswordHasher;
use Platform\Auth\Totp;
use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use Platform\Http\Csrf;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Service\AuditLogger;
use Platform\Service\EmailTemplate;
use Platform\Service\Mailer;
use Platform\User\AccessControl;
use Platform\User\UserRepository;
use Platform\Validation\Validator;
use Platform\View\Flash;
use Platform\View\Layout;
use Platform\Module\Quota;
use Platform\Service\NotificationService;

class CompteController
{
    public function afficher(): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $modules = $ac->getAccessibleModules($user['id']);
        $quotaSummary = Quota::getUserQuotaSummary($user['id']);

        // Dernières connexions
        $db = Connection::get();
        $stmt = $db->prepare('SELECT ip_address, user_agent, created_at FROM login_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
        $stmt->execute([$user['id']]);
        $dernieresConnexions = $stmt->fetchAll();

        Layout::render('layout', [
            'content'              => '',
            'template'             => 'mon-compte',
            'pageTitle'            => 'Mon compte',
            'currentUser'          => $user,
            'accessibleModules'    => $modules,
            'activeModule'         => '',
            'quotaSummary'         => $quotaSummary,
            'dernieresConnexions'  => $dernieresConnexions,
        ]);
    }

    public function mettreAJour(Request $req): void
    {
        Csrf::validateOrAbort();

        $user = Auth::user();
        $donnees = [
            'username' => trim($req->post('username', '')),
            'email' => trim($req->post('email', '')),
            'domaine' => trim($req->post('domaine', '')),
        ];

        $regles = [
            'username' => 'requis|min:3|max:50',
            'email' => 'requis|email',
        ];

        // Vérifier l'unicité seulement si la valeur a changé
        if ($donnees['username'] !== $user['username']) {
            $regles['username'] .= '|unique:users,username';
        }
        if ($donnees['email'] !== ($user['email'] ?? '')) {
            $regles['email'] .= '|unique:users,email';
        }

        $validator = new Validator();
        if (!$validator->valider($donnees, $regles)) {
            Flash::error($validator->premiereErreur());
            Response::redirect('/mon-compte');
        }

        $repo = new UserRepository();
        $emailChange = $donnees['email'] !== ($user['email'] ?? '') && !empty($donnees['email']);

        if ($emailChange) {
            // Ne pas mettre à jour l'email directement : envoyer un lien de confirmation
            $token = bin2hex(random_bytes(32));
            $expireLe = date('Y-m-d H:i:s', time() + 24 * 3600);

            $db = Connection::get();
            $stmt = $db->prepare('UPDATE users SET pending_email = ?, pending_email_token = ?, pending_email_expires = ? WHERE id = ?');
            $stmt->execute([$donnees['email'], $token, $expireLe, $user['id']]);

            // Envoyer l'email de confirmation à la nouvelle adresse
            $config = require __DIR__ . '/../../config/app.php';
            $lienConfirmation = rtrim($config['url'], '/') . '/confirmer-email?token=' . $token;

            $contenu = EmailTemplate::rendre('confirmation-changement-email', [
                'username' => $user['username'],
                'nouvelEmail' => $donnees['email'],
                'lien' => $lienConfirmation,
            ]);

            Mailer::instance()->envoyer($donnees['email'], 'Confirmez votre nouvelle adresse email', $contenu);

            $repo->update($user['id'], [
                'username' => $donnees['username'],
                'domaine' => $donnees['domaine'] ?: null,
            ]);

            AuditLogger::instance()->log(AuditAction::UserUpdate, $req->ipAnonymisee(), $user['id'], 'user', $user['id']);

            Flash::info('Un email de confirmation a été envoyé à la nouvelle adresse.');
            Response::redirect('/mon-compte');
        }

        $repo->update($user['id'], [
            'username' => $donnees['username'],
            'email' => $donnees['email'] ?: null,
            'domaine' => $donnees['domaine'] ?: null,
        ]);

        AuditLogger::instance()->log(AuditAction::UserUpdate, $req->ipAnonymisee(), $user['id'], 'user', $user['id']);

        Flash::success('Informations mises à jour.');
        Response::redirect('/mon-compte');
    }

    public function confirmerEmail(Request $req): void
    {
        $token = $req->get('token', '');

        if ($token === '') {
            Flash::error('Token manquant.');
            Response::redirect('/login');
        }

        $db = Connection::get();
        $stmt = $db->prepare(
            'SELECT id, pending_email FROM users WHERE pending_email_token = ? AND pending_email_expires > NOW() AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$token]);
        $utilisateur = $stmt->fetch();

        if (!$utilisateur) {
            Flash::error('Lien de confirmation invalide ou expiré.');
            Response::redirect('/login');
        }

        $repo = new UserRepository();
        $repo->update((int) $utilisateur['id'], [
            'email' => $utilisateur['pending_email'],
        ]);

        // Effacer les champs pending
        $stmtClear = $db->prepare('UPDATE users SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL WHERE id = ?');
        $stmtClear->execute([$utilisateur['id']]);

        Flash::success('Votre adresse email a été mise à jour.');
        Response::redirect('/mon-compte');
    }

    public function changerMotDePasse(Request $req): void
    {
        Csrf::validateOrAbort();

        $user = Auth::user();
        $motDePasseActuel = $req->post('mot_de_passe_actuel', '');
        $donnees = [
            'password' => $req->post('password', ''),
            'password_confirmation' => $req->post('password_confirmation', ''),
        ];

        // Vérifier le mot de passe actuel
        if (!PasswordHasher::verify($motDePasseActuel, $user['password_hash'])) {
            Flash::error('Le mot de passe actuel est incorrect.');
            Response::redirect('/mon-compte');
        }

        $validator = new Validator();
        if (!$validator->valider($donnees, ['password' => 'requis|mot_de_passe|confirme'])) {
            Flash::error($validator->premiereErreur());
            Response::redirect('/mon-compte');
        }

        $repo = new UserRepository();
        $repo->update($user['id'], [
            'password_hash' => PasswordHasher::hash($donnees['password']),
            'force_password_reset' => 0,
        ]);

        AuditLogger::instance()->log(AuditAction::UserUpdate, $req->ipAnonymisee(), $user['id'], 'user', $user['id'], [
            'action' => 'changement_mot_de_passe',
        ]);

        NotificationService::instance()->envoyerConfirmationChangementMdp($user['id']);

        Flash::success('Mot de passe modifié avec succès.');
        Response::redirect('/mon-compte');
    }

    public function supprimerCompte(Request $req): void
    {
        Csrf::validateOrAbort();

        $user = Auth::user();

        // Un admin ne peut pas supprimer son compte
        if (($user['role'] ?? '') === 'admin') {
            Flash::error('Un administrateur ne peut pas supprimer son propre compte.');
            Response::redirect('/mon-compte');
        }

        // Vérification du mot de passe
        $motDePasse = $req->post('mot_de_passe_suppression', '');
        if (!PasswordHasher::verify($motDePasse, $user['password_hash'])) {
            Flash::error('Mot de passe incorrect.');
            Response::redirect('/mon-compte');
        }

        $repo = new UserRepository();
        $repo->supprimerCompte($user['id']);

        AuditLogger::instance()->log(AuditAction::AccountDelete, $req->ipAnonymisee(), $user['id'], 'user', $user['id'], [
            'username' => $user['username'],
        ]);

        NotificationService::instance()->envoyerConfirmationSuppression(
            $user['email'] ?? '',
            $user['username']
        );

        Auth::logout();

        Flash::success('Votre compte a été supprimé.');
        Response::redirect('/login');
    }

    // -----------------------------------------------
    // Authentification à deux facteurs (2FA)
    // -----------------------------------------------

    public function activer2fa(): void
    {
        $user = Auth::user();
        $secret = Totp::genererSecret();
        $_SESSION['_2fa_setup_secret'] = $secret;

        $email = $user['email'] ?? $user['username'];
        $uri = Totp::genererUri($secret, $email);
        $qrUrl = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($uri);

        $ac = new AccessControl();
        $modules = $ac->getAccessibleModules($user['id']);

        Layout::render('layout', [
            'content'           => '',
            'template'          => '2fa-setup',
            'pageTitle'         => 'Activer la 2FA',
            'currentUser'       => $user,
            'accessibleModules' => $modules,
            'activeModule'      => '',
            'totpSecret'        => $secret,
            'totpUri'           => $uri,
            'qrUrl'             => $qrUrl,
        ]);
    }

    public function confirmer2fa(Request $req): void
    {
        Csrf::validateOrAbort();

        $user = Auth::user();
        $code = trim($req->post('code', ''));
        $secret = $_SESSION['_2fa_setup_secret'] ?? '';

        if ($secret === '') {
            Flash::error('Session expirée. Veuillez recommencer l\'activation.');
            Response::redirect('/mon-compte');
        }

        if (!Totp::verifier($secret, $code)) {
            Flash::error('Code de vérification invalide. Veuillez réessayer.');
            Response::redirect('/mon-compte/2fa/activer');
        }

        // Sauvegarder le secret en base et activer la 2FA
        $repo = new UserRepository();
        $repo->update($user['id'], [
            'totp_secret' => $secret,
            'totp_enabled' => 1,
        ]);

        unset($_SESSION['_2fa_setup_secret']);

        AuditLogger::instance()->log(AuditAction::UserUpdate, $req->ipAnonymisee(), $user['id'], 'user', $user['id'], [
            'action' => '2fa_activee',
        ]);

        Flash::success('Authentification à deux facteurs activée avec succès.');
        Response::redirect('/mon-compte');
    }

    public function desactiver2fa(Request $req): void
    {
        Csrf::validateOrAbort();

        $user = Auth::user();
        $motDePasse = $req->post('mot_de_passe_2fa', '');

        if (!PasswordHasher::verify($motDePasse, $user['password_hash'])) {
            Flash::error('Mot de passe incorrect.');
            Response::redirect('/mon-compte');
        }

        $repo = new UserRepository();
        $repo->update($user['id'], [
            'totp_secret' => null,
            'totp_enabled' => 0,
        ]);

        AuditLogger::instance()->log(AuditAction::UserUpdate, $req->ipAnonymisee(), $user['id'], 'user', $user['id'], [
            'action' => '2fa_desactivee',
        ]);

        Flash::success('Authentification à deux facteurs désactivée.');
        Response::redirect('/mon-compte');
    }
}
