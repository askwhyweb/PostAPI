<?php

namespace OrviSoft\Cloudburst\Plugin\Event;

use OrviSoft\Cloudburst\Plugin\Exception;

class ProductSync extends AbstractEvent
{
    private $products = [];

    public function getResourceType()
    {
        return 'product';
    }

    public function getLifecycleEvent()
    {
        return 'sync';
    }

    public function parseObjects(array $objects)
    {
        if (!isset($objects['products'])) {
            throw new Exception('Products key not present');
        }
        $products = [];
        foreach ($objects['products'] as $key => $product) {
            if (!isset($product['sourceId']) && !isset($product['sku'])) {
                throw new Exception('Product sourceId or sku not set');
            }
            $products[] = $product;
        }
        $this->products = $products;
    }

    public function getProducts()
    {
        return $this->products;
    }

    public function getObjects()
    {
        return ['products' => $this->products];
    }
}
