<?php

namespace Platform\Controller;

use Platform\Enum\TypeNotification;
use Platform\Repository\NotificationPreferenceRepository;
use Platform\User\UserRepository;

class DesabonnementController
{
    /**
     * GET /desabonnement?token=xxx — Page de gestion des préférences de notification.
     */
    public function afficher(): void
    {
        $token = $_GET['token'] ?? '';
        $action = $_GET['action'] ?? '';

        if ($token === '') {
            $this->rendrePage(['erreur' => 'Lien invalide. Aucun token fourni.']);
            return;
        }

        $repo = new UserRepository();
        $user = $repo->findByUnsubscribeToken($token);

        if ($user === null) {
            $this->rendrePage(['erreur' => 'Ce lien de désabonnement est invalide ou a expiré.']);
            return;
        }

        // Si action=tout dans l'URL, désabonner de tout directement
        if ($action === 'tout') {
            $this->desabonnerToutPourUser($user);
            $this->rendrePage([
                'succes' => 'Vous avez été désabonné de toutes les notifications.',
                'user' => $user,
                'token' => $token,
                'types' => $this->typesDesabonnables(),
                'preferences' => $this->chargerPreferences((int) $user['id']),
            ]);
            return;
        }

        $this->rendrePage([
            'user' => $user,
            'token' => $token,
            'types' => $this->typesDesabonnables(),
            'preferences' => $this->chargerPreferences((int) $user['id']),
        ]);
    }

    /**
     * POST /desabonnement — Sauvegarde des préférences.
     */
    public function mettreAJour(): void
    {
        $token = $_POST['token'] ?? '';

        if ($token === '') {
            $this->rendrePage(['erreur' => 'Token manquant.']);
            return;
        }

        $repo = new UserRepository();
        $user = $repo->findByUnsubscribeToken($token);

        if ($user === null) {
            $this->rendrePage(['erreur' => 'Ce lien de désabonnement est invalide ou a expiré.']);
            return;
        }

        $prefRepo = new NotificationPreferenceRepository();
        $types = $this->typesDesabonnables();

        foreach ($types as $type) {
            $actif = isset($_POST['notif_' . $type->value]);
            $prefRepo->mettreAJour((int) $user['id'], $type->value, $actif);
        }

        $this->rendrePage([
            'succes' => 'Vos préférences de notification ont été enregistrées.',
            'user' => $user,
            'token' => $token,
            'types' => $types,
            'preferences' => $this->chargerPreferences((int) $user['id']),
        ]);
    }

    /**
     * POST /desabonnement/tout — Désabonnement total (List-Unsubscribe-Post).
     */
    public function desabonnerTout(): void
    {
        $token = $_POST['token'] ?? $_GET['token'] ?? '';

        if ($token === '') {
            http_response_code(400);
            echo 'Token manquant';
            return;
        }

        $repo = new UserRepository();
        $user = $repo->findByUnsubscribeToken($token);

        if ($user === null) {
            http_response_code(404);
            echo 'Token invalide';
            return;
        }

        $this->desabonnerToutPourUser($user);

        // Si c'est un POST List-Unsubscribe, répondre simplement
        if (!empty($_SERVER['HTTP_LIST_UNSUBSCRIBE'])) {
            http_response_code(200);
            echo 'Désabonné';
            return;
        }

        $this->rendrePage([
            'succes' => 'Vous avez été désabonné de toutes les notifications.',
            'user' => $user,
            'token' => $token,
            'types' => $this->typesDesabonnables(),
            'preferences' => $this->chargerPreferences((int) $user['id']),
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function desabonnerToutPourUser(array $user): void
    {
        $prefRepo = new NotificationPreferenceRepository();
        foreach ($this->typesDesabonnables() as $type) {
            $prefRepo->mettreAJour((int) $user['id'], $type->value, false);
        }
    }

    /**
     * @return TypeNotification[]
     */
    private function typesDesabonnables(): array
    {
        return array_filter(
            TypeNotification::cases(),
            fn(TypeNotification $t) => $t->estDesabonnable()
        );
    }

    /**
     * @return array<string, bool>
     */
    private function chargerPreferences(int $userId): array
    {
        return (new NotificationPreferenceRepository())->obtenirPreferences($userId);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function rendrePage(array $data): void
    {
        extract($data);
        require __DIR__ . '/../../templates/desabonnement.php';
    }
}
