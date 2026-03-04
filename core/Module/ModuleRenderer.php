<?php

namespace Platform\Module;

use Platform\Auth\Auth;
use Platform\Log\Logger;

class ModuleRenderer
{
    /**
     * Render a module's entry point and capture its output.
     * Extracts body content, styles, and scripts for injection into the platform layout.
     */
    public static function render(ModuleDescriptor $module, ?string $subRoute = null): array
    {
        $file = $subRoute
            ? $module->path . '/' . $subRoute
            : $module->getEntryFile();

        // Protection path traversal : vérifier que le fichier reste dans le répertoire du module
        if ($subRoute && !self::verifierCheminDansModule($file, $module->path)) {
            return [
                'content' => '<div class="alert alert-danger">Fichier non autorisé.</div>',
                'headExtra' => '',
                'footExtra' => '',
            ];
        }

        if (!file_exists($file)) {
            return [
                'content' => '<div class="alert alert-danger">Module file not found.</div>',
                'headExtra' => '',
                'footExtra' => '',
            ];
        }

        // Boot the module if boot.php exists
        $bootFile = $module->path . '/boot.php';
        if (file_exists($bootFile)) {
            require_once $bootFile;
        }

        // Signal aux plugins qu'ils tournent dans la plateforme
        if (!defined('PLATFORM_EMBEDDED')) {
            define('PLATFORM_EMBEDDED', true);
        }

        self::definirConstanteDomaine();

        // Set module context
        $oldCwd = getcwd();
        chdir($module->path);

        // Capture output
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            chdir($oldCwd);
            Logger::error('Erreur module ' . $module->slug, [
                'message' => $e->getMessage(),
                'fichier' => $e->getFile(),
                'ligne'   => $e->getLine(),
            ]);
            return [
                'content' => '<div class="alert alert-danger">Erreur du module : ' . htmlspecialchars($e->getMessage()) . '</div>',
                'headExtra' => '',
                'footExtra' => '',
            ];
        }
        $html = ob_get_clean();
        chdir($oldCwd);

