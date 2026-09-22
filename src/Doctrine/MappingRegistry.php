<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\Doctrine;

use Yoerioptr\TabtApiBundle\Exception\MappingNotFoundException;

/**
 * Holds all configured entity mappings, indexed by their entity class.
 */
final class MappingRegistry
{
    /**
     * @var array<class-string, Mapping>
     */
    private array $mappings = [];

    /**
     * @param array<string, array{
     *     entity: class-string,
     *     identifier: string,
     *     source: array{
     *         repository: string,
     *         method: string,
     *         entries: string,
     *         parameters?: array<string, scalar>,
     *     },
     *     fields: array<string, string>,
     * }> $config
     */
    public function __construct(array $config)
    {
        foreach ($config as $mapping) {
            $source = new Source(
                $mapping['source']['repository'],
                $mapping['source']['method'],
                $mapping['source']['entries'],
                $mapping['source']['parameters'] ?? [],
            );

            $this->mappings[$mapping['entity']] = new Mapping(
                $mapping['entity'],
                $mapping['identifier'],
                $source,
                $mapping['fields'],
            );
        }
    }

    /**
     * @param class-string $entityClass
     */
    public function forEntity(string $entityClass): Mapping
    {
        return $this->mappings[$entityClass]
            ?? throw MappingNotFoundException::forEntity($entityClass);
    }

    /**
     * @return array<class-string, Mapping>
     */
    public function all(): array
    {
        return $this->mappings;
    }
}
