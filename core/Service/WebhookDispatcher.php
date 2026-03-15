<?php

namespace Platform\Service;

use Platform\Database\Connection;
use Platform\Log\Logger;
use PDO;

/**
 * Dispatche des événements vers les webhooks configurés.
 */
class WebhookDispatcher
{
    private PDO $db;

    /** @var string[] Événements supportés */
    public const EVENEMENTS = [
        'user.created',
        'user.deleted',
        'module.installed',
        'module.uninstalled',
        'module.updated',
        'quota.exceeded',
        'quota.warning',
        'backup.created',
        'test.ping',
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * Déclenche un événement : envoie le payload à tous les webhooks abonnés.
     *
     * @param array<string, mixed> $payload
     * @return int Nombre de webhooks notifiés
     */
    public function declencher(string $evenement, array $payload = []): int
    {
        $webhooks = $this->webhooksAbonnes($evenement);
        $count = 0;

        foreach ($webhooks as $webhook) {
            $this->envoyer($webhook, $evenement, $payload);
            $count++;
        }

        return $count;
    }

    /**
     * Retourne les webhooks abonnés à un événement.
     *
     * @return array<int, array<string, mixed>>
     */
    private function webhooksAbonnes(string $evenement): array
    {
        $stmt = $this->db->query('SELECT * FROM webhooks WHERE actif = 1');
        $tous = $stmt->fetchAll();

        return array_filter($tous, function (array $wh) use ($evenement) {
            $events = json_decode($wh['evenements'], true);
            return is_array($events) && (in_array($evenement, $events, true) || in_array('*', $events, true));
        });
    }

    /**
     * Envoie un payload à un webhook.
     */
    private function envoyer(array $webhook, string $evenement, array $payload): void
    {
        $body = json_encode([
            'evenement'  => $evenement,
            'timestamp'  => date('c'),
            'donnees'    => $payload,
        ], JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json',
            'User-Agent: SEO-Platform-Webhook/1.0',
            'X-Webhook-Event: ' . $evenement,
        ];

        // Signature HMAC si un secret est configuré
        if (!empty($webhook['secret'])) {
            $signature = hash_hmac('sha256', $body, $webhook['secret']);
            $headers[] = 'X-Webhook-Signature: sha256=' . $signature;
        }

        $debut = microtime(true);

        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $reponse = curl_exec($ch);
        $statusHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erreur = curl_error($ch);
        curl_close($ch);

        $dureeMs = (int) ((microtime(true) - $debut) * 1000);

        // Logger le résultat
        $this->logWebhook(
            (int) $webhook['id'],
            $evenement,
            $payload,
            $statusHttp,
            $reponse !== false ? substr((string) $reponse, 0, 1000) : $erreur,
            $dureeMs
        );

        // Mettre à jour le dernier envoi
        $this->db->prepare('UPDATE webhooks SET dernier_envoi = NOW(), dernier_statut = ? WHERE id = ?')
            ->execute([$statusHttp, $webhook['id']]);

        if ($statusHttp < 200 || $statusHttp >= 300) {
            Logger::warning('Webhook échoué', [
                'webhook_id' => $webhook['id'],
                'url'        => $webhook['url'],
                'evenement'  => $evenement,
                'status'     => $statusHttp,
                'erreur'     => $erreur,
            ]);
        }
    }

    private function logWebhook(int $webhookId, string $evenement, array $payload, int $statusHttp, string $reponse, int $dureeMs): void
    {
        try {
            $this->db->prepare(
                'INSERT INTO webhook_logs (webhook_id, evenement, payload, statut_http, reponse, duree_ms) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $webhookId,
                $evenement,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                $statusHttp,
                $reponse,
                $dureeMs,
            ]);
        } catch (\PDOException $e) {
            Logger::error('Impossible de logger webhook', ['erreur' => $e->getMessage()]);
        }
    }

    // -----------------------------------------------
    // CRUD Webhooks (pour l'admin)
    // -----------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listerTous(): array
    {
        return $this->db->query('SELECT * FROM webhooks ORDER BY created_at DESC')->fetchAll();
    }

    public function parId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM webhooks WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function creer(array $donnees): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO webhooks (nom, url, secret, evenements, actif, created_by) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $donnees['nom'],
            $donnees['url'],
            $donnees['secret'] ?? null,
            json_encode($donnees['evenements'] ?? [], JSON_UNESCAPED_UNICODE),
            $donnees['actif'] ?? 1,
            $donnees['created_by'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function mettreAJour(int $id, array $donnees): void
    {
        $champs = [];
        $valeurs = [];

        foreach (['nom', 'url', 'secret', 'actif'] as $champ) {
            if (array_key_exists($champ, $donnees)) {
                $champs[] = "{$champ} = ?";
                $valeurs[] = $donnees[$champ];
            }
        }

        if (array_key_exists('evenements', $donnees)) {
            $champs[] = 'evenements = ?';
            $valeurs[] = json_encode($donnees['evenements'], JSON_UNESCAPED_UNICODE);
        }

        if (empty($champs)) {
            return;
        }

        $valeurs[] = $id;
        $this->db->prepare('UPDATE webhooks SET ' . implode(', ', $champs) . ' WHERE id = ?')
            ->execute($valeurs);
    }

    public function supprimer(int $id): void
    {
        $this->db->prepare('DELETE FROM webhooks WHERE id = ?')->execute([$id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function logsRecents(int $webhookId, int $limite = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM webhook_logs WHERE webhook_id = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $webhookId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
