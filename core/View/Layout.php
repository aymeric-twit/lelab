<?php

namespace Platform\View;

class Layout
{
    public static function render(string $template, array $data = []): void
    {
        extract($data);
        $flash = Flash::get();
        require __DIR__ . '/../../templates/layout.php';
    }

    public static function renderStandalone(string $template, array $data = []): void
    {
        extract($data);
        $flash = Flash::get();
        require __DIR__ . '/../../templates/' . $template . '.php';
    }
}
