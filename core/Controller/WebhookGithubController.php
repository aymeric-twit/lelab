<?php

namespace Platform\Controller;

use Platform\Database\Connection;
use Platform\Http\Response;
use Platform\Log\Logger;
use Platform\Module\PluginInstaller;

class WebhookGithubController
{
    /**
     * POST /webhook/github
     *
     * Endpoint public protégé par signature HMAC-SHA256.
     * Déclenche un git pull sur le plugin correspondant au repo qui a pushé.
     */
    public function handle(): never
    {
        $payload = file_get_contents('php://input');
        if ($payload === false || $payload === '') {
            Response::abort(400, 'Payload vide.');
        }

        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        if ($signature === '') {
            Response::abort(403, 'Signature manquante.');
        }

        if (!$this->validerSignature($payload, $signature)) {
            Response::abort(403, 'Signature invalide.');
        }

        $evenement = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
        if ($evenement !== 'push') {
            // On ne traite que les events push
            Response::json(['message' => 'Événement ignoré.']);
        }

        $donnees = json_decode($payload, true);
        if (!is_array($donnees)) {
            Response::abort(400, 'Payload JSON invalide.');
        }

        // Extraire l'URL du repo (format HTTPS)
        $repoUrl = $donnees['repository']['html_url'] ?? null;
        if ($repoUrl === null) {
            Response::abort(400, 'URL du dépôt manquante dans le payload.');
        }

        // Normaliser : retirer le .git éventuel
        $repoUrl = rtrim($repoUrl, '/');

        $db = Connection::get();

        // Chercher le module par git_url (avec ou sans .git)
        $stmt = $db->prepare('
            SELECT id, slug, git_url FROM modules
            WHERE git_url = :url1 OR git_url = :url2
            LIMIT 1
        ');
        $stmt->execute([
            'url1' => $repoUrl,
            'url2' => $repoUrl . '.git',
        ]);
        $module = $stmt->fetch();

        if (!$module) {
            Response::json(['message' => 'Aucun plugin correspondant trouvé.'], 404);
        }

        $installer = new PluginInstaller($db);

        try {
            $resultat = $installer->mettreAJourDepuisGit((int) $module['id']);

            Logger::info("Webhook GitHub : plugin « {$module['slug']} » mis à jour (commit: {$resultat['commit']})");

            Response::json([
                'message' => 'Plugin mis à jour.',
                'slug'    => $module['slug'],
                'commit'  => $resultat['commit'],
                'version' => $resultat['version'],
            ]);
        } catch (\Throwable $e) {
            Logger::error("Webhook GitHub : échec MAJ plugin « {$module['slug']} » — {$e->getMessage()}");

            Response::json(['message' => 'Échec de la mise à jour.', 'erreur' => $e->getMessage()], 500);
        }
    }

    /**
     * Valide la signature HMAC-SHA256 du webhook GitHub.
     */
    private function validerSignature(string $payload, string $signature): bool
    {
        $secret = $_ENV['GITHUB_WEBHOOK_SECRET'] ?? ($_SERVER['GITHUB_WEBHOOK_SECRET'] ?? '');

        if ($secret === '') {
            return false;
        }

        $attendu = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($attendu, $signature);
    }
}
