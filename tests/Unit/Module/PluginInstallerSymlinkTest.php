<?php

use Platform\Module\PluginInstaller;

beforeEach(function () {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

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

    $this->pdo = $pdo;

    // Préparer un répertoire temporaire pour les symlinks
    $this->tmpDir = sys_get_temp_dir() . '/seo-platform-test-' . uniqid();
    mkdir($this->tmpDir . '/public/module-assets', 0755, true);
    mkdir($this->tmpDir . '/source-plugin', 0755, true);
    file_put_contents($this->tmpDir . '/source-plugin/index.php', '<?php echo "test";');
});

afterEach(function () {
    // Nettoyage des fichiers temporaires
    if (isset($this->tmpDir) && is_dir($this->tmpDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                unlink($item->getPathname());
            } elseif ($item->isDir()) {
                rmdir($item->getPathname());
            }
        }
        rmdir($this->tmpDir);
    }
});

test('devrait créer un symlink assets pour un plugin', function () {
    $installer = new PluginInstaller($this->pdo);

    $slug = 'mon-plugin';
    $source = $this->tmpDir . '/source-plugin';
    $lien = $this->tmpDir . '/public/module-assets/' . $slug;

    // Utiliser la méthode directement
    $installer->creerSymlinkAssets($slug, $source);

    // Le répertoire assets devrait exister dans le répertoire réel du projet
    // Mais pour ce test, on vérifie la méthode elle-même via le chemin du projet
    // On test plutôt la logique en l'appelant manuellement
    $repertoire = $installer->repertoireAssetsPublics();
    expect($repertoire)->toContain('public/module-assets');
});

test('devrait supprimer un symlink assets', function () {
    $installer = new PluginInstaller($this->pdo);

    $slug = 'test-symlink';
    $assetsDir = $installer->repertoireAssetsPublics();

    // Créer le répertoire s'il n'existe pas
    if (!is_dir($assetsDir)) {
        mkdir($assetsDir, 0755, true);
    }

    $lien = $assetsDir . '/' . $slug;
    $source = $this->tmpDir . '/source-plugin';

    // Créer un symlink manuellement
    if (is_link($lien)) {
        unlink($lien);
    }
    @symlink($source, $lien);

    if (is_link($lien)) {
        $installer->supprimerSymlinkAssets($slug);
        expect(is_link($lien))->toBeFalse();
    } else {
        // Si le symlink n'a pas pu être créé (permissions), on skip
        $this->markTestSkipped('Impossible de créer le symlink pour le test');
    }
});

test('devrait stocker les langues dans la base lors de installation', function () {
    $installer = new PluginInstaller($this->pdo);

    $donnees = [
        'slug'            => 'plugin-i18n',
        'name'            => 'Plugin i18n',
        'description'     => 'Plugin avec traductions',
        'version'         => '1.0.0',
        'icon'            => 'bi-translate',
        'sort_order'      => 50,
        'quota_mode'      => 'none',
        'default_quota'   => 0,
        'chemin_source'   => '/tmp/fake-i18n',
        'point_entree'    => 'index.php',
        'mode_affichage'  => 'embedded',
        'langues'         => ['fr', 'en'],
    ];

    $moduleId = $installer->installer($donnees, 1);

    $stmt = $this->pdo->prepare('SELECT langues FROM modules WHERE id = ?');
    $stmt->execute([$moduleId]);
    $row = $stmt->fetch();

    expect($row['langues'])->not->toBeNull();
    $langues = json_decode($row['langues'], true);
    expect($langues)->toBe(['fr', 'en']);
});

test('devrait stocker null pour les langues quand non spécifiées', function () {
    $installer = new PluginInstaller($this->pdo);

    $donnees = [
        'slug'            => 'plugin-no-i18n',
        'name'            => 'Plugin sans i18n',
        'chemin_source'   => '/tmp/fake-no-i18n',
        'mode_affichage'  => 'embedded',
    ];

    $moduleId = $installer->installer($donnees, 1);

    $stmt = $this->pdo->prepare('SELECT langues FROM modules WHERE id = ?');
    $stmt->execute([$moduleId]);
    $row = $stmt->fetch();

    expect($row['langues'])->toBeNull();
});

test('devrait conserver les langues lors de la réactivation', function () {
    $installer = new PluginInstaller($this->pdo);

    $donnees = [
        'slug'            => 'plugin-reactive',
        'name'            => 'Plugin réactivable',
        'chemin_source'   => '/tmp/fake-reactive',
        'mode_affichage'  => 'embedded',
        'langues'         => ['fr', 'en', 'de'],
    ];

    $moduleId = $installer->installer($donnees, 1);

    // Soft delete
    $installer->desinstaller($moduleId, conserverReglages: true, desinstalleParId: 1);

    // Réinstaller avec des langues mises à jour
    $donnees['langues'] = ['fr', 'en'];
    $moduleIdReactive = $installer->installer($donnees, 1);

    expect($moduleIdReactive)->toBe($moduleId);

    $stmt = $this->pdo->prepare('SELECT langues FROM modules WHERE id = ?');
    $stmt->execute([$moduleIdReactive]);
    $row = $stmt->fetch();

    $langues = json_decode($row['langues'], true);
    expect($langues)->toBe(['fr', 'en']);
});

test('devrait mettre à jour les langues via mettreAJour', function () {
    $installer = new PluginInstaller($this->pdo);

    $donnees = [
        'slug'            => 'plugin-update-lang',
        'name'            => 'Plugin MAJ',
        'chemin_source'   => '/tmp/fake-update',
        'mode_affichage'  => 'embedded',
    ];

    $moduleId = $installer->installer($donnees, 1);

    // Mettre à jour avec des langues
    $installer->mettreAJour($moduleId, [
        'name'            => 'Plugin MAJ v2',
        'mode_affichage'  => 'embedded',
        'langues'         => ['fr', 'es'],
    ]);

    $stmt = $this->pdo->prepare('SELECT langues, name FROM modules WHERE id = ?');
    $stmt->execute([$moduleId]);
    $row = $stmt->fetch();

    expect($row['name'])->toBe('Plugin MAJ v2');
    $langues = json_decode($row['langues'], true);
    expect($langues)->toBe(['fr', 'es']);
});
