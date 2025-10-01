<?php
declare(strict_types=1);
namespace ParagonIE\DoctrineCipher\Event;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs as DoctrineLifecycleEventArgs;
use Override;
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\CipherSweet;
use ParagonIE\CipherSweet\Contract\TransformationInterface;
use ParagonIE\CipherSweet\EncryptedField;
use ParagonIE\CipherSweet\Exception\BlindIndexNameCollisionException;
use ParagonIE\CipherSweet\Exception\BlindIndexNotFoundException;
use ParagonIE\CipherSweet\Exception\CipherSweetException;
use ParagonIE\CipherSweet\Exception\CryptoOperationException;
use ParagonIE\DoctrineCipher\Attribute\Encrypted;
use RuntimeException;
use SodiumException;

/**
 * @api
 */
class EncryptedFieldSubscriber implements EventSubscriber
{
    private CipherSweet $cipherSweet;
    private array $encryptedFieldsCache = [];

    /** @var array<string, class-string<TransformationInterface>> */
    private array $transformers = [];

    public function __construct(CipherSweet $cipherSweet)
    {
        $this->cipherSweet = $cipherSweet;
    }

    /**
     * @param string $name
     * @param class-string<TransformationInterface> $className
     */
    public function addTransformer(string $name, string $className): void
    {
        if (!class_exists($className) || !in_array(TransformationInterface::class, class_implements($className))) {
            throw new \InvalidArgumentException("Invalid transformer class: " . $className);
        }
        $this->transformers[$name] = $className;
    }

    #[Override]
    public function getSubscribedEvents(): array
    {
        return [
            Events::postLoad,
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    public function postLoad(DoctrineLifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $em = $args->getObjectManager();
        if (!($em instanceof EntityManagerInterface)) {
            return;
        }
        $meta = $em->getClassMetadata(get_class($entity));

        foreach ($meta->getReflectionProperties() as $refProperty) {
            $attributes = $refProperty->getAttributes(Encrypted::class);
            if (empty($attributes)) {
                continue;
            }

            $encryptedAttribute = $attributes[0]->newInstance();
            $encryptedField = $this->getEncryptedField(
                get_class($entity),
                $refProperty->getName(),
                $encryptedAttribute,
                $args
            );

            $ciphertext = $refProperty->getValue($entity);
            if (!empty($ciphertext)) {
                $plaintext = $encryptedField->decryptValue($ciphertext);
                $refProperty->setValue($entity, $plaintext);
            }
        }
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->processEntity($args);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->processEntity($args);
    }

    /**
     * @throws CipherSweetException
     * @throws CryptoOperationException
     * @throws BlindIndexNotFoundException
     * @throws SodiumException
     * @throws BlindIndexNameCollisionException
     */
    private function processEntity(DoctrineLifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $em = $args->getObjectManager();
        if (!($em instanceof EntityManagerInterface)) {
            return;
        }
        $meta = $em->getClassMetadata(get_class($entity));
        $uow = $em->getUnitOfWork();
        $processed = false;

        foreach ($meta->getReflectionProperties() as $refProperty) {
            $attributes = $refProperty->getAttributes(Encrypted::class);
            if (empty($attributes)) {
                continue;
            }

            $propName = $refProperty->getName();
            if ($args instanceof PreUpdateEventArgs && !$args->hasChangedField($propName)) {
                continue;
            }
            $processed = true;

            $encryptedAttribute = $attributes[0]->newInstance();
            $encryptedField = $this->getEncryptedField(
                get_class($entity),
                $propName,
                $encryptedAttribute,
                $args
            );

            $plaintext = $refProperty->getValue($entity);
            [$ciphertext, $blindIndexes] = $encryptedField->prepareForStorage($plaintext ?? '');
            $refProperty->setValue($entity, $ciphertext);

            foreach ($blindIndexes as $name => $value) {
                $camelCaseName = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
                $blindIndexPropertyName = $propName . 'BlindIndex' . $camelCaseName;
                if ($meta->hasField($blindIndexPropertyName)) {
                    $refProp = $meta->getPropertyAccessor($blindIndexPropertyName);
                    $refProp->setValue($entity, $value);
                }
            }
        }

        if ($processed && $args instanceof PreUpdateEventArgs) {
            $uow->recomputeSingleEntityChangeSet($meta, $entity);
        }
    }

    /**
     * @throws CipherSweetException
     * @throws CryptoOperationException
     * @throws BlindIndexNameCollisionException
     */
    private function getEncryptedField(
        string $entityClass,
        string $propertyName,
        Encrypted $attribute,
        DoctrineLifecycleEventArgs $args
    ): EncryptedField {
        $cacheKey = "{$entityClass}::{$propertyName}";
        if (isset($this->encryptedFieldsCache[$cacheKey])) {
            return $this->encryptedFieldsCache[$cacheKey];
        }

        $em = $args->getObjectManager();
        if (!($em instanceof EntityManagerInterface)) {
            throw new RuntimeException('Expected an EntityManagerInterface');
        }
        $meta = $em->getClassMetadata($entityClass);
        $tableName = $meta->getTableName();

        $field = new EncryptedField(
            $this->cipherSweet,
            $tableName,
            $propertyName
        );

        foreach ($attribute->blindIndexes as $indexName => $indexConfig) {
            $transformerName = is_string($indexConfig) ? $indexConfig : ($indexConfig['transformer'] ?? null);
            $bits = is_array($indexConfig) ? ($indexConfig['bits'] ?? 256) : 256;
            $fast = is_array($indexConfig) ? ($indexConfig['fast'] ?? false) : false;

            $transformations = [];
            if ($transformerName) {
                if (!isset($this->transformers[$transformerName])) {
                    throw new RuntimeException("Unknown transformer: " . $transformerName);
                }
                $transformerClass = $this->transformers[$transformerName];
                $transformations[] = new $transformerClass();
            }

            $blindIndex = new BlindIndex(
                $indexName,
                $transformations,
                $bits,
                $fast
            );
            $field->addBlindIndex($blindIndex);
        }

        return $this->encryptedFieldsCache[$cacheKey] = $field;
    }
}
