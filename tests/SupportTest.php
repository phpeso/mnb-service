<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Date;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Services\HungarianNationalBankService;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SupportTest extends TestCase
{
    public function testRequests(): void
    {
        $service = new HungarianNationalBankService();

        self::assertTrue($service->supports(new CurrentExchangeRateRequest('EUR', 'HUF')));
        self::assertTrue($service->supports(new HistoricalExchangeRateRequest('EUR', 'HUF', Date::today())));
        self::assertFalse($service->supports(new CurrentExchangeRateRequest('HUF', 'EUR')));
        self::assertFalse($service->supports(new HistoricalExchangeRateRequest('HUF', 'EUR', Date::today())));
        self::assertFalse($service->supports(new stdClass()));
    }
}
