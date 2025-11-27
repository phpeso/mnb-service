<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Peso\Core\Helpers\Calculator;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Services\HungarianNationalBankService;
use Peso\Services\Tests\Helpers\MockSoap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class WrappedServicesTest extends TestCase
{
    public function testReversible(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $soap = new MockSoap();

        $baseService = new HungarianNationalBankService(cache: $cache, soap: $soap);
        $service = HungarianNationalBankService::reversible(cache: $cache, soap: $soap);

        $request = new CurrentExchangeRateRequest('HUF', 'USD');

        // base service doesn't support
        self::assertInstanceOf(ErrorResponse::class, $baseService->send($request));

        $response = $service->send($request);
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        // ignore calculator changes
        self::assertEquals('0.0030101', Calculator::instance()->round($response->rate, 7)->value);
    }
}
