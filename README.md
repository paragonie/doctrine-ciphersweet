# Doctrine CipherSweet Adapter

[![Build Status](https://github.com/paragonie/doctrine-ciphersweet/actions/workflows/ci.yml/badge.svg)](https://github.com/paragonie/doctrine-ciphersweet/actions)
[![Example App](https://github.com/paragonie/doctrine-ciphersweet/actions/workflows/example-app.yml/badge.svg)](https://github.com/paragonie/doctrine-ciphersweet/tree/main/docs/example-app)
[![Static Analysis](https://github.com/paragonie/doctrine-ciphersweet/actions/workflows/psalm.yml/badge.svg)](https://github.com/paragonie/doctrine-ciphersweet/actions)
[![Latest Stable Version](https://poser.pugx.org/paragonie/doctrine-ciphersweet/v/stable)](https://packagist.org/packages/paragonie/doctrine-cipher)
[![Latest Unstable Version](https://poser.pugx.org/paragonie/doctrine-ciphersweet/v/unstable)](https://packagist.org/packages/paragonie/doctrine-cipher)
[![License](https://poser.pugx.org/paragonie/doctrine-ciphersweet/license)](https://packagist.org/packages/paragonie/doctrine-cipher)
[![Downloads](https://img.shields.io/packagist/dt/paragonie/doctrine-cipher.svg)](https://packagist.org/packages/paragonie/doctrine-cipher)

> [!IMPORTANT]
> This adapter is still being developed. It's only being open sourced so
> it may be tested in a Symfony application. Please don't use it yet.

Use searchable encryption with [Doctrine ORM](https://github.com/doctrine/orm), powered by 
[CipherSweet](https://ciphersweet.paragonie.com/).

## Installation

```bash
composer require paragonie/doctrine-ciphersweet
```

## Usage

First, you need to create a `ParagonIE\CipherSweet\CipherSweet` object. Please refer to 
[the CipherSweet docs](https://ciphersweet.paragonie.com/php/setup).

```php
use ParagonIE\CipherSweet\CipherSweet;
use ParagonIE\CipherSweet\KeyProvider\StringProvider;

$keyProvider = new StringProvider(random_bytes(32));
$engine = new CipherSweet($keyProvider);
```

Next, create an `EncryptedFieldSubscriber` and register it with your `EntityManager`.

```php
use ParagonIE\DoctrineCipher\Event\EncryptedFieldSubscriber;

$subscriber = new EncryptedFieldSubscriber($engine);
$entityManager->getEventManager()->addEventSubscriber($subscriber);
```

Now you can use the `#[Encrypted]` attribute on your entity properties.

```php
use Doctrine\ORM\Mapping as ORM;
use ParagonIE\DoctrineCipher\Attribute\Encrypted;

#[ORM\Entity]
class Message
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: 'text')]
    #[Encrypted]
    private string $text;
    
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $textBlindIndexInsensitive;

    public function __construct(string $text)
    {
        $this->text = $text;
    }

    // ... getters and setters
}
```

When you persist an entity, the `EncryptedFieldSubscriber` will automatically encrypt the properties that have the
`#[Encrypted]` attribute.

```php
$message = new Message('This is a secret message.');
$entityManager->persist($message);
$entityManager->flush();
```

When you retrieve an entity, the encrypted properties will be automatically decrypted.

```php
$message = $entityManager->find(Message::class, 1);
echo $message->getText(); // "This is a secret message."
```

### Blind Indexes

You can also use blind indexes for searchable encryption. To do this, add a `blindIndexes` argument to the 
`#[Encrypted]` attribute.

```php
use Doctrine\ORM\Mapping as ORM;
use ParagonIE\DoctrineCipher\Attribute\Encrypted;

#[ORM\Entity]
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

    // ... getters and setters
}
```

You also need to register a transformer for the blind index.

```php
use ParagonIE\CipherSweet\Transformation\Lowercase;

$subscriber->addTransformer('case-insensitive', Lowercase::class);
```

Now you can query the blind index.

To do so, you must first calculate the blind index for your search term.

```php
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\EncryptedField;

// First, you need to get the blind index for your search term.
// Note: The EncryptedField must be configured exactly as it is for the entity.
$encryptedField = new EncryptedField($engine, 'messages', 'text');
$encryptedField->addBlindIndex(new BlindIndex('insensitive', [new Lowercase()]));

$searchTerm = 'this is a secret message.';
$blindIndex = $encryptedField->getBlindIndex($searchTerm, 'insensitive');

// Now you can use this blind index to query the database.
$repository = $entityManager->getRepository(Message::class);
$message = $repository->findOneBy(['textBlindIndexInsensitive' => $blindIndex]);
```

## Support Contracts

If your company uses this library in their products or services, you may be
interested in [purchasing a support contract from Paragon Initiative Enterprises](https://paragonie.com/enterprise).
