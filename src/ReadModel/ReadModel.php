<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\ReadModel;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Yoerioptr\TabtApiBundle\Doctrine\ApiFetcher;
use Yoerioptr\TabtApiBundle\Doctrine\EntityHydrator;
use Yoerioptr\TabtApiBundle\Doctrine\Mapping;
use Yoerioptr\TabtApiBundle\Doctrine\MappingRegistry;

/**
 * Ephemeral SQLite projection of the TabT API.
 *
 * Every mapped entity gets its own table which is created and seeded from the
 * API on first use. The data is then queried through the Doctrine DBAL
 * QueryBuilder, while matched rows are resolved back to the hydrated entities
 * (including non-scalar, unmapped-in-SQL properties).
 *
 * Tables are seeded and cached once per process. With the default in-memory
 * connection this means once per request, preserving the API as the source of
 * truth without any staleness.
 */
final class ReadModel
{
    /**
     * @var array<class-string, bool>
     */
    private array $seeded = [];

    /**
     * @var array<class-string, array<string, array{type: string}>>
     */
    private array $columns = [];

    /**
     * @var array<class-string, array<string, object>>
     */
    private array $entities = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly MappingRegistry $mappings,
        private readonly EntityHydrator $hydrator,
        private readonly ApiFetcher $fetcher,
    ) {
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * Returns a QueryBuilder for the entity's projection, seeding it on first
     * use. The query is already scoped to the entity's table, so call
     * `select()`, `where()`, `orderBy()`, ... directly on the returned builder.
     *
     * @param class-string $entityClass
     */
    public function query(string $entityClass): QueryBuilder
    {
        $this->seed($entityClass);

        return $this->connection->createQueryBuilder()->from($this->table($entityClass));
    }

    /**
     * @param class-string $entityClass
     */
    public function table(string $entityClass): string
    {
        return mb_strtolower((new \ReflectionClass($entityClass))->getShortName());
    }

    /**
     * Resolves rows (which must contain the mapping identifier column) back to
     * their hydrated entities, preserving the given row order. Rows without a
     * resolvable entity are skipped.
     *
     * @param class-string                     $entityClass
     * @param array<int, array<string, mixed>> $rows
     *
     * @return list<object>
     */
    public function hydrate(string $entityClass, array $rows): array
    {
        $identifier = $this->mappings->forEntity($entityClass)->getIdentifier();

        $entities = [];

        foreach ($rows as $row) {
            $id = $row[$identifier] ?? null;

            if (null === $id) {
                continue;
            }

            $entity = $this->entities[$entityClass][(string) $id] ?? null;

            if (null !== $entity) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    private function seed(string $entityClass): void
    {
        if (isset($this->seeded[$entityClass])) {
            return;
        }

        $mapping = $this->mappings->forEntity($entityClass);
        $columns = $this->columns($entityClass, $mapping);

        if ([] === $columns) {
            throw new \LogicException(sprintf('The mapping for "%s" does not expose any scalar property to query.', $entityClass));
        }

        $this->createTable($entityClass, $columns);

        $identifier = $mapping->getIdentifier();
        $table = $this->table($entityClass);

        $this->connection->beginTransaction();

        try {
            foreach ($this->fetcher->fetch($mapping->getSource()) as $entry) {
                $entity = $this->hydrator->hydrate($mapping->getEntity(), $entry, $mapping->getFields());

                $id = $this->hydrator->readValue($entity, $identifier);

                if (null !== $id) {
                    $this->entities[$entityClass][(string) $id] = $entity;
                }

                $this->connection->insert($table, $this->row($entity, $columns));
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        $this->seeded[$entityClass] = true;
    }

    /**
     * @param array<string, array{type: string}> $columns
     */
    private function createTable(string $entityClass, array $columns): void
    {
        $schema = $this->connection->createSchemaManager();
        $table = $this->table($entityClass);

        if ($schema->tablesExist([$table])) {
            return;
        }

        $editor = Table::editor()->setUnquotedName($table);

        foreach ($columns as $name => $column) {
            $editor->addColumn(
                Column::editor()
                    ->setUnquotedName($name)
                    ->setTypeName($column['type'])
                    ->setNotNull(false)
                    ->create(),
            );
        }

        $schema->createTable($editor->create());
    }

    /**
     * @param array<string, array{type: string}> $columns
     *
     * @return array<string, mixed>
     */
    private function row(object $entity, array $columns): array
    {
        $row = [];

        foreach (array_keys($columns) as $name) {
            $value = $this->hydrator->readValue($entity, $name);

            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif (is_bool($value)) {
                $value = (int) $value;
            }

            $row[$name] = $value;
        }

        return $row;
    }

    /**
     * The scalar, SQL-queryable columns of an entity: every mapped property
     * whose type maps to a DBAL scalar type, plus the identifier.
     *
     * @return array<string, array{type: string}>
     */
    private function columns(string $entityClass, Mapping $mapping): array
    {
        if (isset($this->columns[$entityClass])) {
            return $this->columns[$entityClass];
        }

        $properties = array_values($mapping->getFields());
        $properties[] = $mapping->getIdentifier();

        $columns = [];

        foreach (array_unique($properties) as $property) {
            $type = $this->propertyType($entityClass, (string) $property);

            if (null !== $type) {
                $columns[(string) $property] = ['type' => $type];
            }
        }

        return $this->columns[$entityClass] = $columns;
    }

    /**
     * @param class-string $entityClass
     *
     * @return string|null the DBAL type, or null when the property is not a scalar
     */
    private function propertyType(string $entityClass, string $property): ?string
    {
        if (!property_exists($entityClass, $property)) {
            return null;
        }

        $type = (new \ReflectionProperty($entityClass, $property))->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return null;
        }

        return match ($type->getName()) {
            'int' => Types::INTEGER,
            'bool' => Types::BOOLEAN,
            'float' => Types::FLOAT,
            'string' => Types::STRING,
            default => null,
        };
    }
}
