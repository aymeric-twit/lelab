<?php

namespace Platform\Service;

class EmailTemplate
{
    /**
     * Rend un template email et retourne le HTML.
     *
     * @param string $template Nom du template (sans .php) dans templates/emails/
     * @param array<string, mixed> $donnees Variables injectées dans le template
     */
    public static function rendre(string $template, array $donnees = []): string
    {
        $chemin = __DIR__ . '/../../templates/emails/' . $template . '.php';

        if (!file_exists($chemin)) {
            throw new \RuntimeException("Template email introuvable : {$template}");
        }

        // Injection automatique des variables globales de footer
        $config = require __DIR__ . '/../../config/app.php';
        $baseUrl = rtrim($config['url'] ?? 'https://labs.yapasdequoi.com', '/');

        $donnees += [
            '_baseUrl' => $baseUrl,
            '_logoUrl' => $baseUrl . '/assets/img/logo-email.png',
            '_lienConfidentialite' => $baseUrl . '/politique-de-confidentialite',
            '_lienMentions' => $baseUrl . '/mentions-legales',
            '_lienDesabonnement' => null,
            '_estDesabonnable' => false,
        ];

        extract($donnees);
        ob_start();
        require $chemin;
        return ob_get_clean();
    }
}
