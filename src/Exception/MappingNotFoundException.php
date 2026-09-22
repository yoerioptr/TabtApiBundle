<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\Exception;

final class MappingNotFoundException extends \RuntimeException
{
    /**
     * @param class-string $entityClass
     */
    public static function forEntity(string $entityClass): self
    {
        return new self(sprintf(
            'No TabT mapping is configured for entity "%s". Did you forget to configure "tabt_api.doctrine.mappings"?',
            $entityClass,
        ));
    }
}
