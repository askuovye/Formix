<?php

declare(strict_types=1);

use App\Enums\DeviceType;

it('deduz o aparelho pelo User-Agent', function (?string $userAgent, ?DeviceType $expected) {
    expect(DeviceType::fromUserAgent($userAgent))->toBe($expected);
})->with([
    'Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36', DeviceType::Desktop],
    'Mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 Version/18.0 Safari/605.1.15', DeviceType::Desktop],
    'iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile/15E148 Safari/604.1', DeviceType::Mobile],
    'Android celular' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36', DeviceType::Mobile],
    'Android tablet' => ['Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 Chrome/130.0 Safari/537.36', DeviceType::Tablet],
    'iPad antigo' => ['Mozilla/5.0 (iPad; CPU OS 12_0 like Mac OS X) Mobile/15E148 Safari/604.1', DeviceType::Tablet],
    'Kindle' => ['Mozilla/5.0 (Linux; Android 11; KFTRWI) AppleWebKit/537.36 Silk/120.0 Safari/537.36', DeviceType::Tablet],
    'vazio' => ['  ', null],
    'ausente' => [null, null],
]);
