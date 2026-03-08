<?php

/**
 * Backfill des tokens de désabonnement pour les utilisateurs existants.
 * Usage : php cli/backfill-unsubscribe-tokens.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Platform\Database\Connection;

$db = Connection::get();

$stmt = $db->query('SELECT id FROM users WHERE unsubscribe_token IS NULL AND deleted_at IS NULL');
$users = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($users)) {
    echo "Aucun utilisateur sans token.\n";
    exit(0);
}

$update = $db->prepare('UPDATE users SET unsubscribe_token = ? WHERE id = ?');
$count = 0;

foreach ($users as $userId) {
    $token = bin2hex(random_bytes(32));
    $update->execute([$token, $userId]);
    $count++;
}

echo "Tokens générés pour {$count} utilisateur(s).\n";
