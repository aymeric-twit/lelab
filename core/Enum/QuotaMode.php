<?php

namespace Platform\Enum;

enum QuotaMode: string
{
    case None = 'none';
    case Request = 'request';
    case FormSubmit = 'form_submit';
    case ApiCall = 'api_call';
    case Url = 'url';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Aucun',
            self::Request => 'Par requête',
            self::FormSubmit => 'Par soumission',
            self::ApiCall => 'Par appel API',
            self::Url => 'Par URL',
        };
    }

    public function estSuivi(): bool
    {
        return $this !== self::None;
    }
}
