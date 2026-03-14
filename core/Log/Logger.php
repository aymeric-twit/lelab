<?php

namespace Platform\Log;

class Logger
{
    private static ?string $logDir = null;
    private static ?string $requestId = null;

    public static function init(string $logDir): void
    {
        self::$logDir = $logDir;
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        // Générer un ID de requête unique pour la corrélation
        self::$requestId = substr(bin2hex(random_bytes(8)), 0, 12);
    }

    /**
     * Retourne l'ID de requête unique (pour les headers, logs, debugging).
     */
    public static function requestId(): ?string
    {
        return self::$requestId;
    }

    public static function error(string $message, array $contexte = []): void
    {
        self::ecrire('ERROR', $message, $contexte);
    }

    public static function warning(string $message, array $contexte = []): void
    {
        self::ecrire('WARNING', $message, $contexte);
    }

    public static function info(string $message, array $contexte = []): void
    {
        self::ecrire('INFO', $message, $contexte);
    }

    public static function debug(string $message, array $contexte = []): void
    {
        self::ecrire('DEBUG', $message, $contexte);
    }

    private static function ecrire(string $niveau, string $message, array $contexte): void
    {
        if (self::$logDir === null) {
            return;
        }

        $fichier = self::$logDir . '/platform-' . date('Y-m-d') . '.log';
        $horodatage = date('Y-m-d H:i:s');

        $entry = [
            'timestamp'  => $horodatage,
            'level'      => $niveau,
            'request_id' => self::$requestId,
            'message'    => $message,
        ];

        if ($contexte !== []) {
            $entry['context'] = $contexte;
        }

        $ligne = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        file_put_contents($fichier, $ligne, FILE_APPEND | LOCK_EX);
    }

    /**
     * Rotation : supprime les fichiers de log plus anciens que N jours.
     */
    public static function rotation(int $joursAConserver = 30): int
    {
        if (self::$logDir === null) {
            return 0;
        }

        $seuil = time() - ($joursAConserver * 86400);
        $fichiers = glob(self::$logDir . '/platform-*.log');
        $supprimes = 0;

        foreach ($fichiers as $fichier) {
            if (filemtime($fichier) < $seuil) {
                unlink($fichier);
                $supprimes++;
            }
        }

        return $supprimes;
    }
}
