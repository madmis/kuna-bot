<?php

namespace App\Service;

use madmis\ExchangeApi\Client\ClientInterface;
use madmis\ExchangeApi\Exception\ClientException;
use madmis\KunaApi\KunaApi;
use madmis\KunaApi\Model\MyAccount;

/**
 * Class KunaClient
 * @package App\Service
 */
class KunaClient extends KunaApi
{
    const PAIR_BTCUAH = 'btcuah';
    const PAIR_GOLBTC = 'golbtc';
    const PAIR_ETHUAH = 'ethuah';
    const PAIR_WAVESUAH = 'wavesuah';
    const PAIR_KUNBTC = 'kunbtc';
    const PAIR_BCHBTC = 'bchbtc';

    /**
     * @var array
     */
    private $minTradeAmounts = [
        'btc' => 0.01,
        'uah' => 50,
        'kun' => 1,
        'gol' => 1,
        'eth' => 0.01,
        'waves' => 1,
        'bch' => 0.01,
    ];

    /**
     * @param string $publicKey
     * @param string $secretKey
     */
    public function __construct($publicKey, $secretKey)
    {
        parent::__construct('https://kuna.io', $publicKey, $secretKey);
    }

    /**
     * @param array $amounts
     */
    public function setMinTradeAmounts(array $amounts)
    {
        if ($amounts) {
            $this->minTradeAmounts = array_merge($this->minTradeAmounts, $amounts);
        }
    }

    /**
     * @param string $currency
     *
     * @return MyAccount
     * @throws ClientException
     * @throws \LogicException
     */
    public function getCurrencyAccount(string $currency): MyAccount
    {
        // check pair balance. If we have base currency sell it
        $accounts = $this->signed()->me(true)->getAccounts();
        $filtered = array_filter($accounts,
            function (MyAccount $account) use ($currency) {
                return $account->getCurrency() === $currency;
            });

        if (!$filtered) {
            throw new \LogicException("Can't find account for currency: {$currency}");
        }

        return reset($filtered);
    }

    /**
     * @param string $pair
     *
     * @return array
     */
    public static function splitPair(string $pair): array
    {
        if ($pair === self::PAIR_WAVESUAH) {
            return str_split($pair, 5);
        }

        return str_split($pair, 3);
    }

    /**
     * Get minimal trade amount for currency
     *
     * @param string $currency
     *
     * @return float
     */
    public function minTradeAmount(string $currency): float
    {
        $res = $this->minTradeAmounts[$currency] ?? 1000;

        return (float)$res;
    }
}
