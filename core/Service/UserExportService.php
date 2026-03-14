<?php

namespace Platform\Service;

use Platform\Database\Connection;
use Platform\Module\Quota;
use Platform\User\AccessControl;
use PDO;

/**
 * Service d'export/import des utilisateurs et quotas au format CSV.
 */
class UserExportService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * Exporte les utilisateurs au format CSV (flux direct vers php://output).
     *
     * @param array{role?: string, actif?: string} $filtres
     */
    public function exporterCsv(array $filtres = []): void
    {
        $conditions = ['u.deleted_at IS NULL'];
        $params = [];

        if (!empty($filtres['role']) && in_array($filtres['role'], ['admin', 'user'], true)) {
            $conditions[] = 'u.role = ?';
            $params[] = $filtres['role'];
        }

        if (isset($filtres['actif']) && $filtres['actif'] !== '') {
            $conditions[] = 'u.active = ?';
            $params[] = (int) $filtres['actif'];
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.email, u.domaine, u.role, u.active,
                    u.last_login, u.created_at, p.nom AS plan_nom
             FROM users u
             LEFT JOIN plans p ON p.id = u.plan_id
             {$where}
             ORDER BY u.id"
        );
        $stmt->execute($params);

        // Modules et quotas
        $ac = new AccessControl();
        $modules = $this->db->query('SELECT id, slug, name FROM modules WHERE enabled = 1 ORDER BY sort_order')->fetchAll();
        $moduleSlugs = array_column($modules, 'slug');
        $moduleNames = array_column($modules, 'name');

        // Headers CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="utilisateurs_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fprintf($output, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel

        // En-tête
        $entetes = ['ID', 'Nom utilisateur', 'Email', 'Domaine', 'Rôle', 'Actif', 'Plan', 'Dernière connexion', 'Inscrit le'];
        foreach ($moduleNames as $name) {
            $entetes[] = "Accès: {$name}";
            $entetes[] = "Usage: {$name}";
            $entetes[] = "Quota: {$name}";
        }
        fputcsv($output, $entetes, ';');

        // Données
        while ($user = $stmt->fetch()) {
            $userId = (int) $user['id'];
            $quotas = Quota::getUserQuotaSummary($userId);

            $ligne = [
                $user['id'],
                $user['username'],
                $user['email'] ?? '',
                $user['domaine'] ?? '',
                $user['role'],
                $user['active'] ? 'Oui' : 'Non',
                $user['plan_nom'] ?? '-',
                $user['last_login'] ?? '-',
                $user['created_at'] ?? '-',
            ];

            foreach ($moduleSlugs as $slug) {
                $aAcces = $ac->hasAccess($userId, $slug) ? 'Oui' : 'Non';
                $usage = $quotas[$slug]['usage'] ?? 0;
                $limit = $quotas[$slug]['limit'] ?? 0;
                $ligne[] = $aAcces;
                $ligne[] = $usage;
                $ligne[] = $limit === 0 ? 'Illimité' : $limit;
            }

            fputcsv($output, $ligne, ';');
        }

        fclose($output);
    }

    /**
     * Importe des utilisateurs depuis un CSV.
     * Format attendu : username;email;domaine;role;password
     *
     * @return array{importes: int, erreurs: string[]}
     */
    public function importerCsv(string $cheminFichier): array
    {
        $handle = fopen($cheminFichier, 'r');
        if (!$handle) {
            return ['importes' => 0, 'erreurs' => ['Impossible d\'ouvrir le fichier.']];
        }

        // Ignorer BOM UTF-8
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Ignorer l'en-tête
        fgetcsv($handle, 0, ';');

        $importes = 0;
        $erreurs = [];
        $ligne = 1;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $ligne++;

            if (count($data) < 5) {
                $erreurs[] = "Ligne {$ligne} : données insuffisantes (5 colonnes attendues).";
                continue;
            }

            [$username, $email, $domaine, $role, $password] = array_map('trim', array_slice($data, 0, 5));

            if ($username === '' || $password === '') {
                $erreurs[] = "Ligne {$ligne} : username ou mot de passe vide.";
                continue;
            }

            if (!in_array($role, ['admin', 'user'], true)) {
                $role = 'user';
            }

            // Vérifier unicité
            $stmtCheck = $this->db->prepare('SELECT id FROM users WHERE username = ? AND deleted_at IS NULL');
            $stmtCheck->execute([$username]);
            if ($stmtCheck->fetch()) {
                $erreurs[] = "Ligne {$ligne} : l'utilisateur « {$username} » existe déjà.";
                continue;
            }

            try {
                $token = bin2hex(random_bytes(32));
                $this->db->prepare(
                    'INSERT INTO users (username, email, domaine, password_hash, role, active, unsubscribe_token) VALUES (?, ?, ?, ?, ?, 1, ?)'
                )->execute([
                    $username,
                    $email !== '' ? $email : null,
                    $domaine !== '' ? $domaine : null,
                    \Platform\Auth\PasswordHasher::hash($password),
                    $role,
                    $token,
                ]);
                $importes++;
            } catch (\Throwable $e) {
                $erreurs[] = "Ligne {$ligne} : " . $e->getMessage();
            }
        }

        fclose($handle);
        return ['importes' => $importes, 'erreurs' => $erreurs];
    }
}
