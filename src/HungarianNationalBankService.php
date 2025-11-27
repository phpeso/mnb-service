<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services;

use Arokettu\Clock\SystemClock;
use Arokettu\Date\Calendar;
use Arokettu\Date\Date;
use DateInterval;
use Error;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Helpers\Calculator;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Core\Services\PesoServiceInterface;
use Peso\Core\Services\SDK\Cache\NullCache;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Core\Services\SDK\HTTP\UserAgentHelper;
use Peso\Core\Types\Decimal;
use Peso\Services\HungarianNationalBankService\XML\ExchangeRates;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;
use Sabre\Xml\Reader;
use SoapClient;
use SoapFault;

final readonly class HungarianNationalBankService implements PesoServiceInterface
{
    private SoapClient $soap;

    public function __construct(
        private CacheInterface $cache = new NullCache(),
        private DateInterval $ttl = new DateInterval('PT1H'),
        SoapClient|null $soap = null,
        array $soapOptions = [],
        private ClockInterface $clock = new SystemClock(),
    ) {
        $this->soap = $soap ?? new SoapClient('https://www.mnb.hu/arfolyamok.asmx?singleWsdl', [
            'user_agent' => UserAgentHelper::buildUserAgentString(
                'MNB-Client',
                'peso/mnb-service',
                'PHP-SOAP/' . PHP_VERSION, // same as default soap
            ),
            'keep_alive' => false,
            ...$soapOptions,
        ]);
    }

    public function send(object $request): ExchangeRateResponse|ErrorResponse
    {
        if ($request instanceof CurrentExchangeRateRequest) {
            return $this->performCurrentRequest($request);
        }
        if ($request instanceof HistoricalExchangeRateRequest) {
            return $this->performHistoricalRequest($request);
        }
        return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
    }

    private function performCurrentRequest(CurrentExchangeRateRequest $request): ErrorResponse|ExchangeRateResponse
    {
        if ($request->quoteCurrency !== 'HUF') {
            return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
        }

        $cacheKey = 'peso|mnb|current';
        $data = $this->cache->get($cacheKey);

        if ($data === null) {
            try {
                $ratesXml = $this->soap->GetCurrentExchangeRates();
            } catch (SoapFault $e) {
                throw new HttpFailureException('SOAP error: ' . $e->getMessage(), previous: $e);
            }
            $reader = new Reader();
            $reader->elementMap = [
                '{}MNBCurrentExchangeRates' => ExchangeRates::class,
            ];
            $reader->XML($ratesXml->GetCurrentExchangeRatesResult);

            $data = $reader->parse()['value'];

            $this->cache->set($cacheKey, $data, $this->ttl);
        }

        $day = array_key_first($data);
        $value = $data[$day][$request->baseCurrency] ?? null;

        if ($value === null) {
            return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
        }

        [$rate, $per] = $value;

        $rateObj = new Decimal($rate);
        if ($per !== '1') {
            $calc = Calculator::instance();
            $mul = $calc->trimZeros($calc->invert(new Decimal($per)));
            $rateObj = $calc->multiply($rateObj, $mul);
        }
        return new ExchangeRateResponse($rateObj, Calendar::parse($day));
    }

    private function performHistoricalRequest(
        HistoricalExchangeRateRequest $request,
    ): ErrorResponse|ExchangeRateResponse {
        if ($request->quoteCurrency !== 'HUF') {
            return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
        }

        // future
        if ($request->date->compare(Calendar::fromDateTime($this->clock->now())) > 0) {
            return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
        }
        // first record
        if ($request->date->compare(Calendar::parse('1949-01-03')) < 0) {
            return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
        }

        $cacheKeyBase = "peso|mnb|{$request->baseCurrency}|";

        // read cache for
        $date = $request->date;
        $retrieved = [];
        // check 5 consecutive days (there may be 4 days off in a row)
        for ($i = 0; $i < 5; $i++) {
            $ymd = $date->toString();
            $value = $retrieved[$ymd] ?? $this->cache->get($cacheKeyBase . $ymd); // find value in retrieved or cached
            // build cache
            if ($value === null) {
                $retrieved = $this->fillCache($date, $request->baseCurrency);
                $value = $retrieved[$ymd] ?? false;
            }
            if ($value === false) {
                $date = $date->subDays(1);
                continue;
            }
            // found
            break;
        }

        if ($value === false) {
            return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
        }

        [$rate, $per] = $value;

        $rateObj = new Decimal($rate);
        if ($per !== '1') {
            $calc = Calculator::instance();
            $mul = $calc->trimZeros($calc->invert(new Decimal($per)));
            $rateObj = $calc->multiply($rateObj, $mul);
        }
        return new ExchangeRateResponse($rateObj, $date);
    }

    private function fillCache(Date $date, string $currency): array
    {
        $cacheKeyBase = "peso|mnb|{$currency}|";

        try {
            // get 5 days (there may be 4 consecutive days off)
            $ratesXml = $this->soap->GetExchangeRates([
                'startDate' => $date->subDays(4)->toString(),
                'endDate' => $date->toString(),
                'currencyNames' => $currency,
            ]);
        } catch (SoapFault $e) {
            throw new HttpFailureException('SOAP error: ' . $e->getMessage(), previous: $e);
        }
        $reader = new Reader();
        $reader->elementMap = [
            '{}MNBExchangeRates' => ExchangeRates::class,
        ];
        $reader->XML($ratesXml->GetExchangeRatesResult);

        $data = $reader->parse()['value'];

        $values = [];
        $dateStore = $date;

        // store days as separate cache entries
        for ($i = 0; $i < 5; $i++) {
            $ymd = $dateStore->toString();
            $dateValues = $data[$ymd][$currency] ?? false; // false means see day earlier
            $cacheKey = $cacheKeyBase . $ymd;

            $this->cache->set($cacheKey, $dateValues, $this->ttl);
            $values[$ymd] = $dateValues;

            $dateStore = $dateStore->subDays(1);
        }

        return $values;
    }

    public function supports(object $request): bool
    {
        return ($request instanceof CurrentExchangeRateRequest || $request instanceof HistoricalExchangeRateRequest)
            && $request->quoteCurrency === 'HUF';
    }
}
