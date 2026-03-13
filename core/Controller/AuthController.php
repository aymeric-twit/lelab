<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Auth\EmailVerification;
use Platform\Auth\Inscription;
use Platform\Auth\PasswordHasher;
use Platform\Auth\PasswordReset;
use Platform\Auth\RememberMe;
use Platform\Auth\Totp;
use Platform\Database\Connection;
use Platform\Enum\AuditAction;
use Platform\Http\Csrf;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Service\AuditLogger;
use Platform\User\UserRepository;
use Platform\Validation\Validator;
use Platform\View\Flash;
use Platform\View\Layout;

class AuthController
{
    public function formulaireLogin(): void
    {
        if (Auth::check()) {
            Response::redirect('/');
        }
        $inscriptionActive = Inscription::estActive();
        Layout::renderStandalone('login', ['inscriptionActive' => $inscriptionActive]);
    }

    public function login(Request $req): void
    {
        Csrf::validateOrAbort();

        $ipAnonyme = $req->ipAnonymisee();
        $username = trim($req->post('username', ''));
        $password = $req->post('password', '');
        $sesouvenir = (bool) $req->post('se_souvenir', false);
        $audit = AuditLogger::instance();

        if (Auth::isRateLimited($ipAnonyme)) {
            Flash::error('Trop de tentatives. Réessayez dans 15 minutes.');
            Response::redirect('/login');
        }

        // Vérifier les identifiants sans créer de session complète si 2FA activée
        $repo = new UserRepository();
        $utilisateur = $repo->findByUsername($username);

        if ($utilisateur && $utilisateur['active'] && PasswordHasher::verify($password, $utilisateur['password_hash'])) {
            // Vérifier si la 2FA est activée
            if (!empty($utilisateur['totp_enabled'])) {
                // Stocker l'ID en attente de vérification 2FA (sans Auth::login)
                $_SESSION['_2fa_pending'] = (int) $utilisateur['id'];
                $_SESSION['_2fa_se_souvenir'] = $sesouvenir;
                Response::redirect('/2fa');
            }

            // Pas de 2FA : connexion normale via Auth::attempt
            Auth::attempt($username, $password);
            $audit->log(AuditAction::LoginSuccess, $ipAnonyme, Auth::id(), 'user', null, ['username' => $username]);

            // Historique de connexion
            $db = Connection::get();
            $stmt = $db->prepare('INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)');
            $stmt->execute([Auth::id(), $ipAnonyme, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]);

            if ($sesouvenir) {
                RememberMe::creerToken(Auth::id());
            }

            Response::redirect('/');
        }

        // Vérifier si le compte existe mais est inactif (email non vérifié)
        if ($utilisateur && !$utilisateur['active'] && PasswordHasher::verify($password, $utilisateur['password_hash'])) {
            $audit->log(AuditAction::LoginFailed, $ipAnonyme, (int) $utilisateur['id'], 'user', null, ['username' => $username, 'raison' => 'compte_inactif']);
            $inscriptionActive = Inscription::estActive();
            Layout::renderStandalone('login', [
                'inscriptionActive' => $inscriptionActive,
                'compteInactif' => true,
                'emailInactif' => $utilisateur['email'] ?? '',
            ]);
            return;
        }

        $audit->log(AuditAction::LoginFailed, $ipAnonyme, null, 'user', null, ['username' => $username]);
        Flash::error('Identifiants incorrects.');
        Response::redirect('/login');
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect('/login');
    }

    // -----------------------------------------------
    // Inscription
    // -----------------------------------------------

    public function formulaireInscription(): void
    {
        if (Auth::check()) {
            Response::redirect('/');
        }
        if (!Inscription::estActive()) {
            Response::abort(404);
        }
        Layout::renderStandalone('inscription');
    }

    public function inscrire(Request $req): void
    {
        Csrf::validateOrAbort();

        if (!Inscription::estActive()) {
            Response::abort(404);
        }

        $ipAnonyme = $req->ipAnonymisee();

        if (Inscription::estLimitee($ipAnonyme)) {
            Flash::error('Trop de tentatives d\'inscription. Réessayez plus tard.');
            Response::redirect('/inscription');
        }

        $donnees = [
            'username' => trim($req->post('username', '')),
            'email' => trim($req->post('email', '')),
            'password' => $req->post('password', ''),
            'password_confirmation' => $req->post('password_confirmation', ''),
        ];

        $validator = new Validator();
        $valide = $validator->valider($donnees, [
            'username' => 'requis|min:3|max:50|unique:users,username',
            'email' => 'requis|email|unique:users,email',
            'password' => 'requis|mot_de_passe|confirme',
        ]);

        if (!$valide) {
            Flash::error($validator->premiereErreur());
            Response::redirect('/inscription');
        }

        $compte = Inscription::creerCompte($donnees['username'], $donnees['email'], $donnees['password'], $ipAnonyme);

        EmailVerification::envoyer($compte['id'], $compte['email'], $compte['username']);

        Flash::success('Compte créé ! Vérifiez votre email pour activer votre compte.');
        Response::redirect('/login');
    }

    // -----------------------------------------------
    // Renvoi de vérification email (public)
    // -----------------------------------------------

