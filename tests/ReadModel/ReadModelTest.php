<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\Tests\ReadModel;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Yoerioptr\TabtApiBundle\Doctrine\ApiFetcher;
use Yoerioptr\TabtApiBundle\Doctrine\EntityHydrator;
use Yoerioptr\TabtApiBundle\Doctrine\MappingRegistry;
use Yoerioptr\TabtApiBundle\ReadModel\ReadModel;
use Yoerioptr\TabtApiBundle\Tests\Fixtures\FakeClient;
use Yoerioptr\TabtApiBundle\Tests\Fixtures\Member;
use Yoerioptr\TabtApiClient\Tabt;

final class ReadModelTest extends TestCase
{
    private FakeClient $client;

    private Connection $connection;

    private ReadModel $readModel;

    protected function setUp(): void
    {
        $this->client = new FakeClient([
            'MemberCount' => 3,
            'MemberEntries' => [
                [
                    'Position' => 1,
                    'UniqueIndex' => 101,
                    'RankingIndex' => 5,
                    'FirstName' => 'Ada',
                    'LastName' => 'Lovelace',
                    'Ranking' => 'C2',
                ],
                [
                    'Position' => 2,
                    'UniqueIndex' => 102,
                    'RankingIndex' => 8,
                    'FirstName' => 'Alan',
                    'LastName' => 'Turing',
                    'Ranking' => 'B4',
                ],
                [
                    'Position' => 3,
                    'UniqueIndex' => 103,
                    'RankingIndex' => 3,
                    'FirstName' => 'Grace',
                    'LastName' => 'Hopper',
                    'Ranking' => 'B4',
                ],
            ],
        ]);

        $registry = new MappingRegistry([
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
                    'LastName' => 'lastName',
                    'Ranking' => 'ranking',
                ],
            ],
        ]);

        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->readModel = new ReadModel(
            $this->connection,
            $registry,
            new EntityHydrator(),
            new ApiFetcher(new Tabt($this->client)),
        );
    }

    public function testItCreatesAScalarProjectionTable(): void
    {
        $this->readModel->query(Member::class)->select('*');

        $columns = array_map(
            static fn (string $column): string => mb_strtolower($column),
            array_keys($this->connection->createSchemaManager()->listTableColumns('member')),
        );

        self::assertSame(['id', 'firstname', 'lastname', 'ranking'], $columns);
    }

    public function testQueryFiltersWithSql(): void
    {
        $rows = $this->readModel->query(Member::class)
            ->select('*')
            ->where('LOWER(lastName) LIKE :name')
            ->setParameter('name', '%turing%')
            ->executeQuery()
            ->fetchAllAssociative();

        self::assertCount(1, $rows);
        self::assertSame('Turing', $rows[0]['lastName']);
    }

    public function testQuerySupportsDistinctAndOrdering(): void
    {
        $rankings = $this->readModel->query(Member::class)
            ->select('DISTINCT ranking')
            ->orderBy('ranking', 'ASC')
            ->executeQuery()
            ->fetchFirstColumn();

        self::assertSame(['B4', 'C2'], $rankings);
    }

    public function testTheApiIsOnlyCalledOnceAcrossQueries(): void
    {
        $this->readModel->query(Member::class)->select('*');
        $this->readModel->query(Member::class)->select('*');

        self::assertSame(1, $this->client->calls);
    }

    public function testHydrateResolvesTheEntitiesInRowOrder(): void
    {
        $rows = $this->readModel->query(Member::class)
            ->select('*')
            ->orderBy('id', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        $members = $this->readModel->hydrate(Member::class, $rows);

        self::assertContainsOnlyInstancesOf(Member::class, $members);
        self::assertSame(
            ['Grace', 'Alan', 'Ada'],
            array_map(static fn (Member $member): ?string => $member->getFirstName(), $members),
        );
    }

    public function testHydrateSkipsRowsWithoutAResolvableEntity(): void
    {
        $members = $this->readModel->hydrate(Member::class, [
            ['id' => 999],
            ['id' => null],
        ]);

        self::assertSame([], $members);
    }

    public function testItExposesTheUnderlyingConnection(): void
    {
        self::assertSame($this->connection, $this->readModel->connection());
    }
}
