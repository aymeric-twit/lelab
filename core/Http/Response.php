<?php

namespace Platform\Http;

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    public static function status(int $code): void
    {
        http_response_code($code);
    }

    public static function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        echo $message ?: match ($code) {
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            429 => 'Too Many Requests',
            default => 'Error',
        };
        exit;
    }
}
