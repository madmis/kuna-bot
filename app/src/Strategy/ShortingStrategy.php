<?php

namespace App\Strategy;

use App\Strategy\Traits\CloseNotTopBuyOrders;
use App\Strategy\Traits\MinimumTradeAmount;

/**
 * Class ShortingStrategy
 * @package App\Strategy
 */
class ShortingStrategy extends Strategy
{
    use CloseNotTopBuyOrders;
    use MinimumTradeAmount;

    /**
     * @param string $pair
     * @return float
     */
    public function getCurrentSellPrice(string $pair): float
    {
        $orders = $this->getClient()->shared()->asksOrderBook($pair, true);
        /** @var \madmis\KunaApi\Model\Order $topOrder */
        $topOrder = $orders[0];

        return $topOrder->getPrice();
    }

    /**
     * @param string $pair
     * @return float
     */
    public function getCurrentBuyPrice(string $pair): float
    {
        /** @var \madmis\KunaApi\Model\Order $topOrder */
        $topOrder = $this->getClient()->shared()->bidsOrderBook($pair, true)[0];

        return $topOrder->getPrice();
    }
}
