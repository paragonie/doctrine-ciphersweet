<?php
declare(strict_types=1);
namespace App\Tests;

use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\CipherSweet;
use ParagonIE\CipherSweet\EncryptedField;
use ParagonIE\CipherSweet\Transformation\Lowercase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DatabaseTest extends KernelTestCase
{
    public function testEncryptionAndBlindIndex(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $cipherSweet = $container->get(CipherSweet::class);

        // Create the database schema
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Exception $e) {
            // Ignore errors if schema does not exist
        }
        $schemaTool->createSchema($metadata);

        // Create and persist a message
        $secretMessage = 'This is a secret message.';
        $message = new Message($secretMessage);

        // Calculate and set the blind index before persisting
        $encryptedField = new EncryptedField($cipherSweet, 'messages', 'text');
        $encryptedField->addBlindIndex(new BlindIndex('insensitive', [new Lowercase()]));
        $searchTerm = 'this is a secret message.';
        $blindIndex = $encryptedField->getBlindIndex($searchTerm, 'insensitive');
        $message->setTextBlindIndexInsensitive($blindIndex);

        $entityManager->persist($message);
        $entityManager->flush();
        $entityManager->clear();

        // Retrieve and verify the message
        $retrievedMessage = $entityManager->find(Message::class, $message->getId());
        $this->assertSame($secretMessage, $retrievedMessage->getText());

        // Calculate blind index for searching
        $encryptedField = new EncryptedField($cipherSweet, 'messages', 'text');
        $encryptedField->addBlindIndex(new BlindIndex('insensitive', [new Lowercase()]));
        $searchTerm = 'this is a secret message.';
        $blindIndex = $encryptedField->getBlindIndex($searchTerm, 'insensitive');

        // Find the message using the blind index
        $repository = $entityManager->getRepository(Message::class);
        $foundMessage = $repository->findOneBy(['textBlindIndexInsensitive' => $blindIndex]);

        $this->assertNotNull($foundMessage);
        $this->assertSame($secretMessage, $foundMessage->getText());

        // Clean up the database
        $schemaTool->dropSchema($metadata);
    }
}
