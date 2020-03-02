<?php

namespace OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails;

class Paypal extends AbstractPaymentDetails
{
    private $payerId;
    private $payerFirstname;
    private $payerLastname;
    private $payerEmailAddress;
    private $type;

    public function setPayerId($payerId)
    {
        $this->payerId = $this->enforceUnicode($payerId);
        return $this;
    }

    public function getPayerId()
    {
        return $this->payerId;
    }

    public function setPayerFirstname($payerFirstname)
    {
        $this->payerFirstname = $this->enforceUnicode($payerFirstname);
        return $this;
    }

    public function getPayerFirstname()
    {
        return $this->payerFirstname;
    }

    public function setPayerLastname($payerLastname)
    {
        $this->payerLastname = $this->enforceUnicode($payerLastname);
        return $this;
    }

    public function getPayerLastname()
    {
        return $this->payerLastname;
    }

    public function setPayerEmailAddress($payerEmailAddress)
    {
        $this->payerEmailAddress = $this->enforceUnicode($payerEmailAddress);
        return $this;
    }

    public function getPayerEmailAddress()
    {
        return $this->payerEmailAddress;
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
