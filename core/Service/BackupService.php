<?php

namespace Platform\Service;

use Platform\Log\Logger;

/**
 * Service de backup de la base de données (mysqldump) avec rotation.
 */
class BackupService
{
    private string $repertoireBackup;

    public function __construct(?string $repertoireBackup = null)
    {
        $this->repertoireBackup = $repertoireBackup ?? dirname(__DIR__, 2) . '/storage/backups';
    }

    /**
     * Crée un dump de la base de données.
     *
     * @return array{succes: bool, fichier: ?string, taille: ?int, erreur: ?string}
     */
    public function creerBackup(): array
    {
        if (!is_dir($this->repertoireBackup)) {
            mkdir($this->repertoireBackup, 0755, true);
        }

        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $horodatage = date('Y-m-d_H-i-s');
        $fichier = $this->repertoireBackup . "/backup_{$horodatage}.sql.gz";

        $host = escapeshellarg($config['host']);
        $port = escapeshellarg($config['port']);
        $user = escapeshellarg($config['user']);
        $pass = $config['password'];
        $db = escapeshellarg($config['name']);

        // Construire la commande mysqldump
        $cmd = "mysqldump --host={$host} --port={$port} --user={$user}";
        if ($pass !== '') {
            $cmd .= ' --password=' . escapeshellarg($pass);
        }
        $cmd .= " --single-transaction --routines --triggers {$db} | gzip > " . escapeshellarg($fichier);

        $sortie = [];
        $codeRetour = 0;
        exec($cmd . ' 2>&1', $sortie, $codeRetour);

        if ($codeRetour !== 0 || !file_exists($fichier) || filesize($fichier) === 0) {
            if (file_exists($fichier)) {
                unlink($fichier);
            }
            $erreur = implode("\n", $sortie);
            Logger::error('Échec backup BDD', ['code' => $codeRetour, 'sortie' => $erreur]);
            return ['succes' => false, 'fichier' => null, 'taille' => null, 'erreur' => $erreur ?: 'mysqldump a échoué'];
        }

        $taille = filesize($fichier);
        Logger::info('Backup BDD créé', ['fichier' => basename($fichier), 'taille' => $taille]);

        return ['succes' => true, 'fichier' => basename($fichier), 'taille' => $taille, 'erreur' => null];
    }

    /**
     * Liste les backups existants (les plus récents en premier).
     *
     * @return array<int, array{fichier: string, taille: int, date: string}>
     */
    public function listerBackups(): array
    {
        if (!is_dir($this->repertoireBackup)) {
            return [];
        }

        $fichiers = glob($this->repertoireBackup . '/backup_*.sql.gz');
        $backups = [];

        foreach ($fichiers as $f) {
            $backups[] = [
                'fichier' => basename($f),
                'taille'  => filesize($f),
                'date'    => date('Y-m-d H:i:s', filemtime($f)),
            ];
        }

        usort($backups, fn($a, $b) => $b['date'] <=> $a['date']);
        return $backups;
    }

    /**
     * Supprime les backups plus anciens que N jours.
     *
     * @return int Nombre de fichiers supprimés
     */
    public function rotation(int $joursAConserver = 30): int
    {
        if (!is_dir($this->repertoireBackup)) {
            return 0;
        }

        $seuil = time() - ($joursAConserver * 86400);
        $fichiers = glob($this->repertoireBackup . '/backup_*.sql.gz');
        $supprimes = 0;

        foreach ($fichiers as $f) {
            if (filemtime($f) < $seuil) {
                unlink($f);
                $supprimes++;
            }
        }

        return $supprimes;
    }

    /**
     * Retourne le chemin complet d'un backup pour téléchargement.
     */
    public function cheminBackup(string $nomFichier): ?string
    {
        $chemin = $this->repertoireBackup . '/' . basename($nomFichier);
        if (!file_exists($chemin) || !str_starts_with(basename($nomFichier), 'backup_')) {
            return null;
        }
        return $chemin;
    }

    /**
     * Formate une taille en octets en format lisible.
     */
    public static function formaterTaille(int $octets): string
    {
        if ($octets < 1024) {
            return $octets . ' o';
        }
        if ($octets < 1048576) {
            return round($octets / 1024, 1) . ' Ko';
        }
        return round($octets / 1048576, 1) . ' Mo';
    }
}
