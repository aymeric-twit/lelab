<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Auth\PasswordHasher;
use Platform\Enum\AuditAction;
use Platform\Http\Csrf;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Service\AuditLogger;
use Platform\User\AccessControl;
use Platform\User\UserRepository;
use Platform\Validation\Validator;
use Platform\View\Flash;
use Platform\View\Layout;
use Platform\Module\Quota;

class CompteController
{
    public function afficher(): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $modules = $ac->getAccessibleModules($user['id']);
        $quotaSummary = Quota::getUserQuotaSummary($user['id']);

        Layout::render('layout', [
            'content'           => '',
            'template'          => 'mon-compte',
            'pageTitle'         => 'Mon compte',
            'currentUser'       => $user,
            'accessibleModules' => $modules,
            'activeModule'      => '',
            'quotaSummary'      => $quotaSummary,
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
        $repo->update($user['id'], [
            'username' => $donnees['username'],
            'email' => $donnees['email'] ?: null,
            'domaine' => $donnees['domaine'] ?: null,
        ]);

        AuditLogger::instance()->log(AuditAction::UserUpdate, $req->ip(), $user['id'], 'user', $user['id']);

        Flash::success('Informations mises à jour.');
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
        ]);

        AuditLogger::instance()->log(AuditAction::UserUpdate, $req->ip(), $user['id'], 'user', $user['id'], [
            'action' => 'changement_mot_de_passe',
        ]);

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

        AuditLogger::instance()->log(AuditAction::AccountDelete, $req->ip(), $user['id'], 'user', $user['id'], [
            'username' => $user['username'],
        ]);

        Auth::logout();

        Flash::success('Votre compte a été supprimé.');
        Response::redirect('/login');
    }
}
