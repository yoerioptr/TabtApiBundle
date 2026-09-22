<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\Doctrine;

use Yoerioptr\TabtApiClient\TabtInterface;

/**
 * Executes the configured TabT API request and returns the raw response entries.
 */
final class ApiFetcher
{
    public function __construct(
        private readonly TabtInterface $tabt,
    ) {
    }

    /**
     * @param array<string, scalar> $parameters
     *
     * @return iterable<object>
     */
    public function fetch(Source $source, array $parameters = []): iterable
    {
        $repository = $this->tabt->{$source->getRepository()}();
        $parameters = array_merge($source->getParameters(), $parameters);

        $response = $this->invoke($repository, $source->getMethod(), $parameters);

        $entries = $source->getEntries();

        if (!method_exists($response, $entries)) {
            throw new \RuntimeException(sprintf('Response "%s" does not expose an "%s()" method.', $response::class, $entries));
        }

        return $response->$entries();
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function invoke(object $repository, string $method, array $parameters): object
    {
        $reflection = new \ReflectionMethod($repository, $method);
        $arguments = $reflection->getParameters();

        if ([] === $arguments) {
            return $repository->$method();
        }

        $first = $arguments[0]->getType();

        if ($first instanceof \ReflectionNamedType && 'array' === $first->getName()) {
            return $repository->$method($parameters);
        }

        $normalized = [];

        foreach ($parameters as $key => $value) {
            $normalized[strtolower((string) $key)] = $value;
        }

        $values = [];

        foreach ($arguments as $argument) {
            $name = strtolower($argument->getName());

            if (array_key_exists($name, $normalized)) {
                $values[] = $normalized[$name];

                continue;
            }

            if ($argument->isDefaultValueAvailable()) {
                $values[] = $argument->getDefaultValue();

                continue;
            }

            throw new \RuntimeException(sprintf('Missing value for required parameter "$%s" of "%s::%s()".', $argument->getName(), $repository::class, $method));
        }

        return $repository->$method(...$values);
    }
}
