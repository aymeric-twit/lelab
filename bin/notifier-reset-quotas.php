#!/usr/bin/env php
<?php

/**
 * Script cron mensuel — Envoie un résumé d'usage aux utilisateurs actifs.
 * Cron : 0 8 1 * *
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Platform\Database\Connection;
use Platform\Service\NotificationService;

$dotenv = Dotenv::createMutable(__DIR__ . '/..');
$dotenv->load();

$db = Connection::get();
$notification = NotificationService::instance();

$moisPrecedent = date('Ym', strtotime('first day of last month'));

// Récupérer les utilisateurs actifs non-admin ayant eu de l'activité le mois précédent
$stmt = $db->prepare("
    SELECT DISTINCT u.id
    FROM users u
    INNER JOIN module_usage mu ON mu.user_id = u.id AND mu.`year_month` = ?
    WHERE u.active = 1
      AND u.deleted_at IS NULL
      AND u.role != 'admin'
      AND mu.usage_count > 0
");
$stmt->execute([$moisPrecedent]);
$userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$envoyes = 0;
$erreurs = 0;

foreach ($userIds as $userId) {
    $userId = (int) $userId;

    // Récupérer le résumé d'usage du mois précédent pour cet utilisateur
    $stmtUsage = $db->prepare("
        SELECT m.name AS nom, mu.usage_count AS `usage`,
               COALESCE(umq.monthly_limit, m.default_quota) AS limite
        FROM module_usage mu
        INNER JOIN modules m ON m.id = mu.module_id
        LEFT JOIN user_module_quotas umq ON umq.user_id = mu.user_id AND umq.module_id = mu.module_id
        WHERE mu.user_id = ? AND mu.`year_month` = ? AND mu.usage_count > 0
        ORDER BY m.name
    ");
    $stmtUsage->execute([$userId, $moisPrecedent]);
    $resumeModules = $stmtUsage->fetchAll(PDO::FETCH_ASSOC);

    if ($resumeModules === []) {
        continue;
    }

    if ($notification->envoyerResumeResetQuotas($userId, $resumeModules)) {
        $envoyes++;
    } else {
        $erreurs++;
    }
}

echo "[notifier-reset-quotas] {$envoyes} emails envoyés, {$erreurs} erreurs.\n";
