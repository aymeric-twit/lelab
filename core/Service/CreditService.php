<?php

namespace Platform\Service;

use Platform\Database\Connection;
use Platform\Module\ModuleRegistry;
use PDO;

/**
 * Service de gestion des crédits universels.
 * Chaque analyse consomme N crédits selon le poids du module.
 */
class CreditService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * Consomme les crédits pour une analyse sur un module.
     * Retourne true si les crédits ont été déduits, false si insuffisants.
     */
    public function consommer(int $userId, string $moduleSlug, int $montant = 0): bool
    {
        if ($montant === 0) {
            $montant = $this->poidsModule($moduleSlug);
        }

        // Module gratuit (poids 0) → toujours OK
        if ($montant === 0) {
            return true;
        }

        $record = $this->obtenirOuCreer($userId);

        // Illimité (limite = 0)
        if ((int) $record['credits_limite'] === 0) {
            // Incrémenter quand même pour le suivi
            $this->incrementer($userId, $montant);
            return true;
        }

        // Vérifier solde
        $restant = (int) $record['credits_limite'] - (int) $record['credits_utilises'];
        if ($restant < $montant) {
            return false;
        }

        $this->incrementer($userId, $montant);
        return true;
    }

    /**
     * Vérifie si l'utilisateur a assez de crédits SANS consommer.
     */
    public function peutConsommer(int $userId, string $moduleSlug): bool
    {
        $montant = $this->poidsModule($moduleSlug);
        if ($montant === 0) {
            return true;
        }

        $record = $this->obtenirOuCreer($userId);

        if ((int) $record['credits_limite'] === 0) {
            return true;
        }

        $restant = (int) $record['credits_limite'] - (int) $record['credits_utilises'];
        return $restant >= $montant;
    }

    /**
     * Retourne le solde de crédits restants (null si illimité).
     */
    public function solde(int $userId): ?int
    {
        $record = $this->obtenirOuCreer($userId);

        if ((int) $record['credits_limite'] === 0) {
            return null; // Illimité
        }

        return max(0, (int) $record['credits_limite'] - (int) $record['credits_utilises']);
    }

    /**
     * Vérifie si l'utilisateur a un plan illimité.
     */
    public function estIllimite(int $userId): bool
    {
        $record = $this->obtenirOuCreer($userId);
        return (int) $record['credits_limite'] === 0;
    }

    /**
     * Résumé pour le dashboard.
     *
     * @return array{utilises: int, limite: int, pourcentage: int, illimite: bool, periode_fin: string}
     */
    public function resumePourDashboard(int $userId): array
    {
        $record = $this->obtenirOuCreer($userId);
        $utilises = (int) $record['credits_utilises'];
        $limite = (int) $record['credits_limite'];
        $illimite = $limite === 0;

        return [
            'utilises'    => $utilises,
            'limite'      => $limite,
            'pourcentage' => $illimite ? 0 : ($limite > 0 ? (int) round(($utilises / $limite) * 100) : 0),
            'illimite'    => $illimite,
            'periode_fin' => $record['periode_fin'],
        ];
    }

    /**
     * Résumé d'usage par module pour la période en cours.
     *
     * @return array<string, array{nom: string, credits: int, analyses: int, poids: int}>
     */
    public function usageParModule(int $userId): array
    {
        $record = $this->obtenirOuCreer($userId);
        $jourInscription = $this->jourInscription($userId);
        $yearMonth = \Platform\Module\Quota::currentPeriod($jourInscription);

        $stmt = $this->db->prepare(
            'SELECT m.slug, m.name, m.credits_par_analyse, COALESCE(mu.usage_count, 0) AS usage_count
             FROM modules m
             LEFT JOIN module_usage mu ON mu.module_id = m.id AND mu.user_id = ? AND mu.year_month = ?
             WHERE m.enabled = 1 AND m.desinstalle_le IS NULL AND m.credits_par_analyse > 0
             ORDER BY (COALESCE(mu.usage_count, 0) * m.credits_par_analyse) DESC'
        );
        $stmt->execute([$userId, $yearMonth]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $analyses = (int) $row['usage_count'];
            $credits = $analyses * (int) $row['credits_par_analyse'];
            if ($analyses > 0) {
                $result[$row['slug']] = [
                    'nom'      => $row['name'],
                    'credits'  => $credits,
                    'analyses' => $analyses,
                    'poids'    => (int) $row['credits_par_analyse'],
                ];
            }
        }

        return $result;
    }

    /**
     * Réinitialise les crédits d'un utilisateur pour une nouvelle période.
     */
    public function reinitialiser(int $userId): void
    {
        $limite = $this->limiteDepuisPlan($userId);
        $dates = $this->calculerPeriode($userId);

        $this->db->prepare(
            'INSERT INTO user_credits (user_id, credits_utilises, credits_limite, periode_debut, periode_fin)
             VALUES (?, 0, ?, ?, ?)
             ON DUPLICATE KEY UPDATE credits_utilises = 0, credits_limite = VALUES(credits_limite),
             periode_debut = VALUES(periode_debut), periode_fin = VALUES(periode_fin)'
        )->execute([$userId, $limite, $dates['debut'], $dates['fin']]);
    }

    /**
     * Retourne le poids en crédits d'un module.
     */
    public function poidsModule(string $slug): int
    {
        $module = ModuleRegistry::get($slug);
        if ($module) {
            return $module->creditsParAnalyse;
        }

        // Fallback BDD
        $stmt = $this->db->prepare('SELECT credits_par_analyse FROM modules WHERE slug = ? AND desinstalle_le IS NULL');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ? (int) $row['credits_par_analyse'] : 1;
    }

    /**
     * Obtient ou crée le record de crédits pour un utilisateur.
     * Gère aussi le reset automatique si la période est expirée.
     */
    private function obtenirOuCreer(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_credits WHERE user_id = ?');
        $stmt->execute([$userId]);
        $record = $stmt->fetch();

        if (!$record) {
            $this->reinitialiser($userId);
            $stmt->execute([$userId]);
            $record = $stmt->fetch();
        }

        // Reset automatique si la période est expirée
        if ($record && strtotime($record['periode_fin']) < strtotime('today')) {
            $this->reinitialiser($userId);
            $stmt->execute([$userId]);
            $record = $stmt->fetch();
        }

        return $record;
    }

    private function incrementer(int $userId, int $montant): void
    {
        $this->db->prepare(
            'UPDATE user_credits SET credits_utilises = credits_utilises + ? WHERE user_id = ?'
        )->execute([$montant, $userId]);
    }

    /**
     * Détermine la limite de crédits depuis le plan de l'utilisateur.
     */
    private function limiteDepuisPlan(int $userId): int
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT p.credits_mensuels FROM plans p
                 JOIN users u ON u.plan_id = p.id
                 WHERE u.id = ? AND u.deleted_at IS NULL AND p.actif = 1'
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            if ($row) {
                return (int) $row['credits_mensuels'];
            }
        } catch (\PDOException) {
            // Table plans pas encore créée
        }

        return 50; // Défaut = plan Découverte
    }

    private function jourInscription(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? (int) date('j', strtotime($row['created_at'])) : 1;
    }

    /**
     * @return array{debut: string, fin: string}
     */
    private function calculerPeriode(int $userId): array
    {
        $jourInscription = $this->jourInscription($userId);
        $jourActuel = (int) date('j');
        $maintenant = new \DateTimeImmutable();

        if ($jourActuel >= $jourInscription) {
            $debut = $maintenant->setDate((int) $maintenant->format('Y'), (int) $maintenant->format('n'), min($jourInscription, (int) $maintenant->format('t')));
            $fin = $debut->modify('+1 month')->modify('-1 day');
        } else {
            $moisPrecedent = $maintenant->modify('-1 month');
            $debut = $moisPrecedent->setDate((int) $moisPrecedent->format('Y'), (int) $moisPrecedent->format('n'), min($jourInscription, (int) $moisPrecedent->format('t')));
            $fin = $debut->modify('+1 month')->modify('-1 day');
        }

        return ['debut' => $debut->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')];
    }
}
