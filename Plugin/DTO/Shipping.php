<?php

namespace OrviSoft\Cloudburst\Plugin\DTO;

class Shipping extends AbstractDTO
{
    private $method;
    private $methodLabel;
    private $net;
    private $gross;
    private $tax;
    private $taxCode;
    private $gift_message;

    public function setMethod($method)
    {
        $this->method = $this->enforceUnicode($method);
        return $this;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function setMethodLabel($methodLabel)
    {
        $this->methodLabel = $this->enforceUnicode($methodLabel);
        return $this;
    }

    public function getMethodLabel()
    {
        return $this->methodLabel;
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

    public function setTaxCode($taxCode)
    {
        $this->taxCode = $this->enforceUnicode($taxCode);
        return $this;
    }

    public function getTaxCode()
    {
        return $this->taxCode;
    }

    public function setGiftMessage($message, $recipient = null, $sender = null)
    {
        $this->gift_message = [
            'message'   => $message,
            'recipient' => $recipient,
            'sender'    => $sender,
        ];
        return $this;
    }

    public function getGiftMessage()
    {
        return $this->gift_message;
    }

    public function toArray()
    {
        return \get_object_vars($this);
    }
}
