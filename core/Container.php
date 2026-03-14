<?php

namespace Platform;

/**
 * Conteneur de services léger (PSR-11 compatible).
 * Enregistre des factories paresseuses et résout les dépendances à la demande.
 */
class Container
{
    private static ?self $instance = null;

    /** @var array<string, callable> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Enregistre une factory pour un service.
     * La factory reçoit le Container en paramètre.
     *
     * @param callable(self): object $factory
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * Résout un service (singleton par défaut).
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new \RuntimeException("Service « {$id} » non enregistré dans le conteneur.");
        }

        $this->instances[$id] = ($this->factories[$id])($this);
        return $this->instances[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }

    /**
     * Réinitialise le conteneur (utile pour les tests).
     */
    public function reset(): void
    {
        $this->factories = [];
        $this->instances = [];
        self::$instance = null;
    }

    /**
     * Enregistre directement une instance résolue (pas de factory).
     */
    public function setInstance(string $id, object $resolved): void
    {
        $this->instances[$id] = $resolved;
    }
}
