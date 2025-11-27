<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\HungarianNationalBankService\XML;

use Sabre\Xml\Reader;
use Sabre\Xml\XmlDeserializable;

/**
 * @internal
 */
final readonly class ExchangeRates implements XmlDeserializable
{
    public static function xmlDeserialize(Reader $reader): array
    {
        $data = $reader->parseInnerTree();

        $days = [];

        foreach ($data as $element) {
            if ($element['name'] === '{}Day') {
                $day = [];

                foreach ($element['value'] as $inner) {
                    if ($inner['name'] === '{}Rate') {
                        $day[$inner['attributes']['curr']] = [
                            str_replace(',', '.', $inner['value']),
                            $inner['attributes']['unit'],
                        ];
                    }
                }

                $days[$element['attributes']['date']] = $day;
            }
        }

        return $days;
    }
}
