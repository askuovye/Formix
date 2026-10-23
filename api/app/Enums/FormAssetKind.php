<?php

declare(strict_types=1);

namespace App\Enums;

enum FormAssetKind: string
{
    case Cover = 'cover';
    case Logo = 'logo';
}
