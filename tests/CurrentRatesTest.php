<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Services\HungarianNationalBankService;
use Peso\Services\Tests\Helpers\MockSoap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class CurrentRatesTest extends TestCase
{
    public function testRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $soap = new MockSoap();

        $service = new HungarianNationalBankService(cache: $cache, soap: $soap);

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'HUF'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('383.04000', $response->rate->value);
        self::assertEquals('2025-11-24', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('JPY', 'HUF'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('2.1192000', $response->rate->value);
        self::assertEquals('2025-11-24', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('PHP', 'HUF'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('5.64000', $response->rate->value);
        self::assertEquals('2025-11-24', $response->date->toString());

        self::assertEquals(1, $soap->getRequests()); // subsequent requests are cached
    }
}
