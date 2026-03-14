<?php

namespace Platform\Http\Middleware;

use Platform\Http\Request;
use Platform\Http\Response;

/**
 * Middleware de rate limiting pour les endpoints API.
 * Utilise un fichier cache simple (token bucket par IP).
 */
class RateLimitApi
{
    private int $maxRequetes;
    private int $fenetreSecondes;

    /**
     * @param int $maxRequetes     Nombre max de requêtes par fenêtre
     * @param int $fenetreSecondes Durée de la fenêtre en secondes
     */
    public function __construct(int $maxRequetes = 60, int $fenetreSecondes = 60)
    {
        $this->maxRequetes = $maxRequetes;
        $this->fenetreSecondes = $fenetreSecondes;
    }

    public function __invoke(Request $req, callable $next): mixed
    {
        $ip = $req->ip();
        $cle = 'api_' . md5($ip);

        $cachePath = dirname(__DIR__, 3) . '/storage/cache/rate_limit';
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $fichier = $cachePath . '/' . $cle . '.json';
        $maintenant = time();

        $donnees = $this->charger($fichier);

        // Purger les entrées expirées
        $donnees = array_filter($donnees, fn(int $ts) => $ts > $maintenant - $this->fenetreSecondes);

        $restant = max(0, $this->maxRequetes - count($donnees));

        // Headers de rate limiting (standard)
        header('X-RateLimit-Limit: ' . $this->maxRequetes);
        header('X-RateLimit-Remaining: ' . $restant);
        header('X-RateLimit-Reset: ' . ($maintenant + $this->fenetreSecondes));

        if (count($donnees) >= $this->maxRequetes) {
            $retryAfter = $this->fenetreSecondes - ($maintenant - min($donnees));
            header('Retry-After: ' . $retryAfter);
            Response::json([
                'erreur'  => 'Trop de requêtes. Réessayez dans ' . $retryAfter . ' secondes.',
                'message' => 'Rate limit exceeded',
            ], 429);
        }

        // Enregistrer cette requête
        $donnees[] = $maintenant;
        $this->sauvegarder($fichier, $donnees);

        return $next($req);
    }

    /**
     * @return int[]
     */
    private function charger(string $fichier): array
    {
        if (!file_exists($fichier)) {
            return [];
        }

        $contenu = file_get_contents($fichier);
        $donnees = json_decode($contenu, true);

        return is_array($donnees) ? $donnees : [];
    }

    /**
     * @param int[] $donnees
     */
    private function sauvegarder(string $fichier, array $donnees): void
    {
        file_put_contents($fichier, json_encode(array_values($donnees)), LOCK_EX);
    }
}
