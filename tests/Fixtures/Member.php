<?php

declare(strict_types=1);

namespace Yoerioptr\TabtApiBundle\Tests\Fixtures;

final class Member
{
    private ?int $id = null;

    private ?string $firstName = null;

    private ?string $lastName = null;

    private ?string $ranking = null;

    private ?string $clubId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getRanking(): ?string
    {
        return $this->ranking;
    }

    public function setRanking(?string $ranking): void
    {
        $this->ranking = $ranking;
    }

    public function getClubId(): ?string
    {
        return $this->clubId;
    }

    public function setClubId(?string $clubId): void
    {
        $this->clubId = $clubId;
    }
}
