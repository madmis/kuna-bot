<?php

namespace App\Strategy\Traits;

use madmis\KunaApi\Model\Order;

/**
 * Trait CloseNotTopBuyOrders
 * @package App\Strategy\Traits
 * @method \App\Service\KunaClient getClient()
 * @method void info(string $message)
 */
trait CloseNotTopBuyOrders
{
    /**
     * @param string $pair
     */
    public function closeNotTopBuyOrders(string $pair)
    {
        $this->info('<g>Close not TOP buy orders.</g>');

        /** @var \madmis\KunaApi\Model\Order[] $activeOrders */
        $activeOrders = $this->getClient()->getActiveBuyOrders($pair);
        $count = count($activeOrders);
        $this->info("\t<g>Active buy orders: {$count}</g>");

        if ($count) {
            $bidOrders = $this->getClient()->shared()->bidsOrderBook($pair, true);
            if ($bidOrders) {
                /** @var \madmis\KunaApi\Model\Order $topOrder */
                $topOrder = $bidOrders[0];
                foreach ($activeOrders as $activeOrder) {
                    if ($activeOrder->getId() !== $topOrder->getId()) {
                        $this->info(sprintf(
                            "\t<w>Order #%s: volume|%s price|%s - not in the TOP. Close it.</w>",
                            $activeOrder->getId(),
                            $activeOrder->getVolume(),
                            $activeOrder->getPrice()
                        ));
                        $this->getClient()->signed()->cancelOrder($activeOrder->getId());
                        $this->info("\t<w>Order closed</w>");
                    } else {
                        $this->info(sprintf(
                            "\t<g>Order #%s: volume|%s price|%s - in the TOP. Don't close it.</g>",
                            $activeOrder->getId(),
                            $activeOrder->getVolume(),
                            $activeOrder->getPrice()
                        ));
                    }
                }
            }
        } else {
            $this->info("\t<y>There is nothing to close</y>");
        }
    }
}
