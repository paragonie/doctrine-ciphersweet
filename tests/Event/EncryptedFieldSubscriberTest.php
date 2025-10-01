<?php

declare(strict_types=1);

namespace ParagonIE\DoctrineCipher\Tests\Event;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use ParagonIE\CipherSweet\CipherSweet;
use ParagonIE\CipherSweet\KeyProvider\StringProvider;
use ParagonIE\CipherSweet\Transformation\Lowercase;
use ParagonIE\DoctrineCipher\Event\EncryptedFieldSubscriber;
use ParagonIE\DoctrineCipher\Tests\CipherSweet\LastFourChars;
use ParagonIE\DoctrineCipher\Tests\TestEntity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EncryptedFieldSubscriber::class)]
class EncryptedFieldSubscriberTest extends TestCase
{
    private ?EntityManager $em = null;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../TestEntity'],
            isDevMode: true,
        );

        $conn = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        $keyProvider = new StringProvider(random_bytes(32));
        $engine = new CipherSweet($keyProvider);

        $subscriber = new EncryptedFieldSubscriber($engine);
        $subscriber->addTransformer('lastFourChars', LastFourChars::class);
        $subscriber->addTransformer('full', Lowercase::class);

        $this->em = new EntityManager(\Doctrine\DBAL\DriverManager::getConnection($conn, $config), $config);
        $this->em->getEventManager()->addEventSubscriber($subscriber);

        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $schemaTool->createSchema([$this->em->getClassMetadata(User::class)]);
    }

    public function testEncryptionAndDecryption()
    {
        $ssn = '123-456-7890';
        $user = new User();
        $user->setSsn($ssn);

        $this->em->persist($user);
        $this->em->flush();

        $conn = $this->em->getConnection();
        $stmt = $conn->prepare('SELECT ssn, ssnBlindIndexLastFour, ssnBlindIndexFull FROM user WHERE id = ?');
        $stmt->bindValue(1, $user->getId());
        $result = $stmt->executeQuery()->fetchAssociative();

        $this->assertNotNull($result['ssn']);
        $this->assertNotSame($ssn, $result['ssn']);
        $this->assertNotNull($result['ssnBlindIndexLastFour']);
        $this->assertNotNull($result['ssnBlindIndexFull']);

        $this->em->clear();

        /** @var User $foundUser */
        $foundUser = $this->em->find(User::class, $user->getId());

        $this->assertSame($ssn, $foundUser->getSsn());
        $this->assertNotNull($foundUser->getSsnBlindIndexFull());
        $this->assertNotNull($foundUser->getSsnBlindIndexLastFour());

        $newSsn = '098-765-4321';
        $foundUser->setSsn($newSsn);
        $this->em->flush();
        $this->em->clear();

        /** @var User $updatedUser */
        $updatedUser = $this->em->find(User::class, $user->getId());
        $this->assertSame($newSsn, $updatedUser->getSsn());
    }
}
