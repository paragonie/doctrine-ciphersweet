<?php
declare(strict_types=1);

namespace ParagonIE\DoctrineCipher\Tests\TestEntity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ParagonIE\DoctrineCipher\Attribute\Encrypted;

#[ORM\Entity]
#[ORM\Table(name: 'messages')]
class Message
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::TEXT)]
    #[Encrypted(blindIndexes: ['insensitive' => 'case-insensitive'])]
    private string $text;

    #[ORM\Column(type: Types::STRING, length: 255, name: 'text_blind_index_insensitive', nullable: true)]
    private ?string $textBlindIndexInsensitive;

    public function __construct(string $text)
    {
        $this->text = $text;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
