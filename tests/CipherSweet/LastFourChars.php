<?php

declare(strict_types=1);

namespace ParagonIE\DoctrineCipher\Tests\CipherSweet;

use ParagonIE\CipherSweet\Contract\TransformationInterface;

class LastFourChars implements TransformationInterface
{
    public function __invoke(mixed $input): string
    {
        return substr((string) $input, -4);
    }
}
