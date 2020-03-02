<?php

namespace OrviSoft\Cloudburst\Plugin\DTO;

use OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails\DefaultDetails;
use OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails\Epdq;
use OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails\InfusionSoft;
use OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails\Paypal;
use OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails\Sagepay;
use OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails\WorldPay;

class Payment extends AbstractDTO
{
    private $method;
    private $currency;
    private $baseCurrency;
    private $amount;
    private $baseAmount;
    private $notes      = [];
    private $exceptions = [];

    /* @var $details \OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails\AbstractPaymentDetails */
    private $details;


    public function setMethod($method)
    {
        $this->method = $this->enforceUnicode($method);
        return $this;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function setCurrency($currency)
    {
        $this->currency = $this->enforceUnicode($currency);
        return $this;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function setBaseCurrency($baseCurrency)
    {
        $this->baseCurrency = $this->enforceUnicode($baseCurrency);
        return $this;
    }

    public function getBaseCurrency()
    {
        return $this->baseCurrency;
    }

    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function setBaseAmount($baseAmount)
    {
        $this->baseAmount = $baseAmount;
        return $this;
    }

    public function getBaseAmount()
    {
        return $this->baseAmount;
    }

    public function addNote($note)
    {
        $this->notes[] = $this->enforceUnicode($note);
        return $this;
    }

    public function getNotes()
    {
        return $this->notes;
    }

    public function addException($exception)
    {
        $this->exceptions[] = $this->enforceUnicode($exception);
        return $this;
    }

    public function getExceptions()
    {
        return $this->exceptions;
    }

    public function addSagepayDetails()
    {
        $this->details = new Sagepay();
        return $this->details;
    }

    public function addDefaultDetails()
    {
        $this->details = new DefaultDetails();
        return $this->details;
    }

    public function addInfusionSoftDetails()
    {
        $this->details = new InfusionSoft();
        return $this->details;
    }

    public function addPaypalDetails()
    {
        $this->details = new Paypal();
        return $this->details;
    }

    public function addEpdqDetails()
    {
        $this->details = new Epdq();
        return $this->details;
    }

    public function addWorldPayDetails()
    {
        $this->details = new WorldPay();
        return $this->details;
    }

    public function toArray()
    {
        $data = \get_object_vars($this);
        if ($this->details !== null) {
            $data['details'] = $this->details->toArray();
        }
        return $data;
    }
}
