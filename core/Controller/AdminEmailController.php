<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Enum\AuditAction;
use Platform\Enum\TypeNotification;
use Platform\Http\Csrf;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Repository\EmailLogRepository;
use Platform\Repository\SettingsRepository;
use Platform\Service\AuditLogger;
use Platform\Service\EmailTemplate;
use Platform\Service\Mailer;
use Platform\User\AccessControl;
use Platform\Validation\Validator;
use Platform\View\Flash;
use Platform\View\Layout;

class AdminEmailController
{
    public function index(Request $req): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $modules = $ac->getAccessibleModules($user['id']);
        $onglet = $req->get('onglet', 'smtp');

        $settings = new SettingsRepository();
        $config = require __DIR__ . '/../../config/app.php';

        // Données SMTP
        $smtpDb = $settings->obtenirGroupe('smtp');
        $smtpEnv = $config['email'];
        $smtpEffectif = Mailer::configEffective();

        // Données notifications
        $notifDb = $settings->obtenirGroupe('notifications');
        $sujetsDb = $settings->obtenirGroupe('email_sujets');
        $types = TypeNotification::cases();

        // Données historique
        $historique = [];
        $pagination = ['total' => 0, 'page' => 1, 'parPage' => 30, 'totalPages' => 1];
        $filtres = [
            'date_debut' => $req->get('date_debut', ''),
            'date_fin' => $req->get('date_fin', ''),
            'type' => $req->get('type', ''),
            'statut' => $req->get('statut', ''),
            'destinataire' => $req->get('destinataire', ''),
        ];

        if ($onglet === 'historique') {
            $repo = new EmailLogRepository();
            $page = max(1, (int) $req->get('page', '1'));
            $result = $repo->rechercher($filtres, $page);
            $historique = $result['donnees'];
            $pagination = $result;
        }

        // Stats
        $stats = ['envoye' => 0, 'echec' => 0];
        if ($onglet === 'historique') {
            $stats = (new EmailLogRepository())->compterParStatut(30);
        }

