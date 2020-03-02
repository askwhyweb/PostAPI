<?php

namespace OrviSoft\Cloudburst\Plugin\DTO;

class Discount extends AbstractDTO
{
    private $label;
    private $taxCode;
    private $net;
    private $gross;
    private $tax;

    public function setLabel($label)
    {
        $this->label = $this->enforceUnicode($label);
        return $this;
    }

    public function getLabel()
    {
        return $this->label;
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

    public function setNet($net)
    {
        $this->net = $net;
        return $this;
    }

    public function getNet()
    {
        return $this->net;
    }

    public function setGross($gross)
    {
        $this->gross = $gross;
        return $this;
    }

    public function getGross()
    {
        return $this->gross;
    }

    public function setTax($tax)
    {
        $this->tax = $tax;
        return $this;
    }

    public function getTax()
    {
        return $this->tax;
    }

    public function toArray()
    {
        return \get_object_vars($this);
    }
}
