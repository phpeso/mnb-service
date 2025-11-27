# Hungarian National Bank Service for Peso

[![Packagist]][Packagist Link]
[![PHP]][Packagist Link]
[![License]][License Link]
[![GitHub Actions]][GitHub Actions Link]
[![Codecov]][Codecov Link]

[Packagist]: https://img.shields.io/packagist/v/peso/mnb-service.svg?style=flat-square
[PHP]: https://img.shields.io/packagist/php-v/peso/mnb-service.svg?style=flat-square
[License]: https://img.shields.io/packagist/l/peso/mnb-service.svg?style=flat-square
[GitHub Actions]: https://img.shields.io/github/actions/workflow/status/phpeso/mnb-service/ci.yml?style=flat-square
[Codecov]: https://img.shields.io/codecov/c/gh/phpeso/mnb-service?style=flat-square

[Packagist Link]: https://packagist.org/packages/peso/mnb-service
[GitHub Actions Link]: https://github.com/phpeso/mnb-service/actions
[Codecov Link]: https://codecov.io/gh/phpeso/mnb-service
[License Link]: LICENSE.md

This is an exchange data provider for Peso that retrieves data from
[the Hungarian National Bank (Magyar Nemzeti Bank)](https://www.mnb.hu/web/en).

## Installation

```bash
composer require peso/mnb-service
```

Install the service with all recommended dependencies:

```bash
composer install peso/mnb-service symfony/cache
```

This library also requires [`soap`](https://www.php.net/manual/en/book.soap.php) extension.

## Example

```php
<?php

use Peso\Peso\CurrencyConverter;
use Peso\Services\HungarianNationalBankService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

require __DIR__ . '/vendor/autoload.php';

$cache = new Psr16Cache(new FilesystemAdapter(directory: __DIR__ . '/cache'));
$service = new HungarianNationalBankService($cache);
$converter = new CurrencyConverter($service);
```

## Documentation

Read the full documentation here: <https://phpeso.org/v1.x/services/mnb.html>

## Support

Please file issues on our main repo at GitHub: <https://github.com/phpeso/mnb-service/issues>

## License

The library is available as open source under the terms of the [MIT License][License Link].
