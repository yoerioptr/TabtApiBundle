<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\Doctrine;

/**
 * Describes which TabT API call should be executed to populate an entity collection.
 */
final class Source
{
    /**
     * @param array<string, scalar> $parameters
     */
    public function __construct(
        private readonly string $repository,
        private readonly string $method,
        private readonly string $entries,
        private readonly array $parameters = [],
    ) {
    }

    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getEntries(): string
    {
        return $this->entries;
    }

    /**
     * @return array<string, scalar>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
