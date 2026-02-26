<?php

namespace Platform\Enum;

enum ModeAffichage: string
{
    case Embedded = 'embedded';
    case Iframe = 'iframe';
    case Passthrough = 'passthrough';

    public function estIframe(): bool
    {
        return $this === self::Iframe;
    }

    public function estPassthrough(): bool
    {
        return $this === self::Passthrough;
    }

    public function estEmbedded(): bool
    {
        return $this === self::Embedded;
    }

    public function label(): string
    {
        return match ($this) {
            self::Embedded => 'Intégré (extractParts)',
            self::Iframe => 'Iframe (app complète)',
            self::Passthrough => 'Passthrough (sans layout)',
        };
    }
}
