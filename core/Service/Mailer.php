<?php

namespace Platform\Service;

use Platform\Log\Logger;
use Platform\Repository\EmailLogRepository;
use Platform\Repository\SettingsRepository;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Mailer
{
    private static ?self $instance = null;
    private SymfonyMailer $mailer;
    private Address $expediteur;

    /**
     * @param array<string, mixed>|null $configOverride Si null, résolution auto DB → .env → défaut
     */
    public function __construct(?array $configOverride = null)
    {
        $emailConfig = $configOverride ?? self::configEffective();

        $dsn = sprintf(
            '%s://%s:%s@%s:%d',
            $emailConfig['encryption'] === 'ssl' ? 'smtps' : 'smtp',
            urlencode($emailConfig['username']),
            urlencode($emailConfig['password']),
            $emailConfig['host'],
            $emailConfig['port'],
        );

        $transport = Transport::fromDsn($dsn);
        $this->mailer = new SymfonyMailer($transport);
        $this->expediteur = new Address($emailConfig['from'], $emailConfig['from_name']);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function envoyer(string $destinataire, string $sujet, string $contenuHtml, ?string $typeEmail = null, ?int $userId = null): bool
    {
        try {
            $email = (new Email())
                ->from($this->expediteur)
                ->to($destinataire)
                ->subject($sujet)
                ->html($contenuHtml);

            $this->mailer->send($email);

            self::logEnvoi($destinataire, $sujet, $typeEmail, 'envoye', null, $userId);

            return true;
        } catch (\Throwable $e) {
            Logger::error('Erreur envoi email', [
                'destinataire' => $destinataire,
                'sujet' => $sujet,
                'erreur' => $e->getMessage(),
            ]);

            self::logEnvoi($destinataire, $sujet, $typeEmail, 'echec', $e->getMessage(), $userId);

            return false;
        }
    }

    /**
     * Résout la config SMTP effective : DB (si non vide) → .env → défaut.
     *
     * @return array<string, mixed>
     */
    public static function configEffective(): array
    {
        $config = require __DIR__ . '/../../config/app.php';
        $envConfig = $config['email'];

        try {
            $settingsRepo = new SettingsRepository();
            $dbConfig = $settingsRepo->obtenirGroupe('smtp');
        } catch (\Throwable) {
            $dbConfig = [];
        }

        return [
            'host'       => self::priorite($dbConfig, 'host', $envConfig['host']),
            'port'       => (int) self::priorite($dbConfig, 'port', (string) $envConfig['port']),
            'username'   => self::priorite($dbConfig, 'username', $envConfig['username']),
            'password'   => self::priorite($dbConfig, 'password', $envConfig['password']),
            'encryption' => self::priorite($dbConfig, 'encryption', $envConfig['encryption']),
            'from'       => self::priorite($dbConfig, 'from', $envConfig['from']),
            'from_name'  => self::priorite($dbConfig, 'from_name', $envConfig['from_name']),
        ];
    }

    /**
     * @param array<string, string|null> $dbConfig
     */
    private static function priorite(array $dbConfig, string $cle, string $defaut): string
    {
        $val = $dbConfig[$cle] ?? null;
        return ($val !== null && $val !== '') ? $val : $defaut;
    }

    private static function logEnvoi(
        string $destinataire,
        string $sujet,
        ?string $typeEmail,
        string $statut,
        ?string $erreur,
        ?int $userId,
    ): void {
        try {
            $repo = new EmailLogRepository();
            $repo->enregistrer($destinataire, $sujet, $typeEmail, $statut, $erreur, $userId);
        } catch (\Throwable $e) {
            Logger::error('Impossible de logger l\'envoi email', ['erreur' => $e->getMessage()]);
        }
    }
}