        return self::extractParts($html, $module->slug);
    }

    /**
     * Génère le HTML d'un iframe pointant vers l'app complète du module.
     *
     * @return array{content: string, headExtra: string, footExtra: string}
     */
    public static function renderIframe(ModuleDescriptor $module): array
    {
        $slug = htmlspecialchars($module->slug, ENT_QUOTES, 'UTF-8');
        $iframeSrc = "/m/{$slug}/_app";

        return [
            'content' => '<iframe src="' . $iframeSrc . '" class="module-iframe" allowfullscreen></iframe>',
            'headExtra' => '',
            'footExtra' => '',
        ];
    }

    /**
     * Sert le HTML complet du module en mode iframe (passthrough avec contexte).
     * Le module s'exécute comme une app autonome dans l'iframe.
     *
     * Réécriture de REQUEST_URI : les modules iframe avec routeur interne
     * lisent $_SERVER['REQUEST_URI'] pour dispatcher leurs routes.
     * Sans réécriture, le routeur verrait '/m/{slug}/api/analyser' au lieu de '/api/analyser'.
     */
    public static function servirApp(ModuleDescriptor $module, ?string $subRoute = null): void
    {
        // Protection path traversal
        if ($subRoute && !self::verifierCheminDansModule($module->path . '/' . $subRoute, $module->path)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        $file = $subRoute
            ? $module->path . '/' . $subRoute
            : $module->getEntryFile();

        // Si le fichier sous-route n'existe pas, router via l'entry point
        // (le module a probablement son propre routeur interne)
        $queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
        if ($subRoute && !file_exists($file)) {
            $file = $module->getEntryFile();
            $_SERVER['REQUEST_URI'] = '/' . $subRoute . $queryString;
        } elseif (!$subRoute) {
            // Chargement initial (_app) — le routeur interne attend '/'
            $_SERVER['REQUEST_URI'] = '/' . $queryString;
        }

        if (!file_exists($file)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $bootFile = $module->path . '/boot.php';
        if (file_exists($bootFile)) {
            require_once $bootFile;
        }

        if (!defined('PLATFORM_IFRAME')) {
            define('PLATFORM_IFRAME', true);
        }

        self::definirConstanteDomaine();

        $oldCwd = getcwd();
        chdir($module->path);

        try {
            require $file;
        } finally {
            chdir($oldCwd);
        }
    }

    /**
     * Sert un fichier statique du module avec le bon Content-Type.
     * Retourne true si le fichier a été servi, false si ce n'est pas un asset statique.
     */
    public static function servirAssetStatique(ModuleDescriptor $module, string $subRoute): bool
    {
        $ext = strtolower(pathinfo($subRoute, PATHINFO_EXTENSION));

        $mimeTypes = [
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'json'  => 'application/json',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'map'   => 'application/json',
        ];

        if (!isset($mimeTypes[$ext])) {
            return false;
        }

        $filePath = realpath($module->path . '/' . $subRoute);
        $modulePath = realpath($module->path);

        if (!$filePath || !$modulePath || !str_starts_with($filePath, $modulePath)) {
            return false;
        }

        header('Content-Type: ' . $mimeTypes[$ext]);
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        exit;
    }

    /**
     * Execute a sub-route in passthrough mode (no layout wrapping).
     * Used for AJAX endpoints, SSE streams, etc.
     */
    public static function passthrough(ModuleDescriptor $module, string $subRoute): void
    {
        $file = $module->path . '/' . $subRoute;

        // Protection path traversal
        if (!self::verifierCheminDansModule($file, $module->path)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        if (!file_exists($file)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $bootFile = $module->path . '/boot.php';
        if (file_exists($bootFile)) {
            require_once $bootFile;
        }

        self::definirConstanteDomaine();

        $oldCwd = getcwd();
        chdir($module->path);

        try {
            require $file;
        } finally {
            chdir($oldCwd);
        }
    }

    /**
     * Extract body content, styles, and scripts from a full HTML page.
     */
    private static function extractParts(string $html, string $slug): array
    {
        $headExtra = '';
        $footExtra = '';
        $content = $html;

        // If the output is a full HTML page, extract parts
        if (stripos($html, '<html') !== false || stripos($html, '<!doctype') !== false) {
            // Extract styles from <head>
            if (preg_match('#<head[^>]*>(.*?)</head>#si', $html, $headMatch)) {
                $headContent = $headMatch[1];
                // Extract <style> blocks
                preg_match_all('#<style[^>]*>.*?</style>#si', $headContent, $styles);
                $headExtra .= implode("\n", $styles[0]);

                // Extract <link rel="stylesheet"> tags (only local ones)
                preg_match_all('#<link[^>]*rel=["\']stylesheet["\'][^>]*/?>|<link[^>]*/?\s*>#si', $headContent, $links);
                foreach ($links[0] as $link) {
                    // Skip CDN links (bootstrap, etc.) - they're already in the layout
                    if (preg_match('#(cdn|jsdelivr|googleapis|cloudflare|bootstrap)#i', $link)) {
                        continue;
                    }
                    $link = self::rewriteAssetPaths($link, $slug);
                    $headExtra .= "\n" . $link;
                }
            }

            // Extract body content
            if (preg_match('#<body[^>]*>(.*)</body>#si', $html, $bodyMatch)) {
                $content = $bodyMatch[1];
            }

            // Remove navbar elements from module (we use our own)
            $content = preg_replace('#<nav[^>]*class="[^"]*navbar[^"]*"[^>]*>.*?</nav>#si', '', $content);
            $content = preg_replace('#<header[^>]*class="[^"]*navbar[^"]*"[^>]*>.*?</header>#si', '', $content);

            // Extract <script> tags from end of body
            preg_match_all('#<script[^>]*>.*?</script>#si', $content, $scripts);
            foreach ($scripts[0] as $script) {
                // CDN scripts: remove from content (already in layout) but don't add to footExtra
                if (preg_match('#(cdn|jsdelivr|googleapis|cloudflare|bootstrap)#i', $script)) {
                    $content = str_replace($script, '', $content);
                    continue;
                }
                $original = $script;
                $script = self::rewriteAssetPaths($script, $slug);
                $footExtra .= "\n" . $script;
                $content = str_replace($original, '', $content);
            }
        }

        // Rewrite remaining asset paths in content
        $content = self::rewriteAssetPaths($content, $slug);

        // Inject CSRF field into POST forms
        $csrfField = \Platform\Http\Csrf::field();
        $content = preg_replace(
            '#(<form[^>]*method=["\']POST["\'][^>]*>)#i',
            '$1' . "\n" . $csrfField,
            $content
        );

        return [
            'content' => '<div class="module-content">' . $content . '</div>',
            'headExtra' => $headExtra,
            'footExtra' => $footExtra,
        ];
    }

    /**
     * Rewrite relative asset paths to go through /module-assets/{slug}/
     */
    private static function rewriteAssetPaths(string $html, string $slug): string
    {
        $prefix = "/module-assets/{$slug}/";

        // Rewrite src="relative" (not starting with / or http)
        $html = preg_replace_callback(
            '#(src=["\'])(?!https?://|/|data:)([^"\']+)(["\'])#i',
            fn($m) => $m[1] . $prefix . $m[2] . $m[3],
            $html
        );

        // Rewrite href="relative.css" (only for CSS files, not anchors)
        $html = preg_replace_callback(
            '#(href=["\'])(?!https?://|/|\#)([^"\']+\.css(?:\?[^"\']*)?)(["\'])#i',
            fn($m) => $m[1] . $prefix . $m[2] . $m[3],
            $html
        );

        return $html;
    }

    /**
     * Vérifie qu'un chemin de fichier reste bien à l'intérieur du répertoire du module.
     * Bloque les tentatives de path traversal (../../).
     */
    private static function verifierCheminDansModule(string $cheminFichier, string $cheminModule): bool
    {
        $moduleReel = realpath($cheminModule);
        if (!$moduleReel) {
            return false;
        }

        // realpath() échoue si le fichier n'existe pas — résoudre manuellement les ../
        $cheminResolu = realpath($cheminFichier);
        if ($cheminResolu) {
            return str_starts_with($cheminResolu, $moduleReel . '/');
        }

        // Le fichier n'existe pas encore (sera routé vers l'entry point dans servirApp)
        // Vérifier que le chemin canonisé ne sort pas du module
        $cheminNormalise = realpath(dirname($cheminFichier));
        return $cheminNormalise !== false && str_starts_with($cheminNormalise, $moduleReel);
    }

    /**
     * Définit la constante PLATFORM_USER_DOMAIN avec le domaine de l'utilisateur connecté.
     * Utilisable par les plugins en PHP : defined('PLATFORM_USER_DOMAIN') ? PLATFORM_USER_DOMAIN : ''
     */
    private static function definirConstanteDomaine(): void
    {
        if (defined('PLATFORM_USER_DOMAIN')) {
            return;
        }

        $user = Auth::user();
        $domaine = $user['domaine'] ?? '';
        define('PLATFORM_USER_DOMAIN', $domaine);
    }
}
