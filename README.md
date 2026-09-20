# TabtApiBundle

[![No AI](https://custom-icon-badges.demolab.com/badge/No%20AI-2f2f2f?logo=non-ai&logoColor=white&logoSize=auto)](#)

The TabT API Bundle is a Symfony integration for the [TabT API Client](https://github.com/yoerioptr/TabtApiClient), a helper library for Frenoy's TabT API.

## Setup

Basic setup to get you started using the bundle.

First of all, install the package using Composer

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

Optionally configure your TabT credentials.

```yaml
# config/packages/tabt_api.yaml

tabt_api:
    username: '%env(TABT_USERNAME)%'
    password: '%env(TABT_PASSWORD)%'
```

Add the credentials to your environment.

```dotenv
TABT_USERNAME=username
TABT_PASSWORD=password
```

Credentials are optional and only required for API requests that require authentication.

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

## Configuration

The following configuration options are available.

```yaml
tabt_api:
    username: null
    password: null
```

Both `username` and `password` are optional.

## Requirements

* PHP
* Symfony
* `yoerioptr/tabt-api-client`

## License

This package is released under the MIT License.

