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
    public function getCurrentPrice(string $pair): float
    {
        /** @var Order $topOrder */
        $topOrder = $this->getClient()->shared()->asksOrderBook($pair, true)[0];

        return $topOrder->getPrice();
    }
}
