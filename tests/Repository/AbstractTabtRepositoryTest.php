<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\Tests\Repository;

use Doctrine\Common\Collections\Criteria;
use PHPUnit\Framework\TestCase;
use Yoerioptr\TabtApiBundle\Doctrine\ApiFetcher;
use Yoerioptr\TabtApiBundle\Doctrine\EntityHydrator;
use Yoerioptr\TabtApiBundle\Doctrine\MappingRegistry;
use Yoerioptr\TabtApiBundle\Tests\Fixtures\FakeClient;
use Yoerioptr\TabtApiBundle\Tests\Fixtures\Member;
use Yoerioptr\TabtApiBundle\Tests\Fixtures\TestMemberRepository;
use Yoerioptr\TabtApiClient\Tabt;

final class AbstractTabtRepositoryTest extends TestCase
{
    private FakeClient $client;

    private TestMemberRepository $repository;

    protected function setUp(): void
    {
        $this->client = new FakeClient([
            'MemberCount' => 2,
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

        $this->repository = new TestMemberRepository(
            $registry,
            new EntityHydrator(),
            new ApiFetcher(new Tabt($this->client)),
        );
    }

    public function testFindAllHydratesEveryEntry(): void
    {
        $members = $this->repository->findAll();

        self::assertCount(2, $members);
        self::assertContainsOnlyInstancesOf(Member::class, $members);
        self::assertSame('Ada', $members[0]->getFirstName());
        self::assertSame('Turing', $members[1]->getLastName());
    }

    public function testTheApiIsOnlyCalledOnce(): void
    {
        $this->repository->findAll();
        $this->repository->findAll();

        self::assertSame(1, $this->client->calls);
    }

    public function testFindByIdentifier(): void
    {
        $member = $this->repository->find(102);

        self::assertInstanceOf(Member::class, $member);
        self::assertSame('Alan', $member->getFirstName());
    }

    public function testFindByReturnsMatchingEntities(): void
    {
        $members = $this->repository->findBy(['lastName' => 'Turing']);

        self::assertCount(1, $members);
        self::assertSame(102, $members[0]->getId());
    }

    public function testFindBySupportsInOperatorAndOrderingAndLimit(): void
    {
        $members = $this->repository->findBy(
            ['id' => [101, 102]],
            ['id' => 'DESC'],
            1,
        );

        self::assertCount(1, $members);
        self::assertSame(102, $members[0]->getId());
    }

    public function testFindOneByReturnsTheFirstMatch(): void
    {
        $member = $this->repository->findOneBy(['ranking' => 'C2']);

        self::assertInstanceOf(Member::class, $member);
        self::assertSame(101, $member->getId());
    }

    public function testFindOneByReturnsNullWhenNothingMatches(): void
    {
        self::assertNull($this->repository->findOneBy(['lastName' => 'Hopper']));
    }

    public function testMatchingAcceptsACriteria(): void
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->gt('id', 101));

        $members = $this->repository->matching($criteria);

        self::assertCount(1, $members);
        self::assertSame(102, $members->first()->getId());
    }

    public function testGetClassName(): void
    {
        self::assertSame(Member::class, $this->repository->getClassName());
    }
}
