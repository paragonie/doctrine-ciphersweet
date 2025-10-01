<?php

declare(strict_types=1);

namespace ParagonIE\DoctrineCipher\Tests\TestEntity;

use Doctrine\ORM\Mapping as ORM;
use ParagonIE\DoctrineCipher\Attribute\Encrypted;

#[ORM\Entity]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[Encrypted(blindIndexes: ['last_four' => 'lastFourChars', 'full' => 'full'])]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ssn = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $ssnBlindIndexLastFour = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $ssnBlindIndexFull = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getSsn(): ?string
    {
        return $this->ssn;
    }

    public function setSsn(?string $ssn): void
    {
        $this->ssn = $ssn;
    }

    public function getSsnBlindIndexLastFour(): ?string
    {
        return $this->ssnBlindIndexLastFour;
    }

    public function getSsnBlindIndexFull(): ?string
    {
        return $this->ssnBlindIndexFull;
    }
}
