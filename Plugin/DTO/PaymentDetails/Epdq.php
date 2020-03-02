<?php

namespace OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails;

class Epdq extends AbstractPaymentDetails
{
    private $ccBrand;
    private $aavCheck;
    private $cvcCheck;

    public function setCcBrand($ccBrand)
    {
        $this->ccBrand = $this->enforceUnicode($ccBrand);
        return $this;
    }

    public function getCcBrand()
    {
        return $this->ccBrand;
    }

    public function setAavCheck($aavCheck)
    {
        $this->aavCheck = $this->enforceUnicode($aavCheck);
        return $this;
    }

    public function getAavCheck()
    {
        return $this->aavCheck;
    }

    public function setCvcCheck($cvcCheck)
    {
        $this->cvcCheck = $this->enforceUnicode($cvcCheck);
        return $this;
    }

    public function getCvcCheck()
    {
        return $this->cvcCheck;
    }

    public function toArray()
    {
        return \get_object_vars($this);
    }
}
