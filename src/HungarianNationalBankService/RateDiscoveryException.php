<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\HungarianNationalBankService;

use Exception;
use Peso\Core\Exceptions\RuntimeException;

final class RateDiscoveryException extends Exception implements RuntimeException
{
}
