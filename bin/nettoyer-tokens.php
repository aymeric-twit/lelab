#!/usr/bin/env php
<?php

/**
 * Script de nettoyage des tokens expirés.
 * À exécuter quotidiennement via cron :
 * 0 3 * * * php /chemin/vers/bin/nettoyer-tokens.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Platform\App;
use Platform\Auth\RememberMe;
use Platform\Auth\PasswordReset;
use Platform\Auth\EmailVerification;

App::boot();

$rememberSupprimes = RememberMe::nettoyerExpires();
$resetSupprimes = PasswordReset::nettoyerExpires();
$verificationSupprimes = EmailVerification::nettoyerExpires();

$total = $rememberSupprimes + $resetSupprimes + $verificationSupprimes;

if ($total > 0) {
    echo sprintf(
        "[%s] Nettoyage : %d remember, %d resets, %d vérifications supprimés\n",
        date('Y-m-d H:i:s'),
        $rememberSupprimes,
        $resetSupprimes,
        $verificationSupprimes,
    );
}
