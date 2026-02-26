<?php

/**
 * Router pour le serveur PHP intégré.
 *
 * Usage : php -S localhost:8000 -t public/ public/router.php
 *
 * Le serveur intégré ne passe pas les URLs avec extension (.js, .css, etc.)
 * à index.php. Ce router intercepte toutes les requêtes et sert les fichiers
 * statiques existants dans public/, ou délègue à index.php pour le reste.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . $uri;

// Fichier statique existant dans public/ : laisser le serveur intégré le servir
if ($uri !== '/' && file_exists($filePath) && is_file($filePath)) {
    return false;
}

// Tout le reste passe par le front controller
require __DIR__ . '/index.php';
