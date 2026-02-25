<?php

namespace Platform\Log;

class Logger
{
    private static ?string $logDir = null;

    public static function init(string $logDir): void
    {
        self::$logDir = $logDir;
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
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

    private static function ecrire(string $niveau, string $message, array $contexte): void
    {
        if (self::$logDir === null) {
            return;
        }

        $fichier = self::$logDir . '/platform-' . date('Y-m-d') . '.log';
        $horodatage = date('Y-m-d H:i:s');
        $contexteStr = $contexte !== [] ? ' ' . json_encode($contexte, JSON_UNESCAPED_UNICODE) : '';

        $ligne = "[{$horodatage}] {$niveau}: {$message}{$contexteStr}" . PHP_EOL;

        file_put_contents($fichier, $ligne, FILE_APPEND | LOCK_EX);
    }
}
