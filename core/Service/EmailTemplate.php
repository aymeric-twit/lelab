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

        extract($donnees);
        ob_start();
        require $chemin;
        return ob_get_clean();
    }
}
