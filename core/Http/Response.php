<?php

namespace Platform\Http;

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    public static function status(int $code): void
    {
        http_response_code($code);
    }

    public static function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        echo $message ?: match ($code) {
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            429 => 'Too Many Requests',
            default => 'Error',
        };
        exit;
    }

    /**
     * Affiche une page d'erreur stylée (navigateur) ou du JSON (AJAX).
     */
    public static function abortAvecPage(int $code, string $message = ''): never
    {
        http_response_code($code);

        // AJAX → JSON
        if (self::estRequeteAjax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['erreur' => $message ?: self::messageParDefaut($code)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Navigateur → page d'erreur stylée
        $config = self::configErreur($code);
        $codeErreur = $code;
        $titreErreur = $config['titre'];
        $messageErreur = $message ?: $config['message'];
        $iconeErreur = $config['icone'];
        $couleurAccent = $config['couleur'];

        require __DIR__ . '/../../templates/erreur.php';
        exit;
    }

    private static function estRequeteAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    }

    private static function messageParDefaut(int $code): string
    {
        return match ($code) {
            403 => 'Accès refusé',
            404 => 'Page non trouvée',
            500 => 'Erreur serveur',
            default => 'Erreur',
        };
    }

    /**
     * @return array{titre: string, message: string, icone: string, couleur: string}
     */
    private static function configErreur(int $code): array
    {
        return match ($code) {
            403 => [
                'titre' => 'Accès refusé',
                'message' => 'Vous n\'avez pas la permission d\'accéder à cette page.',
                'icone' => 'bi-shield-x',
                'couleur' => 'var(--color-bad)',
            ],
            404 => [
                'titre' => 'Page non trouvée',
                'message' => 'La page que vous cherchez n\'existe pas ou a été déplacée.',
                'icone' => 'bi-search',
                'couleur' => 'var(--brand-gold)',
            ],
            default => [
                'titre' => 'Erreur serveur',
                'message' => 'Une erreur inattendue est survenue. Veuillez réessayer plus tard.',
                'icone' => 'bi-exclamation-triangle',
                'couleur' => 'var(--color-bad)',
            ],
        };
    }
}
