<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Calendar;
use Arokettu\Date\Date;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Services\HungarianNationalBankService;
use Peso\Services\Tests\Helpers\BadSoap;
use Peso\Services\Tests\Helpers\MockSoap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class HistoricalRatesTest extends TestCase
{
    public function testExactRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $soap = new MockSoap();
        $date = Calendar::parse('2025-11-26');
//        $discoveredDate = Calendar::parse('2023-12-26');

        $service = new HungarianNationalBankService(cache: $cache, soap: $soap);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'HUF', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('382.06000', $response->rate->value);
        self::assertEquals('2025-11-26', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('JPY', 'HUF', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('2.1102000', $response->rate->value);
        self::assertEquals('2025-11-26', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('PHP', 'HUF', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('5.61000', $response->rate->value);
        self::assertEquals('2025-11-26', $response->date->toString());

        self::assertEquals(3, $soap->getRequests());

        // previous day is cached
        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'HUF', $date->subDays(1)));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('382.08', $response->rate->value);
        self::assertEquals('2025-11-25', $response->date->toString());

        self::assertEquals(3, $soap->getRequests());
    }

    public function testDiscoveredRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $soap = new MockSoap();
        $date = Calendar::parse('2023-12-26');

        $service = new HungarianNationalBankService(cache: $cache, soap: $soap);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'HUF', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('382.20', $response->rate->value);
        self::assertEquals('2023-12-22', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('JPY', 'HUF', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('2.4432', $response->rate->value);
        self::assertEquals('2023-12-22', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('PHP', 'HUF', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('6.27', $response->rate->value);
        self::assertEquals('2023-12-22', $response->date->toString());

        self::assertEquals(3, $soap->getRequests());
    }

//    public function testNoRate(): void
//    {
//        $cache = new Psr16Cache(new ArrayAdapter());
//        $soap = new MockSoap();
//
//        $service = new HungarianNationalBankService(cache: $cache, soap: $soap);
//
//        // unknown currency
//        $response = $service->send(new CurrentExchangeRateRequest('KZT', 'HUF'));
//        self::assertInstanceOf(ErrorResponse::class, $response);
//        self::assertEquals('Unable to find exchange rate for KZT/HUF', $response->exception->getMessage());
//
//        // reverse rate
//        $response = $service->send(new CurrentExchangeRateRequest('HUF', 'USD'));
//        self::assertInstanceOf(ErrorResponse::class, $response);
//        self::assertEquals('Unable to find exchange rate for HUF/USD', $response->exception->getMessage());
//    }

    public function testSoapFault(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $soap = new BadSoap();

        $service = new HungarianNationalBankService(cache: $cache, soap: $soap);

        self::expectException(HttpFailureException::class);
        self::expectExceptionMessage('SOAP error: Some SOAP fault in GetExchangeRates');
        $service->send(new HistoricalExchangeRateRequest('EUR', 'HUF', Date::today()));
    }
}
