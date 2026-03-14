<?php

namespace Platform\Module;

use Platform\Enum\ModeAffichage;
use Platform\Enum\QuotaMode;
use Platform\Enum\RouteType;

class ModuleDescriptor
{
    public readonly string $slug;
    public readonly string $name;
    public readonly string $description;
    public readonly string $version;
    public readonly string $icon;
    public readonly string $entryPoint;
    public readonly int $sortOrder;
    public readonly array $envKeys;
    public readonly array $routes;
    public readonly bool $passthroughAll;
    public readonly ModeAffichage $modeAffichage;
    public readonly QuotaMode $quotaMode;
    public readonly int $defaultQuota;
    public readonly ?int $categorieId;
    /** @var string[] Langues supportées (ex: ['fr', 'en']), vide si non traduit */
    public readonly array $langues;
    /** Identifiant HTML du champ à pré-remplir avec le domaine utilisateur */
    public readonly ?string $domainField;
    /** Période de facturation API : 'mensuel' ou 'hebdomadaire' */
    public readonly string $apiCreditsPeriod;
    /** Nombre de crédits API par période (0 = non configuré) */
    public readonly int $apiCreditsDefault;
    /** Poids en crédits universels par analyse (0 = gratuit) */
    public readonly int $creditsParAnalyse;
    public readonly string $path;

    public function __construct(string $basePath, array $data)
    {
        $this->path = $basePath;
        $this->categorieId = isset($data['categorie_id']) ? (int) $data['categorie_id'] : null;
        $this->slug = $data['slug'];
        $this->name = $data['name'];
        $this->description = $data['description'] ?? '';
        $this->version = $data['version'] ?? '1.0.0';
        $this->icon = $data['icon'] ?? 'bi-tools';
        $this->entryPoint = $data['entry_point'] ?? 'index.php';
        $this->sortOrder = $data['sort_order'] ?? 100;
        $this->envKeys = $data['env_keys'] ?? [];
        $this->routes = $data['routes'] ?? [];

        // display_mode prioritaire, fallback passthrough_all pour rétrocompat
        if (isset($data['display_mode'])) {
            $this->modeAffichage = ModeAffichage::tryFrom($data['display_mode']) ?? ModeAffichage::Embedded;
        } elseif (!empty($data['passthrough_all'])) {
            $this->modeAffichage = ModeAffichage::Passthrough;
        } else {
            $this->modeAffichage = ModeAffichage::Embedded;
        }

        $this->passthroughAll = $this->modeAffichage->estPassthrough();
        $this->quotaMode = QuotaMode::tryFrom($data['quota_mode'] ?? 'none') ?? QuotaMode::None;
        $this->defaultQuota = (int) ($data['default_quota'] ?? 0);
        $this->langues = $data['languages'] ?? [];
        $this->domainField = $data['domain_field'] ?? null;
        $this->apiCreditsPeriod = $data['api_credits_period'] ?? 'mensuel';
        $this->apiCreditsDefault = (int) ($data['api_credits_default'] ?? 0);
        $this->creditsParAnalyse = (int) ($data['credits_per_use'] ?? $data['credits_par_analyse'] ?? 1);
    }

    public function getEntryFile(): string
    {
        return $this->path . '/' . $this->entryPoint;
    }

    public function hasSubRoute(string $subPath): bool
    {
        foreach ($this->routes as $route) {
            if ($route['path'] === $subPath) {
                return true;
            }
        }
        return false;
    }

    public function getRouteType(string $subPath): RouteType
    {
        foreach ($this->routes as $route) {
            if ($route['path'] === $subPath) {
                return RouteType::tryFrom($route['type'] ?? 'page') ?? RouteType::Page;
            }
        }
        return RouteType::Page;
    }
}
