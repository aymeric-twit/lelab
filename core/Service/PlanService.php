<?php

namespace Platform\Service;

use Platform\Database\Connection;
use PDO;

/**
 * Service de gestion des plans d'abonnement.
 */
class PlanService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * Retourne tous les plans actifs, ordonnés.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listerPlansActifs(): array
    {
        return $this->db->query(
            'SELECT * FROM plans WHERE actif = 1 ORDER BY sort_order'
        )->fetchAll();
    }

    /**
     * Retourne un plan par son slug.
     */
    public function parSlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plans WHERE slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Retourne un plan par son ID.
     */
    public function parId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plans WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Retourne le plan de l'utilisateur (ou null si aucun).
     */
    public function planUtilisateur(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.* FROM plans p JOIN users u ON u.plan_id = p.id WHERE u.id = ? AND u.deleted_at IS NULL'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Assigne un plan à un utilisateur.
     */
    public function assignerPlan(int $userId, int $planId): void
    {
        $this->db->prepare('UPDATE users SET plan_id = ? WHERE id = ?')
            ->execute([$planId, $userId]);
    }

    /**
     * Vérifie si un utilisateur a accès à un module selon son plan.
     * Retourne true si le plan inclut "*" (tous) ou le slug du module.
     * Retourne true si l'utilisateur n'a pas de plan (pas de restriction).
     */
    public function moduleInclusDansPlan(int $userId, string $moduleSlug): bool
    {
        $plan = $this->planUtilisateur($userId);
        if ($plan === null) {
            return true; // Pas de plan = pas de restriction par plan
        }

        $modulesInclus = json_decode($plan['modules_inclus'] ?? '[]', true);
        if (!is_array($modulesInclus)) {
            return true;
        }

        return in_array('*', $modulesInclus, true) || in_array($moduleSlug, $modulesInclus, true);
    }

    /**
     * Retourne les quotas par défaut du plan.
     *
     * @return array<string, int>
     */
    public function quotasParDefaut(int $planId): array
    {
        $plan = $this->parId($planId);
        if ($plan === null) {
            return [];
        }

        $quotas = json_decode($plan['quotas_defaut'] ?? '{}', true);
        return is_array($quotas) ? $quotas : [];
    }

    /**
     * Retourne les limites du plan (max_modules, etc.).
     *
     * @return array<string, mixed>
     */
    public function limites(int $planId): array
    {
        $plan = $this->parId($planId);
        if ($plan === null) {
            return [];
        }

        $limites = json_decode($plan['limites'] ?? '{}', true);
        return is_array($limites) ? $limites : [];
    }

    /**
     * Crée un nouveau plan.
     */
    public function creer(array $donnees): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO plans (slug, nom, description, prix_mensuel, prix_annuel, quotas_defaut, modules_inclus, limites, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $donnees['slug'],
            $donnees['nom'],
            $donnees['description'] ?? null,
            $donnees['prix_mensuel'] ?? null,
            $donnees['prix_annuel'] ?? null,
            json_encode($donnees['quotas_defaut'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($donnees['modules_inclus'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($donnees['limites'] ?? [], JSON_UNESCAPED_UNICODE),
            $donnees['sort_order'] ?? 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour un plan existant.
     */
    public function mettreAJour(int $id, array $donnees): void
    {
        $champs = [];
        $valeurs = [];

        foreach (['nom', 'description', 'prix_mensuel', 'prix_annuel', 'sort_order', 'actif'] as $champ) {
            if (array_key_exists($champ, $donnees)) {
                $champs[] = "{$champ} = ?";
                $valeurs[] = $donnees[$champ];
            }
        }

        foreach (['quotas_defaut', 'modules_inclus', 'limites'] as $champJson) {
            if (array_key_exists($champJson, $donnees)) {
                $champs[] = "{$champJson} = ?";
                $valeurs[] = json_encode($donnees[$champJson], JSON_UNESCAPED_UNICODE);
            }
        }

        if (empty($champs)) {
            return;
        }

        $valeurs[] = $id;
        $this->db->prepare('UPDATE plans SET ' . implode(', ', $champs) . ' WHERE id = ?')
            ->execute($valeurs);
    }
}
