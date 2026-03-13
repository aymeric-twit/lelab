<?php

namespace Platform\Http;

use Platform\App;

class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return '/' . trim($path, '/');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    /**
     * Adresse IP du client, avec support des proxies de confiance.
     */
    public function ip(): string
    {
        $trustedProxies = App::config('trusted_proxies') ?? [];
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($trustedProxies === [] || !in_array($remoteAddr, $trustedProxies, true)) {
            return $remoteAddr;
        }

        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwardedFor === '') {
            return $remoteAddr;
        }

        // X-Forwarded-For: client, proxy1, proxy2 — le premier est le client original
        $ips = array_map('trim', explode(',', $forwardedFor));

        // Parcourir depuis la droite, ignorer les proxies de confiance
        $ips = array_reverse($ips);
        foreach ($ips as $ip) {
            if (!in_array($ip, $trustedProxies, true) && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return $remoteAddr;
    }

    /**
     * Adresse IP anonymisée (RGPD) : dernier octet IPv4 → 0, 80 derniers bits IPv6 → 0.
     */
    public function ipAnonymisee(): string
    {
        $ip = $this->ip();

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            if ($packed !== false) {
                // Masquer les 80 derniers bits (10 derniers octets)
                $packed = substr($packed, 0, 6) . str_repeat("\0", 10);
                return inet_ntop($packed);
            }
        }

        return '0.0.0.0';
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Détection élargie d'une requête AJAX/API.
     * Utilisée comme fallback quand la sous-route n'est pas déclarée dans module.json.
     *
     * Vérifie : X-Requested-With, Accept: application/json, Content-Type: application/json.
     */
    public function estRequeteAjax(): bool
    {
        if ($this->isAjax()) {
            return true;
        }

        $accept = $this->header('Accept') ?? '';
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        $contentType = $this->header('Content-Type') ?? '';
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        return false;
    }

    /**
     * Détecte une requête Server-Sent Events (EventSource).
     * Le navigateur envoie Accept: text/event-stream automatiquement.
     */
    public function estRequeteSSE(): bool
    {
        $accept = $this->header('Accept') ?? '';
        return str_contains($accept, 'text/event-stream');
    }
}
