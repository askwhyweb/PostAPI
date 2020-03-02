<?php

namespace OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails;

class Sagepay extends AbstractPaymentDetails
{
    private $authCode;
    private $avsCv2Check;
    private $cv2Result;
    private $addressResult;
    private $postcodeResult;
    private $threeDSecureStatus;

    public function setAuthCode($authCode)
    {
        $this->authCode = $this->enforceUnicode($authCode);
        return $this;
    }

    public function getAuthCode()
    {
        return $this->authCode;
    }

    public function setAvsCv2Check($avsCv2Check)
    {
        $this->avsCv2Check = $this->enforceUnicode($avsCv2Check);
        return $this;
    }

    public function getAvsCv2Check()
    {
        return $this->avsCv2Check;
    }

    public function setCv2Result($cv2Result)
    {
        $this->cv2Result = $this->enforceUnicode($cv2Result);
        return $this;
    }

    public function getCv2Result()
    {
        return $this->cv2Result;
    }

    public function setAddressResult($addressResult)
    {
        $this->addressResult = $this->enforceUnicode($addressResult);
        return $this;
    }

    public function getAddressResult()
    {
        return $this->addressResult;
    }

    public function setPostcodeResult($postcodeResult)
    {
        $this->postcodeResult = $this->enforceUnicode($postcodeResult);
        return $this;
    }

    public function getPostcodeResult()
    {
        return $this->postcodeResult;
    }

    public function setThreeDSecureStatus($threeDSecureStatus)
    {
        $this->threeDSecureStatus = $this->enforceUnicode($threeDSecureStatus);
        return $this;
    }

    public function getThreedSecureStatus()
    {
        return $this->threeDSecureStatus;
    }

    public function toArray()
    {
        return \get_object_vars($this);
    }
}
