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

    public function GetCurrentExchangeRates(): never
    {
        throw new \SoapFault('ERR', 'Some SOAP fault...');
    }
}
