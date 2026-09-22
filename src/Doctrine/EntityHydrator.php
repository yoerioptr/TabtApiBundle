<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\Doctrine;

/**
 * Creates local entities from TabT API entries based on a configured field map.
 */
final class EntityHydrator
{
    /**
     * @param class-string          $entityClass
     * @param array<string, string> $fields      entry field => entity property
     */
    public function hydrate(string $entityClass, object $entry, array $fields): object
    {
        $reflection = new \ReflectionClass($entityClass);
        $entity = $reflection->newInstanceWithoutConstructor();

        foreach ($fields as $entryField => $property) {
            $this->writeValue($entity, $property, $this->readValue($entry, (string) $entryField));
        }

        return $entity;
    }

    private function readValue(object $entry, string $field): mixed
    {
        $getter = 'get'.ucfirst($field);

        if (method_exists($entry, $getter)) {
            return $entry->$getter();
        }

        $property = lcfirst($field);
        $public = get_object_vars($entry);

        if (array_key_exists($property, $public)) {
            return $public[$property];
        }

        if (!property_exists($entry, $property)) {
            return null;
        }

        try {
            $reflection = new \ReflectionProperty($entry, $property);
        } catch (\ReflectionException) {
            return null;
        }

        if (!$reflection->isInitialized($entry)) {
            return null;
        }

        return $reflection->getValue($entry);
    }

    private function writeValue(object $entity, string $property, mixed $value): void
    {
        $setter = 'set'.ucfirst($property);

        if (method_exists($entity, $setter)) {
            $entity->$setter($value);

            return;
        }

        if (!property_exists($entity, $property)) {
            return;
        }

        try {
            $reflection = new \ReflectionProperty($entity, $property);
        } catch (\ReflectionException) {
            return;
        }

        $reflection->setValue($entity, $value);
    }
}