        Layout::render('layout', [
            'template'          => 'admin/emails',
            'pageTitle'         => 'Gestion des emails',
            'currentUser'       => $user,
            'accessibleModules' => $modules,
            'activeModule'      => '',
            'adminPage'         => 'emails',
            'onglet'            => $onglet,
            'smtpDb'            => $smtpDb,
            'smtpEnv'           => $smtpEnv,
            'smtpEffectif'      => $smtpEffectif,
            'notifDb'           => $notifDb,
            'sujetsDb'          => $sujetsDb,
            'types'             => $types,
            'historique'        => $historique,
            'pagination'        => $pagination,
            'filtres'           => $filtres,
            'stats'             => $stats,
            'csrfToken'         => Csrf::token(),
        ]);
    }

    public function sauvegarderSmtp(Request $req): void
    {
        $settings = new SettingsRepository();

        $donnees = [
            'host'       => trim($req->post('host', '')),
            'port'       => trim($req->post('port', '')),
            'username'   => trim($req->post('username', '')),
            'encryption' => trim($req->post('encryption', '')),
            'from'       => trim($req->post('from', '')),
            'from_name'  => trim($req->post('from_name', '')),
        ];

        // Le mot de passe n'est sauvé que s'il est renseigné
        $password = $req->post('password', '');
        if ($password !== '') {
            $donnees['password'] = $password;
        }

        // Validation
        $validator = new Validator();
        $regles = [
            'host' => 'requis',
            'port' => 'requis',
            'from' => 'requis|email',
        ];

        if (!$validator->valider($donnees, $regles)) {
            Flash::error($validator->premiereErreur());
            Response::redirect('/admin/emails?onglet=smtp');
        }

        if (!in_array($donnees['encryption'], ['tls', 'ssl', 'none', ''], true)) {
            Flash::error('Chiffrement invalide.');
            Response::redirect('/admin/emails?onglet=smtp');
        }

        $settings->definirGroupe('smtp', $donnees);

        // Reset le singleton Mailer pour utiliser la nouvelle config
        Mailer::resetInstance();

        AuditLogger::instance()->log(AuditAction::EmailConfigUpdate, $req->ip(), Auth::id(), 'settings');

        Flash::success('Configuration SMTP enregistrée.');
        Response::redirect('/admin/emails?onglet=smtp');
    }

    public function envoyerTest(Request $req): void
    {
        $user = Auth::user();
        $destinataire = $user['email'] ?? '';

        if (empty($destinataire)) {
            Response::json(['ok' => false, 'message' => 'Aucune adresse email configurée sur votre compte.'], 400);
        }

        // Construire un mailer avec la config effective actuelle
        Mailer::resetInstance();

        $contenu = EmailTemplate::rendre('verification-email', [
            'username' => $user['username'],
            'lien' => '#',
            'expiration' => 24,
        ]);

        $succes = Mailer::instance()->envoyer(
            $destinataire,
            'Test email — Le lab',
            $contenu,
            'test',
        );

        if ($succes) {
            Response::json(['ok' => true, 'message' => "Email de test envoyé à {$destinataire}."]);
        } else {
            Response::json(['ok' => false, 'message' => 'Échec de l\'envoi. Vérifiez les paramètres SMTP et les logs.'], 500);
        }
    }

    public function sauvegarderNotifications(Request $req): void
    {
        $settings = new SettingsRepository();

        // Paramètres généraux
        $adminEmail = trim($req->post('admin_email', ''));
        $seuilQuota = (int) $req->post('quota_seuil_alerte', '80');

        if ($seuilQuota < 50 || $seuilQuota > 99) {
            Flash::error('Le seuil d\'alerte quota doit être entre 50 et 99%.');
            Response::redirect('/admin/emails?onglet=notifications');
        }

        $settings->definir('notifications', 'admin_email', $adminEmail);
        $settings->definir('notifications', 'quota_seuil_alerte', (string) $seuilQuota);

        // Toggles par type
        foreach (TypeNotification::cases() as $type) {
            $actif = $req->post($type->value . '_active') !== null ? '1' : '0';
            $settings->definir('notifications', $type->value . '_active', $actif);
        }

        // Sujets personnalisés
        foreach (TypeNotification::cases() as $type) {
            $sujet = trim($req->post('sujet_' . $type->value, ''));
            if ($sujet !== '') {
                $settings->definir('email_sujets', $type->value, mb_substr($sujet, 0, 200));
            } else {
                $settings->supprimer('email_sujets', $type->value);
            }
        }

        AuditLogger::instance()->log(AuditAction::EmailConfigUpdate, $req->ip(), Auth::id(), 'settings');

        Flash::success('Configuration des notifications enregistrée.');
        Response::redirect('/admin/emails?onglet=notifications');
    }

    public function apercuTemplate(Request $req, array $params): void
    {
        $typeValue = $params['type'] ?? '';
        $type = TypeNotification::tryFrom($typeValue);

        if (!$type) {
            Response::json(['ok' => false, 'message' => 'Type de notification inconnu.'], 404);
        }

        $html = EmailTemplate::rendre($type->template(), $type->donneesExemple());

        Response::json(['ok' => true, 'html' => $html, 'label' => $type->label()]);
    }

    public function exporterCsv(Request $req): void
    {
        $filtres = [
            'date_debut' => $req->get('date_debut', ''),
            'date_fin' => $req->get('date_fin', ''),
            'type' => $req->get('type', ''),
            'statut' => $req->get('statut', ''),
            'destinataire' => $req->get('destinataire', ''),
        ];

        $repo = new EmailLogRepository();
        $logs = $repo->rechercherTout($filtres);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="emails_' . date('Y-m-d') . '.csv"');

        $handle = fopen('php://output', 'w');
        fputcsv($handle, ['Date', 'Destinataire', 'Type', 'Sujet', 'Statut', 'Erreur']);

        foreach ($logs as $log) {
            $type = TypeNotification::tryFrom($log['type_email'] ?? '');
            fputcsv($handle, [
                $log['created_at'],
                $log['destinataire'],
                $type ? $type->label() : ($log['type_email'] ?? '-'),
                $log['sujet'],
                $log['statut'],
                $log['erreur'] ?? '',
            ]);
        }

        fclose($handle);
        exit;
    }
}
