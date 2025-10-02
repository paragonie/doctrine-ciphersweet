<?php
declare(strict_types=1);
namespace App\Entity;

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

    #[ORM\Column(type: 'text')]
    #[Encrypted(blindIndexes: ['insensitive' => 'case-insensitive'])]
    private string $text;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
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

    public function setTextBlindIndexInsensitive(?string $textBlindIndexInsensitive): self
    {
        $this->textBlindIndexInsensitive = $textBlindIndexInsensitive;

        return $this;
    }
}
