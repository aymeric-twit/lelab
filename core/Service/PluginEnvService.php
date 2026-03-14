<?php

namespace Platform\Service;

use PDO;

/**
 * Service de gestion des clés d'environnement des plugins.
 * Extrait de AdminPluginController.
 */
class PluginEnvService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Retourne la liste des clés d'environnement autorisées (déclarées par les modules actifs).
     *
     * @return string[]
     */
    public function clesAutorisees(): array
    {
        $modules = $this->db->query('SELECT cles_env FROM modules WHERE enabled = 1 AND cles_env IS NOT NULL')->fetchAll();

        $cles = [];
        foreach ($modules as $mod) {
            $envKeys = json_decode($mod['cles_env'], true);
            if (is_array($envKeys)) {
                $cles = array_merge($cles, $envKeys);
            }
        }

        return array_unique($cles);
    }

    /**
     * Met à jour une clé dans le fichier .env et dans l'environnement courant.
     *
     * @return bool true si la clé est autorisée et a été mise à jour
     */
    public function mettreAJourCle(string $cle, string $valeur): bool
    {
        if (!in_array($cle, $this->clesAutorisees(), true)) {
            return false;
        }

        $envPath = dirname(__DIR__, 2) . '/.env';
        $this->ecrireDansEnv($envPath, $cle, $valeur);

        $_ENV[$cle] = $valeur;
        putenv("{$cle}={$valeur}");

        return true;
    }

    /**
     * Résout la valeur d'une clé d'environnement depuis $_ENV ou getenv().
     */
    public function resoudreCle(string $cle): string
    {
        $valeur = array_key_exists($cle, $_ENV) ? (string) $_ENV[$cle] : '';
        if ($valeur === '') {
            $envGetenv = getenv($cle);
            $valeur = $envGetenv !== false ? $envGetenv : '';
        }
        return $valeur;
    }

    /**
     * Écrit ou met à jour une clé dans le fichier .env.
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
