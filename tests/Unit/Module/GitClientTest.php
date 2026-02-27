<?php

use Platform\Module\GitClient;

// =============================================
// Validation d'URL
// =============================================

test('validerUrl accepte une URL GitHub valide', function () {
    expect(GitClient::validerUrl('https://github.com/user/repo'))->toBeTrue();
    expect(GitClient::validerUrl('https://github.com/user/repo.git'))->toBeTrue();
    expect(GitClient::validerUrl('https://github.com/my-org/my-plugin'))->toBeTrue();
    expect(GitClient::validerUrl('https://github.com/user/repo-name.git'))->toBeTrue();
});

test('validerUrl accepte une URL GitLab valide', function () {
    expect(GitClient::validerUrl('https://gitlab.com/user/repo'))->toBeTrue();
    expect(GitClient::validerUrl('https://gitlab.com/user/repo.git'))->toBeTrue();
});

test('validerUrl refuse les URLs invalides', function () {
    expect(GitClient::validerUrl(''))->toBeFalse();
    expect(GitClient::validerUrl('http://github.com/user/repo'))->toBeFalse();
    expect(GitClient::validerUrl('https://evil.com/user/repo'))->toBeFalse();
    expect(GitClient::validerUrl('https://github.com/'))->toBeFalse();
    expect(GitClient::validerUrl('https://github.com/user'))->toBeFalse();
    expect(GitClient::validerUrl('git@github.com:user/repo.git'))->toBeFalse();
    expect(GitClient::validerUrl('https://github.com/user/repo; rm -rf /'))->toBeFalse();
    expect(GitClient::validerUrl('https://github.com/user/repo$(whoami)'))->toBeFalse();
    expect(GitClient::validerUrl('https://github.com/user/repo`id`'))->toBeFalse();
    expect(GitClient::validerUrl('https://github.com/../../../etc/passwd'))->toBeFalse();
});

// =============================================
// Extraction du slug
// =============================================

test('extraireSlug extrait le nom du repo sans .git', function () {
    expect(GitClient::extraireSlug('https://github.com/user/mon-plugin.git'))->toBe('mon-plugin');
    expect(GitClient::extraireSlug('https://github.com/user/mon-plugin'))->toBe('mon-plugin');
});

test('extraireSlug met le slug en minuscules', function () {
    expect(GitClient::extraireSlug('https://github.com/user/Mon-Plugin'))->toBe('mon-plugin');
});

test('extraireSlug retourne null pour une URL invalide', function () {
    expect(GitClient::extraireSlug('pas-une-url'))->toBeNull();
    expect(GitClient::extraireSlug(''))->toBeNull();
});

// =============================================
// Construction URL authentifiée
// =============================================

test('construireUrlAuthentifiee insère le token', function () {
    $client = new GitClient('ghp_test123');

    // Utiliser la réflexion pour tester la méthode privée
    $reflexion = new ReflectionMethod($client, 'construireUrlAuthentifiee');
    $resultat = $reflexion->invoke($client, 'https://github.com/user/repo.git');

    expect($resultat)->toBe('https://ghp_test123@github.com/user/repo.git');
});

test('construireUrlAuthentifiee sans token retourne URL inchangée', function () {
    $client = new GitClient(null);

    $reflexion = new ReflectionMethod($client, 'construireUrlAuthentifiee');
    $resultat = $reflexion->invoke($client, 'https://github.com/user/repo.git');

    expect($resultat)->toBe('https://github.com/user/repo.git');
});

test('construireUrlAuthentifiee avec token vide retourne URL inchangée', function () {
    $client = new GitClient('');

    $reflexion = new ReflectionMethod($client, 'construireUrlAuthentifiee');
    $resultat = $reflexion->invoke($client, 'https://github.com/user/repo.git');

    expect($resultat)->toBe('https://github.com/user/repo.git');
});

// =============================================
// getDernierCommit
// =============================================

test('getDernierCommit retourne null pour un répertoire sans .git', function () {
    $tmpDir = sys_get_temp_dir() . '/git-test-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $client = new GitClient(null);
    expect($client->getDernierCommit($tmpDir))->toBeNull();

    rmdir($tmpDir);
});

// =============================================
// pull
// =============================================

test('pull retourne false pour un répertoire sans .git', function () {
    $tmpDir = sys_get_temp_dir() . '/git-test-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $client = new GitClient(null);
    expect($client->pull($tmpDir))->toBeFalse();

    rmdir($tmpDir);
});

// =============================================
// cloner
// =============================================

test('cloner refuse une URL invalide', function () {
    $tmpDir = sys_get_temp_dir() . '/git-test-' . uniqid();

    $client = new GitClient(null);
    $resultat = $client->cloner('https://evil.com/user/repo', $tmpDir);

    expect($resultat)->toBeFalse();
    expect(is_dir($tmpDir))->toBeFalse();
});
