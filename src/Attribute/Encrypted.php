<?php
declare(strict_types=1);

namespace ParagonIE\DoctrineCipher\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Encrypted
{
    /**
     * @param array<string, string|array{transformer: string, bits: int, fast: bool}> $blindIndexes
     * @param string $type
     */
    public function __construct(
        public array $blindIndexes = [],
        public string $type = 'string'
    ) {}
}
