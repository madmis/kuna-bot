<?php

namespace App\Strategy\Traits;

use App\Exception\BreakIterationException;
use App\Exception\StopBotException;
use App\Service\KunaClient;
use madmis\ExchangeApi\Exception\ClientException;

/**
 * Trait PreconfiguredOrders
 * @package App\Strategy\Traits
 * @method KunaClient getClient()
 * @method void info(string $message)
 */
trait PreconfiguredOrders
{
    /**
     * @param string $currency
     * @param float $orderPrice
     * @param float $boundary
     * @param array $margin
     * @param bool $isBuyOrder
     * @return array [['volume' => ..., 'price' => ...], ...]
     * @throws BreakIterationException
     * @throws StopBotException
     */
    public function createPreconfiguredOrders(
        string $currency,
        float $orderPrice,
        float $boundary,
        array $margin,
        bool $isBuyOrder): array
    {
        try {
            $account = $this->getClient()->getCurrencyAccount($currency);
        } catch (ClientException $e) {
            $ex = new BreakIterationException($e->getMessage(), $e->getCode(), $e);
            $ex->setTimeout(10);

            throw $ex;
        } catch (\Throwable $e) {
            throw new StopBotException($e->getMessage(), $e->getCode(), $e);
        }

        if ($account->getBalance() < $boundary) {
            $this->info('<y>Account balance less than boundary, so only 1 order can be opened</y>');
            // can create only one order
            $margin = array_slice($margin, 0, 1);
        }

        $minAmount = $this->getClient()->minTradeAmount($currency);
        $volume = $account->getBalance() / count($margin);
        if ($volume < $minAmount) {
            $volume = $account->getBalance();
        }

        $this->info('<w>Create orders configurations:</w>');
        $orders = [];
        foreach ($margin as $key => $orderMargin) {
            $orderVolume = $volume;
            $price = bcadd($orderPrice, $orderMargin, 6);
            if ($isBuyOrder) {
                $orderVolume = bcdiv($orderVolume, $price, 6);
            }

            $key++;
            $this->info("<w>Order #{$key}: volume|{$orderVolume} price|{$price}</w>");

            $orders[] = ['volume' => $orderVolume, 'price' => $price];
        }

        return $orders;
    }
}
