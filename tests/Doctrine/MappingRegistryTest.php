<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\Tests\Doctrine;

use PHPUnit\Framework\TestCase;
use Yoerioptr\TabtApiBundle\Doctrine\MappingRegistry;
use Yoerioptr\TabtApiBundle\Exception\MappingNotFoundException;
use Yoerioptr\TabtApiBundle\Tests\Fixtures\Member;

final class MappingRegistryTest extends TestCase
{
    private function createRegistry(): MappingRegistry
    {
        return new MappingRegistry([
            'members' => [
                'entity' => Member::class,
                'identifier' => 'id',
                'source' => [
                    'repository' => 'member',
                    'method' => 'listMembersBy',
                    'entries' => 'getMemberEntries',
                    'parameters' => ['Club' => 'LK058'],
                ],
                'fields' => [
                    'UniqueIndex' => 'id',
                    'FirstName' => 'firstName',
                ],
            ],
        ]);
    }

    public function testItResolvesAMappingByEntity(): void
    {
        $mapping = $this->createRegistry()->forEntity(Member::class);

        self::assertSame(Member::class, $mapping->getEntity());
        self::assertSame('id', $mapping->getIdentifier());
        self::assertSame('member', $mapping->getSource()->getRepository());
        self::assertSame('listMembersBy', $mapping->getSource()->getMethod());
        self::assertSame('getMemberEntries', $mapping->getSource()->getEntries());
        self::assertSame(['Club' => 'LK058'], $mapping->getSource()->getParameters());
        self::assertSame(['UniqueIndex' => 'id', 'FirstName' => 'firstName'], $mapping->getFields());
    }

    public function testItThrowsForAnUnknownEntity(): void
    {
        $this->expectException(MappingNotFoundException::class);

        $this->createRegistry()->forEntity('App\\Entity\\Unknown');
    }
}
