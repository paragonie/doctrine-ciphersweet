# Using the CipherSweet adapter for Doctrine

This guide will walk you through using the adapter in your Doctrine-based apps.

## Installation

```bash
composer require paragonie/doctrine-ciphersweet
```

## Configuration

First, you need a `ParagonIE\CipherSweet\CipherSweet` object. Please refer to
[the CipherSweet docs](https://ciphersweet.paragonie.com/php/setup) for more information.

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

### Symfony Configuration

If you're using Symfony, you can configure the subscriber in your `services.yaml` file.

First, make sure you have a `CIPHERSWEET_KEY` environment variable defined in your `.env` file.
It must be a 64-character hexadecimal string.

```env
# .env
CIPHERSWEET_KEY=your-64-character-hexadecimal-key
```

Then, configure the services in `config/services.yaml`:

```yaml
# config/services.yaml
parameters:
    env(CIPHERSWEET_KEY): ''

services:
    ParagonIE\CipherSweet\KeyProvider\StringProvider:
        factory: ['App\Factory\CipherSweetKeyProviderFactory', 'create']
        arguments:
            - '%env(CIPHERSWEET_KEY)%'

    ParagonIE\CipherSweet\CipherSweet:
        arguments:
            - '@ParagonIE\CipherSweet\KeyProvider\StringProvider'

    ParagonIE\DoctrineCipher\Event\EncryptedFieldSubscriber:
        arguments:
            - '@ParagonIE\CipherSweet\CipherSweet'
        tags:
            - { name: doctrine.event_subscriber, connection: default }
```

You will also need to create a factory to create the `StringProvider` from the hexadecimal key
in your `.env` file.

```php
// src/Factory/CipherSweetKeyProviderFactory.php
<?php
declare(strict_types=1);
namespace App\Factory;

use ParagonIE\CipherSweet\KeyProvider\StringProvider;

final class CipherSweetKeyProviderFactory
{
    public static function create(string $key): StringProvider
    {
        return new StringProvider(hex2bin($key));
    }
}
```

## Usage

Once the above steps are complete, you can use the `#[Encrypted]` attribute on your entity properties.

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

Observe the attribute: `#[Encrypted(blindIndexes: ['insensitive' => 'case-insensitive'])]`. 

In order for this to succeed, you need to register a transformer for the blind index.

```php
use ParagonIE\CipherSweet\Transformation\Lowercase;

$subscriber->addTransformer('case-insensitive', Lowercase::class);
```

If you're using Symfony, you can add the transformer to your `services.yaml` file.

```yaml
# config/services.yaml
services:
    ParagonIE\DoctrineCipher\Event\EncryptedFieldSubscriber:
        # ...
        calls:
            - ['addTransformer', ['case-insensitive', 'ParagonIE\CipherSweet\Transformation\Lowercase']]
```

## Complete Example

Now you can query the blind index. To do so, you must first calculate the blind index for your search term.

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

## Example App

The [example](example) directory contains an example Symfony application that uses the Doctrine-CipherSweet adapter.
This example app is tested as part of our CI/CD pipeline, so the code there is guaranteed to work if the build passes.
