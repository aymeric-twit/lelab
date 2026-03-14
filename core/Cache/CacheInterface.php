<?php

namespace Platform\Cache;

/**
 * Interface de cache applicatif (PSR-16 simplifiée).
 */
interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, int $ttl = 3600): bool;

    public function has(string $key): bool;

    public function delete(string $key): bool;

    public function clear(): bool;
}
