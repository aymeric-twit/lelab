<?php

namespace Platform\View;

class Layout
{
    public static function render(string $_layoutFile, array $data = []): void
    {
        extract($data);
        $flash = Flash::get();
        require __DIR__ . '/../../templates/' . $_layoutFile . '.php';
    }

    public static function renderStandalone(string $template, array $data = []): void
    {
        extract($data);
        $flash = Flash::get();
        require __DIR__ . '/../../templates/' . $template . '.php';
    }
}
