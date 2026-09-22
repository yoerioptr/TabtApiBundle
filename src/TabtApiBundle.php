<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Yoerioptr\TabtApiBundle\Doctrine\ApiFetcher;
use Yoerioptr\TabtApiBundle\Doctrine\EntityHydrator;
use Yoerioptr\TabtApiBundle\Doctrine\MappingRegistry;
use Yoerioptr\TabtApiClient\Client\Client;
use Yoerioptr\TabtApiClient\Client\ClientInterface;
use Yoerioptr\TabtApiClient\Entries\CredentialsType;
use Yoerioptr\TabtApiClient\Tabt;
use Yoerioptr\TabtApiClient\TabtInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class TabtApiBundle extends AbstractBundle
{
    #[\Override]
    public function configure(
        DefinitionConfigurator $definition,
    ): void {
        $definition->rootNode()
            ->children()
                ->scalarNode('username')
                    ->defaultNull()
                ->end()
                ->scalarNode('password')
                    ->defaultNull()
                ->end()
                ->arrayNode('doctrine')
                    ->addDefaultsIfNotSet()
                        ->children()
                            ->arrayNode('mappings')
                            ->useAttributeAsKey('name')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('entity')
                                            ->isRequired()
                                        ->end()
                                        ->scalarNode('identifier')
                                            ->defaultValue('id')
                                        ->end()
                                        ->arrayNode('source')
                                            ->children()
                                                ->scalarNode('repository')
                                                    ->isRequired()
                                                ->end()
                                                ->scalarNode('method')
                                                    ->isRequired()
                                                ->end()
                                                ->scalarNode('entries')
                                                    ->isRequired()
                                                ->end()
                                                ->arrayNode('parameters')
                                                    ->useAttributeAsKey('name')
                                                ->scalarPrototype()->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                        ->arrayNode('fields')
                                            ->useAttributeAsKey('name')
                                        ->scalarPrototype()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[\Override]
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $services = $configurator->services();

        $services
            ->set(CredentialsType::class)
            ->args([
                $config['username'],
                $config['password'],
            ]);

        $services
            ->set(Client::class)
            ->call('setCredentials', [
                service(CredentialsType::class),
            ]);

        $services
            ->alias(ClientInterface::class, Client::class);

        $services
            ->set(Tabt::class)
            ->args([
                service(ClientInterface::class),
            ]);

        $services
            ->alias(TabtInterface::class, Tabt::class);

        $services
            ->set(MappingRegistry::class)
            ->args([
                $config['doctrine']['mappings'],
            ]);

        $services
            ->set(EntityHydrator::class);

        $services
            ->set(ApiFetcher::class)
            ->args([
                service(TabtInterface::class),
            ]);
    }
}
