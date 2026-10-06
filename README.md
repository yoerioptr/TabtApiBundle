# TabtApiBundle

[![No AI](https://custom-icon-badges.demolab.com/badge/No%20AI-2f2f2f?logo=non-ai&logoColor=white&logoSize=auto)](#)

The TabT API Bundle is a Symfony integration for the [TabT API Client](https://github.com/yoerioptr/TabtApiClient), a helper library for Frenoy's TabT API.

## Requirements

* PHP `>=8.4`
* Symfony `^6.4`, `^7.0` or `^8.0`
* Doctrine DBAL `^4.0`
* `yoerioptr/tabt-api-client` `^2.1`

## Installation

Install the package using Composer.

```bash
composer require yoerioptr/tabt-api-bundle
```

Register the bundle if Symfony Flex did not register it automatically.

```php
// config/bundles.php

return [
    // ...

    Yoerioptr\TabtApiBundle\TabtApiBundle::class => ['all' => true],
];
```

Credentials are optional and only required for API requests that require authentication.

```dotenv
TABT_USERNAME=username
TABT_PASSWORD=password
```

## Configuration

The following configuration options are available.

```yaml
# config/packages/tabt_api.yaml

tabt_api:
    username: null
    password: null

    read_model:
        enabled: false

    doctrine:
        mappings:
            # A mapping describes how a TabT API response is turned into a local entity.
            members:
                entity: App\Entity\Member
                identifier: id
                source:
                    repository: member
                    method: listMembersBy
                    entries: getMemberEntries
                    parameters:
                        Club: '%env(TABT_CLUB)%'
                fields:
                    UniqueIndex: id
                    FirstName: firstName
                    LastName: lastName
                    Ranking: ranking
```

The `read_model.enabled` flag registers the SQLite read model (see below). It defaults to `false`.

Each entry under `doctrine.mappings` accepts the following options:

| Option | Description |
| --- | --- |
| `entity` | Fully qualified class name of the entity to hydrate. |
| `identifier` | The entity property that uniquely identifies a row. Defaults to `id`. |
| `source.repository` | The TabT API repository to call, e.g. `member`. |
| `source.method` | The method to call on that repository, e.g. `listMembersBy`. |
| `source.entries` | The response method that returns the entry list, e.g. `getMemberEntries`. |
| `source.parameters` | Static parameters passed to the request. Each value must be a scalar. |
| `fields` | Map of API entry field => entity property. |

## Making Requests

The TabT API Client is automatically registered as a Symfony service and can be injected using `TabtInterface`.

```php
use Yoerioptr\TabtApiClient\TabtInterface;

final class ExampleService
{
    public function __construct(
        private readonly TabtInterface $tabt,
    ) {
    }

    public function example(): void
    {
        $testResponse = $this->tabt->test()->info();
    }
}
```

Executing requests makes use of repositories, which can easily be accessed from the TabT helper class.

```php
$testResponse = $this->tabt->test()->info();
```

All usable requests can be found in the [TabT API Client](https://github.com/yoerioptr/TabtApiClient).

## Entities

Entities are plain PHP classes. They do not require any Doctrine ORM attributes: the bundle hydrates them from the API using the configured `fields` map and reads/writes values through setters, public properties or private properties.

```php
namespace App\Entity;

final class Member
{
    private ?int $id = null;

    private ?string $firstName = null;

    private ?string $lastName = null;

    private ?string $ranking = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): static
    {
        $this->id = $id;

        return $this;
    }

    // ...
}
```

Properties with a scalar type (`int`, `bool`, `float`, `string`) are exposed as columns when the read model is enabled. Other types can still be hydrated, but are not queryable through SQL.

## Repositories

There are two ways to query mapped entities.

### Read model

The read model is an ephemeral SQLite projection of the API. Each mapped entity gets its own table which is created and seeded on first use, after which it can be queried using the Doctrine DBAL `QueryBuilder`. Matched rows are then resolved back to the hydrated entities.

Enable it in your configuration.

```yaml
# config/packages/tabt_api.yaml

tabt_api:
    read_model:
        enabled: true
```

Inject `ReadModel` and use `query()` to build a query and `hydrate()` to turn rows back into entities.

```php
use App\Entity\Member;
use Yoerioptr\TabtApiBundle\ReadModel\ReadModel;

final class MemberRepository
{
    public function __construct(
        private readonly ReadModel $readModel,
    ) {
    }

    public function find(int $id): ?Member
    {
        $rows = $this->readModel->query(Member::class)
            ->select('*')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->readModel->hydrate(Member::class, $rows)[0] ?? null;
    }

    /**
     * @return list<Member>
     */
    public function findByLastName(string $lastName): array
    {
        $rows = $this->readModel->query(Member::class)
            ->select('*')
            ->where('LOWER(lastName) LIKE :name')
            ->setParameter('name', '%' . mb_strtolower($lastName) . '%')
            ->orderBy('lastName', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->readModel->hydrate(Member::class, $rows);
    }
}
```

`query()` returns a query builder already scoped to the entity's table, so you call `select()`, `where()`, `orderBy()`, ... directly on it. Only the scalar columns of an entity are stored in the projection; `hydrate()` returns the full hydrated entity (including non-scalar, unmapped-in-SQL properties) for every row that contains the mapping identifier.

Tables are seeded once per process. With the default in-memory connection this means once per request, so the API always remains the source of truth without any staleness.

### In-memory repositories

If you do not need SQL, extend `AbstractTabtRepository`. It hydrates the mapped entities once per request and supports the Doctrine `ObjectRepository`/`Selectable` API (`find`, `findAll`, `findBy`, `findOneBy`, `matching`).

```php
use App\Entity\Member;
use Yoerioptr\TabtApiBundle\Repository\AbstractTabtRepository;

/**
 * @extends AbstractTabtRepository<Member>
 */
final class MemberRepository extends AbstractTabtRepository
{
    protected function getEntityClass(): string
    {
        return Member::class;
    }
}
```

Override `getFetchParameters()` to merge additional runtime parameters over the configured ones.

```php
protected function getFetchParameters(): array
{
    return ['Club' => 'LK058'];
}
```

## Integrating in a Symfony application

The bundle is designed to be wired up with the framework's default autowiring. This is the recommended setup for a Symfony application.

### 1. Register the bundle

```php
// config/bundles.php

return [
    // ...

    Yoerioptr\TabtApiBundle\TabtApiBundle::class => ['all' => true],
];
```

### 2. Configure the credentials

```dotenv
# .env

###> yoerioptr/tabt-api-bundle ###
TABT_USERNAME=
TABT_PASSWORD=
TABT_CLUB=
###< yoerioptr/tabt-api-bundle
```

### 3. Configure the mappings

```yaml
# config/packages/tabt_api.yaml

tabt_api:
    username: '%env(TABT_USERNAME)%'
    password: '%env(TABT_PASSWORD)%'

    read_model:
        enabled: true

    doctrine:
        mappings:
            members:
                entity: App\Entity\Member
                identifier: id
                source:
                    repository: member
                    method: listMembersBy
                    entries: getMemberEntries
                    parameters:
                        Club: '%env(TABT_CLUB)%'
                fields:
                    UniqueIndex: id
                    FirstName: firstName
                    LastName: lastName
                    Ranking: ranking

            matches:
                entity: App\Entity\CompetitionMatch
                identifier: matchId
                source:
                    repository: match
                    method: listMatchesBy
                    entries: getTeamMatchesEntries
                    parameters:
                        Club: '%env(TABT_CLUB)%'
                        ShowDivisionName: 'yes'
                fields:
                    MatchId: matchId
                    MatchUniqueId: matchUniqueId
                    WeekName: weekName
                    Date: date
                    Time: time
                    Venue: venue
                    HomeClub: homeClub
                    HomeTeam: homeTeam
                    AwayClub: awayClub
                    AwayTeam: awayTeam
                    Score: score
                    DivisionId: divisionId
                    DivisionName: divisionName
                    IsValidated: isValidated
```

### 4. Create the entities

Create a plain PHP class for every mapped entity. See [Entities](#entities) for a full example.

### 5. Create the repositories

Inject `ReadModel` and query the entity's projection. The repository is registered as a service automatically through the `App\` autowiring rule.

```php
namespace App\Repository;

use App\Entity\CompetitionMatch;
use Yoerioptr\TabtApiBundle\ReadModel\ReadModel;

final class MatchRepository
{
    public function __construct(
        private readonly ReadModel $readModel,
    ) {
    }

    /**
     * @return list<CompetitionMatch>
     */
    public function findAll(): array
    {
        $rows = $this->readModel->query(CompetitionMatch::class)
            ->select('*')
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->readModel->hydrate(CompetitionMatch::class, $rows);
    }

    public function find(string $matchId): ?CompetitionMatch
    {
        $rows = $this->readModel->query(CompetitionMatch::class)
            ->select('*')
            ->where('matchId = :matchId')
            ->setParameter('matchId', $matchId)
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->readModel->hydrate(CompetitionMatch::class, $rows)[0] ?? null;
    }
}
```

### 6. Use the repositories

```php
namespace App\Controller;

use App\Repository\MatchRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarController
{
    public function __construct(
        private readonly MatchRepository $matchRepository,
    ) {
    }

    #[Route('/calendar', name: 'calendar')]
    public function __invoke(): Response
    {
        $matches = $this->matchRepository->findAll();

        return $this->render('calendar/index.html.twig', [
            'matches' => $matches,
        ]);
    }
}
```

## License

This package is released under the MIT License.
