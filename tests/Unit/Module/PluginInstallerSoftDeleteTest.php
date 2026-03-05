<?php

use Platform\Module\PluginInstaller;

beforeEach(function () {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // SQLite n'a pas NOW() — on l'émule pour les tests
    $pdo->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'), 0);

    $pdo->exec('CREATE TABLE modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        description TEXT DEFAULT "",
        version TEXT DEFAULT "1.0.0",
        icon TEXT DEFAULT "bi-tools",
        sort_order INTEGER DEFAULT 100,
        quota_mode TEXT DEFAULT "none",
        default_quota INTEGER DEFAULT 0,
        enabled INTEGER DEFAULT 1,
        chemin_source TEXT,
        point_entree TEXT DEFAULT "index.php",
        cles_env TEXT,
        routes_config TEXT,
        passthrough_all INTEGER DEFAULT 0,
        mode_affichage TEXT DEFAULT "embedded",
        langues TEXT,
        domain_field TEXT,
        categorie_id INTEGER,
        installe_par INTEGER,
        installe_le TEXT,
        desinstalle_le TEXT,
        desinstalle_par INTEGER,
        git_url TEXT,
        git_branche TEXT,
        git_dernier_pull TEXT,
        git_dernier_commit TEXT
    )');

    $pdo->exec('CREATE TABLE user_module_access (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        module_id INTEGER NOT NULL,
        granted INTEGER DEFAULT 0,
        granted_by INTEGER,
        UNIQUE(user_id, module_id)
    )');

    $pdo->exec('CREATE TABLE user_module_quotas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        module_id INTEGER NOT NULL,
        monthly_limit INTEGER DEFAULT 0,
        updated_by INTEGER,
        UNIQUE(user_id, module_id)
    )');

    $pdo->exec('CREATE TABLE module_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        module_id INTEGER NOT NULL,
        year_month TEXT NOT NULL,
        usage_count INTEGER DEFAULT 0,
        last_tracked_at TEXT,
        UNIQUE(user_id, module_id, year_month)
    )');

    $pdo->exec('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        role TEXT DEFAULT "user",
        active INTEGER DEFAULT 1,
        deleted_at TEXT DEFAULT NULL
    )');

    // Utilisateurs de test
    $pdo->exec("INSERT INTO users (id, username, role, active) VALUES (1, 'admin', 'admin', 1)");
    $pdo->exec("INSERT INTO users (id, username, role, active) VALUES (2, 'user2', 'user', 1)");
    $pdo->exec("INSERT INTO users (id, username, role, active) VALUES (3, 'user3', 'user', 1)");

    $this->pdo = $pdo;
});

function donneesPlugin(array $surcharge = []): array
{
    return array_merge([
        'slug'          => 'mon-plugin',
        'name'          => 'Mon Plugin',
        'description'   => 'Un plugin de test',
        'version'       => '1.0.0',
        'icon'          => 'bi-tools',
        'sort_order'    => 100,
        'quota_mode'    => 'none',
        'default_quota' => 0,
        'chemin_source' => '/tmp/fake-source',
        'point_entree'  => 'index.php',
        'cles_env'      => null,
        'routes_config' => null,
        'passthrough_all' => false,
        'mode_affichage'  => 'embedded',
        'categorie_id'    => null,
    ], $surcharge);
}

test('devrait soft-delete au lieu de supprimer', function () {
    $installer = new PluginInstaller($this->pdo);
    $moduleId = $installer->installer(donneesPlugin(), 1);

    $installer->desinstaller($moduleId, conserverReglages: true, desinstalleParId: 1);

    // La ligne module doit toujours exister
    $stmt = $this->pdo->prepare('SELECT * FROM modules WHERE id = ?');
    $stmt->execute([$moduleId]);
    $module = $stmt->fetch();

    expect($module)->not->toBeFalse();
    expect($module['desinstalle_le'])->not->toBeNull();
    expect((int) $module['desinstalle_par'])->toBe(1);
    expect((int) $module['enabled'])->toBe(0);
});

test('devrait hard-delete quand conserver_reglages est false', function () {
    $installer = new PluginInstaller($this->pdo);
    $moduleId = $installer->installer(donneesPlugin(), 1);

    // Ajouter des données dépendantes
    $this->pdo->prepare('INSERT INTO user_module_quotas (user_id, module_id, monthly_limit) VALUES (?, ?, ?)')
        ->execute([2, $moduleId, 50]);
    $this->pdo->prepare('INSERT INTO module_usage (user_id, module_id, year_month, usage_count) VALUES (?, ?, ?, ?)')
        ->execute([2, $moduleId, date('Ym'), 5]);

    $installer->desinstaller($moduleId, conserverReglages: false);

    // La ligne module doit avoir été supprimée
    $stmt = $this->pdo->prepare('SELECT id FROM modules WHERE id = ?');
    $stmt->execute([$moduleId]);
    expect($stmt->fetch())->toBeFalse();

    // Les données dépendantes aussi
    $stmt = $this->pdo->prepare('SELECT id FROM user_module_access WHERE module_id = ?');
    $stmt->execute([$moduleId]);
    expect($stmt->fetch())->toBeFalse();

    $stmt = $this->pdo->prepare('SELECT id FROM user_module_quotas WHERE module_id = ?');
    $stmt->execute([$moduleId]);
    expect($stmt->fetch())->toBeFalse();

    $stmt = $this->pdo->prepare('SELECT id FROM module_usage WHERE module_id = ?');
    $stmt->execute([$moduleId]);
    expect($stmt->fetch())->toBeFalse();
});

