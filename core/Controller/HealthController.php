<?php

namespace Platform\Controller;

use Platform\Database\Connection;
use Platform\Http\Request;
use Platform\Http\Response;

class HealthController
{
    public function index(Request $req, array $params): void
    {
        $status = 'ok';
        $checks = [];

        // Vérification BDD
        try {
            $db = Connection::get();
            $db->query('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'erreur';
            $status = 'degraded';
        }

        // Vérification filesystem (storage writable)
        $storagePath = dirname(__DIR__, 2) . '/storage';
        $checks['storage'] = is_writable($storagePath) ? 'ok' : 'erreur';
        if ($checks['storage'] !== 'ok') {
            $status = 'degraded';
        }

        // Vérification logs writable
        $logsPath = $storagePath . '/logs';
        $checks['logs'] = is_dir($logsPath) && is_writable($logsPath) ? 'ok' : 'erreur';
        if ($checks['logs'] !== 'ok') {
            $status = 'degraded';
        }

        // Version PHP
        $checks['php_version'] = PHP_VERSION;

        // Uptime approximatif (basé sur la date du fichier .env)
        $envPath = dirname(__DIR__, 2) . '/.env';
        $checks['env_exists'] = file_exists($envPath) ? 'ok' : 'manquant';

        $httpCode = $status === 'ok' ? 200 : 503;
        http_response_code($httpCode);

        Response::json([
            'status'     => $status,
            'checks'     => $checks,
            'timestamp'  => date('c'),
        ]);
    }
}
