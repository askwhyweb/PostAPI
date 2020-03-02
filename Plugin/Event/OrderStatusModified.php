<?php

namespace OrviSoft\Cloudburst\Plugin\Event;

use OrviSoft\Cloudburst\Plugin\Exception;

class OrderStatusModified extends AbstractEvent
{
    private $orders = [];

    public function getResourceType()
    {
        return 'order.status';
    }

    public function getLifecycleEvent()
    {
        return 'modified';
    }

    public function parseObjects(array $objects)
    {
        if (!isset($objects['orders'])) {
            throw new Exception('Orders key not present');
        }
        $orders = [];
        foreach ($objects['orders'] as $key => $order) {
            if (!isset($order['id'])) {
                throw new Exception('Order id not set');
            }
            if (!isset($order['statusId'])) {
                throw new Exception('Order statusId not set');
            }
            $orders[] = $order;
        }
        $this->orders = $orders;
    }

    public function getOrders()
    {
        return $this->orders;
    }

    public function getObjects()
    {
        return ['order' => $this->orders];
    }
}