test('devrait réactiver un module soft-deleted à la réinstallation avec le même id', function () {
    $installer = new PluginInstaller($this->pdo);
    $moduleIdOriginal = $installer->installer(donneesPlugin(), 1);

    // Soft delete
    $installer->desinstaller($moduleIdOriginal, conserverReglages: true, desinstalleParId: 1);

    // Réinstaller le même slug
    $moduleIdReactive = $installer->installer(donneesPlugin(['version' => '2.0.0']), 2);

    // Même ID conservé
    expect($moduleIdReactive)->toBe($moduleIdOriginal);

    // Vérifier que le module est réactivé
    $stmt = $this->pdo->prepare('SELECT * FROM modules WHERE id = ?');
    $stmt->execute([$moduleIdReactive]);
    $module = $stmt->fetch();

    expect($module['desinstalle_le'])->toBeNull();
    expect($module['desinstalle_par'])->toBeNull();
    expect((int) $module['enabled'])->toBe(1);
    expect($module['version'])->toBe('2.0.0');
});

test('devrait conserver les accès utilisateurs après réinstallation', function () {
    $installer = new PluginInstaller($this->pdo);
    $moduleId = $installer->installer(donneesPlugin(), 1);

    // L'installation accorde l'accès à tous les utilisateurs actifs (3 users)
    $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_module_access WHERE module_id = ?');
    $stmt->execute([$moduleId]);
    expect((int) $stmt->fetchColumn())->toBe(3); // admin + user2 + user3

    // Soft delete
    $installer->desinstaller($moduleId, conserverReglages: true, desinstalleParId: 1);

    // Vérifier que les accès sont toujours en base
    $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_module_access WHERE module_id = ?');
    $stmt->execute([$moduleId]);
    expect((int) $stmt->fetchColumn())->toBe(3);

    // Réinstaller
    $installer->installer(donneesPlugin(), 3);

    // L'accès du user 2 est toujours là
    $stmt = $this->pdo->prepare('SELECT granted FROM user_module_access WHERE user_id = ? AND module_id = ?');
    $stmt->execute([2, $moduleId]);
    $acces = $stmt->fetch();
    expect($acces)->not->toBeFalse();
    expect((int) $acces['granted'])->toBe(1);
});

test('devrait conserver les quotas après réinstallation', function () {
    $installer = new PluginInstaller($this->pdo);
    $moduleId = $installer->installer(donneesPlugin(), 1);

    // Attribuer un quota personnalisé
    $this->pdo->prepare('INSERT INTO user_module_quotas (user_id, module_id, monthly_limit) VALUES (?, ?, ?)')
        ->execute([2, $moduleId, 100]);

    // Soft delete + réinstallation
    $installer->desinstaller($moduleId, conserverReglages: true, desinstalleParId: 1);
    $installer->installer(donneesPlugin(), 1);

    // Le quota doit être intact
    $stmt = $this->pdo->prepare('SELECT monthly_limit FROM user_module_quotas WHERE user_id = ? AND module_id = ?');
    $stmt->execute([2, $moduleId]);
    $quota = $stmt->fetch();

    expect($quota)->not->toBeFalse();
    expect((int) $quota['monthly_limit'])->toBe(100);
});

test('devrait valider le slug en ignorant les modules soft-deleted', function () {
    $installer = new PluginInstaller($this->pdo);
    $moduleId = $installer->installer(donneesPlugin(), 1);

    // Le slug est pris → erreur
    expect($installer->validerSlug('mon-plugin'))->not->toBeNull();

    // Soft delete → le slug redevient disponible
    $installer->desinstaller($moduleId, conserverReglages: true, desinstalleParId: 1);
    expect($installer->validerSlug('mon-plugin'))->toBeNull();
});

test('devrait valider le chemin en ignorant les modules soft-deleted', function () {
    $chemin = sys_get_temp_dir() . '/plugin-softdelete-test-' . uniqid();
    mkdir($chemin, 0755, true);

    $installer = new PluginInstaller($this->pdo);
    $moduleId = $installer->installer(donneesPlugin(['chemin_source' => $chemin]), 1);

    // Le chemin est pris → erreur
    expect($installer->validerChemin($chemin))->toBe('Ce chemin est déjà installé comme plugin.');

    // Soft delete → le chemin redevient disponible
    $installer->desinstaller($moduleId, conserverReglages: true, desinstalleParId: 1);
    expect($installer->validerChemin($chemin))->toBeNull();

    rmdir($chemin);
});
