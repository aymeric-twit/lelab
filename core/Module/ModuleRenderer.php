<?php

namespace Platform\Module;

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
            return [
                'content' => '<div class="alert alert-danger">Module error: ' . htmlspecialchars($e->getMessage()) . '</div>',
                'headExtra' => '',
                'footExtra' => '',
            ];
        }
        $html = ob_get_clean();
        chdir($oldCwd);

        return self::extractParts($html, $module->slug);
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
                $headExtra .= implode("\n", $styles[0] ?? []);

                // Extract <link rel="stylesheet"> tags (only local ones)
                preg_match_all('#<link[^>]*rel=["\']stylesheet["\'][^>]*/?>|<link[^>]*/?\s*>#si', $headContent, $links);
                foreach ($links[0] ?? [] as $link) {
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

            // Remove <nav> elements from module (we use our own)
            $content = preg_replace('#<nav[^>]*class="[^"]*navbar[^"]*"[^>]*>.*?</nav>#si', '', $content);

            // Extract <script> tags from end of body
            preg_match_all('#<script[^>]*>.*?</script>#si', $content, $scripts);
            foreach ($scripts[0] ?? [] as $script) {
                // Skip CDN scripts
                if (preg_match('#(cdn|jsdelivr|googleapis|cloudflare|bootstrap)#i', $script)) {
                    continue;
                }
                $script = self::rewriteAssetPaths($script, $slug);
                $footExtra .= "\n" . $script;
                $content = str_replace($script, '', $content);
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
            '#(href=["\'])(?!https?://|/|#)([^"\']+\.css(?:\?[^"\']*)?)(["\'])#i',
            fn($m) => $m[1] . $prefix . $m[2] . $m[3],
            $html
        );

        return $html;
    }
}