    public function renvoyerVerification(Request $req): void
    {
        Csrf::validateOrAbort();

        $email = trim($req->post('email', ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $repo = new UserRepository();
            $utilisateur = $repo->findByEmail($email);

            if ($utilisateur && !$utilisateur['active']) {
                try {
                    EmailVerification::envoyer((int) $utilisateur['id'], $utilisateur['email'], $utilisateur['username']);
                } catch (\Throwable) {
                    // Silencieux : anti-énumération
                }
            }
        }

        // Anti-énumération : toujours le même message
        Flash::success('Si cette adresse est associée à un compte en attente de vérification, un nouvel email a été envoyé.');
        Response::redirect('/login');
    }

    // -----------------------------------------------
    // Vérification email
    // -----------------------------------------------

    public function verifierEmail(Request $req): void
    {
        $token = $req->get('token', '');

        if ($token === '') {
            Layout::renderStandalone('verifier-email', ['succes' => false, 'message' => 'Token manquant.']);
            return;
        }

        $resultat = EmailVerification::verifier($token, $req->ipAnonymisee());

        if ($resultat) {
            Flash::success('Votre adresse email a été vérifiée. Vous pouvez maintenant vous connecter.');
            Response::redirect('/login');
        }

        Layout::renderStandalone('verifier-email', [
            'succes' => false,
            'message' => 'Lien de vérification invalide ou expiré.',
        ]);
    }

    // -----------------------------------------------
    // Mot de passe oublié
    // -----------------------------------------------

    public function formulaireMotDePasseOublie(): void
    {
        if (Auth::check()) {
            Response::redirect('/');
        }
        Layout::renderStandalone('mot-de-passe-oublie');
    }

    public function demanderReinitialisation(Request $req): void
    {
        Csrf::validateOrAbort();

        $email = trim($req->post('email', ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::error('Veuillez saisir une adresse email valide.');
            Response::redirect('/mot-de-passe-oublie');
        }

        PasswordReset::demander($email, $req->ipAnonymisee());

        // Anti-énumération : toujours le même message
        Flash::success('Si cette adresse est associée à un compte, un email de réinitialisation a été envoyé.');
        Response::redirect('/login');
    }

    public function formulaireReinitialisation(Request $req): void
    {
        $token = $req->get('token', '');

        if ($token === '' || PasswordReset::validerToken($token) === null) {
            Flash::error('Lien de réinitialisation invalide ou expiré.');
            Response::redirect('/mot-de-passe-oublie');
        }

        Layout::renderStandalone('reinitialiser-mot-de-passe', ['token' => $token]);
    }

    public function reinitialiserMotDePasse(Request $req): void
    {
        Csrf::validateOrAbort();

        $token = $req->post('token', '');
        $donnees = [
            'password' => $req->post('password', ''),
            'password_confirmation' => $req->post('password_confirmation', ''),
        ];

        $validator = new Validator();
        $valide = $validator->valider($donnees, [
            'password' => 'requis|mot_de_passe|confirme',
        ]);

        if (!$valide) {
            Flash::error($validator->premiereErreur());
            Response::redirect('/reinitialiser-mot-de-passe?token=' . urlencode($token));
        }

        if (PasswordReset::reinitialiser($token, $donnees['password'], $req->ipAnonymisee())) {
            Flash::success('Votre mot de passe a été réinitialisé. Connectez-vous avec votre nouveau mot de passe.');
            Response::redirect('/login');
        }

        Flash::error('Lien de réinitialisation invalide ou expiré.');
        Response::redirect('/mot-de-passe-oublie');
    }

    // -----------------------------------------------
    // Authentification à deux facteurs (2FA)
    // -----------------------------------------------

    public function formulaire2fa(): void
    {
        if (!isset($_SESSION['_2fa_pending'])) {
            Response::redirect('/login');
        }
        Layout::renderStandalone('2fa');
    }

    public function verifier2fa(Request $req): void
    {
        Csrf::validateOrAbort();

        if (!isset($_SESSION['_2fa_pending'])) {
            Response::redirect('/login');
        }

        $userId = (int) $_SESSION['_2fa_pending'];
        $code = trim($req->post('code', ''));
        $ipAnonyme = $req->ipAnonymisee();
        $audit = AuditLogger::instance();

        $repo = new UserRepository();
        $utilisateur = $repo->findById($userId);

        if (!$utilisateur || empty($utilisateur['totp_secret'])) {
            unset($_SESSION['_2fa_pending'], $_SESSION['_2fa_se_souvenir']);
            Flash::error('Erreur de vérification. Veuillez vous reconnecter.');
            Response::redirect('/login');
        }

        if (Totp::verifier($utilisateur['totp_secret'], $code)) {
            $sesouvenir = $_SESSION['_2fa_se_souvenir'] ?? false;
            unset($_SESSION['_2fa_pending'], $_SESSION['_2fa_se_souvenir']);

            // Connexion effective
            Auth::loginParId($userId);
            $audit->log(AuditAction::LoginSuccess, $ipAnonyme, $userId, 'user', null, [
                'username' => $utilisateur['username'],
                '2fa' => true,
            ]);

            // Historique de connexion
            $db = Connection::get();
            $stmt = $db->prepare('INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $ipAnonyme, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]);

            if ($sesouvenir) {
                RememberMe::creerToken($userId);
            }

            Response::redirect('/');
        }

        $audit->log(AuditAction::LoginFailed, $ipAnonyme, $userId, 'user', null, [
            'username' => $utilisateur['username'],
            'raison' => '2fa_invalide',
        ]);

        Flash::error('Code de vérification invalide.');
        Response::redirect('/2fa');
    }
}
