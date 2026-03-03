<?php

namespace Platform\Module;

class GitClient
{
    private ?string $token;

    public function __construct(?string $token = null)
    {
        $this->token = $token ?? $this->chargerTokenDepuisConfig();
    }

    /**
     * Clone un repo dans $destination (shallow clone --depth 1).
     */
    public function cloner(string $url, string $destination, string $branche = 'main'): bool
    {
        if (!self::validerUrl($url)) {
            return false;
        }

        $urlAuth = $this->construireUrlAuthentifiee($url);

        $resultat = $this->executerGit([
            'git', 'clone',
            '--depth', '1',
            '--branch', $branche,
            '--single-branch',
            $urlAuth,
            $destination,
        ]);

        if ($resultat['code'] !== 0) {
            // Nettoyage du dossier partiel si le clone échoue
            if (is_dir($destination)) {
                self::supprimerRepertoireRecursif($destination);
            }
            return false;
        }

        return true;
    }

    /**
     * Pull les dernières modifications.
     */
    public function pull(string $repertoire): bool
    {
        if (!is_dir($repertoire . '/.git')) {
            return false;
        }

        $resultat = $this->executerGit(['git', 'pull', '--ff-only'], $repertoire);

        return $resultat['code'] === 0;
    }

    /**
     * Retourne le hash du HEAD courant.
     */
    public function getDernierCommit(string $repertoire): ?string
    {
        if (!is_dir($repertoire . '/.git')) {
            return null;
        }

        $resultat = $this->executerGit(['git', 'rev-parse', 'HEAD'], $repertoire);

        if ($resultat['code'] !== 0) {
            return null;
        }

        $hash = trim($resultat['stdout']);

        return $hash !== '' ? $hash : null;
    }

    /**
     * Liste les branches distantes d'un dépôt Git sans cloner.
     *
     * @return list<string> Noms des branches (ex: ['dev', 'main'])
     */
    public function listerBranchesDistantes(string $url): array
    {
        if (!self::validerUrl($url)) {
            return [];
        }

        $urlAuth = $this->construireUrlAuthentifiee($url);

        $resultat = $this->executerGit(['git', 'ls-remote', '--heads', $urlAuth]);

        if ($resultat['code'] !== 0 || $resultat['stdout'] === '') {
            return [];
        }

        $branches = [];
        foreach (explode("\n", trim($resultat['stdout'])) as $ligne) {
            // Format : "{hash}\trefs/heads/{branche}"
            if (preg_match('#refs/heads/(.+)$#', $ligne, $m)) {
                $branches[] = $m[1];
            }
        }

        sort($branches);

        return $branches;
    }

    /**
     * Change la branche d'un clone --single-branch existant.
     *
     * Étapes : élargir le tracking → fetch la branche → checkout.
     */
    public function changerBranche(string $repertoire, string $branche): bool
    {
        if (!is_dir($repertoire . '/.git')) {
            return false;
        }

        // Élargir le tracking pour autoriser d'autres branches
        $r1 = $this->executerGit(
            ['git', 'remote', 'set-branches', 'origin', '*'],
            $repertoire
        );
        if ($r1['code'] !== 0) {
            return false;
        }

        // Fetch la branche cible
        $r2 = $this->executerGit(
            ['git', 'fetch', 'origin', $branche, '--depth', '1'],
            $repertoire
        );
        if ($r2['code'] !== 0) {
            return false;
        }

        // Checkout sur la branche
        $r3 = $this->executerGit(
            ['git', 'checkout', $branche],
            $repertoire
        );

        return $r3['code'] === 0;
    }

    /**
     * Vérifie que l'URL est un repo GitHub/GitLab valide.
     */
    public static function validerUrl(string $url): bool
    {
        return (bool) preg_match(
            '#^https://(github\.com|gitlab\.com)/[\w.\-]+/[\w.\-]+(\.git)?$#',
            $url
        );
    }

    /**
     * Extrait le slug (dernier segment sans .git) depuis une URL de repo.
     */
    public static function extraireSlug(string $url): ?string
    {
        if (!self::validerUrl($url)) {
            return null;
        }

        $chemin = parse_url($url, PHP_URL_PATH);
        if ($chemin === null || $chemin === false) {
            return null;
        }

        $segments = explode('/', trim($chemin, '/'));
        $dernier = end($segments);

        if ($dernier === '') {
            return null;
        }

        // Retirer l'extension .git si présente
        if (str_ends_with($dernier, '.git')) {
            $dernier = substr($dernier, 0, -4);
        }

        return $dernier !== '' ? strtolower($dernier) : null;
    }

    /**
     * Construit l'URL authentifiée (https://TOKEN@github.com/...).
     */
    private function construireUrlAuthentifiee(string $url): string
    {
        if ($this->token === null || $this->token === '') {
            return $url;
        }

        // Insérer le token après https://
        return (string) preg_replace(
            '#^https://#',
            'https://' . $this->token . '@',
            $url
        );
    }

    /**
     * Charge le token GitHub depuis l'environnement ou la config.
     */
    private function chargerTokenDepuisConfig(): ?string
    {
        // Variable d'environnement prioritaire
        $envToken = $_ENV['GITHUB_TOKEN'] ?? ($_SERVER['GITHUB_TOKEN'] ?? null);
        if ($envToken !== null && $envToken !== '') {
            return $envToken;
        }

        // Fichier de config optionnel
        $fichierConfig = dirname(__DIR__, 2) . '/config/github.php';
        if (is_file($fichierConfig)) {
            /** @var array{token?: string} $config */
            $config = require $fichierConfig;
            if (!empty($config['token'])) {
                return $config['token'];
            }
        }

        return null;
    }

    /**
     * Exécute une commande git via proc_open (tableau args, pas de shell).
     *
     * @param list<string> $commande
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function executerGit(array $commande, ?string $cwd = null): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        // Désactiver les prompts interactifs de git
        $envVars = getenv();
        $envVars['GIT_TERMINAL_PROMPT'] = '0';
        $env = $envVars;

        $process = proc_open($commande, $descriptors, $pipes, $cwd, $env);

        if (!is_resource($process)) {
            return ['code' => -1, 'stdout' => '', 'stderr' => 'Impossible de lancer le processus git.'];
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $code = proc_close($process);

        return [
            'code'   => $code,
            'stdout' => $stdout !== false ? $stdout : '',
            'stderr' => $stderr !== false ? $stderr : '',
        ];
    }

    /**
     * Suppression récursive d'un répertoire (nettoyage après clone échoué).
     */
    private static function supprimerRepertoireRecursif(string $chemin): void
    {
        if (!is_dir($chemin)) {
            return;
        }

        $elements = scandir($chemin);
        if ($elements === false) {
            return;
        }

        foreach ($elements as $element) {
            if ($element === '.' || $element === '..') {
                continue;
            }

            $cheminComplet = $chemin . '/' . $element;

            if (is_link($cheminComplet)) {
                unlink($cheminComplet);
            } elseif (is_dir($cheminComplet)) {
                self::supprimerRepertoireRecursif($cheminComplet);
            } else {
                unlink($cheminComplet);
            }
        }

        rmdir($chemin);
    }
}
