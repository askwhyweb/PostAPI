<?php

namespace OrviSoft\Cloudburst\Plugin\Event;

use OrviSoft\Cloudburst\Plugin\Exception;

class OrderCreated extends AbstractEvent
{
    private $orders = [];

    public function getResourceType()
    {
        return 'order';
    }

    public function getLifecycleEvent()
    {
        return 'created';
    }

    public function addOrder(array $data)
    {
        $errors = $this->checkOrder($data);
        if ($errors) {
            return $errors;
        }
        $this->orders[] = $data;
        $this->syncIdSet();
        return null;
    }

    public function checkOrder(array $data)
    {
        return $data ? [] : ['empty data'];
    }

    private function syncIdSet()
    {
        $ids = [];
        foreach ($this->orders as $order) {
            $ids[] = $order['id'];
        }
        $this->setIdSet(\implode(',', $ids));
    }

    public function parseObjects(array $objects)
    {
        if (!isset($objects['orders'])) {
            throw new Exception('Orders key not present');
        }
        $orders = [];
        foreach ($objects['orders'] as $key => $order) {
            $errors = $this->checkOrder($order);
            if ($errors) {
                return $errors;
            }
            $orders[] = $order;
        }
        return $this->orders = $orders;
    }

    public function getOrders()
    {
        return $this->orders;
    }

    public function getObjects()
    {
        return ['orders' => $this->orders];
    }
}
