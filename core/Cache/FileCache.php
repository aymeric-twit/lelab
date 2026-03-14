<?php

namespace Platform\Cache;

/**
 * Implémentation du cache basée sur le système de fichiers.
 * Chaque clé est sérialisée dans un fichier JSON avec TTL.
 */
class FileCache implements CacheInterface
{
    private string $repertoire;

    public function __construct(?string $repertoire = null)
    {
        $this->repertoire = $repertoire ?? dirname(__DIR__, 2) . '/storage/cache/app';
        if (!is_dir($this->repertoire)) {
            mkdir($this->repertoire, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $fichier = $this->cheminFichier($key);
        if (!file_exists($fichier)) {
            return $default;
        }

        $contenu = file_get_contents($fichier);
        $donnees = json_decode($contenu, true);

        if (!is_array($donnees) || !isset($donnees['expire_at'], $donnees['value'])) {
            $this->delete($key);
            return $default;
        }

        if ($donnees['expire_at'] > 0 && $donnees['expire_at'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $donnees['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $fichier = $this->cheminFichier($key);
        $donnees = [
            'expire_at' => $ttl > 0 ? time() + $ttl : 0,
            'value'     => $value,
        ];

        return file_put_contents(
            $fichier,
            json_encode($donnees, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        ) !== false;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    public function delete(string $key): bool
    {
        $fichier = $this->cheminFichier($key);
        if (file_exists($fichier)) {
            return unlink($fichier);
        }
        return true;
    }

    public function clear(): bool
    {
        $fichiers = glob($this->repertoire . '/*.cache');
        foreach ($fichiers as $f) {
            unlink($f);
        }
        return true;
    }

    /**
     * Récupère une valeur du cache, ou la calcule et la stocke si absente.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    private function cheminFichier(string $key): string
    {
        return $this->repertoire . '/' . md5($key) . '.cache';
    }
}
