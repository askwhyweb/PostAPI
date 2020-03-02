<?php

namespace OrviSoft\Cloudburst\Plugin\DTO;

class LineItem extends AbstractDTO
{
    private $name;
    private $options = [];
    private $productId;
    private $sku;
    private $quantity;
    private $rowNet;
    private $rowGross;
    private $rowTax;
    private $taxCode;
    private $type;

    public function setName($name)
    {
        $this->name = $this->enforceUnicode($name);
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function addOption($key, $value)
    {
        $this->options[$key] = $this->enforceUnicode($value);
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setProductId($productId)
    {
        $this->productId = $this->enforceUnicode($productId);
        return $this;
    }

    public function getProductId()
    {
        return $this->productId;
    }

    public function setSku($sku)
    {
        $this->sku = $this->enforceUnicode($sku);
        return $this;
    }

    public function getSku()
    {
        return $this->sku;
    }

    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setRowNet($rowNet)
    {
        $this->rowNet = $rowNet;
        return $this;
    }

    public function getRowNet()
    {
        return $this->rowNet;
    }

    public function setRowGross($rowGross)
    {
        $this->rowGross = $rowGross;
        return $this;
    }

    public function getRowGross()
    {
        return $this->rowGross;
    }

    public function setRowTax($rowTax)
    {
        $this->rowTax = $rowTax;
        return $this;
    }

    public function getRowTax()
    {
        return $this->rowTax;
    }

    public function setTaxCode($taxCode)
    {
        $this->taxCode = $this->enforceUnicode($taxCode);
        return $this;
    }

    public function getTaxCode()
    {
        return $this->taxCode;
    }

    public function setType($type)
    {
        $this->type = $this->enforceUnicode($type);
        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function toArray()
    {
        return \get_object_vars($this);
    }
}
