<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\Tests\Fixtures;

use Yoerioptr\TabtApiBundle\Repository\AbstractTabtRepository;

/**
 * @extends AbstractTabtRepository<Member>
 */
final class TestMemberRepository extends AbstractTabtRepository
{
    protected function getEntityClass(): string
    {
        return Member::class;
    }
}
