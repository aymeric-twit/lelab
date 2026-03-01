<?php

namespace Platform\Module;

use Platform\Database\Connection;

/**
 * Installe et vérifie les dépendances Composer/npm des plugins.
 */
class DependencyInstaller
{
    private const TIMEOUT_SECONDES = 120;

    /**
     * Installe les dépendances Composer et npm d'un plugin.
     *
     * @return array{composer: bool|null, npm: bool|null, erreurs: list<string>}
     *   null = pas de fichier détecté, true = succès, false = échec
     */
    public function installerDependances(string $cheminPlugin): array
    {
        $resultat = ['composer' => null, 'npm' => null, 'erreurs' => []];

        if (!is_dir($cheminPlugin)) {
            $resultat['erreurs'][] = "Répertoire introuvable : {$cheminPlugin}";
            return $resultat;
        }

        if (is_file($cheminPlugin . '/composer.json')) {
            $res = $this->executerCommande(
                ['composer', 'install', '--no-dev', '--no-interaction', '--no-progress'],
                $cheminPlugin
            );
            $resultat['composer'] = $res['code'] === 0;
            if (!$resultat['composer']) {
                $resultat['erreurs'][] = 'composer install a échoué : ' . trim($res['stderr']);
            }
        }

        if (is_file($cheminPlugin . '/package.json')) {
            $res = $this->executerCommande(
                ['npm', 'install', '--production', '--no-audit', '--no-fund'],
                $cheminPlugin
            );
            $resultat['npm'] = $res['code'] === 0;
            if (!$resultat['npm']) {
                $resultat['erreurs'][] = 'npm install a échoué : ' . trim($res['stderr']);
            }
        }

        return $resultat;
    }

    /**
     * Vérifie si les dépendances d'un plugin sont installées.
     *
     * @return array{composer: string|null, npm: string|null}
     *   null = pas de fichier détecté, 'ok' = dossier présent, 'manquant' = dossier absent
     */
    public function verifierDependances(string $cheminPlugin): array
    {
        $resultat = ['composer' => null, 'npm' => null];

        if (!is_dir($cheminPlugin)) {
            return $resultat;
        }

        if (is_file($cheminPlugin . '/composer.json')) {
            $resultat['composer'] = is_dir($cheminPlugin . '/vendor') ? 'ok' : 'manquant';
        }

        if (is_file($cheminPlugin . '/package.json')) {
            $resultat['npm'] = is_dir($cheminPlugin . '/node_modules') ? 'ok' : 'manquant';
        }

        return $resultat;
    }

    /**
     * Installe les dépendances de tous les plugins actifs.
     *
     * @return array<string, array{composer: bool|null, npm: bool|null, erreurs: list<string>}>
     */
    public function installerToutesLesDependances(): array
    {
        $db = Connection::get();
        $stmt = $db->query(
            'SELECT slug, chemin_source FROM modules WHERE enabled = 1 AND desinstalle_le IS NULL'
        );
        $modules = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $resultats = [];

        foreach ($modules as $module) {
            $chemin = $module['chemin_source'];
            if (empty($chemin) || !is_dir($chemin)) {
                continue;
            }

            // Ne traiter que les plugins qui ont des dépendances
            $aComposer = is_file($chemin . '/composer.json');
            $aNpm = is_file($chemin . '/package.json');

            if (!$aComposer && !$aNpm) {
                continue;
            }

            $resultats[$module['slug']] = $this->installerDependances($chemin);
        }

        return $resultats;
    }

    /**
     * Exécute une commande shell via proc_open avec timeout.
     *
     * @param list<string> $commande
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function executerCommande(array $commande, string $cwd): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($commande, $descriptors, $pipes, $cwd);

        if (!is_resource($process)) {
            $cmd = implode(' ', $commande);
            return ['code' => -1, 'stdout' => '', 'stderr' => "Impossible de lancer : {$cmd}"];
        }

        fclose($pipes[0]);

        // Timeout via stream_select
        $debut = time();
        $stdout = '';
        $stderr = '';

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                // Lire le reste
                $stdout .= stream_get_contents($pipes[1]);
                $stderr .= stream_get_contents($pipes[2]);
                break;
            }

            if ((time() - $debut) > self::TIMEOUT_SECONDES) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                return ['code' => -1, 'stdout' => $stdout, 'stderr' => 'Timeout dépassé.'];
            }

            $lecture = [$pipes[1], $pipes[2]];
            $ecriture = null;
            $except = null;
            if (stream_select($lecture, $ecriture, $except, 1) > 0) {
                foreach ($lecture as $flux) {
                    $chunk = fread($flux, 8192);
                    if ($chunk === false) {
                        continue;
                    }
                    if ($flux === $pipes[1]) {
                        $stdout .= $chunk;
                    } else {
                        $stderr .= $chunk;
                    }
                }
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $code = $status['exitcode'] ?? proc_close($process);

        return [
            'code'   => $code,
            'stdout' => $stdout !== false ? $stdout : '',
            'stderr' => $stderr !== false ? $stderr : '',
        ];
    }
}
