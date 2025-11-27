<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Peso\Services\Tests\Helpers;

use SoapClient;

final class BadSoap extends SoapClient
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct()
    {
        // ignore
    }

    private function throwError(string $method): never
    {
        throw new \SoapFault('ERR', 'Some SOAP fault in ' . $method);
    }

    public function GetCurrentExchangeRates(): never
    {
        $this->throwError(__FUNCTION__);
    }

    public function GetExchangeRates(array $params): never
    {
        $this->throwError(__FUNCTION__);
    }
}
