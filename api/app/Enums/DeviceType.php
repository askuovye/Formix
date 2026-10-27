<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de aparelho de quem respondeu, deduzido do User-Agent no envio (resultados).
 *
 * Heurística simples, sem biblioteca: iPad com iPadOS se apresenta como Mac e conta
 * como desktop. Sem User-Agent, fica sem tipo.
 */
enum DeviceType: string
{
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case Tablet = 'tablet';

    public static function fromUserAgent(?string $userAgent): ?self
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return null;
        }

        // Android sem "Mobile" é tablet; com "Mobile", celular.
        if (preg_match('/iPad|Tablet|PlayBook|Silk|Kindle|Android(?!.*Mobile)/i', $userAgent) === 1) {
            return self::Tablet;
        }

        if (preg_match('/Mobi|iPhone|iPod|Android|Windows Phone|BlackBerry|Opera Mini/i', $userAgent) === 1) {
            return self::Mobile;
        }

        return self::Desktop;
    }
}
