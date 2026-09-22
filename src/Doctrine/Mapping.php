<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\Doctrine;

/**
 * Maps a TabT API response entry to a local entity.
 */
final class Mapping
{
    /**
     * @param array<string, string> $fields entry field => entity property
     */
    public function __construct(
        private readonly string $entity,
        private readonly string $identifier,
        private readonly Source $source,
        private readonly array $fields,
    ) {
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    /**
     * @return array<string, string>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
