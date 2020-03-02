<?php

namespace OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails;

class DefaultDetails extends AbstractPaymentDetails
{
    private $values = [];

    public function setValue($key, $value)
    {
        $this->values[$key] = $this->enforceUnicode($value);
        return $this;
    }

    public function getValue($key)
    {
        if (!isset($this->values[$key])) {
            return null;
        }
        return $this->values[$key];
    }

    public function toArray()
    {
        $ret = \get_object_vars($this);
        unset($ret['values']);
        foreach ($this->values as $key => $value) {
            $ret[$key] = $value;
        }
        return $ret;
    }
}
