<?php

namespace App\Strategy;

use App\Strategy\Traits\MinimumTradeAmount;
use App\Strategy\Traits\PreconfiguredOrders;
use madmis\KunaApi\Model\Order;

/**
 * Class SimpleStrategy
 * @package App\Strategy
 */
class SimpleStrategy extends Strategy
{
    use MinimumTradeAmount;
    use PreconfiguredOrders;

    /**
     * @param string $pair
     * @return float
     */
    public function getCurrentSellPrice(string $pair): float
    {
        $orders = $this->getClient()->shared()->asksOrderBook($pair, true);
        /** @var Order $topOrder */
        $topOrder = $orders[0];

        return $topOrder->getPrice();
    }

    /**
     * @param string $pair
     * @return float
     */
    public function getCurrentBuyPrice(string $pair): float
    {
        /** @var Order $topOrder */
        $topOrder = $this->getClient()->shared()->bidsOrderBook($pair, true)[0];

        return $topOrder->getPrice();
    }
}
