<?php

namespace OrviSoft\Cloudburst\Plugin\Event;

use OrviSoft\Cloudburst\Plugin\Exception;

class ProductModified extends AbstractEvent
{
    private $products = [];

    public function getResourceType()
    {
        return 'product';
    }

    public function getLifecycleEvent()
    {
        return 'modified';
    }

    public function parseObjects(array $objects)
    {
        if (!isset($objects['products'])) {
            throw new Exception('Products key not present');
        }
        $products = [];
        foreach ($objects['products'] as $key => $product) {
            if (!isset($product['identity'])) {
                throw new Exception('Missing products informations');
            }
            $products[] = $product;
        }

        //Sort products so that variant master are at the end
        $variant_masters = [];
        $this->products = [];
        foreach ($products as $product) {
            if (isset($product['type']{0}) && $product['type'] === 'variant-master') {
                $variant_masters[] = $product;
                continue;
            }

            $this->products[] = $product;
        }

        foreach ($variant_masters as $variant_master) {
            $this->products[] = $variant_master;
        }
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
