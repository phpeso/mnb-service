<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Peso\Services\Tests\Helpers;

use SoapClient;
use stdClass;

final class MockSoap extends SoapClient
{
    private int $requests = 0;

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct()
    {
        // ignore
    }

    public function getRequests(): int
    {
        return $this->requests;
    }

    public function GetCurrentExchangeRates(): stdClass
    {
        ++$this->requests;
        $object = new stdClass();
        $object->GetCurrentExchangeRatesResult = file_get_contents(__DIR__ . '/../data/current.xml');
        return $object;
    }
}
