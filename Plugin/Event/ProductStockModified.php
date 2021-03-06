<?php

namespace OrviSoft\Cloudburst\Plugin\Event;

use OrviSoft\Cloudburst\Plugin\Exception;

class ProductStockModified extends AbstractEvent
{
    private $products = [];

    public function getResourceType()
    {
        return 'product.stock';
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
            if (!isset($product['id']) && !isset($product['sku'])) {
                throw new Exception('Product id or sku not set');
            }
            if (!isset($product['stock'])) {
                throw new Exception('Product stock not set or invalid');
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
