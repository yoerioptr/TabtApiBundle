<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\Tests\Doctrine;

use PHPUnit\Framework\TestCase;
use Yoerioptr\TabtApiBundle\Doctrine\EntityHydrator;
use Yoerioptr\TabtApiBundle\Tests\Fixtures\Member;
use Yoerioptr\TabtApiClient\Entries\MemberEntry;

final class EntityHydratorTest extends TestCase
{
    public function testItHydratesAnEntityFromAnEntry(): void
    {
        $entry = new MemberEntry([
            'UniqueIndex' => 101,
            'FirstName' => 'Ada',
            'LastName' => 'Lovelace',
            'Ranking' => 'C2',
        ]);

        $member = (new EntityHydrator())->hydrate(Member::class, $entry, [
            'UniqueIndex' => 'id',
            'FirstName' => 'firstName',
            'LastName' => 'lastName',
            'Ranking' => 'ranking',
        ]);

        self::assertInstanceOf(Member::class, $member);
        self::assertSame(101, $member->getId());
        self::assertSame('Ada', $member->getFirstName());
        self::assertSame('Lovelace', $member->getLastName());
        self::assertSame('C2', $member->getRanking());
    }

    public function testItLeavesMissingMappedFieldsNull(): void
    {
        $entry = new MemberEntry([
            'UniqueIndex' => 101,
            'FirstName' => 'Ada',
        ]);

        $member = (new EntityHydrator())->hydrate(Member::class, $entry, [
            'UniqueIndex' => 'id',
            'Club' => 'clubId',
        ]);

        self::assertSame(101, $member->getId());
        self::assertNull($member->getClubId());
    }
}
