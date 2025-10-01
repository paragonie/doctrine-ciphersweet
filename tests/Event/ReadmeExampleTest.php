<?php
declare(strict_types=1);

namespace ParagonIE\DoctrineCipher\Tests\Event;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use ParagonIE\CipherSweet\CipherSweet;
use ParagonIE\CipherSweet\KeyProvider\StringProvider;
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\EncryptedField;
use ParagonIE\CipherSweet\Transformation\Lowercase;
use ParagonIE\DoctrineCipher\Event\EncryptedFieldSubscriber;
use ParagonIE\DoctrineCipher\Tests\TestEntity\Message;
use PHPUnit\Framework\TestCase;

class ReadmeExampleTest extends TestCase
{
    private EntityManager $entityManager;
    private CipherSweet $engine;

    public function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../TestEntity'],
            isDevMode: true,
        );
        $conn = \Doctrine\DBAL\DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $this->entityManager = new EntityManager($conn, $config);

        $keyProvider = new StringProvider(random_bytes(32));
        $this->engine = new CipherSweet($keyProvider);

        $subscriber = new EncryptedFieldSubscriber($this->engine);
        $subscriber->addTransformer('case-insensitive', Lowercase::class);
        $this->entityManager->getEventManager()->addEventSubscriber($subscriber);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([
            $this->entityManager->getClassMetadata(Message::class)
        ]);
    }

    public function testReadmeExample(): void
    {
        $secretMessage = 'This is a secret message.';
        $message = new Message($secretMessage);
        $this->entityManager->persist($message);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Test decryption
        $retrievedMessage = $this->entityManager->find(Message::class, $message->getId());
        $this->assertSame($secretMessage, $retrievedMessage->getText());

        // Test blind index
        $repository = $this->entityManager->getRepository(Message::class);

        $encryptedField = new EncryptedField($this->engine, 'messages', 'text');
        $encryptedField->addBlindIndex(new BlindIndex('insensitive', [new Lowercase()]));

        $blindIndex = $encryptedField->getBlindIndex($secretMessage, 'insensitive');

        $found = $repository->findOneBy(['textBlindIndexInsensitive' => $blindIndex]);
        $this->assertInstanceOf(Message::class, $found);
        $this->assertSame($secretMessage, $found->getText());
    }
}
