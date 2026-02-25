<?php

namespace Platform\Enum;

enum RouteType: string
{
    case Page = 'page';
    case Ajax = 'ajax';
    case Stream = 'stream';

    public function estPassthrough(): bool
    {
        return $this === self::Ajax || $this === self::Stream;
    }
}
