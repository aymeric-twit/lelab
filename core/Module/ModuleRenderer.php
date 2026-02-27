<?php

namespace Platform\Module;

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
     */
    public static function servirApp(ModuleDescriptor $module, ?string $subRoute = null): void
    {
        $file = $subRoute
            ? $module->path . '/' . $subRoute
            : $module->getEntryFile();

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

        if (!file_exists($file)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $bootFile = $module->path . '/boot.php';
        if (file_exists($bootFile)) {
            require_once $bootFile;
        }

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
}
