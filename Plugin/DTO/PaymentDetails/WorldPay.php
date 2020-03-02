<?php

namespace OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails;

class WorldPay extends AbstractPaymentDetails
{
    private $payerName;
    private $payerEmailAddress;
    private $message;
    private $countryMatch;
    private $avs;
    private $rawAuthCode;
    private $authMode;

    public function setPayerName($payerName)
    {
        $this->payerName = $this->enforceUnicode($payerName);
        return $this;
    }

    public function getPayerName()
    {
        return $this->payerName;
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

    public function setMessage($message)
    {
        $this->message = $this->enforceUnicode($message);
        return $this;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setCountryMatch($countryMatch)
    {
        $this->countryMatch = $this->enforceUnicode($countryMatch);
        return $this;
    }

    public function getCountryMatch()
    {
        return $this->countryMatch;
    }

    public function setAvs($avs)
    {
        $this->avs = $this->enforceUnicode($avs);
        return $this;
    }

    public function getAvs()
    {
        return $this->avs;
    }

    public function setRawAuthCode($rawAuthCode)
    {
        $this->rawAuthCode = $this->enforceUnicode($rawAuthCode);
        return $this;
    }

    public function getRawAuthCode()
    {
        return $this->rawAuthCode;
    }

    public function setAuthmode($authMode)
    {
        $this->authMode = $this->enforceUnicode($authMode);
        return $this;
    }

    public function getAuthMode()
    {
        return $this->authMode;
    }

    public function toArray()
    {
        return \get_object_vars($this);
    }
}
