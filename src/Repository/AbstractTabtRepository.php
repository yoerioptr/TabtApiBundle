<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\Repository;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;
use Yoerioptr\TabtApiBundle\Doctrine\ApiFetcher;
use Yoerioptr\TabtApiBundle\Doctrine\EntityHydrator;
use Yoerioptr\TabtApiBundle\Doctrine\Mapping;
use Yoerioptr\TabtApiBundle\Doctrine\MappingRegistry;

/**
 * In-memory, DB-free repository that hydrates entities from the TabT API once
 * per request and supports the Doctrine ObjectRepository/Criteria query API.
 *
 * @template T of object
 *
 * @implements ObjectRepository<T>
 * @implements Selectable<int, T>
 */
abstract class AbstractTabtRepository implements ObjectRepository, Selectable
{
    /**
     * @var ArrayCollection<int, T>|null
     */
    private ?ArrayCollection $collection = null;

    public function __construct(
        private readonly MappingRegistry $mappings,
        private readonly EntityHydrator $hydrator,
        private readonly ApiFetcher $fetcher,
    ) {
    }

    /**
     * @return class-string<T>
     */
    abstract protected function getEntityClass(): string;

    /**
     * Additional, runtime request parameters merged over the configured ones.
     *
     * @return array<string, scalar>
     */
    protected function getFetchParameters(): array
    {
        return [];
    }

    protected function getMapping(): Mapping
    {
        return $this->mappings->forEntity($this->getEntityClass());
    }

    /**
     * @return ArrayCollection<int, T>
     */
    protected function getCollection(): ArrayCollection
    {
        if (null !== $this->collection) {
            return $this->collection;
        }

        $mapping = $this->getMapping();
        $collection = new ArrayCollection();

        foreach ($this->fetcher->fetch($mapping->getSource(), $this->getFetchParameters()) as $entry) {
            $collection->add($this->hydrator->hydrate($mapping->getEntity(), $entry, $mapping->getFields()));
        }

        return $this->collection = $collection;
    }

    public function find(mixed $id): ?object
    {
        return $this->findOneBy([$this->getMapping()->getIdentifier() => $id]);
    }

    public function findAll(): array
    {
        return $this->getCollection()->toArray();
    }

    public function findBy(
        array $criteria,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        $criteriaObject = Criteria::create();

        if ([] !== $criteria) {
            $criteriaObject = $criteriaObject->where($this->buildExpression($criteria));
        }

        if (null !== $orderBy) {
            $criteriaObject = $criteriaObject->orderBy($this->normalizeOrderBy($orderBy));
        }

        if (null !== $offset) {
            $criteriaObject = $criteriaObject->setFirstResult($offset);
        }

        if (null !== $limit) {
            $criteriaObject = $criteriaObject->setMaxResults($limit);
        }

        return array_values($this->matching($criteriaObject)->toArray());
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        return $this->findBy($criteria, $orderBy, 1)[0] ?? null;
    }

    public function matching(Criteria $criteria): Collection
    {
        return $this->getCollection()->matching($criteria);
    }

    public function getClassName(): string
    {
        return $this->getEntityClass();
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function buildExpression(array $criteria): Expression
    {
        $expressions = [];

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $expressions[] = Criteria::expr()->in($field, $value);

                continue;
            }

            $expressions[] = null === $value
                ? Criteria::expr()->isNull($field)
                : Criteria::expr()->eq($field, $value);
        }

        return Criteria::expr()->andX(...$expressions);
    }

    /**
     * @param array<string, string> $orderBy
     *
     * @return array<string, \SortDirection>
     */
    private function normalizeOrderBy(array $orderBy): array
    {
        return array_map(
            static fn (mixed $direction): \SortDirection => $direction instanceof \SortDirection
                ? $direction
                : ('DESC' === strtoupper((string) $direction) ? \SortDirection::Descending : \SortDirection::Ascending),
            $orderBy,
        );
    }
}
