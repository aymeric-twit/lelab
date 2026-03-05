<?php

namespace Platform\Service;

use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Platform\Log\Logger;

class Mailer
{
    private static ?self $instance = null;
    private SymfonyMailer $mailer;
    private Address $expediteur;

    public function __construct()
    {
        $config = require __DIR__ . '/../../config/app.php';
        $emailConfig = $config['email'];

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

    public function envoyer(string $destinataire, string $sujet, string $contenuHtml): bool
    {
        try {
            $email = (new Email())
                ->from($this->expediteur)
                ->to($destinataire)
                ->subject($sujet)
                ->html($contenuHtml);

            $this->mailer->send($email);
            return true;
        } catch (\Throwable $e) {
            Logger::error('Erreur envoi email', [
                'destinataire' => $destinataire,
                'sujet' => $sujet,
                'erreur' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
