<?php
declare(strict_types=1);
namespace App\Factory;

use ParagonIE\CipherSweet\KeyProvider\StringProvider;

class CipherSweetKeyProviderFactory
{
    public static function create(string $hexKey): StringProvider
    {
        return new StringProvider(hex2bin($hexKey));
    }
}
