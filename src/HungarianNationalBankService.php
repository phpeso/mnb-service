<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services;

use Arokettu\Date\Calendar;
use DateInterval;
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
use Peso\Services\HungarianNationalBankService\XML\MNBCurrentExchangeRates;
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
                '{}MNBCurrentExchangeRates' => MNBCurrentExchangeRates::class,
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

    public function supports(object $request): bool
    {
        return ($request instanceof CurrentExchangeRateRequest /*|| $request instanceof HistoricalExchangeRateRequest*/)
            && $request->quoteCurrency === 'HUF';
    }
}
